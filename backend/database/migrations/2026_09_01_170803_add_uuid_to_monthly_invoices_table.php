<?php

use App\Models\MonthlyInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        // Populate existing rows with UUID v7
        MonthlyInvoice::query()->withoutGlobalScope('tenant_isolation')->each(function (MonthlyInvoice $invoice) {
            $invoice->update(['uuid' => (string) Str::uuid7()]);
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
