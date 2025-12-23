<?php

namespace App\Livewire;

use App\Models\MessageDeliveryStat;
use Livewire\Component;

class DeliveryStats extends Component
{
    public $messageId;

    public $stats;

    public $isUsingSystemSmtp = false;

    public $message;

    public $criticalErrorsCount = 0;

    public $wasPausedForErrors = false;

    public $showSubscribersModal = false;

    public $potentialSubscribers = [];

    public $subscribersStats = [
        'total' => 0,
        'with_delivery' => 0,
        'without_delivery' => 0,
    ];

    public function mount($messageId)
    {
        $this->messageId = $messageId;
        $this->message = \App\Models\Message::find($messageId);
        $this->loadStats();
        $this->checkForCriticalErrors();

        // Check if team is using system SMTP
        $team = auth()->user()->currentTeam;
        $this->isUsingSystemSmtp = $team ? $team->isUsingSystemSmtp() : false;

        // Calculate potential subscribers on mount
        $this->updatePotentialSubscribersCount();
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

        // Count unique contacts (subscribers), not total deliveries
        $subscribers = $deliveries->pluck('contact_id')->unique()->count();

        // If no deliveries exist yet, count potential subscribers
        if ($subscribers === 0)
        {
            $subscribers = $this->getPotentialSubscribersCount();
        }

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

        $ratio = $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0;

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

    private function updatePotentialSubscribersCount()
    {
        // This is called on mount to ensure stats table has correct subscriber count
        $this->updateRealTimeStats();
    }

    private function getPotentialSubscribersCount(): int
    {
        $message = \App\Models\Message::find($this->messageId);

        if (! $message)
        {
            return 0;
        }

        $contactsQuery = $this->getContactsForMessage($message);

        return $contactsQuery->count();
    }

    private function checkForCriticalErrors()
    {
        if (! $this->message)
        {
            return;
        }

        // Count critical errors in last hour
        $this->criticalErrorsCount = \App\Models\MessageDelivery::where('message_id', $this->messageId)
            ->where('status_id', 4) // error status
            ->where('updated_at', '>=', now()->subHour())
            ->whereNotNull('provider_data')
            ->get()
            ->filter(function ($delivery)
            {
                $errorMessage = $delivery->provider_data['error'] ?? '';

                return \App\Models\Message::isCriticalError($errorMessage);
            })
            ->count();

        // Check if campaign was recently paused (status changed to 0 in last hour)
        $this->wasPausedForErrors = $this->message->status_id == 0 &&
            $this->message->updated_at->isAfter(now()->subHour()) &&
            $this->criticalErrorsCount > 0;
    }

    public function showSubscribers()
    {
        $this->loadPotentialSubscribers();
        $this->showSubscribersModal = true;
    }

    public function closeSubscribersModal()
    {
        $this->showSubscribersModal = false;
    }

    private function loadPotentialSubscribers()
    {
        $message = \App\Models\Message::with('category', 'contactStatus')->find($this->messageId);

        if (! $message)
        {
            $this->potentialSubscribers = [];

            return;
        }

        // Get contacts that match message criteria
        $contactsQuery = $this->getContactsForMessage($message);
        $contacts = $contactsQuery->get();

        // Get existing deliveries for comparison
        $existingDeliveryIds = \App\Models\MessageDelivery::where('message_id', $message->id)
            ->pluck('contact_id')
            ->toArray();

        // Mark which contacts already have deliveries and prepare data
        $this->potentialSubscribers = $contacts->map(function ($contact) use ($existingDeliveryIds)
        {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'has_delivery' => in_array($contact->id, $existingDeliveryIds),
            ];
        })->toArray();

        $this->subscribersStats = [
            'total' => count($this->potentialSubscribers),
            'with_delivery' => count(array_filter($this->potentialSubscribers, fn ($c) => $c['has_delivery'])),
            'without_delivery' => count(array_filter($this->potentialSubscribers, fn ($c) => ! $c['has_delivery'])),
        ];
    }

    private function getContactsForMessage($message)
    {
        $query = null;

        if ($message->category)
        {
            $query = $message->category->contacts();

            // Filter by contact status - use message's contact_status_id or default to active (1)
            $statusId = $message->contact_status_id ?: 1;
            $query->where('status_id', $statusId);
        } else
        {
            // If no category, get all contacts from the team
            $query = \App\Models\Contact::where('team_id', $message->team_id)
                ->whereNotNull('email');

            // Filter by contact status - use message's contact_status_id or default to active (1)
            $statusId = $message->contact_status_id ?: 1;
            $query->where('status_id', $statusId);
        }

        // Exclude test/demo email addresses
        $testDomains = [
            '@example.org',
            '@example.net',
            '@example.com',
            '@demo.com',
            '@test.com',
            '@localhost',
            '@testing.com',
            '@dummy.com',
            '@fake.com',
        ];

        foreach ($testDomains as $domain)
        {
            $query->where('email', 'not like', '%'.$domain);
        }

        return $query;
    }

    public function render()
    {
        // Recargar stats en cada render (para el polling)
        $this->loadStats();
        $this->checkForCriticalErrors();

        return view('livewire.delivery-stats');
    }
}
