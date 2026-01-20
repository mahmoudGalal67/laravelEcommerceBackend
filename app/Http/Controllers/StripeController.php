<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripeController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function createPaymentIntent(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $validated = $request->validate([
            'amount'  => 'required|integer',
            'name'    => 'nullable|string|max:255',
            'email'   => 'nullable|email',
            'phone'   => 'required|string',
            'address' => 'required|string',
            'city'    => 'required|string',
        ]);

        try {
            // 1. Create Order via Service
            $order = $this->orderService->createFromCart(
                $validated,
                $request->user()->id
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Order creation failed',
                'error'   => $e->getMessage(),
            ], 500);
        }

        // 2. Create Stripe Payment Intent
        $paymentIntent = PaymentIntent::create([
            'amount'   => $validated['amount'],
            'currency' => 'usd',
            'metadata' => [
                'order_id' => $order->id,
            ],
        ]);

        return response()->json([
            'client_secret' => $paymentIntent->client_secret,
        ]);
    }
}
