<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->dropUnique('platform_plans_code_unique');
            $table->unique(['code', 'version'], 'platform_plans_code_version_unique');
            $table->string('status', 16)->default('published')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retired_at')->nullable();
        });

        DB::table('platform_plans')->where('is_active', true)->update([
            'status' => 'published',
            'published_at' => DB::raw('updated_at'),
        ]);
        DB::table('platform_plans')->where('is_active', false)->update([
            'status' => 'retired',
            'retired_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        $hasMultipleVersions = DB::table('platform_plans')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleVersions) {
            throw new RuntimeException('Cannot restore unique platform plan codes while multiple versions exist.');
        }

        Schema::table('platform_plans', function (Blueprint $table) {
            $table->dropUnique('platform_plans_code_version_unique');
            $table->unique('code', 'platform_plans_code_unique');
            $table->dropColumn(['status', 'published_at', 'retired_at']);
        });
    }
};
