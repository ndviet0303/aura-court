<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\MonthlyInvoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PriceRule;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Infrastructure\OutboxService;
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

    public function test_monthly_invoice_payment_transitions_status(): void
    {
        [$organization, $user, $court] = $this->tenant();
        $startsAt = Carbon::now(config('app.timezone'))->addDays(5)->setTime(9, 0)->startOfHour();

        $service = app(RecurringSeriesService::class);
        $series = $service->createAndConfirm($user, $organization->id, [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $startsAt->toIso8601String(),
            'occurrences' => 2,
        ]);

        $invoice = $series->invoices->first();
        $this->assertSame('pending', $invoice->status);

        $paid = $service->payInvoice($invoice, 'BANK-TRANSFER-REF-99');
        $this->assertSame('paid', $paid->status);
        $this->assertSame('BANK-TRANSFER-REF-99', $paid->payment_reference);
        $this->assertNotNull($paid->paid_at);
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
}
