<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->string('address')->nullable();
            $table->text('google_maps_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        $defaultVenueId = DB::table('venues')->insertGetId([
            'name' => 'Aura Court',
            'slug' => 'aura-court',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::table('courts', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('id');
        });

        DB::table('courts')->whereNull('venue_id')->update(['venue_id' => $defaultVenueId]);

        Schema::table('courts', function (Blueprint $table) {
            $table->foreign('venue_id')->references('id')->on('venues')->restrictOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE courts ALTER COLUMN venue_id SET NOT NULL');
        } else {
            // SQLite is used by some local tooling. Its schema builder cannot
            // change nullability without rebuilding the table, but all rows
            // are still backfilled and the application requires the field.
            Schema::table('courts', function (Blueprint $table) {
                $table->unsignedBigInteger('venue_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });

        Schema::dropIfExists('venues');
    }
};
