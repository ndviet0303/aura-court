<?php

namespace Tests\Feature\Booking;

use App\Models\Court;
use App\Models\MonthlyInvoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundAllocation;
use App\Models\OwnerRefundObligation;
use App\Models\PriceRule;
use App\Models\ReceivingConnection;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSeries;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Flag-ON direct-owner recurring series: accept -> hold pending -> confirm on verified payment.
 */
class RecurringDirectOwnerSeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_holds_occurrences_pending_and_never_auto_confirms_the_series(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(3));

        $response = $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'accept-hold-1');

        $response->assertCreated()->assertJsonPath('series.status', 'pending');
        $seriesId = (int) $response->json('series.id');

        $occurrences = RecurringOccurrence::where('recurring_series_id', $seriesId)->get();
        $this->assertCount(3, $occurrences);
        foreach ($occurrences as $occurrence) {
            $this->assertSame('held', $occurrence->status);
            $this->assertSame('held', $occurrence->reservation->status);
            $this->assertSame('direct_owner_v1', $occurrence->reservation->payment_model);
        }
        // Per-period: one pending recurring_period attempt per monthly invoice, none confirmed.
        $this->assertSame(3, OwnerPaymentAttempt::where('purpose', 'recurring_period')->count());
        $this->assertSame(3, OwnerPaymentAttempt::where('status', OwnerPaymentAttempt::STATUS_PENDING)->count());
        $this->assertSame('pending', RecurringSeries::find($seriesId)->status);
        $this->assertSame(0, MonthlyInvoice::where('recurring_series_id', $seriesId)->where('status', 'paid')->count());
    }

    public function test_all_or_nothing_conflict_rejects_the_whole_accept(): void
    {
        $fixture = $this->tenant(); // conflict_policy defaults to all_or_nothing
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $this->occupy($fixture, $preview['occurrences'][1]['starts_at'], $preview['occurrences'][1]['ends_at']);

        $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'all-or-nothing-1')->assertStatus(409);

        $this->assertDatabaseCount('recurring_series', 0);
        $this->assertDatabaseCount('recurring_occurrences', 0);
        $this->assertDatabaseCount('monthly_invoices', 0);
        $this->assertDatabaseCount('owner_payment_attempts', 0);
        // Only the pre-existing occupier reservation exists; the accept persisted nothing.
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_partial_conflict_without_accept_partial_returns_excluded_and_persists_nothing(): void
    {
        $fixture = $this->tenant(['conflict_policy' => 'partial']);
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $this->occupy($fixture, $preview['occurrences'][1]['starts_at'], $preview['occurrences'][1]['ends_at']);

        $response = $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'partial-negotiate-1');

        $response->assertOk()
            ->assertJsonPath('status', 'partial_conflict')
            ->assertJsonPath('held_count', 2)
            ->assertJsonPath('revised_total', $preview['occurrences'][0]['price'] + $preview['occurrences'][2]['price'])
            ->assertJsonCount(1, 'excluded')
            ->assertJsonPath('excluded.0.starts_at', $preview['occurrences'][1]['starts_at']);

        $this->assertDatabaseCount('recurring_series', 0);
        $this->assertDatabaseCount('recurring_occurrences', 0);
        $this->assertDatabaseCount('owner_payment_attempts', 0);
    }

    public function test_partial_conflict_with_accept_partial_holds_only_non_conflicted(): void
    {
        $fixture = $this->tenant(['conflict_policy' => 'partial']);
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $conflictStart = $preview['occurrences'][1]['starts_at'];
        $this->occupy($fixture, $conflictStart, $preview['occurrences'][1]['ends_at']);

        $response = $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
            'accept_partial' => true,
        ]), 'partial-accept-1');

        $response->assertCreated()->assertJsonPath('series.status', 'pending');
        $seriesId = (int) $response->json('series.id');

        $occurrences = RecurringOccurrence::where('recurring_series_id', $seriesId)->get();
        $this->assertCount(2, $occurrences);
        foreach ($occurrences as $occurrence) {
            $this->assertSame('held', $occurrence->status);
            $this->assertNotSame($conflictStart, $occurrence->starts_at->utc()->toIso8601String());
        }
        $this->assertSame(2, OwnerPaymentAttempt::where('purpose', 'recurring_period')->count());
    }

    public function test_verified_period_payment_confirms_only_that_period(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'period-pay-1')->assertCreated()->json('series.id');

        $invoices = MonthlyInvoice::where('recurring_series_id', $seriesId)->orderBy('period_start')->get();
        $this->assertCount(3, $invoices);
        $invoice1 = $invoices->first();
        $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':invoice:'.$invoice1->id)->firstOrFail();

        $this->sendOwnerPayment($fixture, $attempt1->reference, (int) $invoice1->amount, 'rec-period-1');

        $occurrences = RecurringOccurrence::where('recurring_series_id', $seriesId)->orderBy('sequence')->get();
        $this->assertSame('confirmed', $occurrences[0]->status);
        $this->assertSame('confirmed', $occurrences[0]->reservation->status);
        $this->assertSame('held', $occurrences[1]->status);
        $this->assertSame('held', $occurrences[2]->status);
        $this->assertSame('paid', $invoice1->fresh()->status);
        $this->assertSame('pending', $invoices[1]->fresh()->status);
        $this->assertSame('pending', $invoices[2]->fresh()->status);
        $this->assertSame('paid', $attempt1->fresh()->status);
        // The series still has held periods -> it MUST NOT be confirmed yet.
        $this->assertSame('pending', RecurringSeries::find($seriesId)->status);
    }

    public function test_full_series_payment_confirms_every_occurrence(): void
    {
        $fixture = $this->tenant(['payment_policy' => 'full_series']);
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'full-series-1')->assertCreated()->json('series.id');

        $attempt = OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':full')->firstOrFail();
        $this->assertSame($preview['total_amount'], (int) $attempt->amount);
        $this->assertSame(1, OwnerPaymentAttempt::where('purpose', 'recurring_period')->count());

        $this->sendOwnerPayment($fixture, $attempt->reference, (int) $attempt->amount, 'rec-full-1');

        foreach (RecurringOccurrence::where('recurring_series_id', $seriesId)->get() as $occurrence) {
            $this->assertSame('confirmed', $occurrence->status);
            $this->assertSame('confirmed', $occurrence->reservation->status);
        }
        $this->assertSame(0, MonthlyInvoice::where('recurring_series_id', $seriesId)->where('status', 'pending')->count());
        $this->assertSame('confirmed', RecurringSeries::find($seriesId)->status);
    }

    public function test_monthly_overdue_respects_grace_then_releases_only_that_period(): void
    {
        $fixture = $this->tenant(); // overdue_release_hours = 24
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'overdue-1')->assertCreated()->json('series.id');

        $service = app(RecurringSeriesService::class);
        $invoices = MonthlyInvoice::where('recurring_series_id', $seriesId)->orderBy('period_start')->get();
        $invoice1 = $invoices->first();

        // Past due but still inside the 24h grace window -> nothing released.
        $invoice1->update(['due_at' => Carbon::now()->subHour()]);
        $this->assertSame(0, $service->markOverdueAndReleaseFuture());
        $this->assertSame('pending', $invoice1->fresh()->status);

        // Beyond the grace window -> release ONLY this period's future occurrences.
        $invoice1->update(['due_at' => Carbon::now()->subDays(2)]);
        $this->assertSame(1, $service->markOverdueAndReleaseFuture());

        $occurrences = RecurringOccurrence::where('recurring_series_id', $seriesId)->orderBy('sequence')->get();
        $this->assertSame('released', $occurrences[0]->status);
        $this->assertSame('cancelled', $occurrences[0]->reservation->fresh()->status);
        $this->assertSame('held', $occurrences[1]->status);
        $this->assertSame('held', $occurrences[2]->status);
        $this->assertSame('overdue', $invoice1->fresh()->status);
        $this->assertSame('pending', $invoices[1]->fresh()->status);
        $this->assertSame('delinquent', RecurringSeries::find($seriesId)->status);
        $this->assertSame(
            OwnerPaymentAttempt::STATUS_EXPIRED,
            OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':invoice:'.$invoice1->id)->firstOrFail()->status
        );
    }

    public function test_price_drift_between_preview_and_accept_is_rejected_with_no_holds(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(3));

        // The base price changes after the client accepted the preview.
        PriceRule::where('court_id', $fixture['court']->id)->where('type', 'base')->update(['price' => 250000]);

        $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'drift-1')->assertStatus(409);

        $this->assertDatabaseCount('recurring_series', 0);
        $this->assertDatabaseCount('recurring_occurrences', 0);
        $this->assertDatabaseCount('owner_payment_attempts', 0);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_guarantee_confirm_confirms_at_accept_without_a_payment_attempt(): void
    {
        $fixture = $this->tenant(['guarantee_confirm' => true]);
        $preview = $this->preview($fixture, $this->monthlyPayload(3));

        $response = $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'guarantee-1');

        $response->assertCreated()->assertJsonPath('series.status', 'confirmed');
        $seriesId = (int) $response->json('series.id');

        foreach (RecurringOccurrence::where('recurring_series_id', $seriesId)->get() as $occurrence) {
            $this->assertSame('confirmed', $occurrence->status);
            $this->assertSame('confirmed', $occurrence->reservation->status);
        }
        $this->assertDatabaseCount('owner_payment_attempts', 0);
        $invoices = MonthlyInvoice::where('recurring_series_id', $seriesId)->get();
        $this->assertCount(3, $invoices);
        foreach ($invoices as $invoice) {
            $this->assertSame('guaranteed', $invoice->status);
            $this->assertNull($invoice->paid_at);
            $this->assertNull($invoice->payment_reference);
        }
        $invoices->first()->update(['due_at' => now()->subDays(2)]);
        $this->assertSame(0, app(RecurringSeriesService::class)->markOverdueAndReleaseFuture());
        $this->assertSame('guaranteed', $invoices->first()->fresh()->status);
    }

    public function test_zero_payable_period_confirms_without_a_false_paid_invoice_or_expiry_release(): void
    {
        $fixture = $this->tenant();
        PriceRule::where('court_id', $fixture['court']->id)
            ->where('type', 'base')->update(['price' => 0]);
        $preview = $this->preview($fixture, $this->monthlyPayload(1));

        $response = $this->createSeries($fixture, array_merge($this->monthlyPayload(1), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'zero-payable-1');

        $response->assertCreated()->assertJsonPath('series.status', 'confirmed');
        $invoice = MonthlyInvoice::findOrFail((int) $response->json('series.invoices.0.id'));
        $this->assertSame(0, $invoice->amount);
        $this->assertSame('zero_payable', $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertNull($invoice->payment_reference);
        $this->assertSame(0, OwnerPaymentAttempt::where('purpose', 'recurring_period')->count());
        $this->assertSame(0, app(RecurringSeriesService::class)->markOverdueAndReleaseFuture());
        $this->assertSame('confirmed', $invoice->series->occurrences()->firstOrFail()->fresh()->status);
    }

    public function test_invoice_endpoint_exposes_the_recurring_period_payment_attempt(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'invoice-surface-1')->assertCreated()->json('series.id');

        $invoice = MonthlyInvoice::where('recurring_series_id', $seriesId)->orderBy('period_start')->firstOrFail();
        $attempt = OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':invoice:'.$invoice->id)->firstOrFail();

        $this->actingAs($fixture['payer'])
            ->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->getJson('/api/monthly-invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('payment_attempt.reference', $attempt->reference)
            ->assertJsonPath('payment_attempt.transfer_content', $attempt->reference)
            ->assertJsonPath('payment_attempt.amount', (int) $invoice->amount)
            ->assertJsonPath('payment_attempt.status', 'pending')
            ->assertJsonPath('payment_attempt.receiver.account_number', '0123456789');
    }

    public function test_invoice_rejects_customer_claim_without_verified_owner_receipt(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(1));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(1), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'invoice-client-claim')->assertCreated()->json('series.id');
        $invoice = MonthlyInvoice::where('recurring_series_id', $seriesId)->firstOrFail();

        $this->actingAs($fixture['payer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => 'forged-invoice-claim',
            ])
            ->postJson('/api/monthly-invoices/'.$invoice->id.'/pay', [
                'payment_reference' => 'CLIENT-FABRICATED-PAID',
            ])->assertNotFound();

        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame(0, OwnerPaymentReceipt::count());
        $this->assertSame('held', RecurringOccurrence::where('recurring_series_id', $seriesId)->firstOrFail()->status);
    }

    public function test_owner_admin_cannot_mark_a_period_paid_with_a_manual_reference(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(1));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(1), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'invoice-owner-claim')->assertCreated()->json('series.id');
        $invoice = MonthlyInvoice::where('recurring_series_id', $seriesId)->firstOrFail();

        $this->actingAs($fixture['owner'])
            ->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->postJson("/api/admin/contracts/{$seriesId}/invoices/{$invoice->id}/pay", [
                'payment_reference' => 'OWNER-FABRICATED-PAID',
            ])->assertNotFound();

        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame(0, OwnerPaymentReceipt::count());
    }

    public function test_owner_admin_cannot_create_confirmed_series_and_fake_paid_deposit(): void
    {
        $fixture = $this->tenant();
        $beforeCount = RecurringSeries::count();
        $startsAt = Carbon::now(config('app.timezone'))->addMonths(2)->startOfMonth()->setTime(9, 0);

        $response = $this->actingAs($fixture['owner'])
            ->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->postJson('/api/admin/contracts', [
                'court_id' => $fixture['court']->id,
                'customer_name' => 'Unverified Contract Payer',
                'phone' => '0900009012',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $startsAt->copy()->addMonths(6)->toIso8601String(),
                'monthly_amount' => 100000,
            ]);
        $this->assertTrue(in_array($response->status(), [404, 405], true));
        $this->assertSame($beforeCount, RecurringSeries::count());
        $this->assertSame(0, MonthlyInvoice::where('status', 'paid')->count());
        $this->assertSame(0, OwnerPaymentReceipt::count());
    }

    public function test_replayed_and_second_payment_events_do_not_settle_invoice_twice(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(1));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(1), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'invoice-replay')->assertCreated()->json('series.id');
        $invoice = MonthlyInvoice::where('recurring_series_id', $seriesId)->firstOrFail();
        $attempt = OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':invoice:'.$invoice->id)->firstOrFail();

        $this->sendOwnerPayment($fixture, $attempt->reference, (int) $attempt->amount, 'invoice-event-1');
        $paidAt = $invoice->fresh()->paid_at;
        $this->sendOwnerPayment($fixture, $attempt->reference, (int) $attempt->amount, 'invoice-event-1');
        $this->sendOwnerPayment($fixture, $attempt->reference, (int) $attempt->amount, 'invoice-event-2');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame($attempt->reference, $invoice->fresh()->payment_reference);
        $this->assertTrue($invoice->fresh()->paid_at->equalTo($paidAt));
        $this->assertSame(1, OwnerPaymentAllocation::where('payment_attempt_id', $attempt->id)->count());
        $this->assertSame(2, OwnerPaymentReceipt::where('payment_attempt_id', $attempt->id)->count());
        $this->assertSame('attempt_not_pending', OwnerPaymentReceipt::where('provider_event_id', 'invoice-event-2')->firstOrFail()->exception_code);
        $this->assertDatabaseHas('finance_exceptions', ['type' => 'owner_payment_attempt_not_pending', 'status' => 'open']);
    }

    public function test_late_invoice_receipt_remains_a_money_exception_after_inventory_expiry(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(1));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(1), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'invoice-late')->assertCreated()->json('series.id');
        $invoice = MonthlyInvoice::where('recurring_series_id', $seriesId)->firstOrFail();
        $attempt = OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':invoice:'.$invoice->id)->firstOrFail();

        $attempt->forceFill(['expires_at' => now()->subMinute()])->save();
        $invoice->forceFill(['due_at' => now()->subDays(2)])->save();
        $this->assertSame(1, app(RecurringSeriesService::class)->markOverdueAndReleaseFuture());
        $this->sendOwnerPayment($fixture, $attempt->reference, (int) $attempt->amount, 'invoice-late-event');

        $receipt = OwnerPaymentReceipt::where('provider_event_id', 'invoice-late-event')->firstOrFail();
        $this->assertSame('late', $receipt->status);
        $this->assertSame('transfer_after_expiry', $receipt->exception_code);
        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->paid_at);
        $this->assertSame('released', RecurringOccurrence::where('recurring_series_id', $seriesId)->firstOrFail()->status);
        $this->assertSame(0, OwnerPaymentAllocation::where('payment_attempt_id', $attempt->id)->count());
        $this->assertDatabaseHas('finance_exceptions', [
            'type' => 'owner_payment_transfer_after_expiry',
            'reference' => 'owner-receipt:'.$receipt->id,
            'amount' => (int) $attempt->amount,
            'status' => 'open',
        ]);
    }

    public function test_admin_cancel_releases_future_inventory_voids_open_periods_and_refunds_paid_ones(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'cancel-1')->assertCreated()->json('series.id');
        $invoices = MonthlyInvoice::where('recurring_series_id', $seriesId)->orderBy('period_start')->get();
        $attempts = $invoices->map(fn (MonthlyInvoice $invoice) => OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':invoice:'.$invoice->id)->firstOrFail());
        $this->sendOwnerPayment($fixture, $attempts[0]->reference, (int) $invoices[0]->amount, 'cancel-paid-1');
        $this->sendOwnerPayment($fixture, $attempts[1]->reference, (int) $invoices[1]->amount, 'cancel-paid-2');
        $occurrences = RecurringOccurrence::where('recurring_series_id', $seriesId)->orderBy('sequence')->get();

        // The first session is under way: it and its paid period stay as history.
        $this->travelTo($occurrences[0]->starts_at->copy()->addHour());
        $cancel = fn () => $this->actingAs($fixture['owner'])
            ->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->postJson("/api/admin/contracts/{$seriesId}/cancel", ['reason' => 'Khách dừng hợp đồng'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
        $cancel();

        $this->assertSame(['confirmed', 'released', 'released'], RecurringOccurrence::where('recurring_series_id', $seriesId)->orderBy('sequence')->pluck('status')->all());
        $this->assertSame('confirmed', $occurrences[0]->reservation->fresh()->status);
        $this->assertGreaterThan(0, ReservationSlot::where('reservation_id', $occurrences[0]->reservation_id)->where('status', 'confirmed')->count());
        $this->assertGreaterThan(0, ReservationEntitlement::where('reservation_id', $occurrences[0]->reservation_id)->where('status', 'active')->count());
        foreach ([$occurrences[1], $occurrences[2]] as $released) {
            $this->assertSame('cancelled', $released->reservation->fresh()->status);
            $this->assertSame(0, ReservationSlot::where('reservation_id', $released->reservation_id)->whereIn('status', ['held', 'confirmed'])->count());
            $this->assertGreaterThan(0, ReservationSlot::where('reservation_id', $released->reservation_id)->where('status', 'cancelled')->count());
            $this->assertSame(0, ReservationEntitlement::where('reservation_id', $released->reservation_id)->whereIn('status', ['held', 'active'])->count());
            $this->assertGreaterThan(0, ReservationEntitlement::where('reservation_id', $released->reservation_id)->where('status', 'cancelled')->count());
        }

        $this->assertSame(['paid', 'paid', 'cancelled'], MonthlyInvoice::where('recurring_series_id', $seriesId)->orderBy('period_start')->pluck('status')->all());
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attempts[1]->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, $attempts[2]->fresh()->status);

        // Only the released paid session is owed back, as an owner-executed refund; never a wallet credit.
        $refund = OwnerRefundObligation::sole();
        $this->assertSame(OwnerRefundObligation::STATUS_APPROVED, $refund->status);
        $this->assertSame((int) $attempts[1]->id, (int) $refund->payment_attempt_id);
        $this->assertSame((int) $occurrences[1]->amount, (int) $refund->amount);
        $this->assertSame((int) $occurrences[1]->amount, (int) $refund->gross_amount);
        $this->assertSame(0, (int) $refund->fee_amount);
        $this->assertSame((int) $fixture['payer']->id, (int) $refund->payer_id);
        $this->assertSame((int) $fixture['owner']->id, (int) $refund->approved_by);
        $allocation = OwnerPaymentAllocation::where('payment_attempt_id', $attempts[1]->id)->sole();
        $this->assertSame(
            [(int) $allocation->id => (int) $refund->amount],
            $refund->allocations->mapWithKeys(fn ($share) => [(int) $share->owner_payment_allocation_id => (int) $share->amount])->all()
        );
        $this->assertSame(['approved'], $refund->evidence->pluck('event_type')->all());
        $this->assertSame([(int) $refund->id], RecurringSeries::find($seriesId)->metadata['refund_obligation_ids']);
        $this->assertDatabaseCount('wallet_transactions', 0);

        $cancel();
        $this->assertSame(1, OwnerRefundObligation::count());
        $this->assertSame(1, OwnerRefundAllocation::count());
    }

    public function test_admin_cancel_releases_inventory_when_the_owner_works_in_another_organization(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(1));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(1), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'cancel-cross-org')->assertCreated()->json('series.id');
        $other = Organization::create(['name' => 'Second Club', 'slug' => 'second-club-'.Str::lower(Str::random(8)), 'status' => 'active']);
        OrganizationMember::create([
            'organization_id' => $other->id,
            'user_id' => $fixture['owner']->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $this->actingAs($fixture['owner'])
            ->withHeader('X-Organization-ID', (string) $other->id)
            ->postJson("/api/admin/contracts/{$seriesId}/cancel")
            ->assertOk();

        $occurrence = RecurringOccurrence::withoutGlobalScopes()->where('recurring_series_id', $seriesId)->sole();
        $this->assertSame('released', $occurrence->status);
        $this->assertSame('cancelled', Reservation::withoutGlobalScopes()->findOrFail($occurrence->reservation_id)->status);
        $this->assertSame(0, ReservationSlot::withoutGlobalScopes()->where('reservation_id', $occurrence->reservation_id)->whereIn('status', ['held', 'confirmed'])->count());
        $this->assertSame('cancelled', MonthlyInvoice::withoutGlobalScopes()->where('recurring_series_id', $seriesId)->sole()->status);
    }

    public function test_admin_cancel_of_a_period_paid_without_verified_receipt_opens_a_finance_exception(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(1));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(1), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'cancel-legacy-paid')->assertCreated()->json('series.id');
        // Pre-D43 admin endpoints could mark a period paid with a manual reference and no owner receipt.
        $invoice = MonthlyInvoice::where('recurring_series_id', $seriesId)->sole();
        $invoice->forceFill(['status' => 'paid', 'payment_reference' => 'LEGACY-MANUAL-1', 'paid_at' => now()])->save();

        $this->actingAs($fixture['owner'])
            ->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->postJson("/api/admin/contracts/{$seriesId}/cancel")
            ->assertOk();

        $this->assertSame('released', RecurringOccurrence::where('recurring_series_id', $seriesId)->sole()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0, OwnerRefundObligation::count());
        $this->assertDatabaseHas('finance_exceptions', [
            'type' => 'recurring_unverified_paid_release',
            'reference' => 'monthly-invoice:'.$invoice->id,
            'amount' => (int) $invoice->amount,
            'status' => 'open',
        ]);
    }

    public function test_repair_finishes_a_contract_the_old_admin_path_cancelled(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(3));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(3), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'repair-1')->assertCreated()->json('series.id');
        $invoices = MonthlyInvoice::where('recurring_series_id', $seriesId)->orderBy('period_start')->get();
        $attempts = $invoices->map(fn (MonthlyInvoice $invoice) => OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':invoice:'.$invoice->id)->firstOrFail());
        $this->sendOwnerPayment($fixture, $attempts[0]->reference, (int) $invoices[0]->amount, 'repair-paid-1');
        $this->sendOwnerPayment($fixture, $attempts[1]->reference, (int) $invoices[1]->amount, 'repair-paid-2');
        $occurrences = RecurringOccurrence::where('recurring_series_id', $seriesId)->orderBy('sequence')->get();

        // What the pre-fix admin cancel wrote the day before the first session, then the sweep's delinquent flip.
        $this->travelTo($occurrences[0]->starts_at->copy()->subDay());
        $cancelledAt = now()->toDateTimeString();
        $series = RecurringSeries::findOrFail($seriesId);
        $series->update(['status' => 'delinquent', 'metadata' => array_merge($series->metadata, [
            'cancelled_at' => now()->toIso8601String(),
            'cancel_reason' => 'Khách dừng hợp đồng',
        ])]);
        RecurringOccurrence::where('recurring_series_id', $seriesId)->where('starts_at', '>', now())->update(['status' => 'released', 'released_at' => now()]);
        $admin = User::factory()->create(['role' => 'platform_admin']);

        // The first session has started on its still-valid booking: reported for review, never released or refunded.
        $this->travelTo($occurrences[0]->starts_at->copy()->addHour());
        $this->artisan('contracts:repair-legacy-cancel', ['--actor' => $admin->id])
            ->expectsTable(
                ['Series', 'Organization', 'Status before', 'Sessions released', 'Invoices voided', 'Refund obligations', 'Refund amount', 'Started sessions to review'],
                [[$seriesId, $fixture['organization']->id, 'delinquent', 2, 1, 1, (int) $occurrences[1]->amount, 1]]
            )
            ->expectsOutputToContain('would repair 1 contract(s)')
            ->assertExitCode(0);
        $this->assertSame('confirmed', $occurrences[1]->reservation->fresh()->status);
        $this->assertSame('pending', $invoices[2]->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_PENDING, $attempts[2]->fresh()->status);
        $this->assertSame(0, OwnerRefundObligation::count());

        $this->artisan('contracts:repair-legacy-cancel', ['--actor' => $admin->id, '--apply' => true])
            ->expectsOutputToContain('Repaired 1 contract(s)')
            ->assertExitCode(0);

        $this->assertSame('confirmed', $occurrences[0]->reservation->fresh()->status);
        foreach ([$occurrences[1], $occurrences[2]] as $released) {
            $this->assertSame('cancelled', $released->reservation->fresh()->status);
            $this->assertSame(0, ReservationSlot::where('reservation_id', $released->reservation_id)->whereIn('status', ['held', 'confirmed'])->count());
            $this->assertSame(0, ReservationEntitlement::where('reservation_id', $released->reservation_id)->whereIn('status', ['held', 'active'])->count());
            $this->assertSame($cancelledAt, $released->fresh()->released_at->toDateTimeString());
        }
        $this->assertSame(['paid', 'paid', 'cancelled'], MonthlyInvoice::where('recurring_series_id', $seriesId)->orderBy('period_start')->pluck('status')->all());
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, $attempts[2]->fresh()->status);
        $refund = OwnerRefundObligation::sole();
        $this->assertSame(OwnerRefundObligation::STATUS_APPROVED, $refund->status);
        $this->assertSame((int) $attempts[1]->id, (int) $refund->payment_attempt_id);
        $this->assertSame((int) $occurrences[1]->amount, (int) $refund->amount);
        $this->assertSame((int) $admin->id, (int) $refund->approved_by);
        $this->assertSame('Khách dừng hợp đồng', $refund->reason);
        $series->refresh();
        $this->assertSame('cancelled', $series->status);
        $this->assertSame([(int) $refund->id], $series->metadata['refund_obligation_ids']);
        $this->assertDatabaseCount('wallet_transactions', 0);

        $this->artisan('contracts:repair-legacy-cancel', ['--actor' => $admin->id, '--apply' => true])
            ->expectsOutputToContain('Repaired 0 contract(s)')
            ->assertExitCode(0);
        $this->assertSame(1, OwnerRefundObligation::count());
    }

    public function test_repair_refuses_an_actor_who_is_not_a_platform_admin(): void
    {
        $this->artisan('contracts:repair-legacy-cancel', ['--actor' => User::factory()->create()->id, '--apply' => true])
            ->expectsOutputToContain('--actor must be the id of a platform admin.')
            ->assertExitCode(1);
    }

    public function test_overdue_sweep_keeps_a_cancelled_contract_cancelled(): void
    {
        $fixture = $this->tenant();
        $preview = $this->preview($fixture, $this->monthlyPayload(1));
        $seriesId = (int) $this->createSeries($fixture, array_merge($this->monthlyPayload(1), [
            'accepted' => $this->acceptedFrom($preview),
        ]), 'cancel-then-sweep')->assertCreated()->json('series.id');
        $invoice = MonthlyInvoice::where('recurring_series_id', $seriesId)->sole();

        // The unpaid session is under way, so the cancel keeps its period's invoice as a debt.
        $this->travelTo(RecurringOccurrence::where('recurring_series_id', $seriesId)->sole()->starts_at->copy()->addHour());
        $this->actingAs($fixture['owner'])
            ->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->postJson("/api/admin/contracts/{$seriesId}/cancel")
            ->assertOk();
        $invoice->update(['due_at' => now()->subDays(2)]);
        app(RecurringSeriesService::class)->markOverdueAndReleaseFuture();

        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertSame('cancelled', RecurringSeries::findOrFail($seriesId)->status);
    }

    /**
     * @param  array<string, mixed>  $recurring  Overrides merged into settings.recurring.
     * @return array<string, mixed>
     */
    private function tenant(array $recurring = []): array
    {
        $organization = Organization::create([
            'name' => 'Recurring Owner Club',
            'slug' => 'recurring-owner-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $payer = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $payer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Recurring Owner Venue',
            'slug' => 'recurring-owner-venue-'.Str::lower(Str::random(8)),
            'address' => '202 Test Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Recurring Court', 'is_active' => true]);
        PriceRule::create([
            'organization_id' => $organization->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 100000,
        ]);
        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                // Wide horizon so multi-month recurring occurrences are within book-ahead.
                'booking' => ['hold_ttl_minutes' => 30, 'max_days_ahead' => 400, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'percentage', 'fee_value' => 10, 'refund_due_hours' => 48],
                'change' => ['deadline_hours' => 12, 'fee_type' => 'none', 'fee_value' => 0],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => array_merge([
                    'payment_policy' => 'per_period',
                    'conflict_policy' => 'all_or_nothing',
                    'overdue_release_hours' => 24,
                ], $recurring),
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true],
                'ancillary_credit_policy' => 'refund',
            ],
        ]);
        $connection = new ReceivingConnection([
            'organization_id' => $organization->id,
            'venue_id' => $venue->id,
            'owner_user_id' => $owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'owner_attested_at' => now(),
            'verified_at' => now(),
            'effective_from' => now(),
        ]);
        $connection->setAccountDetails('Recurring Owner', '0123456789');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'payer', 'venue', 'court', 'connection', 'secret');
    }

    /**
     * @return array<string, mixed>
     */
    private function monthlyPayload(int $occurrences): array
    {
        $start = Carbon::now(config('app.timezone'))->addMonths(2)->startOfMonth()->addDays(14)->setTime(9, 0);

        return [
            'court_id' => 0, // filled in preview()/createSeries() from the fixture court
            'frequency' => 'monthly',
            'starts_at' => $start->toIso8601String(),
            'occurrences' => $occurrences,
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function preview(array $fixture, array $payload): array
    {
        $payload['court_id'] = $fixture['court']->id;

        return $this->actingAs($fixture['payer'])
            ->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->postJson('/api/recurring-series/preview', $payload)
            ->assertOk()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function acceptedFrom(array $preview): array
    {
        return [
            'total' => $preview['total_amount'],
            'occurrences' => array_map(
                static fn (array $o): array => ['starts_at' => $o['starts_at'], 'price' => $o['price']],
                $preview['occurrences']
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $payload
     */
    private function createSeries(array $fixture, array $payload, string $idempotencyKey): TestResponse
    {
        $payload['court_id'] = $fixture['court']->id;

        return $this->actingAs($fixture['payer'])
            ->withHeaders([
                'X-Organization-ID' => (string) $fixture['organization']->id,
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->postJson('/api/recurring-series', $payload);
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function occupy(array $fixture, string $startsUtc, string $endsUtc): void
    {
        $reservation = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['owner']->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RSVCONF'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 0,
            'payment_model' => 'legacy_wallet_v1',
        ]);
        ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => Carbon::parse($startsUtc),
            'ends_at' => Carbon::parse($endsUtc),
            'unit_price' => 0,
            'status' => 'confirmed',
            'snapshot' => [],
        ]);
    }

    /**
     * @var array<string, string>
     */
    private array $eventDates = [];

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function sendOwnerPayment(array $fixture, string $reference, int $amount, string $eventId): void
    {
        $connection = $fixture['connection'];
        $payload = [
            'id' => $eventId,
            'transactionDate' => $this->eventDates[$eventId] ??= now()->addSeconds(2)->toIso8601String(),
            'accountNumber' => $connection->accountNumber(),
            'code' => $reference,
            'content' => $reference,
            'transferType' => 'in',
            'transferAmount' => $amount,
            'referenceCode' => 'BANK-'.$eventId,
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $this->call('POST', '/api/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, $fixture['secret']),
        ], $raw)->assertOk();

        $receipt = OwnerPaymentReceipt::where('provider_event_id', $eventId)->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }
    }
}
