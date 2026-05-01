<?php

namespace App\Enums;

enum MessageDeliverySendProfile
{
    /** Derivar de `campaign_id` en `MessageDelivery`. */
    case Auto;

    /** Secuencias / difusiones ligadas a `Campaign`; menor prioridad (más espaciado opcional). */
    case Campaign;

    /** Newsletter suelta, pruebas, reenvíos manuales; mayor prioridad de encolado. */
    case Message;
}
