<?php

namespace App\Services\Tickets;

use App\Mail\TicketActivityMail;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TicketActivityMailer
{
    public function ticketOpened(Ticket $ticket): void
    {
        $ticket->loadMissing(['user', 'team']);

        $this->send(
            $ticket,
            $this->staffRecipients($ticket, (int) $ticket->user_id),
            'Nuevo ticket #'.$ticket->id,
            trim((string) $ticket->user?->name) !== '' ? $ticket->user->name : 'Un cliente',
            'abrió este ticket.',
            (string) $ticket->description,
            true,
        );
    }

    public function replyAdded(TicketResponse $response): void
    {
        if ($response->is_internal_note)
        {
            return;
        }

        $response->loadMissing(['user', 'ticket.user', 'ticket.team', 'ticket.assignedTo']);
        $ticket = $response->ticket;
        if (! $ticket instanceof Ticket)
        {
            return;
        }

        $authorId = (int) $response->user_id;
        $authorName = trim((string) $response->user?->name) !== '' ? $response->user->name : 'Alguien';
        $clientWrote = $authorId === (int) $ticket->user_id;

        $recipients = $clientWrote
            ? $this->staffRecipients($ticket, $authorId)
            : $this->clientRecipient($ticket, $authorId);

        $this->send(
            $ticket,
            $recipients,
            'Respuesta en el ticket #'.$ticket->id,
            $authorName,
            'respondió en este ticket.',
            (string) $response->message,
            $clientWrote,
        );
    }

    /**
     * @param  list<string>  $recipients
     */
    private function send(
        Ticket $ticket,
        array $recipients,
        string $heading,
        string $actorName,
        string $action,
        string $body,
        bool $forStaff,
    ): void
    {
        if ($recipients === [])
        {
            return;
        }

        $sender = $this->sender($ticket->team);
        if ($sender === null)
        {
            return;
        }

        $subject = Str::limit(trim($ticket->subject), 120, '');
        $subjectLine = $heading.($subject !== '' ? ': '.$subject : '');

        try
        {
            Mail::to($recipients)->send(new TicketActivityMail(
                subjectLine: $subjectLine,
                heading: $heading,
                intro: $actorName.' '.$action,
                excerpt: $this->excerpt($body),
                url: $forStaff ? $this->staffUrl($ticket) : $this->clientUrl($ticket),
                fromAddress: $sender['from_address'],
                fromName: $sender['from_name'],
            ));
        } catch (\Throwable $exception)
        {
            Log::warning('Ticket activity email failed', [
                'ticket_id' => $ticket->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function staffRecipients(Ticket $ticket, int $exceptUserId): array
    {
        $team = $ticket->team;
        if (! $team instanceof Team)
        {
            return [];
        }

        $recipients = [];
        foreach ($team->allUsers() as $user)
        {
            if (! $user instanceof User || ! $user->canManageTeam($team))
            {
                continue;
            }

            $this->rememberRecipient($recipients, $user, $exceptUserId);
        }

        $assignee = $ticket->assignedTo;
        if ($assignee instanceof User)
        {
            $this->rememberRecipient($recipients, $assignee, $exceptUserId);
        }

        return array_keys($recipients);
    }

    /**
     * @return list<string>
     */
    private function clientRecipient(Ticket $ticket, int $exceptUserId): array
    {
        $owner = $ticket->user;
        if (! $owner instanceof User)
        {
            return [];
        }

        $recipients = [];
        $this->rememberRecipient($recipients, $owner, $exceptUserId);

        return array_keys($recipients);
    }

    /**
     * @param  array<string, true>  $recipients
     */
    private function rememberRecipient(array &$recipients, User $user, int $exceptUserId): void
    {
        if ((int) $user->id === $exceptUserId)
        {
            return;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            return;
        }

        $recipients[$email] = true;
    }

    /**
     * @return array{from_name: string, from_address: string}|null
     */
    private function sender(?Team $team): ?array
    {
        $sender = $team instanceof Team ? $team->getCommunicationEmailSender() : ['from_name' => '', 'from_address' => ''];
        $address = $sender['from_address'] !== ''
            ? $sender['from_address']
            : trim((string) config('mail.from.address'));
        $name = $sender['from_name'] !== ''
            ? $sender['from_name']
            : trim((string) config('mail.from.name'));

        if ($address === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL))
        {
            return null;
        }

        return [
            'from_name' => $name !== '' ? $name : $address,
            'from_address' => $address,
        ];
    }

    private function staffUrl(Ticket $ticket): string
    {
        return route('ticket.show', $ticket->id);
    }

    private function clientUrl(Ticket $ticket): string
    {
        $portal = rtrim((string) config('services.revisionalpha.url'), '/');
        if ($portal === '')
        {
            return $this->staffUrl($ticket);
        }

        return $portal.'/cms/tickets/'.$ticket->id;
    }

    private function excerpt(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        if ($text === '')
        {
            return 'Sin mensaje.';
        }

        return Str::limit($text, 400);
    }
}
