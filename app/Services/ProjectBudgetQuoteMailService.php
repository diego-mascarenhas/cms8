<?php

namespace App\Services;

use App\Mail\ProjectBudgetQuoteMail;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class ProjectBudgetQuoteMailService
{
    /**
     * Mark the project as authorized and email the public quote to the CRM contact.
     *
     * @throws RuntimeException
     */
    public function authorizeAndSend(Project $project, User $sender): Project
    {
        if ((int) $project->status_id !== ProjectStatus::STATUS_BUDGETED
            && (int) $project->status_id !== ProjectStatus::STATUS_AUTHORIZED)
        {
            throw new RuntimeException(__('Only budgeted projects can be authorized to send the quote email.'));
        }

        $project->status_id = ProjectStatus::STATUS_AUTHORIZED;
        $project->save();

        return $this->sendQuoteEmail($project->fresh(['enterprise.contacts', 'team']), $sender);
    }

    /**
     * Email the public quote link after an admin moves the project to Authorized.
     *
     * @throws RuntimeException
     */
    public function sendQuoteEmail(Project $project, User $sender): Project
    {
        $project->loadMissing(['enterprise.contacts', 'team']);

        if ((int) $project->status_id !== ProjectStatus::STATUS_AUTHORIZED)
        {
            throw new RuntimeException(__('Only authorized projects can send the quote email.'));
        }

        if (data_get($project->data, 'budget_client_response.status') === 'accepted')
        {
            throw new RuntimeException(__('This quote was already answered.'));
        }

        $previewToken = trim((string) data_get($project->data, 'budget_preview_token', ''));
        if ($previewToken === '')
        {
            throw new RuntimeException(__('Generate the budget preview before authorizing the quote.'));
        }

        $recipient = $project->enterprise?->quoteContact();
        $recipientName = $recipient
            ? trim($recipient->name.' '.(string) ($recipient->surname ?? ''))
            : '';
        $recipientEmail = trim((string) ($recipient?->email ?? ''));
        if ($recipient === null || $recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))
        {
            throw new RuntimeException(__('The enterprise contact does not have a valid email address.'));
        }

        $trackingToken = Str::random(48);
        $previewUrl = route('project.budget-preview', $previewToken);
        $data = $project->data ?? [];
        $data['budget_email'] = [
            'tracking_token' => $trackingToken,
            'to_email' => $recipientEmail,
            'to_name' => $recipientName !== '' ? $recipientName : $recipientEmail,
            'contact_id' => $recipient->id,
            'sent_at' => now()->toIso8601String(),
            'opened_at' => null,
            'clicked_at' => null,
            'sent_by' => $sender->id,
        ];

        $project->data = $data;
        $project->save();

        $mail = new ProjectBudgetQuoteMail(
            project: $project->fresh(['enterprise', 'team']),
            recipientName: $recipientName !== '' ? $recipientName : $recipientEmail,
            previewUrl: $previewUrl,
            trackingToken: $trackingToken,
        );

        $team = $project->team;
        if ($team)
        {
            $fromAddress = trim((string) ($team->getSetting('mail_from_address') ?? ''));
            $fromName = trim((string) ($team->getSetting('mail_from_name') ?? ''));
            if ($fromAddress !== '')
            {
                $mail->from($fromAddress, $fromName !== '' ? $fromName : (string) $team->name);
            }
        }

        Mail::to($recipientEmail, $recipientName !== '' ? $recipientName : null)->send($mail);

        return $project->fresh(['status', 'enterprise.contacts']);
    }

    public function markOpened(string $trackingToken): bool
    {
        $project = $this->findByTrackingToken($trackingToken);
        if (! $project)
        {
            return false;
        }

        $data = $project->data ?? [];
        $email = is_array($data['budget_email'] ?? null) ? $data['budget_email'] : [];
        if (empty($email['opened_at']))
        {
            $email['opened_at'] = now()->toIso8601String();
            $data['budget_email'] = $email;
            $project->data = $data;
            $project->save();
        }

        return true;
    }

    public function markClicked(string $trackingToken): ?string
    {
        $project = $this->findByTrackingToken($trackingToken);
        if (! $project)
        {
            return null;
        }

        $data = $project->data ?? [];
        $email = is_array($data['budget_email'] ?? null) ? $data['budget_email'] : [];
        if (empty($email['opened_at']))
        {
            $email['opened_at'] = now()->toIso8601String();
        }
        if (empty($email['clicked_at']))
        {
            $email['clicked_at'] = now()->toIso8601String();
        }
        $data['budget_email'] = $email;
        $project->data = $data;
        $project->save();

        $previewToken = trim((string) data_get($project->data, 'budget_preview_token', ''));

        return $previewToken !== '' ? route('project.budget-preview', $previewToken) : null;
    }

    public function findByTrackingToken(string $trackingToken): ?Project
    {
        $trackingToken = trim($trackingToken);
        if ($trackingToken === '')
        {
            return null;
        }

        return Project::withoutGlobalScopes()
            ->where('data->budget_email->tracking_token', $trackingToken)
            ->first();
    }
}
