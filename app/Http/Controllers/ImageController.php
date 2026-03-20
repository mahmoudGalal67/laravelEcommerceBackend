<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    // ✅ Upload Image
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:2048', // 2MB
        ]);

        $path = $request->file('file')->store('uploads', 'public');

        return response()->json([
            'url' => Storage::url($path), // /storage/uploads/xxx.jpg
        ]);
    }

    // ✅ Delete Image
    public function delete(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
        ]);

        // Convert URL → storage path
        $path = str_replace('/storage/', '', $request->url);

        Storage::disk('public')->delete($path);

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }
}