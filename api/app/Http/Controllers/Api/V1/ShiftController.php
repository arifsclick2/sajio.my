<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Rules\ShiftTimeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    /**
     * The owner/manager's restaurant (tenant-scoped, never from the client).
     */
    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    /**
     * List shifts for the restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $shifts = $restaurant->shifts()
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Shift $s) => $this->shape($s));

        return response()->json(['shifts' => $shifts]);
    }

    /**
     * Create a shift (e.g. 2×12h or 3×8h patterns).
     */
    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', new ShiftTimeRule],
            'crosses_midnight' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $shift = $restaurant->shifts()->create([
            'name' => $validated['name'],
            'start_time' => $validated['start_time'].':00',
            'end_time' => $validated['end_time'].':00',
            'crosses_midnight' => (bool) ($validated['crosses_midnight'] ?? false),
            'is_active' => true,
            'sort_order' => $validated['sort_order'] ?? ($restaurant->shifts()->count()),
        ]);

        return response()->json(['shift' => $this->shape($shift)], 201);
    }

    /**
     * Update a shift.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($shift->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your restaurant.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', new ShiftTimeRule],
            'crosses_midnight' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $data = $validated;
        foreach (['start_time', 'end_time'] as $t) {
            if (isset($data[$t])) {
                $data[$t] .= ':00';
            }
        }

        $shift->update($data);

        return response()->json(['shift' => $this->shape($shift->fresh())]);
    }

    /**
     * Delete a shift (blocked if it has attendance records).
     */
    public function destroy(Request $request, Shift $shift): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($shift->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your restaurant.');
        }

        if ($shift->attendances()->exists()) {
            return response()->json([
                'message' => 'This shift has attendance records and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $shift->delete();

        return response()->json(['message' => 'Shift deleted.']);
    }

    private function shape(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'start_time' => substr((string) $shift->start_time, 0, 5),
            'end_time' => substr((string) $shift->end_time, 0, 5),
            'crosses_midnight' => (bool) $shift->crosses_midnight,
            'is_active' => (bool) $shift->is_active,
            'sort_order' => $shift->sort_order,
        ];
    }
}
