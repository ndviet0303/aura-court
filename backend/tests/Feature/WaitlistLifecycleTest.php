<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\WaitlistEntry;
use App\Notifications\AuraBookingNotification;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WaitlistLifecycleTest extends TestCase
{
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

        $res = $this->actingAs($user)->postJson('/api/v1/waitlist', [
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
        $this->fundUserWallet($owner, $venue->organization_id, 500000);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Occupied Waitlist', 'is_active' => true]);
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        // 1. Owner books the slot
        $this->actingAs($owner)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ])->assertCreated();

        // 2. Waiter joins waitlist
        $joinRes = $this->actingAs($waiter)->postJson('/api/v1/waitlist', [
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
        $dupRes = $this->actingAs($waiter)->postJson('/api/v1/waitlist', [
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
        $this->fundUserWallet($owner, $venue->organization_id, 500000);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Leave Waitlist', 'is_active' => true]);
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        // Book slot
        $this->actingAs($owner)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ])->assertCreated();

        // Join waitlist
        $joinRes = $this->actingAs($waiter)->postJson('/api/v1/waitlist', [
            'court_id' => $court->id,
            'starts_at' => $date->toIso8601String(),
        ])->assertCreated();
        $entryId = $joinRes->json('id');

        // Leave waitlist via DELETE
        $delRes = $this->actingAs($waiter)->deleteJson("/api/v1/waitlist/{$entryId}");
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
        $this->fundUserWallet($owner, $venue->organization_id, 500000);
        $this->fundUserWallet($waiter, $venue->organization_id, 500000);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Notify Waitlist', 'is_active' => true]);
        $date = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        // 1. Owner books
        $bookRes = $this->actingAs($owner)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $bookRes->assertCreated();
        $bookingCode = $bookRes->json('booking.code');

        // 2. Waiter joins waitlist
        $this->actingAs($waiter)->postJson('/api/v1/waitlist', [
            'court_id' => $court->id,
            'starts_at' => $date->toIso8601String(),
        ])->assertCreated();

        // 3. Owner cancels booking -> releases slot
        $cancelRes = $this->actingAs($owner)->postJson("/api/v1/bookings/{$bookingCode}/cancel");
        $cancelRes->assertOk();

        // 4. Waitlist entry marked 'notified' and notification sent without automatic booking promises
        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $waiter->id,
            'court_id' => $court->id,
            'status' => 'notified',
        ]);

        Notification::assertSentTo($waiter, AuraBookingNotification::class, function (AuraBookingNotification $n) {
            return $n->event === 'waitlist_available'
                && ! str_contains(strtolower($n->body), 'tự động')
                && str_contains($n->body, 'đặt ngay');
        });

        // 5. The waiter must complete normal checkout themselves
        $waiterBookRes = $this->actingAs($waiter)->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$date->toIso8601String()],
        ]);
        $waiterBookRes->assertCreated();
        $waiterBookRes->assertJsonPath('booking.status', 'confirmed');
    }
}
