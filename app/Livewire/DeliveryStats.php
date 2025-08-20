<?php

namespace App\Livewire;

use App\Models\MessageDeliveryStat;
use Livewire\Component;

class DeliveryStats extends Component
{
	public $messageId;

	public $stats;

	public $isUsingSystemSmtp = false;

	public function mount($messageId)
	{
		$this->messageId = $messageId;
		$this->loadStats();

		// Check if team is using system SMTP
		$team = auth()->user()->currentTeam;
		$this->isUsingSystemSmtp = $team ? $team->isUsingSystemSmtp() : false;
	}

	public function loadStats()
	{
		// Always calculate real-time stats from actual deliveries
		$this->updateRealTimeStats();

		$this->stats = MessageDeliveryStat::where('message_id', $this->messageId)->first();

		// Si no hay stats, crear un objeto vacío con valores por defecto
		if (! $this->stats)
		{
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

	private function updateRealTimeStats()
	{
		$message = \App\Models\Message::with('deliveries')->find($this->messageId);

		if (! $message)
		{
			return;
		}

		$deliveries = $message->deliveries;

		$subscribers = $deliveries->count();

		// Only count as "sent" if sent_at is in the past (actually sent)
		$sent = $deliveries->filter(function ($delivery)
		{
			return $delivery->sent_at && $delivery->sent_at->isPast();
		})->count();

		$delivered = $deliveries->whereNotNull('delivered_at')->count();
		$opened = $deliveries->whereNotNull('opened_at')->count();
		$clicks = $deliveries->whereNotNull('clicked_at')->count(); // ✅ Count real clicks
		$failed = $deliveries->where('status_id', 4)->count();

		// Pending includes both unsent and scheduled (future sent_at)
		$pending = $deliveries->filter(function ($delivery)
		{
			return ! $delivery->sent_at || $delivery->sent_at->isFuture();
		})->count();

		$ratio = $subscribers > 0 ? round(($opened / $subscribers) * 100, 2) : 0;

		\App\Models\MessageDeliveryStat::updateOrCreate(
			['message_id' => $this->messageId],
			[
				'subscribers' => $subscribers,
				'sent' => $sent,
				'delivered' => $delivered,
				'opened' => $opened,
				'clicks' => $clicks,
				'failed' => $failed,
				'remaining' => $pending,
				'rejected' => 0,
				'unsubscribed' => 0,
				'unique_opens' => $opened,
				'ratio' => $ratio,
			],
		);
	}

	public function render()
	{
		// Recargar stats en cada render (para el polling)
		$this->loadStats();

		return view('livewire.delivery-stats');
	}
}
