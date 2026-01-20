<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Page;
use Illuminate\Support\Str;

class PageController extends Controller
{
    // GET /api/pages
    public function index()
    {
        return Page::all();
    }

    // GET /api/pages/{slug}
    public function show($slug)
    {
        return Page::where('slug', $slug)->firstOrFail();
    }

    // POST /api/pages
        public function store(Request $request)
        {
            $page = Page::where('title', $request->title)->firstOrFail();
            $validated = $request->validate([
                'slug' => 'nullable|unique:pages,slug',
                'title' => 'required',
                'content' => 'required|array',
            ]);
        // Auto-generate slug if null
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }
        
            $page->update($validated);

            return $page;
        }

    // PUT /api/pages/{slug}
    public function update(Request $request, $slug)
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'content' => 'sometimes|array',
        ]);

        $page->update($validated);

        return $page;
    }

    // DELETE /api/pages/{slug}
    public function destroy($slug)
    {
        Page::where('slug', $slug)->delete();
        return response()->noContent();
    }
}