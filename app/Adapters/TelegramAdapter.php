<?php

namespace App\Adapters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramAdapter implements ChannelInterface
{
    public function parseWebhook(Request $request): ?array
    {
        $payload = $request->all();

        if (!isset($payload['message']['text'])) {
            return null; // Only handle text messages for now
        }

        return [
            'channel_type' => 'telegram',
            'external_id' => (string) $payload['message']['chat']['id'],
            'customer_name' => $payload['message']['from']['first_name'] ?? 'Unknown',
            'username' => $payload['message']['from']['username'] ?? null,
            'message' => $payload['message']['text'],
            'message_type' => 'text'
        ];
    }

    public function sendReply(string $externalId, string $message, array $config = []): bool
    {
        $botToken = $config['bot_token'] ?? env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            return false;
        }

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $externalId,
            'text' => $message
        ]);

        return $response->successful();
    }
}
