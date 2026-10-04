<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopedHoldExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_sweep_expires_only_selected_tenants_and_default_sweep_remains_global(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $holds = [];
        foreach (['org1', 'org2'] as $key) {
            $tenant = $fixture[$key];
            $court = $tenant[$key === 'org1' ? 'court1' : 'court'];
            $booking = Booking::create([
                'code' => 'SCOPED-BOOKING-'.$key,
                'user_id' => $tenant['owner']->id,
                'court_id' => $court->id,
                'status' => 'pending_payment',
                'payment_model' => 'wallet',
                'payment_expires_at' => now()->subMinute(),
                'total_amount' => 100000,
            ]);
            $bookingSlot = BookingSlot::create([
                'booking_id' => $booking->id,
                'court_id' => $court->id,
                'starts_at' => now()->addDays(2)->startOfHour(),
                'ends_at' => now()->addDays(2)->startOfHour()->addHour(),
                'unit_price' => 100000,
                'status' => 'held',
            ]);
            $reservation = Reservation::create([
                'organization_id' => $tenant['org']->id,
                'code' => 'SCOPED-RESERVATION-'.$key,
                'user_id' => $tenant['owner']->id,
                'court_id' => $court->id,
                'status' => 'held',
                'hold_expires_at' => now()->subMinute(),
                'total_amount' => 100000,
                'currency' => 'VND',
            ]);
            $reservationSlot = ReservationSlot::create([
                'organization_id' => $tenant['org']->id,
                'reservation_id' => $reservation->id,
                'court_id' => $court->id,
                'starts_at' => now()->addDays(3)->startOfHour(),
                'ends_at' => now()->addDays(3)->startOfHour()->addHour(),
                'unit_price' => 100000,
                'status' => 'held',
            ]);
            $holds[$key] = compact('booking', 'bookingSlot', 'reservation', 'reservationSlot');
        }

        $sweep = app(ExpirePendingBookings::class);
        $this->assertSame(0, $sweep->execute(200, []));
        $this->assertSame(2, $sweep->execute(200, [$fixture['org1']['org']->id]));
        foreach ($holds['org1'] as $model) {
            $this->assertSame('expired', $model->fresh()->status);
        }
        $this->assertSame('pending_payment', $holds['org2']['booking']->fresh()->status);
        foreach (['bookingSlot', 'reservation', 'reservationSlot'] as $key) {
            $this->assertSame('held', $holds['org2'][$key]->fresh()->status);
        }
        $this->assertSame(0, $sweep->execute(200, [$fixture['org1']['org']->id]));
        $this->assertSame(2, $sweep->execute());
        foreach ($holds['org2'] as $model) {
            $this->assertSame('expired', $model->fresh()->status);
        }
    }
}
