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

    private function getOpenAiToken()
    {
        $setting = \App\Models\AiSetting::first();
        return $setting ? $setting->openai_token : null;
    }

    public function generateReply(Conversation $conversation)
    {
        $openAiToken = $this->getOpenAiToken();
        $apiKey = $this->getApiKey();

        if (!$openAiToken && !$apiKey) {
            return "Maaf, API Key belum dikonfigurasi.";
        }

        // Priority 1: SOP
        $sop = KnowledgeBase::where('category', 'SOP Customer Service')->get();
        // Priority 2: FAQ
        $faqs = Faq::all();
        // Priority 3: Product Data / Company Profile
        $products = KnowledgeBase::whereIn('category', ['Product Catalog', 'Company Profile'])->get();

        // Get customer's last message as query for RAG
        $lastCustomerMessage = $conversation->messages()
            ->where('sender_type', 'customer')
            ->orderBy('created_at', 'desc')
            ->value('message') ?? '';

        // Add Dynamic Product Data from DB via Vector Search
        $dbProducts = $this->getRelevantProducts($lastCustomerMessage, 3);

        $setting = \App\Models\AiSetting::first();
        $personality = ($setting && $setting->personality) ? $setting->personality : "You are a customer service representative.";
        $briefing = ($setting && $setting->briefing) ? $setting->briefing : "Answer the customer concisely and politely based ONLY on the following prioritized information:";

        $context = "AI Personality: {$personality}\nAI Briefing: {$briefing}\n\n";
        
        $context .= "[PRIORITY 1: SOP]\n";
        foreach ($sop as $item) $context .= "- {$item->title}: {$item->content}\n";
        
        $context .= "\n[PRIORITY 2: FAQ]\n";
        foreach ($faqs as $item) $context .= "Q: {$item->question}\nA: {$item->answer}\n";
        
        $context .= "\n[PRIORITY 3: PRODUCT & COMPANY INFO]\n";
        foreach ($products as $item) $context .= "- {$item->title}: {$item->content}\n";

        $context .= "\n[PRIORITY 4: LIVE PRODUCT CATALOG (PRICE, STOCK, COMPATIBILITY)]\n";
        foreach ($dbProducts as $prod) {
            $compats = $prod->productCompatibilities->map(fn($c) => "{$c->vehicle_brand} {$c->vehicle_model} ({$c->vehicle_year})")->implode(', ');
            $context .= "- {$prod->name} (Rp" . number_format($prod->price, 0, ',', '.') . ") | Stock: {$prod->stock} | Compatibility: {$compats}\n";
        }

        $context .= "\nIf the answer is not in the data above, politely ask the customer to wait for an admin to assist them. DO NOT make up information.\n\n";

        // Priority 5: Chat History
        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();
        $historyText = "[PRIORITY 5: CHAT HISTORY]\n";
        foreach ($messages as $msg) {
            $historyText .= ucfirst($msg->sender_type) . ": " . $msg->message . "\n";
        }
        $historyText .= "Customer Service (You): ";

        $prompt = $context . $historyText;

        if ($openAiToken) {
            return $this->callOpenAi($prompt, $openAiToken, $conversation->id);
        }

        return $this->callGemini($prompt, $apiKey, $conversation->id);
    }

    public function generateReplyWithConfidence(Conversation $conversation)
    {
        $openAiToken = $this->getOpenAiToken();
        $apiKey = $this->getApiKey();

        if (!$openAiToken && !$apiKey) {
            return [
                'reply' => "Maaf, API Key belum dikonfigurasi.",
                'confidence' => 0,
                'source' => null
            ];
        }

        $sop = KnowledgeBase::where('category', 'SOP Customer Service')->get();
        $faqs = Faq::all();
        $kbProducts = KnowledgeBase::whereIn('category', ['Product Catalog', 'Company Profile'])->get();
        
        // Get customer's last message as query for RAG
        $lastCustomerMessage = $conversation->messages()
            ->where('sender_type', 'customer')
            ->orderBy('created_at', 'desc')
            ->value('message') ?? '';

        // Add Dynamic Product Data from DB via Vector Search
        $dbProducts = $this->getRelevantProducts($lastCustomerMessage, 3);

        $setting = \App\Models\AiSetting::first();
        $personality = ($setting && $setting->personality) ? $setting->personality : "You are a customer service representative.";
        $briefing = ($setting && $setting->briefing) ? $setting->briefing : "Answer the customer concisely and politely based ONLY on the following prioritized information:";

        $context = "AI Personality: {$personality}\nAI Briefing: {$briefing}\n\n";
        
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

        if ($openAiToken) {
            $response = $this->callOpenAiJson($prompt, $openAiToken, $conversation->id);
        } else {
            $response = $this->callGeminiJson($prompt, $apiKey, $conversation->id);
        }
        
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

    public function generateSummary(Conversation $conversation, bool $force = false)
    {
        $openAiToken = $this->getOpenAiToken();
        $apiKey = $this->getApiKey();

        if (!$openAiToken && !$apiKey) {
            return "API Key belum dikonfigurasi untuk ringkasan.";
        }

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();
        if ($messages->count() < 2) {
            return "Belum ada percakapan yang cukup untuk dirangkum.";
        }

        // Check cache
        $latestMsg = $messages->last();
        $cached = ConversationSummary::where('conversation_id', $conversation->id)->first();
        
        if (!$force && $cached && $cached->updated_at >= $latestMsg->created_at) {
            return $cached->summary;
        }

        $historyText = "Chat History:\n";
        foreach ($messages as $msg) {
            $historyText .= ucfirst($msg->sender_type) . ": " . $msg->message . "\n";
        }

        $prompt = "Buatlah analisis percakapan singkat dalam bahasa Indonesia format JSON dengan key: 'topic' (string), 'status' (string, misal 'Selesai'/'Menunggu Info'), dan 'next_action' (string, tindakan selanjutnya).\n\n" . $historyText;

        if ($openAiToken) {
            $responseJson = $this->callOpenAiJson($prompt, $openAiToken, $conversation->id);
        } else {
            $responseJson = $this->callGeminiJson($prompt, $apiKey, $conversation->id);
        }
        
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

    private function callOpenAi($prompt, $openAiToken, $conversationId = null)
    {
        $setting = \App\Models\AiSetting::first();
        $model = ($setting && str_contains($setting->model, 'gpt')) ? $setting->model : 'gpt-4o-mini';
        $temperature = $setting ? (float)$setting->temperature : 0.7;
        $tokenUsage = 0;

        try {
            $response = Http::withToken($openAiToken)
                ->post("https://api.openai.com/v1/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => 800,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $responseText = $data['choices'][0]['message']['content'] ?? "Gagal memproses respons AI.";
                $tokenUsage = $data['usage']['total_tokens'] ?? 0;
            } else {
                $body = $response->json();
                $errorMsg = $body['error']['message'] ?? 'Unknown error';
                $responseText = "Maaf, terjadi kesalahan pada layanan AI. OpenAI error: " . $errorMsg;
            }
        } catch (\Exception $e) {
            $responseText = "Maaf, terjadi kesalahan pada layanan AI. Connection error: " . $e->getMessage();
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

    private function callOpenAiJson($prompt, $openAiToken, $conversationId = null)
    {
        $setting = \App\Models\AiSetting::first();
        $model = ($setting && str_contains($setting->model, 'gpt')) ? $setting->model : 'gpt-4o-mini';
        $temperature = $setting ? (float)$setting->temperature : 0.1;
        $tokenUsage = 0;

        try {
            $response = Http::withToken($openAiToken)
                ->post("https://api.openai.com/v1/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => 800,
                    'response_format' => ['type' => 'json_object']
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $responseText = $data['choices'][0]['message']['content'] ?? "{}";
                $tokenUsage = $data['usage']['total_tokens'] ?? 0;
            } else {
                $body = $response->json();
                $errorMsg = $body['error']['message'] ?? 'Unknown error';
                $responseText = json_encode([
                    'reply' => "Maaf, terjadi kesalahan pada layanan AI. OpenAI error: " . $errorMsg,
                    'confidence' => 0,
                    'source_citation' => 'System'
                ]);
            }
        } catch (\Exception $e) {
            $responseText = json_encode([
                'reply' => "Maaf, terjadi kesalahan pada layanan AI. Connection error: " . $e->getMessage(),
                'confidence' => 0,
                'source_citation' => 'System'
            ]);
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

    public function generateEmbedding($text)
    {
        $openAiToken = $this->getOpenAiToken();
        if (!$openAiToken) {
            \Log::warning("OpenAI Token is not configured. Vector embedding skipped.");
            return null;
        }

        try {
            $response = Http::withToken($openAiToken)
                ->post("https://api.openai.com/v1/embeddings", [
                    'model' => 'text-embedding-3-small',
                    'input' => $text
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'][0]['embedding'] ?? null;
            }

            \Log::error("OpenAI Embedding failed: " . json_encode($response->json()));
        } catch (\Exception $e) {
            \Log::error("OpenAI Embedding exception: " . $e->getMessage());
        }

        return null;
    }

    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        
        $count = min(count($vecA), count($vecB));
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }
        
        if ($normA == 0 || $normB == 0) {
            return 0;
        }
        
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    public function getRelevantProducts(string $queryText, int $limit = 5): array
    {
        if (empty(trim($queryText))) {
            return \App\Models\Product::with('productCompatibilities')->take($limit)->get()->all();
        }

        $queryVector = $this->generateEmbedding($queryText);
        if (!$queryVector) {
            return \App\Models\Product::with('productCompatibilities')->take($limit)->get()->all();
        }

        $products = \App\Models\Product::with('productCompatibilities')
            ->whereNotNull('vector')
            ->get();

        $scoredProducts = [];
        foreach ($products as $product) {
            $productVector = $product->vector;
            if (is_array($productVector) && count($productVector) > 0) {
                $similarity = $this->cosineSimilarity($queryVector, $productVector);
                $scoredProducts[] = [
                    'product' => $product,
                    'similarity' => $similarity
                ];
            }
        }

        usort($scoredProducts, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        $results = [];
        foreach ($scoredProducts as $item) {
            if ($item['similarity'] >= 0.35) {
                $results[] = $item['product'];
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        if (empty($results) && !empty($scoredProducts)) {
            $results[] = $scoredProducts[0]['product'];
        }

        if (empty($results)) {
            return \App\Models\Product::with('productCompatibilities')->take($limit)->get()->all();
        }

        return $results;
    }
}
