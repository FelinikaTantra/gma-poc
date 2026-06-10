<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use App\Models\Faq;
use App\Models\Conversation;
use App\Models\AiLog;
use App\Models\ConversationSummary;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    private function getApiKey()
    {
        return env('GEMINI_API_KEY');
    }

    public function generateReply(Conversation $conversation)
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) return "Maaf, API Key Gemini belum dikonfigurasi.";

        // Priority 1: SOP
        $sop = KnowledgeBase::where('category', 'SOP Customer Service')->get();
        // Priority 2: FAQ
        $faqs = Faq::all();
        // Priority 3: Product Data / Company Profile
        $products = KnowledgeBase::whereIn('category', ['Product Catalog', 'Company Profile'])->get();

        $context = "You are a customer service representative. Answer the customer concisely and politely based ONLY on the following prioritized information:\n\n";
        
        $context .= "[PRIORITY 1: SOP]\n";
        foreach ($sop as $item) $context .= "- {$item->title}: {$item->content}\n";
        
        $context .= "\n[PRIORITY 2: FAQ]\n";
        foreach ($faqs as $item) $context .= "Q: {$item->question}\nA: {$item->answer}\n";
        
        $context .= "\n[PRIORITY 3: PRODUCT & COMPANY INFO]\n";
        foreach ($products as $item) $context .= "- {$item->title}: {$item->content}\n";

        $context .= "\nIf the answer is not in the data above, politely ask the customer to wait for an admin to assist them. DO NOT make up information.\n\n";

        // Priority 4: Chat History
        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();
        $historyText = "[PRIORITY 4: CHAT HISTORY]\n";
        foreach ($messages as $msg) {
            $historyText .= ucfirst($msg->sender_type) . ": " . $msg->message . "\n";
        }
        $historyText .= "Customer Service (You): ";

        $prompt = $context . $historyText;

        return $this->callGemini($prompt, $apiKey, $conversation->id);
    }

    public function generateReplyWithConfidence(Conversation $conversation)
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) return ['reply' => "Maaf, API Key Gemini belum dikonfigurasi.", 'confidence' => 0, 'source' => null];

        $sop = KnowledgeBase::where('category', 'SOP Customer Service')->get();
        $faqs = Faq::all();
        $kbProducts = KnowledgeBase::whereIn('category', ['Product Catalog', 'Company Profile'])->get();
        
        // Add Dynamic Product Data from DB
        $dbProducts = \App\Models\Product::with('productCompatibilities')->get();

        $context = "You are a customer service representative. Answer the customer concisely and politely based ONLY on the following prioritized information:\n\n";
        
        $context .= "[PRIORITY 1: SOP]\n";
        foreach ($sop as $item) $context .= "- {$item->title}: {$item->content}\n";
        
        $context .= "\n[PRIORITY 2: FAQ]\n";
        foreach ($faqs as $item) $context .= "Q: {$item->question}\nA: {$item->answer}\n";
        
        $context .= "\n[PRIORITY 3: KNOWLEDGE BASE PRODUCTS & COMPANY INFO]\n";
        foreach ($kbProducts as $item) $context .= "- {$item->title}: {$item->content}\n";

        $context .= "\n[PRIORITY 4: LIVE PRODUCT CATALOG (PRICE, STOCK, COMPATIBILITY)]\n";
        foreach ($dbProducts as $prod) {
            $compats = $prod->productCompatibilities->map(fn($c) => "{$c->vehicle_brand} {$c->vehicle_model} ({$c->vehicle_year})")->implode(', ');
            $context .= "- {$prod->name} (Rp" . number_format($prod->price, 0, ',', '.') . ") | Stock: {$prod->stock} | Compatibility: {$compats}\n";
        }

        $context .= "\nRespond in JSON format with three keys: 'reply' (string), 'confidence' (integer from 0 to 100), and 'source_citation' (string). 
If you are fully sure the answer is in the data, confidence should be 90-100. If you are guessing or the info is missing, confidence should be <40.
For 'source_citation', write exactly where you found the answer (e.g., 'Source: FAQ > Delivery' or 'Source: Live Product Catalog > Spion X'). If low confidence, leave empty.\n\n";

        // Smart Memory: Conversation Summary + Last 10 Messages
        $summary = \App\Models\ConversationSummary::where('conversation_id', $conversation->id)->value('summary');
        $historyText = "[PRIORITY 5: CHAT HISTORY]\n";
        if ($summary) {
            $historyText .= "Previous Summary: $summary\n---\n";
        }
        
        $messages = $conversation->messages()->orderBy('created_at', 'desc')->take(10)->get()->reverse();
        foreach ($messages as $msg) {
            $historyText .= ucfirst($msg->sender_type) . ": " . $msg->message . "\n";
        }
        $historyText .= "Customer Service (You): ";

        $prompt = $context . $historyText;

        $response = $this->callGeminiJson($prompt, $apiKey, $conversation->id);
        
        $replyText = $response['reply'] ?? "Maaf, kami sedang mengalami gangguan.";
        if (!empty($response['source_citation'])) {
            $replyText .= "\n\n[" . $response['source_citation'] . "]";
        }

        return [
            'reply' => $replyText,
            'confidence' => $response['confidence'] ?? 0,
            'source' => $response['source_citation'] ?? null
        ];
    }

    public function generateSummary(Conversation $conversation)
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) return "API Key Gemini belum dikonfigurasi untuk ringkasan.";

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();
        if ($messages->count() < 2) {
            return "Belum ada percakapan yang cukup untuk dirangkum.";
        }

        // Check cache
        $latestMsg = $messages->last();
        $cached = ConversationSummary::where('conversation_id', $conversation->id)->first();
        
        if ($cached && $cached->updated_at >= $latestMsg->created_at) {
            return $cached->summary;
        }

        $historyText = "Chat History:\n";
        foreach ($messages as $msg) {
            $historyText .= ucfirst($msg->sender_type) . ": " . $msg->message . "\n";
        }

        $prompt = "Buatlah analisis percakapan singkat dalam bahasa Indonesia format JSON dengan key: 'topic' (string), 'status' (string, misal 'Selesai'/'Menunggu Info'), dan 'next_action' (string, tindakan selanjutnya).\n\n" . $historyText;

        $responseJson = $this->callGeminiJson($prompt, $apiKey, $conversation->id);
        
        $summary = "Topik: " . ($responseJson['topic'] ?? 'N/A') . "\n";
        $summary .= "Status: " . ($responseJson['status'] ?? 'N/A') . "\n";
        $summary .= "Next Action: " . ($responseJson['next_action'] ?? 'N/A');

        ConversationSummary::updateOrCreate(
            ['conversation_id' => $conversation->id],
            ['summary' => $summary]
        );

        return $summary;
    }

    private function callGemini($prompt, $apiKey, $conversationId = null)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key={$apiKey}", [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.5,
                'maxOutputTokens' => 800,
            ]
        ]);

        $responseText = "Maaf, terjadi kesalahan pada layanan AI.";
        $tokenUsage = 0;

        if ($response->successful()) {
            $data = $response->json();
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? "Gagal memproses respons AI.";
            $tokenUsage = $data['usageMetadata']['totalTokenCount'] ?? 0;
        }

        // Write AI Log
        AiLog::create([
            'conversation_id' => $conversationId,
            'prompt' => $prompt,
            'response' => $responseText,
            'token_usage' => $tokenUsage
        ]);

        return $responseText;
    }

    private function callGeminiJson($prompt, $apiKey, $conversationId = null)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key={$apiKey}", [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 800,
                'responseMimeType' => 'application/json'
            ]
        ]);

        $responseText = "{}";
        $tokenUsage = 0;

        if ($response->successful()) {
            $data = $response->json();
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? "{}";
            $tokenUsage = $data['usageMetadata']['totalTokenCount'] ?? 0;
        }

        // Write AI Log
        AiLog::create([
            'conversation_id' => $conversationId,
            'prompt' => $prompt,
            'response' => $responseText,
            'token_usage' => $tokenUsage
        ]);

        return json_decode($responseText, true) ?? [];
    }
}
