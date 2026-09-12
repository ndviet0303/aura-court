<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\BookingService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_booking_with_wallet_balance(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân A', 'is_active' => true]);
        $date = now(config('app.timezone'))->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);

        // Credit user wallet
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $venue->organization_id);
        $walletService->credit($wallet, 500000, 'test_deposit', 1, 'Initial topup');

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]]);

        $response->assertCreated()
            ->assertJsonPath('booking.status', 'confirmed')
            ->assertJsonPath('booking.total_amount', 120000);

        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('booking_slots', ['court_id' => $court->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payments', ['provider' => 'wallet', 'amount' => 120000, 'status' => 'paid']);
        $this->assertDatabaseHas('wallet_accounts', ['id' => $wallet->id, 'balance' => 380000]);
        $this->assertDatabaseHas('wallet_transactions', ['wallet_account_id' => $wallet->id, 'type' => 'debit', 'amount' => 120000]);
    }

    public function test_booking_creation_fails_when_wallet_balance_is_insufficient(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân No Balance', 'is_active' => true]);
        $date = now(config('app.timezone'))->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['wallet', 'balance', 'shortfall']]);

        $this->assertDatabaseMissing('bookings', ['user_id' => $user->id]);
    }

    public function test_cancellation_endpoint_is_retired_and_returns_404(): void
    {
        $user = User::factory()->create();
        $court = Court::create(['name' => 'Sân B', 'is_active' => true]);
        $booking = $user->bookings()->create(['code' => 'BKTEST123', 'court_id' => $court->id, 'total_amount' => 100000, 'status' => 'confirmed']);
        $booking->slots()->create(['court_id' => $court->id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2), 'unit_price' => 100000, 'status' => 'confirmed']);

        $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->code}/cancel")->assertNotFound();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('booking_slots', ['booking_id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_retired_cancellation_does_not_refund_wallet_or_change_booking_state(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Refund Test', 'is_active' => true]);
        $date = now(config('app.timezone'))->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);

        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $venue->organization_id);
        $walletService->credit($wallet, 500000, 'test_deposit', 1, 'Initial topup');

        $booking = app(BookingService::class)->create($user->id, $court->id, [$date->toIso8601String()]);
        $this->assertSame(380000, $wallet->fresh()->balance);

        $response = $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->code}/cancel");
        $response->assertNotFound();

        // Repeat attempt
        $retryResponse = $this->actingAs($user)->postJson("/api/v1/bookings/{$booking->code}/cancel");
        $retryResponse->assertNotFound();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('booking_slots', ['booking_id' => $booking->id, 'status' => 'confirmed']);
        $this->assertSame(380000, $wallet->fresh()->balance);
        $this->assertDatabaseMissing('wallet_transactions', ['wallet_account_id' => $wallet->id, 'type' => 'credit', 'amount' => 120000]);
        $this->assertDatabaseMissing('refunds', ['booking_id' => $booking->id]);
    }

    public function test_customer_can_fetch_wallet_balance(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $venue->organization_id);
        $walletService->credit($wallet, 250000, 'test_deposit', 1, 'Top up');

        $response = $this->actingAs($user)->getJson('/api/v1/wallet/balance');
        $response->assertOk()
            ->assertJsonPath('balance', 250000)
            ->assertJsonPath('currency', 'VND');
    }
}
