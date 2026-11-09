<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $connections = Connection::with(['connectedUser'])
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->get();

        $users = $connections->map(fn ($c) => $c->connectedUser)->filter()->values();

        return response()->json($users);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        // Users who are not currently connected
        $existingIds = Connection::where('user_id', $user->id)->pluck('connected_user_id')->toArray();
        $existingIds[] = $user->id;

        $suggestions = User::whereNotIn('id', $existingIds)
            ->when($user->industry, fn ($q) => $q->orderByRaw("CASE WHEN industry = ? THEN 0 ELSE 1 END", [$user->industry]))
            ->limit(10)
            ->get();

        return response()->json($suggestions);
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $incoming = Connection::with('user')
            ->where('connected_user_id', $user->id)
            ->where('status', 'pending')
            ->get();

        return response()->json($incoming);
    }

    public function sendRequest(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'connected_user_id' => 'required|exists:users,id',
        ]);

        $targetId = (int) $validated['connected_user_id'];
        if ($targetId === $user->id) {
            return response()->json(['message' => 'Cannot connect with yourself'], 422);
        }

        $connection = Connection::updateOrCreate(
            ['user_id' => $user->id, 'connected_user_id' => $targetId],
            ['status' => 'pending']
        );

        Notification::create([
            'user_id' => $targetId,
            'type' => 'connection',
            'title' => "{$user->name} sent you a connection request",
            'description' => "Review and grow your professional trade network.",
            'from_user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Connection request sent',
            'connection' => $connection,
        ]);
    }

    public function acceptRequest(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);

        $connection = Connection::where('id', $id)
            ->where('connected_user_id', $user->id)
            ->firstOrFail();

        $connection->update(['status' => 'accepted']);

        // Create mutual reverse record
        Connection::updateOrCreate(
            ['user_id' => $user->id, 'connected_user_id' => $connection->user_id],
            ['status' => 'accepted']
        );

        Notification::create([
            'user_id' => $connection->user_id,
            'type' => 'connection',
            'title' => "{$user->name} accepted your connection request",
            'description' => "You can now share private needs and collaborate.",
            'from_user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Connection accepted',
        ]);
    }

    public function declineRequest(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);

        $connection = Connection::where('id', $id)
            ->where('connected_user_id', $user->id)
            ->firstOrFail();

        $connection->delete();

        return response()->json([
            'message' => 'Connection request declined',
        ]);
    }

    public function removeConnection(Request $request, int $targetUserId): JsonResponse
    {
        $user = $this->resolveUser($request);

        Connection::where(function ($q) use ($user, $targetUserId) {
            $q->where('user_id', $user->id)->where('connected_user_id', $targetUserId);
        })->orWhere(function ($q) use ($user, $targetUserId) {
            $q->where('user_id', $targetUserId)->where('connected_user_id', $user->id);
        })->delete();

        return response()->json([
            'message' => 'Connection removed',
        ]);
    }
}

