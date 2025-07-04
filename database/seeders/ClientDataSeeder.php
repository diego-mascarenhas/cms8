<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ClientDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Setting up Client Data for team_id 1...');
        
        // Get team_id 1
        $team = Team::find(1);
        if (!$team) {
            $this->command->error('Team with ID 1 not found. Please run team seeder first.');
            return;
        }

        // 1. Create/Update user with client role
        $user = User::updateOrCreate(
            ['email' => 'cliente@example.com'],
            [
                'name' => 'Cliente Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Set current team and assign role
        $user->current_team_id = $team->id;
        $user->save();

        // Add user to team if not already member
        if (!$user->teams()->where('team_id', $team->id)->exists()) {
            $user->teams()->attach($team->id);
        }

        // Assign client role
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $user->assignRole($clientRole);

        $this->command->info("✅ Created user: {$user->email} with role: client");

        // 2. Create/Update contact for this user
        $contact = Contact::updateOrCreate(
            ['email' => 'cliente@example.com', 'team_id' => $team->id],
            [
                'name' => 'Cliente',
                'surname' => 'Demo',
                'phone' => 600123456, // Store as bigInteger
                'country' => 724, // Spain
                'language' => 'es',
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
                'status_id' => 5, // Active status
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info("✅ Created contact: {$contact->name} {$contact->surname}");

        // 3. Create/Update enterprise for this contact
        $enterprise = Enterprise::updateOrCreate(
            ['name' => 'Demo Client Company', 'team_id' => $team->id],
            [
                'code' => 'DEMO-CLIENT-' . rand(1000, 9999),
                'type_id' => 1, // Client type
                'address' => 'Calle Principal 123',
                'postal_code' => '28001',
                'locality' => 'Madrid',
                'province' => 'Madrid',
                'country' => 'España',
                'phone' => '+34 600 123 456',
                'whatsapp' => '+34 600 123 456',
                'email' => 'cliente@example.com',
                'website' => 'https://democlient.com',
                'payment_type_id' => 1,
                'invoice_type_id' => 1,
                'status_id' => 1, // Active
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info("✅ Created enterprise: {$enterprise->name}");

        // 4. Associate contact with enterprise
        if (!$contact->enterprises()->where('enterprise_id', $enterprise->id)->exists()) {
            $contact->enterprises()->attach($enterprise->id, ['position' => 'Cliente Principal']);
        }

        // 5. Create Services for this enterprise
        $this->command->info('📋 Creating Services...');
        
        $services = Service::factory()
            ->count(8)
            ->create([
                'enterprise_id' => $enterprise->id,
                'responsible_id' => $user->id,
            ]);

        $this->command->info("✅ Created {$services->count()} services");

        // 6. Create Invoices for this enterprise
        $this->command->info('💰 Creating Invoices...');
        
        $invoices = Invoice::factory()
            ->count(12)
            ->create([
                'enterprise_id' => $enterprise->id,
            ]);

        // Create some specific invoice scenarios
        Invoice::factory()
            ->count(3)
            ->unpaid()
            ->overdue()
            ->create([
                'enterprise_id' => $enterprise->id,
            ]);

        Invoice::factory()
            ->count(2)
            ->highValue()
            ->recent()
            ->create([
                'enterprise_id' => $enterprise->id,
            ]);

        $this->command->info("✅ Created " . ($invoices->count() + 5) . " invoices (12 standard + 3 overdue + 2 high-value)");

        // 7. Create Projects for this enterprise
        $this->command->info('📁 Creating Projects...');
        
        $projects = Project::factory()
            ->count(5)
            ->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'responsible_id' => $user->id,
            ]);

        $this->command->info("✅ Created {$projects->count()} projects");

        // 8. Summary
        $this->command->newLine();
        $this->command->info('🎉 Client Data Setup Complete!');
        $this->command->info('==========================================');
        $this->command->info("👤 User: {$user->email} (Role: client)");
        $this->command->info("📇 Contact: {$contact->name} {$contact->surname}");
        $this->command->info("🏢 Enterprise: {$enterprise->name}");
        $this->command->info("📋 Services: {$services->count()}");
        $this->command->info("💰 Invoices: " . ($invoices->count() + 5));
        $this->command->info("📁 Projects: {$projects->count()}");
        $this->command->newLine();
        $this->command->info('🔑 Login credentials:');
        $this->command->info('Email: cliente@example.com');
        $this->command->info('Password: password');
        $this->command->newLine();
    }
}
