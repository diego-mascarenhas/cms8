<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Module;

class ModuleCategorySeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Creating module-specific categories...');
        
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
        
        // Global Categories (no team_id)
        $this->createGlobalCategories($moduleIds);
        
        // Team-specific Categories
        $this->createTeam1Categories($moduleIds);
        $this->createTeam2Categories($moduleIds);
        
        $this->command->info('Module categories created successfully.');
    }
    
    private function createGlobalCategories($moduleIds)
    {
        $this->command->info('Creating global module categories...');
        
        // Service Categories (Global)
        $servicesParent = Category::create([
            'name' => 'Service Types',
            'module_id' => $moduleIds['services'],
            'team_id' => 1,
            'description' => 'Main service categories available to all teams',
            'status' => 1
        ]);
        
        $serviceTypes = [
            'Web Development' => [
                'Website Design',
                'E-commerce',
                'Web Applications',
                'Landing Pages',
                'API Development'
            ],
            'Digital Marketing' => [
                'SEO',
                'Google Ads',
                'Social Media',
                'Email Marketing',
                'Content Strategy'
            ],
            'Hosting Services' => [
                'Shared Hosting',
                'Cloud Hosting',
                'Dedicated Server',
                'Domain Registration',
                'SSL Certificates'
            ]
        ];
        
        foreach ($serviceTypes as $mainCategory => $subCategories) {
            $parent = Category::create([
                'name' => $mainCategory,
                'module_id' => $moduleIds['services'],
                'team_id' => 1,
                'parent_id' => $servicesParent->id,
                'status' => 1
            ]);
            
            foreach ($subCategories as $subCategory) {
                Category::create([
                    'name' => $subCategory,
                    'module_id' => $moduleIds['services'],
                    'team_id' => 1,
                    'parent_id' => $parent->id,
                    'status' => 1
                ]);
            }
        }
        
        // Communication Categories (Global)
        $communicationsParent = Category::create([
            'name' => 'Message Types',
            'module_id' => $moduleIds['communications'],
            'team_id' => 1,
            'description' => 'Types of communications',
            'status' => 1
        ]);
        
        $messageTypes = ['Prospect', 'Client', 'Staff', 'Support', 'Automated'];
        
        foreach ($messageTypes as $type) {
            Category::create([
                'name' => $type,
                'module_id' => $moduleIds['communications'],
                'team_id' => 1,
                'parent_id' => $communicationsParent->id,
                'status' => 1
            ]);
        }
        
        // Task Categories (Global)
        $taskTypesParent = Category::create([
            'name' => 'Task Types',
            'module_id' => $moduleIds['tasks'],
            'team_id' => 1,
            'description' => 'Types of tasks',
            'status' => 1
        ]);
        
        $taskCategories = ['Development', 'Design', 'Research', 'Meeting', 'Support', 'Administrative', 'Documentation'];
        
        foreach ($taskCategories as $category) {
            Category::create([
                'name' => $category,
                'module_id' => $moduleIds['tasks'],
                'team_id' => 1,
                'parent_id' => $taskTypesParent->id,
                'status' => 1
            ]);
        }
        
        // Project Categories (Global)
        $projectTypesParent = Category::create([
            'name' => 'Project Types',
            'module_id' => $moduleIds['projects'],
            'team_id' => 1,
            'description' => 'Types of projects',
            'status' => 1
        ]);
        
        $projectCategories = ['Web', 'Marketing', 'Design', 'Consulting', 'Maintenance', 'Development'];
        
        foreach ($projectCategories as $category) {
            Category::create([
                'name' => $category,
                'module_id' => $moduleIds['projects'],
                'team_id' => 1,
                'parent_id' => $projectTypesParent->id,
                'status' => 1
            ]);
        }
    }
    
    private function createTeam1Categories($moduleIds)
    {
        $this->command->info('Creating Team 1 specific categories...');
        
        // Invoice Categories for Team 1
        $invoiceParent = Category::create([
            'name' => 'Invoice Categories',
            'module_id' => $moduleIds['invoices'],
            'team_id' => 1,
            'description' => 'Categories for organizing invoices',
            'status' => 1
        ]);
        
        $invoiceCategories = ['Monthly Services', 'One-time Projects', 'Consulting', 'Product Sales', 'Maintenance'];
        
        foreach ($invoiceCategories as $category) {
            Category::create([
                'name' => $category,
                'module_id' => $moduleIds['invoices'],
                'team_id' => 1,
                'parent_id' => $invoiceParent->id,
                'status' => 1
            ]);
        }
        
        // Ticket Categories for Team 1
        $ticketParent = Category::create([
            'name' => 'Support Ticket Types',
            'module_id' => $moduleIds['tickets'],
            'team_id' => 1,
            'description' => 'Types of support tickets',
            'status' => 1
        ]);
        
        $ticketCategories = [
            'Technical Issue' => ['Server Error', 'Performance Problem', 'Configuration Issue', 'Feature Request'],
            'Account Management' => ['Login Issues', 'Account Settings', 'Subscription Change'],
            'Billing Questions' => ['Invoice Question', 'Payment Issue', 'Refund Request']
        ];
        
        foreach ($ticketCategories as $mainCategory => $subCategories) {
            $parent = Category::create([
                'name' => $mainCategory,
                'module_id' => $moduleIds['tickets'],
                'team_id' => 1,
                'parent_id' => $ticketParent->id,
                'status' => 1
            ]);
            
            foreach ($subCategories as $subCategory) {
                Category::create([
                    'name' => $subCategory,
                    'module_id' => $moduleIds['tickets'],
                    'team_id' => 1,
                    'parent_id' => $parent->id,
                    'status' => 1
                ]);
            }
        }
        
        // Mail Categories for Team 1
        if ($moduleIds['mail']) {
            $mailParent = Category::create([
                'name' => 'Email Categories',
                'module_id' => $moduleIds['mail'],
                'team_id' => 1,
                'description' => 'Email organization categories',
                'status' => 1
            ]);
            
            $mailCategories = [
                'Client Communications' => ['Proposals', 'Contracts', 'Project Updates', 'Follow-ups'],
                'Marketing' => ['Newsletters', 'Promotions', 'Announcements', 'Product Updates'],
                'Internal' => ['Team Communications', 'Reports', 'Administrative']
            ];
            
            foreach ($mailCategories as $mainCategory => $subCategories) {
                $parent = Category::create([
                    'name' => $mainCategory,
                    'module_id' => $moduleIds['mail'],
                    'team_id' => 1,
                    'parent_id' => $mailParent->id,
                    'status' => 1
                ]);
                
                foreach ($subCategories as $subCategory) {
                    Category::create([
                        'name' => $subCategory,
                        'module_id' => $moduleIds['mail'],
                        'team_id' => 1,
                        'parent_id' => $parent->id,
                        'status' => 1
                    ]);
                }
            }
        }
        
        // Chat Categories for Team 1
        if ($moduleIds['chat']) {
            $chatParent = Category::create([
                'name' => 'Chat Channels',
                'module_id' => $moduleIds['chat'],
                'team_id' => 1,
                'description' => 'Chat channel types',
                'status' => 1
            ]);
            
            $chatCategories = [
                'Support' => ['Technical Support', 'Billing Support', 'General Inquiries'],
                'Sales' => ['Lead Qualification', 'Product Demo', 'Pricing Discussions'],
                'Internal' => ['Team Chat', 'Project Coordination', 'General Discussion']
            ];
            
            foreach ($chatCategories as $mainCategory => $subCategories) {
                $parent = Category::create([
                    'name' => $mainCategory,
                    'module_id' => $moduleIds['chat'],
                    'team_id' => 1,
                    'parent_id' => $chatParent->id,
                    'status' => 1
                ]);
                
                foreach ($subCategories as $subCategory) {
                    Category::create([
                        'name' => $subCategory,
                        'module_id' => $moduleIds['chat'],
                        'team_id' => 1,
                        'parent_id' => $parent->id,
                        'status' => 1
                    ]);
                }
            }
        }
    }
    
    private function createTeam2Categories($moduleIds)
    {
        $this->command->info('Creating Team 2 specific categories...');
        
        // Invoice Categories for Team 2
        $invoiceParent = Category::create([
            'name' => 'Retail Invoice Types',
            'module_id' => $moduleIds['invoices'],
            'team_id' => 2,
            'description' => 'Categories for retail invoices',
            'status' => 1
        ]);
        
        $invoiceCategories = ['Product Sales', 'Services', 'Repairs', 'Subscriptions', 'Custom Work'];
        
        foreach ($invoiceCategories as $category) {
            Category::create([
                'name' => $category,
                'module_id' => $moduleIds['invoices'],
                'team_id' => 2,
                'parent_id' => $invoiceParent->id,
                'status' => 1
            ]);
        }
        
        // Support Ticket Types for Team 2
        $ticketParent = Category::create([
            'name' => 'Retail Support Issues',
            'module_id' => $moduleIds['tickets'],
            'team_id' => 2,
            'description' => 'Support issues for retail customers',
            'status' => 1
        ]);
        
        $supportCategories = ['Product Help', 'Returns', 'Warranty Claims', 'Technical Support', 'Feature Requests'];
        
        foreach ($supportCategories as $category) {
            Category::create([
                'name' => $category,
                'module_id' => $moduleIds['tickets'],
                'team_id' => 2,
                'parent_id' => $ticketParent->id,
                'status' => 1
            ]);
        }
    }
} 