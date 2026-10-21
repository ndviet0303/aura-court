<?php

namespace Tests\Feature\Reservation;

use App\Models\Court;
use App\Models\CourtPriceTableLink;
use App\Models\MatchPost;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

/** Step-14 / BR-37: a quote issued before a suspension, pause or closure must not still turn into a booking. */
class InflightQuoteEligibilityTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    private Organization $org;

    private Venue $venue;

    private Court $court;

    private User $owner;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Inflight Org', 'slug' => 'inflight-org', 'status' => 'active', 'partner_status' => 'approved']);
        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->customer = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $this->org->id, 'user_id' => $this->owner->id, 'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $this->org->id, 'user_id' => $this->customer->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $this->venue = Venue::create(['organization_id' => $this->org->id, 'name' => 'Sân Inflight', 'slug' => 'san-inflight', 'status' => 'published', 'is_active' => true]);
        $this->court = Court::create(['venue_id' => $this->venue->id, 'name' => 'Sân 1', 'is_active' => true]);
        $this->priceCourt($this->court, 150000);

        $wallets = app(WalletPaymentService::class);
        $wallets->credit($wallets->getOrCreateWallet($this->customer->id, $this->org->id), 1000000, 'test_topup', 1, 'Fund for test');
    }

    /** @return array<string, array{string, string}> */
    public static function lapses(): array
    {
        return [
            'venue suspended by the platform' => ['suspended', 'Cơ sở thể thao chưa được xuất bản hoặc đang tạm ngừng hoạt động.'],
            'venue paused' => ['temporarily_closed', 'Cơ sở thể thao chưa được xuất bản hoặc đang tạm ngừng hoạt động.'],
            'court deactivated' => ['court_inactive', 'Sân không tồn tại hoặc đã ngừng hoạt động.'],
        ];
    }

    #[DataProvider('lapses')]
    public function test_a_quote_issued_before_the_lapse_can_no_longer_be_held_or_checked_out(string $lapse, string $message): void
    {
        $quote = $this->quote(10);
        match ($lapse) {
            'suspended' => $this->venue->update(['status' => 'suspended', 'is_active' => false]),
            'temporarily_closed' => $this->venue->update(['status' => 'temporarily_closed']),
            'court_inactive' => $this->court->update(['is_active' => false]),
        };

        $this->as($this->customer)->postJson('/api/reservations', ['quote_token' => $quote['quote_token'], 'expected_total' => $quote['total_amount']])
            ->assertStatus(409)->assertJsonPath('message', $message);
        $this->as($this->customer)->postJson('/api/reservations/checkout', ['quote_token' => $quote['quote_token'], 'expected_total' => $quote['total_amount']])
            ->assertStatus(409)->assertJsonPath('message', $message);

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseHas('price_quotes', ['id' => $quote['id'], 'status' => 'open']);
    }

    public function test_a_quote_issued_before_the_plan_lapsed_can_no_longer_be_held_or_checked_out(): void
    {
        config(['partner.enforce_subscription_check' => true]);
        $plan = PlatformPlan::create(['code' => 'PL_INFLIGHT', 'name' => 'Inflight Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        $subscription = PlatformSubscription::create(['organization_id' => $this->org->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'ends_at' => now()->addDay()]);
        $quote = $this->quote(10);
        $subscription->update(['ends_at' => now()->subMinute()]);

        $this->as($this->customer)->postJson('/api/reservations', ['quote_token' => $quote['quote_token'], 'expected_total' => $quote['total_amount']])->assertStatus(409);
        $this->as($this->customer)->postJson('/api/reservations/checkout', ['quote_token' => $quote['quote_token'], 'expected_total' => $quote['total_amount']])->assertStatus(409);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_a_quote_on_an_eligible_court_still_holds_and_checks_out(): void
    {
        [$held, $paid] = [$this->quote(10), $this->quote(12)];

        $this->as($this->customer)->postJson('/api/reservations', ['quote_token' => $held['quote_token'], 'expected_total' => $held['total_amount']])
            ->assertCreated()->assertJsonPath('reservation.status', 'held');
        $this->as($this->customer)->postJson('/api/reservations/checkout', ['quote_token' => $paid['quote_token'], 'expected_total' => $paid['total_amount']])
            ->assertCreated()->assertJsonPath('reservation.status', 'confirmed');

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_hold_and_checkout_lock_the_subscription_before_the_court_like_quote_creation(): void
    {
        $quotes = [$this->quote(10), $this->quote(12)];

        foreach (['/api/reservations', '/api/reservations/checkout'] as $i => $uri) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->as($this->customer)->postJson($uri, ['quote_token' => $quotes[$i]['quote_token'], 'expected_total' => $quotes[$i]['total_amount']])->assertCreated();
            // An opposite order to quote creation (subscription, then court) lets two bookings deadlock.
            $locks = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $q) => str_contains($q, 'for update'))->values();
            $subscriptionLock = $locks->search(fn (string $q) => preg_match('/\bfrom\W+platform_subscriptions\b/', $q) === 1);
            $this->assertIsInt($subscriptionLock, "{$uri} never locks the subscription");
            $this->assertLessThan($locks->search(fn (string $q) => preg_match('/\bfrom\W+courts\b/', $q) === 1), $subscriptionLock, $uri);
        }
    }

    public function test_owner_cannot_open_a_hosted_session_on_a_paused_venue_or_inactive_court(): void
    {
        config(['features.flags.social_matchmaking_tournaments' => true, 'features.pilot_organizations' => [(string) $this->org->id]]);

        $this->venue->update(['status' => 'temporarily_closed']);
        $this->as($this->owner)->postJson('/api/social/matches', $this->hostedSession())
            ->assertStatus(409)->assertJsonPath('message', 'Cơ sở thể thao chưa được xuất bản hoặc đang tạm ngừng hoạt động.');

        $this->venue->update(['status' => 'published']);
        $this->court->update(['is_active' => false]);
        $this->as($this->owner)->postJson('/api/social/matches', $this->hostedSession())
            ->assertStatus(409)->assertJsonPath('message', 'Sân không tồn tại hoặc đã ngừng hoạt động.');
        $this->assertSame(0, MatchPost::query()->count());

        // A session carries its own price, so an unpriced court may still host one.
        $this->court->update(['is_active' => true]);
        CourtPriceTableLink::query()->delete();
        $this->as($this->owner)->postJson('/api/social/matches', $this->hostedSession())->assertCreated();
    }

    public function test_owner_cannot_move_a_hosted_session_onto_a_paused_venue_or_inactive_court(): void
    {
        config(['features.flags.social_matchmaking_tournaments' => true, 'features.pilot_organizations' => [(string) $this->org->id]]);
        $postId = $this->as($this->owner)->postJson('/api/social/matches', $this->hostedSession())->assertCreated()->json('match.id');
        $pausedVenue = tap(Venue::create(['organization_id' => $this->org->id, 'name' => 'Sân Tạm Nghỉ', 'slug' => 'san-tam-nghi', 'is_active' => true]))->update(['status' => 'temporarily_closed']);
        $targets = [
            'Cơ sở thể thao chưa được xuất bản hoặc đang tạm ngừng hoạt động.' => Court::create(['venue_id' => $pausedVenue->id, 'name' => 'Sân 1', 'is_active' => true]),
            'Sân không tồn tại hoặc đã ngừng hoạt động.' => Court::create(['venue_id' => $this->venue->id, 'name' => 'Sân 2', 'is_active' => false]),
        ];

        foreach ($targets as $message => $court) {
            $this->as($this->owner)->putJson("/api/social/matches/{$postId}", ['court_id' => $court->id])
                ->assertStatus(409)->assertJsonPath('message', $message);
        }
        $this->assertDatabaseHas('match_posts', ['id' => $postId, 'court_id' => $this->court->id]);

        // Servicing the session on its own court stays allowed after its venue is paused.
        $this->venue->update(['status' => 'temporarily_closed']);
        $this->as($this->owner)->putJson("/api/social/matches/{$postId}", ['title' => 'Đổi tên buổi'])->assertOk();
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withHeaders(['X-Organization-ID' => (string) $this->org->id, 'Idempotency-Key' => uniqid('inflight-', true)]);
    }

    /** @return array<string, mixed> */
    private function quote(int $hour): array
    {
        return $this->as($this->customer)
            ->postJson('/api/price-quotes', ['court_id' => $this->court->id, 'slots' => [now(config('app.timezone'))->addDays(4)->setTime($hour, 0)->toIso8601String()]])
            ->assertCreated()->json('quote');
    }

    /** @return array<string, mixed> */
    private function hostedSession(): array
    {
        return [
            'court_id' => $this->court->id,
            'is_owner_hosted' => true,
            'title' => 'Giao lưu tối thứ 5',
            'starts_at' => now()->addDays(2)->setTime(18, 0)->toIso8601String(),
            'ends_at' => now()->addDays(2)->setTime(20, 0)->toIso8601String(),
            'skill_levels' => ['TB'],
            'required_participants' => 8,
        ];
    }
}
