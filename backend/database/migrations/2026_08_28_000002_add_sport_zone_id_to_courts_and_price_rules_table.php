<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->foreignId('sport_zone_id')->nullable()->after('venue_id')->constrained('sport_zones')->nullOnDelete();
        });

        Schema::table('price_rules', function (Blueprint $table) {
            $table->foreignId('sport_zone_id')->nullable()->after('venue_id')->constrained('sport_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_zone_id');
        });

        Schema::table('courts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_zone_id');
        });
    }
};
