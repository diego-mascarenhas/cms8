<?php

namespace App\Services;

use App\Enums\AutomationKind;
use App\Enums\ProductCatalogStatus;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\List60;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\List60NextContactDate;
use App\Support\List60StatusAdvancer;
use Illuminate\Support\Str;

class InboxList60SlashService
{
    /**
     * @return array{ok: bool, messages: list<string>, silent?: bool, notice?: string, error?: string}
     */
    public function enroll(Team $team, ?string $argument, ?Contact $contact, ?User $actor = null): array
    {
        $needle = trim((string) $argument);
        if ($needle === '')
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => 'Usá /list y la nota. Ej: /list assistant, /list shop o /list le interesa el plan.',
            ];
        }

        if ($contact === null)
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => 'No hay un contacto en este chat para sumarlo a la lista de seguimiento.',
            ];
        }

        if (! $this->teamHasList60($team))
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => 'Este equipo no tiene el módulo Lista de seguimiento.',
            ];
        }

        $topic = $this->resolveTopic($team, $needle);
        $responsible = $this->resolveResponsible($team, $contact, $actor);
        if ($responsible === null)
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => 'No hay un responsable válido para la lista de seguimiento.',
            ];
        }

        $note = $this->interestNote($topic);
        $existing = List60::query()->where('contact_id', $contact->id)->first();

        if ($existing !== null)
        {
            $this->appendNotes($contact, $existing, $note);

            return [
                'ok' => true,
                'messages' => [],
                'silent' => true,
                'notice' => $contact->name.' ya estaba en la lista de seguimiento. Dejé la nota: '.$topic['label'].'.',
            ];
        }

        if ($this->responsibleListIsFull($team, $responsible))
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => 'La lista ya tiene 60 contactos.',
            ];
        }

        $record = new List60;
        $record->contact_id = $contact->id;
        $record->date_next = List60NextContactDate::afterOutreach();
        $record->responsible_id = $responsible->id;
        $record->status_id = List60StatusAdvancer::initialStatusId();
        $record->notes = $note;
        $record->save();

        $this->appendContactNotes($contact, $note);
        $this->markContactInFollowUp($contact);

        return [
            'ok' => true,
            'messages' => [],
            'silent' => true,
            'notice' => $contact->name.' quedó en la lista de seguimiento: '.$topic['label'].'.',
        ];
    }

    /**
     * @return array{kind: string, key: string, label: string}|null
     */
    public function resolveTopic(Team $team, string $needle): ?array
    {
        $normalized = mb_strtolower(trim($needle));
        if ($normalized === '')
        {
            return null;
        }

        $platform = $this->platformTopic($normalized);
        if ($platform !== null)
        {
            return $platform;
        }

        $product = $this->shopProductTopic($team, $normalized, $needle);
        if ($product !== null)
        {
            return $product;
        }

        return $this->funnelTopic($team, $normalized)
            ?? [
                'kind' => 'note',
                'key' => 'note',
                'label' => trim($needle),
            ];
    }

    /**
     * @return array{kind: string, key: string, label: string}|null
     */
    private function platformTopic(string $normalized): ?array
    {
        $topics = [
            'assistant' => ['assistant', 'asistente', 'inbox'],
            'shop' => ['shop', 'tienda', 'comercio'],
        ];

        foreach ($topics as $key => $aliases)
        {
            if (in_array($normalized, $aliases, true))
            {
                return [
                    'kind' => 'platform',
                    'key' => $key,
                    'label' => $key === 'assistant' ? 'Assistant' : 'Shop',
                ];
            }
        }

        return null;
    }

    /**
     * @return array{kind: string, key: string, label: string}|null
     */
    private function shopProductTopic(Team $team, string $normalized, string $original): ?array
    {
        $fromQuickReply = app(InboxQuickReplyService::class)->findPublishedShopProduct($team, $original);
        if ($fromQuickReply !== null)
        {
            $product = $fromQuickReply['product'];
            $code = trim((string) ($product->code ?? ''));

            return [
                'kind' => 'product',
                'key' => $code !== '' ? $code : (string) $product->id,
                'label' => $code !== '' ? $product->name.' ('.$code.')' : (string) $product->name,
            ];
        }

        $product = Product::withoutGlobalScope('team')
            ->whereIn('team_id', $this->shopCatalogTeamIds($team))
            ->where('catalog_status', ProductCatalogStatus::Publish)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->orderByRaw('team_id = ? desc', [(int) $team->id])
            ->first();

        if ($product === null)
        {
            return null;
        }

        $code = trim((string) ($product->code ?? ''));

        return [
            'kind' => 'product',
            'key' => $code !== '' ? $code : (string) $product->id,
            'label' => $code !== '' ? $product->name.' ('.$code.')' : (string) $product->name,
        ];
    }

    /**
     * @return array{kind: string, key: string, label: string}|null
     */
    private function funnelTopic(Team $team, string $normalized): ?array
    {
        $funnel = Automation::query()
            ->forTeam((int) $team->id)
            ->ofKind(AutomationKind::Funnel)
            ->where(function ($query) use ($normalized): void
            {
                $query->whereRaw('LOWER(slug) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) = ?', [$normalized]);
            })
            ->orderByRaw('LOWER(slug) = ? desc', [$normalized])
            ->orderByRaw('LOWER(name) = ? desc', [$normalized])
            ->first();

        if ($funnel === null)
        {
            return null;
        }

        return [
            'kind' => 'funnel',
            'key' => (string) ($funnel->slug ?: $funnel->id),
            'label' => 'Embudo «'.$funnel->name.'»',
        ];
    }

    /**
     * @param  array{kind: string, key: string, label: string}  $topic
     */
    private function interestNote(array $topic): string
    {
        return 'Inbox /list: '.$topic['label'].' — abordar más tarde';
    }

    private function teamHasList60(Team $team): bool
    {
        return $team->hasModule('list60');
    }

    private function resolveResponsible(Team $team, Contact $contact, ?User $actor): ?User
    {
        $candidates = array_filter([
            $actor,
            $contact->responsible_id ? User::query()->find($contact->responsible_id) : null,
            $team->user_id ? User::query()->find($team->user_id) : null,
        ]);

        foreach ($candidates as $candidate)
        {
            if (! $candidate instanceof User)
            {
                continue;
            }

            if ((int) $candidate->id === (int) $team->user_id)
            {
                return $candidate;
            }

            if ($candidate->teams->contains($team->id))
            {
                return $candidate;
            }
        }

        return null;
    }

    private function responsibleListIsFull(Team $team, User $responsible): bool
    {
        $total = List60::query()
            ->join('contacts', 'list60.contact_id', '=', 'contacts.id')
            ->where('contacts.team_id', $team->id)
            ->where('list60.responsible_id', $responsible->id)
            ->count();

        return $total >= 60;
    }

    private function appendNotes(Contact $contact, List60 $record, string $note): void
    {
        $current = trim((string) $record->notes);
        $record->notes = $this->joinNotes($current, $note);
        $record->save();
        $this->appendContactNotes($contact, $note);
    }

    private function appendContactNotes(Contact $contact, string $note): void
    {
        $data = (array) ($contact->data ?? new \stdClass);
        $data['notes'] = $this->joinNotes(trim((string) ($data['notes'] ?? '')), $note);
        $contact->update(['data' => $data]);
    }

    private function joinNotes(string $current, string $note): string
    {
        if ($current === '')
        {
            return $note;
        }

        if (Str::contains($current, $note))
        {
            return $current;
        }

        return $current."\n".$note;
    }

    private function markContactInFollowUp(Contact $contact): void
    {
        $followingStatus = ContactStatus::query()->where('name', 'En seguimiento')->first();
        if ($followingStatus === null)
        {
            return;
        }

        $contact->update(['status_id' => $followingStatus->id]);
    }

    /**
     * @return list<int>
     */
    private function shopCatalogTeamIds(Team $team): array
    {
        $ids = [(int) $team->id];
        $user = auth()->user();
        if ($user instanceof User)
        {
            foreach ($user->allTeams() as $memberTeam)
            {
                $ids[] = (int) $memberTeam->id;
            }
        }

        return array_values(array_unique($ids));
    }
}
