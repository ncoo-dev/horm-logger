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

        $entries = $model::query()
            ->where('created_at', '>=', $validated['start'])
            ->oldest()
            ->limit(config('horm.endpoint.max_entries', 100))
            ->get();

        return EntryResource::collection($entries);
    }
}
