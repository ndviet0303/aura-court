<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Partner Registration V2 Enforcement
    |--------------------------------------------------------------------------
    |
    | When enabled, partner application review is strictly required before
    | an organization can be provisioned or granted partner capabilities.
    |
    */
    'enforce_v2_registration' => (bool) env('PARTNER_ENFORCE_V2_REGISTRATION', true),

    /*
    |--------------------------------------------------------------------------
    | Venue Publication Lifecycle Enforcement
    |--------------------------------------------------------------------------
    |
    | When enabled, venues must have an approved publication revision before
    | being exposed to public booking catalogs or accepting customer bookings.
    |
    */
    'enforce_publication_check' => (bool) env('PARTNER_ENFORCE_PUBLICATION_CHECK', false),

    /*
    |--------------------------------------------------------------------------
    | Enforced Organization Cohorts
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of organization IDs to enforce strict rules on,
    | or '*' to enforce on all organizations when flags are active.
    |
    */
    'enforce_cohorts' => array_filter(array_map('trim', explode(',', (string) env('PARTNER_ENFORCE_COHORTS', '*')))),
];
