<?php

namespace App\Services\Mail;

use App\Enums\MailboxInboundKind;
use App\Models\Contact;
use App\Models\Email;
use App\Models\MessageDelivery;
use App\Models\Team;
use App\Services\InboxList60SlashService;

class MailboxInboundActionService
{
    public function __construct(
        private MailboxInboundClassifier $classifier,
        private InboxList60SlashService $list60,
    ) {}

    public function handle(Email $email): void
    {
        if ($email->inbound_handled_at !== null)
        {
            return;
        }

        $email->loadMissing('mailbox');
        if ($email->mailbox === null || $email->mailbox->user_id !== null)
        {
            $this->markHandled($email);

            return;
        }

        $body = trim((string) ($email->body_text ?: strip_tags((string) $email->body_html)));
        $classification = $this->classifier->classify(
            (string) $email->from_address,
            (string) $email->subject,
            $body,
        );

        $kind = $classification['kind'];
        $address = $classification['address'];
        $reason = $classification['reason'];

        if ($kind === MailboxInboundKind::Ignore || $address === null)
        {
            $this->markHandled($email);

            return;
        }

        $contact = $this->contactForAddress((int) $email->team_id, $address);
        if ($contact === null)
        {
            $this->markHandled($email);

            return;
        }

        if ($kind === MailboxInboundKind::Reply)
        {
            $this->applyReply($email, $contact, $reason);
            $this->markHandled($email);

            return;
        }

        if ($this->deliveryAlreadyClassified($contact))
        {
            $this->markHandled($email);

            return;
        }

        match ($kind)
        {
            MailboxInboundKind::HardBounce => $this->applyHard($contact, $reason),
            MailboxInboundKind::SoftBounce => $this->applySoft($contact, $address, $reason),
            MailboxInboundKind::PolicyBounce => $this->applyPolicy($contact, $address, $reason),
            default => null,
        };

        $this->markHandled($email);
    }

    private function applyHard(Contact $contact, string $reason): void
    {
        $address = trim((string) $contact->email);
        if ($address === '')
        {
            return;
        }

        if (Contact::emailFailureIsPermanent($reason))
        {
            $contact->dropPermanentlyRejectedEmail($reason);
        } else
        {
            $contact->clearStoredEmail();
            $contact->appendNote('Rebote duro: la casilla '.$address.' no existe. '.$reason);
        }

        $this->rememberDelivery($contact, 'hard', $reason, true);
    }

    private function applySoft(Contact $contact, string $address, string $reason): void
    {
        $contact->appendNote('Rebote temporal de '.$address.': '.$reason);
        $this->rememberDelivery($contact, 'soft', $reason, false);
    }

    private function applyPolicy(Contact $contact, string $address, string $reason): void
    {
        $contact->appendNote('El servidor rechazó el mensaje a '.$address.': '.$reason);
        $this->rememberDelivery($contact, 'block', $reason, false);
    }

    private function applyReply(Email $email, Contact $contact, string $excerpt): void
    {
        $note = $excerpt === ''
            ? 'Respondió por email.'
            : 'Respondió por email: '.$excerpt;

        $team = Team::query()->find($email->team_id);
        if ($team instanceof Team && $this->list60->enrollFromMailboxReply($team, $contact, $note))
        {
            return;
        }

        $contact->appendNote($note);
    }

    private function contactForAddress(int $teamId, string $address): ?Contact
    {
        return Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereRaw('LOWER(email) = ?', [strtolower($address)])
            ->first();
    }

    private function deliveryAlreadyClassified(Contact $contact): bool
    {
        $delivery = $this->latestSentDelivery($contact);
        if ($delivery === null)
        {
            return false;
        }

        return $delivery->bounce_type !== null || $delivery->bounced_at !== null;
    }

    private function rememberDelivery(Contact $contact, string $bounceType, string $reason, bool $permanent): void
    {
        $delivery = $this->latestSentDelivery($contact);
        if ($delivery === null || $delivery->bounce_type !== null || $delivery->bounced_at !== null)
        {
            return;
        }

        $delivery->update([
            'bounce_type' => $bounceType,
            'bounce_reason' => $reason,
            'error_message' => $reason,
            'error_type' => 'bounce',
            'bounced_at' => $permanent ? now() : null,
            'status_id' => $permanent ? 4 : $delivery->status_id,
        ]);
    }

    private function latestSentDelivery(Contact $contact): ?MessageDelivery
    {
        return MessageDelivery::query()
            ->where('team_id', $contact->team_id)
            ->where('contact_id', $contact->id)
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->first();
    }

    private function markHandled(Email $email): void
    {
        $email->forceFill(['inbound_handled_at' => now()])->save();
    }
}
