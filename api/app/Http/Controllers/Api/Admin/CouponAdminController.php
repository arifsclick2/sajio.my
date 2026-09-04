<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super Admin — manage coupons (Sajio plan: coupon system for packages).
 */
class CouponAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::query()
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => collect($coupons->items())->map(fn (Coupon $c) => $this->shape($c)),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCoupon($request);

        $coupon = Coupon::query()->create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'applies_to' => $validated['applies_to'] ?? 'first_invoice',
            'max_uses' => $validated['max_uses'] ?? null,
            'starts_at' => isset($validated['starts_at']) ? now()->parse($validated['starts_at']) : null,
            'expires_at' => isset($validated['expires_at']) ? now()->parse($validated['expires_at']) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json(['coupon' => $this->shape($coupon)], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $this->validateCoupon($request, partial: true);

        $data = [];
        foreach (['name', 'max_uses', 'is_active', 'applies_to'] as $f) {
            if (array_key_exists($f, $validated)) {
                $data[$f] = $validated[$f];
            }
        }
        if (isset($validated['code'])) {
            $data['code'] = strtoupper($validated['code']);
        }
        if (isset($validated['type'])) {
            $data['type'] = $validated['type'];
        }
        if (isset($validated['value'])) {
            $data['value'] = $validated['value'];
        }
        if (array_key_exists('starts_at', $validated)) {
            $data['starts_at'] = $validated['starts_at'] ? now()->parse($validated['starts_at']) : null;
        }
        if (array_key_exists('expires_at', $validated)) {
            $data['expires_at'] = $validated['expires_at'] ? now()->parse($validated['expires_at']) : null;
        }

        $coupon->update($data);

        return response()->json(['coupon' => $this->shape($coupon->fresh())]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function validateCoupon(Request $request, bool $partial = false): array
    {
        // Normalize code to uppercase BEFORE validation so the unique rule
        // is consistent with how codes are stored (Postgres is case-sensitive).
        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $sometimes = $partial ? 'sometimes' : 'required';

        $rules = [
            'code' => [$sometimes, 'string', 'max:64', 'alpha_dash', 'unique:coupons,code'.($partial ? ','.$request->route('coupon')?->id : '')],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => [$sometimes, Rule::in([CouponType::Percent->value, CouponType::Fixed->value])],
            'value' => [$sometimes, 'numeric', 'min:0', 'max:1000000'],
            'applies_to' => ['sometimes', Rule::in(['first_invoice', 'forever'])],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }

    private function shape(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'applies_to' => $coupon->applies_to,
            'max_uses' => $coupon->max_uses,
            'used_count' => $coupon->used_count,
            'starts_at' => $coupon->starts_at?->toISOString(),
            'expires_at' => $coupon->expires_at?->toISOString(),
            'is_active' => (bool) $coupon->is_active,
        ];
    }
}
