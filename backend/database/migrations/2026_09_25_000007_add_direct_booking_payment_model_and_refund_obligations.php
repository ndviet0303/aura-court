<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_model', 40)->default('legacy_wallet_v1')->index();
        });

        Schema::create('owner_refund_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('owner_payment_attempts')->restrictOnDelete();
            $table->string('reference_code', 64)->unique();
            $table->string('idempotency_key', 255);
            $table->char('request_fingerprint', 64);
            $table->json('slot_ids');
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->string('status', 32)->default('requested')->index();
            $table->text('reason');
            $table->json('policy_snapshot');
            $table->text('recipient_ciphertext');
            $table->string('execution_reference', 160)->nullable();
            $table->text('execution_evidence_ciphertext')->nullable();
            $table->text('recipient_response_ciphertext')->nullable();
            $table->text('decision_reason')->nullable();
            $table->dateTime('due_at');
            $table->dateTime('requested_at');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('owner_reported_at')->nullable();
            $table->dateTime('recipient_acknowledged_at')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['reservation_id', 'status']);
            $table->index(['payment_attempt_id', 'status']);
        });

        Schema::create('owner_refund_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_refund_obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_payment_allocation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('amount');
            $table->dateTime('created_at');
            $table->unique(['owner_refund_obligation_id', 'owner_payment_allocation_id'], 'owner_refund_allocations_refund_allocation_unique');
            $table->index('owner_payment_allocation_id', 'owner_refund_allocations_allocation_index');
        });

        Schema::create('owner_refund_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_refund_obligation_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('event_fingerprint', 64);
            $table->text('evidence_ciphertext');
            $table->dateTime('created_at');
            $table->index(['owner_refund_obligation_id', 'id'], 'owner_refund_evidence_obligation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_refund_evidence');
        Schema::dropIfExists('owner_refund_allocations');
        Schema::dropIfExists('owner_refund_obligations');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['payment_model']);
            $table->dropColumn('payment_model');
        });
    }
};
