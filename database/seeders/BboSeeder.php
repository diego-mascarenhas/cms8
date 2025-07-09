<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactLanguageVariant;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BboSeeder extends Seeder
{
    private $teamId = 4; // BBO Team ID
    
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
        
        // 6. Import BBO collaborators from SQL
        $this->importBboCollaborators($team);
        
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
                'email' => 'bego@bbosubtitulado.com',
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
                    'password' => Hash::make('bbounicornio123'),
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

    /**
     * Import BBO collaborators from SQL file
     */
    private function importBboCollaborators($team)
    {
        $this->command->info('📄 Importing BBO collaborators from SQL...');
        
        // Get the SQL file path
        $sqlFilePath = base_path('../db/bbo.sql');

        if (!file_exists($sqlFilePath)) {
            Log::error("SQL file not found: {$sqlFilePath}");
            $this->command->error("SQL file not found: {$sqlFilePath}");
            return;
        }

        $sqlContent = file_get_contents($sqlFilePath);

        // Parse INSERT statements
        preg_match_all(
            '/INSERT INTO colaboradoras \([^)]+\) VALUES \(([^;]+)\);/',
            $sqlContent,
            $matches
        );

        if (empty($matches[1])) {
            Log::error("No INSERT statements found in SQL file");
            $this->command->error("No INSERT statements found in SQL file");
            return;
        }

        $this->command->info("Found " . count($matches[1]) . " BBO collaborators to import");

        $teamId = $team->id;
        $defaultCountry = 724; // Spain
        $defaultLanguage = 'es';
        $defaultStatusId = 1;
        $collaboratorRoleId = 3; // collaborator role ID

        foreach ($matches[1] as $valueString) {
            try {
                // Parse values from SQL string
                $values = $this->parseValues($valueString);

                if (count($values) < 13) {
                    Log::warning("Skipping incomplete record: " . substr($valueString, 0, 100));
                    continue;
                }

                $name = $this->cleanValue($values[0]);
                $email = $this->cleanValue($values[1]);
                $phone = $this->cleanValue($values[2]);
                $country = $this->cleanValue($values[3]);
                $city = $this->cleanValue($values[4]);
                $postalCode = $this->cleanValue($values[5]);
                $nifCif = $this->cleanValue($values[6]);
                $address = $this->cleanValue($values[7]);
                $valoracion = $this->cleanValue($values[8]);
                $tarifas = $this->cleanValue($values[9]);
                $combinacion = $this->cleanValue($values[10]);
                $servicios = $this->cleanValue($values[11]);
                $programas = $this->cleanValue($values[12]);

                // Skip if name is empty
                if (empty($name)) {
                    Log::warning("Skipping record with empty name");
                    continue;
                }

                // Check if contact already exists
                $existingContact = null;
                if (!empty($email)) {
                    $existingContact = Contact::where('email', $email)
                        ->where('team_id', $teamId)
                        ->first();
                }

                if ($existingContact) {
                    $this->command->warn("Contact already exists: {$name} ({$email})");
                    continue;
                }

                // Clean phone number (extract only digits)
                $cleanPhone = !empty($phone) ? preg_replace('/[^0-9]/', '', $phone) : null;
                if ($cleanPhone && strlen($cleanPhone) > 15) {
                    $cleanPhone = substr($cleanPhone, -15); // Keep last 15 digits
                }

                // Map country names to IDs that exist in the database
                $countryCode = $this->mapCountryToCode($country);

                // Create additional data array
                $additionalData = [
                    'city' => $city,
                    'postal_code' => $postalCode,
                    'nif_cif' => $nifCif,
                    'address' => $address,
                    'valoracion' => $valoracion,
                    'original_country' => $country,
                    'imported_from_bbo' => true,
                ];

                // Add parsed JSON data if available
                if (!empty($tarifas) && $tarifas !== 'NULL') {
                    $additionalData['tarifas'] = $this->parseJsonField($tarifas);
                }
                if (!empty($combinacion) && $combinacion !== 'NULL') {
                    $additionalData['combinaciones'] = $this->parseJsonField($combinacion);
                }
                if (!empty($servicios) && $servicios !== 'NULL') {
                    $additionalData['servicios'] = $this->parseJsonField($servicios);
                }
                if (!empty($programas) && $programas !== 'NULL') {
                    $additionalData['programas'] = $this->parseJsonField($programas);
                }

                // Create contact
                $contact = Contact::create([
                    'team_id' => $teamId,
                    'name' => $name,
                    'email' => $email ?: null,
                    'phone' => $cleanPhone ? (int)$cleanPhone : null,
                    'country' => $countryCode,
                    'language' => $defaultLanguage,
                    'status_id' => $defaultStatusId,
                    'creator_id' => 1, // Admin user
                    'responsible_id' => 1, // Admin user
                    'data' => $additionalData,
                    'profile' => 'BBO Collaborator imported from legacy system',
                ]);

                // Process language combinations if available
                if (!empty($combinacion) && $combinacion !== 'NULL') {
                    $this->processLanguageCombinations($contact, $combinacion);
                }

                // Process rates and software
                $tarifasNoImportadas = [];
                $softwaresNoImportados = [];

                // Tarifas
                if (!empty($tarifas) && $tarifas !== 'NULL') {
                    $tarifasArray = $this->parseJsonField($tarifas);
                    if (is_array($tarifasArray)) {
                        foreach ($tarifasArray as $tarifa) {
                            $tarifaName = is_array($tarifa) ? ($tarifa[0] ?? null) : $tarifa;
                            if ($tarifaName) {
                                $fare = \App\Models\Fare::where('name', $tarifaName)
                                    ->where('team_id', $teamId)
                                    ->first();
                                if ($fare) {
                                    $contact->fares()->syncWithoutDetaching([
                                        $fare->id => []
                                    ]);
                                } else {
                                    $tarifasNoImportadas[] = $tarifaName;
                                }
                            }
                        }
                    }
                }

                // Programas (Software)
                if (!empty($programas) && $programas !== 'NULL') {
                    $programasArray = $this->parseJsonField($programas);
                    if (is_array($programasArray)) {
                        foreach ($programasArray as $programa) {
                            $programaName = is_array($programa) ? ($programa[0] ?? null) : $programa;
                            if ($programaName) {
                                $software = \App\Models\Software::where('name', $programaName)
                                    ->where('team_id', $teamId)
                                    ->first();
                                if ($software) {
                                    $contact->softwares()->syncWithoutDetaching([$software->id]);
                                } else {
                                    $softwaresNoImportados[] = $programaName;
                                }
                            }
                        }
                    }
                }

                // Save unimported data
                $updateData = is_object($contact->data) ? (array)$contact->data : ($contact->data ?? []);
                if (!empty($tarifasNoImportadas)) {
                    $updateData['unimported_fares'] = $tarifasNoImportadas;
                }
                if (!empty($softwaresNoImportados)) {
                    $updateData['unimported_software'] = $softwaresNoImportados;
                }
                if (!empty($tarifasNoImportadas) || !empty($softwaresNoImportados)) {
                    $contact->data = $updateData;
                    $contact->save();
                }

                // Create associated user if email is provided
                if (!empty($email)) {
                    $existingUser = User::where('email', $email)->first();

                    if (!$existingUser) {
                        $user = User::create([
                            'name' => $name,
                            'email' => $email,
                            'password' => Hash::make('bbounicornio123'),
                            'current_team_id' => $teamId,
                            'phone' => $cleanPhone ? (int)$cleanPhone : null,
                            'email_verified_at' => now(),
                        ]);

                        // Add user to team
                        $user->teams()->attach($teamId);

                        // Assign collaborator role
                        $user->assignRole('collaborator');

                        // Link contact to user
                        $contact->update(['user_id' => $user->id]);

                        $this->command->info("✅ Created BBO user and contact: {$name} ({$email})");
                    } else {
                        // Link existing user to contact
                        $contact->update(['user_id' => $existingUser->id]);

                        // Ensure user has collaborator role
                        if (!$existingUser->hasRole('collaborator')) {
                            $existingUser->assignRole('collaborator');
                        }

                        $this->command->info("✅ Linked existing user to BBO contact: {$name} ({$email})");
                    }
                } else {
                    $this->command->info("✅ Created BBO contact without user: {$name} (no email)");
                }

            } catch (\Exception $e) {
                Log::error("Error importing BBO collaborator: " . $e->getMessage());
                Log::error("Value string: " . substr($valueString, 0, 200));
                $this->command->error("Error importing BBO collaborator: " . $e->getMessage());
            }
        }

        $this->command->info("✅ BBO collaborators import completed!");

        // Process language combinations for existing contacts
        $this->command->info("Processing language combinations for existing BBO contacts...");
        $this->processExistingContactCombinations($teamId);
    }

    /**
     * Parse SQL VALUES string into array
     */
    private function parseValues(string $valueString): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $i = 0;

        while ($i < strlen($valueString)) {
            $char = $valueString[$i];

            if ($char === "'" || $char === '"') {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    // Check for escaped quote
                    if ($i + 1 < strlen($valueString) && $valueString[$i + 1] === $quoteChar) {
                        $current .= $char;
                        $i++; // Skip next quote
                    } else {
                        $inQuotes = false;
                        $quoteChar = null;
                    }
                } else {
                    $current .= $char;
                }
            } elseif ($char === ',' && !$inQuotes) {
                $values[] = trim($current);
                $current = '';
            } else {
                if ($inQuotes || trim($char) !== '') {
                    $current .= $char;
                }
            }

            $i++;
        }

        // Add the last value
        if ($current !== '') {
            $values[] = trim($current);
        }

        return $values;
    }

    /**
     * Clean and normalize values
     */
    private function cleanValue(string $value): ?string
    {
        $value = trim($value);

        // Remove quotes
        if ((str_starts_with($value, "'") && str_ends_with($value, "'")) ||
            (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            $value = substr($value, 1, -1);
        }

        // Handle NULL values
        if (strtoupper($value) === 'NULL' || $value === '') {
            return null;
        }

        // Unescape quotes
        $value = str_replace(["''", '""'], ["'", '"'], $value);

        return trim($value);
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
            'Argentina' => 32,
            'Bolivia' => 68,
            'Brasil' => 76,
            'Brazil' => 76,
            'Chile' => 152,
            'Colombia' => 170,
            'Costa Rica' => 188,
            'Cuba' => 192,
            'República Dominicana' => 214,
            'Dominican Republic' => 214,
            'Ecuador' => 218,
            'El Salvador' => 222,
            'Estados Unidos' => 840,
            'United States' => 840,
            'EEUU' => 840,
            'USA' => 840,
            'US' => 840,
            'Guatemala' => 320,
            'Honduras' => 340,
            'México' => 484,
            'Mexico' => 484,
            'Nicaragua' => 558,
            'Panamá' => 591,
            'Panama' => 591,
            'Paraguay' => 600,
            'Perú' => 604,
            'Peru' => 604,
            'Uruguay' => 858,
            'Venezuela' => 862,
            'France' => 250,
            'Germany' => 276,
        ];

        return $countryMappings[$country] ?? 724;
    }

    /**
     * Process language combinations for existing contacts
     */
    private function processExistingContactCombinations(int $teamId): void
    {
        $contacts = Contact::where('team_id', $teamId)
            ->whereNotNull('data')
            ->get();

        $processedCount = 0;

        foreach ($contacts as $contact) {
            $data = is_object($contact->data) ? (array)$contact->data : $contact->data;

            if (isset($data['combinaciones']) && !empty($data['combinaciones'])) {
                try {
                    // Check if it's raw_data format
                    if (is_array($data['combinaciones']) && isset($data['combinaciones']['raw_data'])) {
                        $this->processRawDataCombinations($contact, $data['combinaciones']['raw_data']);
                    } else {
                        // Convert back to JSON string for processing
                        $combinacionData = json_encode($data['combinaciones']);
                        $this->processLanguageCombinations($contact, $combinacionData);
                    }
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::error("Error processing existing combinations for {$contact->name}: " . $e->getMessage());
                }
            }
        }

        $this->command->info("Processed language combinations for {$processedCount} existing BBO contacts.");
    }

    /**
     * Process raw data combinations from escaped JSON
     */
    private function processRawDataCombinations(Contact $contact, string $rawData): void
    {
        try {
            // Clean up escaped characters
            $cleanData = $rawData;
            $cleanData = str_replace('\\"', '"', $cleanData);
            $cleanData = str_replace('\\\\', '\\', $cleanData);
            $cleanData = stripslashes($cleanData);

            // Parse JSON
            $combinations = json_decode($cleanData, true);

            if (!is_array($combinations)) {
                Log::warning("Could not parse raw data combinations for contact: {$contact->name}");
                return;
            }

            // Process each combination
            foreach ($combinations as $combination) {
                if (is_array($combination) && count($combination) === 2) {
                    $sourceLanguage = $this->mapLanguageNameToCode($combination[0]);
                    $targetLanguage = $this->mapLanguageNameToCode($combination[1]);

                    if ($sourceLanguage && $targetLanguage && $sourceLanguage !== $targetLanguage) {
                        $this->createLanguageVariant($contact, $sourceLanguage, $targetLanguage);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("Error processing raw data combinations for contact {$contact->name}: " . $e->getMessage());
        }
    }

    /**
     * Process language combinations for a contact
     */
    private function processLanguageCombinations(Contact $contact, string $combinacionData): void
    {
        try {
            // Parse the combinations data
            $combinations = $this->parseJsonField($combinacionData);

            if (!$combinations || !is_array($combinations)) {
                Log::warning("Could not parse language combinations for contact: {$contact->name}");
                return;
            }

            // Process each combination
            foreach ($combinations as $combination) {
                if (is_string($combination)) {
                    $this->processSingleCombination($contact, $combination);
                } elseif (is_array($combination)) {
                    $this->processArrayCombination($contact, $combination);
                }
            }

        } catch (\Exception $e) {
            Log::error("Error processing language combinations for contact {$contact->name}: " . $e->getMessage());
        }
    }

    /**
     * Process single language combination string
     */
    private function processSingleCombination(Contact $contact, string $combination): void
    {
        $separators = ['->', '>', '←', '<', '-', '/', '|'];
        $languages = null;

        foreach ($separators as $separator) {
            if (strpos($combination, $separator) !== false) {
                $languages = array_map('trim', explode($separator, $combination, 2));
                break;
            }
        }

        if (!$languages || count($languages) !== 2) {
            Log::warning("Could not parse combination: {$combination}");
            return;
        }

        $sourceLanguage = $this->mapLanguageNameToCode($languages[0]);
        $targetLanguage = $this->mapLanguageNameToCode($languages[1]);

        if ($sourceLanguage && $targetLanguage && $sourceLanguage !== $targetLanguage) {
            $this->createLanguageVariant($contact, $sourceLanguage, $targetLanguage);
        }
    }

    /**
     * Process array format language combination
     */
    private function processArrayCombination(Contact $contact, array $combination): void
    {
        if (isset($combination['source']) && isset($combination['target'])) {
            $sourceLanguage = $this->mapLanguageNameToCode($combination['source']);
            $targetLanguage = $this->mapLanguageNameToCode($combination['target']);

            if ($sourceLanguage && $targetLanguage && $sourceLanguage !== $targetLanguage) {
                $proficiencyLevel = $combination['level'] ?? 4;
                $isCertified = $combination['certified'] ?? true;
                $notes = $combination['notes'] ?? null;

                $this->createLanguageVariant($contact, $sourceLanguage, $targetLanguage, $proficiencyLevel, $isCertified, $notes);
            }
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
     * Map language names to language variant codes
     */
    private function mapLanguageNameToCode(string $languageName): ?string
    {
        // First, check if it's already a valid language code
        if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $languageName)) {
            $variant = \App\Models\LanguageVariant::where('code', $languageName)->first();
            if ($variant) {
                return $languageName;
            }
        }

        $languageMapping = [
            // Spanish variants
            'español' => 'es-ES',
            'castellano' => 'es-ES',
            'spanish' => 'es-ES',
            'español españa' => 'es-ES',
            'español mexicano' => 'es-MX',
            'español méxico' => 'es-MX',
            'español argentino' => 'es-AR',
            'español colombia' => 'es-CO',
            'español chile' => 'es-CL',
            'español perú' => 'es-PE',
            'español venezuela' => 'es-VE',
            'espanhol' => 'es-ES',

            // English variants
            'inglés' => 'en-US',
            'english' => 'en-US',
            'inglés americano' => 'en-US',
            'inglés británico' => 'en-GB',
            'inglés reino unido' => 'en-GB',
            'inglés canadiense' => 'en-CA',
            'inglés australia' => 'en-AU',
            'anglès' => 'en-US',

            // French variants
            'francés' => 'fr-FR',
            'french' => 'fr-FR',
            'français' => 'fr-FR',
            'francés francia' => 'fr-FR',
            'francés canadiense' => 'fr-CA',
            'francés bélgica' => 'fr-BE',
            'francés suiza' => 'fr-CH',

            // German variants
            'alemán' => 'de-DE',
            'german' => 'de-DE',
            'deutsch' => 'de-DE',
            'alemán alemania' => 'de-DE',
            'alemán austria' => 'de-AT',
            'alemán suiza' => 'de-CH',

            // Italian variants
            'italiano' => 'it-IT',
            'italian' => 'it-IT',
            'italiano italia' => 'it-IT',
            'italiano suiza' => 'it-CH',

            // Portuguese variants
            'portugués' => 'pt-PT',
            'portuguese' => 'pt-PT',
            'português' => 'pt-PT',
            'portugués portugal' => 'pt-PT',
            'portugués brasil' => 'pt-BR',
            'portugués brasileño' => 'pt-BR',

            // Catalan variants
            'catalán' => 'ca-ES',
            'catalan' => 'ca-ES',
            'català' => 'ca-ES',
            'catalán españa' => 'ca-ES',
            'catalán andorra' => 'ca-AD',

            // Language codes used in the SQL data
            'es-ES' => 'es-ES',
            'es-MX' => 'es-MX',
            'es-AR' => 'es-AR',
            'es-CO' => 'es-CO',
            'es-CL' => 'es-CL',
            'es-PE' => 'es-PE',
            'es-VE' => 'es-VE',
            'en-US' => 'en-US',
            'en-GB' => 'en-GB',
            'en-CA' => 'en-CA',
            'en-AU' => 'en-AU',
            'fr-FR' => 'fr-FR',
            'fr-CA' => 'fr-CA',
            'fr-BE' => 'fr-BE',
            'fr-CH' => 'fr-CH',
            'de-DE' => 'de-DE',
            'de-AT' => 'de-AT',
            'de-CH' => 'de-CH',
            'it-IT' => 'it-IT',
            'it-CH' => 'it-CH',
            'pt-PT' => 'pt-PT',
            'pt-BR' => 'pt-BR',
            'ca-ES' => 'ca-ES',
            'ca-AD' => 'ca-AD',
        ];

        $normalizedName = strtolower(trim($languageName));
        return $languageMapping[$normalizedName] ?? null;
    }

    /**
     * Parse JSON field from SQL
     */
    private function parseJsonField(string $jsonString): ?array
    {
        if (empty($jsonString) || $jsonString === 'NULL') {
            return null;
        }

        // Try to decode as JSON
        $decoded = json_decode($jsonString, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // If JSON decode fails, return as string
        return ['raw_data' => $jsonString];
    }
}
