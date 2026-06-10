<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Adapters\TelegramAdapter;
use App\Engines\ConversationEngine;

class WebhookController extends Controller
{
    protected $engine;

    public function __construct(ConversationEngine $engine)
    {
        $this->engine = $engine;
    }

    public function telegram(Request $request)
    {
        $adapter = new TelegramAdapter();
        $payload = $adapter->parseWebhook($request);

        if ($payload) {
            $this->engine->handleIncoming($payload, $adapter);
        }

        return response()->json(['status' => 'ok']);
    }
}
