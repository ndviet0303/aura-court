<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * memberships/membership_plans were superseded by tenant_memberships/tenant_membership_plans,
 * and reserves never had a writer. Refuse to drop a table that unexpectedly holds rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // memberships references membership_plans, so it goes first.
        foreach (['memberships', 'membership_plans', 'reserves'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("Refusing to drop {$table}: the table still has rows.");
            }

            Schema::drop($table);
        }
    }

    public function down(): void
    {
        Schema::create('reserves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 100);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->string('status', 32)->default('held')->index();
            $table->json('metadata')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['user_id', 'starts_at', 'ends_at']);
        });
    }
};
