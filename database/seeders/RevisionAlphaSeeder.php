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

class RevisionAlphaSeeder extends Seeder
{
    private $teamId;
    
    public function run()
    {
        $this->command->info('🚀 Setting up Revision Alpha Client Data...');
        
        // 1. Create Revision Alpha Team
        $team = $this->createRevisionAlphaTeam();
        $this->teamId = $team->id;
        
        // 2. Create Revision Alpha users
        $this->createRevisionAlphaUsers($team);
        
        // 3. Create Revision Alpha enterprise
        $this->createRevisionAlphaEnterprise($team);
        
        // 4. Create Revision Alpha contacts
        $this->createRevisionAlphaContacts($team);
        
        // 5. Create Revision Alpha categories
        $this->createRevisionAlphaCategories();
        
        $this->command->info('✅ Revision Alpha Client setup completed successfully');
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
            ['name' => "revision alpha's Team"],
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
        
        $this->command->info("✅ Created Revision Alpha Team (ID: {$team->id})");
        
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
        
        // Create Lucas Luna - revision alpha
        $lucas = User::updateOrCreate(
            ['email' => 'lucaslunaclaraso@gmail.com'],
            [
                'name' => 'Lucas Luna Claraso',
                'email' => 'lucaslunaclaraso@gmail.com',
                'password' => Hash::make('Passw0rd!'),
                'email_verified_at' => now(),
                'current_team_id' => $team->id,
            ]
        );
        $lucas->assignRole(2);
        
        // Add to team if not already there
        if (!$lucas->teams()->where('team_id', $team->id)->exists()) {
            $lucas->teams()->attach($team->id);
        }
        
        $this->command->info("✅ Created/Updated user: Lucas Luna Claraso");
        
        // Create Jesica Lorente - revision alpha
        $jesica = User::updateOrCreate(
            ['email' => 'jesicalorente@selltion.com'],
            [
                'name' => 'Jesica Lorente',
                'email' => 'jesicalorente@selltion.com',
                'password' => Hash::make('Passw0rd!'),
                'email_verified_at' => now(),
                'current_team_id' => $team->id,
            ]
        );
        $jesica->assignRole(2);
        
        // Add to team if not already there
        if (!$jesica->teams()->where('team_id', $team->id)->exists()) {
            $jesica->teams()->attach($team->id);
        }
        
        $this->command->info("✅ Created/Updated user: Jesica Lorente");
    }
    
    /**
     * Create Revision Alpha enterprise
     */
    private function createRevisionAlphaEnterprise($team)
    {
        $this->command->info('🏢 Creating Revision Alpha enterprise...');
        
        $enterprise = Enterprise::updateOrCreate(
            ['name' => 'Revision Alpha', 'team_id' => $team->id],
            [
                'name' => 'Revision Alpha',
                'team_id' => $team->id,
                'type_id' => 1,
                'status_id' => 1,
                'creator_id' => 1,
            ]
        );
        
        $this->command->info("✅ Created Revision Alpha enterprise (ID: {$enterprise->id})");
    }
    
    /**
     * Create Revision Alpha contacts
     */
    private function createRevisionAlphaContacts($team)
    {
        $this->command->info('📞 Creating Revision Alpha contacts...');
        
        $revisionContacts = [
            [
                'name' => 'Diego Mascarenhas',
                'email' => 'diego.mascarenhas@revisionalpha.com',
                'phone' => 618123456,
                'profile' => 'Software Artisan & Freaky ;-)',
                'creator_id' => 2,
                'responsible_id' => 2,
                'status_id' => 5,
            ],
            [
                'name' => 'Carla de Loureiro',
                'email' => 'carla.loureiro@revisionalpha.com',
                'phone' => 618234567,
                'profile' => 'Senior Developer',
                'creator_id' => 1,
                'responsible_id' => 2,
                'status_id' => 5,
            ],
            [
                'name' => 'Fernando Barneto',
                'email' => 'fernando@revisionalpha.com',
                'phone' => 618345678,
                'profile' => 'Technical Support Specialist',
                'creator_id' => 1,
                'responsible_id' => 2,
                'status_id' => 5,
            ],
            [
                'name' => 'Cecilia Nuñez',
                'email' => 'cecilia@revisionalpha.com',
                'phone' => 618456789,
                'profile' => 'Project Manager',
                'creator_id' => 1,
                'responsible_id' => 2,
                'status_id' => 5,
            ],
            [
                'name' => 'Lucas Luna',
                'email' => 'lucas@revisionalpha.com',
                'phone' => 612345678,
                'profile' => 'CTO and Technical Lead',
                'creator_id' => 1,
                'responsible_id' => 2,
                'status_id' => 5,
            ],
            [
                'name' => 'Jesica Lorente',
                'email' => 'jesica@revisionalpha.com',
                'phone' => 623456789,
                'profile' => 'COO and Operations Manager',
                'creator_id' => 1,
                'responsible_id' => 2,
                'status_id' => 5,
            ],
        ];
        
        foreach ($revisionContacts as $contactData) {
            $contact = Contact::updateOrCreate(
                ['email' => $contactData['email'], 'team_id' => $team->id],
                array_merge($contactData, ['team_id' => $team->id])
            );
            
            // Relate contact to revision alpha enterprise
            $enterprise = Enterprise::where('name', 'Revision Alpha')->where('team_id', $team->id)->first();
            if ($enterprise && !$contact->enterprises()->where('enterprise_id', $enterprise->id)->exists()) {
                $contact->enterprises()->attach($enterprise->id);
            }
            
            $this->command->info("✅ Created/Updated contact: {$contactData['name']}");
        }
        
        // Update enterprise responsible
        $enterprise = Enterprise::where('name', 'Revision Alpha')->where('team_id', $team->id)->first();
        if ($enterprise) {
            $enterprise->save();
        }
    }
    
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
        ];
        
        // Invoice Categories for Revision Alpha
        if ($moduleIds['invoices']) {
            $invoiceParent = Category::updateOrCreate(
                ['name' => 'Retail Invoice Types', 'module_id' => $moduleIds['invoices'], 'team_id' => $this->teamId],
                [
                    'name' => 'Retail Invoice Types',
                    'module_id' => $moduleIds['invoices'],
                    'team_id' => $this->teamId,
                    'description' => 'Categories for retail invoices',
                    'status' => 1,
                ]
            );
            
            $invoiceCategories = ['Product Sales', 'Services', 'Repairs', 'Subscriptions', 'Custom Work'];
            
            foreach ($invoiceCategories as $category) {
                Category::updateOrCreate(
                    ['name' => $category, 'module_id' => $moduleIds['invoices'], 'team_id' => $this->teamId],
                    [
                        'name' => $category,
                        'module_id' => $moduleIds['invoices'],
                        'team_id' => $this->teamId,
                        'parent_id' => $invoiceParent->id,
                        'status' => 1,
                    ]
                );
            }
            
            $this->command->info("✅ Created invoice categories for Revision Alpha");
        }
        
        // Support Ticket Types for Revision Alpha
        if ($moduleIds['tickets']) {
            $ticketParent = Category::updateOrCreate(
                ['name' => 'Retail Support Issues', 'module_id' => $moduleIds['tickets'], 'team_id' => $this->teamId],
                [
                    'name' => 'Retail Support Issues',
                    'module_id' => $moduleIds['tickets'],
                    'team_id' => $this->teamId,
                    'description' => 'Support issues for retail customers',
                    'status' => 1,
                ]
            );
            
            $supportCategories = ['Product Help', 'Returns', 'Warranty Claims', 'Technical Support', 'Feature Requests'];
            
            foreach ($supportCategories as $category) {
                Category::updateOrCreate(
                    ['name' => $category, 'module_id' => $moduleIds['tickets'], 'team_id' => $this->teamId],
                    [
                        'name' => $category,
                        'module_id' => $moduleIds['tickets'],
                        'team_id' => $this->teamId,
                        'parent_id' => $ticketParent->id,
                        'status' => 1,
                    ]
                );
            }
            
            $this->command->info("✅ Created support categories for Revision Alpha");
        }
    }
} 