<?php

namespace App\Http\Controllers;

use App\Models\PhotoRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PhotoNoteController extends Controller
{
    // UPDATE — the resident's own note on a photo; only what they type, or edit, and then save is stored
    public function __invoke(Request $request, PhotoRecord $record)
    {
        Gate::authorize('update', $record);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']], ['note.max' => 'Keep the note under 1,000 characters.']);

        $record->update(['note' => $data['note'] ?? null]);

        return back()->with('success', 'Note saved.');
    }
}
