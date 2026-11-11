<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    // READ — show all notes
    public function index()
    {
        $notes = Note::where('user_id', Auth::id())->latest()->get();

        return view('notes.index', ['notes' => $notes]);
    }

    // CREATE — show the form
    public function create()
    {
        return view('notes.create');
    }

    // CREATE — validate and save
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:100',
            'body' => 'required',

        ]);

        $note = new Note($validated);
        $note->user_id = Auth::id();
        $note->save();

        return redirect()->route('notes.index')->with('success', 'Note added.');
    }

    // Like every spot and photo, another account's note is answered with a 404, so it can't be confirmed to exist
    private function authorizedOwner(Note $note): void
    {
        abort_if($note->user_id != Auth::id(), 404);
    }

    // UPDATE — show the form, pre-filled
    public function edit(Note $note)
    {
        $this->authorizedOwner($note);

        return view('notes.edit', ['note' => $note]);
    }

    // UPDATE — validate and save changes
    public function update(Request $request, Note $note)
    {
        $this->authorizedOwner($note);

        $validated = $request->validate([
            'title' => 'required|max:100',
            'body' => 'required',
        ]);

        $note->update($validated);

        return redirect()->route('notes.index')->with('success', 'Note updated.');
    }

    // DELETE — remove the note
    public function destroy(Note $note)
    {
        $this->authorizedOwner($note);

        $note->delete();

        return redirect()->route('notes.index')->with('success', 'Note deleted.');
    }
}
