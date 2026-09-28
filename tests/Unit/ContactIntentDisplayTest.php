<?php

namespace Tests\Unit;

use App\Models\ContactIntent;
use Tests\TestCase;

class ContactIntentDisplayTest extends TestCase
{
    public function test_spanish_locale_shows_intent_labels(): void
    {
        app()->setLocale('es_ES');

        $intent = new ContactIntent;
        $intent->key = 'update';
        $intent->name = 'Update';

        $this->assertSame('Actualizar', $intent->displayName());
        $this->assertSame('Intención', __('contact_intents.label'));
        $this->assertSame('Comprar', __('contact_intents.buy'));
        $this->assertSame('Poco clara', __('contact_intents.unclear'));
    }
}
