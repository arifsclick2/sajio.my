<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Owner/manager manages menu categories + products (§8). Tenant-scoped.
 */
class MenuController extends Controller
{
    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    /* ------------------------------------------------------------------ */
    /*  Categories                                                         */
    /* ------------------------------------------------------------------ */

    public function categories(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $categories = $restaurant->categories()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = $restaurant->categories()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? ($restaurant->categories()->count()),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json(['category' => $category], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($category->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your category.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json(['category' => $category->fresh()]);
    }

    public function destroyCategory(Request $request, Category $category): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($category->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your category.');
        }

        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'Category has products. Move or delete them first.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Products                                                           */
    /* ------------------------------------------------------------------ */

    public function products(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $products = Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->with('category:id,name')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->input('category_id')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.$request->input('q').'%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->input('per_page', 100));

        return response()->json($products);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'sku' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'available' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Category must belong to this restaurant.
        $category = Category::find($validated['category_id']);
        if (! $category || $category->restaurant_id !== $restaurant->id) {
            throw ValidationException::withMessages(['category_id' => ['Invalid category.']]);
        }

        $product = $restaurant->products()->create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'image_url' => $validated['image_url'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'available' => (bool) ($validated['available'] ?? true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['product' => $product->load('category:id,name')], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($product->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your product.');
        }

        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:1000000'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'available' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($validated['category_id'])) {
            $category = Category::find($validated['category_id']);
            if (! $category || $category->restaurant_id !== $restaurant->id) {
                throw ValidationException::withMessages(['category_id' => ['Invalid category.']]);
            }
        }

        $product->update($validated);

        return response()->json(['product' => $product->fresh()->load('category:id,name')]);
    }

    public function destroyProduct(Request $request, Product $product): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($product->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your product.');
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}
