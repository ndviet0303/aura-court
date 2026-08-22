<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name', 160);
            $table->string('type', 40);
            $table->string('currency', 3)->default('VND');
            $table->boolean('is_system')->default(false);
            $table->string('status', 24)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('event_id', 160)->nullable();
            $table->string('reference', 160)->nullable()->index();
            $table->string('currency', 3)->default('VND');
            $table->unsignedBigInteger('total_debit');
            $table->unsignedBigInteger('total_credit');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('posted_at');
            $table->timestamps();
            $table->unique(['organization_id', 'event_type', 'event_id']);
            $table->index(['organization_id', 'posted_at']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('side', 8);
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('line_number');
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['journal_id', 'line_number']);
            $table->index(['organization_id', 'account_id']);
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_beneficiary_id', 160)->nullable();
            $table->string('bank_code', 30);
            $table->string('account_number', 80);
            $table->string('account_holder', 180);
            $table->string('tax_id', 80)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->dateTime('verified_at')->nullable();
            $table->json('verification_payload')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'provider', 'bank_code', 'account_number']);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->dateTime('eligible_at');
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->unsignedBigInteger('provider_fee_amount')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->bigInteger('tenant_payable')->default(0);
            $table->string('currency', 3)->default('VND');
            $table->string('status', 32)->default('draft')->index();
            $table->json('snapshot')->nullable();
            $table->dateTime('snapshotted_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'period_start', 'period_end']);
        });

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

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->string('provider', 40)->nullable();
            $table->string('provider_payout_id', 160)->nullable();
            $table->string('idempotency_key', 255);
            $table->string('status', 32)->default('draft')->index();
            $table->json('settlement_snapshot');
            $table->text('failure_reason')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key']);
            $table->unique(['provider', 'provider_payout_id']);
        });

        Schema::create('finance_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 80);
            $table->string('severity', 24)->default('medium')->index();
            $table->string('status', 24)->default('open')->index();
            $table->bigInteger('amount')->nullable();
            $table->string('currency', 3)->default('VND');
            $table->string('reference', 160)->nullable()->index();
            $table->text('description');
            $table->json('links')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('detected_at');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->date('run_date');
            $table->string('status', 24)->index();
            $table->bigInteger('provider_total')->default(0);
            $table->bigInteger('journal_total')->default(0);
            $table->bigInteger('settlement_total')->default(0);
            $table->bigInteger('payout_total')->default(0);
            $table->bigInteger('mismatch_amount')->default(0);
            $table->json('details')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'run_date']);
        });

        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('finance_exceptions');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('reserves');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('beneficiaries');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('accounts');
    }

    private function createAppendOnlyTriggers(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("CREATE OR REPLACE FUNCTION prevent_financial_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Financial records are append-only'; END; $$");
            foreach (['journals', 'journal_entries'] as $table) {
                DB::statement("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_financial_mutation()");
            }
        } elseif ($driver === 'sqlite') {
            foreach (['journals', 'journal_entries'] as $table) {
                DB::statement("CREATE TRIGGER {$table}_append_only_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'Financial records are append-only'); END");
                DB::statement("CREATE TRIGGER {$table}_append_only_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'Financial records are append-only'); END");
            }
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        foreach (['journals_append_only', 'journal_entries_append_only'] as $trigger) {
            if ($driver === 'pgsql') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON ".str_replace('_append_only', '', $trigger));
            } elseif ($driver === 'sqlite') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}_update");
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}_delete");
            }
        }
        if ($driver === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_financial_mutation()');
        }
    }
};
