<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner/manager manages Table Tags (§10-14). Pro-gated at the client later.
 */
class TableTagController extends Controller
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

        $tags = $restaurant->tableTags()
            ->with('table:id,number')
            ->orderByDesc('id')
            ->get();

        return response()->json(['tags' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'tag_type' => ['required', Rule::in(TableTag::TYPES)],
        ]);

        if ($validated['table_id'] ?? null) {
            $table = RestaurantTable::find($validated['table_id']);
            if (! $table || $table->restaurant_id !== $restaurant->id) {
                throw ValidationException::withMessages(['table_id' => ['Invalid table.']]);
            }
        }

        $tag = $restaurant->tableTags()->create([
            'table_id' => $validated['table_id'] ?? null,
            'tag_code' => TableTag::nextTagCode($restaurant->id),
            'public_token' => TableTag::generateToken(),
            'tag_type' => $validated['tag_type'],
            'status' => 'active',
        ]);

        return response()->json(['tag' => $tag->load('table:id,number')], 201);
    }

    /**
     * Assign / reassign a tag to a table (tags are reassignable, §11).
     */
    public function assign(Request $request, TableTag $tableTag): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($tableTag->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your tag.');
        }

        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:restaurant_tables,id'],
        ]);

        $table = RestaurantTable::find($validated['table_id']);
        if ($table->restaurant_id !== $restaurant->id) {
            throw ValidationException::withMessages(['table_id' => ['Invalid table.']]);
        }

        $tableTag->update(['table_id' => $table->id]);

        return response()->json(['tag' => $tableTag->fresh()->load('table:id,number')]);
    }

    public function unassign(Request $request, TableTag $tableTag): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($tableTag->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your tag.');
        }

        $tableTag->update(['table_id' => null]);

        return response()->json(['tag' => $tableTag->fresh()]);
    }

    public function update(Request $request, TableTag $tableTag): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($tableTag->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your tag.');
        }

        $validated = $request->validate([
            'tag_type' => ['sometimes', Rule::in(TableTag::TYPES)],
            'status' => ['sometimes', Rule::in(TableTag::STATUSES)],
        ]);

        $tableTag->update($validated);

        return response()->json(['tag' => $tableTag->fresh()->load('table:id,number')]);
    }

    /**
     * Regenerate a damaged/lost tag's token.
     */
    public function regenerateToken(Request $request, TableTag $tableTag): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($tableTag->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your tag.');
        }

        $tableTag->forceFill(['public_token' => TableTag::generateToken()])->save();

        return response()->json(['tag' => $tableTag->fresh()]);
    }

    public function destroy(Request $request, TableTag $tableTag): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($tableTag->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your tag.');
        }

        $tableTag->delete();

        return response()->json(['message' => 'Tag deleted.']);
    }
}
