<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PartnerReviewHistory;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillReviewedPartnerMappingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_processes_mappings_with_idempotency_and_dry_run(): void
    {
        $admin = User::factory()->create();
        $plan = PlatformPlan::create([
            'code' => 'standard',
            'name' => 'Standard Plan',
            'price' => 500000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'max_venues' => 2,
            'max_courts' => 10,
            'max_staff' => 5,
            'features' => ['booking'],
            'is_active' => true,
        ]);

        $org1 = Organization::create([
            'name' => 'Verified Club',
            'slug' => 'verified-club',
            'partner_status' => 'pending_verification',
        ]);
        $venue1 = Venue::create([
            'organization_id' => $org1->id,
            'name' => 'Verified Venue',
            'slug' => 'verified-venue',
            'address' => '123 Venue St',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $org2 = Organization::create([
            'name' => 'Unresolved Club',
            'slug' => 'unresolved-club',
            'partner_status' => 'pending_verification',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'mappings_').'.json';
        file_put_contents($tempFile, json_encode([
            'mappings' => [
                [
                    'organization_id' => $org1->id,
                    'decision' => 'verified',
                    'reviewer_id' => $admin->id,
                    'notes' => 'Contract verified on paper',
                    'plan_code' => 'standard',
                    'venues' => [
                        ['venue_id' => $venue1->id, 'decision' => 'published'],
                    ],
                ],
                [
                    'organization_id' => $org2->id,
                    'decision' => 'unresolved',
                ],
            ],
        ]));

        try {
            // 1. Dry run should make zero persistent changes
            $this->artisan('partner:backfill-mappings', [
                '--file' => $tempFile,
                '--dry-run' => true,
            ])->assertSuccessful()
                ->expectsOutputToContain('Running in DRY-RUN mode')
                ->expectsOutputToContain('Dry-run complete. Changes rolled back successfully.');
            $this->assertSame('pending_verification', $org1->fresh()->partner_status);
            $this->assertSame('draft', $venue1->fresh()->status);
            $this->assertSame(0, PlatformSubscription::count());

            // 2. Real backfill run
            $this->artisan('partner:backfill-mappings', [
                '--file' => $tempFile,
            ])->assertSuccessful()
                ->expectsOutputToContain('Backfill transaction committed successfully.')
                ->expectsOutputToContain('- Verified & Provisioned: 1')
                ->expectsOutputToContain('- Unresolved (Untouched): 1');

            $this->assertSame('approved', $org1->fresh()->partner_status);
            $this->assertSame('published', $venue1->fresh()->status);
            $this->assertTrue((bool) $venue1->fresh()->is_active);
            $this->assertSame('pending_verification', $org2->fresh()->partner_status);

            $this->assertSame(1, PlatformSubscription::where('organization_id', $org1->id)->count());
            $this->assertSame(1, PartnerReviewHistory::count());
            $this->assertSame(1, AuditLog::where('action', 'partner_migration_verified')->count());

            // 3. Rerun should be idempotent and not duplicate records
            $this->artisan('partner:backfill-mappings', [
                '--file' => $tempFile,
            ])->assertSuccessful();

            $this->assertSame(1, PlatformSubscription::where('organization_id', $org1->id)->count());
            $this->assertSame(1, $venue1->publicationRevisions()->count());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
