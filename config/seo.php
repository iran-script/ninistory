<?php

return [
    'indexing_enabled' => (bool) env('SEO_INDEXING_ENABLED', false),
    'production_url' => rtrim((string) env('SEO_PRODUCTION_URL', 'https://kidoki.ir'), '/'),
    'brand_name' => 'Kidoki',
    'brand_name_fa' => 'کیدوکی',
    'instagram' => (string) env('SEO_INSTAGRAM', 'kidoki_shop'),
];
