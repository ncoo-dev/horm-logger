<?php

return [
    'enabled' => env('HORM_LOGGER_ENABLED', true),
    'database' => [
        'connection' => env('HORM_DB_CONNECTION', null),
        'table_name' => 'horm_entries',
    ],
    'model' => [
        'entry' => \NcooDev\HormLogger\Models\Entry::class,
        'keep_history_for_days' => 2,
    ],
    'endpoint' => [
        'enabled' => env('HORM_ENDPOINT_ENABLED', true),
        'secret' => env('HORM_ENDPOINT_SECRET', 'my-little-secret-with-horm'),
        'url' => env('HORM_ENDPOINT_URL', 'horm-logger-get-entries'),
    ],
    'excluded_incoming_urls' => [
        // Path patterns for incoming requests (middleware)
        'telescope/*',
        'horizon/*',
        'nova-api/*',
        '_ignition/*',
        'livewire/*',
        'horm-logger-get-entries',
        'health',
        'up',
    ],
    'excluded_outgoing_urls' => [
        // Full URL patterns for outgoing requests (listener)
        // 'https://api.stripe.com/*',
        // 'https://webhook.site/*',
        // 'https://analytics.google.com/*',
        // 'https://*.github.com/api/*',
    ],
    'obfuscate_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'api_key',
        'secret',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
    ],
];
