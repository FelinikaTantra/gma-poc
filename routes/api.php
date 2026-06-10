<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\ChannelSettingsController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AIController;

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
Route::put('/settings/ai-toggle', [ChannelSettingsController::class, 'toggleAi']);

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

// Webhook
Route::post('/webhooks/telegram', [\App\Http\Controllers\Api\WebhookController::class, 'telegram']);
