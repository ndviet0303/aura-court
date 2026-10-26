<?php

namespace Tests\Feature\ResaleVenueCredit\Concerns;

use App\Models\Court;
use App\Models\MarketplaceListing;
use App\Models\ReservationEntitlement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BooksCourts;

/**
 * Owner-collected resale as the app sends it: a paid booking, its slot listed, a buyer's hold and the owner's receipt.
 *
 *     $court = $this->resaleCourt();
 *     $listing = $this->listSlot($this->paidSlot($court, $seller), $seller, 80000);
 *     $reference = $this->hold($listing, $buyer)->assertOk()->json('payment.reference');
 *     $this->transfer($court, $reference, 80000);
 */
trait SellsSlots
{
    use BooksCourts;

    /** Today is 2026-11-03 09:00 in tests that use this; the booked slot is two days later. */
    protected function resaleCourt(int $pricePerHour = 100000): Court
    {
        Carbon::setTestNow('2026-11-03 09:00:00');
        config(['features.flags.marketplace_slot_resale' => true]);

        return $this->payableCourt($pricePerHour);
    }

    /** A booking the seller paid in full; returns its one active slot. */
    protected function paidSlot(Court $court, User $seller, string $start = '2026-11-05 18:00', string $end = '2026-11-05 19:00', int $total = 100000): ReservationEntitlement
    {
        $code = $this->checkout($seller, $court, $start, $end, $total)->assertCreated()->json('reservation.code');
        $this->transfer($court, $this->paymentCode($code), $total)->assertOk();

        return ReservationEntitlement::query()->withoutGlobalScopes()->where('user_id', $seller->id)->where('status', 'active')->latest('id')->firstOrFail();
    }

    protected function listSlot(ReservationEntitlement $slot, User $seller, int $price): MarketplaceListing
    {
        $id = $this->resale($seller, "marketplace/entitlements/{$slot->id}/list", $slot->organization_id, ['asking_price' => $price])->assertCreated()->json('listing.id');

        return MarketplaceListing::query()->withoutGlobalScopes()->findOrFail($id);
    }

    protected function hold(MarketplaceListing $listing, User $buyer, array $body = []): TestResponse
    {
        return $this->resale($buyer, "marketplace/listings/{$listing->id}/checkout", $listing->organization_id, $body);
    }

    /** @param  array<string, mixed>  $body */
    protected function resale(User $user, string $uri, int $organizationId, array $body = [], string $method = 'POST'): TestResponse
    {
        // A customer is not yet a member of the venue's organization, so it names it as input, not the member-only header.
        return $this->actingAs($user)->json($method, "/api/{$uri}", $body + ['organization_id' => $organizationId], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
    }
}
