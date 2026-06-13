<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\GeminiService;
use Illuminate\Http\Request;

class AIController extends Controller
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function suggestReply(Request $request, $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        
        $lastMessage = $conversation->messages()
            ->where('sender_type', '!=', 'system')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
            
        if (!$lastMessage || $lastMessage->sender_type !== 'customer') {
            return response()->json(['suggestion' => '']);
        }

        $suggestion = $this->gemini->generateReply($conversation);
        return response()->json(['suggestion' => $suggestion]);
    }

    public function generateSummary(Request $request, $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        $force = $request->boolean('force');
        $summary = $this->gemini->generateSummary($conversation, $force);
        return response()->json(['summary' => $summary]);
    }
}
