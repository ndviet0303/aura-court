<?php

namespace Tests\Feature\Module05;

use App\Domain\Package\Actions\ExpirePackagePaymentRequests;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePaymentRequest;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PaysPackages;
use Tests\TestCase;

/** UC-5.4, UC-5.5, UC-5.8 (request side), FR-85, FR-86, FR-88, FR-90, BR-89, BR-95, BR-99, BR-100, BR-115. */
class PackagePaymentRequestTest extends TestCase
{
    use PaysPackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usesAuraCourtAccount();
        $this->travelTo(Carbon::parse('2026-11-01 10:00', 'Asia/Ho_Chi_Minh'));
        foreach ([[PackageTerm::Days30, 300000], [PackageTerm::Days90, 800000], [PackageTerm::Days180, 1500000], [PackageTerm::Days360, 2800000]] as [$term, $amount]) {
            $this->packagePrice($term, $amount, now()->subMonth());
        }
    }

    public function test_fr_85_fr_86_owner_registers_a_term_for_a_venue_and_gets_the_payment_details(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();

        $response = $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), ['term_days' => 180, 'expected_amount' => 1500000])
            ->assertCreated()
            ->assertJsonPath('data.venue_id', $venue->id)
            ->assertJsonPath('data.kind', 'purchase')
            ->assertJsonPath('data.term_days', 180)
            ->assertJsonPath('data.days', 180)
            ->assertJsonPath('data.amount', 1500000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.receiver.bank_code', 'VCB')
            ->assertJsonPath('data.receiver.account_number', '0011223344')
            ->assertJsonPath('data.receiver.account_name', 'AURA COURT')
            // BR-95: the request lasts 10 minutes from its creation.
            ->assertJsonPath('data.expires_at', now()->utc()->addMinutes(10)->toISOString());

        $this->assertMatchesRegularExpression('/^PKR[A-Z0-9]{8}$/', $response->json('data.request_code'));
        $this->assertMatchesRegularExpression('/^PKG[A-Z0-9]{8}$/', $response->json('data.payment_code'));
        $this->assertStringContainsString(urlencode($response->json('data.payment_code')), $response->json('data.qr_url'));
        $this->assertStringContainsString('1500000', $response->json('data.qr_url'));
    }

    public function test_br_95_reopening_a_request_keeps_its_amount_and_deadline(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $owner = $this->ownerOf($venue->organization);
        $created = $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])->assertCreated()->json('data');

        $this->travel(6)->minutes();
        $this->actingAs($owner)->getJson("/api/partner/organizations/{$venue->organization_id}/package-requests/{$created['request_code']}")
            ->assertOk()
            ->assertJsonPath('data.payment_code', $created['payment_code'])
            ->assertJsonPath('data.amount', $created['amount'])
            ->assertJsonPath('data.expires_at', $created['expires_at']);
        // Asking again for the same term returns the same request unchanged, not a new 10 minutes.
        $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])
            ->assertOk()
            ->assertJsonPath('data.request_code', $created['request_code'])
            ->assertJsonPath('data.expires_at', $created['expires_at']);
        $this->assertSame(1, PackagePaymentRequest::query()->count());
    }

    public function test_br_95_one_pending_request_per_venue_and_no_other_term_while_it_lasts(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $owner = $this->ownerOf($venue->organization);
        $code = $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])->json('data.request_code');

        $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 90, 'expected_amount' => 800000])
            ->assertConflict()
            ->assertJsonPath('code', 'PACKAGE_REQUEST_PENDING')
            ->assertJsonPath('data.request_code', $code);
    }

    public function test_fr_88_br_115_an_unpaid_request_reads_expired_after_10_minutes_and_the_sweep_stores_it(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $owner = $this->ownerOf($venue->organization);
        $code = $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])->json('data.request_code');
        $show = "/api/partner/organizations/{$venue->organization_id}/package-requests/{$code}";

        $this->travelTo(now()->addMinutes(9)->addSeconds(59));
        $this->actingAs($owner)->getJson($show)->assertJsonPath('data.status', 'pending');
        $this->travel(1)->seconds();
        $this->actingAs($owner)->getJson($show)->assertJsonPath('data.status', 'expired');
        $this->assertSame(PackagePaymentRequest::STATUS_PENDING, PackagePaymentRequest::query()->value('status'));

        $this->assertSame(1, app(ExpirePackagePaymentRequests::class)->execute());
        $this->assertSame(PackagePaymentRequest::STATUS_EXPIRED, PackagePaymentRequest::query()->value('status'));
        $this->assertNull(VenuePackage::query()->where('venue_id', $venue->id)->first());

        // A new request, at the price in effect now, can be made at once.
        $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 90, 'expected_amount' => 800000])->assertCreated()->assertJsonPath('data.amount', 800000);
    }

    public function test_fr_90_br_99_an_active_package_is_renewed_only_with_its_own_term(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days90, 'starts_at' => now()->subDays(10)]);
        $owner = $this->ownerOf($venue->organization);

        $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])
            ->assertConflict()->assertJsonPath('code', 'PACKAGE_TERM_LOCKED');
        $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 90, 'expected_amount' => 800000])
            ->assertCreated()->assertJsonPath('data.kind', 'renewal')->assertJsonPath('data.amount', 800000);
    }

    public function test_br_100_another_term_is_a_purchase_once_the_package_has_expired_or_been_terminated(): void
    {
        $expired = Venue::factory()->withoutPackage()->create();
        VenuePackage::factory()->create(['venue_id' => $expired->id, 'term_days' => PackageTerm::Days30, 'starts_at' => now()->subDays(31)]);
        $terminated = Venue::factory()->withoutPackage()->create();
        VenuePackage::factory()->create(['venue_id' => $terminated->id, 'term_days' => PackageTerm::Days30, 'terminated_at' => now()->subDay()]);

        $this->actingAs($this->ownerOf($expired->organization))->postJson($this->url($expired), ['term_days' => 360, 'expected_amount' => 2800000])
            ->assertCreated()->assertJsonPath('data.kind', 'purchase');
        $this->actingAs($this->ownerOf($terminated->organization))->postJson($this->url($terminated), ['term_days' => 180, 'expected_amount' => 1500000])
            ->assertCreated()->assertJsonPath('data.kind', 'purchase');
    }

    public function test_uc_5_4_a2_uc_5_8_e1_a_price_changed_since_the_owner_saw_it_is_shown_again_not_charged(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $owner = $this->ownerOf($venue->organization);
        $this->packagePrice(PackageTerm::Days30, 350000);

        $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])
            ->assertConflict()
            ->assertJsonPath('code', 'PACKAGE_PRICE_CHANGED')
            ->assertJsonPath('current_amount', 350000);
        $this->assertSame(0, PackagePaymentRequest::query()->count());

        $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 350000])
            ->assertCreated()->assertJsonPath('data.amount', 350000);
        $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_amount');
    }

    public function test_fr_85_the_term_must_be_one_of_the_four(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();

        $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), ['term_days' => 31, 'expected_amount' => 300000])
            ->assertUnprocessable()->assertJsonValidationErrors('term_days');
    }

    public function test_br_89_only_the_owner_of_the_venue_organization_registers_or_reads_a_request(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $other = Venue::factory()->withoutPackage()->create();
        $owner = $this->ownerOf($venue->organization);

        $this->actingAs($this->staffOf($venue))->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])->assertForbidden();
        $this->actingAs($this->ownerOf($other->organization))->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])->assertForbidden();
        // A venue of another organization under the owner's own organization path is not found.
        $this->actingAs($owner)->postJson("/api/partner/organizations/{$venue->organization_id}/venues/{$other->id}/package-requests", ['term_days' => 30, 'expected_amount' => 300000])->assertNotFound();

        $code = $this->actingAs($owner)->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])->assertCreated()->json('data.request_code');
        $this->actingAs($this->staffOf($venue))->getJson("/api/partner/organizations/{$venue->organization_id}/package-requests/{$code}")->assertForbidden();
        $this->actingAs($this->ownerOf($other->organization))->getJson("/api/partner/organizations/{$other->organization_id}/package-requests/{$code}")->assertNotFound();
        $this->actingAs($this->staffOf($venue))->getJson("/api/partner/organizations/{$venue->organization_id}/packages")->assertForbidden();
    }

    public function test_fr_86_no_request_is_made_without_auracourt_receiving_account(): void
    {
        config(['services.sepay.account' => null]);
        $venue = Venue::factory()->withoutPackage()->create();

        $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])
            ->assertStatus(503)->assertJsonPath('code', 'PACKAGE_PAYMENT_UNAVAILABLE');
        $this->assertSame(0, PackagePaymentRequest::query()->count());
    }

    public function test_a_closed_venue_cannot_register_a_package(): void
    {
        $venue = Venue::factory()->withoutPackage()->create(['status' => Venue::STATUS_CLOSED]);

        $this->actingAs($this->ownerOf($venue->organization))->postJson($this->url($venue), ['term_days' => 30, 'expected_amount' => 300000])
            ->assertUnprocessable()->assertJsonPath('code', 'VENUE_CLOSED');
    }

    private function url(Venue $venue): string
    {
        return "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/package-requests";
    }
}
