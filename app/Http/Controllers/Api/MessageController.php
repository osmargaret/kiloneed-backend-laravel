<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $conversations = $user->conversations()
            ->with(['users', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->get();

        $formatted = $conversations->map(function ($c) use ($user) {
            $otherUser = $c->users->firstWhere('id', '!=', $user->id);
            $lastMessage = $c->messages->first();

            return [
                'id' => $c->id,
                'title' => $c->title ?: ($otherUser ? $otherUser->name : 'Conversation'),
                'avatar' => $otherUser?->avatar,
                'otherUser' => $otherUser,
                'lastMessage' => $lastMessage ? $lastMessage->text : null,
                'lastMessageTime' => $lastMessage ? $lastMessage->time : null,
                'unread' => false,
            ];
        });

        return response()->json($formatted);
    }

    public function showConversation(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);

        $conversation = Conversation::with(['users', 'messages.sender'])->findOrFail($id);

        if (!$conversation->users->contains('id', $user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $otherUser = $conversation->users->firstWhere('id', '!=', $user->id);

        $messages = $conversation->messages()->oldest()->get()->map(function ($m) use ($user) {
            $m->isMine = ($m->sender_id === $user->id);
            return $m;
        });

        return response()->json([
            'conversation' => $conversation,
            'otherUser' => $otherUser,
            'messages' => $messages,
        ]);
    }

    public function startOrGetConversation(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id',
        ]);

        $recipientId = (int) $validated['recipient_id'];

        // Find existing conversation between these 2 users
        $conversation = Conversation::where('is_group', false)
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $recipientId))
            ->first();

        if (!$conversation) {
            $recipient = User::findOrFail($recipientId);
            $conversation = Conversation::create([
                'title' => "Chat with {$recipient->name}",
                'is_group' => false,
            ]);
            $conversation->users()->attach([$user->id, $recipientId]);
        }

        return response()->json([
            'conversationId' => $conversation->id,
        ]);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $conversation = Conversation::with('users')->findOrFail($id);

        if (!$conversation->users->contains('id', $user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'text' => 'required|string',
            'media_url' => 'nullable|string',
            'media_type' => 'nullable|string',
        ]);

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'text' => $validated['text'],
            'media_url' => $validated['media_url'] ?? null,
            'media_type' => $validated['media_type'] ?? null,
        ]);

        $otherUser = $conversation->users->firstWhere('id', '!=', $user->id);
        if ($otherUser) {
            Notification::create([
                'user_id' => $otherUser->id,
                'type' => 'message',
                'title' => "New message from {$user->name}",
                'description' => mb_substr($validated['text'], 0, 70),
                'from_user_id' => $user->id,
            ]);
        }

        $msg->load('sender');

        try {
            broadcast(new \App\Events\MessageSent([
                'id' => (string) $msg->id,
                'conversation_id' => (int) $conversation->id,
                'receiver_id' => $otherUser ? (int) $otherUser->id : null,
                'sender' => $user->name,
                'sender_id' => $user->id,
                'sender_avatar' => $user->avatar,
                'text' => $msg->text,
                'time' => 'Just now',
                'media_url' => $msg->media_url,
                'media_type' => $msg->media_type,
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Broadcasting failed: ' . $e->getMessage());
        }

        $msg->isMine = true;

        return response()->json([
            'message' => 'Message sent',
            'data' => $msg,
        ], 201);
    }
}

