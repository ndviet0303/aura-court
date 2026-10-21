<?php

namespace Tests\Feature\Booking;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BooksDirectOwnerCourt;
use Tests\TestCase;

/** BR-74: a booking records who booked it, as the account read at that moment. */
class BookerSnapshotTest extends TestCase
{
    use BooksDirectOwnerCourt;
    use RefreshDatabase;

    public function test_missing_or_invalid_phone_is_refused_before_anything_is_held(): void
    {
        $fixture = $this->directOwnerCourt();
        $start = now()->addDays(2)->setTime(18, 0);

        foreach ([null, '12345'] as $phone) {
            $customer = $this->bookingCustomer($fixture['organization'], ['phone' => $phone]);
            $this->checkoutFor($customer, $fixture['organization'], $fixture['court'], [$start])
                ->assertUnprocessable()
                ->assertJsonPath('code', 'PHONE_REQUIRED');
        }

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('owner_payment_attempts', 0);
    }

    public function test_profile_edited_after_booking_keeps_the_recorded_booker(): void
    {
        $fixture = $this->directOwnerCourt();
        $customer = $this->bookingCustomer($fixture['organization'], ['name' => 'Nguyen Van An', 'phone' => '0912345678']);
        $code = $this->checkoutFor($customer, $fixture['organization'], $fixture['court'], [now()->addDays(2)->setTime(18, 0)])
            ->assertCreated()
            ->assertJsonPath('reservation.booker.phone', '0912345678')
            ->json('reservation.code');

        $customer->update(['name' => 'Tran Thi Binh', 'phone' => '0987000111']);

        $booker = ['name' => 'Nguyen Van An', 'phone' => '0912345678', 'email' => $customer->email];
        $this->assertSame($booker, Reservation::where('code', $code)->firstOrFail()->metadata['booker']);
        $this->actingAs($customer)->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->getJson("/api/reservations/{$code}")
            ->assertOk()
            ->assertJsonPath('reservation.booker', $booker);
        $listed = collect($this->actingAs($fixture['owner'])->getJson('/api/admin/reservations')->assertOk()->json('data'))
            ->firstWhere('code', $code);
        $this->assertSame($booker, $listed['booker']);
        $this->actingAs($fixture['owner'])->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->getJson("/api/attendance/{$code}")
            ->assertOk()
            ->assertJsonPath('reservation.booker', $booker);
    }

    public function test_backfill_copies_todays_profile_into_bookings_without_a_booker(): void
    {
        $fixture = $this->directOwnerCourt();
        $customer = $fixture['payer'];
        $recorded = $this->checkoutFor($customer, $fixture['organization'], $fixture['court'], [now()->addDays(2)->setTime(18, 0)])
            ->assertCreated()->json('reservation.code');
        $legacy = $this->checkoutFor($customer, $fixture['organization'], $fixture['court'], [now()->addDays(2)->setTime(20, 0)])
            ->assertCreated()->json('reservation.code');
        $legacyReservation = Reservation::where('code', $legacy)->firstOrFail();
        $legacyReservation->forceFill(['metadata' => collect($legacyReservation->metadata)->except('booker')->all()])->save();
        $customer->update(['name' => 'Le Van Cuong', 'phone' => '0933444555']);

        $migration = require database_path('migrations/2026_10_21_000001_backfill_reservation_booker.php');
        $migration->up();

        $backfilled = $legacyReservation->fresh()->metadata;
        $this->assertEquals(['name' => 'Le Van Cuong', 'phone' => '0933444555', 'email' => $customer->email], $backfilled['booker']);
        $this->assertTrue($backfilled['booker_backfilled']);
        $untouched = Reservation::where('code', $recorded)->firstOrFail()->metadata;
        $this->assertNotSame('Le Van Cuong', $untouched['booker']['name']);
        $this->assertArrayNotHasKey('booker_backfilled', $untouched);

        $migration->down();
        $this->assertArrayNotHasKey('booker', $legacyReservation->fresh()->metadata);
        $this->assertArrayHasKey('booker', Reservation::where('code', $recorded)->firstOrFail()->metadata);
    }
}
