<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status CHECK constraints for POS and on-site service.
 * Allowed values are what the code writes and reads plus what existing data holds.
 * Adding a status means a migration that drops and re-adds the constraint.
 */
return new class extends Migration
{
    private const CHECKS = [
        ['pos_orders', 'status', ['cancelled', 'completed', 'pending_payment', 'refunded']],
        ['pos_orders', 'handover_status', ['handed_over']],
        ['pos_payments', 'status', ['cancelled', 'completed', 'paid', 'pending']],
        ['pos_carts', 'status', ['draft']],
        ['cash_shifts', 'status', ['closed', 'open']],
        ['shift_reconciliations', 'status', ['approved', 'exception', 'matched', 'rejected']],
        ['service_tabs', 'status', ['cancelled', 'settled', 'unpaid']],
        ['service_tab_items', 'status', ['released', 'reserved', 'settled']],
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
