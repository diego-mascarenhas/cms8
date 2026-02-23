<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMailboxRequest;
use App\Http\Requests\UpdateMailboxRequest;
use App\Models\Mailbox;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeamMailboxController extends Controller
{
    /**
     * Ensure the mailbox belongs to the given team.
     */
    private function ensureMailboxBelongsToTeam(Team $team, Mailbox $mailbox): void
    {
        if ($mailbox->team_id !== $team->id)
        {
            abort(404);
        }
    }

    public function index(Team $team): View
    {
        $this->authorize('viewAny', [Mailbox::class, $team]);

        $mailboxes = $team->mailboxes()->orderBy('name')->get();

        return view('team.mailboxes.index', compact('team', 'mailboxes'));
    }

    public function create(Team $team): View
    {
        $this->authorize('create', [Mailbox::class, $team]);

        return view('team.mailboxes.create', compact('team'));
    }

    public function store(StoreMailboxRequest $request, Team $team): RedirectResponse
    {
        $this->authorize('create', [Mailbox::class, $team]);

        $team->mailboxes()->create($request->validated());

        return redirect()->route('team.mailboxes.index', $team)
            ->with('success', __('Mailbox created successfully.'));
    }

    public function edit(Team $team, Mailbox $mailbox): View|RedirectResponse
    {
        $this->ensureMailboxBelongsToTeam($team, $mailbox);
        $this->authorize('update', $mailbox);

        return view('team.mailboxes.edit', compact('team', 'mailbox'));
    }

    public function update(UpdateMailboxRequest $request, Team $team, Mailbox $mailbox): RedirectResponse
    {
        $this->ensureMailboxBelongsToTeam($team, $mailbox);
        $this->authorize('update', $mailbox);

        $data = $request->validated();
        if (empty($data['password']))
        {
            unset($data['password']);
        }

        $mailbox->update($data);

        return redirect()->route('team.mailboxes.index', $team)
            ->with('success', __('Mailbox updated successfully.'));
    }

    public function destroy(Team $team, Mailbox $mailbox): RedirectResponse
    {
        $this->ensureMailboxBelongsToTeam($team, $mailbox);
        $this->authorize('delete', $mailbox);

        $mailbox->delete();

        return redirect()->route('team.mailboxes.index', $team)
            ->with('success', __('Mailbox deleted successfully.'));
    }

    public function testConnection(Team $team, Mailbox $mailbox): \Illuminate\Http\JsonResponse
    {
        $this->ensureMailboxBelongsToTeam($team, $mailbox);
        $this->authorize('update', $mailbox);

        try
        {
            $host = $mailbox->host;
            $port = $mailbox->port;
            $username = $mailbox->username;
            $password = $mailbox->password ?? '';

            if (empty($host) || empty($username))
            {
                return response()->json([
                    'success' => false,
                    'message' => __('IMAP configuration is incomplete. Please configure host and username.'),
                ]);
            }

            $connectionString = "{{$host}:{$port}/imap";

            if ($mailbox->encryption === 'ssl')
            {
                $connectionString .= '/ssl';
            } elseif ($mailbox->encryption === 'tls')
            {
                $connectionString .= '/tls';
            }

            $connectionString .= '/novalidate-cert}';

            $connection = @imap_open($connectionString, $username, $password);

            if ($connection)
            {
                imap_close($connection);

                return response()->json([
                    'success' => true,
                    'message' => __('IMAP connection successful!'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => __('IMAP connection failed: ').imap_last_error(),
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => __('IMAP connection failed: ').$e->getMessage(),
            ]);
        }
    }
}
