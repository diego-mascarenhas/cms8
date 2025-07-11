<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class RevisionAlphaSeeder extends Seeder
{
    private $teamId;

    public function run()
    {
        $this->command->info('🚀 Setting up Revision Alpha Data...');

        // 1. Create Revision Alpha Team
        $team = $this->createRevisionAlphaTeam();
        $this->teamId = $team->id;

        // 2. Create Revision Alpha users
        $this->createRevisionAlphaUsers($team);

        // 3. Create Revision Alpha enterprise
        // $this->createRevisionAlphaEnterprise($team);

        // 4. Create Revision Alpha contacts
        // $this->createRevisionAlphaContacts($team);

        // 5. Create Revision Alpha categories
        $this->createRevisionAlphaCategories();

        $this->command->info('✅ REVISION ALPHA setup completed successfully');
    }

    /**
     * Create Revision Alpha Team
     */
    private function createRevisionAlphaTeam()
    {
        $revisionUser = User::where('email', 'diego.mascarenhas@icloud.com')->first();

        if (!$revisionUser) {
            $this->command->error('Revision user not found. Please run UserSeeder first.');
            return null;
        }

        $team = Team::updateOrCreate(
            ['name' => "REVISION ALPHA's Team"],
            [
                'user_id' => $revisionUser->id,
                'name' => "revision alpha's Team",
                'personal_team' => false,
            ]
        );

        // Ensure the user is in the team
        if (!$team->users()->where('user_id', $revisionUser->id)->exists()) {
            $team->users()->attach($revisionUser->id, ['role' => 'admin']);
        }

        $this->command->info("✅ Created REVISION ALPHA Team (ID: {$team->id})");

        return $team;
    }

    /**
     * Create Revision Alpha users
     */
    private function createRevisionAlphaUsers($team)
    {
        $this->command->info('👥 Creating Revision Alpha users...');

        $revisionUser = User::where('email', 'diego.mascarenhas@icloud.com')->first();

        // Update current team for main user
        $revisionUser->update(['current_team_id' => $team->id]);

        // // Create Fernando Barneto - revision alpha
        // $fernando = User::updateOrCreate(
        //     ['email' => 'fernando@revisionalpha.com'],
        //     [
        //         'name' => 'Fernando Barneto',
        //         'email' => 'fernando@revisionalpha.com',
        //         'password' => Hash::make('@PabloHDP!'),
        //         'email_verified_at' => now(),
        //         'current_team_id' => $team->id,
        //     ]
        // );
        // $fernando->assignRole(1);

        // // Add to team if not already there
        // if (!$fernando->teams()->where('team_id', $team->id)->exists()) {
        //     $fernando->teams()->attach($team->id);
        // }

        // $this->command->info("✅ Created/Updated user: Fernando Barneto");

        // // Create Cecilia Nuñez - revision alpha
        // $cecy = User::updateOrCreate(
        //     ['email' => 'cecilia@revisionalpha.com'],
        //     [
        //         'name' => 'Cecilia Nuñez',
        //         'email' => 'cecilia@revisionalpha.com',
        //         'password' => Hash::make('@PabloHDP!'),
        //         'email_verified_at' => now(),
        //         'current_team_id' => $team->id,
        //     ]
        // );
        // $cecy->assignRole(3);

        // // Add to team if not already there
        // if (!$cecy->teams()->where('team_id', $team->id)->exists()) {
        //         $cecy->teams()->attach($team->id);
        // }

        // $this->command->info("✅ Created/Updated user: Cecilia Nuñez");
    }

    /**
     * Create Revision Alpha enterprise
     */
    // private function createRevisionAlphaEnterprise($team)
    // {
    //     $this->command->info('🏢 Creating Revision Alpha enterprise...');

    //     $enterprise = Enterprise::updateOrCreate(
    //         ['name' => 'Revision Alpha', 'team_id' => $team->id],
    //         [
    //             'name' => 'REVISION ALPHA',
    //             'team_id' => $team->id,
    //             'type_id' => 1,
    //             'status_id' => 1,
    //             'creator_id' => 1,
    //         ]
    //     );

    //     $this->command->info("✅ Created Revision Alpha enterprise (ID: {$enterprise->id})");
    // }

    /**
     * Create Revision Alpha contacts
     */
    // private function createRevisionAlphaContacts($team)
    // {
    //     $this->command->info('📞 Creating Revision Alpha contacts...');

    //     $revisionContacts = [
    //         [
    //             'name' => 'Diego Mascarenhas',
    //             'email' => 'diego.mascarenhas@revisionalpha.com',
    //             'phone' => 618123456,
    //             'profile' => 'Software Artisan & Freaky ;-)',
    //             'creator_id' => 2,
    //             'responsible_id' => 2,
    //             'status_id' => 5,
    //         ],
    //         [
    //             'name' => 'Carla de Loureiro',
    //             'email' => 'carla.loureiro@revisionalpha.com',
    //             'phone' => 618234567,
    //             'profile' => 'Senior Developer',
    //             'creator_id' => 1,
    //             'responsible_id' => 2,
    //             'status_id' => 5,
    //         ],
    //         [
    //             'name' => 'Fernando Barneto',
    //             'email' => 'fernando@revisionalpha.com',
    //             'phone' => 618345678,
    //             'profile' => 'Technical Support Specialist',
    //             'creator_id' => 1,
    //             'responsible_id' => 2,
    //             'status_id' => 5,
    //         ],
    //         [
    //             'name' => 'Cecilia Nuñez',
    //             'email' => 'cecilia@revisionalpha.com',
    //             'phone' => 618456789,
    //             'profile' => 'Project Manager',
    //             'creator_id' => 1,
    //             'responsible_id' => 2,
    //             'status_id' => 5,
    //         ],
    //     ];

    //     foreach ($revisionContacts as $contactData) {
    //         $contact = Contact::updateOrCreate(
    //             ['email' => $contactData['email'], 'team_id' => $team->id],
    //             array_merge($contactData, ['team_id' => $team->id])
    //         );

    //         // Relate contact to revision alpha enterprise
    //         $enterprise = Enterprise::where('name', 'Revision Alpha')->where('team_id', $team->id)->first();
    //         if ($enterprise && !$contact->enterprises()->where('enterprise_id', $enterprise->id)->exists()) {
    //             $contact->enterprises()->attach($enterprise->id);
    //         }

    //         $this->command->info("✅ Created/Updated contact: {$contactData['name']}");
    //     }

    //     // Update enterprise responsible
    //     $enterprise = Enterprise::where('name', 'Revision Alpha')->where('team_id', $team->id)->first();
    //     if ($enterprise) {
    //         $enterprise->save();
    //     }
    // }

    /**
     * Create Revision Alpha categories
     */
    private function createRevisionAlphaCategories()
    {
        $this->command->info('📂 Creating Revision Alpha categories...');

        // Find module IDs
        $moduleIds = [
            'services' => Module::where('key', 'services')->first()?->id,
            'communications' => Module::where('key', 'communications')->first()?->id,
            'projects' => Module::where('key', 'projects')->first()?->id,
            'tasks' => Module::where('key', 'tasks')->first()?->id,
            'invoices' => Module::where('key', 'invoices')->first()?->id,
            'tickets' => Module::where('key', 'tickets')->first()?->id,
            'mail' => Module::where('key', 'mail')->first()?->id,
            'chat' => Module::where('key', 'chat')->first()?->id,
            'campaigns' => Module::where('key', 'campaigns')->first()?->id,
            'hosting' => Module::where('key', 'hosting')->first()?->id,
        ];

        $this->command->info("✅ Created Revision Alpha categories");
    }
}
