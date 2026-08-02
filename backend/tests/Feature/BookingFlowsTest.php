<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Refund;
use App\Models\User;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_admin_endpoints(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->getJson('/api/v1/admin/reports')->assertForbidden();
    }

    public function test_admin_can_create_court_price_and_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $court = $this->actingAs($admin)->postJson('/api/v1/admin/courts', ['name' => 'Sân Test'])->assertCreated()->json();
        $courtId = $court['id'];

        $this->actingAs($admin)->postJson("/api/v1/admin/courts/{$courtId}/prices", [
            'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 150000,
        ])->assertCreated();
        $this->actingAs($admin)->postJson("/api/v1/admin/courts/{$courtId}/blocks", [
            'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'ends_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'reason' => 'Bảo trì',
        ])->assertCreated();

        $this->assertDatabaseHas('court_price_slots', ['court_id' => $courtId, 'price' => 150000]);
        $this->assertDatabaseHas('court_blocks', ['court_id' => $courtId, 'reason' => 'Bảo trì']);
    }

    public function test_sepay_webhook_confirms_a_valid_payment(): void
    {
        config(['services.sepay.hmac_secret' => 'test-secret']);
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Webhook', 'is_active' => true]);
        $date = now()->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);

        $booking = app(BookingService::class)->create($user->id, $court->id, [$date->toIso8601String()]);
        $payload = ['id' => 'tx-webhook-1', 'code' => $booking->code, 'transferAmount' => 120000, 'transferType' => 'in'];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $this->call('POST', '/api/v1/webhooks/sepay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SEPAY_SIGNATURE' => $signature], $raw)->assertOk();

        $this->assertDatabaseHas('payments', ['external_transaction_id' => 'tx-webhook-1', 'status' => 'paid']);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('booking_slots', ['booking_id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_pending_booking_expiration_releases_held_slots(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Expire', 'is_active' => true]);
        $booking = Booking::create(['code' => 'BKEXPIRE123', 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'pending_payment', 'payment_expires_at' => now()->subMinute()]);
        BookingSlot::create(['booking_id' => $booking->id, 'court_id' => $court->id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2), 'unit_price' => 100000, 'status' => 'held']);

        $this->assertSame(1, app(BookingService::class)->expirePending());
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'expired']);
        $this->assertDatabaseHas('booking_slots', ['booking_id' => $booking->id, 'status' => 'expired']);
    }

    public function test_admin_can_process_a_refund(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $court = Court::create(['name' => 'Sân Refund', 'is_active' => true]);
        $booking = Booking::create(['code' => 'BKREFUND123', 'user_id' => $customer->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'refund_pending']);
        $refund = Refund::create(['booking_id' => $booking->id, 'amount' => 100000, 'status' => 'pending']);

        $this->actingAs($admin)->patchJson("/api/v1/admin/refunds/{$refund->id}", ['status' => 'processed'])->assertOk();

        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'processed', 'processed_by' => $admin->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'refunded']);
    }

    public function test_two_customers_cannot_hold_the_same_slot(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $court = Court::create(['name' => 'Sân Conflict', 'is_active' => true]);
        $date = Carbon::now()->addDays(3)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        $this->actingAs($first)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]])->assertCreated();
        $this->actingAs($second)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]])->assertStatus(409);
    }
}
