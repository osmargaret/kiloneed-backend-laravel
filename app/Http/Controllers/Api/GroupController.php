<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $groups = Group::withCount('members')->latest()->get();

        $processed = $groups->map(function ($g) use ($user) {
            $g->isMember = $user ? GroupMember::where('group_id', $g->id)->where('user_id', $user->id)->exists() : false;
            return $g;
        });

        return response()->json($processed);
    }

    public function myGroups(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $groups = $user->groups()->withCount('members')->get();

        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'category' => 'nullable|string',
            'is_private' => 'nullable|boolean',
            'group_type' => 'nullable|string|in:open,closed,special',
            'monthly_dues' => 'nullable|numeric|min:0',
        ]);

        $gType = $validated['group_type'] ?? (!empty($validated['is_private']) ? 'closed' : 'open');

        $group = Group::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'cover_image' => $validated['cover_image'] ?? null,
            'category' => $validated['category'] ?? 'General',
            'is_private' => $gType !== 'open',
            'group_type' => $gType,
            'monthly_dues' => $gType === 'special' ? ($validated['monthly_dues'] ?? 5000) : 0,
            'treasury_balance' => 0,
            'max_members' => 1000,
        ]);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $group->loadCount('members');
        $group->isMember = true;

        return response()->json([
            'message' => 'Group created successfully',
            'group' => $group,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $group = Group::with(['owner', 'members.user'])->withCount('members')->findOrFail($id);
        return response()->json($group);
    }

    public function join(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $group = Group::findOrFail($id);

        $member = GroupMember::firstOrCreate([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ], [
            'role' => 'member',
        ]);

        return response()->json([
            'message' => 'Joined group successfully',
            'member' => $member,
        ]);
    }

    public function leave(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        GroupMember::where('group_id', $id)->where('user_id', $user->id)->delete();

        return response()->json([
            'message' => 'Left group successfully',
        ]);
    }
}

