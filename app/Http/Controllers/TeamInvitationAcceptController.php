<?php

namespace App\Http\Controllers;

use App\Actions\Jetstream\AcceptTeamInvitation;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\PendingTeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamInvitationAcceptController extends Controller
{
    public function __construct(private AcceptTeamInvitation $acceptTeamInvitation) {}

    public function __invoke(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        PendingTeamInvitation::store($request, $invitation);

        $user = $request->user();

        if ($user)
        {
            return $this->acceptForAuthenticatedUser($user, $invitation);
        }

        $existingUser = User::query()->where('email', $invitation->email)->first();

        if ($existingUser)
        {
            return redirect()
                ->route('login')
                ->with('status', __('Please sign in to accept the team invitation.'))
                ->withInput(['email' => $invitation->email]);
        }

        return redirect()
            ->route('register')
            ->with('status', __('Create your account to join :team.', ['team' => $invitation->team->name]));
    }

    private function acceptForAuthenticatedUser(User $user, TeamInvitation $invitation): RedirectResponse
    {
        $teamName = $invitation->team->name;

        try
        {
            $this->acceptTeamInvitation->accept($user, $invitation);
        } catch (ValidationException $e)
        {
            return redirect()
                ->route('profile.show')
                ->withErrors($e->errors());
        }

        return redirect(config('fortify.home'))->with(
            'banner',
            __('Great! You have accepted the invitation to join the :team team.', ['team' => $teamName]),
        );
    }
}
