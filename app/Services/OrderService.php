<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use Illuminate\Support\Facades\DB;

class OrderService
{
 /**
  * Create Order from Cart
  */
 public function createFromCart(array $data, int $userId): Order
 {
  return DB::transaction(function () use ($data, $userId) {

   // 1. Get cart
   $cart = Cart::with('items.variant.product')
    ->where('user_id', $userId)
    ->firstOrFail();

   // 2. Create main order
   $order = Order::create([
    'user_id'        => $userId,
    'name'           => $data['name'] ?? null,
    'email'          => $data['email'] ?? null,
    'phone'          => $data['phone'],
    'adress'         => $data['address'],
    'city'           => $data['city'],
    'subtotal'       => 0,
    'total_amount'   => 0,
    'status'         => 'pending',
    'payment_status' => 'unpaid',
   ]);

   $orderSubtotal = 0;

   // 3. Group cart items by seller
   $itemsBySeller = $cart->items->groupBy('seller_id');

   foreach ($itemsBySeller as $sellerId => $items) {
    if (!$sellerId) {
     throw new \Exception('Invalid seller_id in cart items');
    }

    $sellerSubtotal = 0;

    // 4. Create seller order
    $sellerOrder = SellerOrder::create([
     'order_id'  => $order->id,
     'seller_id' => $sellerId,
     'subtotal'  => 0,
     'status'    => 'pending',
    ]);

    // 5. Create order items
    foreach ($items as $item) {
     $lineTotal = $item->quantity * $item->variant->price;

     OrderItem::create([
      'order_id'        => $order->id,
      'seller_order_id' => $sellerOrder->id,
      'product_id'      => $item->variant->product_id,
      'variant_id'      => $item->variant->id,
      'unit_price'      => $item->variant->price,
      'quantity'        => $item->quantity,
      'line_total'      => $lineTotal,
     ]);

     $sellerSubtotal += $lineTotal;
    }

    $sellerOrder->update(['subtotal' => $sellerSubtotal]);
    $orderSubtotal += $sellerSubtotal;
   }

   // 6. Update totals
   $order->update([
    'subtotal'     => $orderSubtotal,
    'total_amount' => $orderSubtotal,
   ]);

   // 7. Clear cart
   $cart->items()->delete();

   return $order;
  });
 }
}
