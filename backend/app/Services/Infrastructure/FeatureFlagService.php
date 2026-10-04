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
        if ($organizationId === null || in_array('*', $pilotOrganizations, true)) {
            return true;
        }
        return in_array((string) $organizationId, $pilotOrganizations, true);
    }

    public function assertEnabled(string $flag, ?int $organizationId = null): void
    {
        if (! $this->enabled($flag, $organizationId)) {
            abort(response()->json([
                'message' => 'This capability is not enabled for the organization.',
                'code' => 'CAPABILITY_DISABLED',
                'capability' => $flag,
            ], 404));
        }
    }

    public function effectiveCapabilities(?int $organizationId = null): array
    {
        $flags = array_keys(config('features.flags', []));
        $result = [];
        foreach ($flags as $flag) {
            $result[$flag] = $this->enabled($flag, $organizationId);
        }

        return $result;
    }
}
