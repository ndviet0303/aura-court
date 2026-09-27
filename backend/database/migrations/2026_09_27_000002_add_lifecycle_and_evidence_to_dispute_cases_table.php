<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispute_cases', function (Blueprint $table) {
            $table->string('court_name', 120)->nullable()->change();
            $table->string('facility_name', 190)->nullable()->change();
            $table->string('complainant_name', 120)->nullable()->change();
            $table->string('respondent_name', 120)->nullable()->change();

            $table->string('reference_type', 32)->nullable()->after('booking_id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->foreignId('duplicate_of_id')->nullable()->after('reference_id')->constrained('dispute_cases')->nullOnDelete();
            $table->json('evidence')->nullable()->after('chat_messages');
            $table->json('requested_evidence')->nullable()->after('evidence');
            $table->json('decision_history')->nullable()->after('digital_signature_hash');
            $table->integer('decision_version')->default(1)->after('decision_history');
            $table->foreignId('refund_obligation_id')->nullable()->after('decision_version')->constrained('owner_refund_obligations')->nullOnDelete();
            $table->dateTime('reopened_at')->nullable()->after('refund_obligation_id');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by');
            $table->foreignId('assigned_reviewer_id')->nullable()->after('reopen_reason')->constrained('users')->nullOnDelete();
            $table->dateTime('conflict_reassigned_at')->nullable()->after('assigned_reviewer_id');
            $table->foreignId('conflict_reassigned_by')->nullable()->after('conflict_reassigned_at')->constrained('users')->nullOnDelete();
            $table->text('reassignment_reason')->nullable()->after('conflict_reassigned_by');
        });
    }

    public function down(): void
    {
        Schema::table('dispute_cases', function (Blueprint $table) {
            $table->dropForeign(['duplicate_of_id']);
            $table->dropForeign(['refund_obligation_id']);
            $table->dropForeign(['reopened_by']);
            $table->dropForeign(['assigned_reviewer_id']);
            $table->dropForeign(['conflict_reassigned_by']);

            $table->dropColumn([
                'reference_type',
                'reference_id',
                'duplicate_of_id',
                'evidence',
                'requested_evidence',
                'decision_history',
                'decision_version',
                'refund_obligation_id',
                'reopened_at',
                'reopened_by',
                'reopen_reason',
                'assigned_reviewer_id',
                'conflict_reassigned_at',
                'conflict_reassigned_by',
                'reassignment_reason',
            ]);
        });
    }
};
