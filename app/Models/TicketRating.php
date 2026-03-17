<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'tiempo_respuesta',
        'atencion',
        'solucion',
        'comentarios',
    ];

    protected $casts = [
        'tiempo_respuesta' => 'integer',
        'atencion' => 'integer',
        'solucion' => 'integer',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getPromedioAttribute(): float
    {
        return round(($this->tiempo_respuesta + $this->atencion + $this->solucion) / 3, 1);
    }

    /**
     * @return array<int, bool>
     */
    public static function getStarsArray(int $rating): array
    {
        $stars = [];
        for ($i = 1; $i <= 5; $i++)
        {
            $stars[] = $i <= $rating;
        }

        return $stars;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getValidationRules(): array
    {
        return [
            'tiempo_respuesta' => 'required|integer|between:1,5',
            'atencion' => 'required|integer|between:1,5',
            'solucion' => 'required|integer|between:1,5',
            'comentarios' => 'nullable|string|max:1000',
        ];
    }

    public static function getRatingForTicket(int $ticketId, int $userId): ?self
    {
        return self::where('ticket_id', $ticketId)
            ->where('user_id', $userId)
            ->first();
    }
}
