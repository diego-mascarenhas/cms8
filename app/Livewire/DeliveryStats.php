<?php

namespace App\Livewire;

use App\Models\MessageDeliveryStat;
use Livewire\Component;

class DeliveryStats extends Component
{
    public $messageId;
    public $stats;

    public function mount($messageId)
    {
        $this->messageId = $messageId;
        $this->loadStats();
    }

    public function loadStats()
    {
        $this->stats = MessageDeliveryStat::where('message_id', $this->messageId)->first();

        // Si no hay stats, crear un objeto vacío con valores por defecto
        if (!$this->stats) {
            $this->stats = (object) [
                'subscribers' => 0,
                'remaining' => 0,
                'failed' => 0,
                'sent' => 0,
                'rejected' => 0,
                'delivered' => 0,
                'opened' => 0,
                'unsubscribed' => 0,
                'clicks' => 0,
                'unique_opens' => 0,
                'ratio' => 0,
            ];
        }
    }

    public function render()
    {
        // Recargar stats en cada render (para el polling)
        $this->loadStats();

        return view('livewire.delivery-stats');
    }
}
