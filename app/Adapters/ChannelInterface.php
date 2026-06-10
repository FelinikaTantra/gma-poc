<?php

namespace App\Adapters;

use Illuminate\Http\Request;

interface ChannelInterface
{
    /**
     * Parse incoming webhook payload into a standardized format.
     */
    public function parseWebhook(Request $request): ?array;

    /**
     * Send a standardized reply back to the specific channel.
     */
    public function sendReply(string $externalId, string $message, array $config = []): bool;
}
