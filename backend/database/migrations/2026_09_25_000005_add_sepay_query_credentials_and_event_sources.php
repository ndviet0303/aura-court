<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receiving_connections', function (Blueprint $table) {
            $table->string('provider_account_id', 120)->nullable();
            $table->text('api_token_ciphertext')->nullable();
            $table->unsignedInteger('api_token_version')->default(0);
            $table->string('last_queried_transaction_id', 190)->nullable();
        });

        Schema::table('receiving_connection_evidence', function (Blueprint $table) {
            $table->string('credential_type', 32)->nullable();
            $table->char('payload_fingerprint', 64)->nullable();
        });

        Schema::table('owner_payment_receipts', function (Blueprint $table) {
            $table->string('source', 24)->default('webhook');
        });
    }

    public function down(): void
    {
        Schema::table('owner_payment_receipts', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('receiving_connection_evidence', function (Blueprint $table) {
            $table->dropColumn(['credential_type', 'payload_fingerprint']);
        });

        Schema::table('receiving_connections', function (Blueprint $table) {
            $table->dropColumn([
                'provider_account_id',
                'api_token_ciphertext',
                'api_token_version',
                'last_queried_transaction_id',
            ]);
        });
    }
};
