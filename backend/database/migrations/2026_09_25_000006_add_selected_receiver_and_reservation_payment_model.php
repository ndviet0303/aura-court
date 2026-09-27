<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->foreignId('current_receiving_connection_id')
                ->nullable()
                ->constrained('receiving_connections')
                ->nullOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('payment_model', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['payment_model']);
            $table->dropColumn('payment_model');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_receiving_connection_id');
        });
    }
};
