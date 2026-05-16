<?php

namespace App\Services\Mail;

use App\Enums\EmailFolder;
use App\Models\Email;
use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MailInboxService
{
    public const PER_PAGE = 10;

    public function baseQuery(Team $team): Builder
    {
        return Email::query()
            ->where('team_id', $team->id)
            ->orderByDesc('message_date')
            ->orderByDesc('id');
    }

    public function applyFolderFilter(Builder $query, string $folderKey): Builder
    {
        if ($folderKey === 'starred')
        {
            return $query
                ->where('flagged', true)
                ->whereNotIn('folder', [EmailFolder::Trash->value, EmailFolder::Spam->value]);
        }

        $folder = EmailFolder::tryFrom($folderKey) ?? EmailFolder::Inbox;

        return $query->where('folder', $folder->value);
    }

    public function applySearch(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '')
        {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term)
        {
            $like = '%'.$term.'%';
            $q->where('subject', 'like', $like)
                ->orWhere('from_address', 'like', $like)
                ->orWhere('to_address', 'like', $like)
                ->orWhere('body_text', 'like', $like);
        });
    }

    public function paginate(Team $team, string $folder, string $search, int $page = 1): LengthAwarePaginator
    {
        $query = $this->baseQuery($team);
        $query = $this->applyFolderFilter($query, $folder);
        $query = $this->applySearch($query, $search);

        return $query->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * @return array<string, int>
     */
    public function folderCounts(Team $team): array
    {
        $counts = Email::query()
            ->where('team_id', $team->id)
            ->selectRaw('folder, COUNT(*) as aggregate')
            ->groupBy('folder')
            ->pluck('aggregate', 'folder')
            ->all();

        $starred = Email::query()
            ->where('team_id', $team->id)
            ->where('flagged', true)
            ->whereNotIn('folder', [EmailFolder::Trash->value, EmailFolder::Spam->value])
            ->count();

        $unreadInbox = Email::query()
            ->where('team_id', $team->id)
            ->where('folder', EmailFolder::Inbox->value)
            ->where('seen', false)
            ->count();

        return [
            'inbox' => (int) ($counts[EmailFolder::Inbox->value] ?? 0),
            'sent' => (int) ($counts[EmailFolder::Sent->value] ?? 0),
            'draft' => (int) ($counts[EmailFolder::Draft->value] ?? 0),
            'spam' => (int) ($counts[EmailFolder::Spam->value] ?? 0),
            'trash' => (int) ($counts[EmailFolder::Trash->value] ?? 0),
            'archive' => (int) ($counts[EmailFolder::Archive->value] ?? 0),
            'starred' => $starred,
            'inbox_unread' => $unreadInbox,
        ];
    }

    /**
     * @param  list<int>  $emailIds
     */
    public function markRead(Team $team, array $emailIds, bool $read): int
    {
        return $this->scopedIds($team, $emailIds)->update(['seen' => $read]);
    }

    /**
     * @param  list<int>  $emailIds
     */
    public function moveToFolder(Team $team, array $emailIds, EmailFolder $folder): int
    {
        return $this->scopedIds($team, $emailIds)->update(['folder' => $folder->value]);
    }

    /**
     * @param  list<int>  $emailIds
     */
    public function toggleStar(Team $team, array $emailIds, ?bool $starred = null): int
    {
        $emails = $this->scopedIds($team, $emailIds)->get();
        $updated = 0;

        foreach ($emails as $email)
        {
            $newFlag = $starred ?? ! $email->flagged;
            if ($email->flagged !== $newFlag)
            {
                $email->update(['flagged' => $newFlag]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  list<int>  $emailIds
     */
    public function deletePermanently(Team $team, array $emailIds): int
    {
        return $this->scopedIds($team, $emailIds)->delete();
    }

    public function formatForList(Email $email): array
    {
        return [
            'id' => $email->id,
            'message_id' => $email->message_id,
            'subject' => $email->subject ?? '',
            'from' => $email->from_address,
            'to' => $email->to_address ?? '',
            'date' => $email->message_date?->format('r') ?? '',
            'body' => $email->body_html ?: $email->body_text ?: '',
            'attachments' => [],
            'seen' => $email->seen,
            'flagged' => $email->flagged,
            'folder' => $email->folder instanceof EmailFolder ? $email->folder->value : (string) $email->folder,
        ];
    }

    /**
     * @param  list<int>  $emailIds
     */
    private function scopedIds(Team $team, array $emailIds): Builder
    {
        return Email::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $emailIds);
    }

    public function detectFolderForIncoming(string $fromAddress, string $mailboxUsername): EmailFolder
    {
        $from = strtolower(trim($fromAddress));
        $mailbox = strtolower(trim($mailboxUsername));

        if ($mailbox !== '' && (str_contains($from, $mailbox) || str_contains($from, '<'.$mailbox.'>')))
        {
            return EmailFolder::Sent;
        }

        return EmailFolder::Inbox;
    }

    public function paginationLabel(LengthAwarePaginator $paginator): string
    {
        if ($paginator->total() === 0)
        {
            return '0-0 of 0';
        }

        return $paginator->firstItem().'-'.$paginator->lastItem().' of '.$paginator->total();
    }
}
