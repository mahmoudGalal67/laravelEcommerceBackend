<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()->with([
            'categories',
            'variants.images',
            'variants.color',   // include color relation
            'variants.size'     // include size relation
        ]);
        // ✅ Name filter
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // ✅ Category filter
        if ($request->filled('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('name', $request->category);
            });
        }

        // ✅ Price range filter (check both base_price and variant prices)
        if ($request->filled('min_price') && $request->filled('max_price')) {
            $min = $request->min_price;
            $max = $request->max_price;

            $query->where(function ($q) use ($min, $max) {
                // base price in range
                $q->whereBetween('base_price', [$min, $max])
                    // or at least one variant in range
                    ->orWhereHas('variants', function ($sub) use ($min, $max) {
                        $sub->whereBetween('price', [$min, $max]);
                    });
            });
        }

        // ✅ Size filter
        if ($request->filled('size')) {
            $query->whereHas('variants', function ($q) use ($request) {
                $q->where('size', $request->size);
            });
        }

        // ✅ Color filter
        if ($request->filled('color')) {
            $query->whereHas('variants', function ($q) use ($request) {
                $q->where('color', $request->color);
            });
        }

        // ✅ Sorting
        if ($request->filled('sort_by')) {
            $sortOrder = $request->get('sort_order', 'asc');

            if ($request->sort_by === 'price') {
                // compute min price between base and variants
                $query->withMin('variants', 'price')
                    ->select('products.*')
                    ->selectRaw('LEAST(base_price, COALESCE((SELECT MIN(price) FROM product_variants WHERE product_variants.product_id = products.id), base_price)) as effective_price')
                    ->orderBy('effective_price', $sortOrder);
            } else {
                $query->orderBy($request->sort_by, $sortOrder);
            }
        } else {
            $query->latest();
        }

        return response()->json($query->paginate(12));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:50',
            'category_id' => 'nullable|numeric',
            'base_price' => 'nullable|numeric',
            'is_active' => 'boolean',

            // ✅ Variants optional
            'variants' => 'nullable|array',
            'variants.*.color_id' => 'required_with:variants|string|max:100',
            'variants.*.size_id' => 'required_with:variants|string|max:50',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.stock' => 'required_with:variants|integer|min:0',
            'variants.*.sku' => 'nullable:variants|string|min:0',
            'variants.*.images' => 'required_with:variants|array',
        ]);

        /**
         * @var \App\Models\User $user
         */
        $user = $request->get('user');
        $userId = $user->id;
        try {
            DB::beginTransaction();
            // ✅ Create Product
            $product = Product::create([
                'seller_id' => $userId,
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                // 'category_id' => $validated['category_id'],
                'base_price' => $validated['base_price'],
                'is_active' => $validated['status'] ?? 1,
            ]);
            if (!empty($validated['category_id'])) {
                $categoryIds = (array) $validated['category_id'];
                $product->categories()->attach($categoryIds);
            }
            // ✅ Only create variants if they exist
            if (!empty($validated['variants'])) {
                foreach ($validated['variants'] as $variantData) {
                    $images = $variantData['images'] ?? [];

                    unset($variantData['images']); // prevent mass assignment error

                    $variant = $product->variants()->create($variantData);

                    foreach ($images as $img) {
                        $path = $img->store('uploads', 'public'); // store in storage/app/public/uploads
                        $variant->images()->create(['file_path' => $path]);
                    }
                }
            }
            DB::commit();
            return response()->json($product->load('variants.images'), 201);
        } catch (\Throwable $e) {
            // ❌ If any step failed → rollback
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $Product = Product::with(
            'categories',
            'variants.images',
            'variants.color',   // include color relation
            'variants.size'
        )->find($id);

        if (!$Product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($Product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    // ✅ Create variant with images
    public function storeVariant(Request $request, $productId)
    {

        $validated = $request->validate([
            'color' => 'required|string|max:100',
            'size' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'images' => 'array',
            'images.*' => 'string' // store image URLs (can be s3, local path, etc.)
        ]);

        $product = Product::findOrFail($productId);

        $variant = $product->variants()->create($validated);
        // attach images
        if ($request->has('images')) {
            foreach ($request->images as $img) {
                $variant->images()->create(['image_url' => $img]);
            }
        }

        return response()->json($variant->load('images'), 201);
    }
}
