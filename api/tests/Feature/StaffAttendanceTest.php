<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\StaffWelcomeMail;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::factory()->create(array_merge(['timezone' => 'UTC'], $overrides));
    }

    private function makeStaff(Restaurant $restaurant, array $opts = []): User
    {
        $user = User::factory()->restaurant($restaurant)->role($opts['role'] ?? UserRole::Staff)->create([
            'password' => $opts['password'] ?? 'password123',
        ]);

        StaffProfile::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'staff_code' => StaffProfile::nextStaffCode($restaurant->id),
            'position' => $opts['position'] ?? 'Waiter',
            'is_active' => $opts['is_active'] ?? true,
        ]);

        return $user;
    }

    /* ------------------------------------------------------------------ */
    /*  Shift coverage                                                     */
    /* ------------------------------------------------------------------ */

    public function test_day_shift_covers_expected_hours(): void
    {
        $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00']);

        $this->assertTrue($shift->covers(CarbonImmutable::parse('2026-09-04 09:00', 'UTC')));
        $this->assertTrue($shift->covers(CarbonImmutable::parse('2026-09-04 12:00', 'UTC')));
        $this->assertFalse($shift->covers(CarbonImmutable::parse('2026-09-04 08:44', 'UTC'))); // outside 15-min grace
        $this->assertTrue($shift->covers(CarbonImmutable::parse('2026-09-04 08:50', 'UTC'))); // 10min early = grace
        $this->assertFalse($shift->covers(CarbonImmutable::parse('2026-09-04 17:00', 'UTC')));
    }

    public function test_overnight_shift_crosses_midnight(): void
    {
        $shift = Shift::factory()->overnight()->create();

        $this->assertTrue($shift->covers(CarbonImmutable::parse('2026-09-04 22:30', 'UTC')));
        $this->assertTrue($shift->covers(CarbonImmutable::parse('2026-09-05 02:00', 'UTC')));
        $this->assertTrue($shift->covers(CarbonImmutable::parse('2026-09-05 05:59', 'UTC')));
        $this->assertFalse($shift->covers(CarbonImmutable::parse('2026-09-05 12:00', 'UTC')));
    }

    /* ------------------------------------------------------------------ */
    /*  Clock in / out (service)                                           */
    /* ------------------------------------------------------------------ */

    public function test_clock_in_requires_a_running_shift(): void
    {
        $restaurant = $this->restaurant();
        $shift = Shift::factory()->create([
            'restaurant_id' => $restaurant->id,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        $staff = $this->makeStaff($restaurant);

        $service = app(AttendanceService::class);

        // Outside shift -> rejected.
        try {
            $service->clockIn($staff, now: CarbonImmutable::parse('2026-09-04 20:00', 'UTC'));
            $this->fail('Expected clock-in outside shift to be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attendance', $e->errors());
        }

        // Inside shift -> ok.
        $result = $service->clockIn($staff, now: CarbonImmutable::parse('2026-09-04 10:00', 'UTC'));
        $this->assertSame($shift->id, $result['shift']->id);
        $this->assertTrue($result['attendance']->isOnDuty());

        // Audit log written.
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $staff->id,
            'action' => 'clock_in',
            'method' => 'web',
        ]);

        // Second clock-in same day rejected.
        try {
            $service->clockIn($staff, now: CarbonImmutable::parse('2026-09-04 12:00', 'UTC'));
            $this->fail('Expected double clock-in to be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attendance', $e->errors());
        }
    }

    public function test_free_clock_in_when_restaurant_has_no_shifts(): void
    {
        $restaurant = $this->restaurant();
        $staff = $this->makeStaff($restaurant);

        $service = app(AttendanceService::class);
        $result = $service->clockIn($staff, now: CarbonImmutable::parse('2026-09-04 03:00', 'UTC'));

        $this->assertNull($result['shift']);
        $this->assertTrue($result['attendance']->isOnDuty());
    }

    public function test_clock_out_completes_shift_and_records_minutes(): void
    {
        $restaurant = $this->restaurant();
        $staff = $this->makeStaff($restaurant);
        $service = app(AttendanceService::class);

        $service->clockIn($staff, now: CarbonImmutable::parse('2026-09-04 09:00', 'UTC'));
        $out = $service->clockOut($staff, now: CarbonImmutable::parse('2026-09-04 13:30', 'UTC'));

        $this->assertTrue($out->isCompleted());
        $this->assertSame(4 * 60 + 30, $out->worked_minutes);
        $this->assertDatabaseHas('attendance_logs', ['user_id' => $staff->id, 'action' => 'clock_out']);

        // Clock out without clock in -> rejected.
        $other = $this->makeStaff($restaurant, ['position' => 'Cashier']);
        try {
            $service->clockOut($other, now: CarbonImmutable::parse('2026-09-04 20:00', 'UTC'));
            $this->fail('Expected clock-out without clock-in to be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attendance', $e->errors());
        }
    }

    public function test_owner_does_not_clock_in(): void
    {
        $restaurant = $this->restaurant();
        $owner = User::factory()->restaurant($restaurant)->owner()->create();

        $service = app(AttendanceService::class);

        try {
            $service->clockIn($owner, now: CarbonImmutable::parse('2026-09-04 10:00', 'UTC'));
            $this->fail('Expected owner clock-in to be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attendance', $e->errors());
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Duty gate (orders in Phase 4 must call this)                       */
    /* ------------------------------------------------------------------ */

    public function test_only_on_duty_staff_can_create_orders(): void
    {
        $restaurant = $this->restaurant();
        $staff = $this->makeStaff($restaurant);
        $service = app(AttendanceService::class);

        // Off duty -> blocked.
        try {
            $service->assertCanCreateOrders($staff, now: CarbonImmutable::parse('2026-09-04 10:00', 'UTC'));
            $this->fail('Expected off-duty staff to be blocked from orders');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('duty', $e->errors());
        }

        // Clock in -> allowed.
        $service->clockIn($staff, now: CarbonImmutable::parse('2026-09-04 10:00', 'UTC'));
        $service->assertCanCreateOrders($staff, now: CarbonImmutable::parse('2026-09-04 10:30', 'UTC'));
        $this->assertTrue($service->isOnDuty($staff, CarbonImmutable::parse('2026-09-04 10:30', 'UTC')));

        // After clock out -> blocked again.
        $service->clockOut($staff, now: CarbonImmutable::parse('2026-09-04 15:00', 'UTC'));
        $this->assertFalse($service->isOnDuty($staff, CarbonImmutable::parse('2026-09-04 15:01', 'UTC')));
    }

    public function test_owner_is_exempt_from_duty_gate(): void
    {
        $restaurant = $this->restaurant();
        $owner = User::factory()->restaurant($restaurant)->owner()->create();
        $service = app(AttendanceService::class);

        $service->assertCanCreateOrders($owner); // should not throw
        $this->assertTrue($service->isOnDuty($owner));
    }

    /* ------------------------------------------------------------------ */
    /*  Staff creation via API                                             */
    /* ------------------------------------------------------------------ */

    public function test_owner_creates_staff_and_temp_password_is_emailed(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $owner = User::factory()->restaurant($restaurant)->owner()->create();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Ali Bin Abu',
            'email' => 'ali@kedai.my',
            'role' => 'staff',
            'position' => 'Waiter',
        ]);

        $response->assertCreated()
            ->assertJsonPath('staff.staff_code', 'S001')
            ->assertJsonPath('staff.role', 'staff');

        $this->assertDatabaseHas('users', ['email' => 'ali@kedai.my', 'role' => 'staff']);
        Mail::assertQueued(StaffWelcomeMail::class);
    }

    public function test_manager_can_also_create_staff_but_staff_cannot(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $manager = $this->makeStaff($restaurant, ['role' => UserRole::Manager, 'position' => 'Supervisor']);
        $staff = $this->makeStaff($restaurant);

        Sanctum::actingAs($manager);
        $this->postJson('/api/v1/staff', [
            'name' => 'New Hire',
            'email' => 'hire@kedai.my',
            'role' => 'staff',
        ])->assertCreated();

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/staff', [
            'name' => 'Nope',
            'email' => 'nope@kedai.my',
            'role' => 'staff',
        ])->assertForbidden();
    }

    public function test_staff_created_email_unique_and_tenant_scoped(): void
    {
        Mail::fake();
        $r1 = $this->restaurant();
        $r2 = $this->restaurant();
        $owner1 = User::factory()->restaurant($r1)->owner()->create();
        $owner2 = User::factory()->restaurant($r2)->owner()->create();

        Sanctum::actingAs($owner1);
        $this->postJson('/api/v1/staff', ['name' => 'A', 'email' => 'dup@x.my', 'role' => 'staff'])->assertCreated();

        // Same email from another restaurant -> unique violation.
        Sanctum::actingAs($owner2);
        $this->postJson('/api/v1/staff', ['name' => 'B', 'email' => 'dup@x.my', 'role' => 'staff'])->assertUnprocessable();
    }

    /* ------------------------------------------------------------------ */
    /*  Login guards                                                       */
    /* ------------------------------------------------------------------ */

    public function test_inactive_staff_cannot_login(): void
    {
        $restaurant = $this->restaurant();
        $staff = $this->makeStaff($restaurant, ['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertForbidden();
    }

    public function test_active_staff_can_login(): void
    {
        $restaurant = $this->restaurant();
        $staff = $this->makeStaff($restaurant);

        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['role']]);
    }

    /* ------------------------------------------------------------------ */
    /*  HTTP attendance flow                                               */
    /* ------------------------------------------------------------------ */

    public function test_attendance_http_flow_and_owner_views(): void
    {
        $restaurant = $this->restaurant();
        Shift::factory()->create(['restaurant_id' => $restaurant->id, 'start_time' => '09:00:00', 'end_time' => '17:00:00']);
        $staff = $this->makeStaff($restaurant);

        $this->travelTo(CarbonImmutable::parse('2026-09-04 10:00', 'UTC'));

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/clock-in')
            ->assertOk()
            ->assertJsonPath('attendance.status', 'on_duty');

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('on_duty', true);

        $this->travelTo(CarbonImmutable::parse('2026-09-04 16:00', 'UTC'));
        $this->postJson('/api/v1/attendance/clock-out')
            ->assertOk()
            ->assertJsonPath('attendance.status', 'completed');

        // Owner view: attendance list for the day.
        $owner = User::factory()->restaurant($restaurant)->owner()->create();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/attendance?date=2026-09-04')
            ->assertOk()
            ->assertJsonCount(1, 'attendances')
            ->assertJsonPath('attendances.0.staff.name', $staff->name);

        $this->getJson('/api/v1/attendance/on-duty')->assertOk()->assertJsonCount(0, 'on_duty');
    }

    public function test_staff_cannot_view_attendance_list_of_others(): void
    {
        $restaurant = $this->restaurant();
        $staff = $this->makeStaff($restaurant);

        Sanctum::actingAs($staff);
        $this->getJson('/api/v1/attendance')->assertForbidden();
    }

    public function test_tenant_isolation_between_restaurants(): void
    {
        // Pin "now" to a mid-day time so the day shift (09:00-17:00) is running.
        $this->travelTo(CarbonImmutable::parse('2026-09-04 12:00', 'UTC'));

        $r1 = $this->restaurant();
        $r2 = $this->restaurant();
        Shift::factory()->create(['restaurant_id' => $r1->id, 'start_time' => '09:00:00', 'end_time' => '17:00:00']);

        $owner1 = User::factory()->restaurant($r1)->owner()->create();
        $staff1 = $this->makeStaff($r1);

        // Staff of r2 clocking in must not affect r1, and owner1 must not see r2 staff.
        $staff2 = $this->makeStaff($r2);

        Sanctum::actingAs($staff1);
        $this->postJson('/api/v1/attendance/clock-in')->assertOk();

        Sanctum::actingAs($staff2);
        // No shifts in r2 => free clock-in (r2 has none) but still isolated.
        $this->postJson('/api/v1/attendance/clock-in')->assertOk();

        Sanctum::actingAs($owner1);
        $this->getJson('/api/v1/attendance')
            ->assertOk()
            ->assertJsonCount(1, 'attendances');

        $this->assertDatabaseHas('attendances', ['restaurant_id' => $r1->id, 'user_id' => $staff1->id]);
        $this->assertDatabaseHas('attendances', ['restaurant_id' => $r2->id, 'user_id' => $staff2->id]);
    }

    /* ------------------------------------------------------------------ */
    /*  Shift CRUD (owner)                                                 */
    /* ------------------------------------------------------------------ */

    public function test_owner_can_manage_shifts(): void
    {
        $restaurant = $this->restaurant();
        $owner = User::factory()->restaurant($restaurant)->owner()->create();
        Sanctum::actingAs($owner);

        // Create a 2-shift pattern (12h + 12h).
        $this->postJson('/api/v1/shifts', [
            'name' => 'Pagi (12H)',
            'start_time' => '07:00',
            'end_time' => '19:00',
        ])->assertCreated();

        $this->postJson('/api/v1/shifts', [
            'name' => 'Malam (12H)',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'crosses_midnight' => true,
        ])->assertCreated();

        $this->getJson('/api/v1/shifts')->assertOk()->assertJsonCount(2, 'shifts');

        $shift = Shift::where('restaurant_id', $restaurant->id)->first();
        $this->putJson("/api/v1/shifts/{$shift->id}", ['name' => 'Pagi (Updated)'])
            ->assertOk()
            ->assertJsonPath('shift.name', 'Pagi (Updated)');
    }

    public function test_shift_used_by_attendance_cannot_be_deleted(): void
    {
        $restaurant = $this->restaurant();
        $owner = User::factory()->restaurant($restaurant)->owner()->create();
        $shift = Shift::factory()->create(['restaurant_id' => $restaurant->id]);
        $staff = $this->makeStaff($restaurant);

        app(AttendanceService::class)->clockIn($staff, now: CarbonImmutable::parse('2026-09-04 10:00', 'UTC'));

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/shifts/{$shift->id}")->assertStatus(422);
    }
}
