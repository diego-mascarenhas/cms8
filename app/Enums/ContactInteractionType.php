<?php

namespace App\Enums;

enum ContactInteractionType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Note = 'note';
    case WhatsApp = 'whatsapp';
    case CartAbandoned = 'cart_abandoned';
    case OrderPaid = 'order_paid';
    case Other = 'other';
}
