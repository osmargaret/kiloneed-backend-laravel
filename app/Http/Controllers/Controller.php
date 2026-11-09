<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve authenticated Sanctum user or fallback to default seeded user for frictionless UX
     */
    protected function resolveUser(Request $request): User
    {
        return $request->user('sanctum')
            ?? $request->user()
            ?? User::where('email', 'user@kiloneed.com')->first()
            ?? User::first()
            ?? User::factory()->create();
    }
}
