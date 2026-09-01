<?php

namespace App\Services\Infrastructure;

class FeatureFlagService
{
    public function enabled(string $flag, ?int $organizationId = null): bool
    {
        $configured = filter_var(config("features.flags.{$flag}", false), FILTER_VALIDATE_BOOLEAN);
        if (! $configured) {
            return false;
        }
        if (! in_array($flag, config('features.pilot_only', []), true)) {
            return true;
        }
        $pilotOrganizations = config('features.pilot_organizations', []);
        if ($organizationId === null) {
            return true;
        }
        return in_array((string) $organizationId, $pilotOrganizations, true);
    }

    public function assertEnabled(string $flag, ?int $organizationId = null): void
    {
        abort_unless($this->enabled($flag, $organizationId), 404, 'This capability is not enabled for the organization.');
    }

}
