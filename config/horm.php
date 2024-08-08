<?php

return [
    'database' => [
        'connection' => 'sqlite',
        'table_name' => 'horm_entries',
    ],
    'model' => [
        'entry' => \NcooDev\HormLogger\Models\Entry::class,
    ],
    'horm_endpoint' => [
        'enabled' => true,
        'secret' => 'my-little-secret-with-horm',
        'url' => 'horm-logger-get-entries',
    ],
];
