<?php

namespace App\Livewire;

use App\Services\Mail\MailInboxService;
use Livewire\Attributes\On;
use Livewire\Component;

class MailNavbarIcon extends Component
{
    public int $unreadCount = 0;

    public function mount(MailInboxService $mailInboxService): void
    {
        $this->refreshUnreadCount($mailInboxService);
    }

    #[On('mail-inbox-updated')]
    public function handleInboxUpdated(MailInboxService $mailInboxService): void
    {
        $this->refreshUnreadCount($mailInboxService);
    }

    public function refreshUnreadCount(MailInboxService $mailInboxService): void
    {
        $team = auth()->user()?->currentTeam;

        if ($team === null || ! $team->hasModule('mailbox'))
        {
            $this->unreadCount = 0;

            return;
        }

        $counts = $mailInboxService->folderCounts($team);
        $this->unreadCount = (int) ($counts['inbox_unread'] ?? 0);
    }

    public function render()
    {
        return view('livewire.mail-navbar-icon');
    }
}
