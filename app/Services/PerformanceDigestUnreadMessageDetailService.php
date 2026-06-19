<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Team;
use App\Support\PerformanceDigestReplyParser;
use Illuminate\Support\Str;

class PerformanceDigestUnreadMessageDetailService
{
    private const MAX_ITEMS = 25;

    public function __construct(
        private readonly DailyTeamDigestMetricsCollector $digestCollector,
        private readonly PerformanceDigestContactInvoiceContextService $invoiceContextService,
        private readonly PerformanceDigestCalendarSchedulingContextService $calendarSchedulingService,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     channel: string,
     *     contact_name: string,
     *     contact_label: string,
     *     preview: string,
     *     received_at: string,
     *     response_hint: string,
     *     suggestion: string,
     *     schedule_action: string|null,
     *     schedule_recipient: string,
     *     schedule_subject: string|null,
     *     action_url: string|null,
     *     action_label: string|null
     * }>
     */
    public function forHighlightKey(string $key, Team $team): array
    {
        return match ($key)
        {
            'whatsapp_unread', 'whatsapp_inbound' => $this->whatsappUnreadMessages($team, $key === 'whatsapp_inbound'),
            'email_unread' => $this->unreadEmails($team),
            default => [],
        };
    }

    /**
     * @return list<array{
     *     id: int,
     *     channel: string,
     *     contact_name: string,
     *     contact_label: string,
     *     preview: string,
     *     received_at: string,
     *     response_hint: string,
     *     suggestion: string,
     *     action_url: string|null,
     *     action_label: string|null
     * }>
     */
    private function whatsappUnreadMessages(Team $team, bool $recentInboundOnly): array
    {
        if (! $team->hasModule('chat') || ! $team->getWhatsAppFrom())
        {
            return [];
        }

        $query = $this->digestCollector->whatsappConversationQueryForTeam($team)
            ->where('direction', 'inbound');

        if ($recentInboundOnly)
        {
            $query->where('created_at', '>=', now()->subDay());
        } else
        {
            $query->where('status', 'received');
        }

        $messages = $query
            ->orderByDesc('created_at')
            ->limit(self::MAX_ITEMS)
            ->get();

        $details = [];
        foreach ($messages as $message)
        {
            $details[] = $this->mapWhatsAppMessage($message, $team);
        }

        return $details;
    }

    /**
     * @return array{
     *     id: int,
     *     channel: string,
     *     contact_name: string,
     *     contact_label: string,
     *     preview: string,
     *     received_at: string,
     *     response_hint: string,
     *     suggestion: string,
     *     action_url: string|null,
     *     action_label: string|null
     * }
     */
    private function mapWhatsAppMessage(Conversation $message, Team $team): array
    {
        $phoneDigits = $this->normalizePhoneDigits((string) $message->from);
        $contact = $this->findContactByPhone($team->id, $phoneDigits);
        $firstName = $this->resolveContactFirstName($contact);
        $contactName = $firstName ?? (string) __('app.performance_digest_message_unknown_contact');
        $body = $this->normalizeMessageBodyForDisplay(trim((string) $message->body));
        $preview = $body !== '' ? Str::limit($body, 500) : (string) __('app.performance_digest_message_empty_body');
        $invoiceContext = $this->invoiceContextService->forContactAndMessage($team, $contact, $body);
        $calendarContext = $invoiceContext === null
            ? $this->calendarSchedulingService->forMessage($team, $contact, $body)
            : null;
        $suggestion = $this->buildWhatsAppSuggestion($contact, $body, $team, $invoiceContext, $calendarContext);

        return array_merge([
            'id' => (int) $message->id,
            'channel' => 'whatsapp',
            'contact_name' => $contactName,
            'contact_label' => $phoneDigits !== ''
                ? ($firstName !== null ? $firstName.' · +'.$phoneDigits : '+'.$phoneDigits)
                : $contactName,
            'preview' => $preview,
            'received_at' => $message->created_at?->format('d/m/Y H:i') ?? '',
            'response_hint' => $this->resolveResponseHint($body, $invoiceContext, $calendarContext),
            'suggestion' => $suggestion,
        ], $this->resolveScheduleMeta(
            channel: 'whatsapp',
            contact: $contact,
            recipientEmail: '',
            phoneDigits: $phoneDigits,
            invoiceContext: $invoiceContext,
            calendarContext: $calendarContext,
            suggestion: $suggestion,
        ));
    }

    /**
     * @return list<array{
     *     id: int,
     *     channel: string,
     *     contact_name: string,
     *     contact_label: string,
     *     preview: string,
     *     received_at: string,
     *     response_hint: string,
     *     suggestion: string,
     *     action_url: string|null,
     *     action_label: string|null
     * }>
     */
    private function unreadEmails(Team $team): array
    {
        if (! $team->hasModule('mailbox'))
        {
            return [];
        }

        $emails = Email::query()
            ->where('team_id', $team->id)
            ->where('seen', false)
            ->orderByDesc('message_date')
            ->limit(self::MAX_ITEMS)
            ->get();

        $details = [];
        foreach ($emails as $email)
        {
            $details[] = $this->mapEmailMessage($email, $team);
        }

        return $details;
    }

    /**
     * @return array{
     *     id: int,
     *     channel: string,
     *     contact_name: string,
     *     contact_label: string,
     *     preview: string,
     *     received_at: string,
     *     response_hint: string,
     *     suggestion: string,
     *     action_url: string|null,
     *     action_label: string|null
     * }
     */
    private function mapEmailMessage(Email $email, Team $team): array
    {
        $parsedFrom = $this->parseEmailFromAddress((string) $email->from_address);
        $contact = $this->findContactByEmail($team->id, $parsedFrom['email']);
        $firstName = $this->resolveContactFirstName($contact)
            ?? $this->resolveFirstNameFromDisplayName($parsedFrom['name']);
        $contactName = $firstName ?? ($parsedFrom['name'] !== '' ? $parsedFrom['name'] : (string) __('app.performance_digest_message_unknown_contact'));
        $subject = trim((string) $email->subject);
        $body = $this->normalizeMessageBodyForDisplay(
            trim(strip_tags((string) ($email->body_text ?: $email->body_html))),
        );
        $preview = $this->buildEmailPreview($subject, $body);
        $contactLabel = $subject !== ''
            ? $contactName.' · '.$subject
            : $contactName;

        $messageText = $body !== '' ? $body : $subject;
        $invoiceContext = $this->invoiceContextService->forContactAndMessage($team, $contact, $messageText);
        $calendarContext = $invoiceContext === null
            ? $this->calendarSchedulingService->forMessage($team, $contact, $messageText)
            : null;
        $suggestion = $this->buildEmailSuggestion($contact, $parsedFrom['name'], $subject, $body, $team, $invoiceContext, $calendarContext);
        $phoneDigits = $this->normalizePhoneDigits((string) ($contact?->phone ?? ''));

        return array_merge([
            'id' => (int) $email->id,
            'channel' => 'email',
            'contact_name' => $contactName,
            'contact_label' => $contactLabel,
            'preview' => $preview,
            'received_at' => $email->message_date?->format('d/m/Y H:i') ?? '',
            'response_hint' => $this->resolveResponseHint($messageText, $invoiceContext, $calendarContext),
            'suggestion' => $suggestion,
        ], $this->resolveScheduleMeta(
            channel: 'email',
            contact: $contact,
            recipientEmail: $parsedFrom['email'],
            phoneDigits: $phoneDigits,
            invoiceContext: $invoiceContext,
            calendarContext: $calendarContext,
            suggestion: $suggestion,
        ));
    }

    private function buildEmailPreview(string $subject, string $body): string
    {
        $subjectLine = $subject !== ''
            ? (string) __('app.performance_digest_email_preview_subject', ['subject' => $subject])
            : '';

        if ($body !== '')
        {
            $excerpt = Str::limit($body, 400);

            return trim($subjectLine."\n\n".$excerpt);
        }

        if ($subjectLine !== '')
        {
            return $subjectLine;
        }

        return (string) __('app.performance_digest_message_empty_body');
    }

    private function normalizeMessageBodyForDisplay(string $body): string
    {
        $body = trim($body);
        if ($body === '')
        {
            return '';
        }

        return $this->prettyPrintJsonIfApplicable($body) ?? $body;
    }

    private function prettyPrintJsonIfApplicable(string $text): ?string
    {
        $candidate = trim($text);
        if ($candidate === '' || (! str_starts_with($candidate, '{') && ! str_starts_with($candidate, '[')))
        {
            return null;
        }

        try
        {
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException)
        {
            return null;
        }

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : null;
    }

    /**
     * @return array{name: string, email: string}
     */
    private function parseEmailFromAddress(string $from): array
    {
        $from = trim($from);
        if ($from === '')
        {
            return ['name' => '', 'email' => ''];
        }

        if (preg_match('/^(.+?)<([^>]+)>$/u', $from, $matches))
        {
            return [
                'name' => trim($matches[1], " \t\n\r\0\x0B\""),
                'email' => strtolower(trim($matches[2])),
            ];
        }

        if (filter_var($from, FILTER_VALIDATE_EMAIL))
        {
            return ['name' => '', 'email' => strtolower($from)];
        }

        return ['name' => $from, 'email' => ''];
    }

    private function resolveFirstNameFromDisplayName(?string $displayName): ?string
    {
        if ($displayName === null || trim($displayName) === '')
        {
            return null;
        }

        $parts = preg_split('/\s+/u', trim($displayName), 2, PREG_SPLIT_NO_EMPTY);
        $first = $parts[0] ?? '';

        if ($first === '' || str_contains($first, '@'))
        {
            return null;
        }

        return mb_convert_case($first, MB_CASE_TITLE, 'UTF-8');
    }

    private function resolveEmailGreeting(?Contact $contact, ?string $senderDisplayName): string
    {
        $firstName = $this->resolveContactFirstName($contact)
            ?? $this->resolveFirstNameFromDisplayName($senderDisplayName);

        if ($firstName !== null)
        {
            return (string) __('app.performance_digest_email_greeting_named', [
                'name' => $firstName,
            ]);
        }

        $hour = (int) now()->format('G');
        if ($hour >= 6 && $hour < 14)
        {
            return (string) __('app.performance_digest_email_greeting_morning');
        }

        return (string) __('app.performance_digest_email_greeting_generic');
    }

    /**
     * @param  array<string, mixed>|null  $invoiceContext
     * @param  array<string, mixed>|null  $calendarContext
     */
    private function buildWhatsAppSuggestion(
        ?Contact $contact,
        string $incomingBody,
        Team $team,
        ?array $invoiceContext = null,
        ?array $calendarContext = null,
    ): string {
        $greeting = $this->resolveWhatsAppGreeting($contact);
        $invoiceContext ??= $this->invoiceContextService->forContactAndMessage($team, $contact, $incomingBody);

        if ($invoiceContext !== null)
        {
            return (string) __('app.performance_digest_whatsapp_reply_invoices_'.$invoiceContext['variant'], array_merge(
                ['greeting' => $greeting],
                $invoiceContext,
            ));
        }

        if ($calendarContext !== null)
        {
            return $this->buildSchedulingReply('whatsapp', $greeting, $calendarContext);
        }

        $hintKey = $this->resolveResponseHintKey($incomingBody);

        return (string) __('app.performance_digest_whatsapp_reply_'.$hintKey, [
            'greeting' => $greeting,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $invoiceContext
     * @param  array<string, mixed>|null  $calendarContext
     * @return array{
     *     schedule_action: string|null,
     *     schedule_recipient: string,
     *     schedule_subject: string|null,
     *     action_url: string|null,
     *     action_label: string|null
     * }
     */
    private function resolveScheduleMeta(
        string $channel,
        ?Contact $contact,
        string $recipientEmail,
        string $phoneDigits,
        ?array $invoiceContext,
        ?array $calendarContext,
        string $suggestion,
    ): array {
        if ($invoiceContext !== null)
        {
            if ($channel === 'email' && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))
            {
                $parsed = PerformanceDigestReplyParser::parseEmailSuggestion($suggestion);

                return [
                    'schedule_action' => 'email',
                    'schedule_recipient' => strtolower($recipientEmail),
                    'schedule_subject' => $parsed['subject'] !== '' ? $parsed['subject'] : null,
                    'action_url' => null,
                    'action_label' => (string) __('app.performance_digest_schedule_email'),
                ];
            }

            $phone = $phoneDigits !== ''
                ? $phoneDigits
                : $this->normalizePhoneDigits((string) ($contact?->phone ?? ''));

            if ($phone === '')
            {
                return [
                    'schedule_action' => null,
                    'schedule_recipient' => '',
                    'schedule_subject' => null,
                    'action_url' => $this->resolveInvoiceActionUrl($invoiceContext),
                    'action_label' => (string) __('app.performance_digest_suggestion_action_invoice'),
                ];
            }

            return [
                'schedule_action' => 'whatsapp',
                'schedule_recipient' => $phone,
                'schedule_subject' => null,
                'action_url' => null,
                'action_label' => (string) __('app.performance_digest_schedule_whatsapp'),
            ];
        }

        if ($channel === 'email' && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))
        {
            $parsed = PerformanceDigestReplyParser::parseEmailSuggestion($suggestion);

            return [
                'schedule_action' => 'email',
                'schedule_recipient' => strtolower($recipientEmail),
                'schedule_subject' => $parsed['subject'] !== '' ? $parsed['subject'] : null,
                'action_url' => null,
                'action_label' => (string) __('app.performance_digest_schedule_email'),
            ];
        }

        if ($channel === 'whatsapp' && $phoneDigits !== '')
        {
            return [
                'schedule_action' => 'whatsapp',
                'schedule_recipient' => $phoneDigits,
                'schedule_subject' => null,
                'action_url' => null,
                'action_label' => (string) __('app.performance_digest_schedule_whatsapp'),
            ];
        }

        if ($calendarContext !== null && ($calendarContext['calendar_url'] ?? null) !== null)
        {
            return [
                'schedule_action' => null,
                'schedule_recipient' => '',
                'schedule_subject' => null,
                'action_url' => $calendarContext['calendar_url'],
                'action_label' => (string) __('app.performance_digest_suggestion_action_calendar'),
            ];
        }

        return [
            'schedule_action' => null,
            'schedule_recipient' => '',
            'schedule_subject' => null,
            'action_url' => null,
            'action_label' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $invoiceContext
     * @param  array<string, mixed>|null  $calendarContext
     */
    private function resolveWhatsAppActionUrl(string $phoneDigits, ?array $invoiceContext, ?array $calendarContext = null): ?string
    {
        if ($invoiceContext !== null)
        {
            return $this->resolveInvoiceActionUrl($invoiceContext);
        }

        if ($calendarContext !== null && ($calendarContext['calendar_url'] ?? null) !== null)
        {
            return $calendarContext['calendar_url'];
        }

        if ($phoneDigits !== '' && \Illuminate\Support\Facades\Route::has('chat.index'))
        {
            return route('chat.index', ['phone' => $phoneDigits]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $invoiceContext
     * @param  array<string, mixed>|null  $calendarContext
     */
    private function resolveEmailActionUrl(?string $composeUrl, ?array $invoiceContext, ?array $calendarContext = null): ?string
    {
        if ($invoiceContext !== null)
        {
            return $this->resolveInvoiceActionUrl($invoiceContext);
        }

        if ($calendarContext !== null && ($calendarContext['calendar_url'] ?? null) !== null)
        {
            return $calendarContext['calendar_url'];
        }

        return $composeUrl;
    }

    /**
     * @param  array<string, mixed>  $invoiceContext
     */
    private function resolveInvoiceActionUrl(array $invoiceContext): ?string
    {
        $invoiceId = (int) ($invoiceContext['primary_invoice_id'] ?? 0);
        if ($invoiceId > 0 && \Illuminate\Support\Facades\Route::has('invoice.show'))
        {
            return route('invoice.show', $invoiceId);
        }

        if (\Illuminate\Support\Facades\Route::has('invoice.index'))
        {
            return route('invoice.index');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $invoiceContext
     * @param  array<string, mixed>|null  $calendarContext
     */
    private function resolveActionLabel(string $channel, ?array $invoiceContext, ?array $calendarContext): string
    {
        if ($invoiceContext !== null)
        {
            return (string) __('app.performance_digest_suggestion_action_invoice');
        }

        if ($calendarContext !== null)
        {
            return (string) __('app.performance_digest_suggestion_action_calendar');
        }

        return $channel === 'email'
            ? (string) __('app.performance_digest_suggestion_action_email_unread')
            : (string) __('app.performance_digest_suggestion_action_whatsapp_unread');
    }

    /**
     * @param  array<string, mixed>  $calendarContext
     */
    private function buildSchedulingReply(string $channel, string $greeting, array $calendarContext): string
    {
        $params = array_merge(['greeting' => $greeting], $calendarContext);
        $templateKey = $this->schedulingReplyTemplateKey($calendarContext);

        $body = (string) __('app.performance_digest_'.$channel.'_reply_'.$templateKey, $params);

        if ($channel === 'email')
        {
            return (string) __('app.performance_digest_email_reply_wrapper', [
                'subject' => (string) __('app.performance_digest_email_reply_scheduling_subject', [
                    'day' => $calendarContext['requested_date_label'],
                ]),
                'body' => $body,
            ]);
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $calendarContext
     */
    /**
     * @param  array<string, mixed>  $invoiceContext
     */
    private function resolveInvoiceEmailSubject(array $invoiceContext): string
    {
        $variant = (string) ($invoiceContext['variant'] ?? '');

        return match ($variant)
        {
            'billing_up_to_date' => (string) __('app.performance_digest_email_reply_billing_subject_history', [
                'enterprise' => $invoiceContext['enterprise_name'],
            ]),
            'billing_no_invoices' => (string) __('app.performance_digest_email_reply_billing_subject_info'),
            'billing_no_enterprise' => (string) __('app.performance_digest_email_reply_billing_subject_info'),
            default => (string) __('app.performance_digest_email_reply_invoice_subject', [
                'number' => $invoiceContext['invoice_number'],
            ]),
        };
    }

    private function schedulingReplyTemplateKey(array $calendarContext): string
    {
        if (! ($calendarContext['has_free_slots'] ?? false))
        {
            return 'scheduling_no_slots';
        }

        if (($calendarContext['booked_start'] ?? '') !== '')
        {
            return 'scheduling_booked';
        }

        return 'scheduling_slots';
    }

    private function resolveContactFirstName(?Contact $contact): ?string
    {
        if ($contact === null)
        {
            return null;
        }

        $firstName = trim((string) $contact->name);
        if ($firstName === '')
        {
            return null;
        }

        return mb_convert_case($firstName, MB_CASE_TITLE, 'UTF-8');
    }

    private function resolveWhatsAppGreeting(?Contact $contact): string
    {
        $firstName = $this->resolveContactFirstName($contact);
        if ($firstName !== null)
        {
            return (string) __('app.performance_digest_whatsapp_greeting_named', [
                'name' => $firstName,
            ]);
        }

        $hour = (int) now()->format('G');
        if ($hour >= 6 && $hour < 14)
        {
            return (string) __('app.performance_digest_whatsapp_greeting_morning');
        }

        return (string) __('app.performance_digest_whatsapp_greeting_generic');
    }

    private function resolveResponseHintKey(string $text): string
    {
        $normalized = mb_strtolower($text);

        if (preg_match('/\?|¿/', $text))
        {
            return 'question';
        }

        if (preg_match('/\b(gracias|thank)/u', $normalized))
        {
            return 'thanks';
        }

        if (preg_match('/\b(urgente|urgent|asap|ya|hoy|mañana|manana|llamada|call)\b/u', $normalized))
        {
            return 'urgent';
        }

        if (preg_match('/\b(precio|presupuesto|coste|cost|factura|invoice)\b/u', $normalized))
        {
            return 'commercial';
        }

        return 'default';
    }

    /**
     * @param  array<string, mixed>|null  $invoiceContext
     * @param  array<string, mixed>|null  $calendarContext
     */
    private function buildEmailSuggestion(
        ?Contact $contact,
        ?string $senderDisplayName,
        string $subject,
        string $body,
        Team $team,
        ?array $invoiceContext = null,
        ?array $calendarContext = null,
    ): string {
        $greeting = $this->resolveEmailGreeting($contact, $senderDisplayName);
        $messageText = $body !== '' ? $body : $subject;
        $invoiceContext ??= $this->invoiceContextService->forContactAndMessage($team, $contact, $messageText);

        if ($invoiceContext !== null)
        {
            $subjectLine = $this->resolveInvoiceEmailSubject($invoiceContext);
            $replyBody = (string) __('app.performance_digest_email_reply_invoices_'.$invoiceContext['variant'], array_merge(
                ['greeting' => $greeting],
                $invoiceContext,
            ));
        } elseif ($calendarContext !== null)
        {
            return $this->buildSchedulingReply('email', $greeting, $calendarContext);
        } else
        {
            $hintKey = $this->resolveResponseHintKey($body !== '' ? $body : $subject);
            $subjectLine = $subject !== '' ? $subject : (string) __('app.performance_digest_email_reply_no_subject');
            $replyBody = (string) __('app.performance_digest_email_reply_'.$hintKey, [
                'greeting' => $greeting,
            ]);
        }

        return (string) __('app.performance_digest_email_reply_wrapper', [
            'subject' => $subjectLine,
            'body' => $replyBody,
        ]);
    }

    private function findContactByEmail(int $teamId, string $email): ?Contact
    {
        if ($email === '')
        {
            return null;
        }

        return Contact::query()
            ->where('team_id', $teamId)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $invoiceContext
     * @param  array<string, mixed>|null  $calendarContext
     */
    private function resolveResponseHint(string $text, ?array $invoiceContext = null, ?array $calendarContext = null): string
    {
        if ($invoiceContext !== null)
        {
            $hintKey = 'app.performance_digest_response_hint_invoices_'.$invoiceContext['variant'];
            if (\Illuminate\Support\Facades\Lang::has($hintKey))
            {
                return (string) __($hintKey, $invoiceContext);
            }

            return (string) __('app.performance_digest_response_hint_commercial');
        }

        if ($calendarContext !== null)
        {
            if (($calendarContext['booked_start'] ?? '') === '')
            {
                return (string) __('app.performance_digest_response_hint_scheduling_no_slots', $calendarContext);
            }

            return (string) __('app.performance_digest_response_hint_scheduling', $calendarContext);
        }

        $normalized = mb_strtolower($text);

        if (preg_match('/\?|¿/', $text))
        {
            return (string) __('app.performance_digest_response_hint_question');
        }

        if (preg_match('/\b(gracias|thank)/u', $normalized))
        {
            return (string) __('app.performance_digest_response_hint_thanks');
        }

        if (preg_match('/\b(urgente|urgent|asap|ya|hoy)\b/u', $normalized))
        {
            return (string) __('app.performance_digest_response_hint_urgent');
        }

        if (preg_match('/\b(precio|presupuesto|coste|cost|factura|invoice)\b/u', $normalized))
        {
            return (string) __('app.performance_digest_response_hint_commercial');
        }

        return (string) __('app.performance_digest_response_hint_default');
    }

    private function normalizePhoneDigits(string $raw): string
    {
        $normalized = explode(':', $raw)[0];

        return preg_replace('/[^0-9]/', '', $normalized) ?? '';
    }

    private function findContactByPhone(int $teamId, string $digits): ?Contact
    {
        if ($digits === '')
        {
            return null;
        }

        return Contact::query()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($digits): void
            {
                $query->where('phone', $digits);
                if (strlen($digits) === 11 && str_starts_with($digits, '34'))
                {
                    $query->orWhere('phone', substr($digits, -9));
                }
                if (strlen($digits) === 9)
                {
                    $query->orWhere('phone', '34'.$digits);
                }
            })
            ->orderBy('id')
            ->first();
    }
}
