<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\MonthlyInvoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\RecurringSeriesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringSeriesAndInvoicesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Organization,1:User,2:Court} */
    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Series Tenant',
            'slug' => 'series-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'customer', 'status' => 'active']);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Series Venue',
            'slug' => 'series-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = $venue->courts()->create(['name' => 'Series Court', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 150000]);

        return [$organization, $user, $court];
    }

    public function test_recurring_series_creation_reserves_occurrences_and_generates_monthly_invoices(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $startsAt = Carbon::now(config('app.timezone'))->addDays(5)->setTime(9, 0)->startOfHour();

        $service = app(RecurringSeriesService::class);
        $series = $service->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $startsAt->toIso8601String(),
            'occurrences' => 4,
        ]);

        $this->assertSame('confirmed', $series->status);
        $this->assertCount(4, $series->occurrences);
        $this->assertNotEmpty($series->invoices);

        // Every occurrence has confirmed status and attached reservation
        foreach ($series->occurrences as $occurrence) {
            $this->assertSame('confirmed', $occurrence->status);
            $this->assertNotNull($occurrence->reservation);
            $this->assertSame('confirmed', $occurrence->reservation->status);
        }

        // Invoice is generated in pending state
        $invoice = $series->invoices->first();
        $this->assertSame('pending', $invoice->status);
        $this->assertGreaterThan(0, $invoice->amount);
    }


    public function test_overdue_invoice_releases_future_inventory_and_marks_series_delinquent(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $startsAt = Carbon::now(config('app.timezone'))->addDays(5)->setTime(9, 0)->startOfHour();

        $service = app(RecurringSeriesService::class);
        $series = $service->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $startsAt->toIso8601String(),
            'occurrences' => 4,
        ]);

        $invoice = $series->invoices->first();
        // Force invoice to be past due
        $invoice->update(['due_at' => Carbon::now()->subDay()]);

        $releasedCount = $service->markOverdueAndReleaseFuture();
        $this->assertGreaterThan(0, $releasedCount);

        // Invoice is overdue
        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->overdue_at);

        // Series is delinquent
        $this->assertSame('delinquent', $series->fresh()->status);

        // Future occurrences released and reservations cancelled
        $futureOccurrences = $series->occurrences()->where('starts_at', '>', now())->get();
        foreach ($futureOccurrences as $occ) {
            $this->assertSame('released', $occ->status);
            $this->assertSame('cancelled', $occ->reservation?->fresh()->status);
        }
    }

    public function test_upcoming_invoice_reminder_enqueues_notification(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $startsAt = Carbon::now(config('app.timezone'))->addDays(14)->setTime(9, 0)->startOfHour();

        $service = app(RecurringSeriesService::class);
        $series = $service->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $startsAt->toIso8601String(),
            'occurrences' => 2,
        ]);

        $invoice = $series->invoices->first();
        // Set due_at between 7 and 8 days from now
        $invoice->update(['due_at' => Carbon::now()->addDays(7)->addHours(12)]);

        $notified = $service->notifyUpcomingInvoices();
        $this->assertSame(1, $notified);
        $this->assertNotNull($invoice->fresh()->reminder_sent_at);

        $this->assertDatabaseHas('outbox_messages', [
            'aggregate_type' => MonthlyInvoice::class,
            'aggregate_id' => $invoice->id,
            'event_type' => 'recurring.invoice_due',
        ]);
    }

    public function test_overdue_release_leaves_paid_invoice_inventory_tracked(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $service = app(RecurringSeriesService::class);

        // Series A: an unpaid invoice forced past due -> its future inventory must release.
        $startA = Carbon::now(config('app.timezone'))->addDays(5)->setTime(9, 0)->startOfHour();
        $seriesA = $service->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $startA->toIso8601String(),
            'occurrences' => 4,
        ]);
        $invoiceA = $seriesA->invoices->first();
        $invoiceA->update(['due_at' => Carbon::now()->subDay()]);

        // Series B: an invoice that has RECEIVED money (paid) -> must stay tracked and untouched.
        $startB = Carbon::now(config('app.timezone'))->addDays(6)->setTime(11, 0)->startOfHour();
        $seriesB = $service->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $startB->toIso8601String(),
            'occurrences' => 4,
        ]);
        $invoiceB = $seriesB->invoices->first();
        $invoiceB->forceFill([
            'status' => 'paid',
            'payment_reference' => 'PRE-CUTOVER-RECEIPT',
            'paid_at' => Carbon::now()->subDay(),
        ])->save();
        // Force the paid invoice's due date into the past to prove that status, not the date, gates release.
        $invoiceB->refresh();
        $invoiceB->update(['due_at' => Carbon::now()->subDay()]);

        $released = $service->markOverdueAndReleaseFuture();
        $this->assertGreaterThan(0, $released);

        // Series A future inventory released and series marked delinquent.
        $this->assertSame('overdue', $invoiceA->fresh()->status);
        $this->assertSame('delinquent', $seriesA->fresh()->status);
        foreach ($seriesA->occurrences()->where('starts_at', '>', now())->get() as $occ) {
            $this->assertSame('released', $occ->status);
            $this->assertSame('cancelled', $occ->reservation?->fresh()->status);
        }

        // Series B (received money) remains paid and tracked; its inventory is untouched.
        $this->assertSame('paid', $invoiceB->fresh()->status);
        $this->assertNotSame('delinquent', $seriesB->fresh()->status);
        foreach ($seriesB->occurrences()->where('starts_at', '>', now())->get() as $occ) {
            $this->assertSame('confirmed', $occ->status);
            $this->assertSame('confirmed', $occ->reservation?->fresh()->status);
        }
    }
}
