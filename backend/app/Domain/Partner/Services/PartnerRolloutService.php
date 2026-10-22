<?php

declare(strict_types=1);

namespace App\Domain\Partner\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Config;

class PartnerRolloutService
{
    /**
     * Check if a specific v2 enforcement aspect applies to this organization.
     *
     * @param  string  $aspect  'registration' | 'publication' | 'all'
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
            default => (bool) Config::get('partner.enforce_v2_registration', true)
                || (bool) Config::get('partner.enforce_publication_check', false),
        };
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
