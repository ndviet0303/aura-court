<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venues') && ! Schema::hasColumn('venues', 'amenities')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->json('amenities')->nullable()->after('google_maps_url');
            });
        }

        if (Schema::hasTable('courts') && ! Schema::hasColumn('courts', 'amenities')) {
            Schema::table('courts', function (Blueprint $table) {
                $table->json('amenities')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venues') && Schema::hasColumn('venues', 'amenities')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropColumn('amenities');
            });
        }

        if (Schema::hasTable('courts') && Schema::hasColumn('courts', 'amenities')) {
            Schema::table('courts', function (Blueprint $table) {
                $table->dropColumn('amenities');
            });
        }
    }
};
