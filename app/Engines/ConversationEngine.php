<?php

namespace App\Engines;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\AiSetting;
use App\Services\GeminiService;

class ConversationEngine
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function handleIncoming(array $standardPayload, \App\Adapters\ChannelInterface $adapter)
    {
        // 1. Resolve Channel
        $channel = Channel::firstOrCreate(
            ['type' => $standardPayload['channel_type']],
            ['name' => ucfirst($standardPayload['channel_type']), 'status' => 'Connected']
        );

        // 2. Resolve Customer
        $customer = Customer::firstOrCreate(
            ['external_id' => $standardPayload['external_id'], 'channel_id' => $channel->id],
            ['name' => $standardPayload['customer_name'], 'username' => $standardPayload['username'] ?? null, 'company_id' => $channel->company_id]
        );

        // 3. Resolve Conversation
        // Rule 3: Satu customer hanya boleh memiliki 1 Active Conversation (status != closed)
        $conversation = Conversation::where('customer_id', $customer->id)
            ->where('channel_id', $channel->id)
            ->whereIn('status', ['open', 'waiting_admin', 'waiting_customer'])
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id, 
                'channel_id' => $channel->id, 
                'status' => 'open',
                'unread_count' => 0
            ]);
        }

        // 4. Save Customer Message
        $conversation->messages()->create([
            'sender_type' => 'customer',
            'message_type' => $standardPayload['message_type'],
            'message' => $standardPayload['message']
        ]);

        $conversation->increment('unread_count');
        $conversation->update([
            'status' => 'waiting_admin',
            'last_message_at' => now()
        ]);

        // 5. AI Engine Logic
        $aiSetting = AiSetting::first();
        if ($aiSetting) {
            $aiReplyData = $this->gemini->generateReplyWithConfidence($conversation);
            $confidence = $aiReplyData['confidence'];
            
            if ($aiSetting->full_control) {
                // Directly reply to customer
                $conversation->messages()->create([
                    'sender_type' => 'ai',
                    'message_type' => 'text',
                    'message' => $aiReplyData['reply']
                ]);

                $conversation->update([
                    'status' => 'waiting_customer',
                    'unread_count' => 0
                ]);

                // Send reply to customer via adapter
                $config = $channel->config_json ?? [];
                $adapter->sendReply($customer->external_id, $aiReplyData['reply'], $config);
            } else {
                // Generate Suggestion for 1-Klik Send (Human in the loop)
                if ($confidence >= 50) {
                    $conversation->messages()->create([
                        'sender_type' => 'system',
                        'message' => 'AI prepared a suggestion. Awaiting human review.',
                        'metadata' => [
                            'confidence' => $confidence,
                            'suggestion' => $aiReplyData['reply']
                        ]
                    ]);
                } else {
                    $conversation->messages()->create([
                        'sender_type' => 'system',
                        'message' => 'AI confidence too low. Escalated to human agent.',
                        'metadata' => ['confidence' => $confidence]
                    ]);
                }
            }
        }
    }
}
