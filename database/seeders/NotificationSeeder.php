<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing teams, users, contacts, and notification types
        $teams = Team::all();
        $users = User::all();
        $contacts = Contact::all();
        $notificationTypes = NotificationType::all();

        if ($teams->isEmpty() || $users->isEmpty() || $contacts->isEmpty() || $notificationTypes->isEmpty()) {
            $this->command->info('Make sure you have teams, users, contacts, and notification types before running this seeder.');
            return;
        }

        // Create various types of notifications
        $this->command->info('Creating general notifications...');
        
        // Create 15 random notifications with mixed states
        Notification::factory()
            ->count(15)
            ->state(function () use ($users, $contacts, $notificationTypes) {
                return [
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $contacts->random()->id,
                    'type_id' => $notificationTypes->random()->id,
                ];
            })
            ->create();

        $this->command->info('Creating project-related notifications...');
        
        // Create 8 project-related notifications
        Notification::factory()
            ->count(8)
            ->projectRelated()
            ->state(function () use ($users, $contacts, $notificationTypes) {
                return [
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $contacts->random()->id,
                    'type_id' => $notificationTypes->where('name', 'Project Assignment')->first()?->id ?? $notificationTypes->random()->id,
                ];
            })
            ->create();

        $this->command->info('Creating payment-related notifications...');
        
        // Create 5 payment-related notifications
        Notification::factory()
            ->count(5)
            ->paymentRelated()
            ->state(function () use ($users, $contacts, $notificationTypes) {
                return [
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $contacts->random()->id,
                    'type_id' => $notificationTypes->where('name', 'Payment Reminder')->first()?->id ?? $notificationTypes->random()->id,
                ];
            })
            ->create();

        $this->command->info('Creating urgent notifications...');
        
        // Create 3 urgent notifications
        Notification::factory()
            ->count(3)
            ->urgent()
            ->sentUnread()
            ->state(function () use ($users, $contacts, $notificationTypes) {
                return [
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $contacts->random()->id,
                    'type_id' => $notificationTypes->random()->id,
                ];
            })
            ->create();

        $this->command->info('Creating unsent notifications...');
        
        // Create 5 unsent notifications
        Notification::factory()
            ->count(5)
            ->unsent()
            ->state(function () use ($users, $contacts, $notificationTypes) {
                return [
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $contacts->random()->id,
                    'type_id' => $notificationTypes->random()->id,
                ];
            })
            ->create();

        $this->command->info('Creating sent and read notifications...');
        
        // Create 7 sent and read notifications
        Notification::factory()
            ->count(7)
            ->sentRead()
            ->state(function () use ($users, $contacts, $notificationTypes) {
                return [
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $contacts->random()->id,
                    'type_id' => $notificationTypes->random()->id,
                ];
            })
            ->create();

        // Create specific notifications for the first contact if available
        if ($contacts->count() > 0) {
            $firstContact = $contacts->first();
            $this->command->info("Creating specific notifications for contact: {$firstContact->name}...");
            
            // Welcome notification
            Notification::factory()
                ->sentRead()
                ->state([
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $firstContact->id,
                    'type_id' => $notificationTypes->where('name', 'Welcome Message')->first()?->id ?? $notificationTypes->random()->id,
                    'subject' => '¡Bienvenido al equipo, ' . $firstContact->name . '!',
                    'message' => 'Nos complace darte la bienvenida a nuestro equipo de traductores profesionales. Esperamos trabajar contigo en proyectos emocionantes.',
                ])
                ->create();

            // Recent project assignment
            Notification::factory()
                ->sentUnread()
                ->projectRelated()
                ->state([
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $firstContact->id,
                    'type_id' => $notificationTypes->where('name', 'Project Assignment')->first()?->id ?? $notificationTypes->random()->id,
                ])
                ->create();

            // Pending notification
            Notification::factory()
                ->unsent()
                ->state([
                    'team_id' => 1,
                    'user_id' => $users->random()->id,
                    'contact_id' => $firstContact->id,
                    'type_id' => $notificationTypes->where('name', 'General Message')->first()?->id ?? $notificationTypes->random()->id,
                    'subject' => 'Actualización importante de perfil',
                    'message' => 'Hola ' . $firstContact->name . ', necesitamos que actualices tu información de perfil para mejorar nuestro servicio.',
                ])
                ->create();
        }

        $this->command->info('Notification seeding completed successfully!');
        $this->command->info('Total notifications created: ' . Notification::count());
    }
}
