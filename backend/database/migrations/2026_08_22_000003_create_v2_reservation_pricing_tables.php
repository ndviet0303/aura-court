<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('court_id')->nullable()->constrained('courts')->nullOnDelete();
            $table->string('type', 24);
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->date('exception_date')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'type', 'is_active']);
            $table->index(['organization_id', 'exception_date']);
            $table->index(['court_id', 'weekday', 'starts_at']);
        });

        Schema::create('price_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->restrictOnDelete();
            $table->uuid('quote_token')->unique();
            $table->string('status', 24)->default('open')->index();
            $table->string('currency', 3)->default('VND');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedInteger('rule_version')->default(1);
            $table->json('slot_snapshot');
            $table->json('applied_rule_ids')->nullable();
            $table->dateTime('expires_at')->index();
            $table->dateTime('accepted_at')->nullable();
            $table->json('accepted_snapshot')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'user_id', 'status']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->restrictOnDelete();
            $table->foreignId('price_quote_id')->nullable()->constrained('price_quotes')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('status', 24)->index();
            $table->string('currency', 3)->default('VND');
            $table->unsignedBigInteger('total_amount');
            $table->dateTime('hold_expires_at')->nullable()->index();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['court_id', 'status']);
        });

        Schema::create('reservation_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedBigInteger('unit_price');
            $table->string('status', 24)->index();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->unique(['reservation_id', 'court_id', 'starts_at']);
            $table->index(['organization_id', 'court_id', 'starts_at']);
        });

        Schema::create('reservation_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('reservation_slot_id')->constrained('reservation_slots')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->index();
            $table->timestamps();
            $table->index(['organization_id', 'reservation_id']);
            $table->index(['court_id', 'starts_at', 'status']);
        });

        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 255);
            $table->string('command', 120);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'key']);
        });

        Schema::create('legacy_booking_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_booking_id')->unique();
            $table->string('legacy_code', 32);
            $table->string('legacy_status', 32);
            $table->unsignedBigInteger('legacy_total_amount');
            $table->unsignedInteger('legacy_slot_count');
            $table->char('source_checksum', 64);
            $table->dateTime('migrated_at');
            $table->timestamps();
            $table->unique(['organization_id', 'legacy_code']);
            $table->unique('reservation_id');
        });

        Schema::create('legacy_booking_slot_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('legacy_booking_mapping_id')->constrained('legacy_booking_mappings')->cascadeOnDelete();
            $table->foreignId('reservation_slot_id')->constrained('reservation_slots')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_booking_slot_id')->unique();
            $table->char('source_checksum', 64);
            $table->timestamps();
            $table->unique('reservation_slot_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement("CREATE UNIQUE INDEX reservation_entitlements_active_unique ON reservation_entitlements (court_id, starts_at) WHERE status IN ('held', 'active')");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS reservation_entitlements_active_unique');
        }

        Schema::dropIfExists('legacy_booking_slot_mappings');
        Schema::dropIfExists('legacy_booking_mappings');
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('reservation_entitlements');
        Schema::dropIfExists('reservation_slots');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('price_quotes');
        Schema::dropIfExists('price_rules');
    }
};
