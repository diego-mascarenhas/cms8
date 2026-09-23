<?php

namespace App\Services\Tickets;

use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\Permission\Models\Role;

class RevisionTicketImporter
{
    /**
     * @return array{
     *     users_created: int,
     *     users_existing: int,
     *     tickets_imported: int,
     *     tickets_skipped: int,
     *     responses_imported: int,
     *     attachments_imported: int,
     *     attachments_missing: int
     * }
     */
    public function import(string $connection, int $teamId, ?string $filesPath = null, bool $dryRun = false): array
    {
        $team = Team::query()->find($teamId);
        if ($team === null)
        {
            throw new RuntimeException("Team {$teamId} does not exist.");
        }

        $legacy = DB::connection($connection);
        $stats = [
            'users_created' => 0,
            'users_existing' => 0,
            'tickets_imported' => 0,
            'tickets_skipped' => 0,
            'responses_imported' => 0,
            'attachments_imported' => 0,
            'attachments_missing' => 0,
        ];

        $legacyUsers = $legacy->table('users')->orderBy('id')->get();
        $localUsers = [];

        foreach ($legacyUsers as $legacyUser)
        {
            $email = strtolower(trim((string) $legacyUser->email));
            if ($email === '')
            {
                continue;
            }

            $existing = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existing?->trashed() && ! $dryRun)
            {
                $existing->restore();
            }
            if ($existing)
            {
                $stats['users_existing']++;
                if (! $dryRun)
                {
                    $this->attachToTeam($existing, $team, (string) $legacyUser->role);
                }
                $localUsers[(int) $legacyUser->id] = $existing;

                continue;
            }

            $stats['users_created']++;
            if ($dryRun)
            {
                $localUsers[(int) $legacyUser->id] = new User(['email' => $email]);

                continue;
            }

            $user = new User([
                'name' => (string) ($legacyUser->name ?: $email),
                'email' => $email,
                'phone' => $this->phone($legacyUser->phone),
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->email_verified_at = now();
            $user->current_team_id = $team->id;
            $user->save();
            $this->attachToTeam($user, $team, (string) $legacyUser->role);
            $localUsers[(int) $legacyUser->id] = $user;
        }

        $tickets = $legacy->table('tickets')->orderBy('id')->get();
        foreach ($tickets as $legacyTicket)
        {
            $owner = $localUsers[(int) $legacyTicket->user_id] ?? null;
            if ($owner === null)
            {
                $stats['tickets_skipped']++;

                continue;
            }

            if ($owner->id)
            {
                $already = Ticket::withoutGlobalScope('team')
                    ->where('team_id', $team->id)
                    ->where('user_id', $owner->id)
                    ->where('subject', (string) $legacyTicket->subject)
                    ->where('created_at', $legacyTicket->created_at)
                    ->exists();
                if ($already)
                {
                    $stats['tickets_skipped']++;

                    continue;
                }
            }

            if ($dryRun)
            {
                $stats['tickets_imported']++;
                $stats['responses_imported'] += $legacy->table('ticket_responses')->where('ticket_id', $legacyTicket->id)->count();

                continue;
            }

            $assignee = $legacyTicket->assigned_to
                ? ($localUsers[(int) $legacyTicket->assigned_to] ?? null)
                : null;

            $ticket = new Ticket([
                'team_id' => $team->id,
                'user_id' => $owner->id,
                'subject' => (string) $legacyTicket->subject,
                'description' => (string) $legacyTicket->description,
                'status' => (string) $legacyTicket->status,
                'priority' => (string) $legacyTicket->priority,
                'assigned_to' => $assignee?->id,
                'closed_at' => $legacyTicket->closed_at,
                'last_response_at' => $legacyTicket->last_response_at,
            ]);
            $ticket->timestamps = false;
            $ticket->created_at = $legacyTicket->created_at;
            $ticket->updated_at = $legacyTicket->updated_at;
            $ticket->save();
            $stats['tickets_imported']++;

            $this->copyAttachments($legacy, 'App\\Models\\Ticket', (int) $legacyTicket->id, $ticket, $filesPath, $stats);

            $responses = $legacy->table('ticket_responses')->where('ticket_id', $legacyTicket->id)->orderBy('id')->get();
            foreach ($responses as $legacyResponse)
            {
                $author = $localUsers[(int) $legacyResponse->user_id] ?? null;
                if ($author === null)
                {
                    continue;
                }

                $response = new TicketResponse([
                    'ticket_id' => $ticket->id,
                    'user_id' => $author->id,
                    'message' => (string) $legacyResponse->message,
                    'is_internal_note' => (bool) $legacyResponse->is_internal_note,
                ]);
                $response->timestamps = false;
                $response->created_at = $legacyResponse->created_at;
                $response->updated_at = $legacyResponse->updated_at;
                TicketResponse::withoutEvents(function () use ($response): void
                {
                    $response->save();
                });
                $stats['responses_imported']++;

                $this->copyAttachments($legacy, 'App\\Models\\TicketResponse', (int) $legacyResponse->id, $response, $filesPath, $stats);
            }
        }

        return $stats;
    }

    private function attachToTeam(User $user, Team $team, string $legacyRole): void
    {
        $roleName = $legacyRole === 'admin' ? 'admin' : 'client';
        Role::findOrCreate($roleName, 'web');
        if (! $user->hasRole($roleName))
        {
            $user->assignRole($roleName);
        }

        if (! $user->teams()->where('teams.id', $team->id)->exists())
        {
            $user->teams()->attach($team->id, ['role' => $roleName === 'admin' ? 'admin' : 'editor']);
        }

        if ($user->current_team_id === null)
        {
            $user->current_team_id = $team->id;
            $user->save();
        }
    }

    private function phone(mixed $phone): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '' || strlen($digits) > 18)
        {
            return null;
        }

        return (int) $digits;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function copyAttachments(object $legacy, string $modelType, int $modelId, Ticket|TicketResponse $model, ?string $filesPath, array &$stats): void
    {
        if ($filesPath === null || $filesPath === '')
        {
            return;
        }

        $files = $legacy->table('media')
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->where('collection_name', 'attachments')
            ->orderBy('id')
            ->get();

        foreach ($files as $file)
        {
            $path = rtrim($filesPath, '/').'/'.$file->id.'/'.$file->file_name;
            if (! is_file($path))
            {
                $stats['attachments_missing']++;

                continue;
            }

            try
            {
                $model->addMedia($path)
                    ->preservingOriginal()
                    ->withCustomProperties(['legacy_media_id' => (int) $file->id])
                    ->toMediaCollection('attachments');
                $stats['attachments_imported']++;
            } catch (FileUnacceptableForCollection)
            {
                $stats['attachments_missing']++;
            }
        }
    }
}
