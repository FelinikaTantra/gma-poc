<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\ChannelSettingsController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\UserRoleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Master Data (Knowledge Base)
Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index']);
Route::post('/knowledge-base', [KnowledgeBaseController::class, 'store']);
Route::delete('/knowledge-base/{id}', [KnowledgeBaseController::class, 'destroy']);

// Channel Settings
Route::get('/settings', [ChannelSettingsController::class, 'index']);
Route::put('/settings/channel/{id}', [ChannelSettingsController::class, 'updateChannel']);
Route::post('/settings/telegram/test', [ChannelSettingsController::class, 'testTelegramConnection']);
Route::post('/settings/telegram/sync', [ChannelSettingsController::class, 'syncTelegramMessages']);
Route::put('/settings/ai-toggle', [ChannelSettingsController::class, 'toggleAi']);
Route::post('/settings/openai/test', [ChannelSettingsController::class, 'testOpenAiConnection']);

// Chat API (Standardized to PRD)
Route::get('/conversations', [ChatController::class, 'inbox']);
Route::get('/conversations/{id}/messages', [ChatController::class, 'show']);
Route::post('/messages/send', [ChatController::class, 'sendMessage']);

// Phase 3 Features
Route::post('/messages/{id}/feedback', [ChatController::class, 'submitFeedback']);
Route::get('/conversations/{id}/notes', [ChatController::class, 'getNotes']);
Route::post('/conversations/{id}/notes', [ChatController::class, 'addNote']);
Route::get('/quick-replies', [ChatController::class, 'getQuickReplies']);

// AI API
Route::get('/conversations/{id}/suggest', [AIController::class, 'suggestReply']);
Route::get('/conversations/{id}/summary', [AIController::class, 'generateSummary']);

// User & Role Management
Route::apiResource('/users', UserRoleController::class)->only(['index', 'store', 'update', 'destroy']);
Route::get('/roles', [UserRoleController::class, 'rolesIndex']);
Route::post('/roles', [UserRoleController::class, 'rolesStore']);
Route::put('/roles/matrix', [UserRoleController::class, 'rolesUpdateMatrix']);

// Webhook
Route::post('/webhooks/telegram', [\App\Http\Controllers\Api\WebhookController::class, 'telegram']);

Route::post('/telegram/webhook', function (Request $request) {
    $channel = \App\Models\Channel::where('type', 'telegram')->first();
    $token = ($channel && isset($channel->config_json['bot_token'])) ? $channel->config_json['bot_token'] : env('TELEGRAM_BOT_TOKEN');

    if (!$request->has('message.chat.id') || !$request->has('message.text')) {
        return response()->json(['status' => 'ignored']);
    }

    $chatId = $request->input('message.chat.id');
    $text = $request->input('message.text');

    if ($text == '/status') {
        Http::post(
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'chat_id' => $chatId,
                'text' => 'System Online'
            ]
        );
    }

    // Process using ConversationEngine to save to DB / chat list
    $adapter = new \App\Adapters\TelegramAdapter();
    $payload = $adapter->parseWebhook($request);
    if ($payload) {
        $engine = app(\App\Engines\ConversationEngine::class);
        $engine->handleIncoming($payload, $adapter);
    }

    return response()->json(['status' => 'ok']);
});

