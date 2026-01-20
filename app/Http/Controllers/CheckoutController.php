<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email',

            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
        ]);
        DB::beginTransaction();

        try {
            $cart = Cart::with('items.variant.product')
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            // 1. Create main order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'adress' => $validated['address'],
                'city' => $validated['city'],
                'total_amount' => 0,
                'subtotal' => 0,
                'status' => 'pending',
                'payment_status' => 'unpaid'
            ]);

            $total = 0;

            // 2. Group items by vendor
            $grouped = $cart->items->groupBy('seller_id');
            foreach ($grouped as $sellerId => $items) {
                if (!$sellerId) {
                    throw new \Exception('Invalid seller_id in cart items');
                }
                $vendorTotal = 0;

                $sellerOrder = SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'subtotal' => 0,
                    'status' => 'pending'
                ]);

                foreach ($items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'seller_order_id' => $sellerOrder->id,
                        'product_id' => $item->variant->product_id,
                        'variant_id' => $item->variant->id,
                        'unit_price' => $item->variant->price,
                        'quantity' => $item->quantity,
                        'line_total' => $item->quantity * $item->variant->price
                    ]);

                    $vendorTotal += $item->quantity * $item->variant->price;
                }

                $sellerOrder->update(['subtotal' => $vendorTotal]);
                $total += $vendorTotal;
            }

            $order->update(['subtotal' => $total]);

            // 3. Clear cart
            $cart->items()->delete();

            DB::commit();

            return response()->json($order);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
