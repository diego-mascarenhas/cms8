<?php

namespace App\Services\Contacts;

use App\Enums\ContactInteractionType;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\PersonEnrichment;
use App\Services\InboxContactAccessService;
use App\Services\Prospecting\ProspectDirectory;
use App\Services\WhatsApp\WhatsAppThreadCategoryService;

class ContactProfileBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Contact $contact): array
    {
        $contact->loadMissing(['status', 'responsible', 'currentEnterprise', 'person.enrichments', 'categories', 'team', 'currentSentiment.sentiment']);
        $country = $contact->country()->first();
        $payload = $this->apolloPayload($contact);
        $organization = $this->organization($contact, $payload);
        $enterpriseName = trim((string) ($contact->currentEnterprise?->name ?? ''));
        $title = trim((string) ($contact->title ?: $contact->person?->title ?: ($payload['title'] ?? '')));
        $email = trim((string) $contact->email);
        $access = app(InboxContactAccessService::class)->presentForContact($contact->team, $contact);
        $sentiment = $contact->currentSentiment?->sentiment;

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'surname' => $contact->surname,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'avatar_url' => $contact->avatarUrl(120),
            'role' => $title !== '' ? $title : null,
            'title' => $title !== '' ? $title : null,
            'organization' => $organization !== '' && strcasecmp($organization, $enterpriseName) !== 0 ? $organization : null,
            'city' => $this->text($payload['city'] ?? null),
            'country_name' => $country?->name ?: $this->text($payload['country'] ?? null),
            'linkedin' => $this->text($payload['linkedin_url'] ?? null),
            'seniority' => $this->seniority($payload['seniority'] ?? null),
            'headline' => $this->text($payload['headline'] ?? null),
            'can_enrich' => $this->canEnrich($contact, $organization !== '' ? $organization : $enterpriseName),
            'status' => $contact->status ? [
                'id' => $contact->status->id,
                'name' => $contact->status->name,
                'label_class' => $contact->status->label_class,
            ] : null,
            'responsible' => $contact->responsible ? ['id' => $contact->responsible->id, 'name' => $contact->responsible->name] : null,
            'enterprise' => $contact->currentEnterprise ? ['id' => $contact->currentEnterprise->id, 'name' => $contact->currentEnterprise->name] : null,
            'categories' => $contact->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => WhatsAppThreadCategoryService::normalizeColor($category->color),
            ])->values(),
            'access' => $access ? [
                'id' => $access['id'],
                'staff' => $access['staff'],
            ] : null,
            'sentiment' => $sentiment ? [
                'id' => $sentiment->id,
                'name' => $sentiment->name,
                'emoji' => $sentiment->emoji,
            ] : null,
            'activity' => $this->activity($contact),
            'email_present' => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL),
        ];
    }

    public function canEnrich(Contact $contact, string $organization = ''): bool
    {
        $email = trim((string) $contact->email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            return true;
        }

        return trim((string) $contact->name) !== ''
            && trim((string) $contact->surname) !== ''
            && trim($organization) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function apolloPayload(Contact $contact): array
    {
        $enrichment = $contact->person?->enrichments
            ?->first(fn (PersonEnrichment $row): bool => $row->source === ProspectDirectory::SOURCE_APOLLO && is_array($row->payload));

        return is_array($enrichment?->payload) ? $enrichment->payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function organization(Contact $contact, array $payload): string
    {
        $stored = trim((string) ($contact->person?->organization_name ?? ''));
        if ($stored !== '')
        {
            return $stored;
        }

        $organization = $payload['organization'] ?? null;
        if (is_array($organization))
        {
            return trim((string) ($organization['name'] ?? ''));
        }

        return trim((string) ($payload['organization_name'] ?? ''));
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function seniority(mixed $value): ?string
    {
        $text = str_replace('_', ' ', trim((string) $value));

        return $text !== '' ? ucfirst($text) : null;
    }

    /**
     * @return list<array{id: int, type: string, label: string, text: string|null, at: string|null, author: string|null}>
     */
    private function activity(Contact $contact): array
    {
        return $contact->contactInteractions()
            ->with('user:id,name')
            ->limit(6)
            ->get()
            ->map(function (ContactInteraction $row): array
            {
                $type = $row->type instanceof ContactInteractionType
                    ? $row->type->value
                    : (string) $row->type;
                $text = trim((string) $row->subject);
                $body = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $row->body)));

                if ($text === '' && $body !== '' && ! preg_match('/\[\d{1,2}\/\d{1,2}\//', $body))
                {
                    $text = mb_substr($body, 0, 140);
                }

                return [
                    'id' => (int) $row->id,
                    'type' => $type !== '' ? $type : 'other',
                    'label' => $this->activityLabel($type),
                    'text' => $text !== '' ? $text : null,
                    'at' => $row->occurred_at?->timezone('Europe/Madrid')->format('d/m/Y H:i'),
                    'author' => $row->user?->name,
                ];
            })
            ->all();
    }

    private function activityLabel(string $type): string
    {
        return match ($type)
        {
            'call' => 'Llamada',
            'email' => 'Correo',
            'meeting' => 'Reunión',
            'note' => 'Nota',
            'whatsapp' => 'WhatsApp',
            'cart_abandoned' => 'Carrito abandonado',
            'order_paid' => 'Pedido pagado',
            default => 'Actividad',
        };
    }
}
