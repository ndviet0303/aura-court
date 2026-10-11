<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status CHECK constraints for payments and finance. payouts, provider_events and provider_operations store provider-reported values and stay unconstrained.
 * Allowed values are what the code writes and reads plus what existing data holds.
 * Adding a status means a migration that drops and re-adds the constraint.
 */
return new class extends Migration
{
    private const CHECKS = [
        ['payments', 'status', ['manual_review', 'paid', 'rejected']],
        ['refunds', 'status', ['completed', 'pending', 'processed', 'rejected']],
        ['owner_payment_attempts', 'status', ['cancelled', 'expired', 'paid', 'pending']],
        ['owner_payment_receipts', 'status', ['allocated', 'exception', 'late', 'pending', 'unmatched']],
        ['owner_refund_obligations', 'status', ['acknowledged', 'approved', 'owner_reported', 'recipient_disputed', 'rejected', 'requested']],
        ['payment_intents', 'status', ['failed', 'pending', 'succeeded']],
        ['platform_payment_attempts', 'status', ['failed', 'pending', 'succeeded']],
        ['platform_subscriptions', 'status', ['active', 'cancelled', 'expired', 'pending_payment', 'trial']],
        ['platform_plans', 'status', ['draft', 'published', 'retired']],
        ['monthly_invoices', 'status', ['guaranteed', 'overdue', 'paid', 'pending', 'zero_payable']],
        ['settlements', 'status', ['draft', 'eligible', 'pending']],
        ['beneficiaries', 'status', ['pending', 'verified']],
        ['accounts', 'status', ['active']],
        ['wallet_accounts', 'status', ['active']],
        ['reconciliation_runs', 'status', ['failed', 'matched', 'mismatch', 'running']],
        ['finance_exceptions', 'status', ['dismissed', 'investigating', 'open', 'resolved']],
        ['outbox_messages', 'status', ['exhausted', 'pending', 'processing', 'published']],
    ];

    public function up(): void
    {
        foreach (self::CHECKS as [$table, $column, $values]) {
            $allowed = implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$allowed}))");
        }
    }

    public function down(): void
    {
        foreach (self::CHECKS as [$table, $column]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_{$column}_check");
        }
    }
};
