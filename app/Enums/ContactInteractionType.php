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

    public function label(): string
    {
        return match ($this)
        {
            self::Call => __('contact_interaction_type.call'),
            self::Email => __('contact_interaction_type.email'),
            self::Meeting => __('contact_interaction_type.meeting'),
            self::Note => __('contact_interaction_type.note'),
            self::WhatsApp => __('contact_interaction_type.whatsapp'),
            self::CartAbandoned => __('contact_interaction_type.cart_abandoned'),
            self::OrderPaid => __('contact_interaction_type.order_paid'),
            self::Other => __('contact_interaction_type.other'),
        };
    }
}
