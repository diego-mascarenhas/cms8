<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\TeamAssetRepository;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class TestTeamAssetPath extends Command
{
    protected $signature = 'test:team-asset-path {user_id? : The ID of the user to impersonate}';
    protected $description = 'Test the team asset path functionality';

    public function handle()
    {
        // Optionally impersonate a user for testing
        $userId = $this->argument('user_id');
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $this->info("Impersonating user: {$user->name}");
                Auth::login($user);
            } else {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
        }

        // Get the current team
        $team = Auth::user()?->currentTeam;
        if (!$team) {
            $this->error("No current team found.");
            return 1;
        }

        $this->info("Current team: {$team->name} (ID: {$team->id})");

        // Test the repository
        $repository = app(TeamAssetRepository::class);
        $diskPath = $repository->getDiskPath();

        $this->info("Asset upload path: {$diskPath}");
        
        return 0;
    }
} 