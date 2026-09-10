<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_kycs', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->foreignId('current_user_id')->nullable()->unique()->constrained('users')->nullOnDelete()->after('user_id');
            $table->foreignId('parent_kyc_id')->nullable()->constrained('host_kycs')->nullOnDelete()->after('current_user_id');

            // Business & contact detail fields
            $table->string('contact_name', 120)->nullable()->after('representative_name');
            $table->string('contact_phone', 30)->nullable()->after('contact_name');
            $table->string('contact_email', 190)->nullable()->after('contact_phone');
            $table->string('address_line', 255)->nullable()->after('contact_email');
            $table->string('ward', 120)->nullable()->after('address_line');
            $table->string('district', 120)->nullable()->after('ward');
            $table->string('city', 120)->nullable()->after('district');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->json('sports')->nullable()->after('postal_code');

            // Submission snapshot & version tracking
            $table->json('submission_snapshot')->nullable()->after('checklist_state');
            $table->unsignedInteger('submitted_version')->nullable()->after('submission_snapshot');

            // Reapplication & duplicate identity investigation policy
            $table->json('reapplication_policy')->nullable()->after('submitted_version');
            $table->string('investigation_status', 32)->default('clean')->index()->after('reapplication_policy');
        });
    }

    public function down(): void
    {
        Schema::table('host_kycs', function (Blueprint $table) {
            $table->dropForeign(['current_user_id']);
            $table->dropForeign(['parent_kyc_id']);
            $table->dropColumn([
                'version',
                'current_user_id',
                'parent_kyc_id',
                'contact_name',
                'contact_phone',
                'contact_email',
                'address_line',
                'ward',
                'district',
                'city',
                'postal_code',
                'sports',
                'submission_snapshot',
                'submitted_version',
                'reapplication_policy',
                'investigation_status',
            ]);
        });
    }
};
