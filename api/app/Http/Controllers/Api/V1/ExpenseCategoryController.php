<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Expense categories (§19) — owner/manager labels for money-out records,
 * e.g. Ingredients, Staff, Utilities, Rent, Supplies.
 */
class ExpenseCategoryController extends Controller
{
    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    private function ownedCategory(Restaurant $restaurant, ExpenseCategory $category): ExpenseCategory
    {
        if ($category->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your category.');
        }

        return $category;
    }

    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $categories = ExpenseCategory::query()
            ->where('restaurant_id', $restaurant->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ExpenseCategory $c) => $this->shape($c));

        return response()->json(['categories' => $categories->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('expense_categories', 'name')->where('restaurant_id', $restaurant->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = ExpenseCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['category' => $this->shape($category)], 201);
    }

    public function update(Request $request, ExpenseCategory $category): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $this->ownedCategory($restaurant, $category);

        $validated = $request->validate([
            'name' => [
                'sometimes', 'string', 'max:120',
                Rule::unique('expense_categories', 'name')
                    ->where('restaurant_id', $restaurant->id)
                    ->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json(['category' => $this->shape($category->fresh())]);
    }

    public function destroy(Request $request, ExpenseCategory $category): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);
        $this->ownedCategory($restaurant, $category);

        // Existing expenses are kept (category becomes null via FK nullOnDelete).
        $category->delete();

        return response()->json(['message' => 'Expense category deleted.']);
    }

    private function shape(ExpenseCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
    }
}
