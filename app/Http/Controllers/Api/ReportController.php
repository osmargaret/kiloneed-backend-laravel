<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $validated = $request->validate([
            'target_type' => 'required|in:need,user,comment',
            'target_id' => 'required|integer',
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string',
        ]);

        $report = Report::create([
            'reporter_id' => $user->id,
            'target_type' => $validated['target_type'],
            'target_id' => $validated['target_id'],
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Report submitted successfully. Our trust and safety team will review it.',
            'report' => $report,
        ], 201);
    }
}

