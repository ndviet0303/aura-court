<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peer-to-peer resale: the buyer pays the seller directly, so a paid listing stores the seller's
 * own receiving account, encrypted. Older listings have none and cannot be locked until it is added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->text('seller_receiver_ciphertext')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn('seller_receiver_ciphertext');
        });
    }
};
