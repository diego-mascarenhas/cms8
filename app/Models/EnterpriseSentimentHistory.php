<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnterpriseSentimentHistory extends Model
{
    use HasFactory;

    protected $fillable = ['enterprise_id', 'sentiment_id', 'notes'];

    public function enterprise()
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function sentiment()
    {
        return $this->belongsTo(EnterpriseSentiment::class);
    }
}