<?php

namespace Tests\Unit;

use App\Helpers\AvatarHelper;
use App\Jobs\FetchWhatsAppProfilePhotoJob;
use App\Models\Contact;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppProfilePhotoStore;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactAvatarUrlTest extends TestCase
{
    public function test_falls_back_to_generated_initials_without_whatsapp_photo(): void
    {
        Storage::fake('public');

        $contact = new Contact;
        $contact->team_id = 3;
        $contact->phone = 5491100000001;
        $contact->name = 'Prueba Categoria';

        $this->assertSame(AvatarHelper::generate('Prueba Categoria', 100), $contact->avatarUrl());
    }

    public function test_uses_stored_whatsapp_profile_photo_when_present(): void
    {
        Storage::fake('public');

        $contact = new Contact;
        $contact->team_id = 3;
        $contact->phone = 5491100000001;
        $contact->name = 'Prueba Categoria';

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        app(WhatsAppProfilePhotoStore::class)->storeFromBase64(3, '5491100000001', $png, 'image/png');

        $url = $contact->avatarUrl();

        $this->assertStringContainsString('whatsapp/avatars/3/5491100000001.jpg', $url);
        $this->assertStringNotContainsString('data:image/svg+xml', $url);
    }

    public function test_uses_linked_user_phone_when_contact_phone_is_empty(): void
    {
        Storage::fake('public');

        $user = new User;
        $user->phone = '5491100000002';

        $contact = new Contact;
        $contact->team_id = 3;
        $contact->phone = null;
        $contact->name = 'Sin Telefono';
        $contact->setRelation('user', $user);

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        app(WhatsAppProfilePhotoStore::class)->storeFromBase64(3, '5491100000002', $png, 'image/png');

        $url = $contact->avatarUrl();

        $this->assertStringContainsString('whatsapp/avatars/3/5491100000002.jpg', $url);
        $this->assertStringNotContainsString('data:image/svg+xml', $url);
    }

    public function test_uses_argentina_whatsapp_number_when_crm_phone_omits_the_mobile_nine(): void
    {
        Storage::fake('public');

        $contact = new Contact;
        $contact->team_id = 3;
        $contact->phone = '541171024567';
        $contact->name = 'Jose Maria';

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        app(WhatsAppProfilePhotoStore::class)->storeFromBase64(3, '5491171024567', $png, 'image/png');

        $url = $contact->avatarUrl();

        $this->assertStringContainsString('whatsapp/avatars/3/5491171024567.jpg', $url);
        $this->assertStringNotContainsString('data:image/svg+xml', $url);
    }

    public function test_dispatch_for_contact_skips_when_phone_is_missing(): void
    {
        Bus::fake();

        $contact = new Contact;
        $contact->team_id = 3;
        $contact->phone = null;

        FetchWhatsAppProfilePhotoJob::dispatchForContact($contact);

        Bus::assertNotDispatched(FetchWhatsAppProfilePhotoJob::class);
    }
}
