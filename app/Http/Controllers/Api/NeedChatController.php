<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Need;
use App\Models\NeedChat;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NeedChatController extends Controller
{
    public function index(Request $request, int $needId): JsonResponse
    {
        $user = $this->resolveUser($request);
        $chats = NeedChat::with(['senderUser', 'recipientUser'])
            ->where('need_id', $needId)
            ->oldest()
            ->get();

        $processed = $chats->map(function ($chat) use ($user) {
            $chat->isMine = $user ? ($chat->sender_id === $user->id) : false;
            return $chat;
        });

        return response()->json($processed);
    }

    public function store(Request $request, int $needId): JsonResponse
    {
        $user = $this->resolveUser($request);
        $need = Need::findOrFail($needId);

        $validated = $request->validate([
            'text' => 'required|string',
            'recipient_id' => 'nullable|exists:users,id',
        ]);

        $recipientId = $validated['recipient_id'] ?? ($need->user_id === $user->id ? null : $need->user_id);

        $chat = NeedChat::create([
            'need_id' => $need->id,
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'text' => $validated['text'],
            'is_read' => false,
        ]);

        $need->increment('responses');

        // Send notification to recipient
        if ($recipientId && $recipientId !== $user->id) {
            Notification::create([
                'user_id' => $recipientId,
                'type' => 'response',
                'title' => "New response from {$user->name}",
                'description' => mb_substr($validated['text'], 0, 80),
                'from_user_id' => $user->id,
                'need_id' => $need->id,
            ]);
        }

        $chat->load(['senderUser', 'recipientUser']);
        $chat->isMine = true;

        return response()->json([
            'message' => 'Chat message sent',
            'chat' => $chat,
        ], 201);
    }
}

