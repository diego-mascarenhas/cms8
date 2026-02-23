<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Email;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessEmailSentimentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $emailId,
    ) {}

    public function handle(): void
    {
        $email = Email::find($this->emailId);

        if (! $email || ! $email->team_id)
        {
            return;
        }

        $address = $this->normalizeEmailAddress($email->from_address);
        if ($address === null)
        {
            return;
        }

        $normalized = strtolower(trim($address));
        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $email->team_id)
            ->whereNotNull('email')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalized])
            ->first();

        if (! $contact)
        {
            return;
        }

        $text = $email->body_text ?: strip_tags((string) $email->body_html);
        if (trim($text) === '')
        {
            return;
        }

        RecordContactSentimentJob::dispatch($contact->id, $text, 'email');
    }

    private function normalizeEmailAddress(string $from): ?string
    {
        $from = trim($from);
        if ($from === '')
        {
            return null;
        }
        if (preg_match('/<([^>]+)>/', $from, $m))
        {
            return trim($m[1]);
        }

        return $from;
    }
}
