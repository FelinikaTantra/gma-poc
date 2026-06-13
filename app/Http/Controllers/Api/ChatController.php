<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function inbox()
    {
        $conversations = Conversation::with(['customer', 'channel', 'messages' => function($query) {
            $query->orderBy('created_at', 'desc')->limit(1);
        }])->get();

        $inbox = $conversations->map(function($c) {
            $lastMsg = $c->messages->first();
            return [
                'id' => $c->id,
                'customer_name' => $c->customer->name,
                'channel_name' => $c->channel->name,
                'last_message' => $lastMsg ? $lastMsg->message : '',
                'last_activity' => $lastMsg ? $lastMsg->created_at : $c->created_at,
                'last_sender' => $lastMsg ? $lastMsg->sender : '',
                'unread_count' => $c->unread_count,
                'status' => $c->status,
            ];
        })->sortByDesc('last_activity')->values();

        return response()->json($inbox);
    }

    public function show($id)
    {
        $conversation = Conversation::with(['customer', 'channel', 'messages' => function($q) {
            $q->orderBy('created_at', 'asc');
        }])->findOrFail($id);

        return response()->json($conversation);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string',
            'sender' => 'required|in:admin,ai,customer'
        ]);

        $conversation = Conversation::with(['customer', 'channel'])->findOrFail($validated['conversation_id']);

        if ($validated['sender'] === 'customer') {
            // For Demo UI Simulator: Route through Conversation Engine so AI Logic applies
            $engine = app(\App\Engines\ConversationEngine::class);
            $adapter = new \App\Adapters\DummyAdapter();
            
            $payload = [
                'external_id' => $conversation->customer->external_id,
                'customer_name' => $conversation->customer->name,
                'username' => $conversation->customer->username,
                'channel_type' => $conversation->channel->type,
                'message' => $validated['message'],
                'message_type' => 'text'
            ];

            $engine->handleIncoming($payload, $adapter);
            return response()->json(['status' => 'success'], 201);
        }

        $message = $conversation->messages()->create([
            'message' => $validated['message'],
            'sender_type' => $validated['sender'],
            'message_type' => 'text'
        ]);

        if ($validated['sender'] === 'admin') {
            $conversation->update(['status' => 'open', 'last_message_at' => now(), 'unread_count' => 0]);

            // Forward reply to external channel (e.g. Telegram)
            $channel = $conversation->channel;
            if ($channel && $channel->type === 'telegram') {
                $adapter = new \App\Adapters\TelegramAdapter();
                $config = $channel->config_json ?? [];
                $adapter->sendReply($conversation->customer->external_id, $validated['message'], $config);
            }
        }

        return response()->json($message, 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,waiting_admin,waiting_customer,closed'
        ]);

        $conversation = Conversation::findOrFail($id);
        $conversation->update([
            'status' => $validated['status']
        ]);

        return response()->json($conversation);
    }

    public function submitFeedback(Request $request, $id)
    {
        $validated = $request->validate([
            'feedback' => 'required|in:good,bad'
        ]);

        \DB::table('ai_feedbacks')->insert([
            'message_id' => $id,
            'feedback' => $validated['feedback'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    public function getNotes($id)
    {
        $notes = \DB::table('conversation_notes')->where('conversation_id', $id)->orderBy('created_at', 'desc')->get();
        return response()->json($notes);
    }

    public function addNote(Request $request, $id)
    {
        $validated = $request->validate([
            'note' => 'required|string'
        ]);

        $noteId = \DB::table('conversation_notes')->insertGetId([
            'conversation_id' => $id,
            'user_id' => 1, // Mocked Admin
            'note' => $validated['note'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $note = \DB::table('conversation_notes')->where('id', $noteId)->first();
        return response()->json($note, 201);
    }

    public function getAnalytics()
    {
        $channels = \App\Models\Channel::all();
        $conversations = \App\Models\Conversation::with('channel')->get();
        $totalMessages = \App\Models\Message::count();

        $byChannel = [];
        foreach ($channels as $c) {
            $byChannel[$c->name] = 0;
        }

        $byStatus = [
            'waiting_admin' => 0,
            'waiting_customer' => 0,
            'closed' => 0
        ];

        foreach ($conversations as $conv) {
            $channelName = $conv->channel->name ?? 'Unknown';
            if (!isset($byChannel[$channelName])) {
                $byChannel[$channelName] = 0;
            }
            $byChannel[$channelName]++;

            $status = $conv->status;
            if ($status === 'open') {
                $status = 'waiting_customer';
            }
            if (isset($byStatus[$status])) {
                $byStatus[$status]++;
            }
        }

        return response()->json([
            'total_conversations' => $conversations->count(),
            'total_messages' => $totalMessages,
            'by_channel' => $byChannel,
            'by_status' => $byStatus
        ]);
    }

    public function getQuickReplies()
    {
        $replies = \DB::table('quick_replies')->get();
        return response()->json($replies);
    }
}
