<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('price'); // Integer VND
            $table->string('currency', 3)->default('VND');
            $table->string('billing_cycle', 32)->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('trial_enabled')->default(false);
            $table->unsignedSmallInteger('max_venues')->default(1);
            $table->unsignedSmallInteger('max_courts')->default(4);
            $table->unsignedSmallInteger('max_staff')->default(5);
            $table->json('features')->nullable();
            $table->string('terms_version', 32)->default('1.0');
            $table->text('terms_text')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('platform_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('platform_plans')->cascadeOnDelete();
            $table->unsignedInteger('plan_version')->default(1);
            $table->string('status', 32)->default('pending_payment')->index();
            $table->dateTime('trial_started_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            $table->boolean('trial_used')->default(false);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('price_snapshot')->default(0);
            $table->string('currency_snapshot', 3)->default('VND');
            $table->string('terms_version_accepted', 32)->nullable();
            $table->dateTime('terms_accepted_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('platform_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('platform_subscriptions')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('reference_code', 64)->unique();
            $table->unsignedBigInteger('amount'); // Integer VND
            $table->string('currency', 3)->default('VND');
            $table->string('provider', 32)->default('vnpay');
            $table->string('provider_ref', 120)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('platform_provider_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->unique()->constrained('platform_payment_attempts')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_transaction_id', 120)->unique(); // Enforce exactly-once provider receipt
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->json('raw_payload');
            $table->dateTime('verified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_provider_receipts');
        Schema::dropIfExists('platform_payment_attempts');
        Schema::dropIfExists('platform_subscriptions');
        Schema::dropIfExists('platform_plans');
    }
};
