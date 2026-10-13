<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Reservation;
use App\Models\ReservationSlot;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarClosureAndUnifiedAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function setupCourtEnvironment(): array
    {
        $org = Organization::create([
            'name' => 'Aura Complex',
            'slug' => 'aura-complex-'.uniqid(),
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Aura Stadium',
            'slug' => 'aura-stadium-'.uniqid(),
            'address' => '123 Test St, Hanoi',
            'google_maps_url' => 'https://maps.google.com/maps?q=stadium',
            'is_active' => true,
        ]);

        $zone = SportZone::create([
            'venue_id' => $venue->id,
            'name' => 'Badminton Zone',
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        $court = Court::create([
            'name' => 'Court Alpha',
            'slug' => 'court-alpha-'.uniqid(),
            'venue_id' => $venue->id,
            'sport_zone_id' => $zone->id,
            'is_active' => true,
        ]);

        $tomorrow = now(config('app.timezone'))->addDay();
        $weekday = $tomorrow->dayOfWeekIso;

        // Create standard price slots for the weekday (e.g. 08:00 to 22:00)
        for ($hour = 8; $hour <= 21; $hour++) {
            CourtPriceSlot::create([
                'court_id' => $court->id,
                'weekday' => $weekday,
                'starts_at' => sprintf('%02d:00:00', $hour),
                'ends_at' => sprintf('%02d:00:00', $hour + 1),
                'price' => 150000,
                'is_active' => true,
            ]);
        }

        return compact('org', 'venue', 'zone', 'court', 'tomorrow', 'weekday');
    }

    public function test_court_block_creation_rejects_when_conflicting_with_confirmed_legacy_bookings(): void
    {
        $env = $this->setupCourtEnvironment();
        $admin = $this->admin();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $slotStart = $env['tomorrow']->copy()->setTime(18, 0, 0);
        $slotEnd = $env['tomorrow']->copy()->setTime(19, 0, 0);

        // 1. Create a confirmed legacy booking and its reservation mirror
        $booking = Booking::create([
            'code' => 'BK-CONF-1800',
            'user_id' => $user->id,
            'court_id' => $env['court']->id,
            'total_amount' => 150000,
            'status' => 'confirmed',
        ]);
        BookingSlot::create([
            'booking_id' => $booking->id,
            'court_id' => $env['court']->id,
            'starts_at' => $slotStart->copy()->utc(),
            'ends_at' => $slotEnd->copy()->utc(),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);
        app(LegacyBookingBackfillService::class)->backfillBooking($booking);

        // 2. Admin attempts to create a maintenance block overlapping this booking
        $response = $this->actingAs($admin)->postJson("/api/admin/courts/{$env['court']->id}/blocks", [
            'starts_at' => $env['tomorrow']->copy()->setTime(17, 30, 0)->toIso8601String(),
            'ends_at' => $env['tomorrow']->copy()->setTime(19, 30, 0)->toIso8601String(),
            'reason' => 'Thay thế đèn LED',
        ]);

        // 3. Rejected with 409 and reports the mirror of the conflicting booking
        $response->assertStatus(409)
            ->assertJsonPath('conflicting_bookings.0', 'LEGACY-BK-CONF-1800');

        $this->assertDatabaseMissing('court_blocks', [
            'court_id' => $env['court']->id,
            'reason' => 'Thay thế đèn LED',
        ]);
    }

    public function test_court_block_creation_rejects_when_conflicting_with_active_v2_reservations(): void
    {
        $env = $this->setupCourtEnvironment();
        $admin = $this->admin();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $slotStart = $env['tomorrow']->copy()->setTime(14, 0, 0);
        $slotEnd = $env['tomorrow']->copy()->setTime(15, 0, 0);

        // 1. Create confirmed V2 reservation
        $reservation = Reservation::create([
            'organization_id' => $env['org']->id,
            'user_id' => $user->id,
            'court_id' => $env['court']->id,
            'code' => 'RES-V2-1400',
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 150000,
        ]);
        ReservationSlot::create([
            'organization_id' => $env['org']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $env['court']->id,
            'starts_at' => $slotStart->copy()->utc(),
            'ends_at' => $slotEnd->copy()->utc(),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);

        // 2. Admin attempts to create maintenance block overlapping this reservation
        $response = $this->actingAs($admin)->postJson("/api/admin/courts/{$env['court']->id}/blocks", [
            'starts_at' => $slotStart->copy()->subMinutes(15)->toIso8601String(),
            'ends_at' => $slotEnd->copy()->addMinutes(15)->toIso8601String(),
            'reason' => 'Bảo trì sàn thảm',
        ]);

        // 3. Rejected with 409 and reports conflicting reservation code
        $response->assertStatus(409)
            ->assertJsonPath('conflicting_bookings.0', 'RES-V2-1400');

        $this->assertDatabaseMissing('court_blocks', [
            'court_id' => $env['court']->id,
            'reason' => 'Bảo trì sàn thảm',
        ]);
    }

    public function test_unbooked_closure_succeeds_and_slot_becomes_unavailable_in_availability_search(): void
    {
        $env = $this->setupCourtEnvironment();
        $admin = $this->admin();

        $blockStart = $env['tomorrow']->copy()->setTime(9, 0, 0);
        $blockEnd = $env['tomorrow']->copy()->setTime(10, 0, 0);

        // 1. Create maintenance block when no bookings exist
        $response = $this->actingAs($admin)->postJson("/api/admin/courts/{$env['court']->id}/blocks", [
            'starts_at' => $blockStart->toIso8601String(),
            'ends_at' => $blockEnd->toIso8601String(),
            'reason' => 'Dọn dẹp định kỳ',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('court_blocks', [
            'court_id' => $env['court']->id,
            'reason' => 'Dọn dẹp định kỳ',
        ]);

        // 2. Check public availability search
        $dateStr = $env['tomorrow']->format('Y-m-d');
        $availRes = $this->getJson("/api/availability?court_id={$env['court']->id}&date={$dateStr}");
        $availRes->assertOk();

        $slots = collect($availRes->json());

        // The 09:00 - 10:00 slot is marked available = false
        $slot09 = $slots->first(fn ($s) => str_contains($s['startsAt'], '09:00:00'));
        $this->assertNotNull($slot09);
        $this->assertFalse($slot09['available']);

        // Other slots (e.g. 10:00 - 11:00) remain available
        $slot10 = $slots->first(fn ($s) => str_contains($s['startsAt'], '10:00:00'));
        $this->assertNotNull($slot10);
        $this->assertTrue($slot10['available']);
    }

    public function test_closed_slot_rejects_direct_booking_and_direct_quote(): void
    {
        $env = $this->setupCourtEnvironment();
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $env['org']->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $blockStart = $env['tomorrow']->copy()->setTime(9, 0, 0);
        $blockEnd = $env['tomorrow']->copy()->setTime(10, 0, 0);

        CourtBlock::create([
            'court_id' => $env['court']->id,
            'starts_at' => $blockStart->copy()->utc(),
            'ends_at' => $blockEnd->copy()->utc(),
            'reason' => 'Khóa bảo trì',
        ]);

        // 1. Direct checkout is rejected
        $bookingRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $env['org']->id)
            ->withHeader('Idempotency-Key', uniqid('checkout-'))
            ->postJson('/api/reservations/checkout', [
                'court_id' => $env['court']->id,
                'slots' => [$blockStart->toIso8601String()],
            ]);
        $bookingRes->assertStatus(409);

        // 2. Direct quote request is rejected
        $quoteRes = $this->actingAs($user)
            ->withHeader('X-Organization-ID', (string) $env['org']->id)
            ->withHeader('Idempotency-Key', uniqid('quote-'))
            ->postJson('/api/price-quotes', [
                'court_id' => $env['court']->id,
                'slots' => [$blockStart->toIso8601String()],
            ]);
        $quoteRes->assertStatus(409)
            ->assertJsonPath('message', 'Sân đang bảo trì hoặc tạm đóng trong khoảng thời gian đã chọn.');
    }

    public function test_v2_active_reservation_marks_slot_unavailable_in_v1_availability(): void
    {
        $env = $this->setupCourtEnvironment();
        $user = User::factory()->create();

        $slotStart = $env['tomorrow']->copy()->setTime(20, 0, 0);
        $slotEnd = $env['tomorrow']->copy()->setTime(21, 0, 0);

        // V2 confirmed reservation
        $reservation = Reservation::create([
            'organization_id' => $env['org']->id,
            'user_id' => $user->id,
            'court_id' => $env['court']->id,
            'code' => 'RES-UNIFIED-2000',
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 150000,
        ]);
        ReservationSlot::create([
            'organization_id' => $env['org']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $env['court']->id,
            'starts_at' => $slotStart->copy()->utc(),
            'ends_at' => $slotEnd->copy()->utc(),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);

        // V1 availability check reflects V2 reservation
        $dateStr = $env['tomorrow']->format('Y-m-d');
        $availRes = $this->getJson("/api/availability?court_id={$env['court']->id}&date={$dateStr}");
        $availRes->assertOk();

        $slots = collect($availRes->json());
        $slot20 = $slots->first(fn ($s) => str_contains($s['startsAt'], '20:00:00'));
        $this->assertNotNull($slot20);
        $this->assertFalse($slot20['available']);
    }
}
