<?php

namespace Tests\Unit;

use App\Helpers\AssistantSparePartNote;
use PHPUnit\Framework\TestCase;

class AssistantSparePartNoteTest extends TestCase
{
    public function test_inbox_body_joins_brand_description_and_code(): void
    {
        $this->assertSame(
            "Pieza detectada: Mann · Filtro De Aceite · W 719/45\nCatálogo: Filtro aceite Gol",
            AssistantSparePartNote::inboxBody([
                'brand' => 'Mann',
                'description' => 'Filtro De Aceite',
                'code' => 'W 719/45',
                'catalog_name' => 'Filtro aceite Gol',
            ]),
        );
    }

    public function test_customer_reply_does_not_mention_the_part(): void
    {
        $reply = AssistantSparePartNote::customerReply();

        $this->assertStringContainsString('vendedores', $reply);
        $this->assertStringNotContainsString('Pieza detectada', $reply);
        $this->assertStringNotContainsString('W 719', $reply);
    }

    public function test_extracts_part_from_ingestion_extracted_data(): void
    {
        $part = AssistantSparePartNote::extractPartFromIngestions([
            (object) [
                'extracted_data' => [
                    'part' => [
                        'description' => 'Filtro De Aceite',
                        'code' => 'HU718',
                    ],
                ],
            ],
        ]);

        $this->assertSame('HU718', $part['code'] ?? null);
        $this->assertSame('Filtro De Aceite', $part['description'] ?? null);
    }

    public function test_unidentified_inbox_body_keeps_ocr_text_internal(): void
    {
        $this->assertSame(
            "Foto de pieza recibida.\nOCR: MANN FILTER W 719/45",
            AssistantSparePartNote::unidentifiedInboxBody("MANN FILTER\nW 719/45"),
        );
        $this->assertSame(
            'Foto de pieza recibida. El OCR no pudo leer marca ni referencia.',
            AssistantSparePartNote::unidentifiedInboxBody(null),
        );
    }

    public function test_unclassified_images_are_treated_as_spare_part_photos(): void
    {
        $this->assertTrue(AssistantSparePartNote::ingestionsAreUnclassifiedImages([]));
        $this->assertTrue(AssistantSparePartNote::ingestionsAreUnclassifiedImages([
            (object) ['document_type' => 'unknown', 'mime_type' => 'image/jpeg'],
        ]));
        $this->assertFalse(AssistantSparePartNote::ingestionsAreUnclassifiedImages([
            (object) ['document_type' => 'invoice', 'mime_type' => 'application/pdf'],
        ]));
    }
}
