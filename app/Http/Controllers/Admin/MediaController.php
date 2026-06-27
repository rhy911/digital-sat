<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    private const MEDIA_FILENAME_PATTERN = '/\A[A-Za-z0-9]{20}\.(?:jpe?g|png|gif|webp|svg)\z/i';

    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            
            // Ensure media directory exists
            if (!Storage::disk('public')->exists('media')) {
                Storage::disk('public')->makeDirectory('media');
            }

            $path = $file->storeAs('media', $filename, 'public');
            if (! $path || ! Storage::disk('public')->exists($path)) {
                return response()->json(['success' => false, 'message' => 'Upload failed'], 500);
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

    public function show(string $filename)
    {
        if (! preg_match(self::MEDIA_FILENAME_PATTERN, $filename)) {
            abort(404);
        }

        $path = 'media/' . $filename;
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        return response()->file($disk->path($path), [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
