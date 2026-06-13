<?php

namespace Tests\Feature;

use App\Engines\ConversationEngine;
use App\Models\AiSetting;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Conversation;
use App\Services\GeminiService;
use App\Adapters\DummyAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConversationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed default settings
        AiSetting::firstOrCreate([
            'id' => 1
        ], [
            'full_control' => false,
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7
        ]);
    }

    public function test_suggested_reply_when_full_control_is_disabled()
    {
        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('generateReplyWithConfidence')
            ->once()
            ->andReturn([
                'reply' => 'Suggested response from AI',
                'confidence' => 85,
                'source' => 'SOP'
            ]);

        $this->app->instance(GeminiService::class, $geminiMock);

        $channel = Channel::create(['name' => 'Telegram', 'type' => 'telegram']);
        $customer = Customer::create([
            'external_id' => '12345',
            'channel_id' => $channel->id,
            'name' => 'Test Customer'
        ]);

        $payload = [
            'channel_type' => 'telegram',
            'external_id' => '12345',
            'customer_name' => 'Test Customer',
            'message' => 'Hello',
            'message_type' => 'text'
        ];

        $adapterMock = Mockery::mock(DummyAdapter::class)->makePartial();
        $adapterMock->shouldNotReceive('sendReply');

        $engine = app(ConversationEngine::class);
        $engine->handleIncoming($payload, $adapterMock);

        // Verify conversation is waiting admin
        $conversation = Conversation::first();
        $this->assertEquals('waiting_admin', $conversation->status);
        $this->assertEquals(1, $conversation->unread_count);

        // Verify system message with suggestion exists
        $this->assertDatabaseHas('messages', [
            'sender_type' => 'system',
            'message' => 'AI prepared a suggestion. Awaiting human review.'
        ]);
    }

    public function test_auto_reply_when_full_control_is_enabled()
    {
        // Enable full control
        AiSetting::first()->update(['full_control' => true]);

        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('generateReplyWithConfidence')
            ->once()
            ->andReturn([
                'reply' => 'Auto response from AI',
                'confidence' => 85,
                'source' => 'SOP'
            ]);

        $this->app->instance(GeminiService::class, $geminiMock);

        $channel = Channel::create(['name' => 'Telegram', 'type' => 'telegram']);
        $customer = Customer::create([
            'external_id' => '12345',
            'channel_id' => $channel->id,
            'name' => 'Test Customer'
        ]);

        $payload = [
            'channel_type' => 'telegram',
            'external_id' => '12345',
            'customer_name' => 'Test Customer',
            'message' => 'Hello',
            'message_type' => 'text'
        ];

        $adapterMock = Mockery::mock(DummyAdapter::class)->makePartial();
        $adapterMock->shouldReceive('sendReply')
            ->once()
            ->with('12345', 'Auto response from AI', [])
            ->andReturn(true);

        $engine = app(ConversationEngine::class);
        $engine->handleIncoming($payload, $adapterMock);

        // Verify conversation is waiting customer and has 0 unread messages (as AI responded)
        $conversation = Conversation::first();
        $this->assertEquals('waiting_customer', $conversation->status);
        $this->assertEquals(0, $conversation->unread_count);

        // Verify AI message was recorded in the database
        $this->assertDatabaseHas('messages', [
            'sender_type' => 'ai',
            'message' => 'Auto response from AI'
        ]);
    }

    public function test_enabling_full_control_triggers_auto_replies_to_outstanding_conversations()
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200)
        ]);

        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('generateReplyWithConfidence')
            ->once()
            ->andReturn([
                'reply' => 'Auto reply from full control activation',
                'confidence' => 95,
                'source' => 'FAQ'
            ]);
        $this->app->instance(GeminiService::class, $geminiMock);

        $channel = Channel::create(['name' => 'Telegram', 'type' => 'telegram']);
        $customer = Customer::create([
            'external_id' => '12345',
            'channel_id' => $channel->id,
            'name' => 'Test Customer'
        ]);

        $conversation = Conversation::create([
            'company_id' => null,
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'status' => 'waiting_admin',
            'unread_count' => 1
        ]);

        $conversation->messages()->create([
            'sender_type' => 'customer',
            'message_type' => 'text',
            'message' => 'Hello last message'
        ]);

        // Hit the API to enable full control
        $response = $this->putJson('/api/settings/ai-toggle', [
            'full_control' => true,
            'openai_token' => null
        ]);

        $response->assertStatus(200);

        // Verify conversation status is now waiting_customer and has 0 unread messages (as AI responded)
        $conversation->refresh();
        $this->assertEquals('waiting_customer', $conversation->status);
        $this->assertEquals(0, $conversation->unread_count);

        // Verify AI message was recorded in the database
        $this->assertDatabaseHas('messages', [
            'sender_type' => 'ai',
            'message' => 'Auto reply from full control activation'
        ]);
    }

    public function test_suggest_reply_returns_suggestion_when_last_sender_is_customer()
    {
        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldReceive('generateReply')
            ->once()
            ->andReturn('Hello human suggestion');
        $this->app->instance(GeminiService::class, $geminiMock);

        $channel = Channel::create(['name' => 'Telegram', 'type' => 'telegram']);
        $customer = Customer::create([
            'external_id' => '12345',
            'channel_id' => $channel->id,
            'name' => 'Test Customer'
        ]);

        $conversation = Conversation::create([
            'company_id' => null,
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'status' => 'waiting_admin',
            'unread_count' => 1
        ]);

        $conversation->messages()->create([
            'sender_type' => 'customer',
            'message_type' => 'text',
            'message' => 'Help me please'
        ]);

        $response = $this->getJson("/api/conversations/{$conversation->id}/suggest");
        $response->assertStatus(200)
            ->assertJson(['suggestion' => 'Hello human suggestion']);
    }

    public function test_suggest_reply_returns_empty_when_last_sender_is_not_customer()
    {
        $geminiMock = Mockery::mock(GeminiService::class);
        $geminiMock->shouldNotReceive('generateReply');
        $this->app->instance(GeminiService::class, $geminiMock);

        $channel = Channel::create(['name' => 'Telegram', 'type' => 'telegram']);
        $customer = Customer::create([
            'external_id' => '12345',
            'channel_id' => $channel->id,
            'name' => 'Test Customer'
        ]);

        $conversation = Conversation::create([
            'company_id' => null,
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'status' => 'waiting_customer',
            'unread_count' => 0
        ]);

        // Last message sender is AI/admin
        $conversation->messages()->create([
            'sender_type' => 'ai',
            'message_type' => 'text',
            'message' => 'Hello AI message'
        ]);

        $response = $this->getJson("/api/conversations/{$conversation->id}/suggest");
        $response->assertStatus(200)
            ->assertJson(['suggestion' => '']);
    }
}
