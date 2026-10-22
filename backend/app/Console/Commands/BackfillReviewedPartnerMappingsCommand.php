<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\PartnerReviewHistory;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillReviewedPartnerMappingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'partner:backfill-mappings 
                            {--file= : Path to reviewed JSON mapping file} 
                            {--dry-run : Simulate without persisting changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply audited, reviewed legacy partner mappings to organizations and venues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->option('file');
        if (! $filePath || ! file_exists($filePath)) {
            $this->error("Valid --file parameter required. File not found: {$filePath}");

            return self::FAILURE;
        }

        $content = file_get_contents($filePath);
        $json = json_decode($content, true);
        if (! is_array($json) || ! isset($json['mappings']) || ! is_array($json['mappings'])) {
            $this->error("Invalid JSON format. Expected root object with 'mappings' array.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode. No changes will be persisted to the database.');
        }

        $results = [
            'total' => count($json['mappings']),
            'verified' => 0,
            'supplement_requested' => 0,
            'unresolved' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($json['mappings'] as $index => $mapping) {
                $orgId = $mapping['organization_id'] ?? null;
                $decision = $mapping['decision'] ?? 'unresolved';
                $reviewerId = $mapping['reviewer_id'] ?? null;
                $notes = $mapping['notes'] ?? 'Legacy partner migration backfill';

                if (! $orgId) {
                    $results['skipped']++;
                    $results['errors'][] = "Row #{$index}: missing organization_id";

                    continue;
                }

                $org = Organization::find($orgId);
                if (! $org) {
                    $results['skipped']++;
                    $results['errors'][] = "Row #{$index}: organization #{$orgId} not found";

                    continue;
                }

                if ($decision === 'unresolved') {
                    $results['unresolved']++;
                    $this->line("Organization #{$orgId} marked UNRESOLVED - skipped mutation.");

                    continue;
                }

                if ($decision === 'needs_supplement') {
                    $org->update([
                        'partner_status' => 'supplement_requested',
                        'partner_status_reason' => $notes,
                        'partner_status_changed_at' => now(),
                    ]);

                    $kycId = $this->findOrCreateLegacyKyc($org, $reviewerId, 'supplement_requested');

                    PartnerReviewHistory::create([
                        'host_kyc_id' => $kycId,
                        'reviewer_id' => $reviewerId,
                        'action' => 'supplement_requested',
                        'decision' => 'needs_supplement',
                        'reviewer_notes' => $notes,
                    ]);

                    continue;
                }

                if ($decision === 'verified') {
                    // Update partner status
                    $org->update([
                        'partner_status' => 'approved',
                        'partner_status_reason' => $notes,
                        'partner_status_changed_at' => now(),
                    ]);

                    $kycId = $this->findOrCreateLegacyKyc($org, $reviewerId, 'approved');

                    // Record audited review history
                    PartnerReviewHistory::create([
                        'host_kyc_id' => $kycId,
                        'reviewer_id' => $reviewerId,
                        'action' => 'migration_backfill_verified',
                        'decision' => 'approved',
                        'reviewer_notes' => $notes,
                    ]);

                    AuditLog::create([
                        'user_id' => $reviewerId,
                        'action' => 'partner_migration_verified',
                        'entity_type' => Organization::class,
                        'entity_id' => $org->id,
                        'details' => ['notes' => $notes, 'source' => 'backfill_command'],
                    ]);

                    // Venues publication backfill
                    if (isset($mapping['venues']) && is_array($mapping['venues'])) {
                        foreach ($mapping['venues'] as $venueMapping) {
                            $venueId = $venueMapping['venue_id'] ?? null;
                            $venueDecision = $venueMapping['decision'] ?? 'draft';

                            if ($venueId) {
                                $venue = Venue::where('id', $venueId)
                                    ->where('organization_id', $org->id)
                                    ->first();

                                if ($venue) {
                                    if ($venueDecision === 'published') {
                                        $revision = $venue->publicationRevisions()
                                            ->where('status', 'approved')
                                            ->first();

                                        if (! $revision) {
                                            $venue->publicationRevisions()->create([
                                                'revision_number' => 1,
                                                'status' => 'approved',
                                                'submitted_by' => $reviewerId ?? 1,
                                                'submitted_at' => now(),
                                                'configuration_snapshot' => [
                                                    'name' => $venue->name,
                                                    'address' => $venue->address,
                                                    'courts_count' => $venue->courts()->count(),
                                                ],
                                                'reviewed_by' => $reviewerId,
                                                'reviewed_at' => now(),
                                                'reviewer_notes' => 'Backfill verified publication',
                                            ]);
                                        }

                                        $venue->update(['status' => Venue::STATUS_ACTIVE]);
                                    } else {
                                        // ponytail: legacy one-off backfill writes the status directly, bypassing LifecycleGuard; retire with the publication revisions.
                                        $venue->update(['status' => Venue::STATUS_INACTIVE]);
                                    }
                                }
                            }
                        }
                    }

                    $results['verified']++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info('Dry-run complete. Changes rolled back successfully.');
            } else {
                DB::commit();
                $this->info('Backfill transaction committed successfully.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Backfill failed with exception: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Backfill Execution Summary:');
        $this->line("- Total Mappings: {$results['total']}");
        $this->line("- Verified & Provisioned: {$results['verified']}");
        $this->line("- Supplement Requested: {$results['supplement_requested']}");
        $this->line("- Unresolved (Untouched): {$results['unresolved']}");
        $this->line("- Skipped / Errors: {$results['skipped']}");

        return self::SUCCESS;
    }

    private function findOrCreateLegacyKyc(Organization $org, ?int $reviewerId, string $status): int
    {
        if ($org->source_kyc_id) {
            return (int) $org->source_kyc_id;
        }

        $existingKyc = HostKyc::where('organization_id', $org->id)->first();
        if ($existingKyc) {
            $org->update(['source_kyc_id' => $existingKyc->id]);

            return (int) $existingKyc->id;
        }

        $owner = $org->owner;

        $kyc = HostKyc::create([
            'organization_id' => $org->id,
            'user_id' => $owner?->id ?? $reviewerId ?? 1,
            'kyc_code' => 'KYC-MIGRATION-'.$org->id,
            'status' => $status,
            'business_type' => 'enterprise',
            'business_name' => $org->name,
            'legal_name' => $org->name,
            'tax_code' => $org->tax_code ?? '0100000000',
            'representative_name' => $org->name.' Representative',
            'id_card_number' => '001090000000',
            'bank_name' => 'Vietcombank',
            'bank_code' => 'VCB',
            'bank_account_number' => '0000000000',
            'bank_account_holder' => $org->name,
            'submitted_at' => now(),
            'reviewed_at' => $status === 'approved' ? now() : null,
            'reviewed_by' => $status === 'approved' ? $reviewerId : null,
        ]);

        $org->update(['source_kyc_id' => $kyc->id]);

        return (int) $kyc->id;
    }
}
