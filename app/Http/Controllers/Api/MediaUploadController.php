<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // max 50MB
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $sizeBytes = $file->getSize();

        // Determine type: image, video, document
        $type = 'document';
        if (str_starts_with($mimeType, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        }

        // Format readable size
        $sizeText = round($sizeBytes / (1024 * 1024), 1) . ' MB';
        if ($sizeBytes < 1024 * 1024) {
            $sizeText = round($sizeBytes / 1024, 1) . ' KB';
        }

        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . ($extension ?: 'bin');
        $path = $file->storeAs('uploads', $filename, 'public');

        $url = asset('storage/' . $path);

        return response()->json([
            'type' => $type,
            'url' => $url,
            'name' => $originalName,
            'size' => $sizeText,
            'path' => $path,
        ]);
    }
}

