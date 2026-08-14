<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Refund;
use App\Models\User;
use App\Models\Venue;
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

        $court = $this->actingAs($admin)->postJson('/api/v1/admin/courts', ['name' => 'Sân Test', 'venue_id' => Venue::firstOrFail()->id])->assertCreated()->json();
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

    public function test_booking_rejects_past_and_unaligned_slots(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Validation', 'is_active' => true]);
        $past = now()->subDay()->setTime(10, 0);

        $this->actingAs($user)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [$past->toIso8601String()]])->assertUnprocessable();
        $future = now()->addDay()->setTime(10, 30);
        $this->actingAs($user)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [$future->toIso8601String()]])->assertUnprocessable();
    }

    public function test_admin_can_list_and_delete_a_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $court = Court::create(['name' => 'Sân Block CRUD', 'is_active' => true]);
        $block = $court->blocks()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'reason' => 'Bảo trì']);

        $this->actingAs($admin)->getJson("/api/v1/admin/courts/{$court->id}/blocks")->assertOk()->assertJsonPath('data.0.id', $block->id);
        $this->actingAs($admin)->deleteJson("/api/v1/admin/courts/{$court->id}/blocks/{$block->id}")->assertOk();
        $this->assertDatabaseMissing('court_blocks', ['id' => $block->id]);
    }

    public function test_rejected_refund_does_not_leave_booking_pending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $court = Court::create(['name' => 'Sân Refund Reject', 'is_active' => true]);
        $booking = Booking::create(['code' => 'BKREJECT123', 'user_id' => $customer->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'refund_pending']);
        $refund = Refund::create(['booking_id' => $booking->id, 'amount' => 100000, 'status' => 'pending']);

        $this->actingAs($admin)->patchJson("/api/v1/admin/refunds/{$refund->id}", ['status' => 'rejected'])->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
    }

    public function test_customer_can_reload_payment_from_booking_code(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Payment Reload', 'is_active' => true]);
        $date = now()->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);
        $booking = app(BookingService::class)->create($user->id, $court->id, [$date->toIso8601String()]);

        $this->actingAs($user)->getJson("/api/v1/bookings/{$booking->code}/payment")->assertOk()->assertJsonPath('payment.code', $booking->code)->assertJsonPath('payment.amount', 120000);
    }

    public function test_customer_can_cancel_one_slot_and_keep_the_remaining_slot(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Partial Cancel', 'is_active' => true]);
        $booking = Booking::create(['code' => 'BKPARTIAL123', 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 200000, 'status' => 'confirmed']);
        $first = $booking->slots()->create(['court_id' => $court->id, 'starts_at' => now()->addDays(2)->setTime(10, 0), 'ends_at' => now()->addDays(2)->setTime(11, 0), 'unit_price' => 100000, 'status' => 'confirmed']);
        $second = $booking->slots()->create(['court_id' => $court->id, 'starts_at' => now()->addDays(2)->setTime(11, 0), 'ends_at' => now()->addDays(2)->setTime(12, 0), 'unit_price' => 100000, 'status' => 'confirmed']);

        $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->code}/cancel-slots", ['slot_ids' => [$first->id]])->assertOk();
        $this->assertDatabaseHas('booking_slots', ['id' => $first->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('booking_slots', ['id' => $second->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('refunds', ['booking_id' => $booking->id, 'amount' => 100000, 'status' => 'pending']);
    }

    public function test_recurring_booking_creates_all_weeks_atomically(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Recurring', 'is_active' => true]);
        $date = now()->addDays(3)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        $response = $this->actingAs($user)->postJson('/api/v1/bookings/recurring', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()], 'weeks' => 2]);
        $response->assertCreated()->assertJsonCount(2, 'bookings');
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_customer_can_reschedule_a_confirmed_booking(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân Reschedule', 'is_active' => true]);
        $oldDate = now()->addDays(2)->setTime(10, 0);
        $newDate = now()->addDays(3)->setTime(10, 0);
        foreach ([$oldDate, $newDate] as $date) {
            CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);
        }
        $booking = Booking::create(['code' => 'BKRESCHEDULE1', 'user_id' => $user->id, 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'confirmed']);
        $booking->slots()->create(['court_id' => $court->id, 'starts_at' => $oldDate, 'ends_at' => $oldDate->copy()->addHour(), 'unit_price' => 100000, 'status' => 'confirmed']);

        $response = $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->code}/reschedule", ['court_id' => $court->id, 'slots' => [$newDate->toIso8601String()]]);
        $response->assertCreated()->assertJsonPath('booking.status', 'pending_payment');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'refund_pending']);
    }

    public function test_unverified_customer_cannot_create_booking(): void
    {
        $user = User::factory()->unverified()->create();
        $court = Court::create(['name' => 'Sân Verify', 'is_active' => true]);
        $date = now()->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        $this->actingAs($user)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]])->assertForbidden();
    }
}
