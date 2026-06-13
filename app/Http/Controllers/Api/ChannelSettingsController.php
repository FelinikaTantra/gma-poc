<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\AiSetting;
use Illuminate\Http\Request;

class ChannelSettingsController extends Controller
{
    public function index()
    {
        // Ensure default channels exist
        $defaultChannels = ['WhatsApp', 'Telegram', 'Shopee', 'TikTok', 'Tokopedia'];
        foreach ($defaultChannels as $name) {
            Channel::firstOrCreate(['name' => $name]);
        }

        $channels = Channel::all();
        $aiSetting = AiSetting::firstOrCreate([
            'id' => 1
        ], [
            'full_control' => false,
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7
        ]);

        return response()->json([
            'channels' => $channels,
            'ai_setting' => $aiSetting
        ]);
    }

    public function updateChannel(Request $request, $id)
    {
        $channel = Channel::findOrFail($id);
        $channel->update($request->only(['app_id', 'secret', 'token', 'webhook_url', 'status', 'config_json']));
        return response()->json($channel);
    }

    public function toggleAi(Request $request)
    {
        $aiSetting = AiSetting::first();
        $isEnablingFullControl = $request->boolean('full_control') && (!$aiSetting || !$aiSetting->full_control);

        $data = [
            'full_control' => $request->boolean('full_control'),
            'openai_token' => $request->input('openai_token'),
            'personality' => $request->input('personality'),
            'briefing' => $request->input('briefing'),
            'kpi_lead_time_ai' => $request->input('kpi_lead_time_ai', 15),
            'kpi_lead_time_manual' => $request->input('kpi_lead_time_manual', 300)
        ];

        if (!$aiSetting) {
            $aiSetting = AiSetting::create($data);
        } else {
            $aiSetting->update($data);
        }

        if ($isEnablingFullControl) {
            $this->autoReplyOutstandingConversations();
        }

        return response()->json($aiSetting);
    }

    protected function autoReplyOutstandingConversations()
    {
        $conversations = \App\Models\Conversation::where('status', '!=', 'closed')->get();
        $gemini = app(\App\Services\GeminiService::class);

        foreach ($conversations as $conversation) {
            $lastMessage = $conversation->messages()->orderBy('created_at', 'desc')->first();
            if ($lastMessage && $lastMessage->sender_type === 'customer') {
                $aiReplyData = $gemini->generateReplyWithConfidence($conversation);
                
                $conversation->messages()->create([
                    'sender_type' => 'ai',
                    'message_type' => 'text',
                    'message' => $aiReplyData['reply']
                ]);

                $conversation->update([
                    'status' => 'waiting_customer',
                    'unread_count' => 0
                ]);

                $channel = $conversation->channel;
                if ($channel) {
                    if ($channel->type === 'telegram') {
                        $adapter = new \App\Adapters\TelegramAdapter();
                    } else {
                        $adapter = new \App\Adapters\DummyAdapter();
                    }
                    
                    $config = $channel->config_json ?? [];
                    $adapter->sendReply($conversation->customer->external_id, $aiReplyData['reply'], $config);
                }
            }
        }
    }


    public function testTelegramConnection(Request $request)
    {
        $validated = $request->validate([
            'bot_token' => 'required|string',
        ]);

        $botToken = $validated['bot_token'];

        try {
            $response = \Illuminate\Support\Facades\Http::get("https://api.telegram.org/bot{$botToken}/getMe");

            if ($response->successful() && $response->json('ok')) {
                $result = $response->json('result');
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful!',
                    'bot' => [
                        'username' => '@' . ($result['username'] ?? ''),
                        'first_name' => $result['first_name'] ?? '',
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect. Telegram responded with: ' . ($response->json('description') ?? 'Unknown error')
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testOpenAiConnection(Request $request)
    {
        $validated = $request->validate([
            'openai_token' => 'required|string',
        ]);

        $openaiToken = $validated['openai_token'];

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($openaiToken)
                ->get("https://api.openai.com/v1/models");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Token is valid! Connected to OpenAI successfully.'
                ]);
            }

            $errorMsg = 'Invalid Token. OpenAI responded with status ' . $response->status();
            $body = $response->json();
            if (isset($body['error']['message'])) {
                $errorMsg .= ': ' . $body['error']['message'];
            }

            return response()->json([
                'success' => false,
                'message' => $errorMsg
            ], $response->status() >= 400 && $response->status() < 600 ? $response->status() : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncTelegramMessages(Request $request)
    {
        $channel = Channel::where('type', 'telegram')->first();
        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Telegram channel not configured.'], 404);
        }

        $botToken = $channel->config_json['bot_token'] ?? env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            return response()->json(['success' => false, 'message' => 'Bot token not found.'], 400);
        }

        try {
            // 1. Temporarily delete the webhook so we can call getUpdates
            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/deleteWebhook");

            // 2. Fetch updates via getUpdates
            $response = \Illuminate\Support\Facades\Http::get("https://api.telegram.org/bot{$botToken}/getUpdates");
            $updatesCount = 0;

            if ($response->successful() && $response->json('ok')) {
                $updates = $response->json('result') ?? [];
                $engine = app(\App\Engines\ConversationEngine::class);
                $adapter = new \App\Adapters\TelegramAdapter();

                foreach ($updates as $update) {
                    // Construct a Request object from the update payload
                    $fakeRequest = \Illuminate\Http\Request::create('/api/telegram/webhook', 'POST', $update);
                    $payload = $adapter->parseWebhook($fakeRequest);
                    if ($payload) {
                        $engine->handleIncoming($payload, $adapter);
                        $updatesCount++;
                    }
                }
            }

            // 3. Re-enable the webhook
            $host = $request->getHost();
            // Preserve scheme and port if developing locally, or default to HTTPS for production
            $scheme = $request->isSecure() ? 'https' : 'http';
            $port = $request->getPort();
            $portString = ($port && !in_array($port, [80, 443])) ? ":{$port}" : '';
            
            // Webhook URL must be HTTPS for Telegram, but we preserve local host names for simulation/mock testing
            $webhookUrl = "https://{$host}{$portString}/api/telegram/webhook";

            $certContent = null;
            try {
                $streamContext = @stream_context_create([
                    "ssl" => [
                        "capture_peer_cert" => true,
                        "verify_peer" => false,
                        "verify_peer_name" => false
                    ]
                ]);
                $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $streamContext);
                if ($client) {
                    $params = stream_context_get_params($client);
                    $cert = $params["options"]["ssl"]["capture_peer_cert"] ?? null;
                    if ($cert) {
                        openssl_x509_export($cert, $certContent);
                    }
                }
            } catch (\Exception $e) {
                // Ignore failure to capture cert dynamically (e.g. on localhost)
            }

            if ($certContent) {
                $tempCertFile = tempnam(sys_get_temp_dir(), 'tg_cert_');
                file_put_contents($tempCertFile, $certContent);

                \Illuminate\Support\Facades\Http::attach('certificate', file_get_contents($tempCertFile), 'certificate.pem')
                    ->post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                        'url' => $webhookUrl
                    ]);

                @unlink($tempCertFile);
            } else {
                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                    'url' => $webhookUrl
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$updatesCount} updates.",
                'count' => $updatesCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
