<?php

namespace App\Livewire;

use App\Models\MessageDelivery;
use Livewire\Component;
use Livewire\WithPagination;

class MessageDeliveries extends Component
{
    use WithPagination;

    public $messageId;

    public $search = '';

    public $statusFilter = 'all';

    protected $paginationTheme = 'bootstrap';

    protected $listeners = ['filterByStatus'];

    public function mount($messageId)
    {
        $this->messageId = $messageId;
    }

    public function updating($name, $value)
    {
        // Reset pagination when search or filter changes
        if ($name === 'search' || $name === 'statusFilter')
        {
            $this->resetPage();
        }
    }

    public function filterByStatus($status)
    {
        // Toggle filter: if clicking the same filter, show all
        if ($this->statusFilter === $status)
        {
            $this->statusFilter = 'all';
        } else
        {
            $this->statusFilter = $status;
        }

        $this->resetPage();
    }

    public function getDeliveriesProperty()
    {
        return $this->loadDeliveries();
    }

    private function loadDeliveries()
    {
        $query = MessageDelivery::where('message_id', $this->messageId)
            ->with(['contact']);

        // Apply status filter
        if ($this->statusFilter !== 'all')
        {
            switch ($this->statusFilter)
            {
                case 'sent':
                    $query->whereNotNull('sent_at')->whereNull('delivered_at');
                    break;
                case 'delivered':
                    $query->whereNotNull('delivered_at');
                    break;
                case 'opened':
                    $query->whereNotNull('opened_at');
                    break;
                case 'clicked':
                    $query->whereNotNull('clicked_at');
                    break;
                case 'failed':
                    $query->where('status_id', 4);
                    break;
                case 'pending':
                    $query->whereNull('sent_at');
                    break;
            }
        }

        // Apply search filter
        if ($this->search)
        {
            $query->whereHas('contact', function ($contactQuery)
            {
                $contactQuery->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate(10);

        // Transform the items manually
        $transformedItems = collect($paginated->items())->map(function ($delivery)
        {
            return [
                'id' => $delivery->id,
                'contact_id' => $delivery->contact ? $delivery->contact->id : null,
                'contact_name' => $delivery->contact ? $delivery->contact->name : '-',
                'contact_email' => $delivery->contact ? $delivery->contact->email : '-',
                'sent_at' => $delivery->sent_at ?
                    (is_string($delivery->sent_at) ? $delivery->sent_at : $delivery->sent_at->format('M j, Y H:i')) : null,
                'delivered_at' => $delivery->delivered_at ?
                    (is_string($delivery->delivered_at) ? $delivery->delivered_at : $delivery->delivered_at->format('M j, Y H:i')) : null,
                'opened_at' => $delivery->opened_at ?
                    (is_string($delivery->opened_at) ? $delivery->opened_at : $delivery->opened_at->format('M j, Y H:i')) : null,
                'clicked_at' => $delivery->clicked_at ?
                    (is_string($delivery->clicked_at) ? $delivery->clicked_at : $delivery->clicked_at->format('M j, Y H:i')) : null,
                'status' => $this->getStatusBadge($delivery),
                'status_text' => $this->getStatusText($delivery),
                'has_opened' => ! is_null($delivery->opened_at),
                'has_clicked' => ! is_null($delivery->clicked_at),
            ];
        });

        // Create a new paginator with transformed data
        $transformedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $transformedItems,
            $paginated->total(),
            $paginated->perPage(),
            $paginated->currentPage(),
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );

        return $transformedPaginator;
    }

    private function getStatusBadge($delivery)
    {
        if ($delivery->status_id == 4) // Error
        {return 'danger';
        } elseif ($delivery->delivered_at)
        {
            return 'success'; // Delivered
        } elseif ($delivery->status_id == 3) // Sending
        {return 'warning';
        } elseif ($delivery->sent_at && $delivery->sent_at->isFuture())
        {
            return 'info'; // Scheduled
        } elseif ($delivery->sent_at && $delivery->sent_at->isPast() && ! $delivery->delivered_at)
        {
            return 'primary'; // Sent but not delivered
        } else
        {
            return 'secondary'; // Pending
        }
    }

    private function getStatusText($delivery)
    {
        if ($delivery->status_id == 4)
        {
            return 'Fallido';
        } elseif ($delivery->delivered_at)
        {
            return 'Entregado';
        } elseif ($delivery->status_id == 3 && $delivery->delivered_at)
        {
            return 'Entregado';
        } elseif ($delivery->status_id == 3)
        {
            return 'Enviando';
        } elseif ($delivery->sent_at && $delivery->sent_at->isFuture())
        {
            return 'Programado';
        } elseif ($delivery->sent_at && $delivery->sent_at->isPast() && ! $delivery->delivered_at)
        {
            return 'Enviado';
        } else
        {
            return 'Pendiente';
        }
    }

    public function render()
    {
        $deliveries = $this->deliveries;

        return view('livewire.message-deliveries', [
            'deliveries' => $deliveries,
            'hasDeliveries' => $deliveries->total() > 0,
        ]);
    }
}
