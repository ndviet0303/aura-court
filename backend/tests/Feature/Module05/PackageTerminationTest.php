<?php

namespace Tests\Feature\Module05;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePaymentRequest;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\PaysPackages;
use Tests\Concerns\RecordsOwnerPayments;
use Tests\TestCase;

/** UC-5.9, FR-91, BR-101: an owner ends a package early after confirming what is lost. */
class PackageTerminationTest extends TestCase
{
    use BuildsVenues;
    use PaysPackages;
    use RecordsOwnerPayments;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usesAuraCourtAccount();
        $this->travelTo(Carbon::parse('2026-11-01 10:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_fr_91_br_101_termination_needs_the_owner_to_confirm_the_consequences(): void
    {
        [$venue, $package] = $this->venueWithPackage();

        $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), [])
            ->assertUnprocessable();
        $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), ['confirm_consequences' => false])
            ->assertUnprocessable();
        $this->assertNull($package->refresh()->terminated_at);
    }

    public function test_fr_91_br_101_termination_is_immediate_keeps_history_and_confirmed_bookings(): void
    {
        [$venue, $package] = $this->venueWithPackage();
        $paid = PackagePaymentRequest::factory()->create(['venue_id' => $venue->id, 'status' => PackagePaymentRequest::STATUS_PAID, 'paid_at' => now()->subDays(10), 'venue_package_id' => $package->id]);
        $booking = $this->booking($this->activeCourt($venue), 100000, now()->subDay());
        $endsAt = $package->ends_at->copy();

        $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), ['confirm_consequences' => true])
            ->assertOk()->assertJsonPath('data.status', 'terminated');

        $package->refresh();
        $this->assertTrue($package->terminated_at->eq(now()));
        $this->assertTrue($package->ends_at->eq($endsAt), 'The original end stays on record; no time is carried over.');
        $this->assertSame(PackagePaymentRequest::STATUS_PAID, $paid->refresh()->status);
        $this->assertSame('confirmed', $booking->refresh()->status);
        // Nothing is left to terminate a second time.
        $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), ['confirm_consequences' => true])
            ->assertConflict()->assertJsonPath('code', 'PACKAGE_NOT_ACTIVE');
    }

    public function test_br_101_a_package_that_is_not_active_cannot_be_terminated(): void
    {
        $none = Venue::factory()->withoutPackage()->create();
        [$expired] = $this->venueWithPackage(now()->subDays(91));

        foreach ([$none, $expired] as $venue) {
            $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), ['confirm_consequences' => true])
                ->assertConflict()->assertJsonPath('code', 'PACKAGE_NOT_ACTIVE');
        }
    }

    public function test_br_89_only_an_owner_of_the_venue_organization_terminates(): void
    {
        [$venue, $package] = $this->venueWithPackage();
        $other = Venue::factory()->create();

        $this->actingAs($this->staffOf($venue))->postJson($this->url($venue), ['confirm_consequences' => true])->assertForbidden();
        $this->actingAs($this->ownerOf($other->organization))->postJson($this->url($venue), ['confirm_consequences' => true])->assertForbidden();
        $this->assertNull($package->refresh()->terminated_at);
    }

    /** @return array{0: Venue, 1: VenuePackage} */
    private function venueWithPackage(?Carbon $startsAt = null): array
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $package = VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days90, 'starts_at' => $startsAt ?? now()->subDays(10)]);

        return [$venue, $package];
    }

    private function url(Venue $venue): string
    {
        return "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/package/terminate";
    }
}
