<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status CHECK constraints for rollout acceptance, drill and pilot migration runs.
 * Allowed values are what the code writes and reads plus what existing data holds.
 * Adding a status means a migration that drops and re-adds the constraint.
 */
return new class extends Migration
{
    private const CHECKS = [
        ['acceptance_runs', 'status', ['blocked', 'passed']],
        ['operational_drill_runs', 'status', ['blocked', 'failed', 'manual', 'passed', 'running']],
        ['pilot_migration_runs', 'status', ['blocked', 'passed', 'running']],
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
