<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Conversation;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GeminiService::class);

        // Seed settings
        AiSetting::firstOrCreate([
            'id' => 1
        ], [
            'full_control' => false,
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7
        ]);

        $channel = Channel::create(['name' => 'Telegram', 'type' => 'telegram']);
        $customer = Customer::create([
            'external_id' => '12345',
            'channel_id' => $channel->id,
            'name' => 'Test Customer',
            'company_id' => null
        ]);

        $this->conversation = Conversation::create([
            'company_id' => null,
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'status' => 'open'
        ]);
    }

    public function test_fails_when_no_keys_are_configured()
    {
        // Ensure env has no key
        putenv('GEMINI_API_KEY=');

        $result = $this->service->generateReply($this->conversation);
        $this->assertEquals("Maaf, API Key belum dikonfigurasi.", $result);

        $confidenceResult = $this->service->generateReplyWithConfidence($this->conversation);
        $this->assertEquals("Maaf, API Key belum dikonfigurasi.", $confidenceResult['reply']);
    }

    public function test_calls_gemini_when_gemini_key_is_present_and_openai_token_is_missing()
    {
        putenv('GEMINI_API_KEY=mock-gemini-key');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Gemini response text']
                            ]
                        ]
                    ]
                ],
                'usageMetadata' => ['totalTokenCount' => 10]
            ], 200)
        ]);

        $result = $this->service->generateReply($this->conversation);
        $this->assertEquals("Gemini response text", $result);

        // Check request was made to Gemini API
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com');
        });
    }

    public function test_calls_openai_when_openai_token_is_present()
    {
        // Set openai_token in DB
        AiSetting::first()->update(['openai_token' => 'mock-openai-token']);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'OpenAI response text'
                        ]
                    ]
                ],
                'usage' => ['total_tokens' => 20]
            ], 200)
        ]);

        $result = $this->service->generateReply($this->conversation);
        $this->assertEquals("OpenAI response text", $result);

        // Check request was made to OpenAI API with bearer token
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions') &&
                $request->hasHeader('Authorization', 'Bearer mock-openai-token');
        });
    }

    public function test_generate_summary_fails_when_no_keys_are_configured()
    {
        putenv('GEMINI_API_KEY=');
        // Ensure openai_token is null
        AiSetting::first()->update(['openai_token' => null]);

        $result = $this->service->generateSummary($this->conversation);
        $this->assertEquals("API Key belum dikonfigurasi untuk ringkasan.", $result);
    }

    public function test_generate_summary_fails_when_less_than_two_messages()
    {
        putenv('GEMINI_API_KEY=mock-gemini-key');

        // 0 messages
        $result = $this->service->generateSummary($this->conversation);
        $this->assertEquals("Belum ada percakapan yang cukup untuk dirangkum.", $result);

        // 1 message
        $this->conversation->messages()->create([
            'message' => 'Hello',
            'sender_type' => 'customer'
        ]);

        $result = $this->service->generateSummary($this->conversation);
        $this->assertEquals("Belum ada percakapan yang cukup untuk dirangkum.", $result);
    }

    public function test_generate_summary_uses_cache_when_force_is_false()
    {
        putenv('GEMINI_API_KEY=mock-gemini-key');

        // Create 2 messages
        $msg1 = $this->conversation->messages()->create([
            'message' => 'Hello',
            'sender_type' => 'customer',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        $msg2 = $this->conversation->messages()->create([
            'message' => 'I need help',
            'sender_type' => 'customer',
            'created_at' => now()->subMinutes(9),
            'updated_at' => now()->subMinutes(9),
        ]);

        // Fake sequential responses
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => json_encode([
                                        'topic' => 'Help request',
                                        'status' => 'Pending',
                                        'next_action' => 'Reply to customer'
                                    ])]
                                ]
                            ]
                        ]
                    ],
                    'usageMetadata' => ['totalTokenCount' => 10]
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => json_encode([
                                        'topic' => 'New topic',
                                        'status' => 'Resolved',
                                        'next_action' => 'None'
                                    ])]
                                ]
                            ]
                        ]
                    ],
                    'usageMetadata' => ['totalTokenCount' => 10]
                ], 200)
        ]);

        // First generation (uncached)
        $result1 = $this->service->generateSummary($this->conversation, false);
        $this->assertStringContainsString('Topik: Help request', $result1);

        // Verify it was stored in db
        $this->assertDatabaseHas('conversation_summaries', [
            'conversation_id' => $this->conversation->id,
            'summary' => $result1
        ]);

        // Call again with force=false, should return cached (doesn't trigger HTTP request)
        $result2 = $this->service->generateSummary($this->conversation, false);
        $this->assertEquals($result1, $result2);
        $this->assertStringContainsString('Topik: Help request', $result2);
    }

    public function test_generate_summary_bypasses_cache_when_force_is_true()
    {
        putenv('GEMINI_API_KEY=mock-gemini-key');

        // Create 2 messages
        $msg1 = $this->conversation->messages()->create([
            'message' => 'Hello',
            'sender_type' => 'customer',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        $msg2 = $this->conversation->messages()->create([
            'message' => 'I need help',
            'sender_type' => 'customer',
            'created_at' => now()->subMinutes(9),
            'updated_at' => now()->subMinutes(9),
        ]);

        // Fake sequential responses
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => json_encode([
                                        'topic' => 'Help request',
                                        'status' => 'Pending',
                                        'next_action' => 'Reply to customer'
                                    ])]
                                ]
                            ]
                        ]
                    ],
                    'usageMetadata' => ['totalTokenCount' => 10]
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => json_encode([
                                        'topic' => 'New topic',
                                        'status' => 'Resolved',
                                        'next_action' => 'None'
                                    ])]
                                ]
                            ]
                        ]
                    ],
                    'usageMetadata' => ['totalTokenCount' => 10]
                ], 200)
        ]);

        // First generation (uncached)
        $result1 = $this->service->generateSummary($this->conversation, false);
        $this->assertStringContainsString('Topik: Help request', $result1);

        // Call again with force=true, should bypass cache and fetch new (triggers sequence's 2nd response)
        $result2 = $this->service->generateSummary($this->conversation, true);
        $this->assertNotEquals($result1, $result2);
        $this->assertStringContainsString('Topik: New topic', $result2);
    }
}

