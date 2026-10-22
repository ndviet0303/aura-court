<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venue packages replace the organization plans (module 05 D1). Business times are UTC; a package's status is derived, never stored.
 * The old platform_* tables stay until task 4.7 drops them.
 */
return new class extends Migration
{
    private const VALUE_CHECKS = [
        ['package_prices', 'term_days', [30, 90, 180, 360]],
        ['venue_packages', 'term_days', [30, 90, 180, 360]],
        ['package_payment_requests', 'term_days', [30, 90, 180, 360]],
        ['package_payment_requests', 'kind', ['purchase', 'renewal']],
        ['package_payment_requests', 'status', ['expired', 'paid', 'pending']],
        ['package_payment_receipts', 'provider', ['sepay']],
        ['package_payment_receipts', 'outcome', ['already_paid', 'amount_mismatch', 'applied', 'late', 'term_conflict', 'unknown_code']],
    ];

    public function up(): void
    {
        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('term_days');
            $table->unsignedBigInteger('amount');
            $table->dateTime('effective_at');
            $table->dateTime('replaced_at')->nullable();
            $table->foreignId('created_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['term_days', 'effective_at']);
        });

        Schema::create('venue_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('term_days');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('terminated_at')->nullable();
            $table->foreignId('terminated_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['venue_id', 'id']);
        });

        Schema::create('package_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 16)->unique();
            $table->string('payment_code', 16)->unique();
            $table->foreignId('organization_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->unsignedSmallInteger('term_days');
            $table->unsignedBigInteger('amount');
            $table->foreignId('package_price_id')->index()->constrained('package_prices')->restrictOnDelete();
            $table->dateTime('expires_at');
            $table->string('status', 16)->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('venue_package_id')->nullable()->index()->constrained('venue_packages')->restrictOnDelete();
            $table->dateTime('period_starts_at')->nullable();
            $table->dateTime('period_ends_at')->nullable();
            $table->foreignId('created_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['venue_id', 'id']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('package_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->default('sepay');
            $table->string('provider_transaction_id', 120)->unique();
            $table->string('payment_code', 16);
            $table->foreignId('package_payment_request_id')->nullable()->index()->constrained('package_payment_requests')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->text('content');
            $table->dateTime('transferred_at');
            $table->dateTime('received_at');
            $table->string('outcome', 32);
            $table->json('raw');
            $table->timestamps();
        });

        foreach (self::VALUE_CHECKS as [$table, $column, $values]) {
            $allowed = implode(', ', array_map(fn (int|string $value) => is_int($value) ? (string) $value : "'{$value}'", $values));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column} IN ({$allowed}))");
        }
        DB::statement('ALTER TABLE package_prices ADD CONSTRAINT package_prices_amount_check CHECK (amount > 0)');
        DB::statement('ALTER TABLE package_payment_requests ADD CONSTRAINT package_payment_requests_amount_check CHECK (amount > 0)');
        DB::statement('ALTER TABLE venue_packages ADD CONSTRAINT venue_packages_period_check CHECK (ends_at > starts_at)');
        // D4: at most one pending request per venue.
        DB::statement("CREATE UNIQUE INDEX package_payment_requests_pending_venue_unique ON package_payment_requests (venue_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('package_payment_receipts');
        Schema::dropIfExists('package_payment_requests');
        Schema::dropIfExists('venue_packages');
        Schema::dropIfExists('package_prices');
    }
};
