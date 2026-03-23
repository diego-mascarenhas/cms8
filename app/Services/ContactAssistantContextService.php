<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Country;
use App\Models\Language;

class ContactAssistantContextService
{
    private const PROFILE_MAX_CHARS = 1200;

    /**
     * Single markdown block with CRM data for the assistant (one query + eager loads).
     * No external APIs — avoid duplicate tool calls for the same contact fields.
     */
    public function buildMarkdownSummary(int $contactId, int $teamId): string
    {
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->with([
                'status',
                'country',
                'language',
                'valoration',
                'responsible',
                'creator',
                'currentEnterprise',
                'enterprises',
                'categories',
                'primarySource',
            ])
            ->find($contactId);

        if (! $contact)
        {
            return '';
        }

        $lines = [];
        $lines[] = '### Contexto del contacto (CRM — resumen)';
        $lines[] = 'Datos ya cargados en el sistema. **No pidas** al operador información que ya figura aquí salvo que falte o sea ambigua.';
        $lines[] = '';
        $lines[] = '- **ID:** '.(string) $contact->id;
        $fullName = trim((string) $contact->name.' '.(string) ($contact->surname ?? ''));
        $lines[] = '- **Nombre:** '.($fullName !== '' ? $fullName : '—');
        $lines[] = '- **Email:** '.($contact->email ?: '—');
        $lines[] = '- **Teléfono:** '.($contact->phone ?: '—');

        if ($contact->birthday)
        {
            $lines[] = '- **Fecha de nacimiento:** '.$contact->birthday->format('Y-m-d');
        }

        if ($contact->status)
        {
            $lines[] = '- **Estado:** '.$contact->status->name;
        }

        if ($contact->valoration)
        {
            $lines[] = '- **Valoración:** '.$contact->valoration->name;
        }

        $countryName = $this->resolveCountryName($contact);
        if ($countryName !== null)
        {
            $lines[] = '- **País:** '.$countryName;
        }

        $languageName = $this->resolveLanguageName($contact);
        if ($languageName !== null)
        {
            $lines[] = '- **Idioma:** '.$languageName;
        }

        if ($contact->primarySource)
        {
            $lines[] = '- **Origen (principal):** '.$contact->primarySource->name;
        }

        if ($contact->responsible)
        {
            $lines[] = '- **Responsable:** '.$contact->responsible->name;
        }

        if ($contact->creator)
        {
            $lines[] = '- **Creado por:** '.$contact->creator->name;
        }

        $lines[] = '- **Alta en CRM:** '.$contact->created_at?->format('Y-m-d H:i') ?? '—';

        $categories = $contact->categories->pluck('name')->filter()->values();
        if ($categories->isNotEmpty())
        {
            $lines[] = '- **Categorías:** '.$categories->implode(', ');
        }

        $enterprise = $contact->currentEnterprise ?: $contact->enterprises->first();
        if ($enterprise)
        {
            $lines[] = '- **Empresa actual / principal:** '.($enterprise->name ?: '—');
            if ($enterprise->code)
            {
                $lines[] = '- **Stripe Customer ID (empresa):** '.$enterprise->code;
            }
        }

        $otherEnterprises = $contact->enterprises
            ->when($enterprise, fn ($c) => $c->where('id', '!=', $enterprise->id))
            ->take(5);
        foreach ($otherEnterprises as $ent)
        {
            $lines[] = '- **Otra empresa:** '.($ent->name ?: '—').($ent->code ? ' (`'.$ent->code.'`)' : '');
        }

        $profile = is_string($contact->profile) ? trim($contact->profile) : '';
        if ($profile !== '')
        {
            if (mb_strlen($profile) > self::PROFILE_MAX_CHARS)
            {
                $profile = mb_substr($profile, 0, self::PROFILE_MAX_CHARS).'…';
            }
            $lines[] = '';
            $lines[] = '**Perfil / notas (extracto):**';
            $lines[] = $profile;
        }

        return implode("\n", $lines);
    }

    /**
     * Contact uses column `country` (FK id) with a relation also named `country`; the attribute can shadow the model.
     */
    private function resolveCountryName(Contact $contact): ?string
    {
        if ($contact->relationLoaded('country'))
        {
            $rel = $contact->getRelation('country');

            return $rel ? $rel->name : null;
        }

        $id = $contact->getAttribute('country');
        if (! $id)
        {
            return null;
        }

        return Country::query()->find((int) $id)?->name;
    }

    /**
     * Contact uses column `language` (code) with a relation also named `language`; the attribute can shadow the model.
     */
    private function resolveLanguageName(Contact $contact): ?string
    {
        if ($contact->relationLoaded('language'))
        {
            $rel = $contact->getRelation('language');

            return $rel ? $rel->name : null;
        }

        $code = $contact->getAttribute('language');
        if (! is_string($code) || $code === '')
        {
            return null;
        }

        return Language::query()->where('code', $code)->value('name') ?? $code;
    }
}
