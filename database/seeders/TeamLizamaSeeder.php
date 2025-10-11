<?php

namespace Database\Seeders;

use Idoneo\HumanoAcademy\Models\Chapter;
use Idoneo\HumanoAcademy\Models\Course;
use Idoneo\HumanoAcademy\Models\Lesson;
use Illuminate\Database\Seeder;

class TeamLizamaSeeder extends Seeder
{
	/**
	 * Run the database seeds - Curso de Derecho Laboral para Team Lizama
	 */
	public function run(): void
	{
		// Configuración del curso
		$teamId = 6;  // Cambiar según el team_id que necesites
		$videosBasePath = '/Volumes/Extreme/Clientes/Lizama/';  // Ruta donde están los videos

		// Crear role student si no existe
		$this->createStudentRole();

		// Gestionar usuario Diego y agregarlo al team
		$this->setupTeamAdmin($teamId);

		// Agregar Cecilia como admin
		$this->addTeamAdmin($teamId, 'cecilia@revisionalpha.com', 'Cecilia');

		// Crear y agregar Ximena como admin con password específico
		$this->addTeamAdmin($teamId, 'ximena.catalan@lizamaycia.cl', 'Ximena Catalán', 'Passw0rd!');

		// Activar módulo academy
		$this->activateAcademyModule();

		// Asignar módulo academy al team
		$this->assignAcademyToTeam($teamId);

		// Asignar todos los módulos necesarios al team
		$this->assignAllModulesToTeam($teamId);

		// Configurar permisos de Academy
		$this->setupAcademyPermissions();

		// Crear categorías de cursos
		$categoryId = $this->createAcademyCategories($teamId);

		$this->command->info('🎓 Creando Curso de Derecho Laboral...');

		// Crear el curso principal
		$course = Course::create([
			'team_id' => $teamId,
			'title' => 'Derecho Laboral - Curso Completo',
			'description' => 'Curso completo de Derecho Laboral con 11 módulos especializados',
			'long_description' => 'Curso intensivo que cubre todos los aspectos fundamentales del Derecho Laboral en Chile, desde el contrato de trabajo hasta la negociación colectiva, incluyendo las leyes más recientes como Ley Karin, Ley 40 horas y Ley de Conciliación.',
			'instructor_name' => 'Equipo Docente',
			'instructor_title' => 'Especialistas en Derecho Laboral',
			'category_id' => $categoryId,
			'skill_level' => 'Intermediate',
			'students_count' => 0,
			'language' => 'es',
			'has_captions' => false,
			'thumbnail' => null,
			'status' => 'published',
			'order' => 1,
		]);

		// ============================================
		// CAPÍTULO 1: Fundamentos del Contrato Laboral
		// ============================================
		$chapter1 = Chapter::create([
			'course_id' => $course->id,
			'title' => 'Fundamentos del Contrato Laboral',
			'description' => 'Conceptos básicos y modalidades del contrato de trabajo',
			'order' => 1,
		]);

		$this->createLesson($chapter1->id, [
			'title' => 'Módulo 1: Contrato de Trabajo',
			'description' => 'Introducción al contrato de trabajo y sus elementos esenciales',
			'video_filename' => 'modulo_01_contrato_de_trabajo.mp4',
			'duration_minutes' => 200,
			'order' => 1,
		], $videosBasePath, $teamId);

		// ============================================
		// CAPÍTULO 2: Modalidades de Trabajo
		// ============================================
		$chapter2 = Chapter::create([
			'course_id' => $course->id,
			'title' => 'Modalidades de Trabajo',
			'description' => 'Teletrabajo y trabajo a distancia',
			'order' => 2,
		]);

		$this->createLesson($chapter2->id, [
			'title' => 'Módulo 2: Teletrabajo y Trabajo a Distancia',
			'description' => 'Normativas y regulaciones del teletrabajo',
			'video_filename' => 'modulo_02_teletrabajo_y_trabajo_a_distancia.mp4',
			'duration_minutes' => 190,
			'order' => 1,
		], $videosBasePath, $teamId);

		// ============================================
		// CAPÍTULO 3: Derechos Fundamentales
		// ============================================
		$chapter3 = Chapter::create([
			'course_id' => $course->id,
			'title' => 'Derechos Fundamentales',
			'description' => 'Derechos fundamentales al interior de la empresa',
			'order' => 3,
		]);

		$this->createLesson($chapter3->id, [
			'title' => 'Módulo 3: Derechos Fundamentales al Interior de la Empresa',
			'description' => 'Protección de derechos en el ámbito laboral',
			'video_filename' => 'modulo_03_derechos_fundamentales_al_interior_de_la_empresa.mp4',
			'duration_minutes' => 200,
			'order' => 1,
		], $videosBasePath, $teamId);

		// ============================================
		// CAPÍTULO 4: Terminación del Contrato
		// ============================================
		$chapter4 = Chapter::create([
			'course_id' => $course->id,
			'title' => 'Terminación del Contrato',
			'description' => 'Causales y procedimientos de terminación laboral',
			'order' => 4,
		]);

		$this->createLesson($chapter4->id, [
			'title' => 'Módulo 4: Terminación del Contrato de Trabajo',
			'description' => 'Análisis de las distintas causales de término del contrato',
			'video_filename' => 'modulo_04_terminacion_del_contrato_de_trabajo.mp4',
			'duration_minutes' => 220,
			'order' => 1,
		], $videosBasePath, $teamId);

		// ============================================
		// CAPÍTULO 5: Finiquitos
		// ============================================
		$chapter5 = Chapter::create([
			'course_id' => $course->id,
			'title' => 'Finiquitos',
			'description' => 'Procedimiento y cálculo de finiquitos',
			'order' => 5,
		]);

		$this->createLesson($chapter5->id, [
			'title' => 'Módulo 5: Finiquitos',
			'description' => 'Elaboración y firma de finiquitos laborales',
			'video_filename' => 'modulo_05_finiquitos.mp4',
			'duration_minutes' => 190,
			'order' => 1,
		], $videosBasePath, $teamId);

		// ============================================
		// CAPÍTULO 6: Ley Karin
		// ============================================
		$chapter6 = Chapter::create([
			'course_id' => $course->id,
			'title' => 'Ley Karin',
			'description' => 'Protocolo de prevención del acoso laboral',
			'order' => 6,
		]);

		$this->createLesson($chapter6->id, [
			'title' => 'Módulo 6: Ley Karin',
			'description' => 'Implementación y cumplimiento de la Ley Karin',
			'video_filename' => 'modulo_06_ley_karin.mp4',
			'duration_minutes' => 180,
			'order' => 1,
		], $videosBasePath, $teamId);

		// ============================================
		// CAPÍTULO 7: Ley 40 Horas
		// ============================================
		$chapter7 = Chapter::create([
			'course_id' => $course->id,
			'title' => 'Ley 40 Horas',
			'description' => 'Reducción de jornada laboral',
			'order' => 7,
		]);

		$this->createLesson($chapter7->id, [
			'title' => 'Módulo 7: Ley 40 Horas',
			'description' => 'Implementación de la jornada de 40 horas',
			'video_filename' => 'modulo_07_ley_40_horas.mp4',
			'duration_minutes' => 185,
			'order' => 1,
		], $videosBasePath, $teamId);

		// ============================================
		// CAPÍTULO 8: Ley de Conciliación
		// ============================================
		$chapter8 = Chapter::create([
			'course_id' => $course->id,
			'title' => 'Ley de Conciliación',
			'description' => 'Balance trabajo-familia',
			'order' => 8,
		]);

		$this->createLesson($chapter8->id, [
			'title' => 'Módulo 8: Ley de Conciliación',
			'description' => 'Derechos de conciliación trabajo y vida familiar',
			'video_filename' => 'modulo_08_ley_de_conciliacion.mp4',
			'duration_minutes' => 175,
			'order' => 1,
		], $videosBasePath, $teamId);

		$this->command->info('✅ Curso de Derecho Laboral creado exitosamente!');
		$this->command->info('📊 Estadísticas:');
		$this->command->info('   - Total de capítulos: 8');
		$this->command->info('   - Total de módulos: 11');
		$this->command->info('   - Duración total estimada: ~20 horas');
		$this->command->info("   - Curso ID: {$course->id}");
	}

	/**
	 * Helper para crear lecciones con videos
	 * Nota: Los videos deben copiarse manualmente a storage/app/public/academy/{team_hash}/videos/
	 */
	private function createLesson(int $chapterId, array $data, string $videosBasePath, int $teamId): Lesson
	{
		$lesson = Lesson::create([
			'chapter_id' => $chapterId,
			'title' => $data['title'],
			'description' => $data['description'] ?? null,
			'video_path' => $data['video_filename'],  // Solo guardar el nombre del archivo
			'duration_minutes' => $data['duration_minutes'],
			'order' => $data['order'],
			'status' => 'published',
		]);

		return $lesson;
	}

	/**
	 * Crear role student si no existe
	 */
	private function createStudentRole(): void
	{
		$studentRole = \Spatie\Permission\Models\Role::firstOrCreate(
			['name' => 'student'],
			['guard_name' => 'web'],
		);

		$this->command->info('✅ Role "student" verificado/creado');
	}

	/**
	 * Agregar usuario como admin al team (método genérico)
	 */
	private function addTeamAdmin(int $teamId, string $email, string $name, string $password = 'password'): void
	{
		// Buscar o crear el usuario
		$user = \App\Models\User::firstOrCreate(
			['email' => $email],
			[
				'name' => $name,
				'password' => bcrypt($password),
			],
		);

		// Si el usuario ya existía, actualizar el password
		if (!$user->wasRecentlyCreated) {
			$user->update(['password' => bcrypt($password)]);
		}

		// Asignar role global de admin (Spatie)
		if (!$user->hasRole('admin')) {
			$user->assignRole('admin');
			$this->command->info("   → Role global 'admin' asignado a {$email}");
		}

		$team = \App\Models\Team::find($teamId);

		if ($team) {
			// Verificar si el usuario ya pertenece al team
			$membership = \DB::table('team_user')
				->where('team_id', $teamId)
				->where('user_id', $user->id)
				->first();

			if (!$membership) {
				// Agregar el usuario al team con rol de admin
				$team->users()->attach($user, ['role' => 'admin']);
				$this->command->info("✅ Usuario {$email} agregado al team como administrador");
			} else {
				// Actualizar el rol a admin si ya pertenece al team
				\DB::table('team_user')
					->where('team_id', $teamId)
					->where('user_id', $user->id)
					->update(['role' => 'admin']);
				$this->command->info("✅ Usuario {$email} ya pertenecía al team, rol actualizado a administrador");
			}

			// Asignar este team como current_team_id si no tiene uno
			if (!$user->current_team_id) {
				$user->update(['current_team_id' => $teamId]);
				$this->command->info("   → current_team_id asignado: {$teamId}");
			}
		}
	}

	/**
	 * Configurar usuario Diego como administrador del team
	 */
	private function setupTeamAdmin(int $teamId): void
	{
		$email = 'diego.mascarenhas@icloud.com';

		// Buscar o crear el usuario
		$user = \App\Models\User::firstOrCreate(
			['email' => $email],
			[
				'name' => 'Diego Mascarenhas',
				'password' => bcrypt('password'),  // Contraseña por defecto si se crea
			],
		);

		// Asignar role global de admin (Spatie)
		if (!$user->hasRole('admin')) {
			$user->assignRole('admin');
			$this->command->info("   → Role global 'admin' asignado a {$email}");
		}

		// Obtener o crear el team
		$team = \App\Models\Team::find($teamId);

		if (!$team) {
			// Crear el team si no existe
			\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
			$team = new \App\Models\Team;
			$team->id = $teamId;
			$team->user_id = $user->id;
			$team->name = 'Lizama';
			$team->personal_team = 0;
			$team->save();
			\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
			$this->command->info("✅ Team 'Lizama' creado con ID {$teamId}");
		}

		// Verificar si el usuario ya pertenece al team
		$membership = \DB::table('team_user')
			->where('team_id', $teamId)
			->where('user_id', $user->id)
			->first();

		if (!$membership) {
			// Agregar el usuario al team con rol de admin
			$team->users()->attach($user, ['role' => 'admin']);
			$this->command->info("✅ Usuario {$email} agregado al team como administrador");
		} else {
			// Actualizar el rol a admin si ya pertenece al team
			\DB::table('team_user')
				->where('team_id', $teamId)
				->where('user_id', $user->id)
				->update(['role' => 'admin']);
			$this->command->info("✅ Usuario {$email} ya pertenecía al team, rol actualizado a administrador");
		}

		// Asignar este team como current_team_id si no tiene uno
		if (!$user->current_team_id) {
			$user->update(['current_team_id' => $teamId]);
			$this->command->info("   → current_team_id asignado: {$teamId}");
		}
	}

	/**
	 * Activar el módulo Academy en el sistema
	 */
	private function activateAcademyModule(): void
	{
		$module = \App\Models\Module::where('key', 'academy')->first();

		if ($module) {
			$module->update(['status' => 1]);
			$this->command->info('✅ Módulo Academy activado (status=1)');
		} else {
			$this->command->warn('⚠️  Módulo Academy no encontrado');
		}
	}

	/**
	 * Asignar módulo Academy al team
	 */
	private function assignAcademyToTeam(int $teamId): void
	{
		$module = \App\Models\Module::where('key', 'academy')->first();
		$team = \App\Models\Team::find($teamId);

		if ($module && $team) {
			// Verificar si ya está asignado
			$exists = \DB::table('module_team')
				->where('module_id', $module->id)
				->where('team_id', $teamId)
				->exists();

			if (!$exists) {
				\DB::table('module_team')->insert([
					'module_id' => $module->id,
					'team_id' => $teamId,
					'created_at' => now(),
					'updated_at' => now(),
				]);
				$this->command->info('✅ Módulo Academy asignado al team Lizama');
			} else {
				$this->command->info('ℹ️  Módulo Academy ya estaba asignado al team');
			}
		}
	}

	/**
	 * Asignar todos los módulos necesarios al team
	 */
	private function assignAllModulesToTeam(int $teamId): void
	{
		$team = \App\Models\Team::find($teamId);

		if (!$team) {
			$this->command->warn('⚠️  Team no encontrado');

			return;
		}

		// Módulos que queremos asignar al team Lizama
		$moduleKeys = [
			'dashboard',
			'users',
			'contacts',
			'clients',
			'services',
			'projects',
			'tasks',
			'notifications',
			'invoices',  // Facturas
			'payments',  // Pagos
			'academy',  // Academy
			'notes',
			'enterprises',
			'times',
			'attendances',
		];

		$assignedCount = 0;
		$alreadyAssignedCount = 0;

		foreach ($moduleKeys as $key) {
			$module = \App\Models\Module::where('key', $key)->first();

			if ($module) {
				// Verificar si ya está asignado
				$exists = \DB::table('module_team')
					->where('module_id', $module->id)
					->where('team_id', $teamId)
					->exists();

				if (!$exists) {
					\DB::table('module_team')->insert([
						'module_id' => $module->id,
						'team_id' => $teamId,
						'created_at' => now(),
						'updated_at' => now(),
					]);
					$assignedCount++;
				} else {
					$alreadyAssignedCount++;
				}
			}
		}

		$this->command->info("✅ Módulos asignados al team: {$assignedCount} nuevos, {$alreadyAssignedCount} ya existían");
	}

	/**
	 * Configurar permisos de Academy para admin y student
	 */
	private function setupAcademyPermissions(): void
	{
		// Permisos de Academy
		$permissions = [
			'academy.list',
			'academy.show',
			'academy.course.details',
		];

		// Crear permisos si no existen
		foreach ($permissions as $permissionName) {
			\Spatie\Permission\Models\Permission::firstOrCreate(
				['name' => $permissionName],
				['guard_name' => 'web'],
			);
		}

		$this->command->info('✅ Permisos de Academy verificados/creados');

		// Asignar permisos al role admin
		$adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
		if ($adminRole) {
			$adminRole->syncPermissions(array_merge(
				$adminRole->permissions->pluck('name')->toArray(),
				$permissions,
			));
			$this->command->info('✅ Permisos de Academy asignados a role "admin"');
		}

		// Asignar permisos al role student
		$studentRole = \Spatie\Permission\Models\Role::where('name', 'student')->first();
		if ($studentRole) {
			$studentRole->syncPermissions(array_merge(
				$studentRole->permissions->pluck('name')->toArray(),
				$permissions,
			));
			$this->command->info('✅ Permisos de Academy asignados a role "student"');
		}
	}

	/**
	 * Crear categorías para los cursos de Academy
	 */
	private function createAcademyCategories(int $teamId): int
	{
		// Obtener el module_id de Academy
		$academyModule = \App\Models\Module::where('key', 'academy')->first();

		if (!$academyModule) {
			$this->command->error('❌ No se encontró el módulo Academy');
			throw new \Exception('Module Academy not found');
		}

		// Crear categoría principal "Derecho Laboral"
		$category = \App\Models\Category::firstOrCreate(
			[
				'name' => 'Derecho Laboral',
				'module_id' => $academyModule->id,
				'team_id' => $teamId,
			],
			[
				'description' => 'Cursos especializados en Derecho Laboral',
				'status' => 1,
				'order' => 1,
			],
		);

		$this->command->info("✅ Categoría 'Derecho Laboral' creada (ID: {$category->id})");

		// Crear subcategorías si es necesario
		$subcategories = [
			'Legislación Laboral' => 'Leyes y normativas laborales',
			'Contratos de Trabajo' => 'Modalidades y tipos de contratos',
			'Relaciones Laborales' => 'Gestión de las relaciones laborales',
		];

		foreach ($subcategories as $name => $description) {
			$subcat = \App\Models\Category::firstOrCreate(
				[
					'name' => $name,
					'module_id' => $academyModule->id,
					'team_id' => $teamId,
					'parent_id' => $category->id,
				],
				[
					'description' => $description,
					'status' => 1,
					'order' => 0,
				],
			);

			$this->command->info("   ✅ Subcategoría '{$name}' creada (ID: {$subcat->id})");
		}

		return $category->id;
	}
}
