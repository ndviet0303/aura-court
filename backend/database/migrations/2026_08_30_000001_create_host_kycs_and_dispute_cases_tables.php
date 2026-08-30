<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_kycs', function (Blueprint $table) {
            $table->id();
            $table->string('kyc_code', 64)->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_type', 32);
            $table->string('business_name', 190);
            $table->string('legal_name', 190);
            $table->string('tax_code', 64);
            $table->string('tax_status', 64)->default('Đang hoạt động');
            $table->string('representative_name', 120);
            $table->string('id_card_number', 64);
            $table->text('id_card_front_url')->nullable();
            $table->text('id_card_back_url')->nullable();
            $table->text('id_card_portrait_url')->nullable();
            $table->text('business_license_url')->nullable();
            $table->string('bank_name', 120);
            $table->string('bank_code', 32);
            $table->string('bank_account_number', 64);
            $table->string('bank_account_holder', 120);
            $table->string('napas_match_status', 32)->default('pending');
            $table->unsignedSmallInteger('court_scale')->default(1);
            $table->string('lighting_spec')->nullable();
            $table->string('lease_contract_status')->nullable();
            $table->json('facility_images')->nullable();
            $table->decimal('ai_ocr_score', 5, 2)->nullable();
            $table->decimal('face_match_score', 5, 2)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->json('checklist_state')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dispute_cases', function (Blueprint $table) {
            $table->id();
            $table->string('dispute_code', 64)->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('court_name', 120);
            $table->string('dispute_type', 48);
            $table->string('severity', 24)->default('medium')->index();
            $table->string('title', 190);
            $table->text('summary');
            $table->string('status', 32)->default('pending')->index();
            $table->dateTime('sla_deadline')->nullable();
            $table->unsignedBigInteger('amount_disputed')->default(0);
            $table->string('currency', 3)->default('VND');
            $table->foreignId('complainant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('complainant_name', 120);
            $table->string('complainant_tier', 64)->nullable();
            $table->unsignedSmallInteger('complainant_reputation_score')->default(100);
            $table->string('complainant_phone', 32)->nullable();
            $table->foreignId('respondent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('respondent_name', 120);
            $table->string('respondent_tier', 64)->nullable();
            $table->unsignedSmallInteger('respondent_reputation_score')->default(100);
            $table->string('respondent_phone', 32)->nullable();
            $table->string('facility_name', 190);
            $table->string('facility_operator_name', 120)->nullable();
            $table->json('timeline_events')->nullable();
            $table->json('chat_messages')->nullable();
            $table->json('court_map_context')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->string('resolution_decision', 48)->nullable();
            $table->string('refund_method', 32)->nullable();
            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->string('compensation_type', 48)->nullable();
            $table->string('compensation_value', 120)->nullable();
            $table->text('penalty_notes')->nullable();
            $table->integer('penalty_points')->nullable();
            $table->foreignId('arbitrated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('arbitrated_at')->nullable();
            $table->text('arbitrator_notes')->nullable();
            $table->string('digital_signature_hash', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_cases');
        Schema::dropIfExists('host_kycs');
    }
};
