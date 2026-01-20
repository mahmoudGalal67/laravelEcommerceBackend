<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List users (with search + pagination)
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $users = User::when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->role, function ($q) use ($request) {
                $q->where('role', $request->role);
            })
            ->latest()
            ->paginate(10);

        return response()->json($users);
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role'     => ['required', Rule::in(['client', 'seller', 'admin'])],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'],
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Show single user
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|min:6',
            'role'     => ['required', Rule::in(['client', 'seller', 'admin'])],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Delete user
     */
    public function destroy(Request $request)
    {
           $data = $request->validate([
        'ids'   => 'required|array|min:1',
        'ids.*' => 'integer|exists:users,id',
    ]);

    // Prevent deleting yourself (optional but recommended)
    if ($request->user()) {
        $data['ids'] = array_diff($data['ids'], [$request->user()->id]);
    }

    $deletedCount = User::whereIn('id', $data['ids'])->delete();

    return response()->json([
        'message' => "{$deletedCount} users deleted successfully",
        'deleted_count' => $deletedCount,
    ]);
    }
}
