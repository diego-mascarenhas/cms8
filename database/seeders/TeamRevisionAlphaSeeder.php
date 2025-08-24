<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Helpers\GrapesJsHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeamRevisionAlphaSeeder extends Seeder
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

        // 6. Create professional email template
        $this->createProfessionalEmailTemplate();

        // 7. Configure email settings
        $this->configureRevisionAlphaEmailSettings($team);

        // Asignar módulos por defecto al equipo Revision Alpha
        $defaultModuleKeys = [
            'contacts',
            'enterprises',
            'projects',
            'services',
            'products',
            'orders',
            'ecommerce',
            'invoices',
            'payments',
            'notes',
            'tickets',
            'website',
            'hosting',
            'mail',
            'chat',
            'campaigns',
            'templates',
            'stylebooks',
            'notifications',
        ];

        foreach ($defaultModuleKeys as $moduleKey) {
            $module = Module::where('key', $moduleKey)->first();
            if ($module) {
                DB::table('module_team')->updateOrInsert([
                    'module_id' => $module->id,
                    'team_id' => $team->id,
                ], [
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("✅ Enabled module: {$module->name} ({$moduleKey})");
            } else {
                $this->command->warn("⚠️  Module not found: {$moduleKey}");
            }
        }

        $this->command->info('✅ REVISION ALPHA setup completed successfully');
    }

    /**
     * Create Revision Alpha Team
     */
    private function createRevisionAlphaTeam()
    {
        $revisionUser = User::where('email', 'diego.mascarenhas@icloud.com')->first();

        if (! $revisionUser) {
            $this->command->error('Revision user not found. Please run UserSeeder first.');

            return null;
        }

        $team = Team::updateOrCreate(
            ['name' => "REVISION ALPHA's Team"],
            [
                'user_id' => $revisionUser->id,
                'name' => "revision alpha's Team",
                'personal_team' => false,
            ],
        );

        // Ensure the user is in the team
        if (! $team->users()->where('user_id', $revisionUser->id)->exists()) {
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

        // Create Fernando Barneto - revision alpha
        $fernando = User::updateOrCreate(
            ['email' => 'fernando@revisionalpha.com'],
            [
                'name' => 'Fernando Barneto',
                'email' => 'fernando@revisionalpha.com',
				'phone' => '34616333128',
                'password' => Hash::make('@PabloHDP!'),
                'email_verified_at' => now(),
                'current_team_id' => $team->id,
            ]
        );
        $fernando->assignRole(2);

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

        // Obtener los módulos de contactos y empresas
        $contactsModuleId = Module::where('key', 'contacts')->first()?->id;
        $enterprisesModuleId = Module::where('key', 'enterprises')->first()?->id;

        // Categorías para el módulo de contactos
        // 1. Crear categoría principal
        $mainContactCategory = Category::updateOrCreate([
            'name' => 'Contactos',
            'module_id' => $contactsModuleId,
            'team_id' => $this->teamId,
            'parent_id' => null,
        ], [
            'description' => 'Categoría principal para contactos',
            'status' => 1,
        ]);

        // 2. Crear subcategorías con parent_id apuntando a la principal
        Category::updateOrCreate([
            'name' => 'Staff',
            'module_id' => $contactsModuleId,
            'team_id' => $this->teamId,
        ], [
            'description' => 'Contactos internos del equipo',
            'parent_id' => $mainContactCategory->id,
            'status' => 1,
        ]);
        Category::updateOrCreate([
            'name' => 'CMS+',
            'module_id' => $contactsModuleId,
            'team_id' => $this->teamId,
        ], [
            'description' => 'Contactos importados de CMS+',
            'parent_id' => $mainContactCategory->id,
            'status' => 1,
        ]);
        Category::updateOrCreate([
            'name' => 'Contacto Potencial',
            'module_id' => $contactsModuleId,
            'team_id' => $this->teamId,
        ], [
            'description' => 'Contactos interesados en nuestros servicios',
            'parent_id' => $mainContactCategory->id,
            'status' => 1,
        ]);
        Category::updateOrCreate([
            'name' => 'Referido',
            'module_id' => $contactsModuleId,
            'team_id' => $this->teamId,
        ], [
            'description' => 'Contactos referidos por clientes',
            'parent_id' => $mainContactCategory->id,
            'status' => 1,
        ]);

        // Categorías para el módulo de empresas
        Category::updateOrCreate([
            'name' => 'Cliente Premium',
            'module_id' => $enterprisesModuleId,
        ], [
            'team_id' => $this->teamId,
            'description' => 'Empresas con contrato premium',
            'parent_id' => null,
            'status' => 1,
        ]);
        Category::updateOrCreate([
            'name' => 'Proveedor Estratégico',
            'module_id' => $enterprisesModuleId,
        ], [
            'team_id' => $this->teamId,
            'description' => 'Empresas proveedoras clave',
            'parent_id' => null,
            'status' => 1,
        ]);

        $this->command->info('✅ Created Revision Alpha categories');
    }

    /**
     * Create professional email template with logos and better design
     */
    private function createProfessionalEmailTemplate(): void
    {
        $this->command->info('🎨 Creating professional email template for Revision Alpha team...');

        $template = Template::updateOrCreate(
            [
                'name' => 'Email Marketing fácil, rápido y seguro',
                'team_id' => $this->teamId,
            ],
            [
                'status_id' => 1,
                'gjs_data' => [
                    'css' => '
                        * {
                            padding: 0;
                            margin: 0;
                            line-height: 1.5;
                        }

                        body {
                            font-family: helvetica, arial, verdana, sans-serif;
                        }

                        h1, h2, h3, h4, h5, h6, strong {
                            font-weight: 600;
                        }

                        p, span, a, td {
                            font-size: 14px;
                            font-weight: 300;
                            color: #777777;
                        }

                        a {
                            text-decoration: none;
                        }

                        a:hover {
                            text-decoration: underline;
                        }
                    ',
                    'html' => '
                        <table width="100%" bgcolor="#F5EFEF" border="0" cellpadding="0" cellspacing="0" style="font-family: helvetica, arial, verdana, sans-serif;">
                            <tr>
                                <td align="center">
                                    <table width="660" bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0">
                                        <!-- Header with Logo -->
                                        <tr>
                                            <td style="padding: 30px 40px 20px 40px;">
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td align="left" style="vertical-align: middle;">
                                                            <img src="' . asset('assets/logo-revision-alpha.png') . '" alt="REVISION ALPHA" style="height: 40px; display: block;" />
                                                        </td>
                                                        <td align="right" style="vertical-align: middle;">
                                                            <div style="text-align: right; margin-bottom: 10px;">
                                                                <!-- Date placeholder removed -->
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>

                                        <!-- Main Title -->
                                        <tr>
                                            <td style="padding: 0 40px 30px 40px;">
                                                <h1 style="font-size: 28px; color: #2a333d; margin: 0; font-weight: 700">Email Marketing fácil, rápido y seguro</h1>
                                            </td>
                                        </tr>

                                        <!-- Content Section -->
                                        <tr>
                                            <td style="padding: 0 40px 30px 40px;">
                                                <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">
                                                    Hola {{name}},
                                                </p>
                                                <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">
                                                    Te damos la bienvenida a <strong>REVISION ALPHA Emailer</strong>, la plataforma de email marketing más fácil, rápida y segura del mercado.
                                                </p>
                                                <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 30px 0;">
                                                    Con nuestro sistema podrás crear campañas profesionales, hacer seguimiento detallado de tus envíos y aumentar tus conversiones de manera efectiva.
                                                </p>
                                            </td>
                                        </tr>

                                        <!-- CTA Button -->
                                        <tr>
                                            <td style="padding: 0 40px 40px 40px; text-align: center;">
                                                <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                                    <tr>
                                                        <td style="background-color: #007bff; border-radius: 6px; padding: 15px 30px;">
                                                            <a href="https://revisionalpha.com/emailer" style="color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; display: block;">
                                                                <strong>¡Empieza ahora!</strong>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>

                                        <!-- Features Section -->
                                        <tr>
                                            <td style="padding: 0 40px 40px 40px;">
                                                <h2 style="font-size: 20px; color: #2a333d; margin: 0 0 20px 0; font-weight: 600;">¿Por qué elegir REVISION ALPHA?</h2>
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="padding: 10px 0; vertical-align: top;">
                                                            <div style="display: inline-block; vertical-align: top; margin-right: 10px;">
                                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="#28a745" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                            </div>
                                                            <strong style="color: #2a333d;">100% GRATUITO</strong> - Sin costos ocultos ni límites
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0; vertical-align: top;">
                                                            <div style="display: inline-block; vertical-align: top; margin-right: 10px;">
                                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M13 10V3L4 14H11V21L20 10H13Z" stroke="#ffc107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                            </div>
                                                            <strong style="color: #2a333d;">Súper Rápido</strong> - Crea campañas en minutos
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding: 10px 0; vertical-align: top;">
                                                            <div style="display: inline-block; vertical-align: top; margin-right: 10px;">
                                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="#17a2b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                            </div>
                                                            <strong style="color: #2a333d;">Máxima Seguridad</strong> - Tus datos siempre protegidos
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>

                                        <!-- Footer -->
                                        <tr>
                                            <td>
                                                <table width="100%" bgcolor="#2A333D" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="padding: 30px 40px;">
                                                            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                                                <tr>
                                                                    <td align="left" style="vertical-align: top;">
                                                                        <img src="' . asset('assets/logo.png') . '" alt="Logo" style="height: 30px; display: block;" />
                                                                        <p style="color: #ffffff; font-size: 12px; margin: 10px 0 0 0;">
                                                                            <strong>¡Gracias por confiar en nosotros!</strong>
                                                                        </p>
                                                                    </td>
                                                                    <td align="right">
                                                                        <span style="color: #ffffff"
                                                                            ><strong>WhatsApp:</strong>
                                                                            <a href="https://api.whatsapp.com/send/?phone=12202137800&text=Hola!"
                                                                               style="color: #ffffff !important; text-decoration: none;"
                                                                               target="_blank">+1 (220) 213-7800</a
                                                                            ><br />
                                                                            <strong>Email:</strong>
                                                                            <a
                                                                                href="mailto:info@revisionalpha.com?subject=Consulta"
                                                                                style="color: inherit"
                                                                                >info@revisionalpha.com</a
                                                                            ></span
                                                                        >
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    ',
                    'styles' => json_encode([]),
                    'components' => json_encode([]),
                ]
            ]
        );

        $this->command->info("✅ Professional template created: {$template->name} (ID: {$template->id})");

        // Fix GrapesJS structure
        try {
            $result = GrapesJsHelper::fixTemplateStructure($template);
            if ($result) {
                $this->command->info("✅ Fixed GrapesJS structure for template: {$template->name}");
            } else {
                $this->command->warn("⚠️ Failed to fix GrapesJS structure for template: {$template->name}");
            }
        } catch (\Exception $e) {
            $this->command->error("❌ Error fixing template structure: " . $e->getMessage());
        }

        // Show editor URL for reference
        $editorUrl = route('template.editor', $template->getHashedId());
        $this->command->info("🔗 Editor URL: {$editorUrl}");
    }

    /**
     * Configure email settings for Revision Alpha team
     */
    private function configureRevisionAlphaEmailSettings(Team $team): void
    {
        $this->command->info('📧 Configuring Revision Alpha email settings...');

        // Email Configuration > Sender Information
        $team->setSetting('mail_from_name', 'REVISION ALPHA Marketing', [
            'type' => 'string',
            'group' => 'email',
            'is_encrypted' => false,
        ]);

        $team->setSetting('mail_from_address', 'mkt@revisionalpha.net', [
            'type' => 'string',
            'group' => 'email',
            'is_encrypted' => false,
        ]);

        // Notification Settings > Sender Information
        $team->setSetting('notification_from_name', 'REVISION ALPHA', [
            'type' => 'string',
            'group' => 'notifications',
            'is_encrypted' => false,
        ]);

        $team->setSetting('notification_from_address', 'info@revisionalpha.com', [
            'type' => 'string',
            'group' => 'notifications',
            'is_encrypted' => false,
        ]);

        $this->command->info('✅ Revision Alpha email settings configured successfully!');
        $this->command->info('   Email Configuration:');
        $this->command->info('   - From Name: REVISION ALPHA Marketing');
        $this->command->info('   - From Email: mkt@revisionalpha.net');
        $this->command->info('   Notification Settings:');
        $this->command->info('   - From Name: REVISION ALPHA');
        $this->command->info('   - From Email: info@revisionalpha.com');
    }
}
