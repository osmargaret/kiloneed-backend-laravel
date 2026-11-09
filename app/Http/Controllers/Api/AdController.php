<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ads = Ad::with('user')
            ->where('status', 'active')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return response()->json($ads);
    }

    public function myAds(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        $ads = $user->ads()->latest()->get();

        return response()->json($ads);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'ad_type' => 'nullable|string|max:100',
            'placement' => 'nullable|string|max:100',
            'media_url' => 'nullable|string',
            'target_url' => 'nullable|string',
            'budget' => 'required|numeric|min:1000',
        ]);

        $ad = Ad::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'ad_type' => $validated['ad_type'] ?? 'Image & Text',
            'placement' => $validated['placement'] ?? 'Marketplace Featured',
            'media_url' => $validated['media_url'] ?? null,
            'target_url' => $validated['target_url'] ?? null,
            'budget' => $validated['budget'],
            'spent' => 0,
            'impressions' => 0,
            'clicks' => 0,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Sponsored ad campaign created successfully',
            'ad' => $ad,
        ], 201);
    }

    public function trackClick(int $id): JsonResponse
    {
        $ad = Ad::findOrFail($id);
        $ad->increment('clicks');

        return response()->json(['clicks' => $ad->clicks]);
    }
}

