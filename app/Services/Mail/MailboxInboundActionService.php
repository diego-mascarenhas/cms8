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

    public function handle(Email $email): string
    {
        if ($email->inbound_handled_at !== null)
        {
            return '';
        }

        $email->loadMissing('mailbox');
        if ($email->mailbox === null || $email->mailbox->user_id !== null)
        {
            $this->markHandled($email);

            return '';
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

            return '';
        }

        $contact = $this->contactForAddress((int) $email->team_id, $address);
        if ($contact === null)
        {
            $this->markHandled($email);

            return '';
        }

        if ($kind === MailboxInboundKind::Reply)
        {
            $summary = $this->applyReply($email, $contact, $reason);
            $this->markHandled($email);

            return $summary;
        }

        if ($this->deliveryAlreadyClassified($contact))
        {
            $this->markHandled($email);

            return '';
        }

        $summary = match ($kind)
        {
            MailboxInboundKind::HardBounce => $this->applyHard($contact, $reason),
            MailboxInboundKind::SoftBounce => $this->applySoft($contact, $address, $reason),
            MailboxInboundKind::PolicyBounce => $this->applyPolicy($contact, $address, $reason),
            default => '',
        };

        $this->markHandled($email);

        return $summary;
    }

    private function applyHard(Contact $contact, string $reason): string
    {
        $address = trim((string) $contact->email);
        if ($address === '')
        {
            return '';
        }

        $note = 'Rebote duro: la casilla '.$address.' no existe. '.$reason;
        if (Contact::emailFailureIsPermanent($reason))
        {
            $contact->dropPermanentlyRejectedEmail($reason);
        } else
        {
            $contact->clearStoredEmail();
            $contact->appendNote($note);
        }

        $this->rememberDelivery($contact, 'hard', $reason, true);

        return $note;
    }

    private function applySoft(Contact $contact, string $address, string $reason): string
    {
        $note = 'Rebote temporal de '.$address.': '.$reason;
        $contact->appendNote($note);
        $this->rememberDelivery($contact, 'soft', $reason, false);

        return $note;
    }

    private function applyPolicy(Contact $contact, string $address, string $reason): string
    {
        $note = 'El servidor rechazó el mensaje a '.$address.': '.$reason;
        $contact->appendNote($note);
        $this->rememberDelivery($contact, 'block', $reason, false);

        return $note;
    }

    private function applyReply(Email $email, Contact $contact, string $excerpt): string
    {
        $note = $excerpt === ''
            ? 'Respondió por email.'
            : 'Respondió por email: '.$excerpt;

        $team = Team::query()->find($email->team_id);
        if ($team instanceof Team && $this->list60->enrollFromMailboxReply($team, $contact, $note))
        {
            return 'Se guardó la respuesta y el contacto entró en la lista de seguimiento.';
        }

        $contact->appendNote($note);

        return 'Se guardó la respuesta en el contacto.';
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
