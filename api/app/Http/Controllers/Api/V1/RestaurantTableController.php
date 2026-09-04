<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Owner/manager manages tables + QR tokens (§9).
 */
class RestaurantTableController extends Controller
{
    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $tables = $restaurant->tables()
            ->orderBy('number')
            ->get();

        return response()->json(['tables' => $tables]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'number' => ['required', 'string', 'max:30'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $table = $restaurant->tables()->create([
            'number' => $validated['number'],
            'capacity' => $validated['capacity'] ?? 2,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'public_token' => RestaurantTable::generateToken(),
        ]);

        return response()->json(['table' => $table], 201);
    }

    /**
     * Bulk create tables 1..N (quick setup).
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'from' => ['required', 'integer', 'min:1', 'max:1000'],
            'to' => ['required', 'integer', 'gte:from', 'max:1000'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $capacity = $validated['capacity'] ?? 2;
        $created = [];

        for ($n = $validated['from']; $n <= $validated['to']; $n++) {
            $created[] = $restaurant->tables()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'number' => (string) $n],
                ['capacity' => $capacity, 'is_active' => true, 'public_token' => RestaurantTable::generateToken()],
            );
        }

        return response()->json(['tables' => $created, 'count' => count($created)], 201);
    }

    public function update(Request $request, RestaurantTable $table): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($table->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your table.');
        }

        $validated = $request->validate([
            'number' => ['sometimes', 'string', 'max:30'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $table->update($validated);

        return response()->json(['table' => $table->fresh()]);
    }

    public function destroy(Request $request, RestaurantTable $table): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($table->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your table.');
        }

        $table->delete();

        return response()->json(['message' => 'Table deleted.']);
    }

    /**
     * Regenerate a table's public QR token (e.g. lost/reprinted card).
     */
    public function regenerateToken(Request $request, RestaurantTable $table): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($table->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your table.');
        }

        $table->forceFill(['public_token' => RestaurantTable::generateToken()])->save();

        return response()->json(['table' => $table->fresh()]);
    }
}
