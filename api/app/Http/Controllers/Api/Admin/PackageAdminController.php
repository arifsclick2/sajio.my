<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageLimit;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Super Admin — manage packages (prices + limits). Sajio plan §3.
 *
 * Price changes apply to NEW subscriptions (a fresh Stripe Price is created
 * for the package and checkout uses it). Existing subscriptions keep their
 * original price until changed by the customer.
 */
class PackageAdminController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {
    }

    public function index(): JsonResponse
    {
        $packages = Package::query()
            ->with('limits')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Package $p) => $this->shape($p));

        return response()->json(['packages' => $packages]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePackage($request);

        $package = Package::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $this->saveLimits($package, $validated);

        // Sync to Stripe so checkout works immediately.
        try {
            $this->stripe->syncPackageToStripe($package->fresh());
        } catch (RuntimeException $e) {
            // Stripe not configured — package still saved locally.
        }

        return response()->json(['package' => $this->shape($package->load('limits'))], 201);
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        $validated = $this->validatePackage($request, partial: true);

        $data = array_intersect_key($validated, array_flip([
            'name', 'slug', 'description', 'is_active', 'sort_order',
        ]));
        if (isset($validated['slug'])) {
            $data['slug'] = Str::slug($validated['slug']);
        }
        $package->update($data);

        if (isset($validated['price_monthly'])) {
            $package->forceFill(['price_monthly' => $validated['price_monthly']])->save();
        }

        if (isset($validated['limits'])) {
            $this->saveLimits($package, $validated);
        }

        // Sync price change to Stripe (new price → new subscriptions).
        try {
            $this->stripe->syncPackageToStripe($package->fresh());
        } catch (RuntimeException $e) {
            // ignore — Stripe sync optional
        }

        return response()->json(['package' => $this->shape($package->fresh()->load('limits'))]);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function validatePackage(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        $rules = [
            'name' => [$sometimes, 'string', 'max:255'],
            'slug' => [$sometimes, 'string', 'max:255', 'alpha_dash'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price_monthly' => [$sometimes, 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'limits' => ['sometimes', 'array'],
            'limits.staff_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limits.pos_devices' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limits.table_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limits.menu_items' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limits.customer_qr_ordering' => ['sometimes', 'boolean'],
            'limits.advanced_reports' => ['sometimes', 'boolean'],
            'limits.table_card_tag_system' => ['sometimes', 'boolean'],
            'limits.fast_table_scan_at_pos' => ['sometimes', 'boolean'],
            'limits.nfc_tag_support' => ['sometimes', 'boolean'],
            'limits.table_card_printing' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }

    private function saveLimits(Package $package, array $validated): void
    {
        $l = $validated['limits'] ?? [];

        $package->limits()->updateOrCreate([], [
            'staff_count' => $l['staff_count'] ?? null,
            'pos_devices' => $l['pos_devices'] ?? null,
            'table_count' => $l['table_count'] ?? null,
            'menu_items' => $l['menu_items'] ?? null,
            'customer_qr_ordering' => (bool) ($l['customer_qr_ordering'] ?? false),
            'advanced_reports' => (bool) ($l['advanced_reports'] ?? false),
            'table_card_tag_system' => (bool) ($l['table_card_tag_system'] ?? false),
            'fast_table_scan_at_pos' => (bool) ($l['fast_table_scan_at_pos'] ?? false),
            'nfc_tag_support' => (bool) ($l['nfc_tag_support'] ?? false),
            'table_card_printing' => (bool) ($l['table_card_printing'] ?? false),
        ]);
    }

    private function shape(Package $package): array
    {
        $limits = $package->limits;

        return [
            'id' => $package->id,
            'name' => $package->name,
            'slug' => $package->slug,
            'description' => $package->description,
            'price_monthly' => $package->price_monthly,
            'stripe_price_id' => $package->stripe_price_id,
            'is_active' => (bool) $package->is_active,
            'sort_order' => $package->sort_order,
            'limits' => $limits ? [
                'staff_count' => $limits->staff_count,
                'pos_devices' => $limits->pos_devices,
                'table_count' => $limits->table_count,
                'menu_items' => $limits->menu_items,
                'customer_qr_ordering' => (bool) $limits->customer_qr_ordering,
                'advanced_reports' => (bool) $limits->advanced_reports,
                'table_card_tag_system' => (bool) $limits->table_card_tag_system,
                'fast_table_scan_at_pos' => (bool) $limits->fast_table_scan_at_pos,
                'nfc_tag_support' => (bool) $limits->nfc_tag_support,
                'table_card_printing' => (bool) $limits->table_card_printing,
            ] : null,
        ];
    }
}
