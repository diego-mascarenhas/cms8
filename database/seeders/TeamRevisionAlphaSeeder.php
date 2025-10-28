<?php

namespace Database\Seeders;

use App\Helpers\GrapesJsHelper;
use App\Models\Category;
use App\Models\Module;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeamRevisionAlphaSeeder extends Seeder
{
    private $teamId;

    /**
     * Set the command context for the seeder
     */
    public function setCommand($command)
    {
        $this->command = $command;
    }

    /**
     * Get command instance or create a dummy one
     */
    private function getCommand()
    {
        if (! $this->command)
        {
            // Create a dummy command instance for output
            $this->command = new class
            {
                public function info($message)
                {
                    echo "INFO: $message\n";
                }

                public function warn($message)
                {
                    echo "WARN: $message\n";
                }

                public function error($message)
                {
                    echo "ERROR: $message\n";
                }
            };
        }

        return $this->command;
    }

    public function run()
    {
        $this->getCommand()->info('🚀 Setting up Revision Alpha Data...');

        // 1. Create Revision Alpha Team
        $team = $this->createRevisionAlphaTeam();
        $this->teamId = $team->id;

        // 2. Create Revision Alpha users
        $this->createRevisionAlphaUsers($team);

        // 3. Create Revision Alpha categories
        $this->createRevisionAlphaCategories();

        // 3.1. Create Revision Alpha task categories
        $this->createRevisionAlphaTaskCategories();

        // 4. Create professional email template
        $this->createProfessionalEmailTemplate();

        // 5. Create demo message for Staff category
        $this->createDemoMessage();

        // 6. Configure email settings
        $this->configureRevisionAlphaEmailSettings($team);

        // 7. Note: Data import is handled by import:interactive --auto command in deployment
        $this->getCommand()->info('');
        $this->getCommand()->info('ℹ️  Data import will be handled by: php artisan import:interactive --auto');

        // 8. Assign core modules to team
        $this->assignCoreModules($team);

        // 9. Configure team shortcuts
        $this->configureTeamShortcuts($team);

        $this->getCommand()->info('✅ REVISION ALPHA setup completed successfully');
    }

    /**
     * Create Revision Alpha Team
     */
    private function createRevisionAlphaTeam()
    {
        $revisionUser = User::where('email', 'diego.mascarenhas@icloud.com')->first();

        if (! $revisionUser)
        {
            $this->getCommand()->warn('⚠️  Revision user not found. Creating it now...');

            // Create the user if it doesn't exist
            $revisionUser = User::create([
                'name' => 'Diego Mascarenhas',
                'email' => 'diego.mascarenhas@icloud.com',
                'password' => Hash::make('Simplicity!'),
                'email_verified_at' => now(),
            ]);

            // Assign admin role
            $revisionUser->assignRole('admin');

            $this->getCommand()->info("✅ Created Revision user: {$revisionUser->email}");
        }

        // Use Jetstream's proper method to create team
        $team = $revisionUser->ownedTeams()->firstOrCreate(
            ['name' => "REVISION ALPHA's Team"],
            [
                'name' => "REVISION ALPHA's Team",
                'personal_team' => false,
            ],
        );

        // Ensure the user is in the team
        if (! $team->users()->where('user_id', $revisionUser->id)->exists())
        {
            $team->users()->attach($revisionUser->id, ['role' => 'admin']);
        }

        $this->getCommand()->info("✅ Created REVISION ALPHA Team (ID: {$team->id})");

        return $team;
    }

    /**
     * Create Revision Alpha users
     */
    private function createRevisionAlphaUsers($team)
    {
        $this->getCommand()->info('👥 Creating Revision Alpha users...');

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
            ],
        );
        // $fernando->assignRole(2);
        $fernando->assignRole('collaborator');

		// Ensure Fernando is attached to team with collaborator role in pivot
		if (! $fernando->teams()->where('team_id', $team->id)->exists())
		{
			$fernando->teams()->attach($team->id, ['role' => 'collaborator']);
		}

        // Create Cecilia - collaborator (Revision Alpha)
        $cecilia = User::updateOrCreate(
            ['email' => 'cecilia@revisionalpha.com'],
            [
                'name' => 'Cecilia Nuñez',
                'email' => 'cecilia@revisionalpha.com',
                'phone' => '34625447817',
                'password' => Hash::make('@PabloHDP!'),
                'email_verified_at' => now(),
                'current_team_id' => $team->id,
            ],
        );
        $cecilia->assignRole('collaborator');

		// Ensure Cecilia is attached to team with collaborator role in pivot
		if (! $cecilia->teams()->where('team_id', $team->id)->exists())
		{
			$cecilia->teams()->attach($team->id, ['role' => 'collaborator']);
		}
    }

    /**
     * Create Revision Alpha categories
     */
    private function createRevisionAlphaCategories()
    {
        $this->getCommand()->info('📂 Creating Revision Alpha categories...');

        // Get the modules for contacts and enterprises
        $contactsModuleId = Module::where('key', 'contacts')->first()?->id;
        $enterprisesModuleId = Module::where('key', 'enterprises')->first()?->id;

        // Categories for contacts module
        // 1. Create main category
        $mainContactCategory = Category::updateOrCreate([
            'name' => 'Contactos',
            'module_id' => $contactsModuleId,
            'team_id' => $this->teamId,
            'parent_id' => null,
        ], [
            'description' => 'Categoría principal para contactos',
            'status' => 1,
        ]);

        // 2. Create subcategories with parent_id pointing to main
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

        // Categories for enterprises module
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

        $this->getCommand()->info('✅ Created Revision Alpha categories');
    }

    /**
     * Create Revision Alpha task categories
     */
    private function createRevisionAlphaTaskCategories()
    {
        $this->getCommand()->info('📋 Creating Revision Alpha task categories...');

        // Get the tasks module
        $tasksModuleId = Module::where('key', 'tasks')->first()?->id;

        if (! $tasksModuleId)
        {
            $this->getCommand()->warn('⚠️  Tasks module not found. Skipping task categories creation.');

            return;
        }

        // Create main categories for different areas
        $administracionCategory = Category::updateOrCreate([
            'name' => 'Administración',
            'module_id' => $tasksModuleId,
            'team_id' => $this->teamId,
            'parent_id' => null,
        ], [
            'description' => 'Tareas administrativas y de gestión empresarial',
            'status' => 1,
        ]);

        $proyectosCategory = Category::updateOrCreate([
            'name' => 'Proyectos',
            'module_id' => $tasksModuleId,
            'team_id' => $this->teamId,
            'parent_id' => null,
        ], [
            'description' => 'Tareas relacionadas con proyectos de desarrollo y tecnología',
            'status' => 1,
        ]);

        // Create subcategories for Administración
        $administracionSubcategories = [
            [
                'name' => 'Cobranza',
                'description' => 'Tareas relacionadas con gestión de cobros y facturación',
            ],
            [
                'name' => 'Pagos',
                'description' => 'Tareas de procesamiento y seguimiento de pagos',
            ],
            [
                'name' => 'Alta de servicio',
                'description' => 'Tareas para dar de alta nuevos servicios a clientes',
            ],
            [
                'name' => 'Baja de servicio',
                'description' => 'Tareas para dar de baja servicios de clientes',
            ],
            [
                'name' => 'Presentaciones a hacienda',
                'description' => 'Tareas relacionadas con obligaciones fiscales y presentaciones',
            ],
            [
                'name' => 'Presupuestos',
                'description' => 'Tareas de elaboración y seguimiento de presupuestos',
            ],
        ];

        // Create subcategories for Proyectos
        $proyectosSubcategories = [
            [
                'name' => 'Diseño',
                'description' => 'Tareas de diseño gráfico, UX/UI y creatividad visual',
            ],
            [
                'name' => 'Maquetado',
                'description' => 'Tareas de maquetación y estructura de sitios web',
            ],
            [
                'name' => 'Programación',
                'description' => 'Tareas de desarrollo y programación de aplicaciones',
            ],
            [
                'name' => 'Migraciones',
                'description' => 'Tareas de migración de datos y sistemas',
            ],
            [
                'name' => 'Mantenimiento',
                'description' => 'Tareas de mantenimiento y actualización de sistemas',
            ],
            [
                'name' => 'Configuraciones',
                'description' => 'Tareas de configuración de servidores y aplicaciones',
            ],
        ];

        // Create Administración subcategories
        foreach ($administracionSubcategories as $categoryData)
        {
            Category::updateOrCreate([
                'name' => $categoryData['name'],
                'module_id' => $tasksModuleId,
                'team_id' => $this->teamId,
            ], [
                'description' => $categoryData['description'],
                'parent_id' => $administracionCategory->id,
                'status' => 1,
            ]);
        }

        // Create Proyectos subcategories
        foreach ($proyectosSubcategories as $categoryData)
        {
            Category::updateOrCreate([
                'name' => $categoryData['name'],
                'module_id' => $tasksModuleId,
                'team_id' => $this->teamId,
            ], [
                'description' => $categoryData['description'],
                'parent_id' => $proyectosCategory->id,
                'status' => 1,
            ]);
        }

        $this->getCommand()->info('✅ Created Revision Alpha task categories');
        $this->getCommand()->info("   - Administración: {$administracionCategory->name} (ID: {$administracionCategory->id})");
        $this->getCommand()->info('     Subcategories: '.count($administracionSubcategories).' categories');
        $this->getCommand()->info("   - Proyectos: {$proyectosCategory->name} (ID: {$proyectosCategory->id})");
        $this->getCommand()->info('     Subcategories: '.count($proyectosSubcategories).' categories');
        $this->getCommand()->info('   - Total subcategories: '.(count($administracionSubcategories) + count($proyectosSubcategories)).' categories created');
    }

    /**
     * Create professional email template with logos and better design
     */
    private function createProfessionalEmailTemplate(): void
    {
        $this->getCommand()->info('🎨 Creating professional email template for Revision Alpha team...');

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
                            box-sizing: border-box;
                        }
                        body {
                            font-family: Arial, sans-serif;
                            line-height: 1.6;
                            color: #333;
                            background-color: #f4f4f4;
                        }
                        .container {
                            max-width: 600px;
                            margin: 0 auto;
                            background-color: #ffffff;
                            border-radius: 8px;
                            overflow: hidden;
                            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                        }
                        .header {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 30px;
                            text-align: center;
                        }
                        .header h1 {
                            font-size: 28px;
                            margin-bottom: 10px;
                        }
                        .header p {
                            font-size: 16px;
                            opacity: 0.9;
                        }
                        .content {
                            padding: 40px 30px;
                        }
                        .content h2 {
                            color: #333;
                            margin-bottom: 20px;
                            font-size: 24px;
                        }
                        .content p {
                            margin-bottom: 20px;
                            font-size: 16px;
                            line-height: 1.8;
                        }
                        .cta-button {
                            display: inline-block;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 15px 30px;
                            text-decoration: none;
                            border-radius: 25px;
                            font-weight: bold;
                            margin: 20px 0;
                            transition: transform 0.3s ease;
                        }
                        .cta-button:hover {
                            transform: translateY(-2px);
                        }
                        .footer {
                            background-color: #f8f9fa;
                            padding: 30px;
                            text-align: center;
                            border-top: 1px solid #e9ecef;
                        }
                        .footer p {
                            color: #6c757d;
                            font-size: 14px;
                            margin-bottom: 10px;
                        }
                        .social-links {
                            margin-top: 20px;
                        }
                        .social-links a {
                            display: inline-block;
                            margin: 0 10px;
                            color: #667eea;
                            text-decoration: none;
                            font-size: 18px;
                        }
                    ',
                    'html' => '
                        <table width="100%" bgcolor="#F5EFEF" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="padding: 20px 0;">
                                    <div class="container">
                                        <div class="header">
                                            <h1>REVISION ALPHA</h1>
                                            <p>Marketing Digital Profesional</p>
                                        </div>
                                        <div class="content">
                                            <h2>¡Hola {{name}}!</h2>
                                            <p>Comunicarte con tus clientes nunca fue tan fácil. Descubre nuestras herramientas profesionales de marketing digital diseñadas para hacer crecer tu negocio.</p>
                                            <p>Con REVISION ALPHA tienes acceso a:</p>
                                            <ul style="margin: 20px 0; padding-left: 20px;">
                                                <li>📧 Email Marketing avanzado</li>
                                                <li>📱 Gestión de contactos inteligente</li>
                                                <li>📊 Análisis y reportes detallados</li>
                                                <li>🎨 Plantillas profesionales</li>
                                                <li>🚀 Automatización de campañas</li>
                                            </ul>
                                            <div style="text-align: center; margin: 30px 0;">
                                                <a href="https://revisionalpha.com/emailer" class="cta-button">Comenzar Ahora</a>
                                            </div>
                                            <p>¿Tienes preguntas? Estamos aquí para ayudarte a alcanzar tus objetivos de marketing.</p>
                                        </div>
                                        <div class="footer">
                                            <p><strong>REVISION ALPHA</strong></p>
                                            <p>Marketing Digital Profesional</p>
                                            <p>📧 info@revisionalpha.com | 🌐 revisionalpha.com</p>
                                            <div class="social-links">
                                                <a href="#">LinkedIn</a>
                                                <a href="#">Twitter</a>
                                                <a href="#">Facebook</a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    ',
                    'styles' => json_encode([]),
                    'components' => json_encode([]),
                ],
            ],
        );

        $this->getCommand()->info("✅ Professional template created: {$template->name} (ID: {$template->id})");

        // Fix GrapesJS structure
        try
        {
            $result = GrapesJsHelper::fixTemplateStructure($template);
            if ($result)
            {
                $this->getCommand()->info("✅ Fixed GrapesJS structure for template: {$template->name}");
            } else
            {
                $this->getCommand()->warn("⚠️ Failed to fix GrapesJS structure for template: {$template->name}");
            }
        } catch (\Exception $e)
        {
            $this->getCommand()->error('❌ Error fixing template structure: '.$e->getMessage());
        }

        // Show editor URL for reference
        $editorUrl = route('template.editor', $template->getHashedId());
        $this->getCommand()->info("🔗 Editor URL: {$editorUrl}");
    }

    /**
     * Create demo message for Staff category
     */
    private function createDemoMessage(): void
    {
        $this->getCommand()->info('📧 Creating demo message for Staff category...');

        // Ensure message types exist
        $this->ensureMessageTypesExist();

        // Get the professional template we just created
        $professionalTemplate = Template::where('name', 'Email Marketing fácil, rápido y seguro')
            ->where('team_id', $this->teamId)
            ->first();

        // Get Staff category for this team
        $staffCategory = Category::where('name', 'Staff')
            ->where('team_id', $this->teamId)
            ->first();

        if ($professionalTemplate && $staffCategory)
        {
            $message = \App\Models\Message::firstOrCreate(
                [
                    'name' => 'Comunicarte con tus clientes nunca fue tan fácil',
                    'team_id' => $this->teamId,
                ],
                [
                    'text' => 'Hola {{name}}, comunicarte con tus clientes nunca fue tan fácil. Descubre REVISION ALPHA Marketing en https://revisionalpha.com/emailer y nuestras aplicaciones en https://humano.app ¡Te esperamos!',
                    'type_id' => 1,
                    'template_id' => $professionalTemplate->id,
                    'category_id' => $staffCategory->id,
                    'enable_open_tracking' => true,
                    'enable_click_tracking' => true,
                    'show_unsubscribe' => false,
                    'status_id' => 0,
                ],
            );

            $this->getCommand()->info("✅ Message created for Staff category (Message ID: {$message->id})");
            $this->getCommand()->info("   - Template: {$professionalTemplate->name} (ID: {$professionalTemplate->id})");
            $this->getCommand()->info("   - Category: {$staffCategory->name} (ID: {$staffCategory->id})");
        } else
        {
            if (! $professionalTemplate)
            {
                $this->getCommand()->warn('⚠️  Professional template not found for message creation');
            }
            if (! $staffCategory)
            {
                $this->getCommand()->warn('⚠️  Staff category not found for message creation');
            }
        }
    }

    /**
     * Configure email settings for Revision Alpha team
     */
    private function configureRevisionAlphaEmailSettings(Team $team): void
    {
        $this->getCommand()->info('📧 Configuring Revision Alpha email settings...');

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

        $this->getCommand()->info('✅ Revision Alpha email settings configured successfully!');
        $this->getCommand()->info('   Email Configuration:');
        $this->getCommand()->info('   - From Name: REVISION ALPHA Marketing');
        $this->getCommand()->info('   - From Email: mkt@revisionalpha.net');
        $this->getCommand()->info('   Notification Settings:');
        $this->getCommand()->info('   - From Name: REVISION ALPHA');
        $this->getCommand()->info('   - From Email: info@revisionalpha.com');
    }

    /**
     * Assign core modules to team
     */
    private function assignCoreModules(Team $team): void
    {
        $this->getCommand()->info('🔧 Assigning core modules to team...');

        // These are the core modules that are automatically activated
        $defaultModuleKeys = [
            'dashboard',  // Dashboard (always active)
            'users',  // User management
            'contacts',  // Contact management
            'clients',  // Client management
            'services',  // Service catalog
            'projects',  // Project management
            'tasks',  // Task system (Kanban)
            'times',  // Time tracking
            'invoices',  // Invoice management
            'payments',  // Payment management
            'attendances',  // Attendance tracking
            // 'accounting',  // Accounting (disabled by default)
        ];

        foreach ($defaultModuleKeys as $moduleKey)
        {
            $module = Module::where('key', $moduleKey)->first();
            if ($module)
            {
                DB::table('module_team')->updateOrInsert([
                    'module_id' => $module->id,
                    'team_id' => $team->id,
                ], [
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->getCommand()->info("✅ Enabled module: {$module->name} ({$moduleKey})");
            } else
            {
                $this->getCommand()->warn("⚠️  Module not found: {$moduleKey}");
            }
        }
    }

    /**
     * Ensure message types exist in the database
     */
    private function ensureMessageTypesExist(): void
    {
        $messageTypes = [
            ['id' => 1, 'name' => 'Mailer', 'status' => 1],
            ['id' => 2, 'name' => 'WhatsApp', 'status' => 1],
        ];

        foreach ($messageTypes as $type)
        {
            DB::table('message_type')->updateOrInsert(
                ['id' => $type['id']],
                $type,
            );
        }

        $this->getCommand()->info('✅ Message types ensured');
    }

    /**
     * Configure team shortcuts for Revision Alpha
     */
    private function configureTeamShortcuts(Team $team): void
    {
        $this->getCommand()->info('🔗 Configuring team shortcuts...');

        $shortcuts = [
            [
                'title' => 'CMS',
                'subtitle' => 'CMS7',
                'url' => 'https://cms.revisionalpha.com',
                'icon' => 'ti ti-link',
                'open_in_new_tab' => true,
            ],
            [
                'title' => 'Odín',
                'subtitle' => 'Server',
                'url' => 'https://odin.revisionalpha.cloud:2087',
                'icon' => 'ti ti-cloud',
                'open_in_new_tab' => true,
            ],
            [
                'title' => 'Huginn',
                'subtitle' => 'Server',
                'url' => 'https://huginn.revisionalpha.cloud:2087',
                'icon' => 'ti ti-cloud',
                'open_in_new_tab' => true,
            ],
            [
                'title' => 'Muninn',
                'subtitle' => 'Server',
                'url' => 'https://muninn.revisionalpha.cloud:2087',
                'icon' => 'ti ti-cloud',
                'open_in_new_tab' => true,
            ],
        ];

        $team->setSetting('team_shortcuts', $shortcuts, [
            'type' => 'json',
            'group' => 'shortcuts',
        ]);

        $this->getCommand()->info('✅ Team shortcuts configured successfully!');
        $this->getCommand()->info('   - CMS: CMS7 management system');
        $this->getCommand()->info('   - Odín: Server management');
        $this->getCommand()->info('   - Huginn: Server management');
        $this->getCommand()->info('   - Muninn: Server management');
    }
}
