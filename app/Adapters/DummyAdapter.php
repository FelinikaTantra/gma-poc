<?php

namespace App\Adapters;

use Illuminate\Http\Request;

class DummyAdapter implements ChannelInterface
{
    public function parseWebhook(Request $request): ?array
    {
        return null;
    }

    public function sendReply(string $externalId, string $message, array $config = []): bool
    {
        // Do nothing for UI simulator
        \Log::info("DummyAdapter sendReply to $externalId: $message");
        return true;
    }
}
