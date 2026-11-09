<?php

return [
    'low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 5),
    'reservation_ttl_minutes' => (int) env('INVENTORY_RESERVATION_TTL_MINUTES', 15),
];
