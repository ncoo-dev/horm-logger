<?php

return [
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
];
