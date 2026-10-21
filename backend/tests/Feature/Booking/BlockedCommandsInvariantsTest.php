<?php

namespace Tests\Feature\Booking;

use App\Events\CourtStatusUpdated;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BlockedCommandsInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenant(): array
    {
        $org = Organization::create([
            'name' => 'Invariants Org',
            'slug' => 'invariants-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Invariants Venue',
            'slug' => 'invariants-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Court Invariants', 'is_active' => true]);

        return [$org, $venue, $court];
    }

    private function fundUserWallet(User $user, int $organizationId, int $amount = 1000000): void
    {
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Top up');
    }

    public function test_blocked_v2_cancellation_preserves_entitlements_and_emits_no_notifications(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);
        $this->fundUserWallet($user, $org->id, 500000);

        PriceRule::create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 150000,
        ]);

        $date = Carbon::now(config('app.timezone'))->addDays(4)->setTime(10, 0)->startOfHour();

        $checkoutRes = $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $org->id,
            'Idempotency-Key' => 'v2-invariants-checkout',
        ])->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'expected_total' => 150000,
        ])->assertCreated();

        $code = $checkoutRes->json('reservation.code');

        Event::fake([CourtStatusUpdated::class]);
        Notification::fake();

        // Attempt V2 cancellation
        $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $org->id,
            'Idempotency-Key' => 'v2-invariants-cancel',
        ])->postJson("/api/reservations/{$code}/cancel")->assertNotFound();

        // Invariants: Entitlement status remains active
        $this->assertSame('confirmed', Reservation::where('code', $code)->value('status'));
        $this->assertSame('active', ReservationEntitlement::where('reservation_id', $checkoutRes->json('reservation.id'))->value('status'));

        Event::assertNotDispatched(CourtStatusUpdated::class);
        Notification::assertNothingSent();
    }
}
