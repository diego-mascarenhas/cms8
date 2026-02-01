<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class TeamContactController extends Controller
{
    /** API-stipulated fields (stored in contact columns). Extra keys go to contact.data (JSON). */
    private const STIPULATED_FIELDS = ['name', 'surname', 'email', 'phone', 'category_id', 'category_ids'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $team = $request->attributes->get('team');

        $contacts = Contact::where('team_id', $team->id)
            ->with(['user.roles', 'user.teams'])
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => ['required_without:phone', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:20', 'regex:/^[+\-\d\s()]+$/'],
            'category_id' => 'nullable|integer|exists:categories,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'email.required_without' => 'Debe proporcionar al menos un email o teléfono',
            'email.email' => 'El email debe ser válido',
            'phone.required_without' => 'Debe proporcionar al menos un teléfono o email',
            'phone.regex' => 'El teléfono solo puede contener números, espacios y los símbolos + -',
            'category_id.exists' => 'La categoría seleccionada no es válida',
            'category_ids.*.exists' => 'Una o más categorías seleccionadas no son válidas',
        ]);

        // Normalize phone to digits only and store as integer
        $phone = null;
        if (! empty($validated['phone']))
        {
            $digits = preg_replace('/[^\d]/', '', $validated['phone']);
            $phone = $digits !== '' ? (int) $digits : null;
        }

        $contact = Contact::create([
            'team_id' => $team->id,
            'name' => $validated['name'],
            'surname' => $validated['surname'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $phone,
            'status_id' => 1,
            'creator_id' => $team->user_id,  // Owner of the team
        ]);

        // Attach categories only if provided in request
        $categoryIds = [];
        if (! empty($validated['category_id']))
        {
            $categoryIds[] = $validated['category_id'];
        }
        if (! empty($validated['category_ids']))
        {
            $categoryIds = array_merge($categoryIds, $validated['category_ids']);
        }
        if (! empty($categoryIds))
        {
            $contact->categories()->sync(array_unique($categoryIds));
        }

        // Store extra request fields in contact.data (JSON)
        $extra = $request->except(self::STIPULATED_FIELDS);
        if (! empty($extra))
        {
            $data = (array) ($contact->data ?? (object) []);
            $contact->data = array_merge($data, $extra);
            $contact->save();
        }

        // Reload with categories
        $contact->load('categories');

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
            ->with(['user.roles', 'user.teams'])
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
            ->with(['user.roles', 'user.teams'])
            ->firstOrFail();

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[+\-\d\s()]+$/'],
        ]);

        $payload = $request->only(['name', 'email', 'surname']);
        if ($request->has('phone'))
        {
            $digits = preg_replace('/[^\d]/', '', $request->input('phone'));
            $payload['phone'] = $digits !== '' ? (int) $digits : null;
        }
        $contact->update($payload);

        // Merge extra request fields into contact.data (JSON)
        $extra = $request->except(self::STIPULATED_FIELDS);
        if (! empty($extra))
        {
            $data = (array) ($contact->data ?? (object) []);
            $contact->data = array_merge($data, $extra);
            $contact->save();
        }

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
            ->with(['user.roles', 'user.teams'])
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully',
        ]);
    }
}
