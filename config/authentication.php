<?php

return [
    'local_auth_enabled' => env('LOCAL_AUTH_ENABLED', env('APP_ENV') !== 'production'),

    'azure_allowed_domain' => strtolower((string) env('AZURE_ALLOWED_DOMAIN', 'cityofimus.gov.ph')),

    'oauth_timeout' => (float) env('OAUTH_HTTP_TIMEOUT', 15),
    'oauth_connect_timeout' => (float) env('OAUTH_HTTP_CONNECT_TIMEOUT', 5),

    'oauth_providers' => [
        'google' => [
            'label' => 'Google',
            'allow_any_verified_email' => true,
        ],
        'azure' => [
            'label' => 'Microsoft',
            'allowed_domain' => strtolower((string) env('AZURE_ALLOWED_DOMAIN', 'cityofimus.gov.ph')),
        ],
    ],
];
