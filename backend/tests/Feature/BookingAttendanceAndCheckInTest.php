<?php

namespace Tests\Feature;

use App\Events\CourtStatusUpdated;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\ServiceTab;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingAttendanceAndCheckInTest extends TestCase
{
    use RefreshDatabase;

    private Organization $tenantA;

    private Organization $tenantB;

    private User $staffA;

    private User $cashierA;

    private User $customer;

    private Venue $venueA;

    private Court $courtA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Organization::create([
            'name' => 'Aura Court Tenant A',
            'slug' => 'aura-a-'.uniqid(),
            'status' => 'active',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        $this->tenantB = Organization::create([
            'name' => 'Competitor Tenant B',
            'slug' => 'comp-b-'.uniqid(),
            'status' => 'active',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        $this->staffA = User::factory()->create(['name' => 'Staff Alice']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->staffA->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $this->cashierA = User::factory()->create(['name' => 'Cashier Bob']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->cashierA->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $this->customer = User::factory()->create(['name' => 'Customer Charlie']);
        OrganizationMember::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->customer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $this->venueA = Venue::create([
            'organization_id' => $this->tenantA->id,
            'name' => 'Aura Complex District 1',
            'slug' => 'aura-d1-'.uniqid(),
            'is_active' => true,
        ]);

        $this->courtA = Court::create([
            'venue_id' => $this->venueA->id,
            'name' => 'Center Badminton Court 1',
            'is_active' => true,
        ]);
    }

    private function headers(Organization $org): array
    {
        return [
            'X-Organization-ID' => (string) $org->id,
            'Idempotency-Key' => uniqid('idem-'),
        ];
    }

    private function createTodayReservation(Organization $org, Court $court, User $user, string $status = 'confirmed'): Reservation
    {
        $tz = config('app.timezone');
        $slotTime = Carbon::now($tz)->subMinutes(5)->second(0);

        $reservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'RES-'.strtoupper(uniqid()),
            'status' => $status,
            'currency' => 'VND',
            'total_amount' => 150000,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
        ]);

        ReservationSlot::create([
            'organization_id' => $org->id,
            'reservation_id' => $reservation->id,
            'court_id' => $court->id,
            'starts_at' => $slotTime,
            'ends_at' => $slotTime->copy()->addHour(),
            'unit_price' => 150000,
            'status' => $status,
            'snapshot' => ['starts_at' => $slotTime->toIso8601String()],
        ]);

        return $reservation;
    }

    public function test_authorized_staff_can_check_in_confirmed_current_day_booking_via_code_and_qr(): void
    {
        $reservation = $this->createTodayReservation($this->tenantA, $this->courtA, $this->customer);

        // 1. Check in via direct reservation code
        $res = $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', [
                'code' => $reservation->code,
                'notes' => 'Checked in at front desk',
            ]);

        $res->assertCreated();
        $res->assertJsonPath('attendance.status', 'checked_in');
        $res->assertJsonPath('attendance.checked_in_by', $this->staffA->id);
        $res->assertJsonPath('duplicate', false);

        $this->assertDatabaseHas('booking_attendances', [
            'organization_id' => $this->tenantA->id,
            'reservation_id' => $reservation->id,
            'status' => 'checked_in',
            'checked_in_by' => $this->staffA->id,
        ]);
    }

    public function test_check_in_supports_scanned_qr_prefixes(): void
    {
        $reservation = $this->createTodayReservation($this->tenantA, $this->courtA, $this->customer);

        // QR prefix format: AURA_V2_RESERVATION_<CODE>
        $qrString = "AURA_V2_RESERVATION_{$reservation->code}";

        $res = $this->actingAs($this->cashierA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', [
                'code' => $qrString,
            ]);

        $res->assertCreated();
        $res->assertJsonPath('attendance.reservation_id', $reservation->id);
        $res->assertJsonPath('attendance.status', 'checked_in');
    }

    public function test_duplicate_scan_is_idempotent_and_returns_existing_attendance(): void
    {
        $reservation = $this->createTodayReservation($this->tenantA, $this->courtA, $this->customer);

        // First scan
        $first = $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $reservation->code]);
        $first->assertCreated();
        $firstAttendanceId = $first->json('attendance.id');

        // Second scan (duplicate)
        $second = $this->actingAs($this->cashierA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $reservation->code]);

        $second->assertOk();
        $second->assertJsonPath('duplicate', true);
        $second->assertJsonPath('attendance.id', $firstAttendanceId);
        $second->assertJsonPath('attendance.checked_in_by', $this->staffA->id); // preserves original actor

        // Only 1 attendance row in DB
        $this->assertSame(1, BookingAttendance::where('reservation_id', $reservation->id)->count());
    }

    public function test_wrong_tenant_booking_scan_is_rejected_without_disclosing_details(): void
    {
        $venueB = Venue::create(['organization_id' => $this->tenantB->id, 'name' => 'Tenant B Venue', 'slug' => 'b-venue-'.uniqid(), 'is_active' => true]);
        $courtB = Court::create(['venue_id' => $venueB->id, 'name' => 'Tenant B Court', 'is_active' => true]);
        $userB = User::factory()->create();

        $reservationB = $this->createTodayReservation($this->tenantB, $courtB, $userB);

        // Staff of Tenant A attempts to check in Tenant B's reservation
        $res = $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $reservationB->code]);

        // Protected details are not disclosed
        $res->assertNotFound();
        $this->assertSame(0, BookingAttendance::where('reservation_id', $reservationB->id)->count());
    }

    public function test_cancelled_booking_scan_is_rejected(): void
    {
        $cancelledRes = $this->createTodayReservation($this->tenantA, $this->courtA, $this->customer, 'cancelled');

        $res = $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $cancelledRes->code]);

        $res->assertStatus(422);
        $res->assertJsonPath('message', 'Cancelled booking cannot be checked in.');
        $this->assertSame(0, BookingAttendance::where('reservation_id', $cancelledRes->id)->count());
    }

    public function test_non_current_day_booking_is_rejected(): void
    {
        $tz = config('app.timezone');
        $futureSlot = Carbon::now($tz)->addDays(2)->setTime(10, 0);

        $reservation = Reservation::create([
            'organization_id' => $this->tenantA->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->courtA->id,
            'code' => 'RES-FUTURE-'.uniqid(),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 120000,
            'confirmed_at' => now(),
        ]);

        ReservationSlot::create([
            'organization_id' => $this->tenantA->id,
            'reservation_id' => $reservation->id,
            'court_id' => $this->courtA->id,
            'starts_at' => $futureSlot,
            'ends_at' => $futureSlot->copy()->addHour(),
            'unit_price' => 120000,
            'status' => 'confirmed',
        ]);

        $res = $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $reservation->code]);

        $res->assertStatus(422);
        $res->assertJsonPath('message', 'Booking is not scheduled for the current venue-local day.');
        $this->assertSame(0, BookingAttendance::where('reservation_id', $reservation->id)->count());
    }

    public function test_unauthorized_customer_cannot_perform_check_in(): void
    {
        $reservation = $this->createTodayReservation($this->tenantA, $this->courtA, $this->customer);

        // Customer attempts to self-check-in via staff endpoint
        $res = $this->actingAs($this->customer)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $reservation->code]);

        $res->assertForbidden();
    }

    public function test_operational_court_status_dispatched_on_check_in_and_check_out(): void
    {
        Event::fake([CourtStatusUpdated::class]);

        $reservation = $this->createTodayReservation($this->tenantA, $this->courtA, $this->customer);

        // 1. Check in -> court operational status transitions to occupied
        $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $reservation->code])
            ->assertCreated();

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) use ($reservation) {
            return $event->courtId === $this->courtA->id
                && $event->status === 'occupied'
                && ($event->metadata['reservation_id'] ?? null) === $reservation->id;
        });

        // 2. Check out -> court operational status transitions to awaiting_inspection (Task 8.5 [D52])
        $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-out', ['code' => $reservation->code])
            ->assertOk();

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) use ($reservation) {
            return $event->courtId === $this->courtA->id
                && $event->status === 'awaiting_inspection'
                && ($event->metadata['reservation_id'] ?? null) === $reservation->id;
        });

        // 3. Staff confirms court ready -> transitions to available
        $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson("/api/v2/attendance/courts/{$this->courtA->id}/confirm-ready")
            ->assertOk();

        Event::assertDispatched(CourtStatusUpdated::class, function (CourtStatusUpdated $event) {
            return $event->courtId === $this->courtA->id
                && $event->status === 'available';
        });
    }

    public function test_check_out_lifecycle_and_unpaid_tab_blocking(): void
    {
        $reservation = $this->createTodayReservation($this->tenantA, $this->courtA, $this->customer);

        // Cannot checkout before check-in
        $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-out', ['code' => $reservation->code])
            ->assertStatus(422);

        // Check in
        $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $reservation->code])
            ->assertCreated();

        // Create an unpaid service tab attached to this reservation
        $tab = ServiceTab::create([
            'organization_id' => $this->tenantA->id,
            'reservation_id' => $reservation->id,
            'status' => ServiceTab::STATUS_UNPAID,
            'total_amount' => 50000,
            'opened_by' => $this->staffA->id,
        ]);

        // Checkout is NOT blocked by unpaid tab; succeeds and records unpaid receivable (Task 8.4 [D51])
        $checkout = $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-out', ['code' => $reservation->code]);

        $checkout->assertOk();
        $checkout->assertJsonPath('attendance.status', 'checked_out');
        $checkout->assertJsonPath('attendance.checked_out_by', $this->staffA->id);
        $checkout->assertJsonPath('unpaid_receivable', 50000);
        $this->assertSame(ServiceTab::STATUS_UNPAID, $tab->fresh()->status);

        // Court transitions to awaiting_inspection (Task 8.5 [D52])
        $this->assertSame('awaiting_inspection', Cache::get("court:{$this->courtA->id}:operational_status"));

        // Tab can still be settled after checkout
        $tab->update(['status' => ServiceTab::STATUS_SETTLED, 'settled_at' => now(), 'settled_by' => $this->staffA->id]);
        $this->assertSame(ServiceTab::STATUS_SETTLED, $tab->fresh()->status);
        // Repeated checkout is idempotent
        $dupCheckout = $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-out', ['code' => $reservation->code]);
        $dupCheckout->assertOk();
        $dupCheckout->assertJsonPath('duplicate', true);
    }

    public function test_legacy_mapped_booking_attendance_check_in(): void
    {
        $tz = config('app.timezone');
        $todaySlot = Carbon::now($tz)->subMinutes(5)->second(0);

        $legacyBooking = Booking::create([
            'code' => 'BK-LEGACY-'.uniqid(),
            'user_id' => $this->customer->id,
            'court_id' => $this->courtA->id,
            'total_amount' => 140000,
            'status' => 'confirmed',
        ]);

        BookingSlot::create([
            'booking_id' => $legacyBooking->id,
            'court_id' => $this->courtA->id,
            'starts_at' => $todaySlot,
            'ends_at' => $todaySlot->copy()->addHour(),
            'unit_price' => 140000,
            'status' => 'confirmed',
        ]);

        // Check in via QR string containing legacy booking code
        $qrString = "AURA_BOOKING_{$legacyBooking->code}";

        $res = $this->actingAs($this->staffA)
            ->withHeaders($this->headers($this->tenantA))
            ->postJson('/api/v2/attendance/check-in', ['code' => $qrString]);

        $res->assertCreated();
        $res->assertJsonPath('attendance.status', 'checked_in');
        $res->assertJsonPath('attendance.legacy_booking_id', $legacyBooking->id);

        // Verify canonical reservation exists and is linked
        $this->assertDatabaseHas('legacy_booking_mappings', [
            'organization_id' => $this->tenantA->id,
            'legacy_booking_id' => $legacyBooking->id,
        ]);
    }
}
