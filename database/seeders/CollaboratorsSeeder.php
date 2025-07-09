<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\ContactLanguageVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CollaboratorsSeeder extends Seeder
{
    private $teamId = 1; // Demo Team ID
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Setting up Demo Collaborators for Team 1...');
        
        // Demo collaborators data
        $demoCollaborators = [
            [
                'name' => 'María González',
                'email' => 'maria.gonzalez@demo.com',
                'phone' => '612345678',
                'country' => 'España',
                'city' => 'Madrid',
                'postal_code' => '28001',
                'address' => 'Calle Mayor 123',
                'valoracion' => 'Top',
                'profile' => 'Experienced translator specializing in technical documents',
                'language_combinations' => [
                    ['es-ES', 'en-US'],
                    ['en-US', 'es-ES'],
                ],
                'software' => ['Trados Studio', 'MemoQ'],
                'specialties' => ['Medical Translation', 'Legal Translation'],
            ],
            [
                'name' => 'John Smith',
                'email' => 'john.smith@demo.com',
                'phone' => '623456789',
                'country' => 'United States',
                'city' => 'New York',
                'postal_code' => '10001',
                'address' => '123 Broadway Ave',
                'valoracion' => 'Validada',
                'profile' => 'Professional subtitle translator with 5+ years experience',
                'language_combinations' => [
                    ['en-US', 'es-ES'],
                    ['en-US', 'fr-FR'],
                ],
                'software' => ['Subtitle Workshop', 'Aegisub'],
                'specialties' => ['Subtitling', 'Voice Over'],
            ],
            [
                'name' => 'Pierre Dubois',
                'email' => 'pierre.dubois@demo.com',
                'phone' => '634567890',
                'country' => 'France',
                'city' => 'Paris',
                'postal_code' => '75001',
                'address' => '123 Rue de la Paix',
                'valoracion' => 'Interesante',
                'profile' => 'Freelance translator and interpreter',
                'language_combinations' => [
                    ['fr-FR', 'en-US'],
                    ['fr-FR', 'es-ES'],
                ],
                'software' => ['SDL Trados', 'Wordfast'],
                'specialties' => ['Business Translation', 'Marketing'],
            ],
            [
                'name' => 'Anna Müller',
                'email' => 'anna.muller@demo.com',
                'phone' => '645678901',
                'country' => 'Germany',
                'city' => 'Berlin',
                'postal_code' => '10115',
                'address' => 'Unter den Linden 123',
                'valoracion' => 'Validada',
                'profile' => 'Certified translator specializing in technical and scientific content',
                'language_combinations' => [
                    ['de-DE', 'en-US'],
                    ['de-DE', 'es-ES'],
                ],
                'software' => ['Across', 'Transit NXT'],
                'specialties' => ['Technical Translation', 'Scientific Translation'],
            ],
            [
                'name' => 'Carlos Ruiz',
                'email' => 'carlos.ruiz@demo.com',
                'phone' => '656789012',
                'country' => 'México',
                'city' => 'Mexico City',
                'postal_code' => '11000',
                'address' => 'Avenida Reforma 123',
                'valoracion' => 'Top',
                'profile' => 'Localization specialist with expertise in Latin American markets',
                'language_combinations' => [
                    ['es-MX', 'en-US'],
                    ['en-US', 'es-MX'],
                ],
                'software' => ['Phrase', 'Lokalise'],
                'specialties' => ['Localization', 'Software Translation'],
            ],
        ];
        
        foreach ($demoCollaborators as $collaboratorData) {
            $this->createDemoCollaborator($collaboratorData);
        }
        
        $this->command->info('✅ Demo collaborators setup completed successfully');
    }
    
    /**
     * Create a demo collaborator
     */
    private function createDemoCollaborator($data)
    {
        try {
            $name = $data['name'];
            $email = $data['email'];
            $phone = $data['phone'];
            
            // Check if contact already exists
            $existingContact = Contact::where('email', $email)
                ->where('team_id', $this->teamId)
                ->first();
            
            if ($existingContact) {
                $this->command->warn("Contact already exists: {$name} ({$email})");
                return;
            }
            
            // Clean phone number (extract only digits)
            $cleanPhone = !empty($phone) ? preg_replace('/[^0-9]/', '', $phone) : null;
            if ($cleanPhone && strlen($cleanPhone) > 15) {
                $cleanPhone = substr($cleanPhone, -15);
            }
            
            // Map country to code
            $countryCode = $this->mapCountryToCode($data['country']);
            
            // Prepare contact data
            $contactData = [
                'city' => $data['city'],
                'postal_code' => $data['postal_code'],
                'address' => $data['address'],
                'valoracion' => $data['valoracion'],
                'software' => $data['software'],
                'specialties' => $data['specialties'],
                'demo_collaborator' => true,
            ];
            
            // Create contact
            $contact = Contact::create([
                'team_id' => $this->teamId,
                'name' => $name,
                'email' => $email,
                'phone' => $cleanPhone ? (int)$cleanPhone : null,
                'country' => $countryCode,
                'language' => 'es',
                'status_id' => 1,
                'creator_id' => 1,
                'responsible_id' => 1,
                'data' => $contactData,
                'profile' => $data['profile'],
            ]);
            
            // Create language combinations
            foreach ($data['language_combinations'] as $combination) {
                if (count($combination) === 2) {
                    $sourceLanguage = $combination[0];
                    $targetLanguage = $combination[1];
                    
                    // Validate language codes exist
                    $sourceExists = \App\Models\LanguageVariant::where('code', $sourceLanguage)->exists();
                    $targetExists = \App\Models\LanguageVariant::where('code', $targetLanguage)->exists();
                    
                    if ($sourceExists && $targetExists && $sourceLanguage !== $targetLanguage) {
                        $this->createLanguageVariant($contact, $sourceLanguage, $targetLanguage);
                    }
                }
            }
            
            // Create associated user
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('demo123'),
                'current_team_id' => $this->teamId,
                'phone' => $cleanPhone ? (int)$cleanPhone : null,
                'email_verified_at' => now(),
            ]);
            
            // Add user to team
            $user->teams()->attach($this->teamId);
            
            // Assign collaborator role
            $user->assignRole('collaborator');
            
            // Link contact to user
            $contact->update(['user_id' => $user->id]);
            
            $this->command->info("✅ Created demo collaborator: {$name} ({$email})");
            
        } catch (\Exception $e) {
            $this->command->error("Error creating demo collaborator {$data['name']}: " . $e->getMessage());
        }
    }
    
    /**
     * Create language variant record for contact
     */
    private function createLanguageVariant(Contact $contact, string $sourceCode, string $targetCode, int $proficiencyLevel = 4, bool $isCertified = true, ?string $notes = null): void
    {
        // Check if combination already exists
        $existingVariant = ContactLanguageVariant::where('contact_id', $contact->id)
            ->where('source_language_code', $sourceCode)
            ->where('target_language_code', $targetCode)
            ->first();
        
        if (!$existingVariant) {
            ContactLanguageVariant::create([
                'contact_id' => $contact->id,
                'source_language_code' => $sourceCode,
                'target_language_code' => $targetCode,
                'proficiency_level' => $proficiencyLevel,
                'is_certified' => $isCertified,
                'notes' => $notes,
            ]);
        }
    }
    
    /**
     * Map country names to country codes
     */
    private function mapCountryToCode(?string $country): int
    {
        if (empty($country)) {
            return 724; // Default to Spain
        }
        
        $countryMappings = [
            'España' => 724,
            'Spain' => 724,
            'United States' => 840,
            'USA' => 840,
            'Estados Unidos' => 840,
            'France' => 250,
            'Germany' => 276,
            'México' => 484,
            'Mexico' => 484,
            'Argentina' => 32,
            'Brasil' => 76,
            'Brazil' => 76,
            'Chile' => 152,
            'Colombia' => 170,
            'Peru' => 604,
            'Perú' => 604,
            'Uruguay' => 858,
            'Venezuela' => 862,
            'Bolivia' => 68,
            'Costa Rica' => 188,
            'Cuba' => 192,
            'República Dominicana' => 214,
            'Ecuador' => 218,
            'El Salvador' => 222,
            'Guatemala' => 320,
            'Honduras' => 340,
            'Nicaragua' => 558,
            'Panamá' => 591,
            'Paraguay' => 600,
        ];
        
        return $countryMappings[$country] ?? 724;
    }
}
