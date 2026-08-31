<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Enums\ContactInteractionType;
use App\Exceptions\WhatsAppSessionWindowClosedException;
use App\Helpers\WhatsAppOutboundText;
use App\Jobs\SendNotificationJob;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppCustomerServiceWindow;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ContactOutreachService
{
    public function send(User $user, Contact $contact, string $channel, string $message, ?string $subject = null): ContactInteraction
    {
        if (! Gate::forUser($user)->allows('logInteraction', $contact))
        {
            throw ValidationException::withMessages([
                'message' => [__('app.list60_outreach_error_unauthorized')],
            ]);
        }

        $message = trim($message);
        if ($message === '')
        {
            throw ValidationException::withMessages([
                'message' => [__('validation.required', ['attribute' => __('app.list60_outreach_message')])],
            ]);
        }

        if ($channel === 'whatsapp')
        {
            $this->sendWhatsApp($user, $contact, $message);

            return $this->recordInteraction(
                $user,
                $contact,
                ContactInteractionType::WhatsApp,
                $subject,
                $message,
            );
        }

        if ($channel === 'email')
        {
            $emailSubject = trim((string) $subject);
            if ($emailSubject === '')
            {
                $emailSubject = __('app.list60_outreach_email_default_subject', [
                    'team' => $user->currentTeam?->name ?? config('app.name'),
                ]);
            }

            $plainMessage = trim(strip_tags($message));
            if ($plainMessage === '')
            {
                throw ValidationException::withMessages([
                    'message' => [__('validation.required', ['attribute' => __('app.list60_outreach_message')])],
                ]);
            }

            $this->sendEmail($user, $contact, $emailSubject, $plainMessage);

            return $this->recordInteraction(
                $user,
                $contact,
                ContactInteractionType::Email,
                $emailSubject,
                $plainMessage,
            );
        }

        throw ValidationException::withMessages([
            'channel' => [__('app.list60_outreach_error_invalid_channel')],
        ]);
    }

    private function sendWhatsApp(User $user, Contact $contact, string $message): void
    {
        $phone = $contact->getWhatsAppNumber();
        if (! $phone)
        {
            throw ValidationException::withMessages([
                'channel' => [__('app.list60_outreach_error_no_whatsapp')],
            ]);
        }

        $gateway = $this->resolveWhatsAppGateway($user);
        if (! $gateway->isConfigured())
        {
            throw ValidationException::withMessages([
                'channel' => [__('whatsapp.send.error.generic')],
            ]);
        }

        if ($user->currentTeam?->usesLocalWhatsApp())
        {
            $status = $gateway->getConnectionStatus();
            if (($status['status'] ?? '') !== 'connected')
            {
                throw ValidationException::withMessages([
                    'channel' => [__('whatsapp.send.error.not_connected')],
                ]);
            }
        }

        $outbound = WhatsAppOutboundText::stripInternalQaMarkers(WhatsAppOutboundText::sanitize($message));
        if ($outbound === '')
        {
            throw ValidationException::withMessages([
                'message' => [__('validation.required', ['attribute' => __('app.list60_outreach_message')])],
            ]);
        }

        try
        {
            app(WhatsAppCustomerServiceWindow::class)->assertOpen($phone);
        } catch (WhatsAppSessionWindowClosedException)
        {
            throw ValidationException::withMessages([
                'channel' => [__('whatsapp.send.error.session_window_closed')],
            ]);
        }

        $gateway->sendMessage($phone, $outbound, null, $user->id);
    }

    private function sendEmail(User $user, Contact $contact, string $subject, string $message): void
    {
        $email = $contact->email;
        if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            throw ValidationException::withMessages([
                'channel' => [__('app.list60_outreach_error_no_email')],
            ]);
        }

        $plainMessage = trim($message);
        if ($plainMessage === '')
        {
            throw ValidationException::withMessages([
                'message' => [__('validation.required', ['attribute' => __('app.list60_outreach_message')])],
            ]);
        }

        $typeId = NotificationType::query()->where('name', 'General Message')->value('id') ?? 3;

        $notification = Notification::query()->create([
            'team_id' => $user->currentTeam->id,
            'type_id' => $typeId,
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'subject' => $subject,
            'message' => $plainMessage,
            'metadata' => ['format' => 'plain_text'],
        ]);

        SendNotificationJob::dispatch($notification);
    }

    private function recordInteraction(
        User $user,
        Contact $contact,
        ContactInteractionType $type,
        ?string $subject,
        string $body,
    ): ContactInteraction {
        return ContactInteraction::withoutGlobalScopes()->create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'type' => $type,
            'subject' => $subject !== null && trim($subject) !== '' ? trim($subject) : null,
            'body' => $body,
            'occurred_at' => now(),
        ]);
    }

    private function resolveWhatsAppGateway(User $user): WhatsAppGateway
    {
        if ($user->currentTeam?->usesLocalWhatsApp())
        {
            $team = $user->currentTeam;
            $baseUrl = $team?->getWhatsAppServiceBaseUrl();
            if (is_string($baseUrl) && $baseUrl !== '')
            {
                return new LocalWhatsAppGateway(
                    $baseUrl,
                    config('whatsapp.local.webhook_secret'),
                    $team->id,
                );
            }
        }

        return app(WhatsAppGateway::class);
    }
}
