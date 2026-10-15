<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Social sign-in matches on the provider's own account id, never on email (BR-06). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('social_id', 191)->nullable()->after('social_provider');
            $table->unique(['social_provider', 'social_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['social_provider', 'social_id']);
            $table->dropColumn('social_id');
        });
    }
};
