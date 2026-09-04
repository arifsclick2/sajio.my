<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Attendance + duty logic.
 *
 * Roles: staff & managers must clock in to be ON DUTY; owners are exempt.
 * Shift-based: a staff can only clock in while one of the restaurant's active
 * shifts covers the current (restaurant-local) time. If a restaurant defines
 * no shifts, free clock-in is allowed (flexible default).
 */
class AttendanceService
{
    /**
     * Clock a staff member in. Returns the attendance row.
     *
     * @return array{attendance: Attendance, shift: ?Shift}
     */
    public function clockIn(User $user, string $method = 'web', ?string $ip = null, ?CarbonImmutable $now = null): array
    {
        $restaurant = $this->restaurantFor($user);

        $this->assertCanClock($user, $restaurant);

        $now ??= CarbonImmutable::now($restaurant->timezone);
        $today = $now->toDateString();

        $existing = Attendance::query()
            ->where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'attendance' => ['You have already clocked in today.'],
            ]);
        }

        // Shift resolution: enforce only when shifts are configured.
        $shift = $this->resolveShift($restaurant, $now);

        $attendance = Attendance::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'shift_id' => $shift?->id,
            'work_date' => $today,
            'clock_in_at' => $now->utc(),
            'clock_in_method' => $method,
        ]);

        AttendanceLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'action' => 'clock_in',
            'method' => $method,
            'ip' => $ip,
            'occurred_at' => $now->utc(),
        ]);

        return ['attendance' => $attendance, 'shift' => $shift];
    }

    /**
     * Clock a staff member out. Returns the completed attendance row.
     */
    public function clockOut(User $user, string $method = 'web', ?string $ip = null, ?CarbonImmutable $now = null): Attendance
    {
        $restaurant = $this->restaurantFor($user);

        $now ??= CarbonImmutable::now($restaurant->timezone);

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->where('work_date', $now->toDateString())
            ->whereNull('clock_out_at')
            ->latest()
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'attendance' => ['You have not clocked in today.'],
            ]);
        }

        $attendance->forceFill([
            'clock_out_at' => $now->utc(),
            'clock_out_method' => $method,
        ]);
        $attendance->computeWorkedMinutes($restaurant->timezone);
        $attendance->save();

        AttendanceLog::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'action' => 'clock_out',
            'method' => $method,
            'ip' => $ip,
            'occurred_at' => $now->utc(),
        ]);

        return $attendance;
    }

    /* ------------------------------------------------------------------ */
    /*  Duty gate (used by order creation)                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Is this user currently ON DUTY (clocked in, not clocked out)?
     * Owners are always exempt.
     */
    public function isOnDuty(User $user, ?CarbonImmutable $now = null): bool
    {
        if (! $user->requiresAttendance()) {
            return true; // owner / super admin
        }

        $restaurant = $user->restaurant;
        if (! $restaurant) {
            return false;
        }

        $now ??= CarbonImmutable::now($restaurant->timezone);

        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('work_date', $now->toDateString())
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->exists();
    }

    /**
     * Enforce the "only on-duty staff may create orders" rule.
     * Throws unless the user may operate right now.
     */
    public function assertCanCreateOrders(User $user, ?CarbonImmutable $now = null): void
    {
        if (! $this->isOnDuty($user, $now)) {
            throw ValidationException::withMessages([
                'duty' => ['You must clock in before you can take orders.'],
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Listing                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function onDutyStaff(Restaurant $restaurant, ?CarbonImmutable $now = null)
    {
        $now ??= CarbonImmutable::now($restaurant->timezone);

        return Attendance::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('work_date', $now->toDateString())
            ->onDuty()
            ->with('user.staffProfile')
            ->get()
            ->map(fn (Attendance $a) => $a->user);
    }

    /* ------------------------------------------------------------------ */
    /*  Internals                                                          */
    /* ------------------------------------------------------------------ */

    private function restaurantFor(User $user): Restaurant
    {
        $restaurant = $user->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages([
                'attendance' => ['This account is not linked to a restaurant.'],
            ]);
        }

        return $restaurant;
    }

    private function assertCanClock(User $user, Restaurant $restaurant): void
    {
        if (! $user->requiresAttendance()) {
            throw ValidationException::withMessages([
                'attendance' => ['Owners do not clock in.'],
            ]);
        }

        $profile = $user->staffProfile;

        if (! $profile || ! $profile->is_active) {
            throw ValidationException::withMessages([
                'attendance' => ['Your staff account is inactive. Ask your owner to reactivate it.'],
            ]);
        }

        if ($restaurant->status->value !== 'active') {
            throw ValidationException::withMessages([
                'attendance' => ['Your restaurant is not active.'],
            ]);
        }
    }

    /**
     * Pick the active shift covering "now". If the restaurant has no active
     * shifts configured, return null (free clock-in). If it has shifts but
     * none is running, throw so staff can't clock in off-shift.
     */
    private function resolveShift(Restaurant $restaurant, CarbonImmutable $localTime): ?Shift
    {
        $shifts = $restaurant->shifts()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($shifts->isEmpty()) {
            return null; // free clock-in mode
        }

        $current = $shifts->first(fn (Shift $shift) => $shift->covers($localTime));

        if (! $current) {
            throw ValidationException::withMessages([
                'attendance' => ['No shift is running right now. Clock in when your shift starts.'],
            ]);
        }

        return $current;
    }
}
