<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\AuraBookingNotification;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class WaitlistLifecycleTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private function fundUserWallet(User $user, int $organizationId, int $amount = 1000000): void
    {
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organizationId);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Fund for test');
    }

    public function test_waitlist_create_rejects_open_slot(): void
    {
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Open Waitlist', 'is_active' => true]);
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();

        $res = $this->actingAs($user)->postJson('/api/waitlist', [
            'court_id' => $court->id,
            'starts_at' => $date->toIso8601String(),
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['starts_at']);
    }

    public function test_waitlist_create_accepts_occupied_slot_and_rejects_duplicate(): void
    {
        $owner = User::factory()->create();
        $waiter = User::factory()->create();
        $venue = Venue::firstOrFail();
        $venue->organization->update(['partner_status' => 'approved']);
        $this->fundUserWallet($owner, $venue->organization_id, 500000);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Occupied Waitlist', 'is_active' => true]);
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $this->priceCourt($court);

        // 1. Owner books the slot
        $this->actingAs($owner)->withHeader('Idempotency-Key', 'owner-checkout')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'expected_total' => 100000,
        ])->assertCreated();

        // 2. Waiter joins waitlist
        $joinRes = $this->actingAs($waiter)->postJson('/api/waitlist', [
            'court_id' => $court->id,
            'starts_at' => $date->toIso8601String(),
        ]);
        $joinRes->assertCreated();
        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $waiter->id,
            'court_id' => $court->id,
            'status' => 'waiting',
        ]);

        // 3. Duplicate join is rejected
        $dupRes = $this->actingAs($waiter)->postJson('/api/waitlist', [
            'court_id' => $court->id,
            'starts_at' => $date->toIso8601String(),
        ]);
        $dupRes->assertStatus(422);
        $dupRes->assertJsonValidationErrors(['starts_at']);
    }

    public function test_waitlist_user_can_leave_waitlist(): void
    {
        $owner = User::factory()->create();
        $waiter = User::factory()->create();
        $venue = Venue::firstOrFail();
        $venue->organization->update(['partner_status' => 'approved']);
        $this->fundUserWallet($owner, $venue->organization_id, 500000);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Leave Waitlist', 'is_active' => true]);
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $this->priceCourt($court);

        // Book slot
        $this->actingAs($owner)->withHeader('Idempotency-Key', 'owner-checkout')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'expected_total' => 100000,
        ])->assertCreated();

        // Join waitlist
        $joinRes = $this->actingAs($waiter)->postJson('/api/waitlist', [
            'court_id' => $court->id,
            'starts_at' => $date->toIso8601String(),
        ])->assertCreated();
        $entryId = $joinRes->json('id');

        // Leave waitlist via DELETE
        $delRes = $this->actingAs($waiter)->deleteJson("/api/waitlist/{$entryId}");
        $delRes->assertOk();

        $this->assertDatabaseHas('waitlist_entries', [
            'id' => $entryId,
            'status' => 'cancelled',
        ]);
    }

    public function test_released_slot_notifies_waiting_users_and_requires_normal_checkout(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $waiter = User::factory()->create();
        $venue = Venue::firstOrFail();
        $venue->organization->update(['partner_status' => 'approved']);
        $this->fundUserWallet($owner, $venue->organization_id, 500000);
        $this->fundUserWallet($waiter, $venue->organization_id, 500000);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Notify Waitlist', 'is_active' => true]);
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        $this->priceCourt($court);

        // 1. Owner books
        $bookRes = $this->actingAs($owner)->withHeader('Idempotency-Key', 'owner-checkout')->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
            'expected_total' => 100000,
        ]);
        $bookRes->assertCreated();
        $bookingCode = $bookRes->json('reservation.code');

        // 2. Waiter joins waitlist
        $this->actingAs($waiter)->postJson('/api/waitlist', [
            'court_id' => $court->id,
            'starts_at' => $date->toIso8601String(),
        ])->assertCreated();

        // 3. Attempting to cancel confirmed booking returns 404 and does NOT notify waitlist
        $this->actingAs($owner)->postJson("/api/reservations/{$bookingCode}/cancel")->assertNotFound();
        Notification::assertNotSentTo($waiter, AuraBookingNotification::class);
        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $waiter->id,
            'court_id' => $court->id,
            'status' => 'waiting',
        ]);

        // Supported release path: create pending hold and expire it
        $court2 = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Expiry Waitlist', 'is_active' => true]);
        $date2 = Carbon::now(config('app.timezone'))->addDays(2)->setTime(14, 0)->startOfHour();
        $this->priceCourt($court2);

        $this->actingAs($owner)->withHeader('Idempotency-Key', 'owner-hold')->postJson('/api/reservations', [
            'court_id' => $court2->id,
            'slots' => [$date2->toIso8601String()],
            'expected_total' => 100000,
        ])->assertCreated();

        $this->actingAs($waiter)->postJson('/api/waitlist', [
            'court_id' => $court2->id,
            'starts_at' => $date2->toIso8601String(),
        ])->assertCreated();

        // Expire pending hold -> releases held inventory and notifies waitlist
        $this->travel(31)->minutes();
        app(ExpirePendingBookings::class)->execute();

        // 4. Waitlist entry marked 'notified' and notification sent without automatic booking promises
        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $waiter->id,
            'court_id' => $court2->id,
            'status' => 'notified',
        ]);

        Notification::assertSentTo($waiter, AuraBookingNotification::class, function (AuraBookingNotification $n) {
            return $n->event === 'waitlist_available'
                && ! str_contains(strtolower($n->body), 'tự động')
                && str_contains($n->body, 'đặt ngay');
        });

        // 5. The waiter must complete normal checkout themselves
        $waiterBookRes = $this->actingAs($waiter)->withHeader('Idempotency-Key', 'waiter-checkout')->postJson('/api/reservations/checkout', [
            'court_id' => $court2->id,
            'slots' => [$date2->toIso8601String()],
            'expected_total' => 100000,
        ]);
        $waiterBookRes->assertCreated();
        $waiterBookRes->assertJsonPath('reservation.status', 'confirmed');
    }
}
