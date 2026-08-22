<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 80);
            $table->string('barcode', 100)->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('cost')->default(0);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'sku']);
            $table->unique(['organization_id', 'barcode']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('pos_products')->restrictOnDelete();
            $table->string('type', 32);
            $table->integer('quantity_delta');
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'product_id', 'created_at']);
        });

        Schema::create('pos_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('offline_draft')->default(false);
            $table->string('client_reference', 160)->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'client_reference']);
        });

        Schema::create('pos_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cart_id')->constrained('pos_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('pos_products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained('pos_carts')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('completed')->index();
            $table->string('payment_method', 24);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->string('payment_reference', 160)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('pos_products')->restrictOnDelete();
            $table->string('sku', 80);
            $table->string('name', 180);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->string('method', 24);
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('paid')->index();
            $table->string('provider_reference', 160)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('opening_cash');
            $table->unsignedBigInteger('expected_cash')->default(0);
            $table->unsignedBigInteger('declared_cash')->nullable();
            $table->bigInteger('variance')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'cashier_id', 'status']);
        });

        Schema::create('shift_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_shift_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('variance');
            $table->string('status', 24)->index();
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('price');
            $table->json('benefits');
            $table->string('status', 24)->default('draft')->index();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name', 'version']);
        });

        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('tenant_membership_plans')->restrictOnDelete();
            $table->unsignedInteger('plan_version');
            $table->string('status', 24)->default('active')->index();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
        });

        Schema::create('membership_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('tenant_memberships')->cascadeOnDelete();
            $table->string('benefit_code', 80);
            $table->json('benefit_value');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
        });

        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->unsignedInteger('version');
            $table->unsignedInteger('earn_points_per_100_vnd')->default(1);
            $table->unsignedInteger('redeem_points_per_100_vnd')->default(100);
            $table->string('status', 24)->default('draft')->index();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name', 'version']);
        });

        Schema::create('point_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('delta');
            $table->integer('balance_after');
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->dateTime('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'source_type', 'source_id']);
        });

        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('VND');
            $table->unsignedBigInteger('balance')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'currency']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->string('reference', 160)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'wallet_account_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_accounts');
        Schema::dropIfExists('point_ledgers');
        Schema::dropIfExists('loyalty_rules');
        Schema::dropIfExists('membership_entitlements');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenant_membership_plans');
        Schema::dropIfExists('shift_reconciliations');
        Schema::dropIfExists('cash_shifts');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_cart_items');
        Schema::dropIfExists('pos_carts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('pos_products');
    }
};
