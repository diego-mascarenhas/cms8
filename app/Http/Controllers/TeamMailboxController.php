<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMailboxRequest;
use App\Http\Requests\UpdateMailboxRequest;
use App\Models\Mailbox;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamMailboxController extends Controller
{
    private function ensureMailboxBelongsToTeam(Team $team, Mailbox $mailbox): void
    {
        if ($mailbox->team_id !== $team->id)
        {
            abort(404);
        }
    }

    private function isPersonalRequest(Request $request): bool
    {
        return in_array($request->input('ownership', $request->query('ownership')), ['personal', '1', 1, true], true)
            || $request->boolean('personal');
    }

    public function index(Team $team): View
    {
        $this->authorize('viewAny', [Mailbox::class, $team]);

        $teamMailboxes = $team->teamMailboxes()->orderBy('name')->get();
        $personalMailboxes = $team->mailboxes()
            ->forUser(auth()->user())
            ->orderBy('name')
            ->get();

        return view('team.mailboxes.index', compact('team', 'teamMailboxes', 'personalMailboxes'));
    }

    public function create(Request $request, Team $team): View
    {
        $this->authorize('create', [Mailbox::class, $team]);

        $isPersonal = $this->isPersonalRequest($request);

        return view('team.mailboxes.create', compact('team', 'isPersonal'));
    }

    public function store(StoreMailboxRequest $request, Team $team): RedirectResponse
    {
        $this->authorize('create', [Mailbox::class, $team]);

        $isPersonal = $this->isPersonalRequest($request);
        $data = $request->validated();
        $data['user_id'] = $isPersonal ? auth()->id() : null;

        $team->mailboxes()->create($data);

        return redirect()->route('team.mailboxes.index', $team)
            ->with('success', $isPersonal
                ? __('Personal mailbox created successfully.')
                : __('Mailbox created successfully.'));
    }

    public function edit(Team $team, Mailbox $mailbox): View|RedirectResponse
    {
        $this->ensureMailboxBelongsToTeam($team, $mailbox);
        $this->authorize('update', $mailbox);

        $isPersonal = $mailbox->isPersonal();

        return view('team.mailboxes.edit', compact('team', 'mailbox', 'isPersonal'));
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

        // Ownership cannot be changed via edit form.
        unset($data['user_id']);

        $mailbox->update($data);

        return redirect()->route('team.mailboxes.index', $team)
            ->with('success', $mailbox->isPersonal()
                ? __('Personal mailbox updated successfully.')
                : __('Mailbox updated successfully.'));
    }

    public function destroy(Team $team, Mailbox $mailbox): RedirectResponse
    {
        $this->ensureMailboxBelongsToTeam($team, $mailbox);
        $this->authorize('delete', $mailbox);

        $wasPersonal = $mailbox->isPersonal();
        $mailbox->delete();

        return redirect()->route('team.mailboxes.index', $team)
            ->with('success', $wasPersonal
                ? __('Personal mailbox deleted successfully.')
                : __('Mailbox deleted successfully.'));
    }

    public function testConnection(Team $team, Mailbox $mailbox): JsonResponse
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
