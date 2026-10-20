<?php

namespace Tests\Feature\Booking;

use App\Events\CourtStatusUpdated;
use App\Models\BookingAttendance;
use App\Models\CashShift;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\ServiceTab;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AttendanceOperationsAndReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Attendance Central',
            'slug' => 'attend-central-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        $owner = User::factory()->create(['name' => 'Owner']);
        $staff = User::factory()->create(['name' => 'Staff Member']);
        $manager = User::factory()->create(['name' => 'Venue Manager']);
        $customer = User::factory()->create(['name' => 'Customer Primary']);
        $secondary = User::factory()->create(['name' => 'Customer Secondary']);
        $outsider = User::factory()->create(['name' => 'Outsider User']);

        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $staff->id, 'role' => 'staff', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $manager->id, 'role' => 'staff', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $customer->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $secondary->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $outsider->id, 'role' => 'customer', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Venue Alpha',
            'slug' => 'venue-alpha-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        // BR-20: staff reach only the venues assigned to them.
        OrganizationMember::where('organization_id', $organization->id)->where('role', 'staff')
            ->get()->each(fn (OrganizationMember $m) => $m->update(['permissions' => ['venue_ids' => [$venue->id]]]));

        $court1 = $venue->courts()->create(['name' => 'Court 1', 'is_active' => true]);
        $court2 = $venue->courts()->create(['name' => 'Court 2', 'is_active' => true]);

        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court1->id, 'type' => 'base', 'price' => 200000]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court2->id, 'type' => 'base', 'price' => 200000]);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 30, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'percentage', 'fee_value' => 10, 'refund_due_hours' => 48],
                'change' => ['deadline_hours' => 12, 'fee_type' => 'none', 'fee_value' => 0],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true, 'transfer_allowed' => true],
                'ancillary_credit_policy' => 'refund',
            ],
        ]);

        config([
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'staff', 'manager', 'customer', 'secondary', 'outsider', 'venue', 'court1', 'court2');
    }

    private function createConfirmedReservation(array $fixture, Court $court, User $user, Carbon $startsAt, int $amount = 200000, string $status = 'confirmed'): Reservation
    {
        $reservation = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'RSV-ATT-'.Str::upper(Str::random(8)),
            'status' => $status,
            'currency' => 'VND',
            'total_amount' => $amount,
            'payment_model' => 'direct_owner_v1',
            'confirmed_at' => now(),
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $court->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'unit_price' => $amount,
            'status' => $status,
            'snapshot' => [],
        ]);

        ReservationEntitlement::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'court_id' => $court->id,
            'user_id' => $user->id,
            'entitlement_type' => 'court_reservation',
            'status' => $status === 'confirmed' ? 'active' : 'held',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'amount' => $amount,
        ]);

        return $reservation;
    }

    public function test_check_in_validates_holder_and_authorized_representative(): void
    {
        $fixture = $this->tenant();
        $tz = 'Asia/Ho_Chi_Minh';
        $now = now($tz);
        // Reservation is scheduled right now (within arrival window)
        $reservation = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $now);

        $attendanceService = app(AttendanceService::class);

        // 1. Valid booking owner succeeds
        $res1 = $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $reservation->code, 'Owner check-in', $fixture['customer']);
        $this->assertSame('checked_in', $res1['attendance']->status);
        $this->assertFalse($res1['duplicate']);

        // Check-out so we can test other customer checks on another reservation
        $attendanceService->checkOut($fixture['staff'], $fixture['organization']->id, $reservation->code);
        $attendanceService->confirmCourtReady($fixture['staff'], $fixture['organization']->id, $fixture['court1']->id);

        // 2. Reservation for customer with entitlement assigned to secondary customer (on court2)
        $res2 = $this->createConfirmedReservation($fixture, $fixture['court2'], $fixture['customer'], $now);
        $entitlement2 = $res2->entitlements()->firstOrFail();
        $entitlement2->update(['user_id' => $fixture['secondary']->id]);

        // Secondary customer checks in with their entitlement: SUCCEEDS
        $res2CheckIn = $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $res2->code, 'Entitlement holder', $fixture['secondary']);
        $this->assertSame('checked_in', $res2CheckIn['attendance']->status);

        $attendanceService->checkOut($fixture['staff'], $fixture['organization']->id, $res2->code);
        $attendanceService->confirmCourtReady($fixture['staff'], $fixture['organization']->id, $fixture['court1']->id);

        // 3. Unauthorized outsider customer checks in: REJECTED with 422 (on court3)
        $court3 = Court::create(['venue_id' => $fixture['venue']->id, 'name' => 'Court 3', 'is_active' => true]);
        $res3 = $this->createConfirmedReservation($fixture, $court3, $fixture['customer'], $now);
        try {
            $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $res3->code, 'Outsider attempt', $fixture['outsider']);
            $this->fail('Expected outsider check-in to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Customer does not hold active check-in authority', $e->getMessage());
        }

        // 4. Authorized representative (with metadata representation grant): SUCCEEDS
        $representative = User::factory()->create();
        OrganizationMember::create(['organization_id' => $fixture['organization']->id, 'user_id' => $representative->id, 'role' => 'customer', 'status' => 'active']);
        $res3->update(['metadata' => array_merge((array) ($res3->metadata ?? []), ['representative_user_id' => $representative->id])]);

        $repCheckIn = $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $res3->code, 'Rep check-in', $representative);
        $this->assertSame('checked_in', $repCheckIn['attendance']->status);
    }

    public function test_check_in_validates_correct_venue(): void
    {
        $fixture = $this->tenant();
        $tz = 'Asia/Ho_Chi_Minh';
        $now = now($tz);
        $reservation = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $now);

        $attendanceService = app(AttendanceService::class);

        // Another venue in same org
        $otherVenue = Venue::create([
            'organization_id' => $fixture['organization']->id,
            'name' => 'Venue Beta',
            'slug' => 'venue-beta-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);

        // Trying to check in specifying wrong venue ID: REJECTED with 422
        try {
            $attendanceService->checkIn(
                $fixture['staff'],
                $fixture['organization']->id,
                $reservation->code,
                'Wrong venue check-in',
                $fixture['customer'],
                venueId: $otherVenue->id
            );
            $this->fail('Expected wrong venue check-in to fail.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Booking does not belong to this venue', $e->getMessage());
        }

        // Checking in specifying matching venue ID: SUCCEEDS
        $res = $attendanceService->checkIn(
            $fixture['staff'],
            $fixture['organization']->id,
            $reservation->code,
            'Correct venue check-in',
            $fixture['customer'],
            venueId: $fixture['venue']->id
        );
        $this->assertSame('checked_in', $res['attendance']->status);
    }

    public function test_check_in_validates_arrival_window_and_court_readiness(): void
    {
        // Frozen at venue-local midday: back-to-back slots must not overlap, and +/-1h must stay on the same venue day.
        Carbon::setTestNow(now('Asia/Ho_Chi_Minh')->setTime(12, 0));
        $fixture = $this->tenant();
        $tz = 'Asia/Ho_Chi_Minh';
        $attendanceService = app(AttendanceService::class);

        // 1. Too early: slot starts in 60 minutes, early threshold is 15 minutes
        $futureSlot = now($tz)->addMinutes(60);
        $earlyRes = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $futureSlot);
        try {
            $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $earlyRes->code, 'Early check-in', $fixture['customer']);
            $this->fail('Expected too early check-in to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Too early', $e->getMessage());
        }

        // 2. Arrival window expired: slot started 45 minutes ago, no_show_after_minutes is 30 minutes
        // court2: court1 holds the maintenance case's slot over this window.
        $pastSlot = now($tz)->subMinutes(45);
        $expiredRes = $this->createConfirmedReservation($fixture, $fixture['court2'], $fixture['customer'], $pastSlot);
        try {
            $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $expiredRes->code, 'Expired window', $fixture['customer']);
            $this->fail('Expected expired arrival window check-in to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Arrival window has expired', $e->getMessage());
        }

        // 3. Maintenance court: court1 marked inactive
        $validSlot = now($tz);
        $maintRes = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $validSlot);
        $fixture['court1']->update(['is_active' => false]);
        try {
            $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $maintRes->code, 'Maintenance check-in', $fixture['customer']);
            $this->fail('Expected check-in on maintenance court to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('maintenance', $e->getMessage());
        }
        $fixture['court1']->update(['is_active' => true]);

        // 4. CourtBlock (active maintenance block)
        CourtBlock::create([
            'court_id' => $fixture['court1']->id,
            'reason' => 'Emergency net repair',
            'starts_at' => now($tz)->subMinutes(10),
            'ends_at' => now($tz)->addMinutes(50),
        ]);
        try {
            $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $maintRes->code, 'Blocked check-in', $fixture['customer']);
            $this->fail('Expected check-in on blocked court to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('maintenance', $e->getMessage());
        }
    }

    public function test_check_in_and_check_out_project_operational_states_consistently_without_altering_payment(): void
    {
        Event::fake([CourtStatusUpdated::class]);
        $fixture = $this->tenant();
        $tz = 'Asia/Ho_Chi_Minh';
        $now = now($tz);
        $reservation = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $now);

        $attendanceService = app(AttendanceService::class);

        // Check-in
        $inResult = $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $reservation->code, 'Valid start');
        $this->assertFalse($inResult['duplicate']);
        $this->assertSame('in_use', $reservation->fresh()->metadata['operational_status']);
        $this->assertSame('confirmed', $reservation->fresh()->status); // Payment/contract status stays confirmed!
        $this->assertSame('direct_owner_v1', $reservation->fresh()->payment_model);

        Event::assertDispatched(CourtStatusUpdated::class, function ($event) use ($fixture) {
            return (int) $event->courtId === (int) $fixture['court1']->id && $event->status === 'occupied';
        });

        // Repeated check-in scan: IDEMPOTENT (no extra event, duplicate: true)
        $dupIn = $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $reservation->code);
        $this->assertTrue($dupIn['duplicate']);
        $this->assertSame($inResult['attendance']->id, $dupIn['attendance']->id);

        // Check-out
        $outResult = $attendanceService->checkOut($fixture['staff'], $fixture['organization']->id, $reservation->code);
        $this->assertFalse($outResult['duplicate']);
        $this->assertSame('completed', $reservation->fresh()->metadata['operational_status']);
        $this->assertSame('confirmed', $reservation->fresh()->status); // Contract stays confirmed

        Event::assertDispatched(CourtStatusUpdated::class, function ($event) use ($fixture) {
            return (int) $event->courtId === (int) $fixture['court1']->id && $event->status === 'awaiting_inspection';
        });

        // Repeated check-out: IDEMPOTENT
        $dupOut = $attendanceService->checkOut($fixture['staff'], $fixture['organization']->id, $reservation->code);
        $this->assertTrue($dupOut['duplicate']);
    }

    public function test_checkout_with_unpaid_tab_succeeds_and_tab_remains_open_receivable(): void
    {
        $fixture = $this->tenant();
        $tz = 'Asia/Ho_Chi_Minh';
        $now = now($tz);
        $reservation = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $now);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $reservation->code);

        // Attach an unpaid service tab of 150,000 VND
        $tab = ServiceTab::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'tab_name' => 'Court 1 Tab',
            'status' => 'unpaid',
            'total_amount' => 150000,
            'opened_at' => now(),
            'opened_by' => $fixture['staff']->id,
        ]);

        // HTTP checkout call with unpaid tab SUCCEEDS (Task 8.4 / D51)
        $response = $this->actingAs($fixture['staff'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'checkout-with-unpaid-tab-'.uniqid(),
            ])
            ->postJson('/api/attendance/check-out', [
                'code' => $reservation->code,
                'notes' => 'Customer leaves early, tab will be paid later',
            ]);

        $response->assertOk();
        $this->assertSame('checked_out', $response->json('attendance.status'));
        $this->assertSame(150000, $response->json('unpaid_receivable'));

        // Tab is NOT paid or cancelled; it remains an open receivable
        $this->assertSame('unpaid', $tab->fresh()->status);
        $this->assertSame(150000, (int) $tab->fresh()->total_amount);

        // Tab can be settled afterward once a cash shift is open
        CashShift::create([
            'organization_id' => $fixture['organization']->id,
            'venue_id' => $fixture['venue']->id,
            'cashier_id' => $fixture['staff']->id,
            'shift_number' => 'SHIFT-'.uniqid(),
            'opened_at' => now(),
            'opening_cash' => 500000,
            'status' => 'open',
        ]);

        $settleResponse = $this->actingAs($fixture['staff'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'post-checkout-tab-settle-'.uniqid(),
            ])
            ->postJson("/api/service-tabs/{$tab->id}/settle", [
                'payment_method' => 'cash',
                'cash_tendered' => 150000,
            ]);

        $settleResponse->assertOk();
        $this->assertSame(ServiceTab::STATUS_SETTLED, $tab->fresh()->status);
    }

    public function test_checkout_moves_court_to_awaiting_inspection_and_staff_confirms_ready(): void
    {
        // Venue-local midday, so the next-hour slot stays on the same venue day.
        Carbon::setTestNow(now('Asia/Ho_Chi_Minh')->setTime(12, 0));
        $fixture = $this->tenant();
        $tz = 'Asia/Ho_Chi_Minh';
        $now = now($tz);
        $reservation = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $now);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $reservation->code);
        $attendanceService->checkOut($fixture['staff'], $fixture['organization']->id, $reservation->code);

        // 1. Immediately after checkout, court is awaiting inspection.
        // A new reservation booking arriving for next slot CANNOT check in until inspection is done:
        $nextSlotTime = $now->copy()->addHour();
        $newRes = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['secondary'], $nextSlotTime);
        $testTime = $nextSlotTime->copy()->subMinutes(10);
        Carbon::setTestNow($testTime);

        try {
            try {
                $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $newRes->code, 'Immediate next check-in');
                $this->fail('Expected check-in to be rejected while awaiting inspection.');
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
                $this->assertStringContainsString('awaiting readiness inspection', $e->getMessage());
            }

            // 2. Customer or outsider trying to confirm court ready is REJECTED with 403
            $outsiderAttempt = $this->actingAs($fixture['outsider'])
                ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
                ->postJson("/api/attendance/courts/{$fixture['court1']->id}/confirm-ready", [
                    'notes' => 'Attempted by customer',
                ]);
            $outsiderAttempt->assertStatus(403);

            // 3. Authorized staff confirms court ready: SUCCEEDS
            $staffConfirm = $this->actingAs($fixture['staff'])
                ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
                ->postJson("/api/attendance/courts/{$fixture['court1']->id}/confirm-ready", [
                    'notes' => 'Net inspected, court swept clean and ready',
                ]);
            $staffConfirm->assertOk();
            $this->assertSame('available', $staffConfirm->json('status'));

            // 4. Now the new reservation CAN check in
            $newCheckIn = $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $newRes->code, 'Next check-in post inspection');
            $this->assertSame('checked_in', $newCheckIn['attendance']->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_no_show_workflow_requires_expired_arrival_window_and_records_audit(): void
    {
        $fixture = $this->tenant();
        $tz = 'Asia/Ho_Chi_Minh';
        $attendanceService = app(AttendanceService::class);

        // 1. Booking scheduled right now (starts now, window is 30 mins, current time is 0 mins after start)
        $now = now($tz);
        $currentRes = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $now);

        // Trying to mark no-show before window expiration: REJECTED with 422
        $prematureResponse = $this->actingAs($fixture['staff'])
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
            ->postJson('/api/attendance/no-show', [
                'code' => $currentRes->code,
                'reason' => 'Customer late 5 minutes',
            ]);
        $prematureResponse->assertStatus(422);
        $this->assertStringContainsString('arrival window has not yet expired', $prematureResponse->json('message'));

        // 2. Booking that started 45 minutes ago (past the 30-min window)
        $expiredSlot = now($tz)->subMinutes(45);
        $pastRes = $this->createConfirmedReservation($fixture, $fixture['court2'], $fixture['customer'], $expiredSlot);

        $validNoShow = $this->actingAs($fixture['staff'])
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
            ->postJson('/api/attendance/no-show', [
                'code' => $pastRes->code,
                'reason' => 'Customer failed to appear within 30-minute grace window',
            ]);

        $validNoShow->assertOk();
        $this->assertSame(BookingAttendance::STATUS_NO_SHOW, $validNoShow->json('attendance.status'));
        $this->assertSame('no_show', $pastRes->fresh()->metadata['operational_status']);
    }

    public function test_incident_court_reassignment_preserves_pricing_and_payment_and_extension_is_retired(): void
    {
        $fixture = $this->tenant();
        $tz = 'Asia/Ho_Chi_Minh';
        $now = now($tz);
        $reservation = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['customer'], $now);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->checkIn($fixture['staff'], $fixture['organization']->id, $reservation->code);

        // 1. Reassign to Court 2 due to light fixture issue on Court 1: SUCCEEDS
        $reassignRes = $this->actingAs($fixture['staff'])
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
            ->postJson('/api/attendance/reassign-court', [
                'code' => $reservation->code,
                'new_court_id' => $fixture['court2']->id,
                'reason' => 'Light fixture flickering on Court 1',
            ]);

        $reassignRes->assertOk();
        $this->assertTrue($reassignRes->json('reassigned'));
        $this->assertSame($fixture['court2']->id, $reservation->fresh()->court_id);
        $this->assertSame($fixture['court2']->id, $reservation->fresh()->slots->first()->court_id);
        $this->assertSame(200000, (int) $reservation->fresh()->total_amount); // Pricing preserved!
        $this->assertCount(1, $reservation->fresh()->metadata['incidents']);

        // 2. Conflict during reassignment: Attempt to reassign to Court 1 when Court 1 is occupied/booked
        $resOnCourt1 = $this->createConfirmedReservation($fixture, $fixture['court1'], $fixture['secondary'], $now);
        $conflictReassign = $this->actingAs($fixture['staff'])
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
            ->postJson('/api/attendance/reassign-court', [
                'code' => $reservation->code,
                'new_court_id' => $fixture['court1']->id,
                'reason' => 'Try to move back to Court 1',
            ]);
        $conflictReassign->assertStatus(409);

        // 3. On-site extension is retired: extra time is booked as new slots.
        // 405 because only GET attendance/{code} still matches this path.
        $endsAt = $reservation->fresh()->slots->max('ends_at');
        $this->actingAs($fixture['staff'])
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id])
            ->postJson('/api/attendance/extend-session', [
                'code' => $reservation->code,
                'additional_minutes' => 30,
            ])
            ->assertStatus(405);
        $this->assertEquals($endsAt, $reservation->fresh()->slots->max('ends_at'));
    }

    public function test_staff_cannot_look_up_a_booking_at_a_venue_they_are_not_assigned_to(): void
    {
        $fixture = $this->tenant();
        $otherVenue = Venue::create([
            'organization_id' => $fixture['organization']->id,
            'name' => 'Venue Beta',
            'slug' => 'venue-beta-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $otherCourt = $otherVenue->courts()->create(['name' => 'Court B1', 'is_active' => true]);
        $reservation = $this->createConfirmedReservation($fixture, $otherCourt, $fixture['customer'], now()->addDay());
        $headers = ['X-Organization-ID' => (string) $fixture['organization']->id];

        // BR-88: staff assigned to Venue Alpha only must not read a Venue Beta booking.
        $this->actingAs($fixture['staff'])->withHeaders($headers)
            ->getJson("/api/attendance/{$reservation->code}")
            ->assertForbidden()
            ->assertJsonMissingPath('reservation');

        $this->actingAs($fixture['owner'])->withHeaders($headers)
            ->getJson("/api/attendance/{$reservation->code}")
            ->assertOk()
            ->assertJsonPath('reservation.code', $reservation->code);
    }
}
