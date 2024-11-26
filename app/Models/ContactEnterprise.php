<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContactEnterprise extends Pivot
{
    protected $table = 'contact_enterprise';

    // Si necesitas definir relaciones adicionales, puedes hacerlo aquí
    public function contact() {
        return $this->belongsTo(Contact::class);
    }

    public function enterprise() {
        return $this->belongsTo(Enterprise::class);
    }
}