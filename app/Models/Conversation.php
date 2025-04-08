<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_sid',
        'channel',
        'from',
        'to',
        'body',
        'status',
        'direction',
        'media',
        'metadata'
    ];

    protected $casts = [
        'media' => 'array',
        'metadata' => 'array',
    ];
    
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
        return $query->where('from', 'like', '%' . $phoneNumber . '%')
                     ->orWhere('to', 'like', '%' . $phoneNumber . '%');
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
}