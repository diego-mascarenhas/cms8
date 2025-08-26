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

    protected $paginationTheme = 'bootstrap';

    public function mount($messageId)
    {
        $this->messageId = $messageId;
    }

    public function updating($name, $value)
    {
        // Reset pagination when search changes
        if ($name === 'search')
        {
            $this->resetPage();
        }
    }

    public function getDeliveriesProperty()
    {
        return $this->loadDeliveries();
    }

    private function loadDeliveries()
    {
        $query = MessageDelivery::where('message_id', $this->messageId)
            ->with(['contact']);

        // Apply search filter
        if ($this->search)
        {
            $query->whereHas('contact', function ($contactQuery)
            {
                $contactQuery->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate(13);

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
            return 'Failed';
        } elseif ($delivery->delivered_at)
        {
            return 'Delivered';
        } elseif ($delivery->status_id == 3 && $delivery->delivered_at)
        {
            return 'Delivered';
        } elseif ($delivery->status_id == 3)
        {
            return 'Sending';
        } elseif ($delivery->sent_at && $delivery->sent_at->isFuture())
        {
            return 'Scheduled';
        } elseif ($delivery->sent_at && $delivery->sent_at->isPast() && ! $delivery->delivered_at)
        {
            return 'Sent';
        } else
        {
            return 'Pending';
        }
    }

    public function render()
    {
        return view('livewire.message-deliveries', [
            'deliveries' => $this->deliveries,
            'hasDeliveries' => MessageDelivery::where('message_id', $this->messageId)->exists(),
        ]);
    }
}
