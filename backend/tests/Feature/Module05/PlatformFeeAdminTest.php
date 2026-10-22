<?php

namespace Tests\Feature\Module05;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePaymentRequest;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PaysPackages;
use Tests\TestCase;

/** UC-5.13..UC-5.16, FR-97, FR-98, BR-89, BR-115, BR-116: the platform admin follows package requests and venue packages. */
class PlatformFeeAdminTest extends TestCase
{
    use PaysPackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usesAuraCourtAccount();
        $this->travelTo(Carbon::parse('2026-11-01 10:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_fr_97_br_115_requests_show_pending_expired_and_paid_by_their_state_when_viewed(): void
    {
        $venue = Venue::factory()->withoutPackage()->create(['name' => 'Aura Long Biên']);
        $owner = $this->ownerOf($venue->organization);
        $pending = PackagePaymentRequest::factory()->create(['venue_id' => $venue->id]);
        // One pending row per venue (D7), so the overdue one belongs to a second venue.
        $overdue = PackagePaymentRequest::factory()->create(['venue_id' => Venue::factory()->withoutPackage(), 'expires_at' => now()->subMinute()]);
        $stored = PackagePaymentRequest::factory()->create(['venue_id' => $venue->id, 'status' => PackagePaymentRequest::STATUS_EXPIRED, 'expires_at' => now()->subDay()]);
        $paid = PackagePaymentRequest::factory()->create(['venue_id' => $venue->id, 'status' => PackagePaymentRequest::STATUS_PAID, 'paid_at' => now()->subHour()]);
        $admin = $this->platformAdmin();

        $rows = collect($this->actingAs($admin)->getJson('/api/admin/platform-fees/requests')->assertOk()->json('data'))->keyBy('request_code');
        $this->assertSame('pending', $rows[$pending->request_code]['status']);
        $this->assertSame('expired', $rows[$overdue->request_code]['status']);
        $this->assertSame('paid', $rows[$paid->request_code]['status']);
        $row = $rows[$pending->request_code];
        $this->assertSame(['Aura Long Biên', $owner->email, 30, 300000], [$row['venue']['name'], $row['owner']['email'], $row['term_days'], $row['amount']]);
        $this->assertNotNull($row['created_at']);
        $this->assertNotNull($row['expires_at']);

        $expired = $this->actingAs($admin)->getJson('/api/admin/platform-fees/requests?status=expired')->json('data');
        $this->assertEqualsCanonicalizing([$overdue->request_code, $stored->request_code], array_column($expired, 'request_code'));
        $this->assertSame([$pending->request_code], array_column($this->actingAs($admin)->getJson('/api/admin/platform-fees/requests?status=pending')->json('data'), 'request_code'));
    }

    public function test_fr_97_the_request_detail_lists_its_transactions_and_their_outcomes(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $request = PackagePaymentRequest::factory()->create(['venue_id' => $venue->id]);
        $this->payPackage($request->payment_code, 299000, now(), 'tx-short')->assertOk();
        $this->payPackage($request->payment_code, 300000, now(), 'tx-ok')->assertOk();

        $detail = $this->actingAs($this->platformAdmin())->getJson('/api/admin/platform-fees/requests/'.strtolower($request->request_code))
            ->assertOk()->assertJsonPath('data.status', 'paid')->json('data');

        $this->assertSame(['tx-short' => 'amount_mismatch', 'tx-ok' => 'applied'], collect($detail['receipts'])->pluck('outcome', 'provider_transaction_id')->all());
        $this->actingAs($this->platformAdmin())->getJson('/api/admin/platform-fees/requests/PKRNOTHERE1')->assertNotFound();
    }

    public function test_fr_98_br_116_venue_packages_are_told_apart_and_counted_once_per_venue(): void
    {
        $none = Venue::factory()->withoutPackage()->create();
        $in12h = $this->venueEnding(now()->addHours(12));
        $in2d = $this->venueEnding(now()->addDays(2));
        $in7d = $this->venueEnding(now()->addDays(7));
        $later = $this->venueEnding(now()->addDays(8));
        $endsNow = $this->venueEnding(now());
        $terminated = $this->venueEnding(now()->addDay(), ['terminated_at' => now()->subHour()]);
        // A venue renewed earlier keeps only its newest row as its package.
        $renewed = $this->venueEnding(now()->addDay());
        VenuePackage::factory()->create(['venue_id' => $renewed->id, 'term_days' => PackageTerm::Days30, 'starts_at' => now()->subDay()]);
        $admin = $this->platformAdmin();

        $response = $this->actingAs($admin)->getJson('/api/admin/platform-fees/venues?per_page=100')->assertOk();
        $statuses = collect($response->json('data'))->pluck('package_status', 'venue_id');
        $this->assertSame('none', $statuses[$none->id]);
        $this->assertSame('active', $statuses[$in12h->id]);
        $this->assertSame('expired', $statuses[$endsNow->id]);
        $this->assertSame('terminated', $statuses[$terminated->id]);
        $this->assertSame(['7' => 3, '3' => 2, '1' => 1], $response->json('expiring_counts'));

        $within = fn (int $days) => collect($this->actingAs($admin)->getJson("/api/admin/platform-fees/venues?expiring_within={$days}")->json('data'))->pluck('venue_id')->sort()->values()->all();
        $this->assertSame([$in12h->id], $within(1));
        $this->assertSame([$in12h->id, $in2d->id], $within(3));
        $this->assertSame([$in12h->id, $in2d->id, $in7d->id], $within(7));
        $this->assertNotContains($later->id, $within(7));
        $byStatus = fn (string $status) => collect($this->actingAs($admin)->getJson("/api/admin/platform-fees/venues?status={$status}&per_page=100")->json('data'))->pluck('venue_id')->all();
        $this->assertSame([$terminated->id], $byStatus('terminated'));
        $this->assertSame([$endsNow->id], $byStatus('expired'));
        $this->assertContains($none->id, $byStatus('none'));
        $this->assertNotContains($endsNow->id, $byStatus('none'));
    }

    public function test_fr_98_the_venue_detail_keeps_its_package_and_request_history(): void
    {
        $venue = $this->venueEnding(now()->addDays(10));
        VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days90, 'starts_at' => now()]);
        PackagePaymentRequest::factory()->create(['venue_id' => $venue->id, 'status' => PackagePaymentRequest::STATUS_PAID, 'paid_at' => now()]);
        PackagePaymentRequest::factory()->create(['venue_id' => $venue->id]);

        $detail = $this->actingAs($this->platformAdmin())->getJson("/api/admin/platform-fees/venues/{$venue->id}")->assertOk()->json('data');

        $this->assertCount(2, $detail['packages']);
        $this->assertCount(2, $detail['requests']);
        $this->assertSame(90, $detail['package']['term_days']);
    }

    public function test_br_89_owners_and_staff_cannot_reach_the_platform_fee_pages(): void
    {
        $venue = Venue::factory()->create();
        foreach ([$this->ownerOf($venue->organization), $this->staffOf($venue)] as $user) {
            foreach (['requests', 'venues', 'report?from=2026-11-01&to=2026-11-30', 'prices'] as $path) {
                $this->actingAs($user)->getJson("/api/admin/platform-fees/{$path}")->assertForbidden();
            }
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function venueEnding(Carbon $endsAt, array $attributes = []): Venue
    {
        $venue = Venue::factory()->withoutPackage()->create();
        VenuePackage::factory()->create($attributes + ['venue_id' => $venue->id, 'term_days' => PackageTerm::Days30, 'starts_at' => $endsAt->copy()->subDays(30)]);

        return $venue;
    }
}
