<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $Sizes = Size::all();
        return response()->json($Sizes);
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

        $Size = Size::create($validated);

        return response()->json([
            'message' => 'Size created successfully',
            'Size' => $Size,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $Size = Size::find($id);

        if (!$Size) {
            return response()->json(['message' => 'Size not found'], 404);
        }

        return response()->json($Size);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $Size = Size::find($id);

        if (!$Size) {
            return response()->json(['message' => 'Size not found'], 404);
        }
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'hex' => 'sometimes|required|string|max:255',
        ]);

        $Size->update($validated);

        return response()->json([
            'message' => 'Size updated successfully',
            'Size' => $Size,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $Size = Size::find($id);

        if (!$Size) {
            return response()->json(['message' => 'Size not found'], 404);
        }

        $Size->delete();

        return response()->json(['message' => 'Size deleted successfully']);
    }
}
