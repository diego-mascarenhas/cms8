<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class bboSeeder extends Seeder
{
    public function run()
    {
        // Get the first team available or create a default one
        $team = Team::first();
        if (!$team) {
            Log::warning('No team found for bboSeeder. Skipping seeder.');
            return;
        }

        // Get the first user for creator_id and responsible_id
        $user = User::first();
        if (!$user) {
            Log::warning('No user found for bboSeeder. Skipping seeder.');
            return;
        }

        // Create BBO team users with specific roles
        $this->createBboUsers($team);

        // Create contacts
        $this->createContacts($team, $user);

        Log::info("bboSeeder completed successfully");
    }

    /**
     * Create BBO team users with their specific roles
     */
    private function createBboUsers($team)
    {
        $bboUsers = [
            [
                'name' => 'Begoña Ballester-Olmos',
                'email' => 'bego@bbosubtitulado.com',
                'role' => 'admin'
            ],
            [
                'name' => 'Claudia Caballero',
                'email' => 'claudia@bbosubtitulado.com',
                'role' => 'admin'
            ],
            [
                'name' => 'Rocío Broseta',
                'email' => 'rocio@bbosubtitulado.com',
                'role' => 'admin'
            ],
            [
                'name' => 'Marta Navas',
                'email' => 'marta@bbosubtitulado.com',
                'role' => 'admin'
            ],
            [
                'name' => 'Tom Jackson',
                'email' => 'tom@bbosubtitulado.com',
                'role' => 'admin'
            ],
            [
                'name' => 'Jesús Buendía',
                'email' => 'jesus@bbosubtitulado.com',
                'role' => 'admin'
            ],
            [
                'name' => 'Vendors',
                'email' => 'vendors@bbosubtitulado.com',
                'role' => 'admin'
            ],
            [
                'name' => 'Amy Martínez',
                'email' => 'amy@bbosubtitulado.com',
                'role' => 'admin'
            ]
        ];

        foreach ($bboUsers as $userData) {
            try {
                // Check if user already exists
                $existingUser = User::where('email', $userData['email'])->first();
                if ($existingUser) {
                    Log::info("User already exists: {$userData['email']}");
                    
                    // Make sure user is in the team
                    if (!$existingUser->teams()->where('team_id', $team->id)->exists()) {
                        $existingUser->teams()->attach($team->id);
                        Log::info("Added existing user to team: {$userData['email']}");
                    }
                    
                    // Assign role if not already assigned
                    if (!$existingUser->hasRole($userData['role'])) {
                        $existingUser->assignRole($userData['role']);
                        Log::info("Assigned role '{$userData['role']}' to existing user: {$userData['email']}");
                    }
                    
                    continue;
                }

                // Create new user
                $user = User::create([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('bbounicornio123'), // BBO admin password
                    'current_team_id' => $team->id,
                    'email_verified_at' => now(), // Mark as verified
                ]);

                // Add user to team
                $user->teams()->attach($team->id);

                // Assign role
                $user->assignRole($userData['role']);

                Log::info("Created user: {$userData['name']} ({$userData['email']}) with role: {$userData['role']}");

            } catch (\Exception $e) {
                Log::error("Error creating user {$userData['email']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Create contacts from contact data
     */
    private function createContacts($team, $user)
    {
        $contactsData = [
            [
                "name" => "Amy Sue Bennett",
                "email" => "amysuebennett@gmail.com",
                "nif_cif_vat" => "X6518552F",
                "address" => "Carrer Gregal 17",
                "postal_code" => "8757",
                "city" => "Corbera De Llobregat",
                "country" => "Reino Unido",
                "phone" => "695583557",
                "language_combinations" => [
                    ["es-ES", "en-US"], 
                    ["ca-ES", "en-US"], 
                    ["fr-FR", "en-US"]
                ],
                "valoration" => "Top",
                "software" => "EZTitles",
                "previous_collaborations" => ["PPEs", "Películas"],
                "rates" => [
                    ["Traducción + subtitulado sin guion", "3,5€"],
                    ["Traducción + subtitulado con guion", "3€"],
                    ["Traducción general", "0,06€"],
                    ["Traducción general con urgencia", "0,08 €/palabra"]
                ]
            ],
            // Add more contacts here...
            [
                "name" => "John Smith",
                "email" => "johnsmith@example.com",
                "nif_cif_vat" => "12345678A",
                "address" => "Calle Mayor 123",
                "postal_code" => "28001",
                "city" => "Madrid",
                "country" => "España",
                "phone" => "612345678",
                "language_combinations" => [
                    ["en-US", "es-ES"], 
                    ["en-US", "fr-FR"]
                ],
                "valoration" => "Premium",
                "software" => "Trados Studio",
                "previous_collaborations" => ["Documentales", "Series"],
                "rates" => [
                    ["Traducción técnica", "0,12€"],
                    ["Revisión", "0,04€"],
                    ["Localización", "0,15€"]
                ]
            ],
            [
                "name" => "Maria García",
                "email" => "maria.garcia@correo.es",
                "nif_cif_vat" => "87654321B",
                "address" => "Passeig de Gràcia 45",
                "postal_code" => "08007",
                "city" => "Barcelona",
                "country" => "España",
                "phone" => "934567890",
                "language_combinations" => [
                    ["es-ES", "ca-ES"], 
                    ["ca-ES", "fr-FR"], 
                    ["es-ES", "en-US"]
                ],
                "valoration" => "Estándar",
                "software" => "Subtitle Workshop",
                "previous_collaborations" => ["Corporativos", "Educativos"],
                "rates" => [
                    ["Subtitulado", "2,8€"],
                    ["Transcripción", "1,2€"],
                    ["Sincronización", "1,8€"]
                ]
            ]
        ];

        foreach ($contactsData as $contactData) {
            try {
                // Extract specific fields
                $name = $contactData['name'] ?? null;
                $email = $contactData['email'] ?? null;
                $phone = $contactData['phone'] ?? null;

                // Skip if essential data is missing
                if (empty($name) || empty($email)) {
                    Log::warning("Skipping contact with missing essential data: " . json_encode($contactData));
                    continue;
                }

                // Check if contact already exists
                $existingContact = Contact::where('email', $email)
                    ->where('team_id', $team->id)
                    ->first();

                if ($existingContact) {
                    Log::info("Contact already exists: {$email}");
                    continue;
                }

                // Prepare extras section with specific mappings
                $extras = [];
                
                // Map specific fields to their target names
                if (isset($contactData['language_combinations'])) {
                    $extras['contact_language_variants'] = $contactData['language_combinations'];
                }
                
                if (isset($contactData['valoration'])) {
                    $extras['contact_valorations'] = $contactData['valoration'];
                }
                
                if (isset($contactData['software'])) {
                    $extras['software'] = $contactData['software'];
                }
                
                if (isset($contactData['previous_collaborations'])) {
                    $extras['contact_portfolios'] = $contactData['previous_collaborations'];
                }
                
                if (isset($contactData['rates'])) {
                    $extras['contact_fare'] = $contactData['rates'];
                }

                // Prepare general data field (everything except mapped fields)
                $generalData = $contactData;
                unset($generalData['name']);
                unset($generalData['email']);
                unset($generalData['phone']);
                unset($generalData['language_combinations']);
                unset($generalData['valoration']);
                unset($generalData['software']);
                unset($generalData['previous_collaborations']);
                unset($generalData['rates']);

                // Create final data structure
                $dataField = [
                    'extras' => $extras,
                    'general' => $generalData
                ];

                // Create the contact
                $contact = Contact::create([
                    'team_id' => $team->id,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'status_id' => 2, // Estado de contacto = 2
                    'creator_id' => $user->id,
                    'responsible_id' => $user->id,
                    'data' => $dataField, // Store structured data as JSON
                ]);

                Log::info("Created contact: {$name} ({$email})");

            } catch (\Exception $e) {
                Log::error("Error creating contact: " . $e->getMessage());
                Log::error("Contact data: " . json_encode($contactData));
            }
        }
    }
} 