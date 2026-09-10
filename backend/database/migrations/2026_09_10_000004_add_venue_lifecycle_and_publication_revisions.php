<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_publication_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 32)->default('pending')->index(); // pending, approved, revision_required, superseded
            $table->json('configuration_snapshot');
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->json('feedback_fields')->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'revision_number']);
            $table->index(['venue_id', 'status']);
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->index()->after('is_active');
            $table->text('status_reason')->nullable()->after('status');
            $table->dateTime('published_at')->nullable()->after('status_reason');
            $table->json('approved_public_snapshot')->nullable()->after('published_at');
            $table->foreignId('current_publication_revision_id')
                ->nullable()
                ->constrained('venue_publication_revisions')
                ->nullOnDelete()
                ->after('approved_public_snapshot');
        });

        // Backfill existing venues: map is_active = true to status = 'active', else 'draft'
        DB::table('venues')->where('is_active', true)->update(['status' => 'active', 'published_at' => now()]);
        DB::table('venues')->where('is_active', false)->update(['status' => 'draft']);
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['current_publication_revision_id']);
            $table->dropColumn([
                'status',
                'status_reason',
                'published_at',
                'approved_public_snapshot',
                'current_publication_revision_id',
            ]);
        });

        Schema::dropIfExists('venue_publication_revisions');
    }
};
