<?php

namespace NcooDev\HormLogger\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;

class Entry extends Model
{
    protected $guarded = [];

    use HasUuids;

    protected $casts = [
        'type' => EntryType::class,
        'body' => 'array',
        'direction' => Direction::class,
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = config('horm.database.table_name');
        parent::__construct($attributes);
    }
}
