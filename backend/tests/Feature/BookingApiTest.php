<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_booking_and_receive_qr_details(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân A', 'is_active' => true]);
        $date = now(config('app.timezone'))->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]]);

        $response->assertCreated()->assertJsonPath('booking.status', 'pending_payment')->assertJsonPath('payment.amount', 120000);
        $this->assertDatabaseHas('booking_slots', ['court_id' => $court->id, 'status' => 'held']);
    }

    public function test_customer_cannot_cancel_confirmed_booking_after_deadline(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân B', 'is_active' => true]);
        $booking = $user->bookings()->create(['code' => 'BKTEST123', 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'confirmed']);
        $booking->slots()->create(['court_id' => $court->id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2), 'unit_price' => 100000, 'status' => 'confirmed']);

        $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->code}/cancel")->assertStatus(409);
    }

    public function test_customer_can_confirm_a_demo_payment_in_non_production_environment(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Demo Payment', 'is_active' => true]);
        $date = now(config('app.timezone'))->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);
        $booking = app(BookingService::class)->create($user->id, $court->id, [$date->toIso8601String()]);

        $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->code}/demo-pay")
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'status' => 'paid']);
        $this->assertDatabaseHas('booking_slots', ['booking_id' => $booking->id, 'status' => 'confirmed']);
    }
}
