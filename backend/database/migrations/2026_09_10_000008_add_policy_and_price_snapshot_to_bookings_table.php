<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('policy_snapshot')->nullable()->after('cancellation_reason');
            $table->json('price_snapshot')->nullable()->after('policy_snapshot');
            $table->json('metadata')->nullable()->after('price_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['policy_snapshot', 'price_snapshot', 'metadata']);
        });
    }
};
