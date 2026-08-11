<?php

namespace App\View\Components;

use App\Models\Enterprise;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class ClientSelect extends Component
{
    /**
     * @var Collection<int, array{
     *     id: int,
     *     name: string,
     *     type: string|null,
     *     responsible: string|null,
     *     contacts: list<array{label: string, search: string}>,
     *     keywords: string
     * }>
     */
    public $options;

    public $selected;

    public $label;

    public $id;

    public $allowNull;

    public function __construct($selected = null, $label = 'Cliente', $id = 'enterprise_id', $allowNull = true)
    {
        $this->selected = $selected;
        $this->label = $label;
        $this->id = $id;
        $this->allowNull = $allowNull;
        $this->options = $this->getClients();
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     type: string|null,
     *     responsible: string|null,
     *     contacts: list<array{label: string, search: string}>,
     *     keywords: string
     * }>
     */
    private function getClients(): Collection
    {
        return Enterprise::query()
            ->with([
                'type:id,name',
                'responsible:id,name,email',
                'contacts' => function ($query): void
                {
                    $query->select('contacts.id', 'contacts.name', 'contacts.surname', 'contacts.email');
                },
            ])
            ->where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type_id', 'responsible_id'])
            ->map(function (Enterprise $enterprise): array
            {
                // Prefer quoteContact() first so budget/outreach recipient stays the default secondary line.
                $quoteContactId = $enterprise->quoteContact()?->id;

                $contacts = $enterprise->contacts
                    ->map(function ($contact) use ($quoteContactId): ?array
                    {
                        $label = $this->formatPersonLabel(
                            trim(($contact->name ?? '').' '.($contact->surname ?? '')),
                            $contact->email,
                        );

                        if ($label === null)
                        {
                            return null;
                        }

                        return [
                            'label' => $label,
                            'search' => $label,
                            'is_quote' => $quoteContactId !== null && (int) $contact->id === (int) $quoteContactId,
                        ];
                    })
                    ->filter()
                    ->sortByDesc(fn (array $contact): int => ! empty($contact['is_quote']) ? 1 : 0)
                    ->values()
                    ->map(function (array $contact): array
                    {
                        unset($contact['is_quote']);

                        return $contact;
                    })
                    ->all();

                $typeName = $enterprise->type?->name;
                $responsibleLabel = $this->formatPersonLabel(
                    $enterprise->responsible?->name,
                    $enterprise->responsible?->email,
                );

                $keywordParts = array_filter([
                    collect($contacts)->pluck('search')->implode(' '),
                    $typeName,
                    $responsibleLabel,
                ]);

                return [
                    'id' => (int) $enterprise->id,
                    'name' => (string) $enterprise->name,
                    'type' => $typeName,
                    'responsible' => $responsibleLabel,
                    'contacts' => $contacts,
                    'keywords' => trim(implode(' ', $keywordParts)),
                ];
            });
    }

    private function formatPersonLabel(?string $name, ?string $email): ?string
    {
        $name = trim((string) $name);
        $email = trim((string) $email);

        if ($name === '' && $email === '')
        {
            return null;
        }

        if ($name !== '' && $email !== '')
        {
            return $name.' · '.$email;
        }

        return $name !== '' ? $name : $email;
    }

    public function render()
    {
        return view('components.client-select');
    }
}
