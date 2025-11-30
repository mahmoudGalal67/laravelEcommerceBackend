<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Colors = Color::all();
        return response()->json($Colors);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hex' => 'required|string',
        ]);

        $Color = Color::create($validated);

        return response()->json([
            'message' => 'Color created successfully',
            'Color' => $Color,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $Color = Color::find($id);

        if (!$Color) {
            return response()->json(['message' => 'Color not found'], 404);
        }

        return response()->json($Color);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $Color = Color::find($id);

        if (!$Color) {
            return response()->json(['message' => 'Color not found'], 404);
        }
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'hex' => 'sometimes|required|string|max:255',
        ]);

        $Color->update($validated);

        return response()->json([
            'message' => 'Color updated successfully',
            'Color' => $Color,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $Color = Color::find($id);

        if (!$Color) {
            return response()->json(['message' => 'Color not found'], 404);
        }

        $Color->delete();

        return response()->json(['message' => 'Color deleted successfully']);
    }
}
