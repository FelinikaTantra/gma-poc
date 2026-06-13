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
        $aiSetting->update([
            'full_control' => $request->boolean('full_control')
        ]);
        return response()->json($aiSetting);
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
}
