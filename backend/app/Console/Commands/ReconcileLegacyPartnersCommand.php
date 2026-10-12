<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\HostKyc;
use App\Models\Organization;
use App\Models\PlatformSubscription;
use Illuminate\Console\Command;

class ReconcileLegacyPartnersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'partner:reconcile-legacy 
                            {--format=table : Output format (table|json)}
                            {--output-file= : Save output to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit legacy organizations, venues, and subscriptions without mutating production data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting read-only legacy partner and venue reconciliation audit...');

        $organizations = Organization::query()->with(['venues'])->get();
        $records = [];
        $summary = [
            'total_organizations' => $organizations->count(),
            'missing_application_evidence' => 0,
            'missing_platform_subscriptions' => 0,
            'venues_missing_publication_revisions' => 0,
            'total_outstanding_bookings' => 0,
        ];

        foreach ($organizations as $org) {
            $hasApplication = HostKyc::query()
                ->where('organization_id', $org->id)
                ->where('status', 'approved')
                ->exists();
            $hasSubscription = PlatformSubscription::query()
                ->where('organization_id', $org->id)
                ->where('status', 'active')
                ->exists();

            $venuesWithoutRevision = 0;
            $orgBookingsCount = 0;

            foreach ($org->venues as $venue) {
                $hasRevision = $venue->publicationRevisions()
                    ->where('status', 'approved')
                    ->exists();

                if (! $hasRevision && ($venue->is_active || $venue->status === 'published')) {
                    $venuesWithoutRevision++;
                }

                $activeBookings = Booking::query()
                    ->whereHas('court', fn ($q) => $q->where('venue_id', $venue->id))
                    ->whereIn('status', ['confirmed', 'paid', 'pending'])
                    ->count();
                $orgBookingsCount += $activeBookings;
            }

            if (! $hasApplication) {
                $summary['missing_application_evidence']++;
            }
            if (! $hasSubscription) {
                $summary['missing_platform_subscriptions']++;
            }
            $summary['venues_missing_publication_revisions'] += $venuesWithoutRevision;
            $summary['total_outstanding_bookings'] += $orgBookingsCount;

            $records[] = [
                'organization_id' => $org->id,
                'name' => $org->name,
                'owner_id' => $org->owner?->id,
                'has_approved_application' => $hasApplication ? 'YES' : 'NO',
                'has_active_subscription' => $hasSubscription ? 'YES' : 'NO',
                'venues_count' => $org->venues->count(),
                'venues_unverified_published' => $venuesWithoutRevision,
                'outstanding_future_bookings' => $orgBookingsCount,
            ];
        }

        $format = $this->option('format');

        if ($format === 'json') {
            $jsonOutput = json_encode([
                'audit_timestamp' => now()->toIso8601String(),
                'read_only' => true,
                'summary' => $summary,
                'records' => $records,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $this->line($jsonOutput);

            if ($this->option('output-file')) {
                file_put_contents($this->option('output-file'), $jsonOutput);
                $this->info("Saved report to: {$this->option('output-file')}");
            }
        } else {
            $this->table(
                ['Org ID', 'Name', 'Owner ID', 'Approved App', 'Active Sub', 'Venues', 'Unverified Published', 'Future Bookings'],
                $records
            );

            $this->newLine();
            $this->info('Reconciliation Summary:');
            $this->line("- Total Organizations: {$summary['total_organizations']}");
            $this->line("- Missing Approved Application: {$summary['missing_application_evidence']}");
            $this->line("- Missing Platform Subscription: {$summary['missing_platform_subscriptions']}");
            $this->line("- Venues Missing Approved Publication Revision: {$summary['venues_missing_publication_revisions']}");
            $this->line("- Outstanding Future Bookings to Protect: {$summary['total_outstanding_bookings']}");
        }

        return self::SUCCESS;
    }
}
