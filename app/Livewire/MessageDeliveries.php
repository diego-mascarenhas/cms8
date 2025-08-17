<?php

namespace App\Livewire;

use App\Models\MessageDelivery;
use Livewire\Component;

class MessageDeliveries extends Component
{
    public $messageId;
    public $deliveries;

    public function mount($messageId)
    {
        $this->messageId = $messageId;
        $this->loadDeliveries();
    }

    public function loadDeliveries()
    {
        $this->deliveries = MessageDelivery::where('message_id', $this->messageId)
            ->with(['contact'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'contact_name' => $delivery->contact ? $delivery->contact->name : '-',
                    'contact_email' => $delivery->contact ? $delivery->contact->email : '-',
                    'sent_at' => $delivery->sent_at ?
                        (is_string($delivery->sent_at) ? $delivery->sent_at : $delivery->sent_at->format('M j, Y H:i')) : null,
                    'delivered_at' => $delivery->delivered_at ?
                        (is_string($delivery->delivered_at) ? $delivery->delivered_at : $delivery->delivered_at->format('M j, Y H:i')) : null,
                    'status' => $this->getStatusBadge($delivery),
                    'status_text' => $this->getStatusText($delivery)
                ];
            });
    }

    private function getStatusBadge($delivery)
    {
        if ($delivery->status_id == 4) { // Error
            return 'danger';
        } elseif ($delivery->delivered_at) {
            return 'success';
        } elseif ($delivery->status_id == 3) { // Sending
            return 'warning';
        } elseif ($delivery->sent_at && $delivery->sent_at->isFuture()) {
            return 'info'; // Scheduled
        } elseif ($delivery->sent_at && $delivery->sent_at->isPast() && !$delivery->delivered_at) {
            return 'primary'; // Sent but not delivered
        } else {
            return 'secondary'; // Pending
        }
    }

    private function getStatusText($delivery)
    {
        if ($delivery->status_id == 4) {
            return 'Failed';
        } elseif ($delivery->delivered_at) {
            return 'Delivered';
        } elseif ($delivery->status_id == 3) {
            return 'Sending';
        } elseif ($delivery->sent_at && $delivery->sent_at->isFuture()) {
            return 'Scheduled';
        } elseif ($delivery->sent_at && $delivery->sent_at->isPast() && !$delivery->delivered_at) {
            return 'Sent';
        } else {
            return 'Pending';
        }
    }

    public function render()
    {
        // Reload deliveries on each render (for polling)
        $this->loadDeliveries();

        return view('livewire.message-deliveries');
    }
}
