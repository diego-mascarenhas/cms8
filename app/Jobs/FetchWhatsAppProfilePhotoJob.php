<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Team;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppProfilePhotoStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchWhatsAppProfilePhotoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $teamId,
        public string $phone,
    ) {}

    public static function dispatchForContact(Contact $contact): void
    {
        $phone = preg_replace('/[^0-9]/', '', (string) ($contact->phone ?? '')) ?? '';
        $teamId = (int) ($contact->team_id ?? 0);
        if ($phone === '' || $teamId < 1)
        {
            return;
        }

        static::dispatch($teamId, $phone)->afterResponse();
    }

    public function handle(WhatsAppProfilePhotoStore $store): void
    {
        $digits = preg_replace('/[^0-9]/', '', $this->phone) ?? '';
        if ($digits === '' || $store->isFresh($this->teamId, $digits))
        {
            return;
        }

        $team = Team::withoutGlobalScopes()->find($this->teamId);
        $baseUrl = $team?->getWhatsAppServiceBaseUrl() ?? '';
        if ($team === null || ! $team->usesLocalWhatsApp() || $baseUrl === '')
        {
            return;
        }

        $gateway = new LocalWhatsAppGateway(
            $baseUrl,
            config('whatsapp.local.webhook_secret'),
            $this->teamId,
        );

        $store->hydrateFromGateway($gateway, $this->teamId, [$digits]);
    }
}
