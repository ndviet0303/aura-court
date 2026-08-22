<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 120)->index();
            $table->string('provider', 40);
            $table->string('provider_intent_id', 160)->nullable();
            $table->string('idempotency_key', 255);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->string('status', 32)->index();
            $table->json('metadata')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('succeeded_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_intent_id']);
            $table->unique(['provider', 'idempotency_key']);
            $table->index(['organization_id', 'reference']);
        });

        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('event_id', 190);
            $table->string('type', 80);
            $table->string('status', 32)->index();
            $table->string('reference', 120)->nullable()->index();
            $table->string('provider_payment_id', 160)->nullable()->index();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('currency', 3)->default('VND');
            $table->json('payload');
            $table->dateTime('verified_at');
            $table->dateTime('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('next_attempt_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('provider_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('type', 40);
            $table->string('idempotency_key', 255);
            $table->string('reference', 120)->nullable()->index();
            $table->string('provider_resource_id', 160)->nullable()->index();
            $table->string('status', 32)->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('next_attempt_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_operations');
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('payment_intents');
    }
};
