<?php

namespace NcooDev\HormLogger\Http\Controllers;

use Illuminate\Http\Request;
use NcooDev\HormLogger\Http\Ressources\EntryResource;
use NcooDev\HormLogger\Models\Entry;

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

        $maxEntries = 1000;
        $limitCount = min($entriesCount, $maxEntries);
        
        return EntryResource::collection($model::query()
            ->where('created_at', '>=', $validated['start'])
            ->oldest()
            ->limit($limitCount)
            ->get()
        );
    }
}
