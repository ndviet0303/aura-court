<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** BR-21: staff hold one fixed permission set, so the separate manager and cashier roles fold into staff. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['organization_members', 'organization_invitations'] as $table) {
            DB::table($table)->whereIn('role', ['venue_manager', 'cashier'])->update(['role' => 'staff']);
        }
    }

    public function down(): void
    {
        // The original manager/cashier split is not recoverable once folded.
    }
};
