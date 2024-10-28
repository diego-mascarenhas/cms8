<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactSource extends Model
{
    public $timestamps = false;

    use HasFactory;

    protected $fillable = ['contact_id', 'source_id', 'value'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }
}
