<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\AiSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiLeadTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AiSetting::firstOrCreate([
            'id' => 1
        ], [
            'full_control' => false,
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7
        ]);
    }

    public function test_can_save_kpi_lead_times_in_settings()
    {
        $response = $this->putJson('/api/settings/ai-toggle', [
            'full_control' => false,
            'kpi_lead_time_ai' => 25,
            'kpi_lead_time_manual' => 180
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('ai_settings', [
            'kpi_lead_time_ai' => 25,
            'kpi_lead_time_manual' => 180
        ]);
    }

    public function test_analytics_computes_average_lead_times_correctly()
    {
        $channel = Channel::create(['name' => 'Telegram', 'type' => 'telegram']);
        $customer = Customer::create(['name' => 'Test Cust', 'external_id' => '123', 'channel_id' => $channel->id]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'status' => 'waiting_customer'
        ]);

        $t0 = now();
        $t1 = $t0->copy()->addSeconds(10);
        $t2 = $t0->copy()->addSeconds(20);
        $t3 = $t0->copy()->addSeconds(60);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'message' => 'hello',
            'created_at' => $t0
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'ai',
            'message' => 'hello back',
            'created_at' => $t1
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'message' => 'query',
            'created_at' => $t2
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'message' => 'admin answer',
            'created_at' => $t3
        ]);

        AiSetting::first()->update([
            'kpi_lead_time_ai' => 15,
            'kpi_lead_time_manual' => 300
        ]);

        $response = $this->getJson('/api/analytics');

        $response->assertStatus(200);
        $response->assertJson([
            'avg_lead_time_ai' => 10.0,
            'avg_lead_time_manual' => 40.0,
            'kpi_lead_time_ai' => 15,
            'kpi_lead_time_manual' => 300
        ]);
    }
}
