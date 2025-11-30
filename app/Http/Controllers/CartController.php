<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(public CartService $service) {}
    // 🧩 Get current user's cart
    public function index(Request $request)
    {

        if ($request->user()) {
            $cart = $this->service->findOrCreateUserCart($request->user()->id);
            return $this->responseCart($cart);
        }

        [$cart, $token] = $this->service->findOrCreateGuestCart($request);

        return $this->responseCart($cart)
            ->cookie("guest_token", $token, 43200, "/");
    }

    // 🧩 Add item to cart
    public function store(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => 'required|exists:variants,id',
            'quantity' => 'integer|min:1',
            'unit_price' => 'required|numeric|min:0',
        ]);

        $cart = $this->service->findOrCreateUserCart($request->get('user')->id);


        $item = CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'variant_id' => $validated['variant_id']],
            [
                'quantity' => $validated['quantity'] ?? 1,
                'unit_price' => $validated['unit_price']
            ]
        );


        $response = response()->json(["item" => $item, "message" => "Added to cart"]);


        return $response;
    }
    // 🧩 Add item to cart
    public function storeGuest(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => 'required|exists:variants,id',
            'quantity' => 'integer|min:1',
            'unit_price' => 'required|numeric|min:0',
        ]);

        [$cart, $guestToken] = $this->service->findOrCreateGuestCart($request);

        $item = CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'variant_id' => $validated['variant_id']],
            [
                'quantity' => $validated['quantity'] ?? 1,
                'unit_price' => $validated['unit_price']
            ]
        );


        $response = response()->json(["item" => $item, "message" => $guestToken]);

        $response->cookie("guest_token", $guestToken, 43200, "/");

        return $response;
    }

    // 🧩 Remove item
    public function destroy(Request $request, $id)
    {
        $cart = $this->service->findOrCreateUserCart($request->get('user')->id);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Item removed']);
    }
    public function destroyGuest(Request $request, $id)
    {
        [$cart, $guestToken] = $this->service->findOrCreateGuestCart($request);

        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Item removed']);
    }
    public function clear(Request $request)
    {
        $cart = $this->service->findOrCreateUserCart($request->get('user')->id);
        $cart->items()->delete();

        return response()->json(["message" => "Cart cleared"]);
    }
    // 🧩 Sync guest cart
    public function syncGuestCart(Request $request)
    {
        $user = $request->get('user');
        $userId = $user->id;
        $items = $request->input('items', []);

        $cart = Cart::firstOrCreate(['user_id' => $userId]);

        foreach ($items as $item) {
            CartItem::updateOrCreate(
                [
                    'cart_id' => $cart->id,
                    'variant_id' => $item['variant_id']
                ],
                [
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price']
                ]
            );
        }

        return response()->json(['message' => 'Guest cart synced']);
    }
    private function responseCart($cart)
    {
        return response()->json([
            "items" => $cart->items()->with(['variant.color', 'variant.size', 'variant.images'])->get()
        ]);
    }
}
