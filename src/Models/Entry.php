<?php

namespace NcooDev\HormLogger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use NcooDev\HormLogger\Database\Factories\EntryFactory;
use NcooDev\HormLogger\Enums\Direction;
use NcooDev\HormLogger\Enums\EntryType;
use NcooDev\HormLogger\Enums\Method;
use NcooDev\HormLogger\Exceptions\InvalidConfiguration;

class Entry extends Model
{
    use HasFactory;
    use HasUuids;
    use MassPrunable;

    protected $fillable = [
        'direction',
        'type',
        'request',
        'response',
    ];

    protected $casts = [
        'type' => EntryType::class,
        'direction' => Direction::class,
        'request' => 'array',
        'response' => 'array',
    ];

    public function getConnectionName(): string
    {
        return $this->connection ?:
            config('horm.database.connection') ?:
                config('database.default');
    }

    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }

    public function getTable(): mixed
    {
        $this->table = config('horm.database.table_name');
        throw_if(empty($this->table), InvalidConfiguration::tableIsEmpty());

        return $this->table;
    }

    public function prunable(): Builder
    {
        $days = config('horm.model.keep_history_for_days') ?? 2;

        return static::where('created_at', '<=', now()->subDays($days));
    }

    public function freshTimestamp()
    {
        return now()->setTimezone('UTC');
    }
}
