<?php

namespace NcooDev\HormLogger\Http\Controllers;

use Illuminate\Http\Request;
use NcooDev\HormLogger\Http\Ressources\EntryResource;

class EntryController
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date',
        ]);

        $model = config('horm.model.entry');

        $entriesCount = $model::query()
            ->where('created_at', '>=', $validated['start'])
            ->count();
        if ($entriesCount == 0) {
            return EntryResource::collection([]);
        }

        $comparableEntry = $model::query()
            ->offset(min($entriesCount - 1, config('horm.endpoint.max_entries')))
            ->where('created_at', '>=', $validated['start'])
            ->oldest()
            ->limit(1)
            ->first();

        return EntryResource::collection($model::query()
            ->where('created_at', '>=', $validated['start'])
            ->where('created_at', '<=', $comparableEntry->created_at)
            ->oldest()
            ->get()
        );
    }
}
