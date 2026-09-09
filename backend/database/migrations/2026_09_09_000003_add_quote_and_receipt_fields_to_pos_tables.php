<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_amount')->default(0)->after('discount_amount');
            $table->bigInteger('rounding_amount')->default(0)->after('total_amount');
            $table->unsignedBigInteger('cash_tendered')->nullable()->after('payment_reference');
            $table->unsignedBigInteger('change_due')->default(0)->after('cash_tendered');
        });

        Schema::table('pos_order_items', function (Blueprint $table) {
            $table->unsignedInteger('rental_duration_hours')->default(1)->after('quantity');
            $table->unsignedInteger('tax_rate')->default(0)->after('line_total');
            $table->unsignedBigInteger('tax_amount')->default(0)->after('tax_rate');
            $table->json('metadata')->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pos_order_items', function (Blueprint $table) {
            $table->dropColumn(['rental_duration_hours', 'tax_rate', 'tax_amount', 'metadata']);
        });

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn(['tax_amount', 'rounding_amount', 'cash_tendered', 'change_due']);
        });
    }
};
