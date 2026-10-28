<?php

namespace Tests\Feature\Module05;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\OutboxMessage;
use App\Models\PackagePrice;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\PaysPackages;
use Tests\TestCase;

/** UC-5.1..UC-5.3, FR-82..FR-84, BR-93, BR-94: package prices per term, scheduled changes and their notice. */
class PackagePriceTest extends TestCase
{
    use PaysPackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->usesAuraCourtAccount();
        $this->travelTo(Carbon::parse('2026-11-01 10:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_fr_82_owner_and_admin_see_the_four_terms_with_the_applicable_price(): void
    {
        $this->packagePrice(PackageTerm::Days30, 300000);
        $this->packagePrice(PackageTerm::Days90, 800000);
        $venue = Venue::factory()->withoutPackage()->create();

        $owner = $this->actingAs($this->ownerOf($venue->organization))->getJson("/api/partner/organizations/{$venue->organization_id}/packages")
            ->assertOk()->json('data.terms');
        $this->assertSame([30, 90, 180, 360], array_column($owner, 'term_days'));
        $this->assertSame(300000, $owner[0]['current_price']['amount']);
        $this->assertSame(800000, $owner[1]['current_price']['amount']);
        $this->assertNull($owner[2]['current_price']);

        $admin = $this->actingAs($this->platformAdmin())->getJson('/api/admin/platform-fees/prices')->assertOk()->json('data');
        $this->assertSame([30, 90, 180, 360], array_column($admin, 'term_days'));
    }

    public function test_fr_83_only_the_platform_admin_sets_a_price_for_one_of_the_four_terms(): void
    {
        $venue = Venue::factory()->create();
        $this->actingAs($this->ownerOf($venue->organization))
            ->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 1000])->assertForbidden();

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 60, 'amount' => 1000])->assertUnprocessable()->assertJsonValidationErrors('term_days');
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 0])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 1500.5])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 180, 'amount' => 1500000])
            ->assertCreated()
            ->assertJsonPath('data.term_days', 180)
            ->assertJsonPath('data.amount', 1500000)
            ->assertJsonPath('data.effective_at', now()->utc()->toISOString());
    }

    public function test_br_93_a_longer_term_is_never_priced_from_the_30_day_price(): void
    {
        $this->packagePrice(PackageTerm::Days30, 300000);
        $venue = Venue::factory()->withoutPackage()->create();

        $this->actingAs($this->ownerOf($venue->organization))
            ->postJson($this->requestUrl($venue), ['term_days' => 90, 'expected_amount' => 800000])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'PACKAGE_PRICE_UNAVAILABLE');
    }

    public function test_br_93_br_94_a_request_takes_the_price_in_effect_when_it_is_created(): void
    {
        $this->packagePrice(PackageTerm::Days30, 300000, now()->subMonth());
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', [
            'term_days' => 30, 'amount' => 350000, 'effective_at' => '2026-11-05 00:00:00',
        ])->assertCreated()->assertJsonPath('data.effective_at', '2026-11-04T17:00:00.000000Z');

        $before = Venue::factory()->withoutPackage()->create();
        $after = Venue::factory()->withoutPackage()->create();
        $old = $this->actingAs($this->ownerOf($before->organization))->postJson($this->requestUrl($before), ['term_days' => 30, 'expected_amount' => 300000])->assertCreated();
        $this->assertSame(300000, $old->json('data.amount'));

        $this->travelTo(Carbon::parse('2026-11-05 00:00', 'Asia/Ho_Chi_Minh'));
        $this->actingAs($this->ownerOf($after->organization))->postJson($this->requestUrl($after), ['term_days' => 30, 'expected_amount' => 350000])
            ->assertCreated()->assertJsonPath('data.amount', 350000);
    }

    public function test_br_94_a_pending_request_keeps_its_amount_after_a_price_change(): void
    {
        $this->packagePrice(PackageTerm::Days30, 300000, now()->subMonth());
        $venue = Venue::factory()->withoutPackage()->create();
        $owner = $this->ownerOf($venue->organization);
        $code = $this->actingAs($owner)->postJson($this->requestUrl($venue), ['term_days' => 30, 'expected_amount' => 300000])->assertCreated()->json('data.request_code');

        $this->actingAs($this->platformAdmin())->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 999000])->assertCreated();

        $this->actingAs($owner)->getJson("/api/partner/organizations/{$venue->organization_id}/package-requests/{$code}")
            ->assertOk()->assertJsonPath('data.amount', 300000);
    }

    public function test_br_94_a_new_price_replaces_the_scheduled_one_of_the_same_term(): void
    {
        $this->packagePrice(PackageTerm::Days30, 300000, now()->subMonth());
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 400000, 'effective_at' => '2026-12-01 00:00:00'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 380000, 'effective_at' => '2026-12-15 00:00:00'])->assertCreated();

        $terms = $this->actingAs($admin)->getJson('/api/admin/platform-fees/prices')->json('data');
        $this->assertSame(300000, $terms[0]['current_price']['amount']);
        $this->assertSame(380000, $terms[0]['next_price']['amount']);

        // From 01/12 the replaced 400.000 ₫ never applies; the old price holds until 15/12.
        $this->travelTo(Carbon::parse('2026-12-02 00:00', 'Asia/Ho_Chi_Minh'));
        $this->assertSame(300000, $this->actingAs($admin)->getJson('/api/admin/platform-fees/prices')->json('data.0.current_price.amount'));
        $this->travelTo(Carbon::parse('2026-12-15 00:00', 'Asia/Ho_Chi_Minh'));
        $this->assertSame(380000, $this->actingAs($admin)->getJson('/api/admin/platform-fees/prices')->json('data.0.current_price.amount'));
    }

    public function test_fr_84_every_owner_is_told_the_term_the_new_price_and_when_it_applies_in_app_and_by_email(): void
    {
        $first = Venue::factory()->create();
        $second = Venue::factory()->create();
        $owners = [$this->ownerOf($first->organization), $this->ownerOf($second->organization)];
        $this->staffOf($first);

        $this->actingAs($this->platformAdmin())->postJson('/api/admin/platform-fees/prices', [
            'term_days' => 90, 'amount' => 850000, 'effective_at' => '2026-11-20 08:00:00',
        ])->assertCreated()->assertJsonPath('notification_failed', false);

        $notices = OutboxMessage::query()->withoutGlobalScopes()->where('event_type', 'package.price.set')->get();
        $this->assertEqualsCanonicalizing(array_map(fn ($owner) => $owner->id, $owners), $notices->pluck('payload.user_id')->all());
        foreach ($notices as $notice) {
            $this->assertSame(['database', 'mail'], $notice->payload['channels']);
            $this->assertSame(['database', 'mail'], $notice->payload['delivered_channels']);
            $this->assertSame(90, $notice->payload['term_days']);
            $this->assertSame(850000, $notice->payload['amount']);
            $this->assertStringContainsString('90 ngày', $notice->payload['body']);
            $this->assertStringContainsString('850.000₫', $notice->payload['body']);
            $this->assertStringContainsString('08:00 20/11/2026', $notice->payload['body']);
        }
    }

    public function test_uc_5_2_e1_an_initial_price_never_overwrites_a_term_priced_meanwhile(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 300000, 'initial' => true])->assertCreated();
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 90, 'amount' => 800000, 'effective_at' => '2026-12-01 00:00:00'])->assertCreated();

        // A scheduled price counts as a price too.
        foreach ([30, 90] as $term) {
            $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => $term, 'amount' => 999000, 'initial' => true])
                ->assertConflict()->assertJsonPath('code', 'PACKAGE_PRICE_EXISTS');
        }
        $this->assertSame(2, PackagePrice::query()->count());

        // UC-5.3: the update path still changes it.
        $this->actingAs($admin)->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 320000])
            ->assertCreated()->assertJsonPath('data.amount', 320000);
    }

    public function test_uc_5_3_e3_a_saved_price_whose_notice_failed_reports_both(): void
    {
        $this->ownerOf(Venue::factory()->create()->organization);
        $this->mock(DurableNotificationServiceInterface::class, fn ($mock) => $mock->shouldReceive('recordOutboxNotification')->andThrow(new \RuntimeException('queue down')));

        $this->actingAs($this->platformAdmin())->postJson('/api/admin/platform-fees/prices', ['term_days' => 30, 'amount' => 300000])
            ->assertCreated()
            ->assertJsonPath('data.amount', 300000)
            ->assertJsonPath('notification_failed', true);
        $this->assertSame(1, PackagePrice::query()->count());
    }

    private function requestUrl(Venue $venue): string
    {
        return "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/package-requests";
    }
}
