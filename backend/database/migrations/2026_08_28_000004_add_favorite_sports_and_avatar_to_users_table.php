<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('favorite_sports')->nullable()->after('play_level');
            $table->string('avatar_url')->nullable()->after('favorite_sports');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['favorite_sports', 'avatar_url']);
        });
    }
};
