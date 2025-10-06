<?php

namespace Database\Seeders;

use App\Helpers\GrapesJsHelper;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Message;
use App\Models\Module;
use App\Models\Project;
use App\Models\Service;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use Idoneo\HumanoBilling\Models\Invoice;
use Idoneo\HumanoBilling\Models\Payment;
use Idoneo\HumanoBilling\Models\PaymentAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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

		// 7. Create demo message for Staff category
		$this->createDemoMessage();

		// 8. Configure email settings
		$this->configureRevisionAlphaEmailSettings($team);

		// 9. Import base data from remote database
		$this->command->info('📊 Importing base data from remote database...');
		$this->importRevisionAlphaCategories();
		$this->importRevisionAlphaPaymentAccounts();
		$this->importRevisionAlphaPayments();

		// Note: Services, Projects, Invoices, and Invoice Items are NOT imported here
		// because they require enterprises to be imported first.
		// Run: php artisan import:interactive --auto to import all data including enterprises
		$this->command->info('');
		$this->command->info('ℹ️  Services, projects, and invoices will be imported with: php artisan import:interactive --auto');

		// Asignar módulos CORE por defecto al equipo
		// Estos son los módulos fundamentales que se activan automáticamente
		$defaultModuleKeys = [
			'dashboard',  // Dashboard (siempre activo)
			'users',  // Gestión de usuarios
			'contacts',  // Gestión de contactos
			'clients',  // Gestión de clientes
			'services',  // Catálogo de servicios
			'projects',  // Gestión de proyectos
			'tasks',  // Sistema de tareas (Kanban)
			'invoices',  // Gestión de facturas
			'payments',  // Gestión de pagos
			'accounting',  // Contabilidad
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

		if (!$revisionUser) {
			$this->command->warn('⚠️  Revision user not found. Creating it now...');

			// Create the user if it doesn't exist
			$revisionUser = User::create([
				'name' => 'Diego Mascarenhas',
				'email' => 'diego.mascarenhas@icloud.com',
				'password' => Hash::make('Simplicity!'),
				'email_verified_at' => now(),
			]);

			// Assign admin role
			$revisionUser->assignRole('admin');

			$this->command->info("✅ Created Revision user: {$revisionUser->email}");
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
		if (!$team->users()->where('user_id', $revisionUser->id)->exists()) {
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

	/** Create Revision Alpha enterprise */
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

	/** Create Revision Alpha contacts */
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
                        <table width="100%" bgcolor="#F5EFEF" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td height="20"></td>
                            </tr>
                            <tr>
                                <td align="center">
                                    <table width="700" bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center">
                                                <table width="660" bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td height="25" colspan="2"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <h1 style="text-align: left; margin: 0; padding: 0">
                                                                <img
                                                                    src="https://revisionalpha.com/assets/revision-alpha-new-logo-color.svg"
                                                                    alt="revision alpha"
                                                                    width="300"
                                                                    style="display: block; position: relative; margin: 0; padding: 0"
                                                                />
                                                            </h1>
                                                        </td>
                                                        <td align="right">
                                                            <!-- Header area - now empty to match template changes -->
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td height="25" colspan="2"></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td height="2px" bgcolor="#FF1A1D"></td>
                                        </tr>
                                        <tr>
                                            <td align="center">
                                                <table width="660" bgcolor="#FFFFFF" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td height="50"></td>
                                                    </tr>

                                                    <!-- CONTENT SECTION -->
                                                    <tr>
                                                        <td>
                                                            <div style="text-align: center; margin-bottom: 30px">
                                                                <h1 style="font-size: 28px; color: #2a333d; margin: 0; font-weight: 700">Email Marketing fácil, rápido y seguro</h1>
                                                                <h2 style="font-size: 18px; color: #666; margin: 8px 0 15px 0; font-weight: 400; font-style: italic;">Comunicarte con tus clientes nunca fue tan fácil</h2>
                                                                <h3 style="font-size: 20px; color: #36f1cd; margin: 15px 0 0 0; font-weight: 600">¡GRATUITO para todos nuestros clientes!</h3>
                                                                <div
                                                                    style="
                                                                        width: 50px;
                                                                        height: 3px;
                                                                        background: linear-gradient(90deg, #36f1cd 0%, #ff1a1d 100%);
                                                                        margin: 15px auto;
                                                                    "
                                                                ></div>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                                                                        <tr>
                                                        <td style="text-align: center; margin: 30px 0;">
                                                            <p style="color: #555; margin: 0 0 25px 0; line-height: 1.6; font-size: 16px; text-align: center;">
                                                                <span style="color: #777; font-size: 14px;">Solo sube tus contactos y redacta tu mensaje<br/>
                                                                <strong style="color: #ff1a1d;">¡Nosotros nos encargamos del resto!</strong></span>
                                                            </p>

                                                            <!-- Features Grid -->
                                                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                                                <tr>
                                                                    <td width="50%" style="padding: 10px; vertical-align: top;">
                                                                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px; margin: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                                                            <div style="width: 50px; height: 50px; background: #ff1a1d; border-radius: 12px; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                                                                                <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                                                                    <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.6 14.8,10V11.5C14.8,12.6 13.9,13.5 12.8,13.5H11.2C10.1,13.5 9.2,12.6 9.2,11.5V10C9.2,8.6 10.6,7 12,7Z"/>
                                                                                </svg>
                                                                            </div>
                                                                            <h4 style="color: #2a333d; margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">Envíos controlados</h4>
                                                                            <p style="color: #666; margin: 0; font-size: 13px; line-height: 1.4;">Sistema inteligente anti-spam que mejora la reputación de tu dominio</p>
                                                                        </div>
                                                                    </td>
                                                                    <td width="50%" style="padding: 10px; vertical-align: top;">
                                                                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px; margin: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                                                            <div style="width: 50px; height: 50px; background: #ff1a1d; border-radius: 12px; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                                                                                <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                                                                    <path d="M20,8L12,13L4,8V6L12,11L20,6M20,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6C22,4.89 21.1,4 20,4Z"/>
                                                                                </svg>
                                                                            </div>
                                                                            <h4 style="color: #2a333d; margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">Campañas simples</h4>
                                                                            <p style="color: #666; margin: 0; font-size: 13px; line-height: 1.4;">Diseño profesional sin conocimientos técnicos</p>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td width="50%" style="padding: 10px; vertical-align: top;">
                                                                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px; margin: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                                                            <div style="width: 50px; height: 50px; background: #ff1a1d; border-radius: 12px; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                                                                                <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                                                                    <path d="M9,12L11,14L15,10L13,8L11,10L9,8M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4Z"/>
                                                                                </svg>
                                                                            </div>
                                                                            <h4 style="color: #2a333d; margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">Protección total</h4>
                                                                            <p style="color: #666; margin: 0; font-size: 13px; line-height: 1.4;">Cumplimiento GDPR y estándares de seguridad</p>
                                                                        </div>
                                                                    </td>
                                                                    <td width="50%" style="padding: 10px; vertical-align: top;">
                                                                        <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 12px; margin: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                                                            <div style="width: 50px; height: 50px; background: #ff1a1d; border-radius: 12px; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                                                                                <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                                                                    <path d="M16,11.78L20.24,4.45L21.97,5.45L16.74,14.5L10.23,10.75L5.46,19H22V21H2V3H4V17.54L9.5,8L16,11.78Z"/>
                                                                                </svg>
                                                                            </div>
                                                                            <h4 style="color: #2a333d; margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">Reportes completos</h4>
                                                                            <p style="color: #666; margin: 0; font-size: 13px; line-height: 1.4;">Métricas detalladas para optimizar campañas</p>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    </tr>

                                                    <tr>
                                                        <td style="text-align: center; padding: 40px 0">
                                                            <div
                                                                style="
                                                                    background: linear-gradient(135deg, #36f1cd 0%, #2dd4b4 100%);
                                                                    border-radius: 50px;
                                                                    display: inline-block;
                                                                    padding: 3px;
                                                                "
                                                            >
                                                                <a
                                                                    href="https://revisionalpha.com/emailer"
                                                                    style="
                                                                        display: inline-block;
                                                                        padding: 16px 35px;
                                                                        background: #fff;
                                                                        color: #2a333d;
                                                                        text-decoration: none;
                                                                        border-radius: 47px;
                                                                        font-weight: 700;
                                                                        font-size: 16px;
                                                                        transition: all 0.3s ease;
                                                                        box-shadow: 0 4px 15px rgba(54, 241, 205, 0.3);
                                                                    "
                                                                >
                                                                    <strong>¡Empieza ahora!</strong>
                                                                </a>
                                                            </div>
                                                            <p style="color: #999; font-size: 12px; margin: 15px 0 0 0">Comienza tu campaña de email marketing</p>
                                                        </td>
                                                    </tr>

                                                    <tr>
                                                        <td>
                                                            <div style="text-align: center; margin: 30px 0">
                                                                <div
                                                                    style="
                                                                        width: 100%;
                                                                        height: 1px;
                                                                        background: linear-gradient(90deg, transparent 0%, #36f1cd 50%, transparent 100%);
                                                                        margin: 20px 0;
                                                                    "
                                                                ></div>
                                                                <div style="margin-top: 15px">
                                                                    <span style="color: #36f1cd; font-size: 20px">✨</span>
                                                                    <span style="color: #ff1a1d; font-size: 16px; margin: 0 8px">•</span>
                                                                    <span style="color: #36f1cd; font-size: 20px">✨</span>
                                                                </div>
                                                                <p style="color: #2a333d; font-size: 16px; font-weight: 600; margin: 15px 0 0 0">
                                                                    <strong>¡Gracias por confiar en nosotros!</strong>
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <!-- END CONTENT SECTION -->

                                                    <tr>
                                                        <td height="50"></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td height="10" bgcolor="#FF1A1D"></td>
                                        </tr>
                                        <tr>
                                            <td align="center">
                                                <table width="100%" bgcolor="#2A333D" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td align="center">
                                                            <table
                                                                width="660"
                                                                bgcolor="#2A333D"
                                                                border="0"
                                                                cellpadding="0"
                                                                cellspacing="0"
                                                            >
                                                                <tr>
                                                                    <td height="25" colspan="2"></td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        <a
                                                                            href="https://www.revisionalpha.com/"
                                                                            style="
                                                                                font-size: 17px;
                                                                                color: #ffffff;
                                                                                text-decoration: none;
                                                                            "
                                                                            ><img
                                                                                src="https://revisionalpha.com/assets/revision-alpha-new-logo-blanco-y-rojo.svg"
                                                                                alt="revision alpha"
                                                                                style="display: block; position: relative; width: 150px"
                                                                            />
                                                                            www.revisionalpha.com</a
                                                                        >
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
                                                                <tr>
                                                                    <td height="25" colspan="2"></td>
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
                            <tr>
                                <td height="20"></td>
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
			$this->command->error('❌ Error fixing template structure: ' . $e->getMessage());
		}

		// Show editor URL for reference
		$editorUrl = route('template.editor', $template->getHashedId());
		$this->command->info("🔗 Editor URL: {$editorUrl}");
	}

	/**
	 * Create demo message for Staff category
	 */
	private function createDemoMessage(): void
	{
		$this->command->info('📧 Creating demo message for Staff category...');

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

		if ($professionalTemplate && $staffCategory) {
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

			$this->command->info("✅ Message created for Staff category (Message ID: {$message->id})");
			$this->command->info("   - Template: {$professionalTemplate->name} (ID: {$professionalTemplate->id})");
			$this->command->info("   - Category: {$staffCategory->name} (ID: {$staffCategory->id})");
		} else {
			if (!$professionalTemplate) {
				$this->command->warn('⚠️  Professional template not found for message creation');
			}
			if (!$staffCategory) {
				$this->command->warn('⚠️  Staff category not found for message creation');
			}
		}
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

	/**
	 * Ensure message types exist in the database
	 */
	private function ensureMessageTypesExist(): void
	{
		$messageTypes = [
			['id' => 1, 'name' => 'Mailer', 'status' => 1],
			['id' => 2, 'name' => 'WhatsApp', 'status' => 1],
		];

		foreach ($messageTypes as $type) {
			DB::table('message_type')->updateOrInsert(
				['id' => $type['id']],
				$type
			);
		}

		$this->command->info('✅ Message types ensured');
	}

	/**
	 * Import service categories from remote database for Revision Alpha
	 */
	private function importRevisionAlphaCategories(): void
	{
		$this->command->info('📂 Importing Revision Alpha service categories from remote database...');

		try {
			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group for Revision Alpha
			$cmsGroup = env('CMS_GROUP', 502);
			$this->command->info("   Using CMS_GROUP: {$cmsGroup}");

			// Import ALL categories from categorias_generales
			$this->command->info('📂 Importing ALL general categories (categorias_generales)...');

			$allCategories = DB::connection('mysql_tmp')
				->table('categorias_generales')
				->where('grupo', $cmsGroup)
				->where('estado', '>', 0)
				->orderBy('padre')
				->orderBy('id')
				->get();

			$imported = 0;
			$skipped = 0;

			foreach ($allCategories as $category) {
				try {
					// Determine module based on parent or ID
					$moduleKey = null;
					if ($category->padre == 10 || $category->id == 10) {
						$moduleKey = 'services';
					}

					$module = $moduleKey ? \App\Models\Module::where('key', $moduleKey)->first() : null;

					$description = strip_tags($category->descripcion ?? '');
					if (empty(trim($description))) {
						$description = $category->categoria ?? 'Sin descripción';
					}

					\App\Models\Category::updateOrCreate(
						['id' => $category->id],
						[
							'team_id' => $this->teamId,
							'name' => $category->categoria ?? 'Sin nombre',
							'module_id' => $module?->id,
							'parent_id' => $category->padre > 0 ? $category->padre : null,
							'description' => $description,
							'status' => $category->estado > 0 ? 1 : 0,
							'created_at' => $category->fecha_alta ?? now(),
							'updated_at' => $category->fecha_modificacion ?? now(),
						]
					);
					$imported++;
				} catch (\Exception $e) {
					$skipped++;
					if ($skipped <= 10) {
						$this->command->warn("     Skipped category {$category->id}: " . $e->getMessage());
					}
				}
			}

			if ($skipped > 0) {
				$this->command->warn("   ⚠️  Skipped {$skipped} general categories due to errors");
			}

			$this->command->info("✅ Imported {$imported} general categories");

			// Import project categories from the 'categorias' table
			$this->command->info('📂 Importing project categories...');
			$projectModule = \App\Models\Module::where('key', 'projects')->first();

			if ($projectModule) {
				// Get all unique category IDs used by projects
				$usedCategoryIds = DB::connection('mysql_tmp')
					->table('proyectos')
					->where('grupo', $cmsGroup)
					->where('estado', '>', 0)
					->whereNotNull('id_categoria')
					->distinct()
					->pluck('id_categoria');

				$projectCategories = DB::connection('mysql_tmp')
					->table('categorias')
					->where('grupo', $cmsGroup)
					->whereIn('id', $usedCategoryIds)
					->get();

				$projectImported = 0;
				foreach ($projectCategories as $category) {
					try {
						$description = $category->categoria ?? 'Sin descripción';

						\App\Models\Category::updateOrCreate(
							['id' => $category->id],
							[
								'team_id' => $this->teamId,
								'name' => $category->categoria ?? 'Sin nombre',
								'module_id' => $projectModule->id,
								'parent_id' => $category->padre == 10 ? 10 : null,
								'description' => $description,
								'status' => 1,
								'created_at' => $category->fecha_alta ?? now(),
								'updated_at' => $category->fecha_modificacion ?? now(),
							]
						);
						$projectImported++;
					} catch (\Exception $e) {
						$this->command->warn("     Skipped project category {$category->id}: " . $e->getMessage());
					}
				}
				$this->command->info("✅ Imported {$projectImported} project categories");
			}
		} catch (\Exception $e) {
			$this->command->warn('⚠️  Could not import categories: ' . $e->getMessage());
		}
	}

	/**
	 * Import services from remote database for Revision Alpha
	 */
	private function importRevisionAlphaServices(): void
	{
		$this->command->info('📦 Importing Revision Alpha services from remote database...');

		try {
			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group for Revision Alpha
			$cmsGroup = env('CMS_GROUP', 502);
			$this->command->info("   Using CMS_GROUP: {$cmsGroup}");

			$services = DB::connection('mysql_tmp')
				->table('servicios')
				->where('servicios.grupo', $cmsGroup)
				->where('servicios.estado', '>', 0)
				->where('servicios.operacion', 'V')  // Only sales
				->get();

			$imported = 0;
			$skipped = 0;
			foreach ($services as $service) {
				try {
					// Map operation codes: V=sell (Venta), C=buy (Compra)
					$operation = ($service->operacion ?? 'V') === 'V' ? 'sell' : 'buy';

					Service::updateOrCreate(
						['id' => $service->id],
						[
							'enterprise_id' => $service->id_empresa,
							'category_id' => $service->id_categoria ?? null,
							'operation' => $operation,
							'description' => strip_tags($service->descripcion ?? ''),
							'price' => $service->valor ?? 0,
							'frequency' => $service->frecuencia ?? 'M',
							'currency_id' => $service->id_moneda ?? 1,
							'discount' => $service->descuento ?? 0,
							'status' => $service->estado ?? 1,
							'next_billing' => $service->proxima ?? null,
							'last_billed' => $service->ultima ?? null,
							'expires_at' => $service->caduca ?? null,
							'created_at' => $service->fecha_alta ?? now(),
							'updated_at' => $service->fecha_modificacion ?? now(),
						]
					);
					$imported++;
				} catch (\Exception $e) {
					$skipped++;
					if ($skipped <= 10) {
						$this->command->warn("     Skipped service {$service->id}: " . $e->getMessage());
					}
				}
			}

			if ($skipped > 0) {
				$this->command->warn("   ⚠️  Skipped {$skipped} services due to errors");
			}

			$this->command->info("✅ Imported {$imported} services");
		} catch (\Exception $e) {
			$this->command->warn('⚠️  Could not import services: ' . $e->getMessage());
		}
	}

	/**
	 * Import invoices from remote database for Revision Alpha
	 */
	private function importRevisionAlphaInvoices(): void
	{
		$this->command->info('📄 Importing Revision Alpha invoices from remote database...');

		try {
			// Verify invoices table exists
			if (!Schema::hasTable('invoices')) {
				$this->command->warn('⚠️  Invoices table does not exist. Skipping invoice import.');
				$this->command->info('   Run: php artisan vendor:publish --tag="humano-billing-migrations" && php artisan migrate');
				return;
			}

			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group for Revision Alpha
			$cmsGroup = env('CMS_GROUP', 502);
			$this->command->info("   Using CMS_GROUP: {$cmsGroup}");

			$invoices = DB::connection('mysql_tmp')
				->table('facturas')
				->join('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
				->leftJoin('facturas_tipo', 'facturas.id_factura_tipo', '=', 'facturas_tipo.id')
				->where('facturas.grupo', $cmsGroup)
				->where('facturas.estado', '>', 0)
				->select('facturas.*', 'empresas_fiscales.id_empresa as enterprise_id', 'facturas_tipo.factura_tipo as tipo_letra')
				->get();

			$imported = 0;
			$skipped = 0;
			foreach ($invoices as $invoice) {
				try {
					// Map operation codes: V=sell (Venta), C=buy (Compra)
					$operation = ($invoice->operacion ?? 'V') === 'V' ? 'sell' : 'buy';

					// Format invoice number: letra + space + talonario (4 digits) - numero_factura (8 digits)
					$invoiceNumber = '1';
					if ($operation === 'sell') {
						// Extract first letter from tipo_letra (e.g. "A Flora" -> "A")
						$letra = $invoice->tipo_letra ? substr($invoice->tipo_letra, 0, 1) : 'X';
						$talonario = str_pad($invoice->numero_talonario ?? 0, 4, '0', STR_PAD_LEFT);
						$numero = str_pad($invoice->numero_factura ?? 0, 8, '0', STR_PAD_LEFT);
						$invoiceNumber = $letra . ' ' . $talonario . '-' . $numero;
					} else {
						$invoiceNumber = $invoice->numero_factura ?? '1';
					}

					Invoice::updateOrCreate(
						['id' => $invoice->id],
						[
							'enterprise_id' => $invoice->enterprise_id,
							'type_id' => $invoice->id_factura_tipo ?? 1,
							'number' => $invoiceNumber,
							'date' => $invoice->fecha ?? now(),
							'due_date' => $invoice->vencimiento ?? null,
							'operation' => $operation,
							'gross_amount' => $invoice->bruto ?? 0,
							'total_amount' => $invoice->total_neto ?? 0,
							'discount' => $invoice->descuento ?? 0,
							'balance' => $invoice->saldo ?? 0,
							'status' => $invoice->estado ?? 1,
							'created_at' => $invoice->fecha_alta ?? now(),
							'updated_at' => $invoice->fecha_modificacion ?? now(),
						]
					);
					$imported++;
				} catch (\Exception $e) {
					$skipped++;
					if ($skipped <= 10) {
						$this->command->warn("     Skipped invoice {$invoice->id}: " . $e->getMessage());
					}
				}
			}

			if ($skipped > 0) {
				$this->command->warn("   ⚠️  Skipped {$skipped} invoices due to errors");
			}

			$this->command->info("✅ Imported {$imported} invoices");
		} catch (\Exception $e) {
			$this->command->warn('⚠️  Could not import invoices: ' . $e->getMessage());
		}
	}

	/**
	 * Import invoice items from remote database for Revision Alpha
	 */
	private function importRevisionAlphaInvoiceItems(): void
	{
		$this->command->info('📝 Importing Revision Alpha invoice items from remote database...');

		try {
			// Verify invoice_items table exists
			if (!Schema::hasTable('invoice_items')) {
				$this->command->warn('⚠️  Invoice items table does not exist. Skipping invoice items import.');
				$this->command->info('   Run: php artisan vendor:publish --tag="humano-billing-migrations" && php artisan migrate');
				return;
			}

			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group for Revision Alpha
			$cmsGroup = env('CMS_GROUP', 502);
			$this->command->info("   Using CMS_GROUP: {$cmsGroup}");

			$items = DB::connection('mysql_tmp')
				->table('facturas_items')
				->whereExists(function ($query) use ($cmsGroup) {
					$query
						->select(DB::raw(1))
						->from('facturas')
						->whereColumn('facturas.id', 'facturas_items.id_factura')
						->where('facturas.grupo', $cmsGroup)
						->where('facturas.estado', '>', 0);
				})
				->get();

			$imported = 0;
			$skipped = 0;
			foreach ($items as $item) {
				try {
					// Verify that the invoice exists
					$invoiceExists = Invoice::where('id', $item->id_factura)->exists();
					if (!$invoiceExists) {
						$skipped++;
						continue;
					}

					// Verify that the category exists if provided
					$categoryId = null;
					$categoryName = null;
					if ($item->id_categoria) {
						$category = \App\Models\Category::where('id', $item->id_categoria)->first();
						if ($category) {
							$categoryId = $category->id;
							$categoryName = $category->name;
						}
					}

					// Determine description: use item description, fallback to category name, then "Sin descripción"
					$description = 'Sin descripción';
					if (!empty($item->articulo) && trim($item->articulo) !== '') {
						$description = $item->articulo;
					} elseif ($categoryName) {
						$description = $categoryName;
					}

					\Idoneo\HumanoBilling\Models\InvoiceItem::updateOrCreate(
						['id' => $item->id],
						[
							'invoice_id' => $item->id_factura,
							'category_id' => $categoryId,
							'description' => $description,
							'quantity' => $item->cantidad ?? 1,
							'unit_price' => $item->precio ?? 0,
							'discount' => $item->descuento ?? 0,
							'tax_percentage' => $item->porcentaje_impuesto ?? 0,
						]
					);
					$imported++;
				} catch (\Exception $e) {
					$skipped++;
					if ($skipped <= 10) {
						$this->command->warn("     Skipped invoice item {$item->id}: " . $e->getMessage());
					}
				}
			}

			if ($skipped > 0) {
				$this->command->warn("   ⚠️  Skipped {$skipped} invoice items due to errors");
			}

			$this->command->info("✅ Imported {$imported} invoice items");
		} catch (\Exception $e) {
			$this->command->warn('⚠️  Could not import invoice items: ' . $e->getMessage());
		}
	}

	/**
	 * Import projects from remote database for Revision Alpha
	 */
	private function importRevisionAlphaProjects(): void
	{
		$this->command->info('📁 Importing Revision Alpha projects from remote database...');

		try {
			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group for Revision Alpha
			$cmsGroup = env('CMS_GROUP', 502);
			$this->command->info("   Using CMS_GROUP: {$cmsGroup}");

			$projects = DB::connection('mysql_tmp')
				->table('proyectos')
				->where('grupo', $cmsGroup)
				->where('estado', '>', 0)
				->get();

			$imported = 0;
			$skipped = 0;
			foreach ($projects as $project) {
				try {
					// Get responsible user - default to the team owner if not found
					$responsibleId = \App\Models\User::where('email', 'diego.mascarenhas@icloud.com')->first()->id;

					// Check if enterprise exists
					if (!DB::table('enterprises')->where('id', $project->id_empresa)->exists()) {
						$skipped++;
						continue;
					}

					\App\Models\Project::updateOrCreate(
						['id' => $project->id],
						[
							'team_id' => $this->teamId,
							'enterprise_id' => $project->id_empresa,
							'category_id' => $project->id_categoria ?? null,
							'responsible_id' => $responsibleId,
							'name' => $project->titulo ?? 'Proyecto ' . $project->id,
							'real_name' => null,
							'description' => $project->descripcion ?? null,
							'date_material' => null,
							'date_start' => $project->desde ?? null,
							'date_end' => $project->hasta ?? null,
							'cost' => $project->costo ?? 0,
							'price' => $project->valor ?? 0,
							'discount' => $project->descuento ?? 0,
							'status_id' => $project->estado ?? 1,
							'created_at' => $project->fecha_alta ?? now(),
							'updated_at' => $project->fecha_modificacion ?? now(),
						]
					);
					$imported++;
				} catch (\Exception $e) {
					$skipped++;
					if ($skipped <= 10) {
						$this->command->warn("     Skipped project {$project->id}: " . $e->getMessage());
					}
				}
			}

			if ($skipped > 0) {
				$this->command->warn("   ⚠️  Skipped {$skipped} projects due to errors");
			}

			$this->command->info("✅ Imported {$imported} projects");
		} catch (\Exception $e) {
			$this->command->warn('⚠️  Could not import projects: ' . $e->getMessage());
		}
	}

	/**
	 * Import payment accounts from remote database for Revision Alpha
	 */
	private function importRevisionAlphaPaymentAccounts(): void
	{
		$this->command->info('🏦 Importing Revision Alpha payment accounts from remote database...');

		try {
			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group for Revision Alpha
			$cmsGroup = env('CMS_GROUP', 502);
			$this->command->info("   Using CMS_GROUP: {$cmsGroup}");

			$accounts = DB::connection('mysql_tmp')
				->table('cuentas')
				->where('grupo', $cmsGroup)
				->where('estado', 1)
				->get();

			// Currency mapping: legacy ID to ISO code
			$currencyMap = [
				1 => 840,  // USD (assuming legacy 1 is USD or local currency)
				2 => 840,  // USD
				3 => 978,  // EUR
			];

			$imported = 0;
			$skipped = 0;
			foreach ($accounts as $account) {
				try {
					// Map legacy currency ID to ISO currency ID
					$legacyCurrencyId = $account->id_moneda ?? 1;
					$currencyId = $currencyMap[$legacyCurrencyId] ?? 840;  // Default to USD

					// Generate unique code: use numero_cuenta if available, otherwise use ID
					$rawCode = trim($account->numero_cuenta ?? '');
					$code = !empty($rawCode) ? substr($rawCode, 0, 10) : 'ACC-' . $account->id;

					// Use team_id + code as unique key (matching the database constraint)
					PaymentAccount::updateOrCreate(
						[
							'team_id' => $this->teamId,
							'code' => $code,
						],
						[
							'id' => $account->id,
							'name' => $account->nombre_cuenta ?? 'Cuenta ' . $account->id,
							'symbol' => null,
							'currency_id' => $currencyId,
							'status' => $account->estado ?? 1,
							'created_at' => $account->fecha_alta ?? now(),
							'updated_at' => $account->fecha_modificacion ?? now(),
						]
					);
					$imported++;
				} catch (\Exception $e) {
					$skipped++;
					if ($skipped <= 10) {
						$this->command->warn("     Skipped account {$account->id}: " . $e->getMessage());
					}
				}
			}

			if ($skipped > 0) {
				$this->command->warn("   ⚠️  Skipped {$skipped} accounts due to errors");
			}

			$this->command->info("✅ Imported {$imported} payment accounts");
		} catch (\Exception $e) {
			$this->command->warn('⚠️  Could not import payment accounts: ' . $e->getMessage());
		}
	}

	/**
	 * Import payments from remote database for Revision Alpha
	 */
	private function importRevisionAlphaPayments(): void
	{
		$this->command->info('💰 Importing Revision Alpha payments from remote database...');

		try {
			// Verify payments table exists
			if (!Schema::hasTable('payments')) {
				$this->command->warn('⚠️  Payments table does not exist. Skipping payment import.');
				$this->command->info('   Run: php artisan vendor:publish --tag="humano-billing-migrations" && php artisan migrate');
				return;
			}

			// Test connection
			DB::connection('mysql_tmp')->getPdo();

			// Get the CMS group for Revision Alpha
			$cmsGroup = env('CMS_GROUP', 502);
			$this->command->info("   Using CMS_GROUP: {$cmsGroup}");

			// Payment type mapping from legacy to new IDs
			$paymentTypeMap = [
				1 => 1,  // Cash
				2 => 2,  // Bank Transfer
				3 => 3,  // Bank Deposit
				4 => 4,  // Check
				5 => 5,  // Debit
				10 => 6,  // Credit Card
				7 => 7,  // PayPal
				17 => 8,  // Stripe
				6 => 12,  // MercadoPago
				13 => 12,  // MercadoPago
				14 => 12,  // MercadoPago
			];

			$payments = DB::connection('mysql_tmp')
				->table('movimientos')
				->leftJoin('facturas', 'movimientos.id_factura', '=', 'facturas.id')
				->leftJoin('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
				->where('movimientos.grupo', $cmsGroup)
				->where('movimientos.estado', '>', 0)
				->select(
					'movimientos.*',
					'empresas_fiscales.id_empresa as enterprise_id',
					'facturas.id_empresa_fiscal'
				)
				->get();

			$imported = 0;
			$skipped = 0;
			foreach ($payments as $payment) {
				try {
					// Get account ID - if not exists, use default account for this team
					$accountId = $payment->id_cuenta;
					if (!$accountId || !DB::table('payment_accounts')->where('id', $accountId)->exists()) {
						// Get the first account for this team as default
						$defaultAccount = DB::table('payment_accounts')
							->where('team_id', $this->teamId)
							->first();

						if (!$defaultAccount) {
							$skipped++;
							continue;  // Skip if no accounts exist for this team
						}

						$accountId = $defaultAccount->id;
					}

					// Map legacy payment type ID to new ID
					$legacyTypeId = $payment->id_forma_pago ?? 1;
					$typeId = $paymentTypeMap[$legacyTypeId] ?? 1;  // Default to Cash if not mapped

					// Determine transaction type: I=Income, E=Expense (default to expense if unknown)
					$transactionType = 'expense';
					if (isset($payment->transaccion)) {
						$transactionType = strtoupper($payment->transaccion) === 'I' ? 'income' : 'expense';
					}

					// Get amount from 'valor' field
					$amount = $payment->valor ?? 0;

					// Get enterprise_id from multiple sources
					$enterpriseId = null;

					// 1. Try from the JOIN result
					if ($payment->enterprise_id) {
						if (DB::table('enterprises')->where('id', $payment->enterprise_id)->exists()) {
							$enterpriseId = $payment->enterprise_id;
						}
					}

					// 2. If still null, try to get from invoice
					$invoiceId = $payment->id_factura;
					if (!$enterpriseId && $invoiceId) {
						$invoice = DB::table('invoices')->where('id', $invoiceId)->first();
						if ($invoice && $invoice->enterprise_id) {
							$enterpriseId = $invoice->enterprise_id;
						}
						// If invoice doesn't exist, set invoiceId to null
						if (!$invoice) {
							$invoiceId = null;
						}
					}

					// 3. If still null and we have id_empresa_fiscal, try to find the enterprise
					if (!$enterpriseId && isset($payment->id_empresa_fiscal)) {
						$enterpriseFromFiscal = DB::table('enterprises')
							->where('id', $payment->id_empresa_fiscal)
							->where('team_id', $this->teamId)
							->first();
						if ($enterpriseFromFiscal) {
							$enterpriseId = $enterpriseFromFiscal->id;
						}
					}

					Payment::updateOrCreate(
						['id' => $payment->id],
						[
							'team_id' => $this->teamId,
							'enterprise_id' => $enterpriseId,
							'invoice_id' => $invoiceId,
							'transaction_type' => $transactionType,
							'date' => $payment->fecha ? \Carbon\Carbon::parse($payment->fecha)->format('Y-m-d') : now()->format('Y-m-d'),
							'amount' => $amount,
							'type_id' => $typeId,
							'account_id' => $accountId,
							'remarks' => $payment->observaciones ?? null,
							'status' => $payment->estado ?? 1,
							'created_at' => $payment->fecha_alta ?? now(),
							'updated_at' => $payment->fecha_modificacion ?? now(),
						]
					);
					$imported++;
				} catch (\Exception $e) {
					$skipped++;
					if ($skipped <= 10) {
						$this->command->warn("     Skipped payment {$payment->id}: " . $e->getMessage());
					}
				}
			}

			if ($skipped > 0) {
				$this->command->warn("   ⚠️  Skipped {$skipped} payments due to errors");
			}

			$this->command->info("✅ Imported {$imported} payments");
		} catch (\Exception $e) {
			$this->command->warn('⚠️  Could not import payments: ' . $e->getMessage());
		}
	}
}
