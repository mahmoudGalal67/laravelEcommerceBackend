<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartService
{
 public function findOrCreateGuestCart(Request $request)
 {
  $token = $request->cookie("guest_token");

  if (!$token) {
   $token = Str::uuid();
  }

  $cart = Cart::firstOrCreate([
   "guest_token" => $token
  ]);

  return [$cart, $token];
 }

 public function findOrCreateUserCart($userId)
 {
  return Cart::firstOrCreate([
   "user_id" => $userId
  ]);
 }

 public function mergeCarts(Cart $guest, Cart $user)
 {
  foreach ($guest->items as $item) {

   $existing = $user->items()->where("variant_id", $item->variant_id)->first();

   if ($existing) {
    $existing->quantity += $item->quantity;
    $existing->save();
    $item->delete();
   } else {
    $item->update(["cart_id" => $user->id]);
   }
  }

  $guest->delete();
 }
}
