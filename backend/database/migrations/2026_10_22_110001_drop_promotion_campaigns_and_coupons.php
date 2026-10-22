<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * align-module-03-pricing-promotions, Migration Plan step 3 (design D22): campaigns and coupons are not carried over to
 * promotion programs and their tables go. Run on prod only after a backup and the user's confirmation. down() rebuilds
 * the tables empty; their rows come back only from the backup.
 *
 * The only foreign key into them is bookings.coupon_id; the constraint goes and the column stays as the old booking's
 * coupon snapshot, like the coupon_usage snapshots in reservation and POS order metadata. A held reservation or pending
 * POS QR order that still holds a coupon means the two expiry migrations before this one never ran, so the guard aborts
 * instead of dropping uses still held. Guard and drops are one PostgreSQL statement, so the same SQL is safe when
 * applied through psql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM reservations WHERE status = 'held'
                        AND jsonb_typeof(metadata::jsonb -> 'coupon_usage') = 'object'
                        AND COALESCE(metadata::jsonb -> 'coupon_usage' ->> 'released_at', '') = '')
                    OR EXISTS (SELECT 1 FROM pos_orders WHERE status = 'pending_payment'
                        AND metadata::jsonb -> 'coupon_usage' ->> 'status' = 'reserved') THEN
                    RAISE EXCEPTION 'held reservations or pending POS orders still hold coupons; run 2026_10_22_090000_cancel_pending_pos_qr_orders_holding_coupons and 2026_10_22_110000_expire_held_reservations_holding_coupons through artisan migrate first';
                END IF;
                ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_coupon_id_foreign;
                DROP TABLE IF EXISTS coupons;
                DROP TABLE IF EXISTS promotion_campaigns;
            END $$
            SQL);
    }

    public function down(): void
    {
        Schema::create('promotion_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('court_id')->nullable()->index()->constrained()->restrictOnDelete();
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
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('retired_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'starts_at', 'ends_at']);
            $table->index(['organization_id', 'venue_id', 'court_id'], 'promotion_campaign_scope_idx');
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('discount_type', 16);
            $table->unsignedBigInteger('discount_value');
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->boolean('can_combine_with_membership')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('court_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('promotion_campaign_id')->nullable()->index()->constrained('promotion_campaigns')->nullOnDelete();
            $table->string('status', 20)->nullable();
            $table->string('eligibility_group', 40)->default('all_customers');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('retired_at')->nullable();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'venue_id', 'court_id'], 'coupon_scope_idx');
        });

        DB::statement("ALTER TABLE promotion_campaigns ADD CONSTRAINT promotion_campaigns_status_check CHECK (status IN ('active', 'draft', 'retired', 'suspended'))");
        DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_status_check CHECK (status IN ('active', 'draft', 'retired', 'suspended'))");
        // Old bookings still name coupons that are gone until the backup is restored, so existing rows are not checked.
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_coupon_id_foreign FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL NOT VALID');
    }
};
