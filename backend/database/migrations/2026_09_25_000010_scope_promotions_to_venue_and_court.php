<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_campaigns', function (Blueprint $table): void {
            $table->foreignId('venue_id')->nullable()->after('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('court_id')->nullable()->after('venue_id')->constrained()->restrictOnDelete();
            $table->index(['organization_id', 'venue_id', 'court_id'], 'promotion_campaign_scope_idx');
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->foreignId('venue_id')->nullable()->after('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('court_id')->nullable()->after('venue_id')->constrained()->restrictOnDelete();
            $table->index(['organization_id', 'venue_id', 'court_id'], 'coupon_scope_idx');
        });

        DB::table('coupons')->whereNull('organization_id')->orderBy('id')->chunkById(100, function ($coupons): void {
            foreach ($coupons as $coupon) {
                $metadata = is_array($coupon->metadata) ? $coupon->metadata : json_decode((string) $coupon->metadata, true);
                $organizationId = is_array($metadata) && is_numeric($metadata['organization_id'] ?? null)
                    ? (int) $metadata['organization_id']
                    : null;

                if ($organizationId && ! DB::table('organizations')->where('id', $organizationId)->exists()) {
                    $organizationId = null;
                }

                if (! $organizationId) {
                    $usedByOrganizations = DB::table('bookings')
                        ->join('courts', 'courts.id', '=', 'bookings.court_id')
                        ->join('venues', 'venues.id', '=', 'courts.venue_id')
                        ->where('bookings.coupon_id', $coupon->id)
                        ->whereNotNull('venues.organization_id')
                        ->distinct()
                        ->pluck('venues.organization_id');

                    if ($usedByOrganizations->count() === 1) {
                        $organizationId = (int) $usedByOrganizations->first();
                    }
                }

                if ($organizationId) {
                    DB::table('coupons')->where('id', $coupon->id)->update(['organization_id' => $organizationId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropForeign(['venue_id']);
            $table->dropForeign(['court_id']);
            $table->dropIndex('coupon_scope_idx');
            $table->dropColumn(['venue_id', 'court_id']);
        });

        Schema::table('promotion_campaigns', function (Blueprint $table): void {
            $table->dropForeign(['venue_id']);
            $table->dropForeign(['court_id']);
            $table->dropIndex('promotion_campaign_scope_idx');
            $table->dropColumn(['venue_id', 'court_id']);
        });
    }
};
