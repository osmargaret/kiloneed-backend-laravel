<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve the authenticated Sanctum user. Aborts with 401 when the request
     * carries no valid bearer token (no more silent fallback to a default user).
     */
    protected function resolveUser(Request $request): User
    {
        $user = $request->user('sanctum');

        if (!$user instanceof User) {
            abort(response()->json(['message' => 'Unauthenticated.'], 401));
        }

        return $user;
    }

    /**
     * Resolve the authenticated user if a valid token was sent, otherwise null.
     * Used by public endpoints that personalise their response for signed-in users.
     */
    protected function optionalUser(Request $request): ?User
    {
        $user = $request->user('sanctum');

        return $user instanceof User ? $user : null;
    }
}
