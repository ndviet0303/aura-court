<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Legacy bookings are frozen history: live operations act on the mapped mirror and leave the legacy rows and the audit intact. */
class LegacyProjectionSyncTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Court $court;

    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Sync Org', 'slug' => 'sync-org-'.uniqid(), 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $this->org->id, 'name' => 'Sync Venue', 'slug' => 'sync-venue-'.uniqid(), 'is_active' => true]);
        $this->court = Court::create(['venue_id' => $venue->id, 'name' => 'Sync Court', 'is_active' => true]);
        $this->customer = User::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    public function test_no_show_on_a_mapped_booking_keeps_the_legacy_rows_and_the_audit_valid(): void
    {
        $booking = $this->mappedBooking('confirmed', 'confirmed', now()->subDays(2));
        $mirror = $this->mirror($booking);
        $staff = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $this->org->id, 'user_id' => $staff->id, 'role' => 'staff', 'status' => 'active']);

        $this->actingAs($staff)
            ->withHeaders(['X-Organization-ID' => (string) $this->org->id])
            ->postJson('/api/attendance/no-show', ['code' => $mirror->code])
            ->assertOk()
            ->assertJsonPath('attendance.status', BookingAttendance::STATUS_NO_SHOW);

        $this->assertSame('confirmed', $mirror->fresh()->status);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('confirmed', $this->mapping($booking)->legacy_status);
        $this->assertAuditValid();
    }

    public function test_expiring_a_mapped_pending_booking_keeps_the_audit_valid(): void
    {
        $booking = $this->mappedBooking('pending_payment', 'held', now()->addDays(2));
        $this->travel(11)->minutes();

        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());

        $this->assertSame('expired', $this->mirror($booking)->status);
        $this->assertSame('pending_payment', $booking->fresh()->status);
        $this->assertSame('pending_payment', $this->mapping($booking)->legacy_status);
        $this->assertAuditValid();
    }

    private function mappedBooking(string $status, string $slotStatus, CarbonInterface $startsAt): Booking
    {
        $booking = Booking::create([
            'code' => 'BK-SYNC-'.strtoupper(uniqid()),
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'total_amount' => 100000,
            'status' => $status,
            'payment_model' => 'legacy_wallet_v1',
            'payment_expires_at' => $status === 'pending_payment' ? now()->addMinutes(10) : null,
        ]);
        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $this->court->id,
            'starts_at' => $startsAt->copy()->setTime(10, 0),
            'ends_at' => $startsAt->copy()->setTime(11, 0),
            'unit_price' => 100000,
            'status' => $slotStatus,
        ]);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        return $booking;
    }

    private function mapping(Booking $booking): LegacyBookingMapping
    {
        return LegacyBookingMapping::withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $booking->id)->firstOrFail();
    }

    private function mirror(Booking $booking): Reservation
    {
        return Reservation::withoutGlobalScope('tenant_isolation')->findOrFail($this->mapping($booking)->reservation_id);
    }

    private function assertAuditValid(): void
    {
        $audit = app(LegacyBookingBackfillService::class)->audit($this->org->id);
        $this->assertSame([], $audit['collisions']);
        $this->assertTrue($audit['valid']);
    }
}
