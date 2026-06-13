<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiConnectionTest extends TestCase
{
    public function test_openai_connection_requires_a_token()
    {
        $response = $this->postJson('/api/settings/openai/test', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['openai_token']);
    }

    public function test_openai_connection_valid_token()
    {
        Http::fake([
            'api.openai.com/v1/models' => Http::response(['data' => []], 200),
        ]);

        $response = $this->postJson('/api/settings/openai/test', [
            'openai_token' => 'valid-mock-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token is valid! Connected to OpenAI successfully.',
            ]);
    }

    public function test_openai_connection_invalid_token()
    {
        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided.',
                    'type' => 'invalid_request_error',
                ]
            ], 401),
        ]);

        $response = $this->postJson('/api/settings/openai/test', [
            'openai_token' => 'invalid-mock-token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid Token. OpenAI responded with status 401: Incorrect API key provided.',
            ]);
    }
}
