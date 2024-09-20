<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnterpriseSentiment extends Model
{
    public $timestamps = false;
    protected $fillable = ['name'];

    public function getEmojiAttribute()
    {
        return match($this->id) {
            1 => '😡',
            2 => '🙁',
            3 => '😐',
            4 => '🙂',
            5 => '🥳',
            default => '❓',
        };
    }

    public function histories()
    {
        return $this->hasMany(EnterpriseSentimentHistory::class, 'sentiment_id');
    }

    public static function getOptions()
    {
        return self::all()->map(function ($sentiment) {
            return [
                'id' => $sentiment->id,
                'name' => $sentiment->name . ' ' . $sentiment->emoji,
            ];
        });
    }
}