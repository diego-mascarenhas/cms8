<?php

namespace App\Http\Controllers;

use App\DataTables\CollaboratorDataTable;
use App\Models\Contact;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    public function index(CollaboratorDataTable $dataTable)
    {
        return $dataTable->render('collaborator.index');
    }

    public function create()
    {
        return view('collaborator.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255'
        ]);

        // Add creator_id and team_id automatically
        $validated['creator_id'] = auth()->user()->id;
        $validated['team_id'] = auth()->user()->currentTeam->id;

        $contact = Contact::create($validated);

        return redirect()->route('collaborator.show', $contact->id)
            ->with('success', __('Collaborator created successfully.'));
    }

    public function show($id)
    {
        $collaborator = Contact::findOrFail($id);
        return view('collaborator.show', compact('collaborator'));
    }

    public function edit($id)
    {
        $collaborator = Contact::findOrFail($id);
        return view('collaborator.form', compact('collaborator'));
    }

    public function update(Request $request, $id)
    {
        $collaborator = Contact::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255'
        ]);

        $collaborator->update($validated);

        return redirect()->route('collaborator.show', $id)
            ->with('success', __('Collaborator updated successfully.'));
    }

    public function destroy($id)
    {
        $collaborator = Contact::findOrFail($id);
        $collaborator->delete();
        return redirect()->route('collaborator-list')
            ->with('success', __('Collaborator deleted successfully.'));
    }
} 