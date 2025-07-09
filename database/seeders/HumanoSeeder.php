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

class HumanoSeeder extends Seeder
{
    private $teamId = 3; // Humano Team ID
    
    public function run()
    {
        $this->command->info('🚀 Setting up Humano Client Data...');
        
        // 1. Create Humano Team
        $team = $this->createHumanoTeam();
        
        // 2. Create Humano users
        $this->createHumanoUsers($team);
        
        // 3. Create Humano enterprise
        $this->createHumanoEnterprise($team);
        
        // 4. Create Humano contacts
        $this->createHumanoContacts($team);
        
        // 5. Create Humano categories
        $this->createHumanoCategories();
        
        $this->command->info('✅ Humano Client setup completed successfully');
    }
    
    /**
     * Create Humano Team
     */
    private function createHumanoTeam()
    {
        $humanoOwner = User::where('email', 'victor@machbel.com')->first();
        
        if (!$humanoOwner) {
            $this->command->error('Humano owner user not found. Please run UserSeeder first.');
            return null;
        }
        
        $team = Team::updateOrCreate(
            ['name' => "Humano's Team"],
            [
                'user_id' => $humanoOwner->id,
                'name' => "Humano's Team",
                'personal_team' => false,
            ]
        );
        
        // Ensure the user is in the team
        if (!$team->users()->where('user_id', $humanoOwner->id)->exists()) {
            $team->users()->attach($humanoOwner->id, ['role' => 'admin']);
        }
        
        $this->command->info("✅ Created Humano Team (ID: {$team->id})");
        
        return $team;
    }
    
    /**
     * Create Humano users
     */
    private function createHumanoUsers($team)
    {
        $this->command->info('👥 Creating Humano users...');
        
        $humanoOwner = User::where('email', 'victor@machbel.com')->first();
        
        // Update current team for main user
        $humanoOwner->update(['current_team_id' => $team->id]);
        
        // Add revision alpha user to humano team as well
        $revision = User::where('email', 'diego.mascarenhas@icloud.com')->first();
        if ($revision) {
            if (!$revision->teams()->where('team_id', $team->id)->exists()) {
                $revision->teams()->attach($team->id, ['role' => 'admin']);
            }
            $this->command->info("✅ Added Diego Mascarenhas to Humano team");
        }
        
        $this->command->info("✅ Updated Humano team users");
    }
    
    /**
     * Create Humano enterprise
     */
    private function createHumanoEnterprise($team)
    {
        $this->command->info('🏢 Creating Humano enterprise...');
        
        $enterprise = Enterprise::updateOrCreate(
            ['name' => 'Humano Agency', 'team_id' => $team->id],
            [
                'name' => 'Humano Agency',
                'team_id' => $team->id,
                'type_id' => 1, // Client type
                'status_id' => 1, // Active status
                'creator_id' => 1,
                'email' => 'info@humano.com',
                'phone' => '913456789',
                'website' => 'https://humano.com',
                'address' => 'Calle Tech 456',
                'locality' => 'Madrid',
                'postal_code' => '28002',
                'country' => 'España',
            ]
        );
        
        $this->command->info("✅ Created Humano enterprise (ID: {$enterprise->id})");
    }
    
    /**
     * Create Humano contacts
     */
    private function createHumanoContacts($team)
    {
        $this->command->info('📞 Creating Humano contacts...');
        
        $humanoContacts = [
            [
                'name' => 'Victor Gómez',
                'email' => 'victor@humano.com',
                'phone' => 34665086080,
                'profile' => 'CEO and Founder of Humano Agency',
                'creator_id' => 1,
                'responsible_id' => 1,
                'status_id' => 5, // Active status
            ],
            [
                'name' => 'Diego Mascarenhas',
                'email' => 'diego@humano.com',
                'phone' => 34722372858,
                'profile' => 'Technical Advisor and Partner',
                'creator_id' => 1,
                'responsible_id' => 1,
                'status_id' => 5,
            ],
        ];
        
        foreach ($humanoContacts as $contactData) {
            $contact = Contact::updateOrCreate(
                ['email' => $contactData['email'], 'team_id' => $team->id],
                array_merge($contactData, ['team_id' => $team->id])
            );
            
            // Relate contact to Humano enterprise
            $enterprise = Enterprise::where('name', 'Humano Agency')->where('team_id', $team->id)->first();
            if ($enterprise && !$contact->enterprises()->where('enterprise_id', $enterprise->id)->exists()) {
                $contact->enterprises()->attach($enterprise->id);
            }
            
            $this->command->info("✅ Created/Updated Humano contact: {$contactData['name']}");
        }
    }
    
    /**
     * Create Humano categories
     */
    private function createHumanoCategories()
    {
        $this->command->info('📂 Creating Humano categories...');
        
        // Get the projects module
        $projectsModule = Module::where('key', 'projects')->first();
        
        if (!$projectsModule) {
            $this->command->warn('Projects module not found, skipping category creation');
            return;
        }
        
        $humanoCategories = [
            [
                'name' => 'Humano - Software Development',
                'description' => 'Software development projects for Humano',
                'module_id' => $projectsModule->id,
                'team_id' => $this->teamId,
                'status' => 1,
            ],
            [
                'name' => 'Humano - Consulting',
                'description' => 'Consulting and advisory projects for Humano',
                'module_id' => $projectsModule->id,
                'team_id' => $this->teamId,
                'status' => 1,
            ],
            [
                'name' => 'Humano - Translation Platform',
                'description' => 'Translation platform development and maintenance',
                'module_id' => $projectsModule->id,
                'team_id' => $this->teamId,
                'status' => 1,
            ],
        ];
        
        foreach ($humanoCategories as $categoryData) {
            $category = Category::updateOrCreate(
                [
                    'name' => $categoryData['name'],
                    'module_id' => $categoryData['module_id'],
                    'team_id' => $categoryData['team_id'],
                ],
                $categoryData
            );
            
            $this->command->info("✅ Created/Updated Humano category: {$categoryData['name']}");
        }
    }
} 