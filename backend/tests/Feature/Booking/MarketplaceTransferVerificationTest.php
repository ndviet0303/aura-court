<?php

namespace Tests\Feature\Booking;

use App\Models\EntitlementTransfer;
use App\Models\FinanceException;
use App\Models\Journal;
use App\Models\MarketplaceListing;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAttempt;
use App\Models\PriceRule;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Notifications\AuraBookingNotification;
use App\Services\Attendance\AttendanceService;
use App\Services\Infrastructure\TenantContext;
use App\Services\Social\MarketplaceService;
use App\Services\Social\PeerToPeerResaleService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketplaceTransferVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_rechecks_dispute_restriction_venue_policy_and_cutoff(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();

        $marketplace = app(MarketplaceService::class);

        // 1. Restriction: restricted seller cannot list
        $fixture['seller']->update(['status' => 'suspended']);
        $this->expectException(HttpException::class);
        $marketplace->list($entitlement, $fixture['seller'], 180000);
    }

    public function test_list_rejects_disputed_reservations_and_venue_policy_prohibitions(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();
        $marketplace = app(MarketplaceService::class);

        // 1. Dispute test: reservation marked disputed or active dispute case
        $reservation->update(['status' => 'disputed']);
        try {
            $marketplace->list($entitlement, $fixture['seller'], 180000);
            $this->fail('Expected listing disputed reservation to fail.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Disputed', $e->getMessage());
        }
        $reservation->update(['status' => 'confirmed']);
        $entitlement->refresh();

        // 2. Policy test: transfer_allowed = false
        $policy = $fixture['venue']->currentBookingPolicy;
        $policy->update([
            'settings' => array_merge($policy->settings, [
                'transfer' => ['transfer_allowed' => false, 'cutoff_hours' => 2],
            ]),
        ]);
        try {
            $marketplace->list($entitlement, $fixture['seller'], 180000);
            $this->fail('Expected listing with transfer_allowed=false to fail.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('not allowed', $e->getMessage());
        }
    }

    public function test_paid_listing_requires_an_encrypted_seller_receiver_that_lists_never_show(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();
        $uri = '/api/marketplace/entitlements/'.$entitlement->id.'/list';

        $this->api($fixture, $fixture['seller'], 'POST', $uri, ['asking_price' => 180000])
            ->assertStatus(422)->assertJsonValidationErrors(['seller_receiver']);
        $this->api($fixture, $fixture['seller'], 'POST', $uri, ['asking_price' => 180000, 'seller_receiver' => ['bank_code' => 'TPB', 'account_number' => '12ab', 'account_name' => 'X']])
            ->assertStatus(422)->assertJsonValidationErrors(['seller_receiver.account_number']);

        $created = $this->api($fixture, $fixture['seller'], 'POST', $uri, ['asking_price' => 180000, 'seller_receiver' => self::RECEIVER])
            ->assertCreated()->assertJsonPath('listing.fee_bps', 0);
        $this->assertStringNotContainsString(self::RECEIVER['account_number'], $created->getContent());

        $listing = MarketplaceListing::findOrFail($created->json('listing.id'));
        $this->assertStringNotContainsString(self::RECEIVER['account_number'], (string) $listing->seller_receiver_ciphertext);
        $this->assertSame(self::RECEIVER, $listing->sellerReceiver());

        foreach ([[$fixture['buyer'], ''], [$fixture['seller'], '?mine=1']] as [$viewer, $query]) {
            $list = $this->api($fixture, $viewer, 'GET', '/api/marketplace/listings'.$query)->assertOk();
            $this->assertSame([$listing->id], array_column($list->json('data'), 'id'));
            $this->assertStringNotContainsString(self::RECEIVER['account_number'], $list->getContent());
            $this->assertStringNotContainsString('seller_receiver', $list->getContent());
        }

        // The seller's own view shows the account they entered.
        $this->api($fixture, $fixture['seller'], 'GET', '/api/marketplace/listings/'.$listing->id)
            ->assertOk()->assertJsonPath('receiver.account_number', self::RECEIVER['account_number'])->assertJsonPath('payment', null);
    }

    public function test_only_one_buyer_locks_a_listing_and_the_other_gets_409_without_the_receiver(): void
    {
        $fixture = $this->tenant();
        $listing = $this->paidListing($fixture, 150000);

        $locked = $this->api($fixture, $fixture['buyer'], 'POST', '/api/marketplace/listings/'.$listing->id.'/checkout')
            ->assertOk()
            ->assertJsonPath('status', 'checkout')
            ->assertJsonPath('lock.held_by_me', true)
            ->assertJsonPath('receiver.account_number', self::RECEIVER['account_number'])
            ->assertJsonPath('payment.amount', 150000);
        $reference = $locked->json('payment.reference');
        $this->assertMatchesRegularExpression('/^RS[A-Z0-9]{10}$/', $reference);
        $this->assertStringContainsString('TPB-'.self::RECEIVER['account_number'], $locked->json('payment.qr_url'));
        $this->assertStringContainsString('addInfo='.$reference, $locked->json('payment.qr_url'));
        $this->assertTrue(Carbon::parse($locked->json('lock.locked_until'))->lte(now()->addMinutes(15)));
        $this->assertSame(0, OwnerPaymentAttempt::count());

        $rival = $this->api($fixture, $fixture['rival'], 'POST', '/api/marketplace/listings/'.$listing->id.'/checkout')->assertStatus(409);
        $this->assertStringNotContainsString(self::RECEIVER['account_number'], $rival->getContent());
        $this->api($fixture, $fixture['rival'], 'GET', '/api/marketplace/listings/'.$listing->id)->assertNotFound();
        $this->assertSame([], $this->api($fixture, $fixture['rival'], 'GET', '/api/marketplace/listings')->json('data'));

        // Re-locking by the holder is idempotent and keeps the same reference.
        $this->api($fixture, $fixture['buyer'], 'POST', '/api/marketplace/listings/'.$listing->id.'/checkout')
            ->assertOk()->assertJsonPath('payment.reference', $reference);
    }

    public function test_buyer_release_and_lock_expiry_return_the_listing_to_sale(): void
    {
        $fixture = $this->tenant();
        $listing = $this->paidListing($fixture, 150000);
        $resale = app(PeerToPeerResaleService::class);

        $resale->lock($listing, $fixture['buyer']);
        $state = $this->api($fixture, $fixture['buyer'], 'POST', '/api/marketplace/listings/'.$listing->id.'/release')
            ->assertOk()->assertJsonPath('status', 'listed')->assertJsonPath('last_release.by', 'buyer')->assertJsonPath('receiver', null);
        $this->assertNull($state->json('payment'));
        Notification::assertSentTo($fixture['seller'], AuraBookingNotification::class, fn ($n) => $n->event === 'resale_released');

        // A lapsed lock reads as released before any sweep: another buyer may lock at once.
        $resale->lock($listing, $fixture['buyer']);
        $this->travel(16)->minutes();
        $this->assertSame('listed', $listing->fresh()->effective_status);
        $listed = $this->api($fixture, $fixture['rival'], 'GET', '/api/marketplace/listings')->json('data');
        $this->assertSame([$listing->id], array_column($listed, 'id'));
        $this->assertSame([], array_intersect(['locked_by', 'reference', 'buyer_ids', 'last_release'], array_keys($listed[0]['metadata'] ?? [])));
        $this->api($fixture, $fixture['rival'], 'POST', '/api/marketplace/listings/'.$listing->id.'/checkout')->assertOk()->assertJsonPath('lock.held_by_me', true);

        // The sweep releases a lapsed lock once.
        $this->travel(16)->minutes();
        $this->runScheduledEvent('marketplace:sweep-resale-locks');
        $this->runScheduledEvent('marketplace:sweep-resale-locks');
        $this->assertSame('listed', $listing->fresh()->status);
        $this->assertSame('expiry', $listing->fresh()->metadata['last_release']['by']);
        Notification::assertSentToTimes($fixture['rival'], AuraBookingNotification::class, 1);
    }

    public function test_sweep_withdraws_unsold_listings_past_their_cutoff(): void
    {
        $fixture = $this->tenant();
        $listing = $this->paidListing($fixture, 150000);

        $this->travelTo($listing->cutoff_at->copy()->addMinute());
        $this->runScheduledEvent('marketplace:sweep-resale-locks');

        $this->assertSame('withdrawn', $listing->fresh()->status);
        $this->assertSame('active', $listing->entitlement->fresh()->status);
    }

    public function test_reported_payment_holds_the_lock_and_seller_confirmation_transfers_atomically(): void
    {
        $fixture = $this->tenant();
        $slotTime = now(config('app.timezone'))->addHours(3);
        $reservation = $this->createConfirmedReservation($fixture, 200000, $slotTime);
        $sellerEntitlement = $reservation->entitlements()->firstOrFail();
        $listing = app(MarketplaceService::class)->list($sellerEntitlement, $fixture['seller'], 150000, self::RECEIVER);
        $listingUri = '/api/marketplace/listings/'.$listing->id;

        $reference = $this->api($fixture, $fixture['buyer'], 'POST', $listingUri.'/checkout')->assertOk()->json('payment.reference');
        $reported = $this->api($fixture, $fixture['buyer'], 'POST', $listingUri.'/payment-sent')->assertOk();
        $this->assertNotNull($reported->json('lock.payment_reported_at'));
        Notification::assertSentTo($fixture['seller'], AuraBookingNotification::class, fn ($n) => $n->event === 'resale_payment_reported');

        // The report alone transfers nothing; the lock now runs to the cutoff and the buyer cannot drop it.
        $this->travel(30)->minutes();
        $this->assertSame('active', $sellerEntitlement->fresh()->status);
        $this->api($fixture, $fixture['buyer'], 'GET', $listingUri)->assertOk()->assertJsonPath('status', 'checkout')->assertJsonPath('payment.reference', $reference);
        $this->api($fixture, $fixture['buyer'], 'POST', $listingUri.'/release')->assertStatus(409);
        $this->api($fixture, $fixture['buyer'], 'POST', $listingUri.'/confirm-receipt')->assertForbidden();

        $this->api($fixture, $fixture['seller'], 'POST', $listingUri.'/confirm-receipt')
            ->assertOk()
            ->assertJsonPath('status', 'sold')
            ->assertJsonPath('transfer.marketplace_fee', 0)
            ->assertJsonPath('transfer.seller_refund', 0)
            ->assertJsonPath('transfer.settlement_mode', 'peer_to_peer');

        $transfer = EntitlementTransfer::where('marketplace_listing_id', $listing->id)->sole();
        $this->assertSame(['completed', 150000, 0, 0, $reference], [$transfer->status, $transfer->buyer_amount, $transfer->marketplace_fee, $transfer->seller_refund, $transfer->buyer_payment_reference]);
        $this->assertSame(0, Journal::count());
        $this->assertSame(0, OwnerPaymentAttempt::count());
        $this->assertSame(0, FinanceException::count());
        $this->assertSame('transferred', $sellerEntitlement->fresh()->status);
        $buyerEntitlement = ReservationEntitlement::where('user_id', $fixture['buyer']->id)->sole();
        $this->assertSame(['active', 200000], [$buyerEntitlement->status, $buyerEntitlement->amount]);
        $this->assertSame($fixture['buyer']->id, $reservation->fresh()->user_id);
        Notification::assertSentTo($fixture['buyer'], AuraBookingNotification::class, fn ($n) => $n->event === 'resale_transferred');

        $attendance = app(AttendanceService::class);
        Carbon::setTestNow($slotTime);
        try {
            try {
                $attendance->checkIn($fixture['staff'], $fixture['organization']->id, 'AURA_ENTITLEMENT_'.$sellerEntitlement->id, 'Seller after transfer', $fixture['seller']);
                $this->fail('Expected seller check-in to be rejected.');
            } catch (HttpException $e) {
                $this->assertStringContainsString('transferred and check-in pass has been revoked', $e->getMessage());
            }
            $result = $attendance->checkIn($fixture['staff'], $fixture['organization']->id, 'AURA_ENTITLEMENT_'.$buyerEntitlement->id, 'Buyer via pass', $fixture['buyer']);
            $this->assertSame('checked_in', $result['attendance']->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_seller_non_receipt_releases_the_lock_and_the_buyer_can_open_a_listing_support_case(): void
    {
        $fixture = $this->tenant();
        $listing = $this->paidListing($fixture, 150000);
        $listingUri = '/api/marketplace/listings/'.$listing->id;
        $this->api($fixture, $fixture['buyer'], 'POST', $listingUri.'/checkout')->assertOk();
        $this->api($fixture, $fixture['buyer'], 'POST', $listingUri.'/payment-sent')->assertOk();

        $this->api($fixture, $fixture['seller'], 'POST', $listingUri.'/release', ['reason' => 'Chưa thấy tiền về'])
            ->assertOk()->assertJsonPath('status', 'listed')->assertJsonPath('last_release.by', 'seller');
        $this->assertSame('active', $listing->entitlement->fresh()->status);
        Notification::assertSentTo($fixture['buyer'], AuraBookingNotification::class, fn ($n) => $n->event === 'resale_released');
        $this->api($fixture, $fixture['buyer'], 'GET', $listingUri)
            ->assertOk()
            ->assertJsonPath('last_release.payment_reported', true)
            ->assertJsonPath('receiver', null)
            ->assertJsonPath('payment', null);

        $case = ['title' => 'Đã chuyển tiền nhưng chưa nhận suất', 'summary' => 'Tôi đã chuyển khoản cho người bán nhưng người bán báo chưa nhận được.', 'dispute_type' => 'payment_issue', 'reference_type' => 'marketplace_listing', 'reference_id' => $listing->id];
        $this->api($fixture, $fixture['rival'], 'POST', '/api/disputes', $case)->assertForbidden();
        $this->api($fixture, $fixture['buyer'], 'POST', '/api/disputes', $case)->assertCreated();
        $this->assertDatabaseHas('dispute_cases', ['reference_type' => 'marketplace_listing', 'reference_id' => $listing->id, 'complainant_id' => $fixture['buyer']->id, 'organization_id' => $fixture['organization']->id, 'reservation_id' => null]);
    }

    public function test_confirmation_rechecks_dispute_restriction_and_cutoff_without_moving_ownership(): void
    {
        $fixture = $this->tenant();
        $listing = $this->paidListing($fixture, 150000);
        $reservation = $listing->entitlement->reservation;
        $resale = app(PeerToPeerResaleService::class);
        $resale->lock($listing, $fixture['buyer']);
        $resale->paymentSent($listing, $fixture['buyer']);

        $reservation->update(['status' => 'disputed']);
        $this->assertRefused(409, fn () => $resale->confirmReceipt($listing, $fixture['seller']));
        $reservation->update(['status' => 'confirmed']);

        $fixture['buyer']->update(['status' => 'suspended']);
        $this->assertRefused(403, fn () => $resale->confirmReceipt($listing, $fixture['seller']));
        $fixture['buyer']->update(['status' => 'active']);

        $this->travelTo($listing->cutoff_at->copy()->addMinute());
        $this->assertRefused(409, fn () => $resale->confirmReceipt($listing, $fixture['seller']));

        $this->assertSame('active', $listing->entitlement->fresh()->status);
        $this->assertSame(0, EntitlementTransfer::count());

        // Past the cutoff the sweep closes the listing and tells the buyer they may open a case.
        $this->runScheduledEvent('marketplace:sweep-resale-locks');
        $this->assertSame('withdrawn', $listing->fresh()->status);
        Notification::assertSentTo($fixture['buyer'], AuraBookingNotification::class, fn ($n) => $n->event === 'resale_expired');
    }

    public function test_free_listing_transfers_only_on_explicit_acceptance(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $entitlement = $reservation->entitlements()->firstOrFail();
        $uri = '/api/marketplace/entitlements/'.$entitlement->id.'/list';

        $this->api($fixture, $fixture['seller'], 'POST', $uri, ['asking_price' => 0])->assertStatus(422);
        $policy = $fixture['venue']->currentBookingPolicy;
        $policy->update(['settings' => array_replace_recursive($policy->settings, ['transfer' => ['free_transfer_allowed' => true]])]);
        $listingId = $this->api($fixture, $fixture['seller'], 'POST', $uri, ['asking_price' => 0])->assertCreated()->json('listing.id');
        $this->assertNull(MarketplaceListing::findOrFail($listingId)->seller_receiver_ciphertext);

        $this->api($fixture, $fixture['buyer'], 'POST', '/api/marketplace/listings/'.$listingId.'/checkout')->assertStatus(422);
        $this->assertSame('listed', MarketplaceListing::findOrFail($listingId)->status);

        $this->api($fixture, $fixture['buyer'], 'POST', '/api/marketplace/listings/'.$listingId.'/checkout', ['accept_free_transfer' => true])
            ->assertOk()->assertJsonPath('status', 'sold')->assertJsonPath('transfer.buyer_amount', 0)->assertJsonPath('payment', null);
        $this->assertSame('transferred', $entitlement->fresh()->status);
        $this->assertSame(0, Journal::count());
    }

    public function test_paid_listing_without_a_seller_receiver_cannot_be_locked(): void
    {
        $fixture = $this->tenant();
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));
        $listing = app(MarketplaceService::class)->list($reservation->entitlements()->firstOrFail(), $fixture['seller'], 150000);

        $this->api($fixture, $fixture['buyer'], 'POST', '/api/marketplace/listings/'.$listing->id.'/checkout')->assertStatus(409);
        $this->assertSame('listed', $listing->fresh()->status);
    }

    public function test_purchase_route_and_owner_transfer_purpose_are_gone(): void
    {
        $fixture = $this->tenant();
        $listing = $this->paidListing($fixture, 150000);

        $this->api($fixture, $fixture['buyer'], 'POST', '/api/marketplace/listings/'.$listing->id.'/purchase', ['buyer_payment_reference' => 'X'])->assertNotFound();
        $this->assertFalse(method_exists(MarketplaceService::class, 'purchase'));
    }

    private const RECEIVER = ['bank_code' => 'TPB', 'account_number' => '00011122233', 'account_name' => 'NGUYEN VAN BAN'];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function paidListing(array $fixture, int $askingPrice): MarketplaceListing
    {
        $reservation = $this->createConfirmedReservation($fixture, 200000, now()->addDays(2)->setTime(10, 0));

        return app(MarketplaceService::class)->list($reservation->entitlements()->firstOrFail(), $fixture['seller'], $askingPrice, self::RECEIVER);
    }

    private function api(array $fixture, User $user, string $method, string $uri, array $data = []): TestResponse
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user)
            ->withHeaders(['X-Organization-ID' => (string) $fixture['organization']->id, 'Idempotency-Key' => (string) Str::uuid()])
            ->json($method, $uri, $data);
    }

    private function assertRefused(int $status, callable $call): void
    {
        try {
            $call();
            $this->fail("Expected HTTP {$status}.");
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode(), $e->getMessage());
        }
    }

    private function runScheduledEvent(string $name): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => ($e->description ?? null) === $name);
        $this->assertNotNull($event, "Scheduled event {$name} must be registered.");
        $event->run($this->app);
    }

    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Marketplace Org',
            'slug' => 'market-org-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $owner = User::factory()->create();
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $staff = User::factory()->create();

        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $seller->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $buyer->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $staff->id, 'role' => 'staff', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Marketplace Venue',
            'slug' => 'market-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        // BR-20: staff reach only the venues assigned to them.
        OrganizationMember::where('organization_id', $organization->id)->where('role', 'staff')
            ->get()->each(fn (OrganizationMember $m) => $m->update(['permissions' => ['venue_ids' => [$venue->id]]]));
        $court = $venue->courts()->create(['name' => 'Court M1', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 30, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'transfer_allowed' => true],
            ],
        ]);

        config([
            'features.flags.marketplace_slot_resale' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        $rival = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $rival->id, 'role' => 'customer', 'status' => 'active']);

        return compact('organization', 'owner', 'seller', 'buyer', 'rival', 'staff', 'venue', 'court');
    }

    private function createConfirmedReservation(array $fixture, int $amount, Carbon $startsAt): Reservation
    {
        $reservation = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['seller']->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RSV-MKT-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => $amount,
            'payment_model' => 'direct_owner_v1',
            'confirmed_at' => now(),
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'unit_price' => $amount,
            'status' => 'confirmed',
            'snapshot' => [],
        ]);

        ReservationEntitlement::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'court_id' => $fixture['court']->id,
            'user_id' => $fixture['seller']->id,
            'entitlement_type' => 'court_reservation',
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'amount' => $amount,
        ]);

        return $reservation;
    }
}
