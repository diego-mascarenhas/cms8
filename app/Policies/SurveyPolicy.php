<?php

namespace App\Policies;

use App\Models\Survey;
use App\Models\User;

class SurveyPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('collaborator');
    }

    public function view(User $user, Survey $survey): bool
    {
        return $user->hasRole('collaborator')
            && $user->currentTeam !== null
            && $user->currentTeam->id === $survey->team_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('collaborator');
    }

    public function update(User $user, Survey $survey): bool
    {
        return $this->view($user, $survey);
    }

    public function delete(User $user, Survey $survey): bool
    {
        return false;
    }
}
