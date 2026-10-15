<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** BR-15: one organization per business identifier, even when two approvals race. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX organizations_tax_code_unique ON organizations (lower(tax_code)) WHERE tax_code IS NOT NULL AND tax_code <> ''");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS organizations_tax_code_unique');
    }
};
