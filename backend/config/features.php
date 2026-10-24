<?php

return [
    'pilot_organizations' => array_values(array_filter(array_map('trim', explode(',', (string) env('PILOT_ORGANIZATION_IDS', ''))))),
    'flags' => [
        'organization_tenancy_enforced' => env('FEATURE_ORGANIZATION_TENANCY', true),
        'marketplace_payouts' => env('FEATURE_MARKETPLACE_PAYOUTS', false),
        'marketplace_split_payments' => env('FEATURE_MARKETPLACE_SPLIT_PAYMENTS', env('FEATURE_MARKETPLACE_SPLIT', false)),
        'marketplace_slot_resale' => env('FEATURE_MARKETPLACE_SLOT_RESALE', env('FEATURE_MARKETPLACE_RESALE', false)),
        'tenant_pos_and_wallet' => env('FEATURE_TENANT_POS_WALLET', false),
        'direct_owner_payments' => env('FEATURE_DIRECT_OWNER_PAYMENTS', false),
    ],
    'pilot_only' => [
        'marketplace_payouts',
        'marketplace_split_payments',
        'marketplace_slot_resale',
        'tenant_pos_and_wallet',
        'direct_owner_payments',
    ],
];
