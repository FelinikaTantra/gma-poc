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
}
