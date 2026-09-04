<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Restaurant;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {
    }

    private function nowLocal(Restaurant $restaurant): CarbonImmutable
    {
        return CarbonImmutable::now($restaurant->timezone);
    }

    /* ------------------------------------------------------------------ */
    /*  Self-service (staff / manager)                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Clock in (start duty). Only allowed while a shift is running (or free
     * mode when the restaurant has no shifts configured).
     */
    public function clockIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        $result = $this->attendance->clockIn(
            $user,
            method: 'web',
            ip: $request->ip(),
            now: $this->nowLocal($restaurant),
        );

        $attendance = $result['attendance'];

        return response()->json([
            'message' => 'Clocked in. Have a good shift!',
            'attendance' => $this->shape($attendance),
        ]);
    }

    /**
     * Clock out (end duty).
     */
    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        $attendance = $this->attendance->clockOut(
            $user,
            method: 'web',
            ip: $request->ip(),
            now: $this->nowLocal($restaurant),
        );

        return response()->json([
            'message' => 'Clocked out. See you next shift!',
            'attendance' => $this->shape($attendance),
        ]);
    }

    /**
     * The authenticated user's attendance status today.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurant;

        if (! $restaurant) {
            throw ValidationException::withMessages(['auth' => ['No restaurant linked.']]);
        }

        $today = $this->nowLocal($restaurant)->toDateString();

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->where('work_date', $today)
            ->with('shift')
            ->first();

        return response()->json([
            'date' => $today,
            'requires_attendance' => $user->requiresAttendance(),
            'on_duty' => $this->attendance->isOnDuty($user, $this->nowLocal($restaurant)),
            'attendance' => $attendance ? $this->shape($attendance) : null,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Owner / manager views                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Attendance list for a date (default today). Owner/manager only.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            abort(403, 'No restaurant linked.');
        }

        $date = $request->input('date', $this->nowLocal($restaurant)->toDateString());
        $this->validateDate($date);

        $rows = Attendance::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('work_date', $date)
            ->with(['user.staffProfile', 'shift'])
            ->orderBy('clock_in_at')
            ->get()
            ->map(fn (Attendance $a) => $this->shape($a));

        return response()->json([
            'date' => $date,
            'count' => $rows->count(),
            'attendances' => $rows,
        ]);
    }

    /**
     * Staff currently on duty.
     */
    public function onDuty(Request $request): JsonResponse
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            abort(403, 'No restaurant linked.');
        }

        $staff = $this->attendance->onDutyStaff($restaurant, $this->nowLocal($restaurant))
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
                'staff_code' => $user->staffProfile?->staff_code,
                'position' => $user->staffProfile?->position,
            ]);

        return response()->json([
            'on_duty' => $staff->values(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function validateDate(string $date): void
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (! $d || $d->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['date' => ['Invalid date format. Use Y-m-d.']]);
        }
    }

    private function shape(Attendance $attendance): array
    {
        $tz = $attendance->restaurant->timezone ?? 'Asia/Kuala_Lumpur';

        return [
            'id' => $attendance->id,
            'work_date' => $attendance->work_date->toDateString(),
            'staff' => $attendance->user ? [
                'id' => $attendance->user->id,
                'name' => $attendance->user->name,
                'staff_code' => $attendance->user->staffProfile?->staff_code,
                'position' => $attendance->user->staffProfile?->position,
            ] : null,
            'shift' => $attendance->shift ? [
                'id' => $attendance->shift->id,
                'name' => $attendance->shift->name,
                'start_time' => substr((string) $attendance->shift->start_time, 0, 5),
                'end_time' => substr((string) $attendance->shift->end_time, 0, 5),
            ] : null,
            'clock_in_at' => $attendance->clock_in_at?->timezone($tz)->toIso8601String(),
            'clock_out_at' => $attendance->clock_out_at?->timezone($tz)->toIso8601String(),
            'status' => $attendance->status(),
            'worked_minutes' => $attendance->worked_minutes,
        ];
    }
}
