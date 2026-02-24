<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Database\Factories\ClientFactory;
use Illuminate\Database\Seeder;

/**
 * Creates 3 demo clients (enterprises) and 10 demo contacts per team for testing.
 * Runs for every team that has at least one user, so the dropdown shows clients
 * regardless of which team you are in.
 * Safe to run multiple times (creates new records each run).
 */
class DemoClientsAndContactsSeeder extends Seeder
{
    public function run(): void
    {
        $teamsWithUsers = Team::whereHas('users')->get();
        if ($teamsWithUsers->isEmpty())
        {
            $this->command->error('No team with users found. Run DatabaseSeeder first.');

            return;
        }

        foreach ($teamsWithUsers as $team)
        {
            $user = User::whereHas('teams', fn ($q) => $q->where('team_id', $team->id))->first();
            if (! $user)
            {
                continue;
            }

            $this->command->info("Seeding 3 clients and 10 contacts for team: {$team->name} (id: {$team->id})");

            $enterprises = ClientFactory::new()
                ->count(3)
                ->client()
                ->create([
                    'team_id' => $team->id,
                    'creator_id' => $user->id,
                    'responsible_id' => $user->id,
                ]);

            $this->command->info('Created 3 clients: '.$enterprises->pluck('name')->join(', '));

            $createdContacts = collect();
            for ($i = 0; $i < 10; $i++)
            {
                $contact = Contact::factory()->create([
                    'team_id' => $team->id,
                    'creator_id' => $user->id,
                    'responsible_id' => $user->id,
                ]);
                $createdContacts->push($contact);
            }

            $this->command->info('Created 10 contacts.');

            $positions = ['Contacto principal', 'Responsable técnico', 'Facturación', 'Soporte'];
            foreach ($enterprises as $index => $enterprise)
            {
                $slice = $index === 0
                    ? $createdContacts->take(4)
                    : $createdContacts->slice(4 + ($index - 1) * 3, 3);
                foreach ($slice->values() as $i => $contact)
                {
                    if (! $contact->enterprises()->where('enterprise_id', $enterprise->id)->exists())
                    {
                        $contact->enterprises()->attach($enterprise->id, [
                            'position' => $positions[$i % count($positions)] ?? 'Contacto',
                        ]);
                    }
                }
            }

            $this->command->info('Linked contacts to clients.');
        }

        $this->command->info('Done. Run: php artisan db:seed --class=DemoClientsAndContactsSeeder');
    }
}
