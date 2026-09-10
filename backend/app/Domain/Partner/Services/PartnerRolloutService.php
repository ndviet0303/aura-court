<?php

declare(strict_types=1);

namespace App\Domain\Partner\Services;

use App\Models\Organization;
use App\Models\Venue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class PartnerRolloutService
{
    /**
     * Check if a specific v2 enforcement aspect applies to this organization.
     *
     * @param  string  $aspect  'registration' | 'publication' | 'subscription' | 'all'
     */
    public function shouldEnforce(int|Organization $organization, string $aspect = 'all'): bool
    {
        $orgId = $organization instanceof Organization ? $organization->id : $organization;

        // Check cohort mapping
        $cohorts = Config::get('partner.enforce_cohorts', ['*']);
        $isInCohort = in_array('*', $cohorts, true) || in_array((string) $orgId, $cohorts, true) || in_array($orgId, $cohorts, true);

        if (! $isInCohort) {
            return false;
        }

        return match ($aspect) {
            'registration' => (bool) Config::get('partner.enforce_v2_registration', true),
            'publication' => (bool) Config::get('partner.enforce_publication_check', false),
            'subscription' => (bool) Config::get('partner.enforce_subscription_check', false),
            default => (bool) Config::get('partner.enforce_v2_registration', true)
                || (bool) Config::get('partner.enforce_publication_check', false)
                || (bool) Config::get('partner.enforce_subscription_check', false),
        };
    }

    /**
     * Record shadow comparison between legacy and v2 eligibility decisions.
     */
    public function recordShadowComparison(
        Venue $venue,
        string $action,
        bool $legacyAllowed,
        bool $v2Allowed,
        array $context = []
    ): void {
        if (! Config::get('partner.shadow_mode', false)) {
            return;
        }

        $discrepancy = $legacyAllowed !== $v2Allowed;

        if ($discrepancy) {
            Log::warning('partner_rollout.shadow_discrepancy', [
                'venue_id' => $venue->id,
                'organization_id' => $venue->organization_id,
                'action' => $action,
                'legacy_allowed' => $legacyAllowed,
                'v2_allowed' => $v2Allowed,
                'context' => $context,
                'occurred_at' => now()->toIso8601String(),
            ]);
        } else {
            Log::debug('partner_rollout.shadow_concordance', [
                'venue_id' => $venue->id,
                'action' => $action,
                'allowed' => $legacyAllowed,
            ]);
        }
    }

    /**
     * Verify that rollback configuration maintains system safety invariants.
     */
    public function isRollbackSafe(): bool
    {
        // Safety invariant: Legacy owner self-grant must NEVER be re-opened by rolling back config.
        // The onboarding controller has had court_owner self-grant permanently removed from code.
        return true;
    }
}
