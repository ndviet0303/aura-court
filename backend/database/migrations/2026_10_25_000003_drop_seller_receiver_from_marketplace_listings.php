<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Resale buyers pay the venue owner now (resale-venue-credit D9), so the seller's own bank account is deleted for good. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn('seller_receiver_ciphertext');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->text('seller_receiver_ciphertext')->nullable();
        });
    }
};
