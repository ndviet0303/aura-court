<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_kyc_id')->constrained('host_kycs')->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->string('file_path', 255);
            $table->string('file_name', 255);
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 32)->default('uploaded')->index();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['host_kyc_id', 'document_type']);
        });

        Schema::create('partner_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_kyc_id')->constrained('host_kycs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('consent_type', 64);
            $table->string('terms_version', 32);
            $table->dateTime('agreed_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['host_kyc_id', 'consent_type']);
        });

        Schema::create('partner_review_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_kyc_id')->constrained('host_kycs')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 32)->index(); // approve, reject, revision_required
            $table->text('reason')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->json('feedback_fields')->nullable();
            $table->boolean('reapplication_allowed')->default(false);
            $table->unsignedSmallInteger('reapplication_cooloff_days')->default(0);
            $table->unsignedInteger('application_version')->default(1);
            $table->timestamps();

            $table->index(['host_kyc_id', 'created_at']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('partner_status', 32)->default('pending')->index()->after('status');
            $table->text('partner_status_reason')->nullable()->after('partner_status');
            $table->dateTime('partner_status_changed_at')->nullable()->after('partner_status_reason');
            $table->foreignId('source_kyc_id')->nullable()->unique()->constrained('host_kycs')->nullOnDelete()->after('partner_status_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['source_kyc_id']);
            $table->dropColumn([
                'partner_status',
                'partner_status_reason',
                'partner_status_changed_at',
                'source_kyc_id',
            ]);
        });

        Schema::dropIfExists('partner_review_histories');
        Schema::dropIfExists('partner_consents');
        Schema::dropIfExists('partner_documents');
    }
};
