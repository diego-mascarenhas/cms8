<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class TeamContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $team = $request->attributes->get('team');

        $contacts = Contact::where('team_id', $team->id)
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $contacts,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $team = $request->attributes->get('team');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $contact = Contact::create([
            'team_id' => $team->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'success' => true,
            'data' => $contact,
            'message' => 'Contact created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $team = $request->attributes->get('team');

        $contact = Contact::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $contact,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $team = $request->attributes->get('team');

        $contact = Contact::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $contact->update($request->only(['name', 'email', 'phone']));

        return response()->json([
            'success' => true,
            'data' => $contact,
            'message' => 'Contact updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $team = $request->attributes->get('team');

        $contact = Contact::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully',
        ]);
    }
}
