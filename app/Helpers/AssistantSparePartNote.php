<?php

namespace App\Helpers;

/**
 * Inbox-only note when OCR identifies a spare part from a customer photo.
 * Never sent to the WhatsApp customer.
 */
class AssistantSparePartNote
{
    /**
     * @param  array{description?: string, code?: string, brand?: string, oem?: string, catalog_name?: string}  $part
     */
    public static function inboxBody(array $part): string
    {
        $description = trim((string) ($part['description'] ?? ''));
        $code = trim((string) ($part['code'] ?? ''));
        $brand = trim((string) ($part['brand'] ?? ''));
        $oem = trim((string) ($part['oem'] ?? ''));
        $catalog = trim((string) ($part['catalog_name'] ?? ''));

        $bits = array_values(array_filter([$brand, $description, $code !== '' ? $code : $oem]));
        if ($bits === [])
        {
            return '';
        }

        $lines = ['Pieza detectada: '.implode(' · ', $bits)];
        if ($catalog !== '')
        {
            $lines[] = 'Catálogo: '.$catalog;
        }

        return implode("\n", $lines);
    }

    public static function customerReply(): string
    {
        return 'Recibí la foto. En breve te va a contactar uno de los vendedores para ayudarte con eso.';
    }

    public static function unidentifiedInboxBody(?string $ocrText = null): string
    {
        $snippet = trim((string) preg_replace('/\s+/u', ' ', (string) $ocrText));
        if ($snippet === '')
        {
            return 'Foto de pieza recibida. El OCR no pudo leer marca ni referencia.';
        }

        if (mb_strlen($snippet) > 240)
        {
            $snippet = mb_substr($snippet, 0, 237).'...';
        }

        return "Foto de pieza recibida.\nOCR: ".$snippet;
    }

    /**
     * @param  array<int, mixed>  $ingestions
     */
    public static function ocrTextFromIngestions(array $ingestions): ?string
    {
        foreach ($ingestions as $ingestion)
        {
            $text = '';
            if (is_object($ingestion))
            {
                $text = trim((string) ($ingestion->ocr_text ?? ''));
            } elseif (is_array($ingestion))
            {
                $text = trim((string) ($ingestion['ocr_text'] ?? ''));
            }
            if ($text !== '')
            {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $ingestions
     */
    public static function ingestionsAreUnclassifiedImages(array $ingestions): bool
    {
        if ($ingestions === [])
        {
            return true;
        }

        foreach ($ingestions as $ingestion)
        {
            $type = 'unknown';
            $mime = '';
            if (is_object($ingestion))
            {
                $type = (string) ($ingestion->document_type ?? 'unknown');
                $mime = strtolower((string) ($ingestion->mime_type ?? ''));
            } elseif (is_array($ingestion))
            {
                $type = (string) ($ingestion['document_type'] ?? 'unknown');
                $mime = strtolower((string) ($ingestion['mime_type'] ?? ''));
            }

            if ($type === 'unknown' && ($mime === '' || str_starts_with($mime, 'image/')))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $ingestions
     * @return array{description?: string, code?: string, brand?: string, oem?: string}|null
     */
    public static function extractPartFromIngestions(array $ingestions): ?array
    {
        foreach ($ingestions as $ingestion)
        {
            $extracted = [];
            if (is_object($ingestion) && is_array($ingestion->extracted_data ?? null))
            {
                $extracted = $ingestion->extracted_data;
            } elseif (is_array($ingestion) && is_array($ingestion['extracted_data'] ?? null))
            {
                $extracted = $ingestion['extracted_data'];
            }

            $part = is_array($extracted['part'] ?? null) ? $extracted['part'] : [];
            if (self::inboxBody($part) !== '')
            {
                return $part;
            }
        }

        return null;
    }
}
