<?php

return [
    'database' => [
        'connection' => 'sqlite',
        'table_name' => 'horm_entries',
    ],
    'model' => [
        'entry' => \NcooDev\HormLogger\Models\Entry::class,
    ],
];
