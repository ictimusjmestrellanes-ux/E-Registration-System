<?php

return [
    'local_auth_enabled' => env('LOCAL_AUTH_ENABLED', env('APP_ENV') !== 'production'),

    'azure_allowed_domain' => strtolower((string) env('AZURE_ALLOWED_DOMAIN', 'cityofimus.gov.ph')),

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
