<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Design D14: one immutable row per remediation submission; the request's state is its latest row's. The partial
 * unique index keeps one pending submission per suspension even under concurrent requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspension_remediations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_suspension_id')->constrained()->restrictOnDelete();
            $table->text('content');
            $table->json('files');
            $table->string('status', 24)->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['venue_suspension_id', 'id']);
            $table->index(['status', 'submitted_at']);
        });
        DB::statement("ALTER TABLE suspension_remediations ADD CONSTRAINT suspension_remediations_status_check CHECK (status IN ('pending', 'more_info_requested', 'rejected', 'approved'))");
        DB::statement("CREATE UNIQUE INDEX suspension_remediations_one_pending ON suspension_remediations (venue_suspension_id) WHERE status = 'pending'");
        DB::statement('ALTER TABLE venue_suspensions ADD CONSTRAINT venue_suspensions_remediation_fk FOREIGN KEY (remediation_id) REFERENCES suspension_remediations (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE venue_suspensions DROP CONSTRAINT IF EXISTS venue_suspensions_remediation_fk');
        Schema::dropIfExists('suspension_remediations');
    }
};
