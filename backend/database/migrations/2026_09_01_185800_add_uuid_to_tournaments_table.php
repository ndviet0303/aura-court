<?php

use App\Models\Tournament;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tournaments', 'uuid')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id')->unique();
            });
        }

        // Populate existing rows with UUID v7
        Tournament::query()->withoutGlobalScope('tenant_isolation')->each(function (Tournament $tournament) {
            if (empty($tournament->uuid)) {
                $tournament->update(['uuid' => (string) Str::uuid7()]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tournaments', 'uuid')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
    }
};
