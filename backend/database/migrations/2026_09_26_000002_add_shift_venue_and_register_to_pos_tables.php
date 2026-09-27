<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->foreignId('venue_id')->nullable()->after('organization_id')->constrained('venues')->nullOnDelete();
            $table->string('register_id', 80)->nullable()->after('venue_id');
            $table->json('cash_movements')->nullable()->after('closed_at');
        });

        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->foreignId('cash_shift_id')->nullable()->after('cashier_id')->constrained('cash_shifts')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->after('cash_shift_id')->constrained('venues')->nullOnDelete();
            $table->string('register_id', 80)->nullable()->after('venue_id');
            $table->string('handover_status', 32)->nullable()->after('status');
            $table->foreignId('handed_over_to_shift_id')->nullable()->after('handover_status')->constrained('cash_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', function (Blueprint $table): void {
            $table->dropForeign(['handed_over_to_shift_id']);
            $table->dropForeign(['venue_id']);
            $table->dropForeign(['cash_shift_id']);
            $table->dropColumn([
                'cash_shift_id',
                'venue_id',
                'register_id',
                'handover_status',
                'handed_over_to_shift_id',
            ]);
        });

        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->dropForeign(['venue_id']);
            $table->dropColumn([
                'venue_id',
                'register_id',
                'cash_movements',
            ]);
        });
    }
};
