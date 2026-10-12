<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** V1 writers that still run keep the V2 mirror and its mapping in sync (Phase A readers use the mirror). */
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

    public function test_admin_no_show_updates_the_mapping_and_keeps_the_audit_valid(): void
    {
        $booking = $this->mappedBooking('confirmed', 'confirmed', now()->subDays(2));

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/bookings/{$booking->id}/no-show")->assertOk();

        $this->assertSame('no_show', $this->mapping($booking)->legacy_status);
        $this->assertAuditValid();
    }

    public function test_manual_payment_confirmation_confirms_the_mirror(): void
    {
        $booking = $this->mappedBooking('pending_payment', 'held', now()->addDays(2));
        $payment = Payment::create(['booking_id' => $booking->id, 'provider' => 'vietqr', 'amount' => 100000, 'transfer_code' => $booking->code, 'status' => 'manual_review']);

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/payments/{$payment->id}", ['status' => 'paid'])->assertOk();

        $this->assertSame('confirmed', $booking->fresh()->status);
        $mapping = $this->mapping($booking);
        $mirror = Reservation::withoutGlobalScope('tenant_isolation')->findOrFail($mapping->reservation_id);
        $this->assertSame('confirmed', $mirror->status);
        $this->assertNotNull($mirror->confirmed_at);
        $this->assertSame(['confirmed'], $mirror->slots()->withoutGlobalScope('tenant_isolation')->pluck('status')->all());
        $this->assertSame(['active'], $mirror->entitlements()->withoutGlobalScope('tenant_isolation')->pluck('status')->all());
        $this->assertSame('confirmed', $mapping->legacy_status);
        $this->assertAuditValid();
    }

    public function test_expiring_a_mapped_pending_booking_keeps_the_audit_valid(): void
    {
        $booking = $this->mappedBooking('pending_payment', 'held', now()->addDays(2));
        $booking->forceFill(['payment_expires_at' => now()->subMinute()])->save();

        $this->assertSame(1, app(ExpirePendingBookings::class)->execute());

        $this->assertSame('expired', $this->mapping($booking)->legacy_status);
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

    private function assertAuditValid(): void
    {
        $audit = app(LegacyBookingBackfillService::class)->audit($this->org->id);
        $this->assertSame([], $audit['collisions']);
        $this->assertTrue($audit['valid']);
    }
}
