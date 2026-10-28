<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** UC-5.6 E-1: a package fee sent to an account other than AuraCourt's is stored for reconciliation as account_mismatch. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE package_payment_receipts DROP CONSTRAINT package_payment_receipts_outcome_check');
        DB::statement("ALTER TABLE package_payment_receipts ADD CONSTRAINT package_payment_receipts_outcome_check CHECK (outcome IN ('account_mismatch', 'already_paid', 'amount_mismatch', 'applied', 'late', 'term_conflict', 'unknown_code'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE package_payment_receipts SET outcome = 'unknown_code' WHERE outcome = 'account_mismatch'");
        DB::statement('ALTER TABLE package_payment_receipts DROP CONSTRAINT package_payment_receipts_outcome_check');
        DB::statement("ALTER TABLE package_payment_receipts ADD CONSTRAINT package_payment_receipts_outcome_check CHECK (outcome IN ('already_paid', 'amount_mismatch', 'applied', 'late', 'term_conflict', 'unknown_code'))");
    }
};
