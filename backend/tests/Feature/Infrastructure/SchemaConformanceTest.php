<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the integrity rules of the migrated schema so a new migration cannot quietly undo them.
 */
class SchemaConformanceTest extends TestCase
{
    use RefreshDatabase;

    private const FINANCIAL_TABLES = [
        'accounts', 'audit_logs', 'beneficiaries', 'cash_shifts', 'elevated_audit_logs', 'entitlement_transfers',
        'finance_exceptions', 'journal_entries', 'journals', 'match_bills', 'monthly_invoices',
        'owner_payment_allocations', 'owner_payment_attempts', 'owner_payment_receipts', 'owner_refund_allocations',
        'owner_refund_evidence', 'owner_refund_obligations', 'payment_intents', 'payments', 'payouts',
        'platform_payment_attempts', 'platform_provider_receipts', 'platform_subscriptions', 'point_ledgers',
        'pos_order_items', 'pos_orders', 'pos_payments', 'provider_events', 'receiving_connection_evidence',
        'reconciliation_runs', 'refunds', 'service_tab_items', 'service_tabs', 'settlements', 'shift_reconciliations',
        'split_groups', 'split_shares', 'stock_movements', 'wallet_accounts', 'wallet_transactions',
    ];

    private const STATUS_CHECK_EXEMPTIONS = [
        'legacy_booking_mappings.legacy_status' => 'verbatim copy of a legacy system value',
        'payouts.status' => 'provider-reported status stored as received',
        'provider_events.status' => 'provider-reported status stored as received',
        'provider_operations.status' => 'provider-reported status stored as received',
        'host_kycs.tax_status' => 'free-text tax registration state entered by the host',
        'host_kycs.lease_contract_status' => 'free-text lease contract note entered by the host',
    ];

    public function test_every_foreign_key_has_an_index_on_its_leading_columns(): void
    {
        $violations = DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS table_name, a.attname AS column_name, c.conname AS constraint_name
            FROM pg_constraint c
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
            WHERE c.contype = 'f'
              AND c.connamespace = 'public'::regnamespace
              AND NOT EXISTS (
                SELECT 1 FROM pg_index i
                WHERE i.indrelid = c.conrelid
                  AND (i.indkey::int2[])[0:array_length(c.conkey, 1) - 1] = c.conkey
              )
            ORDER BY 1, 2
            SQL);

        $this->assertSame([], $this->describe($violations), 'Foreign keys without a covering index.');
    }

    public function test_every_status_column_has_a_check_constraint_unless_exempt(): void
    {
        $columns = DB::select(<<<'SQL'
            SELECT col.table_name, col.column_name
            FROM information_schema.columns col
            WHERE col.table_schema = 'public'
              AND col.data_type IN ('character varying', 'text')
              AND (col.column_name = 'status' OR col.column_name LIKE '%\_status' OR col.column_name = 'state')
              AND NOT EXISTS (
                SELECT 1 FROM pg_constraint k
                JOIN pg_attribute a ON a.attrelid = k.conrelid AND a.attnum = ANY (k.conkey)
                WHERE k.contype = 'c'
                  AND k.conrelid = format('public.%I', col.table_name)::regclass
                  AND a.attname = col.column_name
              )
            ORDER BY 1, 2
            SQL);

        $violations = array_values(array_filter(
            array_map(fn (object $row) => "{$row->table_name}.{$row->column_name}", $columns),
            fn (string $column) => ! array_key_exists($column, self::STATUS_CHECK_EXEMPTIONS),
        ));

        $this->assertSame([], $violations, 'Status columns without a CHECK constraint: add one or a documented exemption.');
    }

    public function test_no_foreign_key_cascades_deletes_into_financial_history(): void
    {
        $violations = DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS table_name, a.attname AS column_name, c.conname AS constraint_name
            FROM pg_constraint c
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
            WHERE c.contype = 'f'
              AND c.confdeltype = 'c'
              AND c.connamespace = 'public'::regnamespace
              AND c.conrelid::regclass::text = ANY (?::text[])
            ORDER BY 1, 2
            SQL, ['{'.implode(',', self::FINANCIAL_TABLES).'}']);

        $this->assertSame([], $this->describe($violations), 'Financial tables reachable by ON DELETE CASCADE.');
    }

    /**
     * @param  array<int, object>  $rows
     * @return list<string>
     */
    private function describe(array $rows): array
    {
        return array_map(fn (object $row) => "{$row->table_name}.{$row->column_name} ({$row->constraint_name})", $rows);
    }
}
