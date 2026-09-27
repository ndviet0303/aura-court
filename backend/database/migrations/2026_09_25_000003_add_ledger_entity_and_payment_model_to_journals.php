<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->string('ledger_entity', 32)->nullable()->index();
            $table->string('payment_model', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropIndex(['ledger_entity']);
            $table->dropIndex(['payment_model']);
            $table->dropColumn(['ledger_entity', 'payment_model']);
        });
    }
};
