<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
            
            // Ensure media directory exists
            if (!Storage::disk('public')->exists('media')) {
                Storage::disk('public')->makeDirectory('media');
            }

            $path = $file->storeAs('media', $filename, 'public');
            $url = asset('storage/media/' . $filename);

            return response()->json([
                'success' => true,
                'url' => $url,
                'markdown' => "![]($url)"
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Upload failed'], 400);
    }
}
