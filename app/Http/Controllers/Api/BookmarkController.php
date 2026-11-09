<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $bookmarks = Bookmark::with(['need.user', 'need.media'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $formatted = $bookmarks->map(function ($b) {
            $need = $b->need;
            if (!$need) return null;
            return [
                'id' => (string) $need->id,
                'bookmarkId' => $b->id,
                'type' => 'need',
                'title' => $need->title ?: mb_substr($need->text, 0, 50),
                'description' => $need->text,
                'author' => $need->author,
                'location' => $need->location,
                'savedAt' => $b->created_at ? $b->created_at->diffForHumans() : 'Just now',
                'expiresAt' => $need->expiresInText ?: 'Within 24h',
                'need' => $need,
            ];
        })->filter()->values();

        return response()->json($formatted);
    }
}

