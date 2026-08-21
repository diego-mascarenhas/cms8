<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Admins have full access to everything in their team.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin'))
        {
            return true;
        }

        return null; // Continue to specific policy methods
    }

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

        // Developers and editors can view projects
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return true;
        }

        return false;
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

        // Collaborators can see projects they advise, are assigned to, or own a task on
        if ($user->hasRole('collaborator'))
        {
            return $project->isVisibleToCollaborator($user);
        }

        // Developers and editors can view projects in their team
        if ($user->hasRole(['developer', 'editor', 'technical']))
        {
            return $project->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'collaborator', 'developer', 'technical']);
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

        // Collaborators can update projects they are authorized to see (advisor, assigned, or task owner)
        if ($user->hasRole('collaborator'))
        {
            return $project->isVisibleToCollaborator($user);
        }

        // Developers and technical users can update projects in their team
        if ($user->hasRole(['developer', 'technical']))
        {
            return $project->team_id === $user->currentTeam->id;
        }

        return false;
    }

    /**
     * Create a project budget (AI spec + pricing fields on the create form).
     */
    public function createBudget(User $user): bool
    {
        return $this->create($user);
    }

    /**
     * Manage budget and pricing on a project the user is already allowed to see.
     */
    public function manageBudget(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        // Only admins can delete projects
        return $user->hasRole('admin') && $project->team_id === $user->currentTeam->id;
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
                return Project::constrainCollaboratorVisibility(
                    $query->where('team_id', $user->currentTeam->id),
                    $user,
                );
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

            // Developers and editors can see all projects in their team
            if ($user->hasRole(['developer', 'editor', 'technical']))
            {
                return $query->where('team_id', $user->currentTeam->id);
            }

            // No access
            return $query->whereRaw('1 = 0');
        };
    }
}
