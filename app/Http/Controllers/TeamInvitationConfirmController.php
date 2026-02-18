<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Jetstream\Contracts\AddsTeamMembers;

class TeamInvitationConfirmController extends Controller
{
    /**
     * Confirm a pending team invitation (add the user to the team without requiring the email link).
     * Allows the team owner or root to add the invited user when the invitation email did not arrive.
     */
    public function __invoke(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $this->authorize('addTeamMember', $invitation->team);

        $team = $invitation->team;
        $email = $invitation->email;

        try
        {
            app(AddsTeamMembers::class)->add(
                $request->user(),
                $team,
                $email,
                $invitation->role,
            );
        } catch (\Illuminate\Validation\ValidationException $e)
        {
            $errors = $e->errors();
            if (isset($errors['email']))
            {
                return redirect()
                    ->route('teams.show', $team)
                    ->with('error', __('Cannot confirm invitation: no user is registered with this email. The invitation will remain pending until they create an account and accept it via the email link.'));
            }

            return redirect()
                ->route('teams.show', $team)
                ->withErrors($errors);
        }

        $invitation->delete();

        return redirect()
            ->route('teams.show', $team)
            ->with('success', __('Invitation confirmed. :email has been added to the team.', ['email' => $email]));
    }
}
