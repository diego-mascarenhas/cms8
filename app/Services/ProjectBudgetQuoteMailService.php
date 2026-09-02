<?php

namespace App\Services;

use App\Helpers\DnsHelper;
use App\Mail\ProjectBudgetQuoteMail;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
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

        $project->loadMissing(['enterprise.contacts', 'team']);
        $this->assertReadyToSend($project);

        $previousStatus = (int) $project->status_id;
        $project->status_id = ProjectStatus::STATUS_AUTHORIZED;
        $project->save();

        try
        {
            return $this->sendQuoteEmail($project->fresh(['enterprise.contacts', 'team']), $sender);
        } catch (RuntimeException $e)
        {
            $project->refresh();
            if ((int) $project->status_id === ProjectStatus::STATUS_AUTHORIZED
                && empty(data_get($project->data, 'budget_email.sent_at')))
            {
                $project->status_id = $previousStatus === ProjectStatus::STATUS_AUTHORIZED
                    ? ProjectStatus::STATUS_BUDGETED
                    : $previousStatus;
                $project->save();
            }

            throw $e;
        }
    }

    /**
     * Send the public quote after the visitor confirms the funnel scope.
     * Leaves the project as a budget request when the team sender is not ready.
     */
    public function trySendAfterFunnelSubmit(Project $project): bool
    {
        $project->loadMissing(['enterprise.contacts', 'team.settings', 'team.owner']);

        $sender = $project->team?->owner;
        if (! $sender instanceof User)
        {
            return false;
        }

        $previousStatus = (int) $project->status_id;
        if ($previousStatus === ProjectStatus::STATUS_BUDGET)
        {
            $project->status_id = ProjectStatus::STATUS_BUDGETED;
            $project->save();
        }

        try
        {
            $this->authorizeAndSend($project->fresh(['enterprise.contacts', 'team']), $sender);

            return true;
        } catch (RuntimeException $e)
        {
            if ($previousStatus === ProjectStatus::STATUS_BUDGET)
            {
                $project->refresh();
                if ((int) $project->status_id === ProjectStatus::STATUS_BUDGETED)
                {
                    $project->status_id = ProjectStatus::STATUS_BUDGET;
                    $project->save();
                }
            }

            Log::warning('Funnel quote email skipped', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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

        $ready = $this->assertReadyToSend($project);
        $recipient = $ready['recipient'];
        $recipientName = $ready['recipient_name'];
        $recipientEmail = $ready['recipient_email'];
        $previewToken = $ready['preview_token'];
        $from = $ready['from'];

        $trackingToken = Str::random(48);
        $previewUrl = \App\Support\BudgetPreviewUrl::forToken($previewToken)
            ?? route('project.budget-preview', $previewToken);

        $mail = new ProjectBudgetQuoteMail(
            project: $project->fresh(['enterprise', 'team']),
            recipientName: $recipientName !== '' ? $recipientName : $recipientEmail,
            previewUrl: $previewUrl,
            trackingToken: $trackingToken,
        );

        $mail->from($from['from_address'], $from['from_name']);

        $mailer = (string) config('mail.default');
        if (! app()->runningUnitTests() && in_array($mailer, ['log', 'array'], true))
        {
            throw new RuntimeException(__('Mail is set to :mailer, so the quote was not delivered to an inbox. Configure SMTP and try again.', [
                'mailer' => $mailer,
            ]));
        }

        try
        {
            $outgoing = Mail::to($recipientEmail, $recipientName !== '' ? $recipientName : null);
            $copyTo = $this->senderCopyAddress($from['from_address'], $recipientEmail);
            if ($copyTo !== null)
            {
                $outgoing->bcc($copyTo);
            }
            $outgoing->send($mail);
        } catch (\Throwable $e)
        {
            Log::error('Project budget quote email failed', [
                'project_id' => $project->id,
                'to' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(__('The quote email could not be sent. Check mail settings and try again.'), 0, $e);
        }

        $data = $project->data ?? [];
        $data['budget_email'] = [
            'tracking_token' => $trackingToken,
            'to_email' => $recipientEmail,
            'to_name' => $recipientName !== '' ? $recipientName : $recipientEmail,
            'contact_id' => $recipient->id,
            'sent_at' => now()->toIso8601String(),
            'opened_at' => null,
            'clicked_at' => null,
            'visited_count' => 0,
            'sent_by' => $sender->id,
            'bcc_email' => $copyTo,
        ];

        $project->data = $data;
        $project->save();

        return $project->fresh(['status', 'enterprise.contacts']);
    }

    /**
     * @return array{
     *     recipient: \App\Models\Contact,
     *     recipient_name: string,
     *     recipient_email: string,
     *     preview_token: string,
     *     from: array{from_name: string, from_address: string}
     * }
     */
    private function assertReadyToSend(Project $project): array
    {
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

        $team = $project->team;
        if (! $team)
        {
            throw new RuntimeException(__('Configure the quote sender in Settings before sending.'));
        }

        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        $from = $team->getTeamEmailSender();
        if (! $team->hasTeamEmailSenderConfigured())
        {
            throw new RuntimeException(__('Configure the quote sender in Settings before sending.'));
        }

        $dnsStatus = DnsHelper::checkEmailDomainConfiguration($from['from_address']);
        $bypassDns = app()->isLocal() || app()->runningUnitTests();
        if (! DnsHelper::canSendBroadcastFromUi($dnsStatus, true, $bypassDns))
        {
            throw new RuntimeException(__('app.email_spf_record_required_include'));
        }

        return [
            'recipient' => $recipient,
            'recipient_name' => $recipientName !== '' ? $recipientName : $recipientEmail,
            'recipient_email' => $recipientEmail,
            'preview_token' => $previewToken,
            'from' => $from,
        ];
    }

    private function senderCopyAddress(string $fromAddress, string $recipientEmail): ?string
    {
        $copyTo = trim($fromAddress);
        if ($copyTo === '' || ! filter_var($copyTo, FILTER_VALIDATE_EMAIL))
        {
            return null;
        }

        if (strcasecmp($copyTo, trim($recipientEmail)) === 0)
        {
            return null;
        }

        return $copyTo;
    }

    /**
     * @param  array<string, mixed>  $email
     */
    private function recordBudgetVisit(array &$email): bool
    {
        $now = now();
        $count = max(0, (int) ($email['visited_count'] ?? 0));
        $lastRaw = $email['last_visited_at'] ?? null;
        if ($count > 0 && is_string($lastRaw) && $lastRaw !== '')
        {
            try
            {
                $last = Carbon::parse($lastRaw);
                if ($last->diffInMinutes($now) < 5)
                {
                    return false;
                }
            } catch (\Throwable)
            {
            }
        }

        $at = $now->toIso8601String();
        $email['visited_count'] = $count + 1;
        $email['last_visited_at'] = $at;
        if (empty($email['opened_at']))
        {
            $email['opened_at'] = $at;
        }
        if (empty($email['clicked_at']))
        {
            $email['clicked_at'] = $at;
        }

        return true;
    }

    public function markPreviewVisited(Project $project): bool
    {
        $data = $project->data ?? [];
        $email = is_array($data['budget_email'] ?? null) ? $data['budget_email'] : [];
        if (empty($email['sent_at']))
        {
            return false;
        }

        if (! $this->recordBudgetVisit($email))
        {
            return true;
        }

        $data['budget_email'] = $email;
        $project->data = $data;
        $project->save();

        return true;
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
        if ($this->recordBudgetVisit($email))
        {
            $data['budget_email'] = $email;
            $project->data = $data;
            $project->save();
        }

        $previewToken = trim((string) data_get($project->data, 'budget_preview_token', ''));

        return $previewToken !== ''
            ? (\App\Support\BudgetPreviewUrl::forToken($previewToken) ?? route('project.budget-preview', $previewToken))
            : null;
    }

    public function countSentForTeam(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        $query = Project::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('data->budget_email->sent_at');

        if ($from !== null)
        {
            $query->where('data->budget_email->sent_at', '>=', $from->toIso8601String());
        }
        if ($to !== null)
        {
            $query->where('data->budget_email->sent_at', '<=', $to->toIso8601String());
        }

        return $query->count();
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
