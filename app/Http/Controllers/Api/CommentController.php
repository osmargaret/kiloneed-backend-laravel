<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Need;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(int $needId): JsonResponse
    {
        $comments = Comment::with('user')
            ->where('need_id', $needId)
            ->latest()
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request, int $needId): JsonResponse
    {
        $user = $this->resolveUser($request);
        $need = Need::findOrFail($needId);

        $validated = $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $comment = Comment::create([
            'need_id' => $need->id,
            'user_id' => $user->id,
            'text' => $validated['text'],
        ]);

        $need->increment('responses');

        // Notify need owner if not commenter
        if ($need->user_id !== $user->id) {
            Notification::create([
                'user_id' => $need->user_id,
                'type' => 'comment',
                'title' => "{$user->name} commented on your need",
                'description' => mb_substr($validated['text'], 0, 80),
                'from_user_id' => $user->id,
                'need_id' => $need->id,
            ]);
        }

        $comment->load('user');

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $user = $this->resolveUser($request);

        if ($comment->user_id !== $user->id && $comment->need->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully',
        ]);
    }
}

