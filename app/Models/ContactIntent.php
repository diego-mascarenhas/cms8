<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactIntent extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'name'];

    public function getEmojiAttribute(): string
    {
        return match ($this->key)
        {
            'buy' => '🛒',
            'update' => '🔄',
            'work' => '🔧',
            'cancel' => '🚪',
            'other' => '💬',
            default => '❔',
        };
    }

    public function histories()
    {
        return $this->hasMany(ContactSentimentHistory::class, 'intent_id');
    }

    public static function getOptions(): array
    {
        return self::query()->orderBy('id')->get()->map(function (self $intent)
        {
            return [
                'id' => $intent->id,
                'name' => $intent->name.' '.$intent->emoji,
            ];
        })->values()->all();
    }
}
