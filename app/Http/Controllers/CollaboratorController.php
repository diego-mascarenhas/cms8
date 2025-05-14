<?php

namespace App\Http\Controllers;

use App\DataTables\CollaboratorDataTable;
use App\Models\Contact;
use App\Models\Category;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    public function index(CollaboratorDataTable $dataTable)
    {
        $categories = Category::all();
        $users = User::role('collaborator')->get();

        return $dataTable->render('collaborator.index', compact('categories', 'users'));
    }

    public function create()
    {
        $categories = Category::all();
        $enterprises = Enterprise::all();
        $users = User::role('collaborator')->get();

        return view('collaborator.form', compact('categories', 'enterprises', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'enterprise_id' => 'nullable|exists:enterprises,id',
            'responsible_id' => 'nullable|exists:users,id',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id'
        ]);

        $contact = Contact::create($validated);
        
        if (isset($validated['categories'])) {
            $contact->categories()->sync($validated['categories']);
        }

        return redirect()->route('collaborator.list')
            ->with('success', __('Collaborator created successfully.'));
    }

    public function show($id)
    {
        $collaborator = Contact::findOrFail($id);
        $collaborator->load(['enterprise', 'responsible', 'categories']);
        return view('collaborator.show', compact('collaborator'));
    }

    public function edit($id)
    {
        $collaborator = Contact::findOrFail($id);
        $categories = Category::all();
        $enterprises = Enterprise::all();
        $users = User::role('collaborator')->get();

        return view('collaborator.form', compact('collaborator', 'categories', 'enterprises', 'users'));
    }

    public function update(Request $request, $id)
    {
        $collaborator = Contact::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'enterprise_id' => 'nullable|exists:enterprises,id',
            'responsible_id' => 'nullable|exists:users,id',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id'
        ]);

        $collaborator->update($validated);
        
        if (isset($validated['categories'])) {
            $collaborator->categories()->sync($validated['categories']);
        }

        return redirect()->route('collaborator.list')
            ->with('success', __('Collaborator updated successfully.'));
    }

    public function destroy($id)
    {
        $collaborator = Contact::findOrFail($id);
        $collaborator->delete();
        return redirect()->route('collaborator.list')
            ->with('success', __('Collaborator deleted successfully.'));
    }
} 