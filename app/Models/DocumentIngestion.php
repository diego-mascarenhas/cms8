<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentIngestion extends Model
{
    protected $fillable = [
        'team_id',
        'source_id',
        'conversation_id',
        'source_reference',
        'file_name',
        'file_url',
        'mime_type',
        'file_size',
        'file_hash',
        'document_type',
        'classification_status',
        'classification_confidence',
        'ocr_text',
        'extracted_data',
        'classification_meta',
        'entity_type',
        'entity_id',
        'processing_error',
        'processed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'classification_confidence' => 'decimal:2',
        'extracted_data' => 'array',
        'classification_meta' => 'array',
        'processed_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
