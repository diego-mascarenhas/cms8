<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContactEnterprise extends Pivot
{
    public const ROLES = ['Administrador', 'Usuario', 'Invitado'];

    protected $table = 'contact_enterprise';

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class);
    }
}
