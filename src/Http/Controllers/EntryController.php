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

        return EntryResource::collection(Entry::query()
            ->where('created_at', '>=', $validated['start'])
            ->oldest()
            ->get()
        );
    }
}
