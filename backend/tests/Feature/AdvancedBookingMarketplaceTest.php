<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Payment\SplitPaymentService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Social\MarketplaceService;
use App\Services\Social\PeerToPeerResaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdvancedBookingMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_shares_must_sum_exactly_to_the_reservation_amount(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $reservation = $this->reservation($organization, $user, $court->id);

        $this->expectException(ValidationException::class);
        app(SplitPaymentService::class)->create($reservation, $user, [['amount' => 1], ['amount' => 2]]);
    }

    public function test_recurring_series_reserves_the_full_horizon_and_creates_monthly_invoices(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $startsAt = now(config('app.timezone'))->addDays(10)->setTime(8, 0);
        $result = app(RecurringSeriesService::class)->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $startsAt->toIso8601String(),
            'occurrences' => 3,
        ]);

        $this->assertSame('confirmed', $result->status);
        $this->assertCount(3, $result->occurrences);
        $this->assertNotEmpty($result->invoices);
    }

    public function test_reselling_one_hour_of_a_two_hour_booking_keeps_the_other_hour_with_the_seller(): void
    {
        [$organization, $seller, $court] = $this->tenant();
        $buyer = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $buyer->id, 'role' => 'customer', 'status' => 'active']);
        $reservation = $this->reservation($organization, $seller, $court->id, true, 2);
        [$first, $second] = $reservation->entitlements->sortBy('starts_at')->values()->all();

        $listing = app(MarketplaceService::class)->list($second, $seller, $second->amount, ['bank_code' => 'TPB', 'account_number' => '0123456789', 'account_name' => 'SELLER']);
        $resale = app(PeerToPeerResaleService::class);
        $resale->lock($listing, $buyer);
        $resale->paymentSent($listing, $buyer);
        $state = $resale->confirmReceipt($listing, $seller);

        $this->assertSame('sold', $state['status']);
        $this->assertSame('transferred', $second->fresh()->status);
        $this->assertSame(['active', $seller->id], [$first->fresh()->status, $first->fresh()->user_id]);
        $this->assertSame($seller->id, $reservation->fresh()->user_id);
        $this->assertTrue(ReservationEntitlement::where('user_id', $buyer->id)->where('starts_at', $second->starts_at)->where('status', 'active')->exists());
    }

    /** @return array{0:Organization,1:User,2:Court} */
    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Advanced Tenant',
            'slug' => 'advanced-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'organization_owner', 'status' => 'active']);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Advanced Venue',
            'slug' => 'advanced-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Advanced Court', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);
        config(['features.pilot_organizations' => [(string) $organization->id], 'features.flags.marketplace_split_payments' => true, 'features.flags.marketplace_slot_resale' => true]);
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $organization->id);
        $walletService->credit($wallet, 5000000, 'test_topup', 1, 'Fund for test');

        return [$organization, $user, $court];
    }

    private function reservation(Organization $organization, User $user, int $courtId, bool $future = false, int $hours = 1): Reservation
    {
        $date = now(config('app.timezone'))->addDays($future ? 4 : 2)->setTime(8, 0);
        $slots = array_map(fn (int $hour) => $date->copy()->addHours($hour)->toIso8601String(), range(0, $hours - 1));
        $response = $this->actingAs($user)->withHeaders(['X-Organization-ID' => (string) $organization->id, 'Idempotency-Key' => uniqid('reservation-')])->postJson('/api/reservations', ['court_id' => $courtId, 'slots' => $slots]);
        $response->assertCreated();
        $reservation = Reservation::where('code', $response->json('reservation.code'))->firstOrFail();
        $this->actingAs($user)->withHeaders(['X-Organization-ID' => (string) $organization->id, 'Idempotency-Key' => uniqid('confirm-')])->postJson('/api/reservations/'.$reservation->code.'/confirm')->assertOk();

        return $reservation->fresh('entitlements');
    }
}
