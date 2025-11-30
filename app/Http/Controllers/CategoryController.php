<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return category::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string',
            'description' => 'nullable|string',
            'icon' => 'nullable|file|mimes:jpg,jpeg,png,svg,webp|max:2048',
        ]);
        if ($request->hasFile('icon')) {
            $path = $request->file('icon')->store('uploads', 'public');
            $validated['icon'] = $path;
        }
        $Category = category::create($validated);
        return response()->json([
            'message' => 'Category created successfully',
            'Category' => $Category,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
