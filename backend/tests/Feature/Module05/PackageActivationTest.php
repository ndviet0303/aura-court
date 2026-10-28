<?php

namespace Tests\Feature\Module05;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\Journal;
use App\Models\OutboxMessage;
use App\Models\PackagePaymentReceipt;
use App\Models\PackagePaymentRequest;
use App\Models\Payment;
use App\Models\Venue;
use App\Models\VenuePackage;
use App\Models\VenueSuspension;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PaysPackages;
use Tests\TestCase;

/** UC-5.6, UC-5.8 (payment side), FR-87, FR-88, FR-90, BR-90..BR-92, BR-96..BR-100, BR-102. */
class PackageActivationTest extends TestCase
{
    use PaysPackages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usesAuraCourtAccount();
        $this->travelTo(Carbon::parse('2026-11-01 10:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_fr_87_br_98_an_exact_on_time_payment_starts_the_package_at_the_payment_time(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $owner = $this->ownerOf($venue->organization);
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);
        $paidAt = now()->addMinutes(3);

        $this->travelTo(now()->addMinutes(4));
        $this->payPackage($request->payment_code, 300000, $paidAt)->assertOk();

        $package = VenuePackage::query()->where('venue_id', $venue->id)->sole();
        $this->assertTrue($package->starts_at->eq($paidAt));
        $this->assertTrue($package->ends_at->eq($paidAt->copy()->addDays(30)));
        $request->refresh();
        $this->assertSame(PackagePaymentRequest::STATUS_PAID, $request->status);
        $this->assertTrue($request->paid_at->eq($paidAt));
        $this->assertSame($package->id, $request->venue_package_id);
        $this->assertSame(PackagePaymentReceipt::OUTCOME_APPLIED, PackagePaymentReceipt::query()->sole()->outcome);
        $this->assertSame(1, Journal::query()->withoutGlobalScopes()->where('event_type', 'platform.package_fee_receipt')->count());
        $notice = OutboxMessage::query()->withoutGlobalScopes()->where('event_type', 'package.request.paid')->sole();
        $this->assertSame($owner->id, $notice->payload['user_id']);
        $this->assertStringContainsString('Gói 30 ngày', $notice->payload['body']);
    }

    public function test_br_92_a_term_is_a_count_of_24_hour_days_not_calendar_months(): void
    {
        $this->assertSame([30, 90, 180, 360], array_map(fn (PackageTerm $t) => $t->value, PackageTerm::cases()));
        $venue = Venue::factory()->withoutPackage()->create();
        $request = $this->pendingRequest($venue, PackageTerm::Days360, 2800000);

        $this->payPackage($request->payment_code, 2800000, now())->assertOk();

        $package = VenuePackage::query()->where('venue_id', $venue->id)->sole();
        $this->assertSame(360 * 86400, (int) $package->starts_at->diffInSeconds($package->ends_at));
    }

    public function test_fr_90_br_99_a_renewal_paid_while_active_extends_from_the_current_end(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $current = VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days90, 'starts_at' => now()->subDays(80)]);
        $oldEnd = $current->ends_at->copy();
        $request = $this->pendingRequest($venue, PackageTerm::Days90, 800000, PackagePaymentRequest::KIND_RENEWAL);

        $this->payPackage($request->payment_code, 800000, now()->addMinute())->assertOk();

        $this->assertSame(1, VenuePackage::query()->where('venue_id', $venue->id)->count());
        $this->assertTrue($current->refresh()->ends_at->eq($oldEnd->copy()->addDays(90)));
        $this->assertTrue($request->refresh()->period_starts_at->eq($oldEnd));
    }

    public function test_br_99_a_renewal_paid_after_the_package_ended_starts_from_the_payment_time(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        // The package ends 5 minutes after the renewal request is made; the bank records the payment at minute 7.
        $current = VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days30, 'starts_at' => now()->subDays(30)->addMinutes(5)]);
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000, PackagePaymentRequest::KIND_RENEWAL);
        $paidAt = now()->addMinutes(7);

        $this->payPackage($request->payment_code, 300000, $paidAt)->assertOk();

        $packages = VenuePackage::query()->where('venue_id', $venue->id)->orderBy('id')->get();
        $this->assertCount(2, $packages);
        $this->assertTrue($packages[0]->ends_at->eq($current->ends_at), 'The ended package keeps its end; the gap is never charged.');
        $this->assertTrue($packages[1]->starts_at->eq($paidAt));
        $this->assertTrue($packages[1]->ends_at->eq($paidAt->copy()->addDays(30)));
    }

    public function test_br_96_a_wrong_amount_is_stored_for_review_and_activates_nothing(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);

        $this->payPackage($request->payment_code, 299000, now())->assertOk();
        $this->payPackage($request->payment_code, 300001, now())->assertOk();

        $this->assertSame([PackagePaymentReceipt::OUTCOME_AMOUNT_MISMATCH, PackagePaymentReceipt::OUTCOME_AMOUNT_MISMATCH], PackagePaymentReceipt::query()->orderBy('id')->pluck('outcome')->all());
        $this->assertSame(PackagePaymentRequest::STATUS_PENDING, $request->refresh()->status);
        $this->assertSame(0, VenuePackage::query()->where('venue_id', $venue->id)->count());
        $this->assertSame(0, Journal::query()->withoutGlobalScopes()->where('event_type', 'platform.package_fee_receipt')->count());
    }

    public function test_uc_5_6_e1_a_fee_sent_to_another_account_is_stored_for_review_and_activates_nothing(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);
        // The webhook reports account 0011223344; AuraCourt's account is now another one.
        config(['services.sepay.account' => '9988776655']);

        $this->payPackage($request->payment_code, 300000, now())->assertOk();

        $this->assertSame(PackagePaymentReceipt::OUTCOME_ACCOUNT_MISMATCH, PackagePaymentReceipt::query()->sole()->outcome);
        $this->assertSame(PackagePaymentRequest::STATUS_PENDING, $request->refresh()->status);
        $this->assertSame(0, VenuePackage::query()->where('venue_id', $venue->id)->count());
        $this->assertSame(0, Journal::query()->withoutGlobalScopes()->where('event_type', 'platform.package_fee_receipt')->count());
    }

    public function test_fr_88_br_96_a_payment_recorded_at_or_after_the_deadline_is_late_and_never_completes_the_request(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);

        $this->travelTo(now()->addMinutes(12));
        $this->payPackage($request->payment_code, 300000, $request->expires_at)->assertOk();

        $this->assertSame(PackagePaymentReceipt::OUTCOME_LATE, PackagePaymentReceipt::query()->sole()->outcome);
        $this->assertSame(PackagePaymentRequest::STATUS_EXPIRED, $request->refresh()->status);
        $this->assertSame(0, VenuePackage::query()->where('venue_id', $venue->id)->count());
    }

    public function test_br_96_a_payment_recorded_before_the_deadline_counts_even_when_it_arrives_later(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);
        $paidAt = $request->expires_at->copy()->subSecond();

        $this->travelTo(now()->addMinutes(30));
        $this->payPackage($request->payment_code, 300000, $paidAt)->assertOk();

        $this->assertSame(PackagePaymentRequest::STATUS_PAID, $request->refresh()->status);
        $this->assertTrue(VenuePackage::query()->where('venue_id', $venue->id)->sole()->starts_at->eq($paidAt));
    }

    public function test_br_96_an_unknown_code_is_stored_and_matches_nothing(): void
    {
        $this->payPackage('PKGZZZZZZZZ', 300000, now())->assertOk();

        $receipt = PackagePaymentReceipt::query()->sole();
        $this->assertSame(PackagePaymentReceipt::OUTCOME_UNKNOWN_CODE, $receipt->outcome);
        $this->assertNull($receipt->package_payment_request_id);
        $this->assertSame(0, VenuePackage::query()->count());
    }

    public function test_br_97_the_same_transaction_received_again_changes_nothing(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $this->ownerOf($venue->organization);
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);

        $this->payPackage($request->payment_code, 300000, now(), 'sepay-1')->assertOk();
        $end = VenuePackage::query()->where('venue_id', $venue->id)->sole()->ends_at;
        $this->payPackage($request->payment_code, 300000, now(), 'sepay-1')->assertOk();
        // A second, different transfer for a paid request is stored but never adds time.
        $this->payPackage($request->payment_code, 300000, now(), 'sepay-2')->assertOk();

        $this->assertSame(1, VenuePackage::query()->where('venue_id', $venue->id)->count());
        $this->assertTrue(VenuePackage::query()->where('venue_id', $venue->id)->sole()->ends_at->eq($end));
        $this->assertSame([PackagePaymentReceipt::OUTCOME_APPLIED, PackagePaymentReceipt::OUTCOME_ALREADY_PAID], PackagePaymentReceipt::query()->orderBy('id')->pluck('outcome')->all());
        $this->assertSame(1, Journal::query()->withoutGlobalScopes()->where('event_type', 'platform.package_fee_receipt')->count());
        $this->assertSame(1, OutboxMessage::query()->withoutGlobalScopes()->where('event_type', 'package.request.paid')->count());
    }

    public function test_br_100_a_payment_for_another_term_never_adds_to_an_active_package(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $current = VenuePackage::factory()->create(['venue_id' => $venue->id, 'term_days' => PackageTerm::Days90, 'starts_at' => now()->subDays(10)]);
        // A 30-day purchase that was still pending when the 90-day package was bought elsewhere.
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);

        $this->payPackage($request->payment_code, 300000, now())->assertOk();

        $this->assertSame(PackagePaymentReceipt::OUTCOME_TERM_CONFLICT, PackagePaymentReceipt::query()->sole()->outcome);
        $this->assertSame(1, VenuePackage::query()->where('venue_id', $venue->id)->count());
        $this->assertTrue($current->refresh()->ends_at->eq($current->starts_at->copy()->addDays(90)));
        $this->assertSame(PackagePaymentRequest::STATUS_PENDING, $request->refresh()->status);
    }

    public function test_br_91_paying_for_one_venue_never_changes_another_venue_of_the_same_owner(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $sibling = Venue::factory()->withoutPackage()->create(['organization_id' => $venue->organization_id]);
        $siblingPackage = VenuePackage::factory()->create(['venue_id' => $sibling->id, 'term_days' => PackageTerm::Days30, 'starts_at' => now()->subDays(5)]);
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);

        $this->payPackage($request->payment_code, 300000, now())->assertOk();

        $this->assertSame(1, VenuePackage::query()->where('venue_id', $venue->id)->count());
        $this->assertTrue($siblingPackage->refresh()->ends_at->eq($siblingPackage->starts_at->copy()->addDays(30)));
        $this->assertSame(1, VenuePackage::query()->where('venue_id', $sibling->id)->count());
    }

    public function test_br_102_activation_neither_reopens_a_paused_venue_nor_lifts_a_suspension(): void
    {
        $paused = Venue::factory()->withoutPackage()->create(['status' => Venue::STATUS_PAUSED]);
        $suspended = Venue::factory()->withoutPackage()->create();
        $suspension = VenueSuspension::query()->create(['venue_id' => $suspended->id, 'reason' => 'Vi phạm', 'suspended_by' => $this->platformAdmin()->id, 'suspended_at' => now()->subDay()]);

        foreach ([$paused, $suspended] as $venue) {
            $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);
            $this->payPackage($request->payment_code, 300000, now())->assertOk();
            $this->assertSame(1, VenuePackage::query()->where('venue_id', $venue->id)->count());
        }

        $this->assertSame(Venue::STATUS_PAUSED, $paused->refresh()->status);
        $this->assertNull($suspension->refresh()->lifted_at);
    }

    public function test_br_90_a_package_fee_never_becomes_a_wallet_or_booking_payment(): void
    {
        $venue = Venue::factory()->withoutPackage()->create();
        $request = $this->pendingRequest($venue, PackageTerm::Days30, 300000);

        // An outgoing transfer quoting the code (a manual refund) pays nothing.
        $this->payPackage($request->payment_code, 300000, now(), null, 'out')->assertOk();
        $this->assertSame(0, PackagePaymentReceipt::query()->count());

        $this->payPackage($request->payment_code, 300000, now())->assertOk();
        $this->assertSame(0, Payment::query()->withoutGlobalScopes()->count());
        $entries = Journal::query()->withoutGlobalScopes()->where('event_type', 'platform.package_fee_receipt')->sole();
        $this->assertSame('platform', $entries->ledger_entity);
        $this->assertSame(0, $entries->metadata['commission']);
    }

    private function pendingRequest(Venue $venue, PackageTerm $term, int $amount, string $kind = PackagePaymentRequest::KIND_PURCHASE): PackagePaymentRequest
    {
        return PackagePaymentRequest::factory()->create([
            'venue_id' => $venue->id,
            'term_days' => $term,
            'amount' => $amount,
            'kind' => $kind,
        ]);
    }
}
