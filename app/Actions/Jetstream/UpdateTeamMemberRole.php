<?php

namespace App\Actions\Jetstream;

use App\Models\Contact;
use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;

class UpdateTeamMemberRole extends JetstreamUpdateTeamMemberRole
{
    public function __construct(private JetstreamTeamRoleSynchronizer $roleSynchronizer) {}

    /**
     * Update the role for the given team member and sync Spatie role when mapped.
     *
     * Promoting a portal client to a staff role unlinks contacts.user_id so the
     * same login can work as a team member (Marketing, Collaborator, etc.).
     */
    public function update($user, $team, $teamMemberId, string $role): void
    {
        $member = User::query()->find($teamMemberId);

        if ($member !== null)
        {
            $currentRole = $member->teamRole($team)?->key;

            if ($currentRole === 'client' && $role !== 'client')
            {
                $this->unlinkPortalContacts($member);
            }
        }

        parent::update($user, $team, $teamMemberId, $role);

        if ($member)
        {
            $this->roleSynchronizer->sync($member->fresh(), $role);
        }
    }

    private function unlinkPortalContacts(User $member): void
    {
        $linked = Contact::withoutGlobalScopes()
            ->where('user_id', $member->id)
            ->get();

        if ($linked->isEmpty())
        {
            return;
        }

        Contact::withoutGlobalScopes()
            ->where('user_id', $member->id)
            ->update(['user_id' => null]);

        session()->flash(
            'flash.banner',
            __('This member was unlinked from the client portal contact so they can use a staff team role.'),
        );
        session()->flash('flash.bannerStyle', 'success');
    }
}
