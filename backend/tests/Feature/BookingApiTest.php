<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\User;
use App\Models\Venue;
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
        $venue->organization->update(['partner_status' => 'approved']);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân A', 'is_active' => true]);
        $date = now(config('app.timezone'))->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);

        // Credit user wallet
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $venue->organization_id);
        $walletService->credit($wallet, 500000, 'test_deposit', 1, 'Initial topup');

        $response = $this->actingAs($user)->withHeader('Idempotency-Key', 'wallet-checkout')
            ->postJson('/api/reservations/checkout', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()], 'expected_total' => 120000]);

        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'confirmed')
            ->assertJsonPath('reservation.total_amount', 120000);

        $this->assertDatabaseHas('reservations', ['user_id' => $user->id, 'status' => 'confirmed', 'payment_model' => 'legacy_wallet_v1']);
        $this->assertDatabaseHas('reservation_slots', ['court_id' => $court->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('journals', ['event_type' => 'reservation.wallet_payment', 'reference' => $response->json('reservation.code')]);
        $this->assertDatabaseHas('wallet_accounts', ['id' => $wallet->id, 'balance' => 380000]);
        $this->assertDatabaseHas('wallet_transactions', ['wallet_account_id' => $wallet->id, 'type' => 'debit', 'amount' => 120000]);
    }

    public function test_booking_creation_fails_when_wallet_balance_is_insufficient(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        $venue->organization->update(['partner_status' => 'approved']);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân No Balance', 'is_active' => true]);
        $date = now(config('app.timezone'))->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);

        $response = $this->actingAs($user)->withHeader('Idempotency-Key', 'wallet-insufficient')
            ->postJson('/api/reservations/checkout', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()], 'expected_total' => 120000]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Số dư ví không đủ để thanh toán lịch đặt. Vui lòng nạp thêm tiền vào ví.');

        $this->assertDatabaseMissing('reservations', ['user_id' => $user->id]);
    }

    public function test_customer_can_fetch_wallet_balance(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $venue->organization_id);
        $walletService->credit($wallet, 250000, 'test_deposit', 1, 'Top up');

        $response = $this->actingAs($user)->getJson('/api/wallet/balance');
        $response->assertOk()
            ->assertJsonPath('balance', 250000)
            ->assertJsonPath('currency', 'VND');
    }
}
