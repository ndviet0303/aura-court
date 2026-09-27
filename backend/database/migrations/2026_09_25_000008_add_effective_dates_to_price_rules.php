<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_rules', function (Blueprint $table): void {
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('price_rules', function (Blueprint $table): void {
            $table->dropColumn(['effective_from', 'effective_until']);
        });
    }
};
