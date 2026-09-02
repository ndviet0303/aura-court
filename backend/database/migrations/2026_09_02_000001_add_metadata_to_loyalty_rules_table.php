<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loyalty_rules') && ! Schema::hasColumn('loyalty_rules', 'metadata')) {
            Schema::table('loyalty_rules', function (Blueprint $table) {
                $table->json('metadata')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loyalty_rules') && Schema::hasColumn('loyalty_rules', 'metadata')) {
            Schema::table('loyalty_rules', function (Blueprint $table) {
                $table->dropColumn('metadata');
            });
        }
    }
};
