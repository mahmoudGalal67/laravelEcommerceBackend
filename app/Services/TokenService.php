<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use App\Models\RefreshToken;
use Carbon\Carbon;

class TokenService
{
 public static function createAccessToken($user)
 {
  $payload = [
   'sub' => $user->id,
   'email' => $user->email,
   'iat' => time(),
   'exp' => time() + env('JWT_TTL', 300),
  ];

  return JWT::encode($payload, env('JWT_SECRET'), 'HS256');
 }

 public static function createRefreshToken($user)
 {
  $plain = Str::random(64);
  $hash = hash('sha256', $plain);
  $expiresAt = Carbon::now()->addSeconds((int) env('REFRESH_TTL', 1209600));

  RefreshToken::create([
   'user_id' => $user->id,
   'token_hash' => $hash,
   'expires_at' => $expiresAt,
  ]);

  return ['plain' => $plain, 'expires_at' => $expiresAt];
 }

 public static function validateAccessToken($token)
 {
  try {
   return JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
  } catch (\Exception $e) {
   return null;
  }
 }
}
