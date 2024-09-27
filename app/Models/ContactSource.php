<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactSource extends Model
{
    use HasFactory;
    use SoftDeletes;

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