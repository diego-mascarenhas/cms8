<?php

namespace App\Services;

use App\Mail\CalendarEventInvitationMail;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Team;
use App\Support\CalendarEventDateTimeParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CalendarEventInvitationService
{
    /**
     * @param  list<int>|null  $onlyContactIds
     */
    public function notify(CalendarEvent $event, string $kind, ?array $onlyContactIds = null): int
    {
        $event->loadMissing('guests');
        $guests = $event->guests;
        if ($onlyContactIds !== null)
        {
            $allowed = array_map('intval', $onlyContactIds);
            $guests = $guests->filter(fn (Contact $contact): bool => in_array((int) $contact->id, $allowed, true));
        }

        $sender = $this->sender($event);
        if ($sender === null)
        {
            return 0;
        }

        $when = $this->scheduleLabel($event);
        $sent = 0;
        $seen = [];
        $guestEmails = [];

        foreach ($guests as $guest)
        {
            $email = strtolower(trim((string) $guest->email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $guestEmails[] = $email;
            }
        }

        $copies = $this->administratorCopies($event, $guestEmails);
        $copied = false;

        foreach ($guests as $guest)
        {
            $email = strtolower(trim((string) $guest->email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email]))
            {
                continue;
            }

            $seen[$email] = true;
            $name = trim((string) $guest->name.' '.(string) ($guest->surname ?? ''));

            try
            {
                $message = Mail::to($email, $name !== '' ? $name : null);
                if (! $copied && $copies !== [])
                {
                    $message->bcc($copies);
                    $copied = true;
                }

                $message->send(new CalendarEventInvitationMail(
                    guestName: $name,
                    title: (string) $event->title,
                    when: $when,
                    url: filled($event->url) ? (string) $event->url : null,
                    kind: $kind,
                    fromAddress: $sender['from_address'],
                    fromName: $sender['from_name'],
                ));
                $sent++;
            } catch (\Throwable $exception)
            {
                Log::warning('Calendar invitation email failed', [
                    'event_id' => $event->id,
                    'email' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * @param  list<string>  $except
     * @return list<string>
     */
    private function administratorCopies(CalendarEvent $event, array $except): array
    {
        $team = Team::query()->find($event->team_id);
        if (! $team instanceof Team)
        {
            return [];
        }

        $skip = array_fill_keys($except, true);
        $copies = [];

        foreach ($team->allUsers() as $user)
        {
            if (! $user->canManageTeam($team))
            {
                continue;
            }

            $email = strtolower(trim((string) $user->email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || isset($skip[$email]) || isset($copies[$email]))
            {
                continue;
            }

            $copies[$email] = true;
        }

        return array_keys($copies);
    }

    /**
     * @return array{from_name: string, from_address: string}|null
     */
    private function sender(CalendarEvent $event): ?array
    {
        $team = Team::query()->find($event->team_id);
        $sender = $team?->getCommunicationEmailSender() ?? ['from_name' => '', 'from_address' => ''];
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

    private function scheduleLabel(CalendarEvent $event): string
    {
        $timezone = CalendarEventDateTimeParser::wallClockTimezone();
        $start = $event->start->copy()->timezone($timezone)->locale('es');
        $end = $event->end->copy()->timezone($timezone)->locale('es');

        if ($event->all_day)
        {
            return ucfirst($start->isoFormat('dddd D [de] MMMM [de] YYYY'));
        }

        $label = ucfirst($start->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm'));
        if ($start->isSameDay($end))
        {
            return $label.' – '.$end->format('H:i');
        }

        return $label.' – '.ucfirst($end->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm'));
    }
}
