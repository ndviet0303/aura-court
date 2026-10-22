<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * align-module-05-packages-finance D1, task 4.7: venue packages replaced the organization plans, so the old platform_*
 * tables go, child tables first. down() rebuilds them empty with the constraints they had; their rows come back only
 * from the backup taken before the cutover (D16).
 */
return new class extends Migration
{
    /** Added by 2026_10_11_000002_restrict_deletes_into_financial_tables. */
    private const FOREIGN_KEYS = [
        ['platform_provider_receipts', 'payment_attempt_id'],
        ['platform_payment_attempts', 'subscription_id'],
        ['platform_payment_attempts', 'organization_id'],
        ['platform_subscriptions', 'plan_id'],
        ['platform_subscriptions', 'organization_id'],
    ];

    /** Added by 2026_10_11_000005_add_finance_status_checks. */
    private const STATUS_CHECKS = [
        'platform_payment_attempts' => ['failed', 'pending', 'succeeded'],
        'platform_subscriptions' => ['active', 'cancelled', 'expired', 'pending_payment', 'trial'],
        'platform_plans' => ['draft', 'published', 'retired'],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEYS as [$table, $column]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_{$column}_foreign");
        }
        foreach (array_keys(self::STATUS_CHECKS) as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_status_check");
        }
        foreach (['platform_provider_receipts', 'platform_payment_attempts', 'platform_subscriptions', 'platform_plans'] as $table) {
            Schema::drop($table);
        }
    }

    public function down(): void
    {
        Schema::create('platform_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('name', 120);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('price');
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
            $table->string('status', 16)->default('published')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->unique(['code', 'version'], 'platform_plans_code_version_unique');
        });

        Schema::create('platform_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('plan_id')->index()->constrained('platform_plans')->restrictOnDelete();
            $table->unsignedInteger('plan_version')->default(1);
            $table->string('status', 32)->default('pending_payment')->index();
            $table->string('billing_cycle', 16)->default('monthly');
            $table->dateTime('trial_started_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            $table->boolean('trial_used')->default(false);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('auto_renew')->default(true);
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
            $table->foreignId('subscription_id')->nullable()->index()->constrained('platform_subscriptions')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('reference_code', 64)->unique();
            $table->unsignedBigInteger('amount');
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
            $table->foreignId('payment_attempt_id')->unique()->constrained('platform_payment_attempts')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_transaction_id', 120)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->json('raw_payload');
            $table->dateTime('verified_at');
            $table->timestamps();
        });

        foreach (self::STATUS_CHECKS as $table => $values) {
            $allowed = implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ({$allowed}))");
        }
    }
};
