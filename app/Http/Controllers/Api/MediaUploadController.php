<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:jpg,jpeg,png,gif,webp,mp4,mov,webm,mp3,m4a,wav,ogg,pdf,doc,docx,xls,xlsx', // max 50MB, safe types only
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $sizeBytes = $file->getSize();

        // Determine type: image, video, audio, document
        $type = 'document';
        if (str_starts_with($mimeType, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            $type = 'audio';
        }

        // Format readable size
        $sizeText = round($sizeBytes / (1024 * 1024), 1) . ' MB';
        if ($sizeBytes < 1024 * 1024) {
            $sizeText = round($sizeBytes / 1024, 1) . ' KB';
        }

        $extension = strtolower($file->guessExtension() ?: 'bin'); // never trust the client-supplied extension
        $filename = Str::uuid() . '.' . ($extension ?: 'bin');
        $path = $file->storeAs('uploads', $filename, 'public');

        $url = asset('storage/' . $path);

        // Prefer Cloudinary (persistent) when fully configured; local disk is the fallback.
        $cloud = $this->uploadToCloudinary($file->getRealPath(), $originalName);
        if ($cloud) {
            Storage::disk('public')->delete($path);
            $url = $cloud;
            $path = $cloud;
        }

        return response()->json([
            'type' => $type,
            'url' => $url,
            'name' => $originalName,
            'size' => $sizeText,
            'path' => $path,
        ]);
    }

    /**
     * Signed upload to Cloudinary. Returns the secure URL or null when not configured / failed.
     */
    protected function uploadToCloudinary(string $realPath, string $originalName): ?string
    {
        $cloud = config('services.cloudinary.cloud_name');
        $key = config('services.cloudinary.api_key');
        $secret = config('services.cloudinary.api_secret');

        if (!$cloud || !$key || !$secret) {
            return null;
        }

        $timestamp = time();
        $folder = 'kiloneed/uploads';
        $signature = sha1("folder={$folder}&timestamp={$timestamp}{$secret}");

        try {
            $response = Http::timeout(60)
                ->attach('file', fopen($realPath, 'r'), $originalName)
                ->post("https://api.cloudinary.com/v1_1/{$cloud}/auto/upload", [
                    'api_key' => $key,
                    'timestamp' => $timestamp,
                    'folder' => $folder,
                    'signature' => $signature,
                ]);

            if ($response->successful() && $response->json('secure_url')) {
                return $response->json('secure_url');
            }

            Log::warning('Cloudinary upload rejected: ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning('Cloudinary upload error: ' . $e->getMessage());
        }

        return null;
    }
}