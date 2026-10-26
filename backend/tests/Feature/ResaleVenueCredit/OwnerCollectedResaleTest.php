<?php

namespace Tests\Feature\ResaleVenueCredit;

use App\Models\Court;
use App\Models\EntitlementTransfer;
use App\Models\Journal;
use App\Models\MarketplaceListing;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReservationEntitlement;
use App\Models\User;
use App\Models\VenueBookingPolicy;
use App\Models\VenueCreditEntry;
use App\Services\Finance\VenueCreditService;
use App\Services\Social\OwnerCollectedResaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\ResaleVenueCredit\Concerns\SellsSlots;
use Tests\TestCase;

/** advanced-booking-marketplace "Owner-collected marketplace transfer": the owner collects, the seller gets venue credit. */
class OwnerCollectedResaleTest extends TestCase
{
    use RefreshDatabase;
    use SellsSlots;

    private Court $court;

    private User $seller;

    private User $buyer;

    private MarketplaceListing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->court = $this->resaleCourt();
        $this->seller = $this->customer();
        $this->buyer = $this->customer();
        $this->listing = $this->listSlot($this->paidSlot($this->court, $this->seller), $this->seller, 80000);
    }

    public function test_eligible_slot_sells_through_the_owner_and_credits_the_seller(): void
    {
        $payment = $this->hold($this->listing, $this->buyer)->assertOk()
            ->assertJsonPath('payment.amount', 80000)->assertJsonPath('payment.receiver.account_number', self::ACCOUNT_NUMBER)
            ->assertJsonPath('lock.held_by_me', true)->json('payment');
        $this->assertStringStartsWith('AC', $payment['reference']);
        $this->assertSame($payment['expires_at'], OwnerPaymentAttempt::query()->where('purpose', 'resale')->firstOrFail()->expires_at->toIso8601String());

        $this->transfer($this->court, $payment['reference'], 80000)->assertOk();

        $this->assertSame('sold', $this->listing->fresh()->status);
        $transfer = EntitlementTransfer::query()->withoutGlobalScopes()->sole();
        $this->assertSame(['owner_collected', 0, 80000, $payment['reference']], [data_get($transfer->metadata, 'settlement_mode'), $transfer->marketplace_fee, $transfer->buyer_amount, $transfer->buyer_payment_reference]);
        $this->assertSame('transferred', ReservationEntitlement::query()->withoutGlobalScopes()->find($this->listing->reservation_entitlement_id)->status);
        $this->assertTrue(ReservationEntitlement::query()->withoutGlobalScopes()->where('user_id', $this->buyer->id)->where('status', 'active')->exists());
        $this->assertSame(80000, app(VenueCreditService::class)->balance($this->listing->organization_id, $this->seller->id));
        $this->assertSame(80000, $this->liability());
        $this->resale($this->seller, "marketplace/listings/{$this->listing->id}", $this->listing->organization_id, method: 'GET')
            ->assertOk()->assertJsonPath('credit_issued', 80000)->assertJsonPath('transfer.marketplace_fee', 0);
        // notifications.data is a text column, so read it through the model's array cast.
        $this->assertContains('resale_transferred', $this->seller->notifications()->pluck('data')->pluck('event'));
    }

    public function test_a_repeated_receipt_transfers_and_credits_once(): void
    {
        $reference = $this->hold($this->listing, $this->buyer)->json('payment.reference');
        $eventId = (string) Str::uuid();

        $this->transfer($this->court, $reference, 80000, eventId: $eventId)->assertOk();
        $this->transfer($this->court, $reference, 80000, eventId: $eventId)->assertOk();

        $this->assertSame(1, EntitlementTransfer::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, VenueCreditEntry::query()->withoutGlobalScopes()->where('type', 'issue')->count());
        $this->assertSame(1, Journal::query()->where('event_type', 'owner.direct_receipt')->where('metadata->purpose', 'resale')->count());
    }

    public function test_a_wrong_amount_is_an_owner_exception(): void
    {
        $reference = $this->hold($this->listing, $this->buyer)->json('payment.reference');

        $this->transfer($this->court, $reference, 50000)->assertOk();

        $this->assertNotSold('partial_amount', 'checkout');
    }

    public function test_payment_after_the_hold_expired_is_an_owner_exception(): void
    {
        $reference = $this->hold($this->listing, $this->buyer)->json('payment.reference');
        $this->travel(16)->minutes();
        app(OwnerCollectedResaleService::class)->sweep();

        $this->transfer($this->court, $reference, 80000)->assertOk();

        $this->assertNotSold('transfer_after_expiry', 'listed');
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, OwnerPaymentAttempt::query()->where('purpose', 'resale')->value('status'));
        $this->assertContains('resale_expired', $this->buyer->notifications()->pluck('data')->pluck('event'));
    }

    public function test_payment_after_the_buyer_cancelled_is_an_owner_exception(): void
    {
        $reference = $this->hold($this->listing, $this->buyer)->json('payment.reference');
        $this->resale($this->buyer, "marketplace/listings/{$this->listing->id}/release", $this->listing->organization_id)->assertOk();

        $this->transfer($this->court, $reference, 80000)->assertOk();

        $this->assertNotSold('transfer_after_expiry', 'listed');
    }

    public function test_a_slot_disputed_before_payment_is_not_transferred(): void
    {
        $reference = $this->hold($this->listing, $this->buyer)->json('payment.reference');
        $booking = ReservationEntitlement::query()->withoutGlobalScopes()->find($this->listing->reservation_entitlement_id)->reservation()->withoutGlobalScopes()->first();
        $booking->forceFill(['metadata' => ['is_disputed' => true]])->save();

        $this->transfer($this->court, $reference, 80000)->assertOk();

        $this->assertNotSold('resale_hold_unavailable', 'checkout');
    }

    public function test_a_buyer_restricted_before_payment_is_not_transferred(): void
    {
        $reference = $this->hold($this->listing, $this->buyer)->json('payment.reference');
        $this->buyer->forceFill(['status' => 'suspended'])->save();

        $this->transfer($this->court, $reference, 80000)->assertOk();

        $this->assertNotSold('resale_hold_unavailable', 'checkout');
    }

    public function test_only_one_of_two_buyers_holds_the_listing(): void
    {
        $this->hold($this->listing, $this->buyer)->assertOk();

        $this->hold($this->listing, $this->customer())->assertStatus(409);
        $this->assertSame(1, OwnerPaymentAttempt::query()->where('purpose', 'resale')->count());
    }

    public function test_a_buyer_holds_one_listing_at_a_time(): void
    {
        $other = $this->customer();
        $second = $this->listSlot($this->paidSlot($this->court, $other, '2026-11-05 19:00', '2026-11-05 20:00'), $other, 50000);
        $this->hold($this->listing, $this->buyer)->assertOk();

        $this->hold($second, $this->buyer)->assertStatus(409);

        $this->assertSame('listed', $second->fresh()->effective_status);
    }

    public function test_an_unverified_buyer_cannot_hold(): void
    {
        $this->hold($this->listing, User::factory()->unverified()->create())->assertForbidden();

        $this->assertSame('listed', $this->listing->fresh()->status);
    }

    public function test_a_venue_without_a_receiving_connection_cannot_sell_a_paid_slot(): void
    {
        $this->court->venue->forceFill(['current_receiving_connection_id' => null])->save();

        $this->hold($this->listing, $this->buyer)->assertStatus(409);

        $this->assertSame('listed', $this->listing->fresh()->status);
        $this->assertSame(0, OwnerPaymentAttempt::query()->where('purpose', 'resale')->count());
    }

    public function test_the_seller_cannot_release_or_withdraw_a_held_listing(): void
    {
        $this->hold($this->listing, $this->buyer)->assertOk();
        $org = $this->listing->organization_id;

        $this->resale($this->seller, "marketplace/listings/{$this->listing->id}/release", $org)->assertForbidden();
        $this->resale($this->seller, "marketplace/listings/{$this->listing->id}/withdraw", $org)->assertStatus(409);

        $this->assertSame($this->buyer->id, $this->listing->fresh()->lockedBy());
    }

    public function test_the_buyer_cancels_once_and_the_listing_returns_to_sale(): void
    {
        $this->hold($this->listing, $this->buyer)->assertOk();
        $uri = "marketplace/listings/{$this->listing->id}/release";

        $this->resale($this->buyer, $uri, $this->listing->organization_id)->assertOk()->assertJsonPath('status', 'listed');
        $this->resale($this->buyer, $uri, $this->listing->organization_id)->assertStatus(409);

        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, OwnerPaymentAttempt::query()->where('purpose', 'resale')->value('status'));
        $this->assertSame(0, VenueCreditEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_a_free_transfer_needs_acceptance_and_moves_without_payment_or_credit(): void
    {
        VenueBookingPolicy::query()->where('venue_id', $this->court->venue_id)->update(['settings' => ['hold_ttl_minutes' => 30, 'transfer' => ['free_transfer_allowed' => true]]]);
        $seller = $this->customer();
        $free = $this->listSlot($this->paidSlot($this->court, $seller, '2026-11-05 20:00', '2026-11-05 21:00'), $seller, 0);

        $this->hold($free, $this->buyer)->assertStatus(422);
        $this->hold($free, $this->buyer, ['accept_free_transfer' => true])->assertOk()->assertJsonPath('payment', null);

        $this->assertSame('sold', $free->fresh()->status);
        $this->assertSame(0, OwnerPaymentAttempt::query()->where('purpose', 'resale')->count());
        $this->assertSame(0, VenueCreditEntry::query()->withoutGlobalScopes()->count());
    }

    public function test_an_old_listing_with_a_stored_fee_pays_the_full_price_as_credit(): void
    {
        $this->listing->forceFill(['fee_bps' => 500])->save();
        $reference = $this->hold($this->listing, $this->buyer)->assertJsonPath('payment.amount', 80000)->json('payment.reference');

        $this->transfer($this->court, $reference, 80000)->assertOk();

        $this->assertSame(0, EntitlementTransfer::query()->withoutGlobalScopes()->value('marketplace_fee'));
        $this->assertSame(80000, app(VenueCreditService::class)->balance($this->listing->organization_id, $this->seller->id));
    }

    public function test_a_listing_never_asks_for_or_shows_a_seller_account(): void
    {
        $seller = $this->customer();
        $slot = $this->paidSlot($this->court, $seller, '2026-11-05 20:00', '2026-11-05 21:00');

        $response = $this->resale($seller, "marketplace/entitlements/{$slot->id}/list", $slot->organization_id, [
            'asking_price' => 60000, 'seller_receiver' => ['bank_code' => 'VCB', 'account_number' => '999999', 'account_name' => 'NGUOI BAN'],
        ])->assertCreated();

        $this->assertStringNotContainsString('999999', $response->getContent());
        $this->assertArrayNotHasKey('seller_receiver_ciphertext', MarketplaceListing::query()->withoutGlobalScopes()->findOrFail($response->json('listing.id'))->getAttributes());
    }

    private function assertNotSold(string $exceptionCode, string $listingStatus): void
    {
        $this->assertSame($listingStatus, $this->listing->fresh()->status);
        $this->assertSame($exceptionCode, OwnerPaymentReceipt::query()->latest('id')->value('exception_code'));
        $this->assertSame(0, EntitlementTransfer::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, VenueCreditEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, $this->liability());
    }

    /** What the organization owes in venue credit, from its ledger. */
    private function liability(): int
    {
        return (int) DB::table('journal_entries')->join('accounts', 'accounts.id', '=', 'journal_entries.account_id')
            ->where('accounts.code', 'owner_credit_liability')->where('accounts.organization_id', $this->listing->organization_id)
            ->selectRaw("COALESCE(SUM(CASE WHEN side = 'credit' THEN amount ELSE -amount END), 0) AS owed")->value('owed');
    }
}
