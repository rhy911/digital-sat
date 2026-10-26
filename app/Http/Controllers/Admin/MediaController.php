<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    private const MEDIA_FILENAME_PATTERN = '/\A[A-Za-z0-9]{20}\.(?:jpe?g|png|gif|webp|svg)\z/i';

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension());
            $realPath = $file->getRealPath();
            $content = file_get_contents($realPath);
            if ($content === false) {
                return response()->json(['success' => false, 'message' => 'Upload failed to read.'], 400);
            }

            if ($ext === 'svg') {
                if (preg_match('/<!(?:DOCTYPE|ENTITY)/i', $content)) {
                    return response()->json(['success' => false, 'message' => 'Invalid SVG: DOCTYPE and ENTITY declarations are not permitted.'], 422);
                }
                $dom = new \DOMDocument();
                $prev = libxml_use_internal_errors(true);
                $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                libxml_clear_errors();
                libxml_use_internal_errors($prev);

                if (! $loaded || ! $dom->documentElement || strtolower($dom->documentElement->tagName) !== 'svg') {
                    return response()->json(['success' => false, 'message' => 'Invalid SVG XML structure.'], 422);
                }
                $canonicalExt = 'svg';
            } else {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->buffer($content);
                $allowedMap = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];
                if (! isset($allowedMap[$detectedMime])) {
                    return response()->json(['success' => false, 'message' => 'Unsupported image format.'], 422);
                }
                $canonicalExt = $allowedMap[$detectedMime];
            }

            $filename = Str::random(20) . '.' . $canonicalExt;
            
            // Ensure media directory exists
            if (!Storage::disk('public')->exists('media')) {
                Storage::disk('public')->makeDirectory('media');
            }

            if (! Storage::disk('public')->put('media/' . $filename, $content)) {
                return response()->json(['success' => false, 'message' => 'Could not save uploaded image.'], 500);
            }

            $url = '/media/' . $filename;

            return response()->json([
                'success' => true,
                'url' => $url,
                'markdown' => "![]($url)"
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Upload failed'], 400);
    }

    public function show(string $filename): BinaryFileResponse
    {
        if (! preg_match(self::MEDIA_FILENAME_PATTERN, $filename)) {
            abort(404);
        }

        $path = 'media/' . $filename;
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        $fullPath = $disk->path($path);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($fullPath) ?: 'application/octet-stream';

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isSvg = $ext === 'svg' || in_array($detectedMime, ['image/svg+xml', 'image/svg'], true);

        if ($isSvg) {
            $sample = file_get_contents($fullPath, false, null, 0, 4096) ?: '';
            if (stripos($sample, '<svg') === false && stripos($sample, '<?xml') === false) {
                abort(404);
            }

            return response()->file($fullPath, [
                'Content-Type' => 'image/svg+xml',
                'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'",
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'public, max-age=0, must-revalidate',
            ]);
        }

        $allowedRasterMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (! in_array($detectedMime, $allowedRasterMimes, true)) {
            abort(404);
        }

        return response()->file($fullPath, [
            'Content-Type' => $detectedMime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
