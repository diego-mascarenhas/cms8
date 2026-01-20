<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactSource;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    public function create()
    {
        return view('lead.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => ['required_without:phone', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:20', 'regex:/^[+\-\d\s()]+$/'],
            'team_id' => 'required|exists:teams,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'surname.string' => 'El apellido debe ser texto válido',
            'email.required_without' => 'Debe proporcionar al menos un email o teléfono',
            'email.email' => 'El email debe ser válido',
            'phone.required_without' => 'Debe proporcionar al menos un teléfono o email',
            'phone.regex' => 'El teléfono solo puede contener números, espacios y los símbolos + -',
            'team_id.required' => 'El equipo es obligatorio',
            'team_id.exists' => 'El equipo seleccionado no es válido',
            'category_id.exists' => 'La categoría seleccionada no es válida',
            'category_ids.*.exists' => 'Una o más categorías seleccionadas no son válidas',
        ]);

        try
        {
            // Get the team to use its owner as creator
            $team = Team::findOrFail($validated['team_id']);

            // Clean phone number: remove spaces, dashes, parentheses, plus signs (only if phone is provided)
            $cleanPhone = null;
            if (! empty($validated['phone']))
            {
                $cleanPhone = preg_replace('/[^\d]/', '', $validated['phone']);
            }

            // Prepare additional data for BBO forms
            $additionalData = $request->has('data') ? $request->input('data') : null;

            $contact = Contact::create([
                'name' => $validated['name'],
                'surname' => $validated['surname'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $cleanPhone, // Use cleaned phone number or null
                'team_id' => $validated['team_id'],
                'status_id' => 1,
                'creator_id' => $team->user_id, // Owner of the team
                'data' => $additionalData, // Store additional BBO data
            ]);

            // Attach categories if provided
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
            // ContactSource::create([
            //	 'contact_id' => $contact->id,
            //	 'source_id' => 1,
            //	 'value' => $validated['email'],
            // ]);

            // ContactSource::create([
            //	 'contact_id' => $contact->id,
            //	 'source_id' => 2,
            //	 'value' => $validated['phone'],
            // ]);

            $logMessage = sprintf(
                '[%s] Nuevo lead - Nombre: %s%s, Email: %s, Teléfono: %s%s',
                now()->format('Y-m-d H:i:s'),
                $validated['name'],
                ! empty($validated['surname']) ? ' '.$validated['surname'] : '',
                $validated['email'] ?? 'no proporcionado',
                $cleanPhone ?? 'no proporcionado',
                ! empty($validated['phone']) ? ' (original: '.$validated['phone'].')' : '',
            );

            Log::channel('leads')->info($logMessage);

            return view('lead.success');
        } catch (\Exception $e)
        {
            Log::error('Error creating lead: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'No se pudo procesar tu solicitud')
                ->withInput();
        }
    }
}
