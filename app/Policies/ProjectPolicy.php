<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        // Admin can see all projects
        if ($user->hasRole('admin'))
        {
            return true;
        }

        // Collaborators can see projects (but filtered to their own)
        if ($user->hasRole('collaborator'))
        {
            return true;
        }

        // Clients can see projects of their enterprises
        if ($user->hasRole('client'))
        {
            return true;
        }

        // Regular users need specific permission
        return $user->can('project.index');
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        // Admin can see any project in their team
        if ($user->hasRole('admin'))
        {
            return $project->team_id === $user->currentTeam->id;
        }

        // Collaborators can only see projects where they are involved
        if ($user->hasRole('collaborator'))
        {
            // Check if user is responsible for the project
            if ($project->responsible_id === $user->id && $project->team_id === $user->currentTeam->id)
            {
                return true;
            }

            // Check if user is assigned as a collaborator to the project
            return $project->collaborators()
                ->where('user_id', $user->id)
                ->exists() && $project->team_id === $user->currentTeam->id;
        }

        // Regular users need specific permission and team membership
        return $user->can('project.show') && $project->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return $user->can('project.create');
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        // Admin can update any project in their team
        if ($user->hasRole('admin'))
        {
            return $project->team_id === $user->currentTeam->id;
        }

        // Collaborators can only update projects they are responsible for
        if ($user->hasRole('collaborator'))
        {
            return $project->responsible_id === $user->id && $project->team_id === $user->currentTeam->id;
        }

        // Regular users need specific permission and team membership
        return $user->can('project.update') && $project->team_id === $user->currentTeam->id;
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        // Only admins can delete projects
        if ($user->hasRole('admin'))
        {
            return $project->team_id === $user->currentTeam->id;
        }

        // Regular users need specific permission
        return $user->can('project.destroy') && $project->team_id === $user->currentTeam->id;
    }

    /**
     * Get the query filter for the user's role.
     */
    public static function getQueryFilter(User $user)
    {
        return function ($query) use ($user)
        {
            // Admin can see all projects in their team
            if ($user->hasRole('admin'))
            {
                return $query->where('team_id', $user->currentTeam->id);
            }

            // Collaborators can only see projects where they are involved
            if ($user->hasRole('collaborator'))
            {
                return $query->where('team_id', $user->currentTeam->id)
                    ->where(function ($q) use ($user)
                    {
                        $q->where('responsible_id', $user->id)
                            ->orWhereHas('collaborators', function ($collaboratorQuery) use ($user)
                            {
                                $collaboratorQuery->where('user_id', $user->id);
                            });
                    });
            }

            // Clients can see projects of their enterprises
            if ($user->hasRole('client'))
            {
                // Get user's contact
                $contact = $user->contact;
                if (! $contact)
                {
                    return $query->whereRaw('1 = 0'); // Return no results
                }

                // Check if project belongs to any of the contact's enterprises
                $enterpriseIds = $contact->enterprises()->pluck('enterprises.id')->toArray();

                return $query->whereIn('enterprise_id', $enterpriseIds);
            }

            // Regular users can see all projects if they have permission
            if ($user->can('project.index'))
            {
                return $query->where('team_id', $user->currentTeam->id);
            }

            // No access
            return $query->whereRaw('1 = 0');
        };
    }
}
