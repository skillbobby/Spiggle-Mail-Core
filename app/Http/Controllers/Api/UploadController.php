<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 4 * 1024 * 1024; // 4MB

    public function store(Request $request): JsonResponse
    {
        // 1. Strict authentication (Session user or non-expired Bearer API key — no User::first() fallback)
        $user = CurrentWorkspace::user($request);
        $apiKey = null;

        if (! $user) {
            $bearer = $request->bearerToken();
            if ($bearer) {
                $candidate = ApiKey::where('key_hash', hash('sha256', $bearer))->first();
                if ($candidate && (! $candidate->expires_at || $candidate->expires_at->isFuture())) {
                    $apiKey = $candidate;
                }
            }
        }

        if (! $user && ! $apiKey) {
            return response()->json([
                'error' => [
                    'code' => 'unauthorized',
                    'message' => 'Authentication required to upload assets.',
                ],
            ], 401);
        }

        if ($apiKey && $apiKey->role === 'read_only') {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'API key has read_only access and cannot upload assets.',
                ],
            ], 403);
        }

        // 2. Extract uploaded file (multipart or raw stream)
        $uploadedFile = $request->file('image') ?? $request->file('file');
        $originalName = 'image.png';
        $content = '';

        if ($uploadedFile) {
            if (! $uploadedFile->isValid()) {
                return response()->json([
                    'error' => [
                        'code' => 'upload_failed',
                        'message' => $uploadedFile->getErrorMessage(),
                    ],
                ], 422);
            }

            $size = (int) $uploadedFile->getSize();
            if ($size > self::MAX_BYTES) {
                return response()->json([
                    'error' => [
                        'code' => 'file_too_large',
                        'message' => 'Image exceeds the maximum allowed size of 4 MB.',
                    ],
                ], 422);
            }

            $originalName = $uploadedFile->getClientOriginalName();
            $content = (string) file_get_contents($uploadedFile->getRealPath());
        } else {
            $content = (string) $request->getContent();
            $size = strlen($content);
            if ($size === 0) {
                return response()->json([
                    'error' => [
                        'code' => 'missing_file',
                        'message' => 'Please provide an image file via multipart form or body stream.',
                    ],
                ], 422);
            }

            if ($size > self::MAX_BYTES) {
                return response()->json([
                    'error' => [
                        'code' => 'file_too_large',
                        'message' => 'Image exceeds the maximum allowed size of 4 MB.',
                    ],
                ], 422);
            }

            $originalName = (string) ($request->header('X-File-Name') ?? 'upload.png');
        }

        // 3. Inspect actual file magic bytes via finfo_buffer (never trust client Content-Type header!)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? strtolower((string) finfo_buffer($finfo, $content)) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (! isset(self::ALLOWED_MIMES[$detectedMime])) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_mime_type',
                    'message' => "Unsupported or forged image format '{$detectedMime}'. Supported types: PNG, JPEG, GIF, WebP.",
                ],
            ], 422);
        }

        $extension = self::ALLOWED_MIMES[$detectedMime];
        $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';

        $filename = "{$baseName}-" . Str::lower(Str::random(10)) . ".{$extension}";
        $yearMonth = date('Y/m');
        $storagePath = "uploads/{$yearMonth}/{$filename}";

        Storage::disk('public')->put($storagePath, $content);

        $publicUrl = "/storage/{$storagePath}";
        $cleanAlt = htmlspecialchars(str_replace(['-', '_'], ' ', $baseName), ENT_QUOTES, 'UTF-8');

        return response()->json([
            'url' => $publicUrl,
            'fullUrl' => url($publicUrl),
            'fileName' => $filename,
            'originalName' => $originalName,
            'size' => strlen($content),
            'mimeType' => $detectedMime,
            'markdown' => "![{$cleanAlt}]({$publicUrl})",
            'html' => "<img src=\"{$publicUrl}\" alt=\"{$cleanAlt}\" style=\"max-width: 100%; height: auto; border-radius: 8px; display: block; margin: 16px auto;\" />",
        ], 201);
    }
}
