<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venue credit (resale-venue-credit D2, D7): an organization owes a customer credit after their slot sold
 * through the owner's account. balance is spendable, reserved is held by an unpaid booking; entries are append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('reserved')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index('user_id');
        });
        DB::statement('ALTER TABLE venue_credit_accounts ADD CONSTRAINT venue_credit_accounts_balance_check CHECK (balance >= 0 AND reserved >= 0)');

        Schema::create('venue_credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_credit_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->bigInteger('balance_delta');
            $table->bigInteger('reserved_delta');
            $table->bigInteger('balance_after');
            $table->bigInteger('reserved_after');
            $table->string('source_type', 40);
            $table->string('source_id', 64);
            $table->string('reference', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['source_type', 'source_id', 'type']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['venue_credit_account_id', 'id']);
            $table->index('user_id');
            $table->index('created_by');
        });
        DB::statement("ALTER TABLE venue_credit_entries ADD CONSTRAINT venue_credit_entries_type_check CHECK (type IN ('issue', 'reserve', 'release', 'capture', 'spend', 'return', 'cash_out'))");

        Schema::create('venue_credit_cash_out_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->bigInteger('amount');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cash_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'user_id']);
            $table->index('user_id');
            $table->index('used_by');
            $table->index('cash_shift_id');
        });
        DB::statement('ALTER TABLE venue_credit_cash_out_codes ADD CONSTRAINT venue_credit_cash_out_codes_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_credit_cash_out_codes');
        Schema::dropIfExists('venue_credit_entries');
        Schema::dropIfExists('venue_credit_accounts');
    }
};
