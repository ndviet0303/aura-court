<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\WaitlistEntry;
use App\Notifications\AuraBookingNotification;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\ReservationService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\HoldsLegacyCoupon;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class AutomaticUnpaidExpiryTest extends TestCase
{
    use HoldsLegacyCoupon;
    use PricesCourts;
    use RefreshDatabase;

    private function setupTenant(): array
    {
        $org = Organization::create([
            'name' => 'Expiry Org',
            'slug' => 'expiry-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Expiry Venue',
            'slug' => 'expiry-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Court Expiry', 'is_active' => true]);

        return [$org, $venue, $court];
    }

    private function fundUserWallet(User $user, int $organizationId, int $amount = 1000000): void
    {
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Top up');
    }

    public function test_automatic_unpaid_expiry_releases_inventory_and_coupon_and_is_retry_safe(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $unpaidUser = User::factory()->create(['role' => 'customer']);
        $paidUser = User::factory()->create(['role' => 'customer']);
        $waiter = User::factory()->create(['role' => 'customer']);

        $this->fundUserWallet($paidUser, $org->id, 500000);
        $this->fundUserWallet($unpaidUser, $org->id, 500000);

        $date1 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $date2 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(14, 0)->startOfHour();

        $this->priceCourt($court, 100000);

        // 1. Unpaid hold on date1, holding a coupon use from before coupons left court bookings
        $heldCode = $this->actingAs($unpaidUser)->withHeader('Idempotency-Key', 'unpaid-hold')->postJson('/api/reservations', [
            'court_id' => $court->id,
            'slots' => [$date1->toIso8601String()],
            'expected_total' => 100000,
        ])->assertCreated()->assertJsonPath('reservation.total_amount', 100000)->json('reservation.code');
        $held = Reservation::where('code', $heldCode)->firstOrFail();
        $coupon = $this->holdLegacyCoupon($held, now()->addDays(2));

        // 2. Confirmed paid booking on date2
        $paidCode = $this->actingAs($paidUser)->withHeader('Idempotency-Key', 'paid-checkout')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date2->toIso8601String()],
            'expected_total' => 100000,
        ])->assertCreated()->assertJsonPath('reservation.status', 'confirmed')->json('reservation.code');
        $paid = Reservation::where('code', $paidCode)->firstOrFail();

        $walletService = app(WalletPaymentService::class);
        $unpaidWallet = $walletService->getOrCreateWallet($unpaidUser->id, $org->id);
        $paidWallet = $walletService->getOrCreateWallet($paidUser->id, $org->id);
        $unpaidBalanceBefore = (int) $unpaidWallet->fresh()->balance;
        $paidBalanceBefore = (int) $paidWallet->fresh()->balance;

        // 3. Waiter joins waitlist for date1
        WaitlistEntry::create([
            'user_id' => $waiter->id,
            'court_id' => $court->id,
            'starts_at' => $date1,
            'ends_at' => $date1->copy()->addHour(),
            'status' => 'waiting',
        ]);

        Notification::fake();

        // 4. Run automatic expiry once the hold has lapsed
        $this->travel(31)->minutes();
        $expiredCount = app(ExpirePendingBookings::class)->execute();
        $this->assertSame(1, $expiredCount);

        // Assert held reservation expired and held slot expired (released)
        $this->assertSame('expired', $held->fresh()->status);
        $this->assertSame(['expired'], $held->slots()->pluck('status')->all());
        $this->assertNotNull($held->fresh()->metadata['coupon_usage']['released_at']);

        // Assert coupon usage released
        $this->assertSame(0, (int) $coupon->fresh()->usage_count);

        // Assert notifications sent: user notified of expiry, waiter notified of availability
        Notification::assertSentTo($unpaidUser, AuraBookingNotification::class, function ($n) {
            return $n->event === 'booking_expired';
        });
        Notification::assertSentTo($waiter, AuraBookingNotification::class, function ($n) {
            return $n->event === 'waitlist_available';
        });

        // Invariant: Confirmed paid booking remains confirmed and was NOT touched
        $this->assertSame('confirmed', $paid->fresh()->status);
        $this->assertSame(['confirmed'], $paid->slots()->pluck('status')->all());

        // Invariant: Neither user's wallet was credited or debited
        $this->assertSame($unpaidBalanceBefore, (int) $unpaidWallet->fresh()->balance);
        $this->assertSame($paidBalanceBefore, (int) $paidWallet->fresh()->balance);
        $this->assertDatabaseMissing('owner_refund_obligations', ['reservation_id' => $held->id]);
        $this->assertDatabaseMissing('owner_refund_obligations', ['reservation_id' => $paid->id]);

        // 5. Retry safety: running again immediately returns 0 and leaves state identical
        $secondRun = app(ExpirePendingBookings::class)->execute();
        $this->assertSame(0, $secondRun);
        $this->assertSame(0, (int) $coupon->fresh()->usage_count);
        $this->assertSame($unpaidBalanceBefore, (int) $unpaidWallet->fresh()->balance);
    }

    public function test_v2_automatic_unpaid_hold_expiry_releases_inventory_without_crediting_wallets(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $unpaidUser = User::factory()->create(['role' => 'customer']);
        $paidUser = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $unpaidUser->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $paidUser->id, 'role' => 'customer', 'status' => 'active']);

        $this->fundUserWallet($unpaidUser, $org->id, 500000);
        $this->fundUserWallet($paidUser, $org->id, 500000);

        $walletService = app(WalletPaymentService::class);
        $unpaidWallet = $walletService->getOrCreateWallet($unpaidUser->id, $org->id);
        $paidWallet = $walletService->getOrCreateWallet($paidUser->id, $org->id);
        $unpaidBalanceBefore = (int) $unpaidWallet->fresh()->balance;
        $paidBalanceBefore = (int) $paidWallet->fresh()->balance;

        $this->priceCourt($court, 150000);

        $date1 = Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0)->startOfHour();
        $date2 = Carbon::now(config('app.timezone'))->addDays(3)->setTime(14, 0)->startOfHour();

        // 1. Expired held reservation
        $heldReservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $unpaidUser->id,
            'court_id' => $court->id,
            'code' => 'RES-HELD-EXP-01',
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 150000,
            'hold_expires_at' => now()->subMinutes(5),
        ]);
        $heldSlot = $heldReservation->slots()->create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'starts_at' => $date1,
            'ends_at' => $date1->copy()->addHour(),
            'unit_price' => 150000,
            'status' => 'held',
        ]);
        $heldEntitlement = $heldReservation->entitlements()->create([
            'organization_id' => $org->id,
            'user_id' => $unpaidUser->id,
            'court_id' => $court->id,
            'reservation_slot_id' => $heldSlot->id,
            'starts_at' => $date1,
            'ends_at' => $date1->copy()->addHour(),
            'amount' => 150000,
            'status' => 'held',
        ]);

        // 2. Confirmed paid reservation
        $confirmedReservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $paidUser->id,
            'court_id' => $court->id,
            'code' => 'RES-CONFIRMED-01',
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 150000,
            'hold_expires_at' => null,
            'confirmed_at' => now()->subHour(),
        ]);
        $confirmedSlot = $confirmedReservation->slots()->create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'starts_at' => $date2,
            'ends_at' => $date2->copy()->addHour(),
            'unit_price' => 150000,
            'status' => 'confirmed',
        ]);
        $confirmedEntitlement = $confirmedReservation->entitlements()->create([
            'organization_id' => $org->id,
            'user_id' => $paidUser->id,
            'court_id' => $court->id,
            'reservation_slot_id' => $confirmedSlot->id,
            'starts_at' => $date2,
            'ends_at' => $date2->copy()->addHour(),
            'amount' => 150000,
            'status' => 'active',
        ]);

        // 3. Trigger hold expiry via confirm or release
        $resService = app(ReservationService::class);
        $resService->release($heldReservation, 'expired');

        // Assert held reservation, entitlement, and slot are released with status 'expired'
        $this->assertSame('expired', $heldReservation->fresh()->status);
        $this->assertSame('expired', $heldEntitlement->fresh()->status);
        $this->assertSame('expired', $heldSlot->fresh()->status);

        // Invariant: Confirmed reservation and entitlements untouched
        $this->assertSame('confirmed', $confirmedReservation->fresh()->status);
        $this->assertSame('active', $confirmedEntitlement->fresh()->status);

        // Invariant: Wallets untouched, no refunds
        $this->assertSame($unpaidBalanceBefore, (int) $unpaidWallet->fresh()->balance);
        $this->assertSame($paidBalanceBefore, (int) $paidWallet->fresh()->balance);

        // 4. Retry safety
        $resService->release($heldReservation->fresh(), 'expired');
        $this->assertSame('expired', $heldReservation->fresh()->status);
        $this->assertSame($unpaidBalanceBefore, (int) $unpaidWallet->fresh()->balance);
    }

    public function test_scheduled_booking_expiry_closure_releases_v2_hold_and_benefits_exactly_once(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);

        $startsAt = Carbon::now(config('app.timezone'))->addDays(3)->setTime(9, 0)->startOfHour();

        $reservation = Reservation::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'code' => 'RES-SCHED-EXP-01',
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 150000,
            'hold_expires_at' => now()->subMinutes(5),
        ]);
        $slot = $reservation->slots()->create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'unit_price' => 150000,
            'status' => 'held',
        ]);
        $entitlement = $reservation->entitlements()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'reservation_slot_id' => $slot->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'amount' => 150000,
            'status' => 'held',
        ]);

        // Loyalty benefit held against the reservation at hold time.
        PointLedger::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'delta' => -500,
            'balance_after' => 0,
            'source_type' => 'reservation',
            'source_id' => $reservation->id,
        ]);

        Notification::fake();

        // Drive expiry through the registered scheduler closure, not a direct service call.
        $this->runScheduledEvent('booking:expire-pending');

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame('expired', $slot->fresh()->status);
        $this->assertSame('expired', $entitlement->fresh()->status);

        $releaseCount = fn (): int => PointLedger::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('source_type', 'reservation_release')
            ->where('source_id', $reservation->id)
            ->count();
        $this->assertSame(1, $releaseCount(), 'Reserved benefit must release exactly once.');

        // Re-running the same scheduled closure is idempotent: no duplicate release, no state change.
        $this->runScheduledEvent('booking:expire-pending');
        $this->assertSame(1, $releaseCount(), 'Benefit release must remain exactly once on re-run.');
        $this->assertSame('expired', $reservation->fresh()->status);
    }

    private function runScheduledEvent(string $name): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => ($e->description ?? null) === $name);
        $this->assertNotNull($event, "Scheduled event {$name} must be registered.");
        $event->run($this->app);
    }
}
