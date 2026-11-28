<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use App\Services\MeetingSpeakerLibrary;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Throwable;

class DeleteUser implements DeletesUsers
{
    /**
     * The team deleter implementation.
     *
     * @var \Laravel\Jetstream\Contracts\DeletesTeams
     */
    protected $deletesTeams;

    /**
     * Create a new action instance.
     */
    public function __construct(DeletesTeams $deletesTeams)
    {
        $this->deletesTeams = $deletesTeams;
    }

    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        try
        {
            app(MeetingSpeakerLibrary::class)->forgetUser($user);
        } catch (Throwable)
        {
            // Closing the account still removes the sample stored here.
        }

        User::query()->getModel()->getConnection()->transaction(function () use ($user)
        {
            $this->deleteTeams($user);
            $user->deleteProfilePhoto();
            $user->deleteVoiceSample();
            $user->tokens->each->delete();
            $user->delete();
        });
    }

    /**
     * Delete the teams and team associations attached to the user.
     */
    protected function deleteTeams(User $user): void
    {
        $user->teams()->detach();

        $user->ownedTeams->each(function (Team $team)
        {
            $this->deletesTeams->delete($team);
        });
    }
}
