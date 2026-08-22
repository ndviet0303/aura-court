<?php

return [
    'currency' => env('FINANCE_CURRENCY', 'VND'),
    'commission_bps' => (int) env('PLATFORM_COMMISSION_BPS', 500),
    'provider_fee_bps' => (int) env('PAYMENT_PROVIDER_FEE_BPS', 0),
    'minimum_payout' => (int) env('MINIMUM_PAYOUT_AMOUNT', 500000),
    'settlement_delay_days' => (int) env('SETTLEMENT_DELAY_DAYS', 2),
];
