<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactSource;
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
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[+\-\d\s()]+$/'],
            'team_id' => 'required|exists:teams,id',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser válido',
            'phone.required' => 'El teléfono es obligatorio',
            'phone.regex' => 'El teléfono solo puede contener números, espacios y los símbolos + -',
            'team_id.required' => 'El equipo es obligatorio',
            'team_id.exists' => 'El equipo seleccionado no es válido',
        ]);

        try {
            $contact = Contact::create([
                'name' => $validated['name'],
                'team_id' => $validated['team_id'],
                'status_id' => 1,
                'creator_id' => auth()->id() ?? 1,
            ]);

            ContactSource::create([
                'contact_id' => $contact->id,
                'source_id' => 1,
                'value' => $validated['email'],
            ]);

            ContactSource::create([
                'contact_id' => $contact->id,
                'source_id' => 2,
                'value' => $validated['phone'],
            ]);

            $logMessage = sprintf(
                '[%s] Nuevo lead - Nombre: %s, Email: %s, Teléfono: %s',
                now()->format('Y-m-d H:i:s'),
                $validated['name'],
                $validated['email'],
                $validated['phone'],
            );

            Log::channel('leads')->info($logMessage);

            return view('lead.success');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'No se pudo procesar tu solicitud')
                ->withInput();
        }
    }
}
