<?php

return [
    'enabled' => (bool) env('DEMO_CATALOG_ENABLED', false),
    'prefix' => 'demo-',
    'public_preview' => (bool) env('DEMO_CATALOG_PUBLIC_PREVIEW', false),
];
