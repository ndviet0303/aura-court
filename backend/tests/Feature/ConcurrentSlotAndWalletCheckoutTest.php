<?php

namespace Tests\Feature;

use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrentSlotAndWalletCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenant(): array
    {
        $organization = Organization::create(['name' => 'Org Concurrency', 'slug' => 'org-conc-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Venue Conc', 'slug' => 'venue-conc-'.uniqid(), 'is_active' => true]);
        $court = $venue->courts()->create(['name' => 'Court Conc', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);

        return [$organization, $venue, $court];
    }

    private function fundUserWallet(User $user, int $organizationId, int $amount): void
    {
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Top up');
    }

    public function test_concurrent_wallet_debits_prevent_overdraft_and_double_spend(): void
    {
        [$organization, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);

        // User wallet has exactly 200,000 VND (only enough for 1 booking)
        $this->fundUserWallet($user, $organization->id, 200000);

        $court2 = $venue->courts()->create(['name' => 'Court Conc 2', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court2->id, 'type' => 'base', 'price' => 200000]);

        $date1 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(8, 0)->startOfHour();
        $date2 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(9, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date1->dayOfWeekIso, 'starts_at' => '08:00', 'ends_at' => '09:00', 'price' => 200000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court2->id, 'weekday' => $date2->dayOfWeekIso, 'starts_at' => '09:00', 'ends_at' => '10:00', 'price' => 200000, 'is_active' => true]);

        // Attempt checkout 1
        $res1 = $this->actingAs($user)->withHeader('Idempotency-Key', 'wallet-overdraft-1')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date1->toIso8601String()],
            'expected_total' => 200000,
        ]);
        $res1->assertCreated();

        // Attempt checkout 2 with same user, now out of funds
        $res2 = $this->actingAs($user)->withHeader('Idempotency-Key', 'wallet-overdraft-2')->postJson('/api/reservations/checkout', [
            'court_id' => $court2->id,
            'slots' => [$date2->toIso8601String()],
            'expected_total' => 200000,
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonPath('message', 'Số dư ví không đủ để thanh toán lịch đặt. Vui lòng nạp thêm tiền vào ví.');

        // Assert wallet balance is exactly 0 and not negative
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organization->id);
        $this->assertSame(0, (int) $wallet->balance);

        // Assert only 1 reservation confirmed
        $this->assertSame(1, Reservation::where('user_id', $user->id)->where('status', 'confirmed')->count());
    }

    public function test_unpaid_legacy_hold_confirmation_is_rejected(): void
    {
        [$organization, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);

        // User has 0 balance
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(16, 0)->startOfHour();

        // Hold reservation directly in held state without wallet debit
        $reservation = Reservation::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'HOLD-'.uniqid(),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 200000,
            'hold_expires_at' => now()->addMinutes(15),
        ]);

        // Attempting to confirm without wallet balance returns 409
        $confirmResponse = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $organization->id,
            'Idempotency-Key' => uniqid('unpaid-conf-'),
        ])->postJson("/api/reservations/{$reservation->code}/confirm");

        $confirmResponse->assertStatus(409);
        $this->assertStringContainsString('insufficient wallet balance', $confirmResponse->json('message'));
        $this->assertSame('held', $reservation->fresh()->status);
    }
}
