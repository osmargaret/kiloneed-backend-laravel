<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Need;
use App\Models\NeedMedia;
use App\Models\Notification;
use App\Models\Recommendation;
use App\Models\Repost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NeedController extends Controller
{
    /**
     * Helper to compute haversine distance in kilometers between two lat/lng coords
     */
    private function calculateDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 1);
    }

    public function index(Request $request): JsonResponse
    {
        $currentUser = $this->resolveUser($request);
        $userLat = $request->query('lat', $currentUser?->latitude ?? 6.5966);
        $userLon = $request->query('lon', $currentUser?->longitude ?? 3.3421);
        $maxRadiusKm = (float) $request->query('radius_km', 50); // Default 50km geofence filter
        $category = $request->query('category');
        $privacy = $request->query('privacy');
        $search = $request->query('search');
        $sort = $request->query('sort', 'oldest'); // 'oldest', 'newest', 'distance'
        $page = (int) $request->query('page', 1);
        $pageSize = (int) $request->query('page_size', 10);

        $query = Need::with(['user', 'media', 'comments', 'chats'])
            ->where('status', 'active');

        // Exclude expired needs if not explicitly requested
        if (!$request->boolean('include_expired')) {
            $query->where('expires_at', '>', Carbon::now());
        }

        // Privacy filter
        if ($privacy && $privacy !== 'all') {
            $query->where('privacy', $privacy);
        }

        // Category filter
        if ($category && $category !== 'All') {
            $query->whereJsonContains('categories', $category);
        }

        // Search in title, text, location, or user name
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('text', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('media', function ($mq) use ($search) {
                        $mq->where('alt', 'like', "%{$search}%");
                    });
            });
        }

        $allNeeds = $query->get();

        // Calculate distanceKm and check bookmark/repost status for each need
        $userBookmarks = $currentUser ? Bookmark::where('user_id', $currentUser->id)->pluck('need_id')->flip() : collect();
        $userReposts = $currentUser ? Repost::where('user_id', $currentUser->id)->pluck('need_id')->flip() : collect();

        $processed = $allNeeds->map(function ($need) use ($userLat, $userLon, $userBookmarks, $userReposts) {
            $distance = $this->calculateDistanceKm($userLat, $userLon, (float) $need->latitude, (float) $need->longitude);
            $need->distanceKm = $distance;
            $need->createdAt = $need->created_at ? $need->created_at->getTimestampMs() : Carbon::now()->getTimestampMs();
            $need->expiresAt = $need->expires_at ? $need->expires_at->getTimestampMs() : 0;
            $need->isBookmarked = isset($userBookmarks[$need->id]);
            $need->isReposted = isset($userReposts[$need->id]);
            return $need;
        });

        // Apply Geofence filter if requested (default 50km)
        if ($request->has('geofence') && $request->boolean('geofence')) {
            $processed = $processed->filter(fn ($need) => $need->distanceKm <= $maxRadiusKm)->values();
        }

        // Sorting
        if ($sort === 'newest') {
            $processed = $processed->sortByDesc('created_at')->values();
        } elseif ($sort === 'distance') {
            $processed = $processed->sortBy('distanceKm')->values();
        } else {
            // Default: oldest to newest
            $processed = $processed->sortBy('created_at')->values();
        }

        $totalCount = $processed->count();
        $paginated = $processed->slice(($page - 1) * $pageSize, $pageSize)->values();

        return response()->json([
            'needs' => $paginated,
            'totalCount' => $totalCount,
            'hasMore' => ($page * $pageSize) < $totalCount,
            'currentPage' => $page,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'text' => 'required|string',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'radius' => 'nullable|string',
            'categories' => 'nullable|array',
            'privacy' => 'nullable|in:public,connections,groups,only_me',
            'expires_in_hours' => 'nullable|integer|min:1|max:720',
            'media' => 'nullable|array',
            'media.*.type' => 'required_with:media|string|in:image,video,document',
            'media.*.url' => 'required_with:media|string',
            'media.*.name' => 'nullable|string',
            'media.*.size' => 'nullable|string',
            'media.*.alt' => 'nullable|string',
        ]);

        $hours = $validated['expires_in_hours'] ?? $request->input('expiresInHours') ?? 24;
        $expiresAt = Carbon::now()->addHours((int) $hours);

        $categories = $validated['categories'] ?? [$user->industry ?: 'General'];
        if (empty($categories)) {
            $categories = ['General'];
        }

        $title = $validated['title'] ?? mb_substr($validated['text'], 0, 50) . '...';

        $need = Need::create([
            'user_id' => $user->id,
            'title' => $title,
            'text' => $validated['text'],
            'location' => $validated['location'] ?? $user->location ?? 'Ikeja, Lagos',
            'latitude' => $validated['latitude'] ?? $user->latitude ?? 6.5966,
            'longitude' => $validated['longitude'] ?? $user->longitude ?? 3.3421,
            'radius' => $validated['radius'] ?? 'Within your location',
            'categories' => $categories,
            'privacy' => $validated['privacy'] ?? 'public',
            'expires_at' => $expiresAt,
            'clicks' => 0,
            'responses' => 0,
            'mentions' => 0,
            'saves_count' => 0,
            'shares_count' => 0,
            'reposts_count' => 0,
            'recos_count' => 0,
            'status' => 'active',
        ]);

        // Attach media
        if (!empty($validated['media'])) {
            foreach ($validated['media'] as $item) {
                NeedMedia::create([
                    'need_id' => $need->id,
                    'type' => $item['type'],
                    'url' => $item['url'],
                    'name' => $item['name'] ?? null,
                    'size' => $item['size'] ?? null,
                    'alt' => $item['alt'] ?? null,
                ]);
            }
        }

        // Increment active_needs_count for user
        $user->increment('active_needs_count');

        $need->load(['user', 'media', 'comments', 'chats']);
        $need->distanceKm = 0;
        $need->createdAt = $need->created_at ? $need->created_at->getTimestampMs() : Carbon::now()->getTimestampMs();
        $need->expiresAt = $need->expires_at ? $need->expires_at->getTimestampMs() : 0;
        $need->isBookmarked = false;
        $need->isReposted = false;

        return response()->json([
            'message' => 'Need posted successfully',
            'need' => $need,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $need = Need::with(['user', 'media', 'comments', 'chats'])->findOrFail($id);
        $need->distanceKm = 2.5;
        $need->createdAt = $need->created_at ? $need->created_at->getTimestampMs() : Carbon::now()->getTimestampMs();
        $need->expiresAt = $need->expires_at ? $need->expires_at->getTimestampMs() : 0;
        return response()->json($need);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $need = Need::findOrFail($id);
        $user = $this->resolveUser($request);

        if ($need->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'text' => 'sometimes|required|string',
            'location' => 'nullable|string',
            'radius' => 'nullable|string',
            'categories' => 'nullable|array',
            'privacy' => 'nullable|in:public,connections,groups,only_me',
            'status' => 'nullable|in:active,fulfilled,expired,archived',
        ]);

        $need->update($validated);
        $need->load(['user', 'media', 'comments', 'chats']);

        return response()->json([
            'message' => 'Need updated successfully',
            'need' => $need,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $need = Need::findOrFail($id);
        $user = $this->resolveUser($request);

        if ($need->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $need->delete();
        $user->decrement('active_needs_count');

        return response()->json([
            'message' => 'Need deleted successfully',
        ]);
    }

    public function incrementClick(int $id): JsonResponse
    {
        $need = Need::findOrFail($id);
        $need->increment('clicks');

        return response()->json([
            'clicks' => $need->clicks,
        ]);
    }

    public function share(int $id): JsonResponse
    {
        $need = Need::findOrFail($id);
        $need->increment('shares_count');

        return response()->json([
            'shares' => $need->shares_count,
        ]);
    }

    public function toggleBookmark(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $need = Need::findOrFail($id);

        $existing = Bookmark::where('user_id', $user->id)->where('need_id', $id)->first();
        if ($existing) {
            $existing->delete();
            $need->decrement('saves_count');
            $bookmarked = false;
        } else {
            Bookmark::create([
                'user_id' => $user->id,
                'need_id' => $id,
            ]);
            $need->increment('saves_count');
            $bookmarked = true;
        }

        return response()->json([
            'bookmarked' => $bookmarked,
            'saves' => $need->saves_count,
        ]);
    }

    public function repost(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $need = Need::findOrFail($id);

        $existing = Repost::where('user_id', $user->id)->where('need_id', $id)->first();
        if ($existing) {
            $existing->delete();
            $need->decrement('reposts_count');
            $reposted = false;
        } else {
            Repost::create([
                'user_id' => $user->id,
                'need_id' => $id,
            ]);
            $need->increment('reposts_count');
            $reposted = true;
        }

        return response()->json([
            'reposted' => $reposted,
            'reposts' => $need->reposts_count,
        ]);
    }

    public function recommend(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $need = Need::findOrFail($id);

        $existing = Recommendation::where('user_id', $user->id)->where('need_id', $id)->first();
        if ($existing) {
            $existing->delete();
            $need->decrement('recos_count');
            $recommended = false;
        } else {
            Recommendation::create([
                'user_id' => $user->id,
                'need_id' => $id,
            ]);
            $need->increment('recos_count');
            $recommended = true;
        }

        return response()->json([
            'recommended' => $recommended,
            'recos' => $need->recos_count,
        ]);
    }

    public function mentionPeers(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $need = Need::findOrFail($id);

        $validated = $request->validate([
            'peers' => 'required|array',
            'peers.*' => 'string',
            'snippet' => 'nullable|string',
        ]);

        $peers = $validated['peers'];
        $snippet = $validated['snippet'] ?? mb_substr($need->text, 0, 60);

        $need->increment('mentions', count($peers));

        // Create notifications for mentioned users
        foreach ($peers as $peerName) {
            $peerUser = User::where('name', $peerName)->first();
            if ($peerUser) {
                Notification::create([
                    'user_id' => $peerUser->id,
                    'type' => 'mention',
                    'title' => "{$user->name} mentioned you to attend to a need",
                    'description' => "Regarding: \"{$snippet}...\"",
                    'detail' => "You were recommended by {$user->name} to attend to this need within your industry. Click to view need details and respond.",
                    'from_user_id' => $user->id,
                    'need_id' => $need->id,
                    'is_read' => false,
                ]);
            }
        }

        return response()->json([
            'message' => 'Peers mentioned successfully',
            'mentions' => $need->mentions,
        ]);
    }
}

