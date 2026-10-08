<?php

namespace App\Services\Prospecting;

use RuntimeException;

class InsufficientProspectCredits extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No tienes suficientes créditos de prospectos. Contrata un plan o compra más créditos en Suscripciones.');
    }
}
