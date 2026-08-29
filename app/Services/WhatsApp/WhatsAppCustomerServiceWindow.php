<?php

namespace App\Services\WhatsApp;

use App\Exceptions\WhatsAppSessionWindowClosedException;
use App\Models\Conversation;
use Carbon\Carbon;

class WhatsAppCustomerServiceWindow
{
    public function isOpen(string $customerPhone): bool
    {
        if (! $this->enabled())
        {
            return true;
        }

        $lastInboundAt = $this->lastInboundAt($customerPhone);

        return $lastInboundAt !== null && $lastInboundAt->gt($this->closesAfter());
    }

    public function lastInboundAt(string $customerPhone): ?Carbon
    {
        $candidates = $this->candidatePhones($customerPhone);
        if ($candidates === [])
        {
            return null;
        }

        $createdAt = Conversation::query()
            ->whatsapp()
            ->inbound()
            ->where(function ($query) use ($candidates)
            {
                $query->whereIn('from', $candidates);
                foreach ($candidates as $phone)
                {
                    $query->orWhere('from', 'like', $phone.':%');
                }
            })
            ->orderByDesc('created_at')
            ->value('created_at');

        return $createdAt !== null ? Carbon::parse($createdAt) : null;
    }

    public function assertOpen(string $customerPhone): void
    {
        if (! $this->isOpen($customerPhone))
        {
            throw new WhatsAppSessionWindowClosedException;
        }
    }

    /**
     * @return array{open: bool, last_inbound_at: string|null}
     */
    public function describe(string $customerPhone): array
    {
        $lastInboundAt = $this->lastInboundAt($customerPhone);

        return [
            'open' => $this->enabled()
                ? ($lastInboundAt !== null && $lastInboundAt->gt($this->closesAfter()))
                : true,
            'last_inbound_at' => $lastInboundAt?->toIso8601String(),
        ];
    }

    public function enabled(): bool
    {
        return (bool) config('whatsapp.customer_service_window.enabled', true);
    }

    /**
     * @return list<string>
     */
    private function candidatePhones(string $customerPhone): array
    {
        $digits = WhatsAppInboxContactStarter::normalizeInboxPhone($customerPhone);
        if ($digits === '')
        {
            return [];
        }

        $candidates = [$digits];
        if (str_starts_with($digits, '34') && strlen($digits) === 11)
        {
            $candidates[] = substr($digits, 2);
        }
        if (strlen($digits) === 9 && preg_match('/^[67]/', $digits) === 1)
        {
            $candidates[] = '34'.$digits;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function closesAfter(): Carbon
    {
        $hours = (int) config('whatsapp.customer_service_window.hours', 24);
        if ($hours < 1)
        {
            $hours = 24;
        }

        return now()->subHours($hours);
    }
}
