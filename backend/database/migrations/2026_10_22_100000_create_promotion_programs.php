<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venue promotion programs (design D7, D8, D14, D27) and their voucher uses (D11). Program times are wall-clock
 * times on the application clock (AppTimezoneDateTime, D13). A percentage is stored in hundredths of a percent
 * (12.5% = 1250). Programs are never deleted, so nothing that keeps usage history cascades from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 16);
            $table->string('discount_kind', 16);
            $table->unsignedInteger('percent_bp')->nullable();
            $table->unsignedInteger('amount')->nullable();
            $table->unsignedInteger('max_discount')->nullable();
            $table->unsignedInteger('min_rental')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('code_count')->nullable();
            $table->dateTime('stopped_at')->nullable();
            $table->timestamps();
            $table->index('venue_id');
        });

        Schema::create('promotion_program_courts', function (Blueprint $table) {
            $table->foreignId('program_id')->constrained('promotion_programs')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->primary(['program_id', 'court_id']);
            $table->index('court_id');
        });

        Schema::create('voucher_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('promotion_programs')->restrictOnDelete();
            // Unique across the platform: a code alone names its program (D8).
            $table->string('code', 32)->unique();
            $table->timestamps();
            $table->index('program_id');
        });

        Schema::create('voucher_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained('promotion_programs')->restrictOnDelete();
            $table->foreignId('voucher_code_id')->nullable()->constrained('voucher_codes')->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('discount');
            $table->dateTime('held_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
            $table->index(['program_id', 'status']);
        });

        DB::statement("ALTER TABLE promotion_programs ADD CONSTRAINT promotion_programs_type_check CHECK (type IN ('public', 'private'))");
        // A NULL makes a CHECK pass, so each required term is tested IS NOT NULL.
        DB::statement(<<<'SQL'
            ALTER TABLE promotion_programs ADD CONSTRAINT promotion_programs_discount_check CHECK (
                (discount_kind = 'percent' AND percent_bp IS NOT NULL AND percent_bp BETWEEN 1 AND 10000 AND amount IS NULL)
                OR (discount_kind = 'fixed' AND amount IS NOT NULL AND amount > 0 AND percent_bp IS NULL AND max_discount IS NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE promotion_programs ADD CONSTRAINT promotion_programs_limits_check CHECK (
                (max_discount IS NULL OR max_discount > 0) AND (min_rental IS NULL OR min_rental > 0)
                AND ends_at > starts_at
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE promotion_programs ADD CONSTRAINT promotion_programs_uses_check CHECK (
                (type = 'public' AND max_uses IS NOT NULL AND max_uses > 0 AND code_count IS NULL)
                OR (type = 'private' AND code_count IS NOT NULL AND code_count > 0 AND max_uses IS NULL)
            )
            SQL);
        DB::statement("ALTER TABLE voucher_uses ADD CONSTRAINT voucher_uses_status_check CHECK (status IN ('held', 'used', 'released'))");
        // A private code is held or used by at most one booking (D11); released uses keep their history.
        DB::statement("CREATE UNIQUE INDEX voucher_uses_code_active_unique ON voucher_uses (voucher_code_id) WHERE status IN ('held', 'used')");
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_uses');
        Schema::dropIfExists('voucher_codes');
        Schema::dropIfExists('promotion_program_courts');
        Schema::dropIfExists('promotion_programs');
    }
};
