<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->string('frequency', 16);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('occurrence_count');
            $table->unsignedSmallInteger('max_occurrences')->default(104);
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->dateTime('next_invoice_due_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'user_id', 'status']);
        });

        Schema::create('recurring_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurring_series_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('held')->index();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
            $table->unique(['recurring_series_id', 'sequence']);
            $table->index(['organization_id', 'starts_at', 'status']);
        });

        Schema::create('monthly_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurring_series_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->dateTime('due_at');
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('pending')->index();
            $table->string('payment_reference', 160)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('overdue_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['recurring_series_id', 'period_start', 'period_end']);
        });

        Schema::create('split_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('expected_amount');
            $table->unsignedSmallInteger('share_count');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->dateTime('expires_at');
            $table->boolean('host_covers_shortfall')->default(false);
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique('reservation_id');
        });

        Schema::create('split_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('split_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('share_number');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('payment_reference', 160)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();
            $table->unique(['split_group_id', 'share_number']);
            $table->index(['organization_id', 'payment_reference']);
        });

        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_entitlement_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('original_price');
            $table->unsignedBigInteger('asking_price');
            $table->unsignedSmallInteger('fee_bps')->default(500);
            $table->string('status', 24)->default('listed')->index();
            $table->dateTime('cutoff_at');
            $table->dateTime('sold_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'cutoff_at']);
        });

        Schema::create('entitlement_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_entitlement_id')->constrained('reservation_entitlements')->restrictOnDelete();
            $table->foreignId('buyer_entitlement_id')->nullable()->constrained('reservation_entitlements')->nullOnDelete();
            $table->unsignedBigInteger('buyer_amount');
            $table->unsignedBigInteger('marketplace_fee');
            $table->unsignedBigInteger('seller_refund');
            $table->string('buyer_payment_reference', 160)->nullable();
            $table->string('seller_refund_reference', 160)->nullable();
            $table->string('idempotency_key', 255);
            $table->string('status', 24)->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key']);
            $table->unique('buyer_entitlement_id');
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement("CREATE UNIQUE INDEX marketplace_listings_active_entitlement_unique ON marketplace_listings (reservation_entitlement_id) WHERE status IN ('listed', 'checkout')");
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS marketplace_listings_active_entitlement_unique');
        }
        Schema::dropIfExists('entitlement_transfers');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('split_shares');
        Schema::dropIfExists('split_groups');
        Schema::dropIfExists('monthly_invoices');
        Schema::dropIfExists('recurring_occurrences');
        Schema::dropIfExists('recurring_series');
    }
};
