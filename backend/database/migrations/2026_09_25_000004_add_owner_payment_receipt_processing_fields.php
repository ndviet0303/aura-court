<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_payment_receipts', function (Blueprint $table) {
            $table->dateTime('processed_at')->nullable();
            $table->unsignedInteger('processing_attempts')->default(0);
            $table->dateTime('next_attempt_at')->nullable()->index();
            $table->string('processing_error', 120)->nullable();
        });

        DB::table('owner_payment_receipts')
            ->where('status', '!=', 'pending')
            ->update(['processed_at' => DB::raw('received_at')]);
    }

    public function down(): void
    {
        Schema::table('owner_payment_receipts', function (Blueprint $table) {
            $table->dropIndex(['next_attempt_at']);
            $table->dropColumn(['processed_at', 'processing_attempts', 'next_attempt_at', 'processing_error']);
        });
    }
};
