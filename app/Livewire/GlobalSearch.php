<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Service;
use App\Models\Project;
use App\Models\Invoice;

class GlobalSearch extends Component
{
    public $query = '';
    public $results = [];
    public $showResults = false;

    protected $listeners = ['searchUpdated' => 'performSearch'];

    public function updatedQuery()
    {
        if (strlen($this->query) < 2) {
            $this->results = [];
            $this->showResults = false;
            return;
        }

        $this->performSearch();
    }

    public function performSearch()
    {
        if (empty($this->query)) {
            $this->results = [];
            $this->showResults = false;
            return;
        }

        $team = auth()->user()->currentTeam;
        $searchQuery = $this->query;

        $this->results = [
            'contacts' => [],
            'enterprises' => [],
            'services' => [],
            'projects' => [],
            'invoices' => [],
        ];

        // Search contacts
        if ($team && $team->hasModule('contacts')) {
            $this->results['contacts'] = Contact::select('id', 'name', 'surname', 'phone', 'email', 'created_at')
                ->where('status_id', '!=', 6)
                ->where(function ($q) use ($searchQuery) {
                    $q->whereRaw("CONCAT(name, ' ', surname) LIKE ?", ["%{$searchQuery}%"])
                      ->orWhere('email', 'like', "%{$searchQuery}%")
                      ->orWhere('phone', 'like', "%{$searchQuery}%");
                })
                ->limit(10)
                ->get()
                ->map(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'name' => trim($contact->name . ' ' . $contact->surname),
                        'subtitle' => $contact->email ?: 'Creado el ' . $contact->created_at->format('d-m-Y H:i:s') . ' hs',
                        'url' => route('contact.show', $contact->id),
                        'type' => 'contact'
                    ];
                })
                ->toArray();
        }

        // Search enterprises
        $this->results['enterprises'] = Enterprise::select('id', 'name', 'code', 'phone', 'email', 'created_at')
            ->where(function ($q) use ($searchQuery) {
                $q->where('name', 'like', "%{$searchQuery}%")
                  ->orWhere('code', 'like', "%{$searchQuery}%")
                  ->orWhere('phone', 'like', "%{$searchQuery}%")
                  ->orWhere('email', 'like', "%{$searchQuery}%");
            })
            ->limit(10)
            ->get()
            ->map(function ($enterprise) {
                return [
                    'id' => $enterprise->id,
                    'name' => $enterprise->name,
                    'subtitle' => $enterprise->code ? 'Código: ' . $enterprise->code : 'Empresa creada el ' . $enterprise->created_at->format('d-m-Y H:i:s') . ' hs',
                    'url' => route('client.show', $enterprise->id),
                    'type' => 'enterprise'
                ];
            })
            ->toArray();

        // Search services
        if ($team && $team->hasModule('services')) {
            $this->results['services'] = Service::select('id', 'description', 'data', 'created_at')
                ->where('status', 1)
                ->where(function ($q) use ($searchQuery) {
                    $q->where('description', 'like', "%{$searchQuery}%")
                      ->orWhereRaw("JSON_SEARCH(data, 'one', ?) IS NOT NULL", ["%{$searchQuery}%"]);
                })
                ->limit(10)
                ->get()
                ->map(function ($service) {
                    $domain = isset($service->data->domain) ? $service->data->domain : ($service->description ?: 'No domain');
                    return [
                        'id' => $service->id,
                        'name' => $domain,
                        'subtitle' => 'Servicio creado el ' . $service->created_at->format('d-m-Y'),
                        'url' => route('service.show', $service->id),
                        'type' => 'service'
                    ];
                })
                ->toArray();
        }

        // Search projects
        if ($team && $team->hasModule('projects')) {
            $this->results['projects'] = Project::with(['client', 'status'])
                ->select('id', 'name', 'real_name', 'description', 'created_at')
                ->where(function ($q) use ($searchQuery) {
                    $q->where('name', 'like', "%{$searchQuery}%")
                      ->orWhere('description', 'like', "%{$searchQuery}%");
                })
                ->limit(10)
                ->get()
                ->map(function ($project) {
                    $clientName = $project->client ? $project->client->name : 'Sin cliente';
                    $statusName = $project->status ? $project->status->name : 'Sin estado';
                    return [
                        'id' => $project->id,
                        'name' => $project->real_name ?: $project->name,
                        'subtitle' => "Cliente: {$clientName} - Estado: {$statusName}",
                        'url' => route('project.show', $project->id),
                        'type' => 'project'
                    ];
                })
                ->toArray();
        }

        // Search invoices
        $this->results['invoices'] = Invoice::with('enterprise')
            ->select('id', 'number', 'total_amount', 'created_at', 'enterprise_id')
            ->where('number', 'like', "%{$searchQuery}%")
            ->limit(10)
            ->get()
            ->map(function ($invoice) {
                $clientName = $invoice->enterprise ? $invoice->enterprise->name : 'Sin cliente';
                return [
                    'id' => $invoice->id,
                    'name' => 'Factura #' . $invoice->number,
                    'subtitle' => "Cliente: {$clientName} - Total: $" . number_format($invoice->total_amount, 2),
                    'url' => route('invoices.show', $invoice->id),
                    'type' => 'invoice'
                ];
            })
            ->toArray();

        $this->showResults = true;
    }

    public function clearSearch()
    {
        $this->query = '';
        $this->results = [];
        $this->showResults = false;
    }

    public function render()
    {
        return view('livewire.global-search');
    }
}
