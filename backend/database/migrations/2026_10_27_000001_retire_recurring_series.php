<?php

use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 08 D2: a recurring booking is a set of ordinary bookings, so the series, its occurrences and its monthly
 * invoices go. A future occurrence outside a paid period was booked ahead of an invoice nobody can pay now, so it is
 * released as cancelling the series did; paid ones stay booked. A still-held one is due now, so the pending-booking
 * sweep releases it like any overdue hold; a transfer that still arrives for a series period becomes a payment
 * exception for the owner (unsupported purpose).
 */
return new class extends Migration
{
    public function up(): void
    {
        $unpaid = DB::table('recurring_occurrences as ro')
            ->join('reservations as r', 'r.id', '=', 'ro.reservation_id')
            ->whereIn('r.status', ['held', 'confirmed'])
            ->where('ro.starts_at', '>', now())
            ->whereNotExists(fn ($paid) => $paid->from('monthly_invoices as mi')
                ->whereColumn('mi.recurring_series_id', 'ro.recurring_series_id')
                ->where('mi.status', 'paid')
                ->whereRaw("ro.starts_at >= mi.period_start and ro.starts_at < mi.period_end + interval '1 day'"))
            ->pluck('r.id');
        Reservation::query()->withoutGlobalScope('tenant_isolation')->whereIn('id', $unpaid)
            ->each(fn (Reservation $reservation) => app(ReservationService::class)->release($reservation, 'cancelled'));

        DB::table('reservations')->where('status', 'held')
            ->whereIn('id', DB::table('recurring_occurrences')->whereNotNull('reservation_id')->select('reservation_id'))
            ->update(['hold_expires_at' => now(), 'updated_at' => now()]);
        DB::table('owner_payment_attempts')->where('purpose', 'recurring_period')->where('status', 'pending')
            ->update(['status' => 'expired', 'updated_at' => now()]);
        DB::statement("UPDATE venue_booking_policies SET settings = (settings::jsonb - 'recurring')::json WHERE settings::jsonb -> 'recurring' IS NOT NULL");

        Schema::dropIfExists('monthly_invoices');
        Schema::dropIfExists('recurring_occurrences');
        Schema::dropIfExists('recurring_series');
    }

    /** Rebuilds the empty tables; their rows and the venues' recurring policy are not restored. */
    public function down(): void
    {
        Schema::create('recurring_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->index()->constrained()->restrictOnDelete();
            $table->string('frequency', 16);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('series_ends_at')->nullable();
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
            $table->foreignId('reservation_id')->nullable()->index()->constrained()->nullOnDelete();
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
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('organization_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('recurring_series_id')->constrained('recurring_series')->restrictOnDelete();
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
        DB::statement("ALTER TABLE monthly_invoices ADD CONSTRAINT monthly_invoices_status_check CHECK (status IN ('cancelled', 'guaranteed', 'overdue', 'paid', 'pending', 'zero_payable'))");
    }
};
