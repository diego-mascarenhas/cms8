<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamMemberRole;
use App\Actions\Jetstream\UpdateTeamName;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);

        $this->app->bind(JetstreamUpdateTeamMemberRole::class, UpdateTeamMemberRole::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        // Register roles in hierarchical order (top → bottom)
        Jetstream::role('root', 'Root', [
            'create', 'read', 'update', 'delete',
        ])->description('Root users can perform any action.');

        Jetstream::role('admin', 'Administrator', [
            'create', 'read', 'update', 'delete',
        ])->description('Administrator users can perform any action.');

        Jetstream::role('developer', 'Developer', [
            'read', 'create', 'update', 'delete',
        ])->description('Developer users can manage technical resources.');

        Jetstream::role('technical', 'Technical', [
            'read', 'create', 'update',
        ])->description('Technical users can read, create, and update technical resources.');

        Jetstream::role('editor', 'Editor', [
            'read', 'create', 'update',
        ])->description('Editor users have the ability to read, create, and update.');

        Jetstream::role('collaborator', 'Collaborator', [
            'read', 'create', 'update',
        ])->description('Collaborator users can read, create, and update.');

        Jetstream::role('employee', 'Employee', [
            'read',
        ])->description('Employee users have limited access.');

        Jetstream::role('client', 'Client', [
            'read',
        ])->description('Client users can view assigned resources.');

        Jetstream::role('auditor', 'Auditor', [
            'read',
        ])->description('Auditor users can view only.');

        Jetstream::role('guest', 'Guest', [
            'read',
        ])->description('Guest users can view public resources.');

        Jetstream::role('user', 'User', [
            'read',
        ])->description('Standard user role with read access.');
    }
}
