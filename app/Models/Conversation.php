<?php

namespace App\Models;

use App\Services\WhatsApp\WhatsAppChatArchiveService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Conversation extends Model
{
    use HasFactory;

    /** Cache key for inbound unread count used by navbar badge; invalidate when inbound/received or when marked read. */
    public const CACHE_KEY_INBOUND_UNREAD = 'inbound_received_count';

    protected $fillable = [
        'message_sid',
        'channel',
        'from',
        'to',
        'body',
        'status',
        'direction',
        'media',
        'metadata',
        'user_id',
    ];

    protected $casts = [
        'media' => 'array',
        'metadata' => 'array',
    ];

    public function isTranscribedAudio(): bool
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        if (filter_var($metadata['TranscribedAudio'] ?? $metadata['transcribed_audio'] ?? false, FILTER_VALIDATE_BOOLEAN))
        {
            return true;
        }

        if (preg_match('/^\s*\[audio\]\s*:?\s+\S/iu', (string) $this->body) === 1)
        {
            return true;
        }

        $media = is_array($this->media) ? $this->media : [];
        foreach ($media as $item)
        {
            $type = is_array($item) ? (string) ($item['content_type'] ?? '') : '';
            if (str_starts_with($type, 'audio/'))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a message has been delivered
     */
    public function isDelivered()
    {
        return in_array($this->status, ['delivered', 'read']);
    }

    /**
     * Check if a message has been read
     */
    public function isRead()
    {
        return $this->status === 'read';
    }

    /**
     * Check if a message has failed
     */
    public function hasFailed()
    {
        return in_array($this->status, ['failed', 'undelivered']);
    }

    protected static function booted(): void
    {
        static::created(function (Conversation $conversation)
        {
            if ($conversation->direction === 'inbound' && $conversation->status === 'received')
            {
                Cache::forget(self::CACHE_KEY_INBOUND_UNREAD);
            }

            if ($conversation->direction === 'inbound')
            {
                app(WhatsAppChatArchiveService::class)->unarchiveIncoming($conversation);
            }
        });

        static::updated(function (Conversation $conversation)
        {
            if ($conversation->isDirty('status') && $conversation->status === 'read')
            {
                Cache::forget(self::CACHE_KEY_INBOUND_UNREAD);
            }
        });
    }

    /**
     * Scope a query to only include WhatsApp conversations.
     */
    public function scopeWhatsapp($query)
    {
        return $query->where('channel', 'whatsapp');
    }

    /**
     * Scope a query to only include SMS conversations.
     */
    public function scopeSms($query)
    {
        return $query->where('channel', 'sms');
    }

    /**
     * Scope a query to only include Email conversations.
     */
    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    /**
     * Get conversations by phone number (from or to)
     */
    public function scopeByPhone($query, $phoneNumber)
    {
        return $query->where('from', 'like', '%'.$phoneNumber.'%')
            ->orWhere('to', 'like', '%'.$phoneNumber.'%');
    }

    /**
     * Get inbound conversations
     */
    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * Get outbound conversations
     */
    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    /**
     * Get the user associated with the conversation based on the from phone number
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'from', 'phone');
    }
}
