<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BboSeeder extends Seeder
{
    private $teamId = 2; // BBO Team ID
    
    public function run()
    {
        $this->command->info('🚀 Setting up BBO Client Data...');
        
        // 1. Create BBO Team
        $team = $this->createBboTeam();
        
        // 2. Create BBO users
        $this->createBboUsers($team);
        
        // 3. Create BBO enterprise
        $this->createBboEnterprise($team);
        
        // 4. Create BBO contacts
        $this->createBboContacts($team);
        
        // 5. Create BBO categories
        $this->createBboCategories();
        
        $this->command->info('✅ BBO Client setup completed successfully');
    }
    
    /**
     * Create BBO Team
     */
    private function createBboTeam()
    {
        $bboOwner = User::where('email', 'victor@machbel.com')->first();
        
        if (!$bboOwner) {
            $this->command->error('BBO owner user not found. Please run UserSeeder first.');
            return null;
        }
        
        $team = Team::updateOrCreate(
            ['name' => "BBO's Team"],
            [
                'user_id' => $bboOwner->id,
                'name' => "BBO's Team",
                'personal_team' => false,
            ]
        );
        
        // Ensure the user is in the team
        if (!$team->users()->where('user_id', $bboOwner->id)->exists()) {
            $team->users()->attach($bboOwner->id, ['role' => 'admin']);
        }
        
        $this->command->info("✅ Created BBO Team (ID: {$team->id})");
        
        return $team;
    }
    
    /**
     * Create BBO users
     */
    private function createBboUsers($team)
    {
        $this->command->info('👥 Creating BBO users...');
        
        $bboUsers = [
            [
                'name' => 'Begoña Martínez',
                'email' => 'begona@bbosubtitulado.com',
                'phone' => 611234567,
                'role' => 2, // Admin role
            ],
            [
                'name' => 'Claudia López',
                'email' => 'claudia@bbosubtitulado.com',
                'phone' => 622345678,
                'role' => 2, // Admin role
            ],
            [
                'name' => 'Rocío García',
                'email' => 'rocio@bbosubtitulado.com',
                'phone' => 633456789,
                'role' => 2, // Admin role
            ],
            [
                'name' => 'Ana Fernández',
                'email' => 'ana@bbosubtitulado.com',
                'phone' => 644567890,
                'role' => 2, // Admin role
            ],
        ];
        
        foreach ($bboUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'phone' => $userData['phone'],
                    'password' => Hash::make('BBO2024!'),
                    'email_verified_at' => now(),
                    'current_team_id' => $team->id,
                ]
            );
            
            $user->assignRole($userData['role']);
            
            // Add to team if not already there
            if (!$user->teams()->where('team_id', $team->id)->exists()) {
                $user->teams()->attach($team->id);
            }
            
            $this->command->info("✅ Created/Updated BBO user: {$userData['name']}");
        }
    }
    
    /**
     * Create BBO enterprise
     */
    private function createBboEnterprise($team)
    {
        $this->command->info('🏢 Creating BBO enterprise...');
        
        $enterprise = Enterprise::updateOrCreate(
            ['name' => 'BBO Translation Agency', 'team_id' => $team->id],
            [
                'name' => 'BBO Translation Agency',
                'team_id' => $team->id,
                'type_id' => 1, // Client type
                'status_id' => 1, // Active status
                'creator_id' => 1,
                'email' => 'info@bbosubtitulado.com',
                'phone' => '912345678',
                'website' => 'https://bbo.com',
                'address' => 'Calle Principal 123',
                'locality' => 'Madrid',
                'postal_code' => '28001',
                'country' => 'España',
            ]
        );
        
        $this->command->info("✅ Created BBO enterprise (ID: {$enterprise->id})");
    }
    
    /**
     * Create BBO contacts
     */
    private function createBboContacts($team)
    {
        $this->command->info('📞 Creating BBO contacts...');
        
        $bboContacts = [
            [
                'name' => 'Begoña Martínez',
                'email' => 'begona@bbosubtitulado.com',
                'phone' => 611234567,
                'profile' => 'Senior project manager with 10+ years experience',
                'creator_id' => 1,
                'status_id' => 5, // Active status
            ],
            [
                'name' => 'Claudia López',
                'email' => 'claudia@bbosubtitulado.com',
                'phone' => 622345678,
                'profile' => 'Quality assurance specialist',
                'creator_id' => 1,
                'status_id' => 5,
            ],
        ];
        
        foreach ($bboContacts as $contactData) {
            $contact = Contact::updateOrCreate(
                ['email' => $contactData['email'], 'team_id' => $team->id],
                array_merge($contactData, ['team_id' => $team->id])
            );
            
            // Relate contact to BBO enterprise
            $enterprise = Enterprise::where('name', 'BBO Translation Agency')->where('team_id', $team->id)->first();
            if ($enterprise && !$contact->enterprises()->where('enterprise_id', $enterprise->id)->exists()) {
            }
            
            $this->command->info("✅ Created/Updated BBO contact: {$contactData['name']}");
        }
    }
    
    /**
     * Create BBO categories
     */
    private function createBboCategories()
    {
        $this->command->info('📂 Creating BBO categories...');
        
        // Get the projects module
        $projectsModule = Module::where('key', 'projects')->first();
        
        if (!$projectsModule) {
            $this->command->warn('Projects module not found, skipping category creation');
            return;
        }
        
        $bboCategories = [
            [
                'name' => 'BBO - Legal Translation',
                'description' => 'Legal translation projects for BBO',
                'module_id' => $projectsModule->id,
                'team_id' => $this->teamId,
                'status' => 1,
            ],
            [
                'name' => 'BBO - Technical Translation',
                'description' => 'Technical translation projects for BBO',
                'module_id' => $projectsModule->id,
                'team_id' => $this->teamId,
                'status' => 1,
            ],
        ];
        
        foreach ($bboCategories as $categoryData) {
            $category = Category::updateOrCreate(
                [
                    'name' => $categoryData['name'],
                    'module_id' => $categoryData['module_id'],
                    'team_id' => $categoryData['team_id'],
                ],
                $categoryData
            );
            
            $this->command->info("✅ Created/Updated BBO category: {$categoryData['name']}");
        }
    }
}
