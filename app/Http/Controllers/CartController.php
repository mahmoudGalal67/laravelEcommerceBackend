<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\RefreshToken;
use App\Services\CartService;
use App\Services\TokenService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        public CartService $cartService,
        public TokenService $tokenService
    ) {}
    // 🧩 Get current  cart
    public function index(Request $request)
    {
        $user = $this->tokenService->getUserFromRefreshCookie($request);
        $user = $this->tokenService->getUserFromRefreshCookie($request);
        if ($user) {
            $cart = $this->cartService->findOrCreateUserCart($user->id);
            return $this->responseCart($cart);
        } else {
            [$cart, $token] = $this->cartService->findOrCreateGuestCart($request);
            return $this->responseCart($cart)->cookie("guest_token", $token, 43200, "/");
        }
    }

    // 🧩 Add item to cart
    public function store(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => 'required|exists:variants,id',
            'seller_id' => 'required|exists:sellers,id',
            'quantity' => 'integer|min:1',
            'unit_price' => 'required|numeric|min:0',
        ]);
        $user = $this->tokenService->getUserFromRefreshCookie($request);
        if ($user) {
            $cart = $this->cartService->findOrCreateUserCart($user->id);
            $item =  $this->increaseQuantity($validated, $cart);
            $response = response()->json(["item" => $item, "message" => "Added to cart"]);
            return $response;
        } else {
            // Create new item
            [$cart, $guestToken] = $this->cartService->findOrCreateGuestCart($request);
            $item =  $this->increaseQuantity($validated, $cart);

            $response = response()->json(["item" => $item, "message" => $guestToken]);
            $response->cookie("guest_token", $guestToken, 43200, "/");
            return $response;
        }
    }

    // 🧩 Remove item
    public function destroy(Request $request, $id)
    {
        $user = $this->tokenService->getUserFromRefreshCookie($request);
        if ($user) {
            $cart = $this->cartService->findOrCreateUserCart($user->id);
        } else {
            [$cart, $guestToken] = $this->cartService->findOrCreateGuestCart($request);
        }
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Item removed']);
    }

    public function clear(Request $request)
    {
        $cart = $this->cartService->findOrCreateUserCart($request->user()->id);
        $cart->items()->delete();

        return response()->json(["message" => "Cart cleared"]);
    }
    // 🧩 Sync guest cart
    public function merge(Request $request)
    {
        $guestToken = $request->cookie("guest_token");

        if (!$guestToken) return response()->json(["message" => "No guest cart"], 200);

        $guest = Cart::where("guest_token", $guestToken)->first();

        
        
        $userCart = $this->cartService->findOrCreateUserCart($request->user()->id);
        
        $this->cartService->mergeCarts($guest, $userCart);

        return response()->json(["message" => "Cart merged"])
            ->cookie("guest_token", null, -1);
    }
    public function increaseQuantity($validated,  $cart)
    {
        $item = CartItem::where('cart_id', $cart->id)
            ->where('variant_id', $validated['variant_id'])
            ->first();
        if ($item) {
            // Item exists → add new quantity to previous
            $item->quantity += $validated['quantity'] ?? 1;
            $item->unit_price = $validated['unit_price'];
            $item->save();
        } else {
            // Create new item
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'seller_id' =>  $validated['seller_id'],
                'variant_id' => $validated['variant_id'],
                'quantity' => $validated['quantity'] ?? 1,
                'unit_price' => $validated['unit_price'],
            ]);
        }
        return $item;
    }
    private function responseCart($cart)
    {
        return response()->json([
            "items" => $cart->items()->with(['variant.color', 'variant.size', 'variant.images'])->get()
        ]);
    }
}
