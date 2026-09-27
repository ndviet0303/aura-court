<?php

namespace Tests\Feature\Booking;

use App\Events\CourtStatusUpdated;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Models\Venue;
use App\Models\WaitlistEntry;
use App\Models\WalletTransaction;
use App\Notifications\AuraBookingNotification;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
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

    public function test_blocked_v1_cancellation_preserves_all_invariants_and_emits_no_events(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create(['role' => 'customer']);
        $waiter = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);

        $this->fundUserWallet($user, $org->id, 1000000);

        $coupon = Coupon::create([
            'organization_id' => $org->id,
            'code' => 'DISC20K',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'usage_count' => 0,
        ]);

        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        // 1. User books with coupon
        $bookRes = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'coupon_code' => 'DISC20K',
        ])->assertCreated();

        $bookingCode = $bookRes->json('booking.code');
        $bookingId = $bookRes->json('booking.id');

        // Coupon was used: usage_count is 1
        $this->assertSame(1, (int) $coupon->fresh()->usage_count);

        // 2. Waiter joins waitlist on this occupied slot
        WaitlistEntry::create([
            'user_id' => $waiter->id,
            'court_id' => $court->id,
            'starts_at' => $date,
            'ends_at' => $date->copy()->addHour(),
            'status' => 'waiting',
        ]);

        // Snapshot DB baseline counts
        $bookingBefore = Booking::findOrFail($bookingId);
        $totalBookingsBefore = Booking::count();
        $walletTransactionsCountBefore = WalletTransaction::count();
        $journalsCountBefore = Journal::count();

        // Fake events and notifications
        Event::fake([CourtStatusUpdated::class]);
        Notification::fake();

        // 3. Attempt retired whole cancellation
        $this->actingAs($user)->postJson("/api/v1/bookings/{$bookingCode}/cancel")->assertNotFound();

        // 4. Invariants check:
        // - Slot state remains confirmed
        $this->assertSame('confirmed', $bookingBefore->slots()->first()->fresh()->status);
        $this->assertSame('confirmed', $bookingBefore->fresh()->status);

        // - Totals and discounts unchanged
        $this->assertSame($bookingBefore->total_amount, $bookingBefore->fresh()->total_amount);
        $this->assertSame($bookingBefore->discount_amount, $bookingBefore->fresh()->discount_amount);

        // - Coupon usage unchanged
        $this->assertSame(1, (int) $coupon->fresh()->usage_count);

        // - Wallet transactions, journals, and booking counts unchanged
        $this->assertSame($totalBookingsBefore, Booking::count());
        $this->assertSame($walletTransactionsCountBefore, WalletTransaction::count());
        $this->assertSame($journalsCountBefore, Journal::count());

        // - No availability or waitlist notifications emitted
        Event::assertNotDispatched(CourtStatusUpdated::class);
        Notification::assertNothingSent();
        $this->assertSame('waiting', WaitlistEntry::where('user_id', $waiter->id)->value('status'));
    }

    public function test_blocked_v1_cancel_slots_and_reschedule_preserve_invariants(): void
    {
        [$org, $venue, $court] = $this->setupTenant();
        $user = User::factory()->create(['role' => 'customer']);
        $this->fundUserWallet($user, $org->id, 1000000);

        $date1 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $date2 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(11, 0)->startOfHour();
        $newDate = Carbon::now(config('app.timezone'))->addDays(3)->setTime(10, 0)->startOfHour();

        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date1->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date2->dayOfWeekIso, 'starts_at' => '11:00', 'ends_at' => '12:00', 'price' => 100000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $newDate->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        $bookRes = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date1->toIso8601String(), $date2->toIso8601String()],
        ])->assertCreated();

        $bookingCode = $bookRes->json('booking.code');
        $slot1Id = $bookRes->json('booking.slots.0.id');

        $totalBookingsBefore = Booking::count();

        // 1. Partial slot cancellation returns 404
        $this->actingAs($user)->postJson("/api/v1/bookings/{$bookingCode}/cancel-slots", [
            'slot_ids' => [$slot1Id],
        ])->assertNotFound();

        $this->assertSame(2, Booking::where('code', $bookingCode)->first()->slots()->where('status', 'confirmed')->count());

        // 2. Rescheduling returns 404
        $this->actingAs($user)->postJson("/api/v1/bookings/{$bookingCode}/reschedule", [
            'court_id' => $court->id,
            'slots' => [$newDate->toIso8601String()],
        ])->assertNotFound();

        // Replacement booking count is zero (no new booking)
        $this->assertSame($totalBookingsBefore, Booking::count());
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
        ])->postJson('/api/v2/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ])->assertCreated();

        $code = $checkoutRes->json('reservation.code');

        Event::fake([CourtStatusUpdated::class]);
        Notification::fake();

        // Attempt V2 cancellation
        $this->actingAs($user)->withHeaders([
            'X-Organization-ID' => (string) $org->id,
            'Idempotency-Key' => 'v2-invariants-cancel',
        ])->postJson("/api/v2/reservations/{$code}/cancel")->assertNotFound();

        // Invariants: Entitlement status remains active
        $this->assertSame('confirmed', Reservation::where('code', $code)->value('status'));
        $this->assertSame('active', ReservationEntitlement::where('reservation_id', $checkoutRes->json('reservation.id'))->value('status'));

        Event::assertNotDispatched(CourtStatusUpdated::class);
        Notification::assertNothingSent();
    }
}
