<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_subscriptions', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true)->after('ends_at');
            $table->string('billing_cycle', 16)->default('monthly')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('platform_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['auto_renew', 'billing_cycle']);
        });
    }
};
