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
}
