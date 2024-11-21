<?php

namespace App\Http\Controllers;

use App\Mail\LeadNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mail;

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
            'phone' => 'required|string|max:20',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser válido',
            'phone.required' => 'El teléfono es obligatorio',
        ]);

        try {
            // Guardar en el archivo de log
            $logMessage = sprintf(
                "[%s] Nuevo lead - Nombre: %s, Email: %s, Teléfono: %s",
                now()->format('Y-m-d H:i:s'),
                $validated['name'],
                $validated['email'],
                $validated['phone']
            );
            
            Log::channel('leads')->info($logMessage);

            // Mail::to('hola@humano.app')->send(new LeadNotification($validated));
            
            return view('lead.success');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'No se pudo procesar tu solicitud')
                ->withInput();
        }
    }
}