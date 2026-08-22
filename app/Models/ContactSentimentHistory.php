<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactSentimentHistory extends Model
{
    use HasFactory;

    protected $fillable = ['contact_id', 'sentiment_id', 'intent_id', 'notes'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function sentiment()
    {
        return $this->belongsTo(ContactSentiment::class);
    }

    public function intent()
    {
        return $this->belongsTo(ContactIntent::class);
    }
}
