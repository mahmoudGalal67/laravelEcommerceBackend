<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        $auth = $request->header('Authorization');
        if (!$auth || !preg_match('/Bearer (.+)/', $auth, $matches)) {
            return response()->json(['message' => 'Missing token'], 401);
        }

        $decoded = TokenService::validateAccessToken($matches[1]);
        if (!$decoded) return response()->json(['message' => 'Invalid token'], 401);

        $user = User::find($decoded->sub);
        if (!$user) return response()->json(['message' => 'User not found'], 401);

        $request->merge(['user' => $user]);
        return $next($request);
    }
}
