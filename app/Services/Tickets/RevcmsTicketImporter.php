<?php

namespace App\Services\Tickets;

use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketRating;
use App\Models\TicketResponse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\Permission\Models\Role;

class RevcmsTicketImporter
{
    /**
     * @return array{
     *     users_created: int,
     *     users_existing: int,
     *     tickets_imported: int,
     *     tickets_skipped: int,
     *     responses_imported: int,
     *     attachments_imported: int,
     *     attachments_missing: int,
     *     ratings_imported: int
     * }
     */
    public function import(string $connection, int $teamId, ?string $filesPath = null, bool $dryRun = false, ?callable $onTicket = null): array
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
            'ratings_imported' => 0,
        ];

        $contactIds = $legacy->table('tickets_rel_contactos')->pluck('id_contacto')
            ->merge($legacy->table('tickets_items')->pluck('id_contacto'))
            ->merge($legacy->table('tickets')->pluck('username_alta'));
        if (Schema::connection($connection)->hasTable('ticket_ratings'))
        {
            $contactIds = $contactIds->merge($legacy->table('ticket_ratings')->pluck('user_id'));
        }
        $contactIds = $contactIds->filter()->unique()->values();

        $contacts = collect();
        foreach ($contactIds->chunk(1000) as $chunk)
        {
            $rows = $legacy->table('contactos')
                ->whereIn('id', $chunk->all())
                ->get(['id', 'nombre', 'apellido', 'email', 'celular', 'telefono', 'area_privada']);
            foreach ($rows as $row)
            {
                $contacts->put((int) $row->id, $row);
            }
        }

        $localUsers = $this->usersForContacts($contacts, $team, $dryRun, $stats);
        $importedTickets = [];

        $legacy->table('tickets')->orderBy('id')->chunk(200, function ($tickets) use ($legacy, $team, $contacts, $localUsers, $filesPath, $dryRun, $onTicket, &$stats, &$importedTickets): void
        {
            $ticketIds = $tickets->pluck('id')->all();
            $relations = $legacy->table('tickets_rel_contactos')->whereIn('id_ticket', $ticketIds)->get()->groupBy('id_ticket');
            $items = $legacy->table('tickets_items')->whereIn('id_ticket', $ticketIds)->orderBy('id')->get()->groupBy('id_ticket');
            $itemIds = $items->flatten()->pluck('id')->all();
            $attachments = $itemIds === []
                ? collect()
                : $legacy->table('tickets_items_adjuntos')->whereIn('id_ticket_item', $itemIds)->orderBy('id')->get()->groupBy('id_ticket_item');

            foreach ($tickets as $legacyTicket)
            {
                if ($onTicket)
                {
                    $onTicket();
                }

                $ownerContactId = $this->ownerContactId($legacyTicket, $relations->get($legacyTicket->id, collect()), $contacts);
                $owner = $ownerContactId ? ($localUsers[$ownerContactId] ?? null) : null;
                $thread = $items->get($legacyTicket->id, collect())->values();
                if ($owner === null || $thread->isEmpty())
                {
                    $stats['tickets_skipped']++;

                    continue;
                }

                $opening = (string) $thread->first()->mensaje;
                $description = $this->text($opening !== '' ? $opening : (string) $legacyTicket->asunto);
                $createdAt = $this->moment((int) $legacyTicket->fecha_alta);
                $status = $this->status((int) $legacyTicket->estado);

                $replies = $thread->slice(1)->map(fn ($item) => $this->text((string) $item->mensaje))->values()->all();
                $existing = $owner->id
                    ? $this->matchingTicket($team->id, $owner->id, (string) $legacyTicket->asunto, $createdAt, $description, $replies)
                    : null;
                if ($existing)
                {
                    $stats['tickets_skipped']++;
                    $importedTickets[(int) $legacyTicket->id] = $existing;

                    continue;
                }

                if ($dryRun)
                {
                    $stats['tickets_imported']++;
                    $stats['responses_imported'] += max(0, $thread->count() - 1);

                    continue;
                }

                $lastAt = $this->moment((int) $thread->last()->fecha_alta);
                $ticket = new Ticket([
                    'team_id' => $team->id,
                    'user_id' => $owner->id,
                    'subject' => $this->text((string) $legacyTicket->asunto, 255),
                    'description' => $description,
                    'status' => $status,
                    'priority' => $this->priority((int) $legacyTicket->prioridad),
                    'assigned_to' => null,
                    'closed_at' => $status === 'closed' ? $lastAt : null,
                    'last_response_at' => $lastAt,
                ]);
                $ticket->timestamps = false;
                $ticket->created_at = $createdAt;
                $ticket->updated_at = $legacyTicket->fecha_modificacion
                    ? $this->moment((int) $legacyTicket->fecha_modificacion)
                    : $lastAt;
                $ticket->save();
                $stats['tickets_imported']++;
                $importedTickets[(int) $legacyTicket->id] = $ticket;

                $this->copyAttachments($attachments->get($thread->first()->id, collect()), $ticket, $filesPath, $stats);

                foreach ($thread->slice(1) as $item)
                {
                    $author = $localUsers[(int) $item->id_contacto] ?? $owner;
                    $response = new TicketResponse([
                        'ticket_id' => $ticket->id,
                        'user_id' => $author->id,
                        'message' => $this->text((string) $item->mensaje),
                        'is_internal_note' => (int) $item->visibilidad === 1,
                    ]);
                    $response->timestamps = false;
                    $response->created_at = $this->moment((int) $item->fecha_alta);
                    $response->updated_at = $response->created_at;
                    TicketResponse::withoutEvents(function () use ($response): void
                    {
                        $response->save();
                    });
                    $stats['responses_imported']++;
                    $this->copyAttachments($attachments->get($item->id, collect()), $response, $filesPath, $stats);
                }
            }
        });

        if (! $dryRun && Schema::connection($connection)->hasTable('ticket_ratings'))
        {
            $this->importRatings($legacy, $importedTickets, $contacts, $localUsers, $stats);
        }

        return $stats;
    }

    /**
     * @param  Collection<int, object>  $contacts
     * @param  array<string, int>  $stats
     * @return array<int, User>
     */
    private function usersForContacts(Collection $contacts, Team $team, bool $dryRun, array &$stats): array
    {
        $byEmail = [];
        foreach ($contacts as $contact)
        {
            $email = $this->email($contact->email ?? null);
            if ($email === null)
            {
                continue;
            }
            $byEmail[$email][] = (int) $contact->id;
        }

        $existing = collect();
        foreach (array_chunk(array_keys($byEmail), 500) as $chunk)
        {
            $existing = $existing->merge(
                User::withTrashed()->whereIn(DB::raw('LOWER(email)'), $chunk)->get(),
            );
        }
        $existingByEmail = $existing->keyBy(fn (User $user) => strtolower($user->email));

        $localUsers = [];
        foreach ($byEmail as $email => $contactIds)
        {
            $contact = $contacts->get($contactIds[0]);
            $user = $existingByEmail->get($email);
            if ($user)
            {
                $stats['users_existing']++;
                if ($user->trashed() && ! $dryRun)
                {
                    $user->restore();
                }
                if (! $dryRun)
                {
                    $this->attachToTeam($user, $team);
                }
            } else
            {
                $stats['users_created']++;
                if ($dryRun)
                {
                    $user = new User(['email' => $email]);
                } else
                {
                    $user = new User([
                        'name' => trim(($contact->nombre ?? '').' '.($contact->apellido ?? '')) ?: $email,
                        'email' => $email,
                        'phone' => $this->phone($contact->celular ?? $contact->telefono ?? null),
                        'password' => Hash::make(Str::random(40)),
                    ]);
                    $user->email_verified_at = now();
                    $user->current_team_id = $team->id;
                    $user->save();
                    $this->attachToTeam($user, $team, assignClient: true);
                }
            }

            foreach ($contactIds as $contactId)
            {
                $localUsers[$contactId] = $user;
            }
        }

        return $localUsers;
    }

    private function attachToTeam(User $user, Team $team, bool $assignClient = false): void
    {
        if ($assignClient)
        {
            Role::findOrCreate('client', 'web');
            if (! $user->hasRole('client'))
            {
                $user->assignRole('client');
            }
        }

        if (! $user->teams()->where('teams.id', $team->id)->exists())
        {
            $user->teams()->attach($team->id, ['role' => 'editor']);
        }

        if ($user->current_team_id === null)
        {
            $user->current_team_id = $team->id;
            $user->save();
        }
    }

    /**
     * @param  Collection<int, object>  $relations
     * @param  Collection<int, object>  $contacts
     */
    private function ownerContactId(object $ticket, Collection $relations, Collection $contacts): ?int
    {
        $ids = $relations->pluck('id_contacto')->push($ticket->username_alta)->filter()->map(fn ($id) => (int) $id)->unique();
        $candidates = $ids->map(fn (int $id) => $contacts->get($id))->filter(function ($contact)
        {
            return $contact && $this->email($contact->email ?? null) !== null;
        });
        $client = $candidates->first(fn ($contact) => (int) $contact->area_privada === 3);

        $chosen = $client ?? $candidates->first();

        return $chosen ? (int) $chosen->id : null;
    }

    /**
     * @param  array<int, string>  $replies
     */
    private function matchingTicket(int $teamId, int $userId, string $subject, Carbon $createdAt, string $description, array $replies): ?Ticket
    {
        $candidates = Ticket::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where('subject', $this->text($subject, 255))
            ->where('created_at', $createdAt->format('Y-m-d H:i:s'))
            ->where('description', $description)
            ->get();

        foreach ($candidates as $candidate)
        {
            $localReplies = TicketResponse::query()
                ->where('ticket_id', $candidate->id)
                ->orderBy('id')
                ->pluck('message')
                ->all();
            if ($localReplies === $replies)
            {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int, Ticket>  $importedTickets
     * @param  Collection<int, object>  $contacts
     * @param  array<int, User>  $localUsers
     * @param  array<string, int>  $stats
     */
    private function importRatings(object $legacy, array $importedTickets, Collection $contacts, array $localUsers, array &$stats): void
    {
        if ($importedTickets === [])
        {
            return;
        }

        $ratings = $legacy->table('ticket_ratings')->whereIn('ticket_id', array_keys($importedTickets))->get();
        foreach ($ratings as $rating)
        {
            $ticket = $importedTickets[(int) $rating->ticket_id] ?? null;
            $user = $localUsers[(int) $rating->user_id] ?? null;
            if ($ticket === null || $user === null || $user->id === null)
            {
                continue;
            }

            TicketRating::query()->firstOrCreate(
                ['ticket_id' => $ticket->id, 'user_id' => $user->id],
                [
                    'tiempo_respuesta' => (int) $rating->tiempo_respuesta,
                    'atencion' => (int) $rating->atencion,
                    'solucion' => (int) $rating->solucion,
                    'comentarios' => $rating->comentarios,
                ],
            );
            $stats['ratings_imported']++;
        }
    }

    /**
     * @param  Collection<int, object>  $files
     * @param  array<string, int>  $stats
     */
    private function copyAttachments(Collection $files, Ticket|TicketResponse $model, ?string $filesPath, array &$stats): void
    {
        if ($filesPath === null || $filesPath === '' || $files->isEmpty())
        {
            return;
        }

        foreach ($files as $file)
        {
            $path = rtrim($filesPath, '/').'/'.$file->archivo;
            if (! is_file($path))
            {
                $stats['attachments_missing']++;

                continue;
            }

            try
            {
                $model->addMedia($path)
                    ->preservingOriginal()
                    ->usingFileName((string) $file->archivo)
                    ->usingName(pathinfo((string) $file->nombre, PATHINFO_FILENAME))
                    ->toMediaCollection('attachments');
                $stats['attachments_imported']++;
            } catch (FileUnacceptableForCollection)
            {
                $stats['attachments_missing']++;
            }
        }
    }

    private function status(int $estado): string
    {
        return match ($estado)
        {
            2 => 'open',
            3, 5, 6 => 'in_progress',
            4 => 'waiting_client',
            default => 'closed',
        };
    }

    private function priority(int $prioridad): string
    {
        return match ($prioridad)
        {
            2 => 'medium',
            3 => 'high',
            4 => 'urgent',
            default => 'low',
        };
    }

    private function moment(int $timestamp): Carbon
    {
        if ($timestamp <= 0)
        {
            return Carbon::createFromTimestamp(0);
        }

        return Carbon::createFromTimestamp($timestamp);
    }

    private function email(mixed $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            return null;
        }

        return $email;
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

    private function text(string $value, int $max = 65000): string
    {
        if (strlen($value) <= $max)
        {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
