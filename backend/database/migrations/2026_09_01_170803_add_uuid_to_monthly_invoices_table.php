<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
        });

        // Populate existing rows with UUID v7 (the MonthlyInvoice model was retired by module 08).
        DB::table('monthly_invoices')->orderBy('id')->each(function (object $invoice) {
            DB::table('monthly_invoices')->where('id', $invoice->id)->update(['uuid' => (string) Str::uuid7()]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
