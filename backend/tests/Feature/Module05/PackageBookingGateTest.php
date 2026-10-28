<?php

namespace Tests\Feature\Module05;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VenuePackage;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsVenues;
use Tests\Concerns\PaysPackages;
use Tests\TestCase;

/** UC-5.7, FR-89, FR-92, BR-103, BR-104: only a venue with an active package takes new bookings. */
class PackageBookingGateTest extends TestCase
{
    use BuildsVenues;
    use PaysPackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usesAuraCourtAccount();
        $this->travelTo(Carbon::parse('2026-11-01 10:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_fr_92_br_103_a_venue_without_an_active_package_refuses_new_bookings(): void
    {
        $none = $this->courtWithPackage(null);
        $expired = $this->courtWithPackage(['starts_at' => now()->subDays(31)]);
        $terminated = $this->courtWithPackage(['terminated_at' => now()->subMinute()]);
        $active = $this->courtWithPackage(['starts_at' => now()->subDays(29)]);
        $player = $this->player();

        foreach ([$none, $expired, $terminated] as $court) {
            $this->quote($player, $court, '2026-11-02 08:00')->assertUnprocessable()->assertJsonValidationErrors('package');
            $this->getJson("/api/availability?court_id={$court->id}&date=2026-11-02")->assertUnprocessable()->assertJsonValidationErrors('package');
        }
        $this->quote($player, $active, '2026-11-02 08:00')->assertCreated();
    }

    public function test_br_98_br_103_the_package_stops_taking_bookings_at_the_exact_end(): void
    {
        $court = $this->courtWithPackage(['starts_at' => now()->subDays(30)->addSecond()]);
        $player = $this->player();

        $this->quote($player, $court, '2026-11-02 08:00')->assertCreated();
        $this->travel(1)->seconds();
        $this->quote($player, $court, '2026-11-02 09:00')->assertUnprocessable()->assertJsonValidationErrors('package');
    }

    public function test_br_104_an_active_package_takes_bookings_for_play_dates_after_its_end(): void
    {
        $court = $this->courtWithPackage(['starts_at' => now()->subDays(29)]);

        $this->quote($this->player(), $court, '2026-11-20 08:00')->assertCreated();
    }

    public function test_br_104_a_hold_made_before_the_end_is_still_paid_until_its_own_deadline(): void
    {
        $court = $this->courtWithPackage(['starts_at' => now()->subDays(30)->addMinutes(2)]);
        $player = $this->player();
        $held = $this->actingAs($player)->postJson('/api/reservations', [
            'court_id' => $court->id, 'starts_at' => '2026-11-02T08:00:00+07:00', 'ends_at' => '2026-11-02T09:00:00+07:00', 'expected_total' => 100000,
        ], $this->idempotent())->assertCreated()->json('reservation');
        app(WalletPaymentService::class)->getOrCreateWallet($player->id, $court->venue->organization_id)->update(['balance' => 100000]);
        $deadline = Reservation::query()->withoutGlobalScopes()->findOrFail($held['id'])->hold_expires_at;

        $this->travel(3)->minutes();
        $this->assertSame(0, VenuePackage::query()->where('venue_id', $court->venue_id)->where('ends_at', '>', now())->count());
        $this->actingAs($player)->postJson("/api/reservations/{$held['id']}/confirm", ['court_id' => $court->id], $this->idempotent())->assertOk()->assertJsonPath('reservation.status', 'confirmed');
        $this->assertTrue(Reservation::query()->withoutGlobalScopes()->findOrFail($held['id'])->hold_expires_at->eq($deadline));
    }

    public function test_fr_89_br_103_after_expiry_the_owner_still_sees_the_package_and_finance_and_can_buy(): void
    {
        $court = $this->courtWithPackage(['starts_at' => now()->subDays(31)]);
        $this->packagePrice(PackageTerm::Days30, 300000, now()->subMonth());
        $venue = $court->venue;
        $owner = $this->ownerOf($venue->organization);
        $base = "/api/partner/organizations/{$venue->organization_id}";

        $packages = $this->actingAs($owner)->getJson("{$base}/packages")->assertOk()->json('data.venues');
        // FR-89: term, start, end and status of each venue's package.
        $package = collect($packages)->firstWhere('venue_id', $venue->id)['package'];
        $this->assertSame([30, 'expired'], [$package['term_days'], $package['status']]);
        $this->assertSame(now()->subDays(31)->utc()->toISOString(), $package['starts_at']);
        $this->assertSame(now()->subDay()->utc()->toISOString(), $package['ends_at']);
        $this->actingAs($owner)->getJson("{$base}/finance/report?from=2026-11-01&to=2026-11-01")->assertOk();
        $this->actingAs($owner)->getJson("{$base}/finance/transactions?from=2026-11-01&to=2026-11-01")->assertOk();
        $this->actingAs($owner)->postJson("{$base}/venues/{$venue->id}/package-requests", ['term_days' => 30, 'expected_amount' => 300000])->assertCreated();
    }

    /** @param  array<string, mixed>|null  $package  a 30-day package with these attributes, or none */
    private function courtWithPackage(?array $package): Court
    {
        $venue = $this->openVenue();
        VenuePackage::query()->where('venue_id', $venue->id)->delete();
        if ($package !== null) {
            VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days30] + $package);
        }
        $court = $this->activeCourt($venue);
        $this->priceCourt($court, 100000);

        return $court;
    }

    /** @return array<string, string> */
    private function idempotent(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    private function player(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'phone' => '0901234567']);
    }

    private function quote(User $player, Court $court, string $start): TestResponse
    {
        $starts = Carbon::parse($start, 'Asia/Ho_Chi_Minh');

        return $this->actingAs($player)->postJson('/api/price-quotes', [
            'court_id' => $court->id, 'starts_at' => $starts->toIso8601String(), 'ends_at' => $starts->copy()->addHour()->toIso8601String(),
        ], $this->idempotent());
    }
}
