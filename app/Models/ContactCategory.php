<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContactCategory extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'contact_category';

    // Puedes agregar aquí lógica personalizada si lo necesitas
}
