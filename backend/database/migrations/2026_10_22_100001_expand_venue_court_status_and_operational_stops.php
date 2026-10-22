<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expand step of the four-status model (design D3, D5, Migration Plan step 4).
 * venues.status accepts the old and the new values until the contract migration narrows it;
 * courts get their own status; suspensions and operational stops get their tables.
 * Stop and suspension times are UTC, like reservation_slots and court_blocks.
 */
return new class extends Migration
{
    private const OLD_VENUE_STATUSES = ['active', 'archived', 'closed', 'draft', 'pending_publication', 'published', 'revision_required', 'suspended', 'temporarily_closed'];

    private const STATUSES = ['inactive', 'active', 'paused', 'closed'];

    public function up(): void
    {
        $this->replaceVenueStatusCheck(array_values(array_unique([...self::OLD_VENUE_STATUSES, ...self::STATUSES])), 'inactive');

        Schema::table('courts', function (Blueprint $table) {
            $table->string('status', 16)->default('inactive')->index();
        });
        DB::statement('ALTER TABLE courts ADD CONSTRAINT courts_status_check CHECK (status IN ('.$this->quoted(self::STATUSES).'))');

        Schema::create('venue_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('suspended_at');
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('lifted_at')->nullable();
            // The foreign key to suspension_remediations comes with that table (task 5.5).
            $table->unsignedBigInteger('remediation_id')->nullable();
            $table->timestamps();
            $table->index(['venue_id', 'suspended_at']);
            $table->index('suspended_by');
            $table->index('lifted_by');
        });
        DB::statement('CREATE UNIQUE INDEX venue_suspensions_one_active ON venue_suspensions (venue_id) WHERE lifted_at IS NULL');

        Schema::create('operational_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->text('reason');
            $table->dateTime('starts_at');
            $table->dateTime('expected_end_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['venue_id', 'court_id', 'starts_at']);
            $table->index(['court_id', 'starts_at']);
            $table->index('created_by');
            $table->index('ended_by');
        });
        // A null court_id stops the whole venue; maintenance is for a court only.
        DB::statement("ALTER TABLE operational_stops ADD CONSTRAINT operational_stops_kind_check CHECK (kind IN ('maintenance', 'business', 'closure') AND (kind <> 'maintenance' OR court_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_stops');
        Schema::dropIfExists('venue_suspensions');
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        // Fails while a venue still holds a new-only value: run the data migration's down first.
        $this->replaceVenueStatusCheck(self::OLD_VENUE_STATUSES, 'draft');
    }

    /** @param  list<string>  $values */
    private function replaceVenueStatusCheck(array $values, string $default): void
    {
        DB::statement('ALTER TABLE venues DROP CONSTRAINT venues_status_check');
        DB::statement('ALTER TABLE venues ADD CONSTRAINT venues_status_check CHECK (status IN ('.$this->quoted($values).'))');
        DB::statement("ALTER TABLE venues ALTER COLUMN status SET DEFAULT '{$default}'");
    }

    /** @param  list<string>  $values */
    private function quoted(array $values): string
    {
        return implode(', ', array_map(fn (string $value) => "'{$value}'", $values));
    }
};
