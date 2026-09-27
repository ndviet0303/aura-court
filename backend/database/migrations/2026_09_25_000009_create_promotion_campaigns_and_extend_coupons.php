<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('eligibility_group', 40)->default('all_customers');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->boolean('can_combine_with_membership')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('retired_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'starts_at', 'ends_at']);
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_campaign_id')->nullable()->constrained('promotion_campaigns')->nullOnDelete();
            $table->string('status', 20)->nullable();
            $table->string('eligibility_group', 40)->default('all_customers');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('retired_at')->nullable();
            $table->index(['organization_id', 'status']);
            $table->index('promotion_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['promotion_campaign_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['promotion_campaign_id']);
            $table->dropColumn([
                'organization_id',
                'promotion_campaign_id',
                'status',
                'eligibility_group',
                'version',
                'created_by',
                'updated_by',
                'activated_at',
                'suspended_at',
                'retired_at',
            ]);
        });

        Schema::dropIfExists('promotion_campaigns');
    }
};
