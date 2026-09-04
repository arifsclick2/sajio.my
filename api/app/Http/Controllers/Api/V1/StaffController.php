<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\StaffWelcomeMail;
use App\Models\Restaurant;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Restaurant staff management (owner / manager).
 */
class StaffController extends Controller
{
    private function restaurantOf(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        return $restaurant;
    }

    /**
     * List staff (with profiles + today's attendance status).
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $staff = User::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('role', [UserRole::Staff, UserRole::Manager])
            ->with('staffProfile')
            ->orderBy('id')
            ->get()
            ->map(fn (User $u) => $this->shape($u));

        return response()->json(['staff' => $staff]);
    }

    /**
     * Create a staff member. Generates a temporary password and emails it.
     */
    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([UserRole::Staff->value, UserRole::Manager->value])],
            'position' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        // Owner may not demote themselves or add another owner here.
        $temporaryPassword = Str::password(10, symbols: false);

        [$user] = DB::transaction(function () use ($validated, $restaurant, $temporaryPassword): array {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $temporaryPassword,
                'role' => UserRole::from($validated['role']),
                'restaurant_id' => $restaurant->id,
            ]);

            StaffProfile::create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $user->id,
                'staff_code' => StaffProfile::nextStaffCode($restaurant->id),
                'position' => $validated['position'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'joined_at' => now($restaurant->timezone)->toDateString(),
                'is_active' => true,
            ]);

            return [$user];
        });

        // Welcome the staff member with their temporary password.
        Mail::to($user->email)
            ->queue(new StaffWelcomeMail($restaurant, $user->name, $temporaryPassword));

        return response()->json(['staff' => $this->shape($user->load('staffProfile'))], 201);
    }

    /**
     * Update a staff member's profile / role / active state.
     */
    public function update(Request $request, User $staff): JsonResponse
    {
        $restaurant = $this->restaurantOf($request);

        if ($staff->restaurant_id !== $restaurant->id) {
            abort(403, 'Not your staff.');
        }

        if (! $staff->requiresAttendance()) {
            abort(403, 'Only staff/manager accounts can be managed here.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => ['sometimes', Rule::in([UserRole::Staff->value, UserRole::Manager->value])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($staff, $validated): void {
            if (isset($validated['name'])) {
                $staff->update(['name' => $validated['name']]);
            }

            if (isset($validated['role'])) {
                $staff->update(['role' => UserRole::from($validated['role'])]);
            }

            $profileData = array_filter([
                'position' => $validated['position'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? null,
            ], fn ($v) => $v !== null);

            if ($profileData) {
                $staff->staffProfile()->update($profileData);
            }
        });

        return response()->json(['staff' => $this->shape($staff->load('staffProfile'))]);
    }

    private function shape(User $user): array
    {
        $profile = $user->staffProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'staff_code' => $profile?->staff_code,
            'position' => $profile?->position,
            'phone' => $profile?->phone,
            'joined_at' => $profile?->joined_at?->toDateString(),
            'is_active' => (bool) $profile?->is_active,
        ];
    }
}
