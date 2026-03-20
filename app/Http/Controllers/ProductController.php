<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Variant;
use App\Models\VariantImage;
use Illuminate\Support\Facades\Storage;

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
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }


        // ✅ Category filter
        if ($request->filled('category') && $request->category != 'undefined') {
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
        if ($request->filled('sort')) {

            $sort = $request->get('sort');  // oldest, newest, price_asc, price_desc

            switch ($sort) {

                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;

                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;

                case 'asc':
                case 'desc':
                    $direction = $sort; // asc or desc

                    $query->select('products.*')
                        ->selectRaw("
            LEAST(
                base_price,
                COALESCE(
                    (SELECT MIN(price) FROM variants WHERE variants.product_id = products.id),
                    base_price
                )
            ) AS effective_price
        ")
                        ->orderBy('effective_price', $direction);
                    break;
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:50',
            'category_id' => 'nullable|numeric',
            'base_price' => 'nullable|numeric',
            'base_images' => 'nullable|array',
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
        $validator->after(function ($validator) use ($request) {
            $variants = $request->input('variants');
            $baseImages = $request->file('base_images');

            if (empty($variants) && empty($baseImages)) {
                $validator->errors()->add(
                    'base_images',
                    'At least one base image is required when no variants exist'
                );
            }
        });
        $validated = $validator->validate();

        /**
         * @var \App\Models\User $user
         */
        $user = $request->user();
        $sellerId = $request->user()->seller->id;
        try {
            DB::beginTransaction();
            // ✅ Create Product
            $product = Product::create([
                'seller_id' => $sellerId,
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
                foreach ($request->variants as $vIndex => $variantData) {

                    $variant = Variant::updateOrCreate(
                        ['id' => $variantData['id']],
                        [
                            'product_id' => $product->id,
                            'price' => $variantData['price'],
                            'stock' => $variantData['stock'],
                            'color_id' => $variantData['color_id'],
                            'size_id' => $variantData['size_id'],
                            'sku' => $variantData['sku'] ?? null,
                        ]
                    );

                    $imagesMeta = $variantData['images'] ?? [];

                    foreach ($imagesMeta as $imgIndex => $imgMeta) {

                        if ($imgMeta['type'] === 'new') {

                            // 🔥 THIS IS THE IMPORTANT LINE
                            $file = $request->file("variants.$vIndex.images.$imgIndex.file");

                            if (!$file) {
                                continue;
                            }

                            $path = $file->store('uploads', 'public');

                            VariantImage::create([
                                'variant_id' => $variant->id,
                                'file_path' => $path,
                                'sort_order' => (int) $imgMeta['sort_order'],
                                'is_main' => (int) $imgMeta['sort_order'] === 0,
                            ]);
                        }
                    }
                }

            } else {
                if (!empty($validated['base_images'])) {
                    $images = [];

                    foreach ($validated['base_images'] as $img) {
                        $path = $img->store('uploads', 'public');
                        $images[] = $path;
                    }

                    // ✅ assign array at once
                    $product->update([
                        'base_images' => $images,
                    ]);
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
        // 1️⃣ VALIDATION
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:50',
            'category_id' => 'nullable|numeric',
            'base_price' => 'nullable|numeric',
            'base_images' => 'nullable|array',
            'deleted_base_images' => 'nullable|array',
            'is_active' => 'boolean',

            // Variants
            'variants' => 'nullable|array',
            'variants.*.id' => 'required|numeric',
            'variants.*.color_id' => 'required_with:variants|string|max:100',
            'variants.*.size_id' => 'required_with:variants|string|max:50',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.stock' => 'required_with:variants|integer|min:0',
            'variants.*.sku' => 'nullable|string|min:0',
            'variants.*.images' => 'nullable|array',
            'variants.*.images.*.type' => 'required|in:existing,new',
            'variants.*.images.*.sort_order' => 'required|integer|min:0',
            'variants.*.images.*.file_path' => 'required_if:variants.*.images.*.type,existing',
            'variants.*.deleted_images' => 'nullable|array',
        ]);

        $validator->after(function ($validator) use ($request) {
            $variants = $request->input('variants');
            $newBaseImages = $request->file('base_images', []);
            $existingBaseImages = $request->input('existing_base_images', []);

            if (empty($variants) && count($newBaseImages) === 0 && count($existingBaseImages) === 0) {
                $validator->errors()->add(
                    'base_images',
                    'At least one base image is required when no variants exist'
                );
            }
        });

        $validated = $validator->validate();

        $product = Product::findOrFail($id);

        /*
        |----------------------------------------------------------------------
        | BASE IMAGES HANDLING (unchanged)
        |---------------------------------------------------------------------- 
        */
        $existingImages = $product->base_images ?? [];

        if ($request->filled('deleted_base_images')) {
            foreach ($request->deleted_base_images as $img) {
                Storage::disk('public')->delete($img);
                $existingImages = array_values(array_diff($existingImages, [$img]));
            }
        }

        if ($request->hasFile('base_images')) {
            foreach ($request->file('base_images') as $file) {
                $path = $file->store('uploads', 'public');
                $existingImages[] = $path;
            }
        }

        $validated['base_images'] = $existingImages;
        $product->update($validated);

        /*
        |--------------------------------------------------------------------------
        | VARIANT IMAGES HANDLING (FIXED SORTING)
        |--------------------------------------------------------------------------
        */
        foreach ($request->input('variants', []) as $vIndex => $variantData) {

            $variant = Variant::findOrFail($variantData['id']);

            /*
            | 1️⃣ Delete removed images
            */
            if (!empty($variantData['deleted_images'])) {
                foreach ($variantData['deleted_images'] as $path) {
                    Storage::disk('public')->delete($path);
                    VariantImage::where('variant_id', $variant->id)
                        ->where('file_path', $path)
                        ->delete();
                }
            }
            // 2️⃣ Get images metadata and uploaded files
            $imagesMeta = $variantData['images'] ?? [];
            $uploadedFiles = $request->file("variants.$vIndex.images", []);

            // Sort by incoming sort_order (string-safe)
            usort(
                $imagesMeta,
                fn($a, $b) =>
                intval($a['sort_order']) <=> intval($b['sort_order'])
            );

            // 🔥 Always rebuild clean
            VariantImage::where('variant_id', $variant->id)->delete();

            $uploadIndex = 0;

            foreach ($imagesMeta as $index => $img) {

                // Existing image
                if ($img['type'] === 'existing') {
                    VariantImage::create([
                        'variant_id' => $variant->id,
                        'file_path' => $img['file_path'],
                        'sort_order' => $index,            // ✅ BACKEND CONTROLS ORDER
                        'is_main' => $index === 0,       // ✅ ONLY FIRST IMAGE
                    ]);
                }

                // New image
                if ($img['type'] === 'new' && isset($uploadedFiles[$uploadIndex])) {
                    $path = $uploadedFiles[$uploadIndex]->store('uploads', 'public');

                    VariantImage::create([
                        'variant_id' => $variant->id,
                        'file_path' => $path,
                        'sort_order' => $index,            // ✅ NORMALIZED
                        'is_main' => $index === 0,
                    ]);

                    $uploadIndex++;
                }
            }


        }
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load('variants', 'variants.images')
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $products = Product::whereIn('id', $validated['ids'])->get();

        foreach ($products as $product) {
            $product->delete();
        }

        return response()->json([
            'message' => 'Products deleted successfully',
        ]);
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

    public function byCategories(Request $request)
    {
        $categoryIds = $request->input('category_ids', []);

        return response()->json(
            Product::with('categories')
                ->where('is_active', true)
                ->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                })
                ->get()
        );
    }

    public function filterByNames(Request $request)
    {
        $ids = $request->input('ids', []);
        $names = $request->input('names', []);
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        if (empty($ids) && empty($names)) {
            return response()->json([]);
        }

        $query = Product::query()
            ->select('id', 'name', 'description', 'base_price', 'base_images')
            ->where('is_active', true);
        $query->where(function ($q) use ($ids, $names) {
            if (!empty($ids)) {
                $q->whereIn('id', $ids);
            }

            if (!empty($names)) {
                $q->orWhereIn('name', $names);
            }
        });

        return response()->json($query->get());
    }

    public function getAllProductsName()
    {
        return response()->json(
            Product::select('id', 'name')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

}
