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
                    'sent_at' => $delivery->sent_at ? $delivery->sent_at->format('Y-m-d H:i:s') : null,
                    'delivered_at' => $delivery->delivered_at ? $delivery->delivered_at->format('Y-m-d H:i:s') : null,
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
        } elseif ($delivery->sent_at) {
            return 'info';
        } elseif ($delivery->status_id == 3) { // Sending
            return 'warning';
        } else {
            return 'secondary';
        }
    }

    private function getStatusText($delivery)
    {
        if ($delivery->status_id == 4) {
            return 'Failed';
        } elseif ($delivery->delivered_at) {
            return 'Delivered';
        } elseif ($delivery->sent_at) {
            return 'Sent';
        } elseif ($delivery->status_id == 3) {
            return 'Sending';
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
