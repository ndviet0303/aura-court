<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deleting an owner must not silently erase financial history: these foreign keys
 * move from ON DELETE CASCADE to ON DELETE RESTRICT, keeping their names.
 */
return new class extends Migration
{
    /** [child table, column, parent table] */
    private const FOREIGN_KEYS = [
        ['refunds', 'booking_id', 'bookings'],
        ['elevated_audit_logs', 'user_id', 'users'],
        ['accounts', 'organization_id', 'organizations'],
        ['journals', 'organization_id', 'organizations'],
        ['journal_entries', 'organization_id', 'organizations'],
        ['beneficiaries', 'organization_id', 'organizations'],
        ['settlements', 'organization_id', 'organizations'],
        ['payouts', 'organization_id', 'organizations'],
        ['monthly_invoices', 'organization_id', 'organizations'],
        ['monthly_invoices', 'recurring_series_id', 'recurring_series'],
        ['split_groups', 'organization_id', 'organizations'],
        ['split_groups', 'reservation_id', 'reservations'],
        ['split_shares', 'organization_id', 'organizations'],
        ['split_shares', 'split_group_id', 'split_groups'],
        ['entitlement_transfers', 'organization_id', 'organizations'],
        ['stock_movements', 'organization_id', 'organizations'],
        ['pos_orders', 'organization_id', 'organizations'],
        ['pos_order_items', 'order_id', 'pos_orders'],
        ['pos_order_items', 'organization_id', 'organizations'],
        ['pos_payments', 'order_id', 'pos_orders'],
        ['pos_payments', 'organization_id', 'organizations'],
        ['cash_shifts', 'organization_id', 'organizations'],
        ['shift_reconciliations', 'cash_shift_id', 'cash_shifts'],
        ['shift_reconciliations', 'organization_id', 'organizations'],
        ['point_ledgers', 'organization_id', 'organizations'],
        ['point_ledgers', 'user_id', 'users'],
        ['wallet_accounts', 'organization_id', 'organizations'],
        ['wallet_accounts', 'user_id', 'users'],
        ['wallet_transactions', 'organization_id', 'organizations'],
        ['wallet_transactions', 'wallet_account_id', 'wallet_accounts'],
        ['service_tabs', 'opened_by', 'users'],
        ['service_tabs', 'organization_id', 'organizations'],
        ['service_tabs', 'reservation_id', 'reservations'],
        ['service_tab_items', 'product_id', 'pos_products'],
        ['service_tab_items', 'service_tab_id', 'service_tabs'],
        ['platform_subscriptions', 'organization_id', 'organizations'],
        ['platform_subscriptions', 'plan_id', 'platform_plans'],
        ['platform_payment_attempts', 'organization_id', 'organizations'],
        ['platform_payment_attempts', 'subscription_id', 'platform_subscriptions'],
        ['platform_provider_receipts', 'payment_attempt_id', 'platform_payment_attempts'],
        ['match_bills', 'match_post_id', 'match_posts'],
        ['match_bills', 'organization_id', 'organizations'],
        ['owner_refund_obligations', 'organization_id', 'organizations'],
        ['owner_refund_allocations', 'owner_refund_obligation_id', 'owner_refund_obligations'],
        ['owner_refund_evidence', 'owner_refund_obligation_id', 'owner_refund_obligations'],
    ];

    public function up(): void
    {
        $this->redefine('RESTRICT');
    }

    public function down(): void
    {
        $this->redefine('CASCADE');
    }

    private function redefine(string $onDelete): void
    {
        foreach (self::FOREIGN_KEYS as [$table, $column, $parent]) {
            $constraint = "{$table}_{$column}_foreign";

            DB::statement(
                "ALTER TABLE {$table} DROP CONSTRAINT {$constraint}, "
                ."ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) REFERENCES {$parent} (id) ON DELETE {$onDelete}"
            );
        }
    }
};
