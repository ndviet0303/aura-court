<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE venues ALTER COLUMN name TYPE json USING json_build_object('en', name)");

            DB::statement("ALTER TABLE courts ALTER COLUMN name TYPE json USING json_build_object('en', name)");
            DB::statement("ALTER TABLE courts ALTER COLUMN description TYPE json USING CASE WHEN description IS NOT NULL THEN json_build_object('en', description) ELSE NULL END");

            DB::statement("ALTER TABLE sport_zones ALTER COLUMN name TYPE json USING json_build_object('en', name)");
            DB::statement("ALTER TABLE sport_zones ALTER COLUMN description TYPE json USING CASE WHEN description IS NOT NULL THEN json_build_object('en', description) ELSE NULL END");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE venues ALTER COLUMN name TYPE varchar(120) USING name->>'en'");

            DB::statement("ALTER TABLE courts ALTER COLUMN name TYPE varchar(255) USING name->>'en'");
            DB::statement("ALTER TABLE courts ALTER COLUMN description TYPE text USING description->>'en'");

            DB::statement("ALTER TABLE sport_zones ALTER COLUMN name TYPE varchar(255) USING name->>'en'");
            DB::statement("ALTER TABLE sport_zones ALTER COLUMN description TYPE text USING description->>'en'");
        }
    }
};
