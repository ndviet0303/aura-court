<?php

return [
    'channel' => env('OBSERVABILITY_LOG_CHANNEL', 'structured'),
    'queue_lag_alert' => (int) env('QUEUE_LAG_ALERT_SECONDS', 30),
    'error_rate_alert' => (float) env('ERROR_RATE_ALERT_PERCENT', 5),
    'webhook_failure_alert' => (int) env('WEBHOOK_FAILURE_ALERT_COUNT', 5),
    'payout_mismatch_alert' => (int) env('PAYOUT_MISMATCH_ALERT_AMOUNT', 100000),
    'lock_contention_alert_ms' => (int) env('LOCK_CONTENTION_ALERT_MS', 500),
];
