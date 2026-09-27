<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('receiving_connection_id')->constrained()->restrictOnDelete();
            $table->string('payment_model', 32)->default('direct_owner_v1');
            $table->string('provider', 32)->default('sepay');
            $table->string('purpose', 48);
            $table->string('purpose_id', 120);
            $table->string('reference', 120);
            $table->string('idempotency_key', 255);
            $table->char('request_fingerprint', 64);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('VND');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('credential_version');
            $table->text('receiver_snapshot_ciphertext');
            $table->text('purpose_snapshot_ciphertext');
            $table->dateTime('expires_at')->index();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key']);
            $table->unique(['organization_id', 'reference']);
            $table->index(['receiving_connection_id', 'status', 'expires_at']);
            $table->index(['purpose', 'purpose_id']);
        });

        Schema::create('owner_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('receiving_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('receiving_connection_evidence_id')->constrained('receiving_connection_evidence')->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('owner_payment_attempts')->restrictOnDelete();
            $table->string('provider', 32)->default('sepay');
            $table->string('provider_event_id', 190);
            $table->char('event_fingerprint', 64);
            $table->string('purpose', 48)->nullable();
            $table->string('purpose_id', 120)->nullable();
            $table->string('payment_code', 120)->nullable()->index();
            $table->string('reference_code', 190)->nullable();
            $table->string('transfer_type', 8);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->dateTime('event_at')->nullable()->index();
            $table->unsignedInteger('credential_version');
            $table->string('status', 32)->index();
            $table->string('exception_code', 48)->nullable();
            $table->text('receiver_snapshot_ciphertext');
            $table->text('purpose_snapshot_ciphertext')->nullable();
            $table->dateTime('received_at');
            $table->timestamps();

            $table->unique(['receiving_connection_id', 'provider', 'provider_event_id']);
            $table->index(['organization_id', 'venue_id', 'status']);
            $table->index(['payment_attempt_id', 'status']);
        });

        Schema::create('owner_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('receiving_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->constrained('owner_payment_attempts')->restrictOnDelete();
            $table->foreignId('payment_receipt_id')->unique()->constrained('owner_payment_receipts')->restrictOnDelete();
            $table->string('purpose', 48);
            $table->string('purpose_id', 120);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->unsignedInteger('credential_version');
            $table->text('receiver_snapshot_ciphertext');
            $table->text('purpose_snapshot_ciphertext');
            $table->timestamp('allocated_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['payment_attempt_id', 'payment_receipt_id']);
            $table->index(['organization_id', 'venue_id', 'allocated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_payment_allocations');
        Schema::dropIfExists('owner_payment_receipts');
        Schema::dropIfExists('owner_payment_attempts');
    }
};
