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
        $user = $this->optionalUser($request);

        $groups = Group::with(['owner', 'members.user'])->withCount('members')->latest()->get();

        $processed = $groups->map(function ($g) use ($user) {
            $g->isMember = $user ? GroupMember::where('group_id', $g->id)->where('user_id', $user->id)->exists() : false;
            $g->isAdmin = $user ? ($g->owner_id === $user->id || GroupMember::where('group_id', $g->id)->where('user_id', $user->id)->where('role', 'admin')->exists()) : false;

            $members = $g->members->map(function ($m) {
                return [
                    'id' => $m->user_id,
                    'name' => $m->user ? $m->user->name : 'Member',
                    'avatar' => $m->user && $m->user->avatar ? $m->user->avatar : strtoupper(substr($m->user ? $m->user->name : 'MB', 0, 2)),
                    'role' => $m->role,
                    'joinedAt' => $m->created_at ? $m->created_at->diffForHumans() : 'Recently',
                ];
            });

            if ($g->owner && !$members->contains('id', $g->owner_id)) {
                $members->prepend([
                    'id' => $g->owner_id,
                    'name' => $g->owner->name,
                    'avatar' => $g->owner->avatar ?: strtoupper(substr($g->owner->name, 0, 2)),
                    'role' => 'admin',
                    'joinedAt' => $g->created_at ? $g->created_at->diffForHumans() : 'Owner',
                ]);
            }

            $g->real_members = $members;
            return $g;
        });

        return response()->json($processed);
    }

    public function myGroups(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $joinedGroupIds = GroupMember::where('user_id', $user->id)->pluck('group_id');
        $groups = Group::whereIn('id', $joinedGroupIds)
            ->orWhere('owner_id', $user->id)
            ->with(['owner', 'members.user'])
            ->withCount('members')
            ->latest()
            ->get();

        $processed = $groups->map(function ($g) use ($user) {
            $g->isMember = true;
            $g->isAdmin = $g->owner_id === $user->id || GroupMember::where('group_id', $g->id)->where('user_id', $user->id)->where('role', 'admin')->exists();
            return $g;
        });

        return response()->json($processed);
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

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $group = Group::findOrFail($id);

        $isAdmin = $group->owner_id === $user->id || GroupMember::where('group_id', $group->id)->where('user_id', $user->id)->where('role', 'admin')->exists();
        if (!$isAdmin) {
            return response()->json(['message' => 'Unauthorized to update this group.'], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'category' => 'nullable|string',
            'group_type' => 'nullable|string|in:open,closed,special',
            'monthly_dues' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['name'])) $group->name = $validated['name'];
        if (array_key_exists('description', $validated)) $group->description = $validated['description'];
        if (array_key_exists('icon', $validated)) $group->icon = $validated['icon'];
        if (array_key_exists('cover_image', $validated)) $group->cover_image = $validated['cover_image'];
        if (isset($validated['category'])) $group->category = $validated['category'];
        if (isset($validated['group_type'])) {
            $group->group_type = $validated['group_type'];
            $group->is_private = $validated['group_type'] !== 'open';
        }
        if (isset($validated['monthly_dues'])) $group->monthly_dues = $validated['monthly_dues'];

        $group->save();
        $group->loadCount('members');
        $group->isMember = true;
        $group->isAdmin = true;

        return response()->json([
            'message' => 'Group updated successfully',
            'group' => $group,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $group = Group::findOrFail($id);

        $isAdmin = $group->owner_id === $user->id || GroupMember::where('group_id', $group->id)->where('user_id', $user->id)->where('role', 'admin')->exists();
        if (!$isAdmin) {
            return response()->json(['message' => 'Unauthorized to delete this group.'], 403);
        }

        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully',
        ]);
    }
}

