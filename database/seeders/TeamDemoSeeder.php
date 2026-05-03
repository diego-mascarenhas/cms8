<?php

namespace Database\Seeders;

use App\Enums\EmailPlan;
use App\Enums\ProspectPlan;
use App\Helpers\GrapesJsHelper;
use App\Models\CalendarEvent;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\EnterpriseTaxStatusType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\List60;
use App\Models\List60Status;
use App\Models\Message;
use App\Models\Module;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\Project;
use App\Models\Service;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo team, categories, contacts, projects, etc.
 * Product catalogue (textiles) is seeded by {@see TextileProductsSeeder} from {@see DatabaseSeeder} after this class.
 */
class TeamDemoSeeder extends Seeder
{
    private $teamId = 1;

    public function run(): void
    {
        $this->command->info('🚀 Setting up Demo Team Data...');

        // 1. Use the primary "Demo" team from UserSeeder (first by id), not a duplicate "Demo's Team"
        $team = $this->ensureDemoTeamExists();
        $this->teamId = $team->id;

        // 2. Assign core modules to team
        $this->assignCoreModules($team);

        $this->call(DemoObaContentsSectionSeeder::class);

        // 3. Create demo categories
        $this->createDemoCategories();

        // 4. Create demo task categories
        $this->createDemoTaskCategories();

        // 5. Create professional email template
        $this->createProfessionalEmailTemplate();

        // 6. Create Staff category and contacts
        $this->createStaffCategoryAndContacts();

        // 6.1. Demo WhatsApp threads (chat list / mobile API) — fictitious line, see DemoWhatsAppConversationsSeeder
        $this->call(DemoWhatsAppConversationsSeeder::class);

        // 7. Create demo message
        $this->createDemoMessage();

        // 8. Configure email settings and Mailer credits
        $this->configureDemoEmailSettings($team);

        // 8.1. Demo clients (REVISION ALPHA, IDONEO) + mail campaigns / secuencias / contactos Tester (equipo Demo)
        $this->seedDemoClientsProjectsAndMail($team);

        // 9. Configure prospect credits for testing
        $this->configureDemoProspectCredits($team);

        // 10. Configure team shortcuts
        $this->configureTeamShortcuts($team);

        // 11. Create task boards
        $this->createTaskBoards();

        // 12. Create demo users with different roles
        $this->createDemoUsers($team);

        // 13. Seed demo data
        $this->seedDemoEnterprises();
        $this->createServiceCategoriesAndTypes();
        $this->seedDemoServices();
        $this->createClientContactsWithInvoicesAndPayments();
        $this->createFinalizedContacts();
        $this->seedDemoList60();
        $this->createProjectCategoriesAndProjects();
        $this->createProjectBoardsWithTasks();

        $this->seedDemoCalendarEvents();

        // 14. Fix GrapesJS structure
        $this->fixGrapesJsStructure();

        $this->command->info('✅ Demo Team setup completed successfully');
    }

    private function ensureDemoTeamExists(): Team
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@humano.app'],
            [
                'name' => 'Admin Humano',
                'password' => bcrypt('Simplicity!'),
                'email_verified_at' => now(),
                'phone' => '34613194131',
            ],
        );
        $user->update(['phone' => '34613194131']);

        if (! $user->hasRole('admin'))
        {
            $user->assignRole('admin');
        }

        $team = $user->ownedTeams()->where('name', 'Demo')->orderBy('id')->first();

        if (! $team)
        {
            $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();
        }

        if ($team)
        {
            $user->update(['current_team_id' => $team->id]);

            return $team;
        }

        $this->command->info('🏢 Creating Demo team (UserSeeder did not run or has no Demo team)...');

        $team = $user->ownedTeams()->firstOrCreate(
            ['name' => 'Demo'],
            [
                'name' => 'Demo',
                'personal_team' => false,
            ],
        );

        $user->update(['current_team_id' => $team->id]);

        return $team;
    }

    /**
     * Shared demo circuit: client enterprises + projects and mailer fixtures (see {@see DemoSeeder}).
     */
    private function seedDemoClientsProjectsAndMail(Team $team): void
    {
        DemoDataService::createClientsAndProjects($team->id, $this->command);
        $console = $this->command instanceof Command ? $this->command : null;
        DemoMailCampaignData::seed($team, $console);
    }

    private function assignCoreModules(Team $team): void
    {
        $this->command->info('🔧 Assigning core modules to Demo team...');

        $defaultModuleKeys = [
            'dashboard',
            'contacts',
            'clients',
            'list60',
            'prospecting',
            'chat',
            'calendar',
            'projects',
            'tasks',
            'times',
            'invoices',
            'attendances',
            'academy',
            'multimedia',
            'contents',
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
                $this->command->info("✅ Enabled module: {$module->name} ({$moduleKey})");
            }
        }

        $modulesDisabledForDemo = [
            'users' => 'Usuarios',
            'services' => 'Servicios',
            'payments' => 'Pagos',
            'expenses' => 'Gastos',
            'templates' => 'Plantillas',
            'website' => 'Sitio web (Entradas, Páginas)',
        ];

        foreach ($modulesDisabledForDemo as $moduleKey => $label)
        {
            $module = Module::where('key', $moduleKey)->first();
            if ($module)
            {
                $now = now();
                DB::table('module_team')->updateOrInsert(
                    [
                        'module_id' => $module->id,
                        'team_id' => $team->id,
                    ],
                    [
                        'status' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
                $this->command->info("✅ Disabled module: {$label} ({$moduleKey})");
            }
        }
    }

    private function createDemoCategories(): void
    {
        $this->command->info('📂 Creating Demo categories...');

        $contactsModuleId = Module::where('key', 'contacts')->first()?->id;
        $enterprisesModuleId = Module::where('key', 'enterprises')->first()?->id;

        // Contact categories (all at root, no parent)
        $contactCategories = [
            ['name' => 'Staff', 'description' => 'Contactos internos del equipo'],
            ['name' => 'Tester', 'description' => 'Contactos de prueba o testing'],
            ['name' => 'Referido', 'description' => 'Contactos referidos por clientes'],
            ['name' => 'Developer', 'description' => 'Desarrolladores o equipo técnico'],
        ];
        foreach ($contactCategories as $cat)
        {
            Category::updateOrCreate([
                'name' => $cat['name'],
                'module_id' => $contactsModuleId,
                'team_id' => $this->teamId,
            ], [
                'description' => $cat['description'],
                'parent_id' => null,
                'status' => 1,
            ]);
        }

        // Enterprise categories
        Category::updateOrCreate([
            'name' => 'Cliente Premium',
            'module_id' => $enterprisesModuleId,
        ], [
            'team_id' => $this->teamId,
            'description' => 'Empresas con contrato premium',
            'parent_id' => null,
            'status' => 1,
        ]);

        $this->command->info('✅ Created Demo categories');
    }

    private function createDemoTaskCategories(): void
    {
        $this->command->info('📋 Creating Demo task categories...');

        $tasksModuleId = Module::where('key', 'tasks')->first()?->id;
        if (! $tasksModuleId)
        {
            $this->command->warn('⚠️  Tasks module not found.');

            return;
        }

        $administracionCategory = Category::updateOrCreate([
            'name' => 'Administración',
            'module_id' => $tasksModuleId,
            'team_id' => $this->teamId,
            'parent_id' => null,
        ], [
            'description' => 'Tareas administrativas',
            'status' => 1,
        ]);

        $proyectosCategory = Category::updateOrCreate([
            'name' => 'Proyectos',
            'module_id' => $tasksModuleId,
            'team_id' => $this->teamId,
            'parent_id' => null,
        ], [
            'description' => 'Tareas de proyectos',
            'status' => 1,
        ]);

        // Subcategories
        $adminSubs = ['Cobranza', 'Pagos', 'Presupuestos'];
        foreach ($adminSubs as $sub)
        {
            Category::updateOrCreate([
                'name' => $sub,
                'module_id' => $tasksModuleId,
                'team_id' => $this->teamId,
            ], [
                'parent_id' => $administracionCategory->id,
                'status' => 1,
            ]);
        }

        $projectSubs = ['Diseño', 'Programación', 'Mantenimiento'];
        foreach ($projectSubs as $sub)
        {
            Category::updateOrCreate([
                'name' => $sub,
                'module_id' => $tasksModuleId,
                'team_id' => $this->teamId,
            ], [
                'parent_id' => $proyectosCategory->id,
                'status' => 1,
            ]);
        }

        $this->command->info('✅ Created Demo task categories');
    }

    private function createProfessionalEmailTemplate(): void
    {
        $this->command->info('🎨 Creating professional email template...');

        $template = Template::updateOrCreate(
            [
                'name' => 'Email Marketing fácil, rápido y seguro',
                'team_id' => $this->teamId,
            ],
            [
                'status_id' => 1,
                'gjs_data' => [
                    'css' => '* { box-sizing: border-box; } body {margin: 0; font-family: Arial, sans-serif;}',
                    'html' => '<body><h1>Welcome Demo</h1><p>This is a demo email template.</p></body>',
                    'styles' => json_encode([]),
                    'components' => json_encode([]),
                ],
            ],
        );

        $this->command->info("✅ Template created: {$template->name}");

        try
        {
            $result = GrapesJsHelper::fixTemplateStructure($template);
            if ($result)
            {
                $this->command->info('✅ Fixed GrapesJS structure');
            }
        } catch (\Exception $e)
        {
            $this->command->error('❌ Error fixing template: '.$e->getMessage());
        }
    }

    private function createStaffCategoryAndContacts(): void
    {
        $this->command->info('👥 Creating 30 demo contacts (staff and clients)...');

        $contactsModule = Module::where('key', 'contacts')->first();
        if (! $contactsModule)
        {
            return;
        }

        $staffCategory = Category::where('name', 'Staff')
            ->where('team_id', $this->teamId)
            ->first();

        $clientCategory = Category::where('name', 'Referido')
            ->where('team_id', $this->teamId)
            ->first();

        // Get enterprises for client contacts
        $enterprises = Enterprise::where('team_id', $this->teamId)->get();

        // Staff contacts (10)
        $staffContacts = [
            ['name' => 'Admin', 'surname' => 'Demo', 'email' => 'admin@humano.app', 'phone' => '34613194131', 'is_staff' => true],
            ['name' => 'Demo', 'surname' => 'User', 'email' => 'demo@example.com', 'phone' => '34600111002', 'is_staff' => true],
            ['name' => 'Internal', 'surname' => 'Manager', 'email' => 'manager@humano.app', 'phone' => '34600111003', 'is_staff' => true],
            ['name' => 'Team', 'surname' => 'Lead', 'email' => 'lead@humano.app', 'phone' => '34600111004', 'is_staff' => true],
            ['name' => 'Support', 'surname' => 'Admin', 'email' => 'support@humano.app', 'phone' => '34600111005', 'is_staff' => true],
            ['name' => 'Sales', 'surname' => 'Director', 'email' => 'sales@humano.app', 'phone' => '34600111006', 'is_staff' => true],
            ['name' => 'Marketing', 'surname' => 'Director', 'email' => 'marketing@humano.app', 'phone' => '34600111007', 'is_staff' => true],
            ['name' => 'HR', 'surname' => 'Manager', 'email' => 'hr@humano.app', 'phone' => '34600111008', 'is_staff' => true],
            ['name' => 'Finance', 'surname' => 'Manager', 'email' => 'finance@humano.app', 'phone' => '34600111009', 'is_staff' => true],
            ['name' => 'Operations', 'surname' => 'Manager', 'email' => 'operations@humano.app', 'phone' => '34600111010', 'is_staff' => true],
        ];

        // Client contacts (20) - with Spanish names
        $clientContacts = [
            ['name' => 'Carlos', 'surname' => 'García López', 'email' => 'carlos.garcia@cliente1.com', 'phone' => '34600222001', 'profile' => 'Director General', 'is_staff' => false],
            ['name' => 'María', 'surname' => 'Rodríguez Sánchez', 'email' => 'maria.rodriguez@cliente2.com', 'phone' => '34600222002', 'profile' => 'CEO', 'is_staff' => false],
            ['name' => 'Juan', 'surname' => 'Martínez Pérez', 'email' => 'juan.martinez@cliente3.com', 'phone' => '34600222003', 'profile' => 'CTO', 'is_staff' => false],
            ['name' => 'Ana', 'surname' => 'López Fernández', 'email' => 'ana.lopez@cliente4.com', 'phone' => '34600222004', 'profile' => 'Directora de Marketing', 'is_staff' => false],
            ['name' => 'Pedro', 'surname' => 'González Martín', 'email' => 'pedro.gonzalez@cliente5.com', 'phone' => '34600222005', 'profile' => 'Gerente de Operaciones', 'is_staff' => false],
            ['name' => 'Laura', 'surname' => 'Sánchez Ruiz', 'email' => 'laura.sanchez@cliente6.com', 'phone' => '34600222006', 'profile' => 'Directora Financiera', 'is_staff' => false],
            ['name' => 'Miguel', 'surname' => 'Hernández Díaz', 'email' => 'miguel.hernandez@cliente7.com', 'phone' => '34600222007', 'profile' => 'Director de Ventas', 'is_staff' => false],
            ['name' => 'Carmen', 'surname' => 'Jiménez Moreno', 'email' => 'carmen.jimenez@cliente8.com', 'phone' => '34600222008', 'profile' => 'Gerente de Proyecto', 'is_staff' => false],
            ['name' => 'David', 'surname' => 'Ruiz Álvarez', 'email' => 'david.ruiz@cliente9.com', 'phone' => '34600222009', 'profile' => 'Jefe de Producto', 'is_staff' => false],
            ['name' => 'Isabel', 'surname' => 'Moreno Torres', 'email' => 'isabel.moreno@cliente10.com', 'phone' => '34600222010', 'profile' => 'Directora de RRHH', 'is_staff' => false],
            ['name' => 'Antonio', 'surname' => 'Romero Castro', 'email' => 'antonio.romero@cliente11.com', 'phone' => '34600222011', 'profile' => 'Director Comercial', 'is_staff' => false],
            ['name' => 'Cristina', 'surname' => 'Navarro Ortiz', 'email' => 'cristina.navarro@cliente12.com', 'phone' => '34600222012', 'profile' => 'Gerente General', 'is_staff' => false],
            ['name' => 'Francisco', 'surname' => 'Serrano Gil', 'email' => 'francisco.serrano@cliente13.com', 'phone' => '34600222013', 'profile' => 'Director de Sistemas', 'is_staff' => false],
            ['name' => 'Lucía', 'surname' => 'Blanco Vega', 'email' => 'lucia.blanco@cliente14.com', 'phone' => '34600222014', 'profile' => 'Responsable de Compras', 'is_staff' => false],
            ['name' => 'Javier', 'surname' => 'Castro Ramos', 'email' => 'javier.castro@cliente15.com', 'phone' => '34600222015', 'profile' => 'Director de Logística', 'is_staff' => false],
            ['name' => 'Elena', 'surname' => 'Iglesias Suárez', 'email' => 'elena.iglesias@cliente16.com', 'phone' => '34600222016', 'profile' => 'Directora de Calidad', 'is_staff' => false],
            ['name' => 'Roberto', 'surname' => 'Vargas Delgado', 'email' => 'roberto.vargas@cliente17.com', 'phone' => '34600222017', 'profile' => 'Gerente de Innovación', 'is_staff' => false],
            ['name' => 'Patricia', 'surname' => 'Ortiz Pascual', 'email' => 'patricia.ortiz@cliente18.com', 'phone' => '34600222018', 'profile' => 'Directora de Comunicación', 'is_staff' => false],
            ['name' => 'Ricardo', 'surname' => 'Medina Santos', 'email' => 'ricardo.medina@cliente19.com', 'phone' => '34600222019', 'profile' => 'Director de Expansión', 'is_staff' => false],
            ['name' => 'Silvia', 'surname' => 'Campos Núñez', 'email' => 'silvia.campos@cliente20.com', 'phone' => '34600222020', 'profile' => 'Directora de Desarrollo', 'is_staff' => false],
        ];

        $created = 0;
        $existing = 0;

        // Create staff contacts
        foreach ($staffContacts as $contactData)
        {
            $contact = Contact::firstOrCreate(
                ['email' => $contactData['email'], 'team_id' => $this->teamId],
                [
                    'name' => $contactData['name'],
                    'surname' => $contactData['surname'],
                    'phone' => $contactData['phone'],
                    'creator_id' => 1,
                    'responsible_id' => 1,
                    'status_id' => 1,
                    'country' => 724,  // Spain
                    'language' => 'es',
                    'engagment' => 'temperate',
                ],
            );

            if ($staffCategory && ! $contact->categories()->where('category_id', $staffCategory->id)->exists())
            {
                $contact->categories()->attach($staffCategory->id);
            }

            $created++;
            $this->command->info("✅ Staff: {$contactData['name']} {$contactData['surname']}");
        }

        // Create client contacts
        foreach ($clientContacts as $index => $contactData)
        {
            // Assign to an enterprise if available
            $enterprise = $enterprises->isNotEmpty() ? $enterprises->random() : null;

            $contact = Contact::firstOrCreate(
                ['email' => $contactData['email'], 'team_id' => $this->teamId],
                [
                    'name' => $contactData['name'],
                    'surname' => $contactData['surname'],
                    'phone' => $contactData['phone'],
                    'profile' => $contactData['profile'],
                    'creator_id' => 1,
                    'responsible_id' => 1,
                    'status_id' => rand(1, 2),  // Active or In Progress
                    'country' => 724,  // Spain
                    'language' => 'es',
                    'engagment' => collect(['cold', 'temperate', 'hot'])->random(),
                    'current_enterprise_id' => $enterprise?->id,
                ],
            );

            if ($clientCategory && ! $contact->categories()->where('category_id', $clientCategory->id)->exists())
            {
                $contact->categories()->attach($clientCategory->id);
            }

            // Link contact to enterprise in pivot table
            if ($enterprise)
            {
                DB::table('contact_enterprise')->updateOrInsert(
                    ['contact_id' => $contact->id, 'enterprise_id' => $enterprise->id],
                    ['position' => $contactData['profile'], 'created_at' => now(), 'updated_at' => now()],
                );
            }

            $created++;
            $this->command->info("✅ Client: {$contactData['name']} {$contactData['surname']} - {$contactData['profile']}");
        }

        $this->command->info("📊 Summary: {$created} contacts created (10 staff, 20 clients)");
    }

    private function createDemoMessage(): void
    {
        $this->command->info('📧 Creating demo message...');

        $this->ensureMessageTypesExist();

        $template = Template::where('name', 'Email Marketing fácil, rápido y seguro')
            ->where('team_id', $this->teamId)
            ->first();

        $staffCategory = Category::where('name', 'Staff')
            ->where('team_id', $this->teamId)
            ->first();

        if ($template && $staffCategory)
        {
            \App\Models\Message::firstOrCreate(
                ['name' => 'Newsletter Demo', 'team_id' => $this->teamId],
                [
                    'text' => 'Hola {{name}}, bienvenido a nuestra plataforma.',
                    'type_id' => 1,
                    'template_id' => $template->id,
                    'category_id' => $staffCategory->id,
                    'status_id' => 0,
                ],
            );
            $this->command->info('✅ Demo message created');
        }
    }

    private function configureDemoEmailSettings(Team $team): void
    {
        $this->command->info('📧 Configuring email settings...');

        $team->setSetting('mail_from_name', 'Demo Team', [
            'type' => 'string',
            'group' => 'email',
            'is_encrypted' => false,
        ]);

        $team->setSetting('mail_from_address', 'demo@example.com', [
            'type' => 'string',
            'group' => 'email',
            'is_encrypted' => false,
        ]);

        // Assign Mailer plan with minimum credits for testing (Basic: 10,000 monthly / 500 daily)
        $team->assignEmailPlan(EmailPlan::BASIC, null);
        $this->command->info('✅ Email settings and Mailer credits (Basic plan) configured');
    }

    private function configureDemoProspectCredits(Team $team): void
    {
        $this->command->info('👥 Configuring prospect credits for testing...');

        // Assign prospect plan with monthly credits (Basic: 50/month)
        $team->assignProspectPlan(ProspectPlan::BASIC, null);
        // Add extra one-time credits so demo has enough to test imports
        $team->addProspectCreditsFromPurchase(50);

        $this->command->info('✅ Prospect credits configured (Basic plan + 50 extra credits)');
    }

    private function configureTeamShortcuts(Team $team): void
    {
        $this->command->info('🔗 Configuring team shortcuts...');

        $shortcuts = [
            [
                'title' => 'Documentation',
                'subtitle' => 'Help Center',
                'url' => 'https://docs.example.com',
                'icon' => 'ti ti-book',
                'open_in_new_tab' => true,
            ],
        ];

        $team->setSetting('team_shortcuts', $shortcuts, [
            'type' => 'json',
            'group' => 'shortcuts',
        ]);

        $this->command->info('✅ Shortcuts configured');
    }

    private function createDemoUsers(Team $team): void
    {
        $this->command->info('👥 Creating 30 demo users with different roles...');

        // Ensure Victor and Diego exist and are added as admins
        $victor = User::firstOrCreate(
            ['email' => 'victor@machbel.com'],
            [
                'name' => 'Victor Machbel',
                'password' => bcrypt('Simplicity!'),
                'email_verified_at' => now(),
            ],
        );
        if (! $victor->hasRole('admin'))
        {
            $victor->assignRole('admin');
        }
        if (! $victor->teams()->where('team_id', $team->id)->exists())
        {
            $victor->teams()->attach($team->id, ['role' => 'admin']);
            $this->command->info('✅ Added Victor as admin to Demo team');
        }

        $diego = User::firstOrCreate(
            ['email' => 'diego.mascarenhas@icloud.com'],
            [
                'name' => 'Diego Mascarenhas',
                'phone' => 34722372858,
                'password' => bcrypt('Simplicity!'),
                'email_verified_at' => now(),
            ],
        );
        $diego->update(['phone' => 34722372858]);
        if (! $diego->hasRole('admin'))
        {
            $diego->assignRole('admin');
        }
        if (! $diego->teams()->where('team_id', $team->id)->exists())
        {
            $diego->teams()->attach($team->id, ['role' => 'admin']);
            $this->command->info('✅ Added Diego as admin to Demo team');
        }

        // Demo users with different roles (using existing roles: admin, employee, collaborator, developer, editor, user)
        $demoUsers = [
            // Employees (5 users) - Senior staff
            ['name' => 'Sarah Johnson', 'email' => 'sarah.johnson@humano.app', 'role' => 'employee', 'position' => 'Project Manager'],
            ['name' => 'Michael Chen', 'email' => 'michael.chen@humano.app', 'role' => 'employee', 'position' => 'Operations Manager'],
            ['name' => 'Emma Wilson', 'email' => 'emma.wilson@humano.app', 'role' => 'employee', 'position' => 'HR Manager'],
            ['name' => 'David Rodriguez', 'email' => 'david.rodriguez@humano.app', 'role' => 'employee', 'position' => 'Sales Manager'],
            ['name' => 'Lisa Anderson', 'email' => 'lisa.anderson@humano.app', 'role' => 'employee', 'position' => 'Marketing Manager'],
            // Developers (5 users)
            ['name' => 'John Smith', 'email' => 'john.smith@humano.app', 'role' => 'developer', 'position' => 'Senior Developer'],
            ['name' => 'Maria Garcia', 'email' => 'maria.garcia@humano.app', 'role' => 'developer', 'position' => 'Frontend Developer'],
            ['name' => 'James Brown', 'email' => 'james.brown@humano.app', 'role' => 'developer', 'position' => 'Backend Developer'],
            ['name' => 'Robert Taylor', 'email' => 'robert.taylor@humano.app', 'role' => 'developer', 'position' => 'DevOps Engineer'],
            ['name' => 'William Davis', 'email' => 'william.davis@humano.app', 'role' => 'developer', 'position' => 'Full Stack Developer'],
            // Editors (3 users)
            ['name' => 'Anna Martinez', 'email' => 'anna.martinez@humano.app', 'role' => 'editor', 'position' => 'UX Designer'],
            ['name' => 'Patricia White', 'email' => 'patricia.white@humano.app', 'role' => 'editor', 'position' => 'Product Designer'],
            ['name' => 'Barbara Walker', 'email' => 'barbara.walker@humano.app', 'role' => 'editor', 'position' => 'Content Writer'],
            // Collaborators (7 users)
            ['name' => 'Jennifer Lee', 'email' => 'jennifer.lee@humano.app', 'role' => 'collaborator', 'position' => 'QA Engineer'],
            ['name' => 'Daniel Harris', 'email' => 'daniel.harris@humano.app', 'role' => 'collaborator', 'position' => 'Mobile Developer'],
            ['name' => 'Linda Clark', 'email' => 'linda.clark@humano.app', 'role' => 'collaborator', 'position' => 'Business Analyst'],
            ['name' => 'Thomas Lewis', 'email' => 'thomas.lewis@humano.app', 'role' => 'collaborator', 'position' => 'System Administrator'],
            ['name' => 'Christopher Hall', 'email' => 'christopher.hall@humano.app', 'role' => 'collaborator', 'position' => 'Data Analyst'],
            ['name' => 'Jessica Allen', 'email' => 'jessica.allen@humano.app', 'role' => 'collaborator', 'position' => 'Customer Success'],
            ['name' => 'Matthew Young', 'email' => 'matthew.young@humano.app', 'role' => 'collaborator', 'position' => 'Technical Support'],
            // Users (10 users) - Junior staff and support
            ['name' => 'Karen King', 'email' => 'karen.king@humano.app', 'role' => 'user', 'position' => 'Junior Developer'],
            ['name' => 'Steven Wright', 'email' => 'steven.wright@humano.app', 'role' => 'user', 'position' => 'Intern Developer'],
            ['name' => 'Nancy Lopez', 'email' => 'nancy.lopez@humano.app', 'role' => 'user', 'position' => 'Marketing Coordinator'],
            ['name' => 'Kevin Hill', 'email' => 'kevin.hill@humano.app', 'role' => 'user', 'position' => 'Sales Representative'],
            ['name' => 'Betty Scott', 'email' => 'betty.scott@humano.app', 'role' => 'user', 'position' => 'Administrative Assistant'],
            ['name' => 'George Green', 'email' => 'george.green@humano.app', 'role' => 'user', 'position' => 'Customer Support'],
            ['name' => 'Helen Adams', 'email' => 'helen.adams@humano.app', 'role' => 'user', 'position' => 'Receptionist'],
            ['name' => 'Edward Baker', 'email' => 'edward.baker@humano.app', 'role' => 'user', 'position' => 'Office Manager'],
            ['name' => 'Sandra Nelson', 'email' => 'sandra.nelson@humano.app', 'role' => 'user', 'position' => 'Accountant'],
            ['name' => 'Brian Carter', 'email' => 'brian.carter@humano.app', 'role' => 'user', 'position' => 'HR Assistant'],
        ];

        $created = 0;
        $existing = 0;

        foreach ($demoUsers as $userData)
        {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => bcrypt('Simplicity!'),
                    'email_verified_at' => now(),
                ],
            );

            // Assign role
            if (! $user->hasRole($userData['role']))
            {
                $user->assignRole($userData['role']);
            }

            // Add to team if not already
            if (! $user->teams()->where('team_id', $team->id)->exists())
            {
                $user->teams()->attach($team->id, ['role' => $userData['role']]);
                $created++;
                $this->command->info("✅ Created: {$userData['name']} ({$userData['role']}) - {$userData['position']}");
            } else
            {
                $existing++;
            }
        }

        $this->command->info("📊 Summary: {$created} new users created, {$existing} already existed");
        $this->command->info('✅ Total team members: '.($team->users()->count()));
    }

    private function createTaskBoards(): void
    {
        $this->command->info('🎯 Creating task boards...');

        $team = Team::find($this->teamId);
        if (! $team)
        {
            return;
        }

        $existingBoards = \App\Models\TaskBoard::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->count();

        if ($existingBoards > 0)
        {
            $this->command->warn("⏭️  Boards already exist ({$existingBoards})");

            return;
        }

        \App\Models\TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'General',
            'description' => 'Tablero general',
            'is_default' => true,
            'order' => 0,
        ]);

        \App\Models\TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Development',
            'description' => 'Tablero de desarrollo',
            'is_default' => false,
            'order' => 1,
        ]);

        $this->command->info('✅ Task boards created');
    }

    private function seedDemoContacts(): void
    {
        $this->command->info('👥 Creating demo contacts...');

        $enterprises = Enterprise::where('team_id', $this->teamId)->get();
        if ($enterprises->isEmpty())
        {
            // Create demo enterprise first
            $enterprise = Enterprise::updateOrCreate(
                ['id' => 1],
                [
                    'team_id' => $this->teamId,
                    'name' => 'Demo Technologies',
                    'website' => 'https://demo.com',
                    'email' => 'info@demo.com',
                    'type_id' => 1,
                    'status_id' => 1,
                ],
            );

            $this->command->info("✅ Created demo enterprise: {$enterprise->name}");
        }
    }

    private function createServiceCategoriesAndTypes(): void
    {
        $this->command->info('🏷️  Creating service categories and types...');

        $servicesModule = Module::where('key', 'services')->first();
        if (! $servicesModule)
        {
            $this->command->warn('⚠️  Services module not found');

            return;
        }

        // Create service categories
        $mainServiceCategory = Category::firstOrCreate(
            ['name' => 'Servicios IT', 'module_id' => $servicesModule->id, 'team_id' => $this->teamId],
            ['description' => 'Categoría principal de servicios IT', 'status' => 1],
        );

        $subcategories = [
            ['name' => 'Desarrollo', 'description' => 'Servicios de desarrollo de software'],
            ['name' => 'Consultoría', 'description' => 'Servicios de consultoría IT'],
            ['name' => 'Infraestructura', 'description' => 'Servicios de infraestructura y cloud'],
            ['name' => 'Diseño', 'description' => 'Servicios de diseño y UX'],
        ];

        foreach ($subcategories as $subcat)
        {
            Category::firstOrCreate(
                ['name' => $subcat['name'], 'module_id' => $servicesModule->id, 'team_id' => $this->teamId],
                [
                    'description' => $subcat['description'],
                    'parent_id' => $mainServiceCategory->id,
                    'status' => 1,
                ],
            );
            $this->command->info("✅ Service category: {$subcat['name']}");
        }

        // Create service types
        $serviceTypes = [
            ['name' => 'Desarrollo Web', 'description' => 'Desarrollo de aplicaciones web', 'category' => 'Desarrollo'],
            ['name' => 'Desarrollo Mobile', 'description' => 'Desarrollo de aplicaciones móviles', 'category' => 'Desarrollo'],
            ['name' => 'Consultoría Técnica', 'description' => 'Asesoría técnica especializada', 'category' => 'Consultoría'],
            ['name' => 'Consultoría Estratégica', 'description' => 'Asesoría estratégica IT', 'category' => 'Consultoría'],
            ['name' => 'Cloud Infrastructure', 'description' => 'Gestión de infraestructura cloud', 'category' => 'Infraestructura'],
            ['name' => 'DevOps', 'description' => 'Servicios de DevOps y CI/CD', 'category' => 'Infraestructura'],
            ['name' => 'UI/UX Design', 'description' => 'Diseño de interfaces y experiencia de usuario', 'category' => 'Diseño'],
            ['name' => 'Branding', 'description' => 'Diseño de identidad corporativa', 'category' => 'Diseño'],
        ];

        foreach ($serviceTypes as $typeData)
        {
            $category = Category::where('name', $typeData['category'])
                ->where('module_id', $servicesModule->id)
                ->where('team_id', $this->teamId)
                ->first();

            \App\Models\ServiceType::firstOrCreate(
                ['name' => $typeData['name']],
                [
                    'description' => $typeData['description'],
                    'category_id' => $category?->id,
                    'status' => 1,
                ],
            );
            $this->command->info("✅ Service type: {$typeData['name']}");
        }

        $this->command->info('✅ Service categories and types created');
    }

    private function seedDemoEnterprises(): void
    {
        $this->command->info('🏢 Creating demo enterprises...');

        $enterpriseNames = [
            ['name' => 'TechCorp Solutions', 'code' => 'TECH001'],
            ['name' => 'Digital Innovations S.L.', 'code' => 'DIGI002'],
            ['name' => 'Consulting Group España', 'code' => 'CONS003'],
            ['name' => 'Marketing Pro Agency', 'code' => 'MARK004'],
            ['name' => 'Software Factory Madrid', 'code' => 'SOFT005'],
            ['name' => 'Cloud Services Iberia', 'code' => 'CLOU006'],
            ['name' => 'Business Intelligence Co.', 'code' => 'BUSI007'],
            ['name' => 'Data Analytics Partners', 'code' => 'DATA008'],
            ['name' => 'eCommerce Ventures', 'code' => 'ECOM009'],
            ['name' => 'Mobile Apps Studio', 'code' => 'MOBI010'],
        ];

        foreach ($enterpriseNames as $enterpriseData)
        {
            Enterprise::firstOrCreate(
                ['code' => $enterpriseData['code'], 'team_id' => $this->teamId],
                [
                    'name' => $enterpriseData['name'],
                    'type_id' => 1,  // Cliente
                    'status_id' => 1,  // Activo
                    'responsible_id' => 1,
                    'phone' => '34'.rand(600000000, 699999999),
                    'email' => strtolower(str_replace(' ', '', explode(' ', $enterpriseData['name'])[0])).'@example.com',
                ],
            );
            $this->command->info("✅ Enterprise: {$enterpriseData['name']}");
        }
    }

    private function seedDemoServices(): void
    {
        $this->command->info('🛠️ Creating demo services...');

        $enterprises = Enterprise::where('team_id', $this->teamId)->get();
        if ($enterprises->isEmpty())
        {
            $this->command->warn('⚠️  No enterprises found');

            return;
        }

        // Get or create a default service type
        $serviceType = \App\Models\ServiceType::first();
        if (! $serviceType)
        {
            $this->command->warn('⚠️  No service types found, skipping services');

            return;
        }

        $serviceTypes = \App\Models\ServiceType::all();
        if ($serviceTypes->isEmpty())
        {
            $this->command->warn('⚠️  No service types found, using default');
            $serviceTypes = collect([$serviceType]);
        }

        $servicesModule = Module::where('key', 'services')->first();
        $serviceCategory = Category::where('module_id', $servicesModule?->id)
            ->where('team_id', $this->teamId)
            ->first();

        $servicesCreated = 0;

        foreach ($enterprises as $enterprise)
        {
            // Create 1-3 services per enterprise
            $numServices = rand(1, 3);
            for ($i = 0; $i < $numServices; $i++)
            {
                $selectedType = $serviceTypes->random();

                Service::firstOrCreate(
                    ['enterprise_id' => $enterprise->id, 'service_type_id' => $selectedType->id],
                    [
                        'description' => $selectedType->name.' para '.$enterprise->name,
                        'price' => rand(500, 5000),
                        'status' => 1,
                        'operation' => 'sell',
                        'frequency' => rand(1, 4),
                        'responsible_id' => 1,
                    ],
                );
                $servicesCreated++;
            }
        }

        $this->command->info("✅ {$servicesCreated} demo services created");
    }

    private function createFinalizedContacts(): void
    {
        $this->command->info('✅ Creating finalized contacts...');

        // Status 3 = Finalized
        $finalizedEmails = [
            'antonio.romero@cliente11.com',
            'cristina.navarro@cliente12.com',
            'francisco.serrano@cliente13.com',
        ];

        Contact::whereIn('email', $finalizedEmails)
            ->where('team_id', $this->teamId)
            ->update(['status_id' => 3]);

        $count = Contact::whereIn('email', $finalizedEmails)
            ->where('team_id', $this->teamId)
            ->count();

        $this->command->info("✅ {$count} contacts marked as finalized");
    }

    private function seedDemoList60(): void
    {
        $this->command->info('📋 Creating List60 entries for demo contacts...');

        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $this->teamId)
            ->get();

        if ($contacts->isEmpty())
        {
            $this->command->warn('⚠️  No contacts found for List60');

            return;
        }

        $team = Team::find($this->teamId);
        $responsibleId = $team?->user_id ?? 1;
        $status = List60Status::first();
        $statusId = $status ? $status->id : 1;
        $typeId = 1; // EnterpriseType default

        $created = 0;
        foreach ($contacts as $contact)
        {
            $exists = List60::where('contact_id', $contact->id)->exists();
            if ($exists)
            {
                continue;
            }

            List60::create([
                'contact_id' => $contact->id,
                'type_id' => $typeId,
                'date_next' => now()->addDays(rand(1, 60)),
                'notes' => null,
                'responsible_id' => $responsibleId,
                'status_id' => $statusId,
            ]);
            $created++;
        }

        $this->command->info("✅ {$created} List60 entries created for demo team");
    }

    private function createProjectCategoriesAndProjects(): void
    {
        $this->command->info('📁 Creating project categories and projects...');

        $projectsModule = Module::where('key', 'projects')->first();
        if (! $projectsModule)
        {
            $this->command->warn('⚠️  Projects module not found');

            return;
        }

        // Create project categories
        $mainProjectCategory = Category::firstOrCreate(
            ['name' => 'Proyectos IT', 'module_id' => $projectsModule->id, 'team_id' => $this->teamId],
            ['description' => 'Categoría principal de proyectos IT', 'status' => 1],
        );

        $subcategories = [
            ['name' => 'Desarrollo Web', 'description' => 'Proyectos de desarrollo web'],
            ['name' => 'Desarrollo Mobile', 'description' => 'Proyectos de aplicaciones móviles'],
            ['name' => 'Infraestructura', 'description' => 'Proyectos de infraestructura'],
            ['name' => 'Consultoría', 'description' => 'Proyectos de consultoría'],
        ];

        $projectCategories = [];
        foreach ($subcategories as $subcat)
        {
            $category = Category::firstOrCreate(
                ['name' => $subcat['name'], 'module_id' => $projectsModule->id, 'team_id' => $this->teamId],
                [
                    'description' => $subcat['description'],
                    'parent_id' => $mainProjectCategory->id,
                    'status' => 1,
                ],
            );
            $projectCategories[] = $category;
            $this->command->info("✅ Project category: {$subcat['name']}");
        }

        // Get enterprises
        $enterprises = Enterprise::where('team_id', $this->teamId)->limit(5)->get();
        if ($enterprises->isEmpty())
        {
            $this->command->warn('⚠️  No enterprises found for projects');

            return;
        }

        // Create projects
        $projectData = [
            ['name' => 'Portal Web Corporativo', 'real_name' => 'Corporate Website', 'description' => 'Desarrollo de portal web institucional con CMS', 'status_id' => 2],
            ['name' => 'App Móvil iOS/Android', 'real_name' => 'Mobile App', 'description' => 'Aplicación móvil nativa para iOS y Android', 'status_id' => 2],
            ['name' => 'Migración a Cloud', 'real_name' => 'Cloud Migration', 'description' => 'Migración de infraestructura local a AWS', 'status_id' => 3],
            ['name' => 'Sistema de Gestión', 'real_name' => 'Management System', 'description' => 'ERP personalizado para gestión empresarial', 'status_id' => 2],
            ['name' => 'Dashboard Analytics', 'real_name' => 'Analytics Dashboard', 'description' => 'Dashboard de analytics y reportes en tiempo real', 'status_id' => 4],
            ['name' => 'eCommerce Platform', 'real_name' => 'eCommerce', 'description' => 'Plataforma de comercio electrónico con pasarela de pagos', 'status_id' => 2],
        ];

        foreach ($projectData as $index => $project)
        {
            $enterprise = $enterprises->random();
            $category = collect($projectCategories)->random();

            Project::firstOrCreate(
                ['name' => $project['name'], 'team_id' => $this->teamId],
                [
                    'real_name' => $project['real_name'],
                    'description' => $project['description'],
                    'enterprise_id' => $enterprise->id,
                    'category_id' => $category->id,
                    'status_id' => $project['status_id'],
                    'responsible_id' => 1,
                ],
            );
            $this->command->info("✅ Project: {$project['name']}");
        }

        $this->command->info('✅ Projects created');
    }

    private function createProjectBoardsWithTasks(): void
    {
        $this->command->info('📋 Creating project boards with tasks...');

        // Get projects that need boards
        $projects = Project::where('team_id', $this->teamId)
            ->whereIn('status_id', [2, 3, 4])  // In progress, testing, or active
            ->limit(3)
            ->get();

        if ($projects->isEmpty())
        {
            $this->command->warn('⚠️  No projects found for boards');

            return;
        }

        $tasksModule = Module::where('key', 'tasks')->first();
        $taskCategories = Category::where('module_id', $tasksModule?->id)
            ->where('team_id', $this->teamId)
            ->get();

        foreach ($projects as $project)
        {
            // Create board for project
            $board = \App\Models\TaskBoard::firstOrCreate(
                ['name' => 'Board: '.$project->name, 'team_id' => $this->teamId],
                [
                    'description' => 'Tablero de tareas para '.$project->name,
                    'is_default' => false,
                    'order' => 10,
                ],
            );

            // Link board to project
            $project->update(['board_id' => $board->id]);

            // Create 5-10 tasks for this project
            $numTasks = rand(5, 10);
            $taskStatuses = [1, 2, 3, 4];  // Pending, In Progress, Completed, Cancelled

            $taskTemplates = [
                'Análisis de requisitos',
                'Diseño de arquitectura',
                'Desarrollo de backend',
                'Desarrollo de frontend',
                'Integración de APIs',
                'Testing unitario',
                'Testing de integración',
                'Deployment a staging',
                'Documentación técnica',
                'Code review',
                'Optimización de performance',
                'Corrección de bugs',
            ];

            for ($i = 0; $i < $numTasks; $i++)
            {
                $taskName = $taskTemplates[$i % count($taskTemplates)].' - '.$project->name;
                $category = $taskCategories->isNotEmpty() ? $taskCategories->random() : null;

                \App\Models\Task::firstOrCreate(
                    ['title' => $taskName, 'board_id' => $board->id],
                    [
                        'description' => 'Tarea de '.strtolower($taskTemplates[$i % count($taskTemplates)]).' para '.$project->name,
                        'category_id' => $category?->id,
                        'status_id' => collect($taskStatuses)->random(),
                        'responsible_id' => 1,
                        'team_id' => $this->teamId,
                        'start_date' => now(),
                        'due_date' => now()->addDays(rand(7, 30)),
                    ],
                );
            }

            $this->command->info("✅ Board created for project: {$project->name} ({$numTasks} tasks)");
        }

        $this->command->info('✅ Project boards with tasks created');
    }

    private function createClientContactsWithInvoicesAndPayments(): void
    {
        $this->command->info('💼 Creating client contacts with billing addresses, invoices and payments...');

        // Get all client contacts (the ones we created earlier)
        $clientContacts = Contact::where('team_id', $this->teamId)
            ->whereIn('email', [
                'carlos.garcia@cliente1.com',
                'maria.rodriguez@cliente2.com',
                'juan.martinez@cliente3.com',
                'ana.lopez@cliente4.com',
                'pedro.gonzalez@cliente5.com',
                'laura.sanchez@cliente6.com',
                'miguel.hernandez@cliente7.com',
                'carmen.jimenez@cliente8.com',
                'david.ruiz@cliente9.com',
                'isabel.moreno@cliente10.com',
            ])
            ->get();

        if ($clientContacts->isEmpty())
        {
            $this->command->warn('⚠️  No client contacts found');

            return;
        }

        // Get enterprises
        $enterprises = Enterprise::where('team_id', $this->teamId)->get();
        if ($enterprises->isEmpty())
        {
            $this->command->warn('⚠️  No enterprises found');

            return;
        }

        // Get invoice and payment types
        $invoiceType = InvoiceType::first();
        $paymentType = PaymentType::first();
        $paymentAccount = PaymentAccount::withoutGlobalScopes()->where('team_id', $this->teamId)->first();

        if (! $paymentAccount)
        {
            $this->command->warn('⚠️  No payment account found, creating one...');
            $paymentAccount = PaymentAccount::withoutGlobalScopes()->create([
                'team_id' => $this->teamId,
                'code' => 'MAIN',
                'name' => 'Cuenta Principal',
                'currency_id' => 840,  // USD
                'status' => 1,
            ]);
        }

        $taxStatuses = EnterpriseTaxStatusType::pluck('id')->all();
        if (empty($taxStatuses))
        {
            $taxStatuses = [1];
        }

        $servicesModule = Module::where('key', 'services')->first();
        $serviceCategory = null;
        if ($servicesModule)
        {
            $serviceCategory = Category::where('module_id', $servicesModule->id)
                ->where('team_id', $this->teamId)
                ->first();
        }

        $invoiceStatuses = [1, 2];  // Paid, Pending
        $clientsCreated = 0;

        foreach ($clientContacts as $contact)
        {
            // 1. Update contact to status 5 (Cliente)
            $contact->update(['status_id' => 5]);

            // 2. Assign or create an enterprise for this contact
            $enterprise = $enterprises->random();
            $contact->update(['current_enterprise_id' => $enterprise->id]);

            // Update pivot table
            DB::table('contact_enterprise')->updateOrInsert(
                ['contact_id' => $contact->id, 'enterprise_id' => $enterprise->id],
                ['position' => $contact->profile, 'created_at' => now(), 'updated_at' => now()],
            );

            // 3. Create billing address for the enterprise
            $billing = EnterpriseBillingAddress::firstOrCreate(
                ['enterprise_id' => $enterprise->id],
                [
                    'name' => $enterprise->name,
                    'identification_number' => 'CIF-'.strtoupper(substr(md5($enterprise->name), 0, 9)),
                    'tax_status_type_id' => collect($taxStatuses)->random(),
                    'address' => 'Calle '.fake()->streetName().', '.rand(1, 200),
                    'postal_code' => rand(28001, 28999),
                    'locality' => 'Madrid',
                    'province' => 'Madrid',
                    'country' => 'ES',
                    'status' => 1,
                ],
            );

            // 4. Create 1-3 invoices for this client
            $numInvoices = rand(1, 3);
            for ($i = 0; $i < $numInvoices; $i++)
            {
                $invoiceNumber = now()->format('Y').'-'.str_pad($enterprise->id, 4, '0', STR_PAD_LEFT).'-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT);
                $invoiceDate = now()->subDays(rand(1, 180));
                $dueDate = $invoiceDate->copy()->addDays(30);

                $grossAmount = rand(500, 5000);
                $discount = rand(0, $grossAmount * 0.1);
                $taxRate = 21;  // 21% IVA
                $totalAmount = ($grossAmount - $discount) * (1 + $taxRate / 100);
                $status = collect($invoiceStatuses)->random();
                $balance = $status == 1 ? 0 : $totalAmount;  // If paid, balance is 0

                $invoice = Invoice::withoutGlobalScopes()->firstOrCreate(
                    ['number' => $invoiceNumber, 'enterprise_id' => $enterprise->id],
                    [
                        'team_id' => $this->teamId,
                        'billing_id' => $billing->id,
                        'type_id' => $invoiceType?->id ?? 1,
                        'operation' => 'sell',
                        'date' => $invoiceDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'gross_amount' => $grossAmount,
                        'discount' => $discount,
                        'total_amount' => $totalAmount,
                        'balance' => $balance,
                        'status' => $status,
                    ],
                );

                // 5. Create invoice items (2-4 items per invoice)
                $numItems = rand(2, 4);
                for ($j = 0; $j < $numItems; $j++)
                {
                    $itemDescriptions = [
                        'Desarrollo de Software',
                        'Consultoría IT',
                        'Diseño Web',
                        'Marketing Digital',
                        'Soporte Técnico',
                        'Análisis de Datos',
                        'Infraestructura Cloud',
                        'Formación y Capacitación',
                    ];

                    InvoiceItem::firstOrCreate(
                        ['invoice_id' => $invoice->id, 'description' => $itemDescriptions[$j % count($itemDescriptions)]],
                        [
                            'category_id' => $serviceCategory?->id,
                            'quantity' => rand(1, 10),
                            'unit_price' => rand(50, 500),
                            'discount' => rand(0, 50),
                            'tax_percentage' => $taxRate,
                        ],
                    );
                }

                // 6. Create payment if invoice is paid
                if ($status == 1 && $paymentType && $paymentAccount)
                {
                    $paymentDate = $invoiceDate->copy()->addDays(rand(1, 15));
                    if ($paymentDate->lt('2024-07-01'))
                    {
                        $paymentDate = $paymentDate->setDate(2024, 7, 1);
                    }
                    Payment::withoutGlobalScopes()->firstOrCreate(
                        ['invoice_id' => $invoice->id],
                        [
                            'team_id' => $this->teamId,
                            'enterprise_id' => $enterprise->id,
                            'transaction_type' => 'income',
                            'date' => $paymentDate->toDateString(),
                            'account_id' => $paymentAccount->id,
                            'type_id' => $paymentType->id,
                            'amount' => $totalAmount,
                            'remarks' => 'Pago de factura '.$invoiceNumber,
                            'status' => 1,
                        ],
                    );
                }
            }

            $clientsCreated++;
            $this->command->info("✅ Client: {$contact->name} {$contact->surname} - {$numInvoices} invoice(s) created");
        }

        $this->command->info("📊 Summary: {$clientsCreated} clients with billing addresses, invoices and payments");
    }

    private function seedDemoBillingAndInvoices(): void
    {
        $this->command->info('💰 Creating demo invoices...');

        $taxStatuses = EnterpriseTaxStatusType::pluck('id')->all();
        if (empty($taxStatuses))
        {
            $this->call(EnterpriseTaxStatusTypeSeeder::class);
            $taxStatuses = EnterpriseTaxStatusType::pluck('id')->all();
        }

        $enterprises = Enterprise::where('team_id', $this->teamId)->get();
        foreach ($enterprises->take(3) as $enterprise)
        {
            $billing = EnterpriseBillingAddress::firstOrCreate(
                ['enterprise_id' => $enterprise->id],
                [
                    'name' => $enterprise->name.' Billing',
                    'identification_number' => 'ID'.str_pad((string) $enterprise->id, 6, '0', STR_PAD_LEFT),
                    'tax_status_type_id' => collect($taxStatuses)->random(),
                    'address' => 'Main St 123',
                    'postal_code' => '28001',
                    'locality' => 'Madrid',
                    'province' => 'Madrid',
                    'country' => 'ES',
                    'status' => 1,
                ],
            );

            $invoiceType = InvoiceType::first();
            $invoice = Invoice::withoutGlobalScopes()->firstOrCreate(
                ['enterprise_id' => $enterprise->id, 'number' => '2024-'.$enterprise->id],
                [
                    'team_id' => $this->teamId,
                    'billing_id' => $billing->id,
                    'type_id' => $invoiceType?->id ?? 1,
                    'operation' => 'sell',
                    'date' => now()->toDateString(),
                    'due_date' => now()->addDays(30)->toDateString(),
                    'gross_amount' => 1500.0,
                    'discount' => 0,
                    'total_amount' => 1815.0,
                    'balance' => 0,
                    'status' => 1,
                ],
            );

            InvoiceItem::firstOrCreate(
                ['invoice_id' => $invoice->id],
                [
                    'description' => 'Software Development',
                    'quantity' => 1,
                    'unit_price' => 1500.0,
                    'discount' => 0,
                    'tax_percentage' => 21,
                ],
            );
        }

        $this->command->info('✅ Demo invoices created');
    }

    private function seedDemoPayments(): void
    {
        $this->command->info('💳 Creating demo payments...');

        $accountsBefore = PaymentAccount::where('team_id', $this->teamId)->count();
        if ($accountsBefore === 0)
        {
            $this->call(PaymentAccountSeeder::class);
        }

        $account = PaymentAccount::withoutGlobalScopes()->where('team_id', $this->teamId)->first();
        $paymentType = PaymentType::first();
        $invoices = Invoice::withoutGlobalScopes()
            ->where('team_id', $this->teamId)
            ->where('status', 1)
            ->get();

        foreach ($invoices->take(10) as $invoice)
        {
            $paymentDate = $invoice->due_date;
            if (is_string($paymentDate))
            {
                $paymentDate = \Carbon\Carbon::parse($paymentDate);
            }
            if ($paymentDate->lt('2024-07-01'))
            {
                $paymentDate = \Carbon\Carbon::parse('2024-07-01');
            }
            Payment::withoutGlobalScopes()->firstOrCreate(
                ['invoice_id' => $invoice->id],
                [
                    'team_id' => $this->teamId,
                    'enterprise_id' => $invoice->enterprise_id,
                    'transaction_type' => 'income',
                    'date' => $paymentDate->toDateString(),
                    'account_id' => $account?->id ?? 1,
                    'type_id' => $paymentType?->id ?? 1,
                    'amount' => $invoice->total_amount,
                    'remarks' => 'Payment for invoice '.$invoice->number,
                    'status' => 1,
                ],
            );
        }

        $this->command->info('✅ Demo payments created');
    }

    private function fixGrapesJsStructure(): void
    {
        $this->command->info('🔧 Fixing GrapesJS structure...');

        $templates = Template::where('team_id', $this->teamId)->get();
        $fixed = 0;

        foreach ($templates as $template)
        {
            try
            {
                $result = GrapesJsHelper::fixTemplateStructure($template);
                if ($result)
                {
                    $fixed++;
                }
            } catch (\Exception $e)
            {
                $this->command->error('❌ Error: '.$e->getMessage());
            }
        }

        $this->command->info("✅ Fixed {$fixed} templates");
    }

    private function ensureMessageTypesExist(): void
    {
        $messageTypes = [
            ['id' => 1, 'name' => 'Mailer', 'status' => 1],
            ['id' => 2, 'name' => 'WhatsApp', 'status' => 1],
        ];

        foreach ($messageTypes as $type)
        {
            DB::table('message_type')->updateOrInsert(['id' => $type['id']], $type);
        }
    }

    private function seedDemoCalendarEvents(): void
    {
        $this->command->info('📅 Creating demo calendar events...');

        $teamId = $this->teamId;
        $base = now()->startOfMonth();

        $events = [
            [
                'title' => 'Design Review',
                'start' => $base->copy()->setDay(16)->setTime(9, 52),
                'end' => $base->copy()->setDay(16)->setTime(11, 0),
                'label' => 'Business',
                'notes' => 'Review Q1 designs with the team',
            ],
            [
                'title' => 'Dinner',
                'start' => $base->copy()->setDay(18)->setTime(0, 0),
                'end' => $base->copy()->setDay(18)->setTime(1, 0),
                'all_day' => false,
                'label' => 'Personal',
                'notes' => 'Team dinner',
            ],
            [
                'title' => 'Dart Game',
                'start' => $base->copy()->setDay(18)->setTime(18, 0),
                'end' => $base->copy()->setDay(18)->setTime(19, 30),
                'label' => 'Personal',
            ],
            [
                'title' => 'Doctor\'s',
                'start' => $base->copy()->setDay(20)->setTime(9, 0),
                'end' => $base->copy()->setDay(20)->setTime(10, 0),
                'label' => 'Personal',
                'notes' => 'Annual check-up',
            ],
            [
                'title' => 'Meeting with client',
                'start' => $base->copy()->setDay(20)->setTime(14, 0),
                'end' => $base->copy()->setDay(20)->setTime(15, 30),
                'label' => 'Business',
                'url' => 'https://meet.example.com/demo',
            ],
            [
                'title' => 'Family Trip',
                'start' => $base->copy()->setDay(22)->setTime(8, 0),
                'end' => $base->copy()->setDay(24)->setTime(20, 0),
                'all_day' => true,
                'label' => 'Family',
                'notes' => 'Weekend getaway',
            ],
            [
                'title' => 'Monthly Meeting',
                'start' => $base->copy()->endOfMonth()->setTime(10, 0),
                'end' => $base->copy()->endOfMonth()->setTime(12, 0),
                'label' => 'Business',
                'notes' => 'Monthly all-hands',
            ],
        ];

        foreach ($events as $data)
        {
            $start = $data['start'];
            $end = $data['end'] ?? $start->copy()->addHour();

            CalendarEvent::withoutGlobalScopes()->firstOrCreate(
                [
                    'team_id' => $teamId,
                    'title' => $data['title'],
                    'start' => $start,
                ],
                [
                    'end' => $end,
                    'all_day' => $data['all_day'] ?? false,
                    'notes' => $data['notes'] ?? null,
                    'url' => $data['url'] ?? null,
                    'label' => $data['label'] ?? 'Business',
                ],
            );
        }

        $this->command->info('✅ Demo calendar events created');
    }
}
