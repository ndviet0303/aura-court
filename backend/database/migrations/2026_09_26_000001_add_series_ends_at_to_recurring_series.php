<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_series', function (Blueprint $table): void {
            // Series horizon (last occurrence's session end), distinct from starts_at/ends_at
            // which remain the FIRST session's start/end template.
            $table->dateTime('series_ends_at')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_series', function (Blueprint $table): void {
            $table->dropColumn('series_ends_at');
        });
    }
};
