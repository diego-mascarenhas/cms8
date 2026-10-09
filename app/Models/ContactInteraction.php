<?php

namespace App\Models;

use App\Enums\ContactInteractionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContactInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'user_id',
        'relatable_type',
        'relatable_id',
        'type',
        'subject',
        'body',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'type' => ContactInteractionType::class,
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * WhatsApp-style export lines: [09/10/2026, 13:14:46] Name: message.
     *
     * @return list<array{at: string, author: string, text: string}>
     */
    public function chatLines(): array
    {
        $body = trim((string) $this->body);
        if ($body === '' || ! preg_match('/\[\d{1,2}\/\d{1,2}\/\d{2,4},/', $body))
        {
            return [];
        }

        preg_match_all(
            '/\[(\d{1,2}\/\d{1,2}\/\d{2,4},\s*\d{1,2}:\d{2}(?::\d{2})?)\]\s*([^:\r\n]+):\s*(.*?)(?=\s*\[\d{1,2}\/\d{1,2}\/\d{2,4},|\z)/su',
            $body,
            $matches,
            PREG_SET_ORDER,
        );

        $lines = [];
        foreach ($matches as $match)
        {
            $text = trim($match[3]);
            $author = trim($match[2]);
            if ($author === '' && $text === '')
            {
                continue;
            }

            $lines[] = [
                'at' => $match[1],
                'author' => $author,
                'text' => $text,
            ];
        }

        return $lines;
    }
}
