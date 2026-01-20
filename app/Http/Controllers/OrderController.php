<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * 🧑 CLIENT – List own orders
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $order = Order::where('id', $id)
            ->with([
                'sellerOrders.items.variant.product',
                'sellerOrders.items.variant.color',
                'sellerOrders.items.variant.size',
                'sellerOrders.seller'
            ])
            ->firstOrFail();

        return response()->json($order);
    }
    public function clientIndex(Request $request)
    {
        $user = $request->user();
        $orders = Order::where('user_id', $user->id)
            ->with([
                'sellerOrders.items.variant.product',
                'sellerOrders.seller'
            ])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * 🏪 SELLER – List seller orders
     */
public function sellerIndex(Request $request)
{
    $user = $request->user();

    $search  = $request->query('search');
    $status  = $request->query('status');
    $filters = $request->query('filters', []);

    $query = SellerOrder::query()
        ->where('seller_id', $user->id)
        ->where('status', '!=', 'cancelled')
        ->with([
            // ✅ CORRECT
            'order:id,name,payment_status,city,email,phone',
            'items.variant.product:id,name'
        ]);

    // 🔍 GLOBAL SEARCH (important fields only)
if ($search !== null && $search !== '') {
    $query->where(function ($q) use ($search) {

        // Search by order name
        $q->whereHas('order', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%");
        });

        // Search by ID only if numeric
        if (is_numeric($search)) {
            $q->orWhere('id', (int) $search);
        }
    });
}


    // 🎯 COLUMN FILTERS
    if ($status!== 'undefined') {
        $query->where('status', $status);
    }

    if (!empty($filters['payment_status'])) {
        $query->whereHas('order', function ($q) use ($filters) {
            $q->where('payment_status', $filters['payment_status']);
        });
    }

    return response()->json(
        $query->latest()->paginate(15)
    );
}


    /**
     * ✏️ Update order / seller order status
     */
    public function updateStatus(Request $request, string $id)
    {
        $user = $request->user();
        $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,completed,cancelled'
        ]);

        // 🏪 Seller updates only their seller order
        if ($user->role === 'seller') {
            $sellerOrder = SellerOrder::where('id', $id)
                ->firstOrFail();

            $sellerOrder->update([
                'status' => $request->status
            ]);

            return response()->json($sellerOrder);
        }

        // 🧑 Admin updates full order
        if ($user->role === 'admin') {
            $order = Order::findOrFail($id);
            $order->update([
                'status' => $request->status
            ]);

            return response()->json($order);
        }

        abort(403);
    }

    public function cancelOrders(Request $request)
{
    $user = $request->user();

    $validated = $request->validate([
        'ids'   => 'required|array|min:1',
        'ids.*' => 'integer|exists:orders,id',
    ]);

    DB::transaction(function () use ($validated, $user) {

        $orders = Order::with('sellerOrders')
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($orders as $order) {

            // 🧑 CLIENT
            if ($user->role === 'client') {

                if ($order->user_id !== $user->id) {
                    abort(403, 'Unauthorized');
                }

                if ($order->status !== 'pending') {
                    abort(422, 'Only pending orders can be cancelled');
                }
            }

            // 🧑 ADMIN passes without restrictions

            // Cancel main order
            $order->update(['status' => 'cancelled']);

            // Cancel all seller orders
            $order->sellerOrders()->update(['status' => 'cancelled']);
        }
    });

    return response()->json([
        'message' => 'Order(s) cancelled successfully'
    ]);
}
public function cancelSellerOrders(Request $request)
{
    $seller = $request->user();

    $validated = $request->validate([
        'ids'   => 'required|array|min:1',
        'ids.*' => 'integer|exists:seller_orders,id',
    ]);

    DB::transaction(function () use ($validated, $seller) {

        $sellerOrders = SellerOrder::with('order')
            ->whereIn('id', $validated['ids'])
            ->where('seller_id', $seller->id)
            ->get();

        if ($sellerOrders->isEmpty()) {
            abort(403, 'No seller orders found');
        }

        foreach ($sellerOrders as $sellerOrder) {

            // Cancel seller order
            $sellerOrder->update(['status' => 'cancelled']);

            $order = $sellerOrder->order;

            // 🔥 Check if other sellerOrders still active
            $hasActiveSellerOrders = $order->sellerOrders()
                ->where('status', '!=', 'cancelled')
                ->exists();

            // If none left → cancel main order
            if (!$hasActiveSellerOrders) {
                $order->update(['status' => 'cancelled']);
            }
        }
    });

    return response()->json([
        'message' => 'Seller order(s) cancelled successfully'
    ]);
}

}
