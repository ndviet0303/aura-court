<?php

namespace Tests\Feature;

use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\ProviderEvent;
use App\Jobs\ProcessOutboxMessage;
use App\Services\LedgerService;
use App\Services\OutboxService;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class FinancialFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unbalanced_journals_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LedgerService::class)->post([], [
            ['side' => 'debit', 'amount' => 100],
            ['side' => 'credit', 'amount' => 99],
        ]);
    }

    public function test_balanced_journal_is_append_only_and_has_equal_totals(): void
    {
        $organization = Organization::create(['name' => 'Ledger Tenant', 'slug' => 'ledger-'.uniqid()]);
        $accounts = app(LedgerService::class)->systemAccounts($organization->id);
        $journal = app(LedgerService::class)->post([
            'organization_id' => $organization->id,
            'event_type' => 'test.collection',
            'event_id' => 'test-'.uniqid(),
        ], [
            ['account_id' => $accounts['cash_clearing']->id, 'side' => 'debit', 'amount' => 1000],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => 1000],
        ]);

        $this->assertSame(1000, $journal->total_debit);
        $this->assertSame($journal->total_debit, $journal->total_credit);
        $this->assertCount(2, $journal->entries);
    }

    public function test_provider_event_identifier_is_deduplicated(): void
    {
        $organization = Organization::create(['name' => 'Webhook Tenant', 'slug' => 'webhook-'.uniqid()]);
        ProviderEvent::create([
            'organization_id' => $organization->id,
            'provider' => 'payos',
            'event_id' => 'event-1',
            'type' => 'payment.status',
            'status' => 'PAID',
            'amount' => 1000,
            'payload' => ['id' => 'event-1'],
            'verified_at' => now(),
        ]);

        $this->assertDatabaseCount('provider_events', 1);
        $this->assertSame(1, ProviderEvent::where('provider', 'payos')->where('event_id', 'event-1')->count());
    }

    public function test_duplicate_outbox_key_schedules_one_worker(): void
    {
        Queue::fake();
        $service = app(OutboxService::class);
        $attributes = ['message_key' => 'test-outbox-1', 'channel' => 'realtime', 'event_type' => 'test.updated'];
        $first = $service->enqueue($attributes + ['organization_id' => null], ['payload' => true]);
        $second = $service->enqueue($attributes + ['organization_id' => null], ['payload' => false]);

        $this->assertSame($first->id, $second->id);
        Queue::assertPushed(ProcessOutboxMessage::class, 1);
    }

    public function test_reconciliation_creates_a_traceable_exception_for_a_mismatch(): void
    {
        $organization = Organization::create(['name' => 'Reconcile Tenant', 'slug' => 'reconcile-'.uniqid()]);
        ProviderEvent::create([
            'organization_id' => $organization->id,
            'provider' => 'payos',
            'event_id' => 'reconcile-event-1',
            'type' => 'payment.status',
            'status' => 'PAID',
            'amount' => 100000,
            'payload' => ['id' => 'reconcile-event-1'],
            'verified_at' => now(),
        ]);

        $run = app(ReconciliationService::class)->run($organization);

        $this->assertSame('mismatch', $run->status);
        $exception = FinanceException::where('type', 'reconciliation_mismatch')->firstOrFail();
        $this->assertSame('open', $exception->status);
        $this->assertTrue($exception->due_at->greaterThanOrEqualTo(now()->addMinutes(14)));
    }
}
