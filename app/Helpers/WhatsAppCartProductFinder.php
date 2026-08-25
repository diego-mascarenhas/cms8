<?php

namespace App\Helpers;

use App\Models\Product;
use App\Support\WhatsAppProductRelevanceSearch;

/**
 * Resolves a WhatsApp cart needle (name, code, or id) by relevance.
 */
class WhatsAppCartProductFinder
{
    public static function find(int $teamId, string $needle): ?Product
    {
        return WhatsAppProductRelevanceSearch::find($teamId, $needle);
    }
}
