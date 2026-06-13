<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_api_returns_correct_statistics()
    {
        // 1. Create Channels
        $telegram = Channel::create(['name' => 'Telegram', 'type' => 'telegram']);
        $whatsapp = Channel::create(['name' => 'WhatsApp', 'type' => 'whatsapp']);
        $shopee = Channel::create(['name' => 'Shopee', 'type' => 'shopee']);

        // 2. Create Customers
        $c1 = Customer::create(['name' => 'Cust 1', 'external_id' => '1', 'channel_id' => $telegram->id]);
        $c2 = Customer::create(['name' => 'Cust 2', 'external_id' => '2', 'channel_id' => $whatsapp->id]);
        $c3 = Customer::create(['name' => 'Cust 3', 'external_id' => '3', 'channel_id' => $shopee->id]);
        $c4 = Customer::create(['name' => 'Cust 4', 'external_id' => '4', 'channel_id' => $telegram->id]);

        // 3. Create Conversations
        // 2 Telegram conversations: 1 waiting_admin, 1 closed
        $conv1 = Conversation::create([
            'customer_id' => $c1->id,
            'channel_id' => $telegram->id,
            'status' => 'waiting_admin',
            'unread_count' => 1
        ]);
        $conv2 = Conversation::create([
            'customer_id' => $c4->id,
            'channel_id' => $telegram->id,
            'status' => 'closed',
            'unread_count' => 0
        ]);

        // 1 WhatsApp conversation: open (which maps to waiting_customer in stats)
        $conv3 = Conversation::create([
            'customer_id' => $c2->id,
            'channel_id' => $whatsapp->id,
            'status' => 'open',
            'unread_count' => 0
        ]);

        // 1 Shopee conversation: waiting_customer
        $conv4 = Conversation::create([
            'customer_id' => $c3->id,
            'channel_id' => $shopee->id,
            'status' => 'waiting_customer',
            'unread_count' => 0
        ]);

        // 4. Create Messages
        $conv1->messages()->create(['sender_type' => 'customer', 'message' => 'Hello TG 1', 'message_type' => 'text']);
        $conv1->messages()->create(['sender_type' => 'admin', 'message' => 'Hi Cust 1', 'message_type' => 'text']);
        $conv2->messages()->create(['sender_type' => 'customer', 'message' => 'Hello TG 2', 'message_type' => 'text']);
        $conv3->messages()->create(['sender_type' => 'customer', 'message' => 'Hello WA', 'message_type' => 'text']);

        // 5. Query Endpoint
        $response = $this->getJson('/api/analytics');

        // 6. Assertions
        $response->assertStatus(200);
        $response->assertJson([
            'total_conversations' => 4,
            'total_messages' => 4,
            'by_channel' => [
                'Telegram' => 2,
                'WhatsApp' => 1,
                'Shopee' => 1,
            ],
            'by_status' => [
                'waiting_admin' => 1,
                'waiting_customer' => 2, // conv3 (open) + conv4 (waiting_customer)
                'closed' => 1
            ]
        ]);
    }
}
