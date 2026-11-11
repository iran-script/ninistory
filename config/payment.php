<?php

return [
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),
    'mock_enabled' => (bool) env('PAYMENT_MOCK_ENABLED', false),
    'attempt_ttl_minutes' => (int) env('PAYMENT_ATTEMPT_TTL_MINUTES', 15),
    'reconciliation_enabled' => (bool) env('PAYMENT_RECONCILIATION_ENABLED', true),
];
