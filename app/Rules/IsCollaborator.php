<?php

namespace App\Rules;

use App\Models\Contact;
use Illuminate\Contracts\Validation\Rule;

class IsCollaborator implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Verificar si el contacto está asociado a un usuario con rol de colaborador
        return Contact::where('id', $value)
            ->whereHas('user', function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->where('name', 'collaborator');
                });
            })->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'El contacto seleccionado debe tener el rol de colaborador.';
    }
}
