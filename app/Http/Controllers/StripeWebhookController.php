<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            // Use env() directly to avoid signature mismatch
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                env('STRIPE_WEBHOOK_SECRET')
            );
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Only handle successful payments
        if ($event->type !== 'payment_intent.succeeded') {
            Log::info('Stripe webhook ignored event', [
                'type' => $event->type,
            ]);
            return response()->json(['status' => 'ignored']);
        }

        $paymentIntent = $event->data->object;
        $orderId = $paymentIntent->metadata->order_id ?? null;

        if (!$orderId) {
            Log::error('Stripe webhook missing order_id', [
                'payment_intent' => $paymentIntent->id,
            ]);
            return response()->json(['error' => 'Missing order_id'], 400);
        }

        try {
            DB::transaction(function () use ($orderId, $paymentIntent) {

                /** @var Order $order */
                $order = Order::with(['items.variant', 'sellerOrders'])
                    ->lockForUpdate()
                    ->findOrFail($orderId);

                // Prevent double processing
                if ($order->payment_status === 'paid') {
                    Log::warning('Order already paid', [
                        'order_id' => $orderId,
                    ]);
                    return;
                }

                // Mark order as paid
                $order->update([
                    'payment_status'    => 'paid',
                    'status'            => 'processing',
                    'payment_intent_id' => $paymentIntent->id,
                ]);

                // Update seller orders
                SellerOrder::where('order_id', $order->id)
                    ->update(['status' => 'processing']);

                // Decrease stock for each variant
                foreach ($order->items as $item) {
                    $variant = $item->variant()->lockForUpdate()->first();

                    if (!$variant) {
                        Log::error('Variant not found', [
                            'order_item_id' => $item->id,
                        ]);
                        throw new \Exception("Variant not found for order item {$item->id}");
                    }

                    if ($variant->stock < $item->quantity) {
                        Log::error('Insufficient stock', [
                            'variant_id' => $variant->id,
                            'required' => $item->quantity,
                            'available' => $variant->stock,
                        ]);
                        throw new \Exception("Insufficient stock for variant {$variant->id}");
                    }

                    $variant->decrement('stock', $item->quantity);
                }

                Log::info('Stripe webhook processed successfully', [
                    'order_id' => $orderId,
                    'payment_intent' => $paymentIntent->id,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }

        return response()->json(['status' => 'success']);
    }
}
