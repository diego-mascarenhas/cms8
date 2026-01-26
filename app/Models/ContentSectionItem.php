<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentSectionItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'content_section_items';

    protected $fillable = [
        'content_id',
        'section_key',
        'section_label',
        'content',
        'order',
        'is_active',
        'data',
    ];

    protected $casts = [
        'content' => 'array',
        'data' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the content that owns this section item.
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * Get translatable content value for current locale
     */
    public function getTranslatableContent(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $value = $this->content;

        if (is_array($value))
        {
            return $value[$locale] ?? $value['es'] ?? $value[array_key_first($value)] ?? null;
        }

        return $value;
    }

    /**
     * Set translatable content value for current locale
     */
    public function setTranslatableContent(string $value, ?string $locale = null): void
    {
        $locale = $locale ?? app()->getLocale();
        $current = $this->content ?? [];

        if (! is_array($current))
        {
            $current = [];
        }

        $current[$locale] = $value;
        $this->content = $current;
    }
}
