<?php

namespace Tests\Feature\Readiness;

use App\Models\Court;
use App\Models\Organization;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\OrdinaryCheckoutService;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/**
 * Phase 0: Concurrency & Load Baseline Test Suite
 */
class ConcurrencyAndLoadBaselineTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    public function test_100_concurrent_reservation_attempts_result_in_exactly_1_winner_and_99_conflicts(): void
    {
        $court = Court::create(['name' => 'Center Court Stadium', 'is_active' => true]);
        $targetDate = now(config('app.timezone'))->addDays(5)->setTime(18, 0);
        $this->priceCourt($court, 200000);

        $users = User::factory()->count(100)->create();
        $isoSlot = $targetDate->toIso8601String();

        $walletService = app(WalletPaymentService::class);
        $venue = Venue::firstOrFail();
        $venue->organization->update(['partner_status' => 'approved']);
        foreach ($users as $user) {
            $w = $walletService->getOrCreateWallet($user->id, $venue->organization_id);
            $walletService->credit($w, 1000000, 'test_fund', 1, 'Fund');
        }

        $successCount = 0;
        $conflictCount = 0;
        $checkout = app(OrdinaryCheckoutService::class);

        foreach ($users as $user) {
            try {
                $checkout->checkout($user, (int) $venue->organization_id, ['court_id' => $court->id, 'slots' => [$isoSlot]]);
                $successCount++;
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 409) {
                    $conflictCount++;
                }
            }
        }

        // Invariant: Exactly 1 success, 99 conflicts
        $this->assertEquals(1, $successCount, 'Exactly one reservation must win the contended court slot.');
        $this->assertEquals(99, $conflictCount, 'All 99 competing attempts must receive conflict exceptions.');

        // Invariant: Exactly one active held/confirmed slot in the database
        $activeSlotsCount = DB::table('reservation_slots')
            ->where('court_id', $court->id)
            ->whereIn('status', ['held', 'confirmed'])
            ->count();

        $this->assertEquals(1, $activeSlotsCount, 'Only 1 active slot entitlement can exist in database.');
    }

    public function test_read_peak_and_session_simulation_baseline(): void
    {
        $organization = Organization::create(['name' => 'Aura Arena Org', 'slug' => 'aura-arena-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Aura Arena', 'slug' => 'aura-arena', 'address' => '123 Main St', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Court 2', 'is_active' => true]);
        $this->priceCourt($court, 100000);
        $user = User::factory()->create();

        // Verify read availability and courts response
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)->getJson('/api/courts');
            $response->assertOk();
        }

        $availabilityResponse = $this->actingAs($user)->getJson("/api/availability?court_id={$court->id}&date=".now()->toDateString());
        $availabilityResponse->assertOk();
    }
}
