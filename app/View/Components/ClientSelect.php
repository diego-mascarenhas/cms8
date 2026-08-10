<?php

namespace App\View\Components;

use App\Models\Enterprise;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class ClientSelect extends Component
{
    /**
     * @var Collection<int, string>
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
     * @return Collection<int, string>
     */
    private function getClients(): Collection
    {
        return Enterprise::query()
            ->where('type_id', 1)
            ->where('team_id', auth()->user()->currentTeam->id)
            ->with(['contacts' => function ($query): void
            {
                $query->select('contacts.id', 'contacts.name', 'contacts.surname', 'contacts.email');
            }])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(function (Enterprise $enterprise): array
            {
                $label = trim((string) $enterprise->name);
                $contact = $enterprise->quoteContact();
                if ($contact)
                {
                    $contactName = trim($contact->name.' '.(string) ($contact->surname ?? ''));
                    $contactEmail = trim((string) ($contact->email ?? ''));
                    if ($contactName !== '' && $contactEmail !== '')
                    {
                        $label .= ' ('.$contactName.' · '.$contactEmail.')';
                    } elseif ($contactEmail !== '')
                    {
                        $label .= ' ('.$contactEmail.')';
                    } elseif ($contactName !== '')
                    {
                        $label .= ' ('.$contactName.')';
                    }
                }

                return [(int) $enterprise->id => $label];
            });
    }

    public function render()
    {
        return view('components.client-select');
    }
}
