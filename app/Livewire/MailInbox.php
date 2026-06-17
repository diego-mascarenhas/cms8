<?php

namespace App\Livewire;

use App\Enums\EmailFolder;
use App\Models\Email;
use App\Models\Team;
use App\Services\Imap\MailboxConnectionService;
use App\Services\Mail\MailInboxService;
use App\Support\MailComposeReplyForward;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class MailInbox extends Component
{
    use WithPagination;

    public string $folder = 'inbox';

    public string $search = '';

    /** @var list<int> */
    public array $selectedIds = [];

    public ?int $selectedEmailId = null;

    public ?string $expandedSenderKey = null;

    public bool $selectAllOnPage = false;

    public ?string $statusMessage = null;

    public ?string $statusType = null;

    protected $paginationTheme = 'bootstrap';

    protected MailInboxService $inboxService;

    public function boot(MailInboxService $inboxService): void
    {
        $this->inboxService = $inboxService;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    #[On('mail-set-folder')]
    public function setFolder(string $folder): void
    {
        $this->folder = $folder;
        $this->resetPage();
        $this->selectedEmailId = null;
        $this->expandedSenderKey = null;
        $this->clearSelection();
    }

    public function refreshMailbox(MailboxConnectionService $mailboxService): void
    {
        $team = $this->currentTeam();
        if (! $team)
        {
            $this->flashStatus(__('No hay equipo seleccionado.'), 'danger');

            return;
        }

        $mailboxes = $team->mailboxes()->get();
        if ($mailboxes->isEmpty())
        {
            $this->flashStatus(__('No hay casillas configuradas. Añade una en Gestionar casillas.'), 'danger');

            return;
        }

        $synced = 0;
        foreach ($mailboxes as $mailbox)
        {
            try
            {
                $synced += $mailboxService->syncMessages($mailbox, 100);
            } catch (ConnectionFailedException $e)
            {
                Log::warning('Mail refresh sync failed', ['mailbox_id' => $mailbox->id, 'error' => $e->getMessage()]);
            } catch (\Throwable $e)
            {
                Log::warning('Mail refresh sync failed', ['mailbox_id' => $mailbox->id, 'error' => $e->getMessage()]);
            }
        }

        $this->resetPage();
        $this->flashStatus(
            __('Sincronización completada. :count mensajes procesados.', ['count' => $synced]),
            'success',
        );
    }

    public function selectEmail(int $emailId): void
    {
        $email = $this->findTeamEmail($emailId);
        if (! $email)
        {
            return;
        }

        $this->selectedEmailId = $emailId;
        $this->expandedSenderKey = MailInboxService::senderKeyFromAddress((string) $email->from_address);
        if (! $email->seen)
        {
            $email->update(['seen' => true]);
        }
    }

    public function toggleSenderExpand(string $senderKey): void
    {
        $this->expandedSenderKey = $this->expandedSenderKey === $senderKey ? null : $senderKey;
    }

    public function closeEmailView(): void
    {
        $this->selectedEmailId = null;
        $this->expandedSenderKey = null;
    }

    public function updatedSelectAllOnPage(bool $value): void
    {
        $pageIds = collect($this->paginatedSenderGroups()->items())
            ->flatMap(fn (array $group) => $group['email_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($value)
        {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $pageIds)));
        } else
        {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
        }
    }

    public function markSelectedRead(): void
    {
        if ($this->shouldMarkEntireFolderReadState())
        {
            $this->markAllInCurrentFolderRead();

            return;
        }

        $this->bulkMarkRead(true);
    }

    public function markSelectedUnread(): void
    {
        if ($this->selectedIds === [])
        {
            $this->markAllInCurrentFolderUnread();

            return;
        }

        $this->bulkMarkRead(false);
    }

    public function deleteSelected(): void
    {
        $team = $this->currentTeam();
        if (! $team || $this->selectedIds === [])
        {
            return;
        }

        if ($this->folder === EmailFolder::Trash->value)
        {
            $this->inboxService->deletePermanently($team, $this->selectedIds);
            $this->flashStatus(__('Mensajes eliminados permanentemente.'), 'success');
        } else
        {
            $this->inboxService->moveToFolder($team, $this->selectedIds, EmailFolder::Trash);
            $this->flashStatus(__('Mensajes movidos a la papelera.'), 'success');
        }

        $this->afterBulkAction();
    }

    public function archiveSelected(): void
    {
        $team = $this->currentTeam();
        if (! $team || $this->selectedIds === [])
        {
            return;
        }

        $this->inboxService->moveToFolder($team, $this->selectedIds, EmailFolder::Archive);
        $this->flashStatus(__('Mensajes archivados.'), 'success');
        $this->afterBulkAction();
    }

    public function moveSelectedToSpam(): void
    {
        $team = $this->currentTeam();
        if (! $team || $this->selectedIds === [])
        {
            return;
        }

        $this->inboxService->moveToFolder($team, $this->selectedIds, EmailFolder::Spam);
        $this->flashStatus(__('Mensajes movidos a spam.'), 'success');
        $this->afterBulkAction();
    }

    public function moveSelectedFromSpam(): void
    {
        $this->moveSelectedToInbox();
    }

    public function moveSelectedToInbox(): void
    {
        $team = $this->currentTeam();
        if (! $team || $this->selectedIds === [])
        {
            return;
        }

        $this->inboxService->moveToFolder($team, $this->selectedIds, EmailFolder::Inbox);
        $this->flashStatus(__('Mensajes movidos a la bandeja de entrada.'), 'success');
        $this->afterBulkAction();
    }

    public function moveSelectedToDraft(): void
    {
        $team = $this->currentTeam();
        if (! $team || $this->selectedIds === [])
        {
            return;
        }

        $this->inboxService->moveToFolder($team, $this->selectedIds, EmailFolder::Draft);
        $this->flashStatus(__('Mensajes movidos a borradores.'), 'success');
        $this->afterBulkAction();
    }

    public function toggleStarFor(int $emailId): void
    {
        $team = $this->currentTeam();
        if (! $team)
        {
            return;
        }

        $this->inboxService->toggleStar($team, [$emailId]);
    }

    public function toggleStarSelected(): void
    {
        $team = $this->currentTeam();
        if (! $team || $this->selectedIds === [])
        {
            return;
        }

        $this->inboxService->toggleStar($team, $this->selectedIds);
        $this->clearSelection();
    }

    /**
     * @param  list<int>  $emailIds
     */
    public function toggleGroupSelection(array $emailIds): void
    {
        $emailIds = array_values(array_map('intval', $emailIds));
        if ($emailIds === [])
        {
            return;
        }

        $allSelected = count(array_intersect($emailIds, $this->selectedIds)) === count($emailIds);

        if ($allSelected)
        {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $emailIds));
        } else
        {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $emailIds)));
        }
    }

    public function forwardSelected(): void
    {
        $email = $this->selectedEmail;
        if ($email === null)
        {
            return;
        }

        $payload = MailComposeReplyForward::forwardPayload($email);
        $this->dispatch('open-mail-compose', ...$payload);
    }

    public function replyToSelected(): void
    {
        $email = $this->selectedEmail;
        if ($email === null)
        {
            return;
        }

        $payload = MailComposeReplyForward::replyPayload($email, $this->folder);
        $this->dispatch('open-mail-compose', ...$payload);
    }

    public function markGroupRead(array $emailIds): void
    {
        $this->markEmailsReadState($emailIds, true);
    }

    public function markGroupUnread(array $emailIds): void
    {
        $this->markEmailsReadState($emailIds, false);
    }

    public function markSingleRead(int $emailId): void
    {
        $this->markEmailsReadState([$emailId], true);
    }

    public function markSingleUnread(int $emailId): void
    {
        $this->markEmailsReadState([$emailId], false);
    }

    /**
     * @param  list<int>  $emailIds
     */
    private function markEmailsReadState(array $emailIds, bool $read): void
    {
        $team = $this->currentTeam();
        if (! $team)
        {
            return;
        }

        $emailIds = array_values(array_unique(array_map('intval', $emailIds)));
        if ($emailIds === [])
        {
            return;
        }

        $this->inboxService->markRead($team, $emailIds, $read);
        $this->flashStatus(
            $read ? __('Marcados como leídos.') : __('Marcados como no leídos.'),
            'success',
        );
    }

    public function deleteSingle(int $emailId): void
    {
        $this->selectedIds = [$emailId];
        $this->deleteSelected();
    }

    public function archiveSingle(int $emailId): void
    {
        $this->selectedIds = [$emailId];
        $this->archiveSelected();
    }

    public function moveSingleToSpam(int $emailId): void
    {
        $this->selectedIds = [$emailId];
        $this->moveSelectedToSpam();
    }

    public function moveSingleFromSpam(int $emailId): void
    {
        $this->selectedIds = [$emailId];
        $this->moveSelectedFromSpam();
    }

    public function moveSingleToInbox(int $emailId): void
    {
        $this->selectedIds = [$emailId];
        $this->moveSelectedToInbox();
    }

    public function goToPreviousPage(): void
    {
        if ($this->paginatedSenderGroups()->onFirstPage())
        {
            return;
        }

        $this->previousPage();
        $this->clearSelection();
    }

    public function goToNextPage(): void
    {
        if (! $this->paginatedSenderGroups()->hasMorePages())
        {
            return;
        }

        $this->nextPage();
        $this->clearSelection();
    }

    public function getFolderCountsProperty(): array
    {
        $team = $this->currentTeam();
        if (! $team)
        {
            return [];
        }

        return $this->inboxService->folderCounts($team);
    }

    public function getPaginationLabelProperty(): string
    {
        return $this->inboxService->paginationLabel($this->paginatedSenderGroups());
    }

    public function getSelectedEmailProperty(): ?array
    {
        if ($this->selectedEmailId === null)
        {
            return null;
        }

        $email = $this->findTeamEmail($this->selectedEmailId);

        return $email ? $this->inboxService->formatForList($email) : null;
    }

    public function getSenderThreadProperty(): array
    {
        if ($this->expandedSenderKey === null)
        {
            return [];
        }

        $team = $this->currentTeam();
        if (! $team)
        {
            return [];
        }

        return $this->inboxService->threadForSender($team, $this->folder, $this->search, $this->expandedSenderKey);
    }

    public function render()
    {
        return view('livewire.mail-inbox', [
            'senderGroupsPage' => $this->paginatedSenderGroups(),
            'folderCounts' => $this->folderCounts,
            'paginationLabel' => $this->paginationLabel,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginatedSenderGroups(): LengthAwarePaginator
    {
        $team = $this->currentTeam();
        if (! $team)
        {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, MailInboxService::PER_PAGE);
        }

        return $this->inboxService->paginateGrouped($team, $this->folder, $this->search, $this->getPage());
    }

    private function bulkMarkRead(bool $read): void
    {
        $team = $this->currentTeam();
        if (! $team || $this->selectedIds === [])
        {
            return;
        }

        $this->inboxService->markRead($team, $this->selectedIds, $read);
        $this->flashStatus(
            $read ? __('Marcados como leídos.') : __('Marcados como no leídos.'),
            'success',
        );
        $this->clearSelection();
    }

    private function markAllInCurrentFolderRead(): void
    {
        $team = $this->currentTeam();
        if (! $team)
        {
            return;
        }

        $updated = $this->inboxService->markAllReadInFolder($team, $this->folder, true);
        if ($updated === 0)
        {
            $this->flashStatus(__('No hay correos sin leer en esta carpeta.'), 'success');

            return;
        }

        $this->flashStatus(__('Marcados como leídos.'), 'success');
        $this->clearSelection();
    }

    private function markAllInCurrentFolderUnread(): void
    {
        $team = $this->currentTeam();
        if (! $team)
        {
            return;
        }

        $updated = $this->inboxService->markAllReadInFolder($team, $this->folder, false);
        if ($updated === 0)
        {
            $this->flashStatus(__('No hay correos leídos en esta carpeta.'), 'success');

            return;
        }

        $this->flashStatus(__('Marcados como no leídos.'), 'success');
        $this->clearSelection();
    }

    private function afterBulkAction(): void
    {
        if ($this->selectedEmailId !== null && in_array($this->selectedEmailId, $this->selectedIds, true))
        {
            $this->selectedEmailId = null;
        }

        $this->clearSelection();
    }

    private function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->selectAllOnPage = false;
    }

    private function shouldMarkEntireFolderReadState(): bool
    {
        if ($this->selectedIds === [] || $this->selectAllOnPage)
        {
            return true;
        }

        $team = $this->currentTeam();
        if (! $team)
        {
            return false;
        }

        $unreadIds = Email::query()
            ->where('team_id', $team->id)
            ->where('seen', false)
            ->where('folder', EmailFolder::tryFrom($this->folder)?->value ?? EmailFolder::Inbox->value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($unreadIds === [])
        {
            return false;
        }

        return count(array_intersect($unreadIds, $this->selectedIds)) === 0;
    }

    private function findTeamEmail(int $emailId): ?Email
    {
        $team = $this->currentTeam();
        if (! $team)
        {
            return null;
        }

        return Email::query()
            ->where('team_id', $team->id)
            ->whereKey($emailId)
            ->first();
    }

    private function currentTeam(): ?Team
    {
        return auth()->user()?->currentTeam;
    }

    private function flashStatus(string $message, string $type): void
    {
        $this->statusMessage = $message;
        $this->statusType = $type;
    }
}
