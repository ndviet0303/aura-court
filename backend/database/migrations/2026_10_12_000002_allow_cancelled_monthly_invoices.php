<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling a recurring contract voids its open invoices for periods that have not started.
 * down() fails while cancelled invoices exist: reopening them would recreate debts for released sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceCheck(['cancelled', 'guaranteed', 'overdue', 'paid', 'pending', 'zero_payable']);
    }

    public function down(): void
    {
        $this->replaceCheck(['guaranteed', 'overdue', 'paid', 'pending', 'zero_payable']);
    }

    /**
     * @param  list<string>  $values
     */
    private function replaceCheck(array $values): void
    {
        $allowed = implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
        DB::statement('ALTER TABLE monthly_invoices DROP CONSTRAINT monthly_invoices_status_check');
        DB::statement("ALTER TABLE monthly_invoices ADD CONSTRAINT monthly_invoices_status_check CHECK (status IN ({$allowed}))");
    }
};
