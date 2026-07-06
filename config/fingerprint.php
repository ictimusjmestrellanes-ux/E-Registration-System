<?php

return [
    'local_bridge_url' => rtrim((string) env('FINGERPRINT_LOCAL_BRIDGE_URL', 'http://127.0.0.1:38654'), '/'),
    'bridge_launch_uri' => env('FINGERPRINT_BRIDGE_LAUNCH_URI', 'ereg-fingerprint://start'),
    'matcher_url' => rtrim((string) env('FINGERPRINT_MATCHER_URL', env('FINGERPRINT_LOCAL_BRIDGE_URL', 'http://127.0.0.1:38654')), '/'),
    'matcher_match_path' => env('FINGERPRINT_MATCHER_MATCH_PATH', '/api/match'),
    'matcher_health_path' => env('FINGERPRINT_MATCHER_HEALTH_PATH', '/api/health'),
    'matcher_token' => env('FINGERPRINT_MATCHER_TOKEN'),
    'bridge_allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FINGERPRINT_BRIDGE_ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1'))
    ))),
    'auto_start' => filter_var(env('FINGERPRINT_BRIDGE_AUTO_START', true), FILTER_VALIDATE_BOOLEAN),
];
