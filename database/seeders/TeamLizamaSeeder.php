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

        // Manage user Diego and add them to the team
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

        // Importar contactos y crear usuarios
        $this->importContactsAndUsers($teamId);

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
     * Importar contactos y crear usuarios vinculados
     */
    private function importContactsAndUsers(int $teamId): void
    {
        $this->command->info('📋 Importando contactos y creando usuarios...');

        // Buscar el modelo Contact
        $contactModel = \App\Models\Contact::class;
        if (! class_exists($contactModel))
        {
            $this->command->error('❌ Modelo Contact no encontrado');

            return;
        }

        // Obtener admin del team para usar como creator_id
        $teamAdmin = \App\Models\User::whereHas('teams', function ($query) use ($teamId)
        {
            $query->where('teams.id', $teamId);
        })->first();

        if (! $teamAdmin)
        {
            $this->command->error('❌ No se encontró un admin para el team');

            return;
        }

        $contacts = $this->getContactsData();
        $adminCount = 0;
        $studentCount = 0;
        $contactsCount = 0;

        foreach ($contacts as $contactData)
        {
            try
            {
                // Limpiar y formatear datos
                $nombre = $this->toProperCase($contactData['nombre']);
                $apellido = $this->toProperCase($contactData['apellido']);
                $email = strtolower(trim($contactData['email']));
                $telefono = $this->cleanPhoneNumber($contactData['telefono']);
                $areaPrivada = $contactData['area_privada'];

                // Nombre completo para el campo name
                $nombreCompleto = trim($nombre.' '.$apellido);
                if (empty($nombreCompleto))
                {
                    $nombreCompleto = explode('@', $email)[0];  // Usar parte del email si no hay nombre
                }

                // Crear usuario primero si area_privada es 3 (admin) o 5 (student)
                $userId = null;
                if (in_array($areaPrivada, ['3', '5']))
                {
                    $role = $areaPrivada === '3' ? 'admin' : 'student';
                    $password = $areaPrivada === '3' ? 'Passw0rd!' : 'Lizama';

                    // Preparar datos del usuario
                    $userData = [
                        'name' => $nombreCompleto,
                        'password' => bcrypt($password),
                    ];

                    // Agregar teléfono si existe
                    if ($telefono)
                    {
                        $userData['phone'] = $telefono;
                    }

                    // Crear usuario
                    $user = \App\Models\User::firstOrCreate(
                        ['email' => $email],
                        $userData,
                    );

                    // Actualizar teléfono si el usuario ya existía y hay teléfono nuevo
                    if (! $user->wasRecentlyCreated && $telefono && ! $user->phone)
                    {
                        $user->update(['phone' => $telefono]);
                    }

                    // Asignar role
                    if (! $user->hasRole($role))
                    {
                        $user->assignRole($role);
                    }

                    // Vincular al team
                    $membership = \DB::table('team_user')
                        ->where('team_id', $teamId)
                        ->where('user_id', $user->id)
                        ->first();

                    if (! $membership)
                    {
                        \DB::table('team_user')->insert([
                            'team_id' => $teamId,
                            'user_id' => $user->id,
                            'role' => $role,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Asignar current_team_id si no tiene uno
                    if (! $user->current_team_id)
                    {
                        $user->update(['current_team_id' => $teamId]);
                    }

                    $userId = $user->id;

                    if ($role === 'admin')
                    {
                        $adminCount++;
                    } else
                    {
                        $studentCount++;
                    }
                }

                // Crear o actualizar contacto con la estructura correcta
                $contact = $contactModel::updateOrCreate(
                    ['email' => $email, 'team_id' => $teamId],
                    [
                        'name' => $nombreCompleto,
                        'surname' => $apellido ?: null,
                        'phone' => $telefono,
                        'country' => 152,  // Chile
                        'user_id' => $userId,
                        'creator_id' => $teamAdmin->id,
                        'status_id' => 1,
                    ],
                );

                $contactsCount++;
            } catch (\Exception $e)
            {
                $this->command->warn("⚠️  Error procesando contacto {$contactData['email']}: ".$e->getMessage());
            }
        }

        $this->command->info("✅ Contactos importados: {$contactsCount}");
        $this->command->info("✅ Usuarios admin creados: {$adminCount}");
        $this->command->info("✅ Usuarios student creados: {$studentCount}");
    }

    /**
     * Convertir texto a formato Proper Case (Primera letra mayúscula)
     */
    private function toProperCase(string $text): string
    {
        if (empty($text))
        {
            return '';
        }

        return mb_convert_case(trim($text), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Limpiar número de teléfono (remover símbolo +)
     */
    private function cleanPhoneNumber(?string $phone): ?string
    {
        if (empty($phone))
        {
            return null;
        }

        return str_replace('+', '', trim($phone));
    }

    /**
     * Obtener datos de contactos desde el SQL proporcionado
     */
    private function getContactsData(): array
    {
        return [
            ['nombre' => 'Luis', 'apellido' => 'Lizama', 'telefono' => '56222463080', 'email' => 'luis.lizama@lizamaabogados.cl', 'area_privada' => '3'],
            ['nombre' => 'Lucas', 'apellido' => 'Astroza', 'telefono' => '56994969564', 'email' => 'Lucas.Astroza@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Aileen', 'apellido' => 'Santelices', 'telefono' => '56994399147', 'email' => 'aileen.santelices@lizamabogados.cl', 'area_privada' => '3'],
            ['nombre' => 'rubenusabiaga@yahoo.com.a', 'apellido' => '', 'telefono' => null, 'email' => 'rubenusabiaga@yahoo.com.ar', 'area_privada' => '5'],
            ['nombre' => 'andy.arguindegui@gmail.co', 'apellido' => '', 'telefono' => '1135889448', 'email' => 'andy.arguindegui@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Belén', 'apellido' => 'Salazar', 'telefono' => '56956556678', 'email' => 'Belen.Salazar@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'torrescarrasco.r', 'apellido' => '', 'telefono' => '+56963455239', 'email' => 'torrescarrasco.r@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Pablo', 'apellido' => 'Barrozo', 'telefono' => null, 'email' => 'pablo@revisionalpha.com', 'area_privada' => '5'],
            ['nombre' => 'fmerino2018', 'apellido' => '', 'telefono' => '998086725', 'email' => 'fmerino2018@udec.cl', 'area_privada' => '5'],
            ['nombre' => 'Joaquín', 'apellido' => 'Estay', 'telefono' => '946147373', 'email' => 'Joaquin.Estay@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'juan.cuitino.l', 'apellido' => '', 'telefono' => '932458222', 'email' => 'juan.cuitino.l@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'felipe.pena', 'apellido' => '', 'telefono' => '964947781', 'email' => 'felipe.pena@taxseven.cl', 'area_privada' => '5'],
            ['nombre' => 'Valenzuela.km', 'apellido' => '', 'telefono' => '+56959144995', 'email' => 'Valenzuela.km@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'alexiscorrea.a', 'apellido' => '', 'telefono' => '985798108', 'email' => 'alexiscorrea.a@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'estudiosolis', 'apellido' => '', 'telefono' => '998729266', 'email' => 'estudiosolis@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Lucas', 'apellido' => 'Astroza', 'telefono' => '985008293', 'email' => 'Lucas.Astroza@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Juan Pablo', 'apellido' => 'Muñoz', 'telefono' => '+56998632044', 'email' => 'jpmunozp2@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'felipealtamiranog', 'apellido' => '', 'telefono' => '88614517', 'email' => 'felipealtamiranog@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'derechodeltrabajadorchile', 'apellido' => '', 'telefono' => '+56948171619', 'email' => 'derechodeltrabajadorchile@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'faayudalegal', 'apellido' => '', 'telefono' => '964901811', 'email' => 'faayudalegal@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Administracion Academia', 'apellido' => 'Lizama', 'telefono' => null, 'email' => 'administracion@academializama.cl', 'area_privada' => '3'],
            ['nombre' => 'sofijiu98', 'apellido' => '', 'telefono' => '981834674', 'email' => 'sofijiu98@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'fgalaz', 'apellido' => '', 'telefono' => '962968580', 'email' => 'fgalaz@myma.cl', 'area_privada' => '5'],
            ['nombre' => 'paola.barrantes12', 'apellido' => '', 'telefono' => '+56964682832', 'email' => 'paola.barrantes12@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'roxana.berrios', 'apellido' => '', 'telefono' => '56942858114', 'email' => 'roxana.berrios@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'juanantonionunezrojas', 'apellido' => '', 'telefono' => '988005696', 'email' => 'juanantonionunezrojas@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'tchiblev', 'apellido' => '', 'telefono' => '945328805', 'email' => 'tchiblev@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'aorellana', 'apellido' => '', 'telefono' => '961923365', 'email' => 'aorellana@betterfood.cl', 'area_privada' => '5'],
            ['nombre' => 'rvega', 'apellido' => '', 'telefono' => '948086138', 'email' => 'rvega@contadorgeneral.cl', 'area_privada' => '5'],
            ['nombre' => 'monserrat.tellez', 'apellido' => '', 'telefono' => '+56956271544', 'email' => 'monserrat.tellez@derecho.uchile.cl', 'area_privada' => '5'],
            ['nombre' => 'Ignacio', 'apellido' => 'Cartes', 'telefono' => '56961205305', 'email' => 'ignacio.cartes@lizamaabogados.cl', 'area_privada' => '3'],
            ['nombre' => 'Ignacio', 'apellido' => 'Cartes', 'telefono' => null, 'email' => 'ignacio.cartes@lizamabogados.com', 'area_privada' => '5'],
            ['nombre' => 'Victoria', 'apellido' => 'Suarez', 'telefono' => '962982782', 'email' => 'vic82008@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Javiera', 'apellido' => 'ALVAREZ VERA', 'telefono' => '+56991544192', 'email' => 'javiera.alvarez@lizamaycia.cl', 'area_privada' => '5'],
            ['nombre' => 'Ximena', 'apellido' => 'Catalán', 'telefono' => '56990911489', 'email' => 'ximena.catalan@lizamaycia.cl', 'area_privada' => '3'],
            ['nombre' => 'Diego', 'apellido' => 'Rojas', 'telefono' => '56999969244', 'email' => 'drojas@imed.cl', 'area_privada' => '5'],
            ['nombre' => 'Alejandra', 'apellido' => 'Jugo', 'telefono' => '+56961213399', 'email' => 'ajugo@proa.cl', 'area_privada' => '5'],
            ['nombre' => 'Víctor', 'apellido' => 'Velásquez Nieto', 'telefono' => '968755862', 'email' => 'victor.velasquez.n@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Jim', 'apellido' => 'Azola', 'telefono' => '982315073', 'email' => 'jazolac@udd.cl', 'area_privada' => '5'],
            ['nombre' => 'Jorge', 'apellido' => 'Consales', 'telefono' => '977066482', 'email' => 'Jorgeconsales@gnail.com', 'area_privada' => '5'],
            ['nombre' => 'Edwin', 'apellido' => 'Ugarte Romero', 'telefono' => '963031280', 'email' => 'edwin.ugarte@cbb.cl', 'area_privada' => '5'],
            ['nombre' => 'Jose Miguel', 'apellido' => 'Crespo Vergara', 'telefono' => '226378173', 'email' => 'jcrespo@ariztia.com', 'area_privada' => '5'],
            ['nombre' => 'Jose luis', 'apellido' => 'Cartes Saez', 'telefono' => '995386132', 'email' => 'Jlcartes.saez@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Eduardo', 'apellido' => 'Carrasco', 'telefono' => '+56988288929', 'email' => 'ecarrasco@csh.cl', 'area_privada' => '5'],
            ['nombre' => 'Christina', 'apellido' => 'Silva', 'telefono' => null, 'email' => 'christina.silva@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Evelyn', 'apellido' => 'Peña', 'telefono' => null, 'email' => 'evelyn.pena@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Alejandro', 'apellido' => 'Tuesta', 'telefono' => '962284455', 'email' => 'alejandro.tuesta@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Juan Luis', 'apellido' => 'Rebolledo', 'telefono' => null, 'email' => 'juan.rebolledo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Carlos', 'apellido' => 'Blanco', 'telefono' => null, 'email' => 'carlos.blanco@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Rene', 'apellido' => 'Lopez', 'telefono' => null, 'email' => 'rene.lopez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Elena', 'apellido' => 'Caceres', 'telefono' => '996793274', 'email' => 'carola.caceres@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jorge', 'apellido' => 'Cuevas', 'telefono' => null, 'email' => 'jorge.cuevas@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Andy', 'apellido' => 'Rubench', 'telefono' => null, 'email' => 'andy.allendes@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jose Luis', 'apellido' => 'Jimenez', 'telefono' => null, 'email' => 'joseluis.jimenez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'María José', 'apellido' => 'Vega', 'telefono' => null, 'email' => 'mariajose.vega@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Klaus', 'apellido' => 'Wennrich', 'telefono' => null, 'email' => 'klaus.wennrich@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Priscila', 'apellido' => 'Cerda', 'telefono' => null, 'email' => 'priscila.cerda@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Denisse', 'apellido' => 'Espinoza', 'telefono' => null, 'email' => 'denisse.espinoza@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jacqueline', 'apellido' => 'Castillo', 'telefono' => null, 'email' => 'jacqueline.castillo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Daniel', 'apellido' => 'Campusano', 'telefono' => null, 'email' => 'daniel.campusano@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jeannette', 'apellido' => 'Guajardo', 'telefono' => null, 'email' => 'jeannette.guajardo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Margarita', 'apellido' => 'Rivas', 'telefono' => null, 'email' => 'margarita.rivas@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Camila', 'apellido' => 'Martínez', 'telefono' => null, 'email' => 'camila.martinez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Francisca', 'apellido' => 'Ovalle', 'telefono' => null, 'email' => 'francisca.ovalle@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Matías', 'apellido' => 'Vial', 'telefono' => null, 'email' => 'matias.vial@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Luis', 'apellido' => 'Prado', 'telefono' => null, 'email' => 'luis.Prado@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Valeria', 'apellido' => 'Tapia', 'telefono' => null, 'email' => 'valeria.tapia@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Diego', 'apellido' => 'Accevedo', 'telefono' => null, 'email' => 'diego.acevedo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Barbara', 'apellido' => 'González', 'telefono' => null, 'email' => 'barbara.gonzalez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Joaquín', 'apellido' => 'Estay', 'telefono' => null, 'email' => 'Joaquin.Estay@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Luis', 'apellido' => 'Gutierrez', 'telefono' => null, 'email' => 'luis.gutierrez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Macarena', 'apellido' => 'Muñoz', 'telefono' => null, 'email' => 'macarena.munoz@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Constanza', 'apellido' => 'Castro', 'telefono' => null, 'email' => 'constanza.castro@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Damian', 'apellido' => 'Gutierrez', 'telefono' => null, 'email' => 'damian.gutierrez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jonathan', 'apellido' => 'Castillo', 'telefono' => null, 'email' => 'jonathan.castillo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Valentina', 'apellido' => 'Marin', 'telefono' => null, 'email' => 'valentina.marin@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Benjamin', 'apellido' => 'Llambías', 'telefono' => null, 'email' => 'benjamin.llambias@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Francisca', 'apellido' => 'Mezzano', 'telefono' => null, 'email' => 'francisca.mezzano@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Francisca', 'apellido' => 'Palaneck', 'telefono' => null, 'email' => 'francisca.palaneck@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Mariela', 'apellido' => 'Castro', 'telefono' => '989228170', 'email' => 'mariela.castro@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Fabiana', 'apellido' => 'Gomez', 'telefono' => null, 'email' => 'fabiana.gomez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Nicole', 'apellido' => 'Indo', 'telefono' => null, 'email' => 'nicole.indo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Maria Eugenia', 'apellido' => 'Salvatierra', 'telefono' => null, 'email' => 'maria.salvatierra@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jorge', 'apellido' => 'Carvajal', 'telefono' => null, 'email' => 'jorge.carvajal@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Guiliano', 'apellido' => 'Villablanca', 'telefono' => null, 'email' => 'gulianno.villablanca@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Rodrigo', 'apellido' => 'Galleguillos', 'telefono' => null, 'email' => 'rodrigo.galleguillos@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Diego', 'apellido' => 'Correa', 'telefono' => null, 'email' => 'diego.correa@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jairo', 'apellido' => 'Quintero', 'telefono' => null, 'email' => 'jairo.quintero@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Antonia', 'apellido' => 'Hidalgo', 'telefono' => null, 'email' => 'antonia.hidalgo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Javiera', 'apellido' => 'Vargas', 'telefono' => null, 'email' => 'javiera.vargas@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Maria', 'apellido' => 'Salazar', 'telefono' => null, 'email' => 'maria.salazar@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Daniela', 'apellido' => 'Urtubia', 'telefono' => null, 'email' => 'daniela.urtubia@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Daniela', 'apellido' => 'Reinoso', 'telefono' => null, 'email' => 'daniela.reinoso@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Martín', 'apellido' => 'Pelayo', 'telefono' => null, 'email' => 'martin.pelayo@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Nataly', 'apellido' => 'De La Hoz', 'telefono' => '56976182741', 'email' => 'natalydelahozmoraga@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Martín', 'apellido' => 'Pelayo', 'telefono' => null, 'email' => 'martin.pelayo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Klaus', 'apellido' => 'Wennrich', 'telefono' => null, 'email' => 'klaus.wennrich@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Admin', 'apellido' => '', 'telefono' => null, 'email' => 'administracion@academializama.cl', 'area_privada' => '5'],
            ['nombre' => 'Agustín', 'apellido' => 'Marletta', 'telefono' => null, 'email' => 'agustin.marletta@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Natalia Andrea', 'apellido' => 'Escobar Arce', 'telefono' => '976109862', 'email' => 'natalia.escobar@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Andrés Felipe', 'apellido' => 'Escobar Rioseco', 'telefono' => '+56998705723', 'email' => 'aescobarr@udd.cl', 'area_privada' => '5'],
            ['nombre' => 'César', 'apellido' => 'Pizarro', 'telefono' => '934003801', 'email' => 'cpizarrov@atiport.cl', 'area_privada' => '5'],
            ['nombre' => 'Gabriel', 'apellido' => 'Lizama', 'telefono' => '976690489', 'email' => 'gabriel.lizama@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'Luckas', 'apellido' => 'Garrido', 'telefono' => '+56983734369', 'email' => 'logarrido@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'Damian', 'apellido' => 'Gutiérrez', 'telefono' => '+56945353068', 'email' => 'damy1200.agp@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Esteban', 'apellido' => 'Uribe', 'telefono' => '997434308', 'email' => 'es.uribe@yahoo.cl', 'area_privada' => '5'],
            ['nombre' => 'Monica', 'apellido' => 'Vega', 'telefono' => '927761289', 'email' => 'monicavapp63@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Angel', 'apellido' => 'Rojas Gutierrez', 'telefono' => '+56952106209', 'email' => 'angel.rojas@derecho.uchile.cl', 'area_privada' => '5'],
            ['nombre' => 'Claudia', 'apellido' => 'Diaz', 'telefono' => '956284952', 'email' => 'cdiaz@elsauce.CL', 'area_privada' => '5'],
            ['nombre' => 'Agustin', 'apellido' => 'Marletta', 'telefono' => null, 'email' => 'agustin.marletta@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'SILVIA ANDREA', 'apellido' => 'HORMAZABAL ARAYA', 'telefono' => '991932483', 'email' => 'shormazabala@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Diego', 'apellido' => 'Díaz', 'telefono' => '922222118', 'email' => 'diegodiazah@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'carolina estrella', 'apellido' => 'villa Astudillo', 'telefono' => '9992976698', 'email' => 'carolinaestrellaastudillo@gmail.com', 'area_privada' => '5'],
            // Contactos adicionales (121 más)
            ['nombre' => 'carolvines', 'apellido' => '', 'telefono' => '88777654', 'email' => 'carolvines@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'paloma.sepulveda', 'apellido' => '', 'telefono' => '+56984487175', 'email' => 'paloma.sepulveda@derecho.uchile.cl', 'area_privada' => '5'],
            ['nombre' => 'Neljorquera', 'apellido' => '', 'telefono' => '964074858', 'email' => 'Neljorquera@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'carola.dupre', 'apellido' => '', 'telefono' => '998729189', 'email' => 'carola.dupre@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'edison.derecho', 'apellido' => '', 'telefono' => '9457366521', 'email' => 'edison.derecho@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'p.olivares', 'apellido' => '', 'telefono' => '966182490', 'email' => 'p.olivares@ceya-chile.cl', 'area_privada' => '5'],
            ['nombre' => 'ni.calderon', 'apellido' => '', 'telefono' => '995796712', 'email' => 'ni.calderon@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'ldaza63', 'apellido' => '', 'telefono' => '944088415', 'email' => 'ldaza63@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'psf', 'apellido' => '', 'telefono' => '962463816', 'email' => 'psf@nachipa.com', 'area_privada' => '5'],
            ['nombre' => 'aboglma', 'apellido' => '', 'telefono' => '971270312', 'email' => 'aboglma@outlook.com', 'area_privada' => '5'],
            ['nombre' => 'mjpalma', 'apellido' => '', 'telefono' => '9775732171', 'email' => 'mjpalma@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'asotojarpa', 'apellido' => '', 'telefono' => '941401262', 'email' => 'asotojarpa@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'daguirrep', 'apellido' => '', 'telefono' => '+56987164141', 'email' => 'daguirrep@nflegal.info', 'area_privada' => '5'],
            ['nombre' => 'manuelrazeto', 'apellido' => '', 'telefono' => '978495537', 'email' => 'manuelrazeto@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'camigonzalez968', 'apellido' => '', 'telefono' => '977624477', 'email' => 'camigonzalez968@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'hbaron.abogada', 'apellido' => '', 'telefono' => '940839049', 'email' => 'hbaron.abogada@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'lesliereyesalazar', 'apellido' => '', 'telefono' => '+56972232695', 'email' => 'lesliereyesalazar@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'josefina_morenotudela', 'apellido' => '', 'telefono' => '952297526', 'email' => 'josefina_morenotudela@hotmail.com', 'area_privada' => '5'],
            ['nombre' => 'areyuna.abogada', 'apellido' => '', 'telefono' => '9932343018', 'email' => 'areyuna.abogada@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'hilda', 'apellido' => '', 'telefono' => '940839049', 'email' => 'hilda@ahainclusion.com', 'area_privada' => '5'],
            ['nombre' => 'ejgillmo', 'apellido' => '', 'telefono' => '978613448', 'email' => 'ejgillmo@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'macarenagonzalezf', 'apellido' => '', 'telefono' => '992754457', 'email' => 'macarenagonzalezf@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'marcos.palaciosp', 'apellido' => '', 'telefono' => '964324053', 'email' => 'marcos.palaciosp@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'samuel', 'apellido' => '', 'telefono' => '959183905', 'email' => 'samuel@ametatron.cl', 'area_privada' => '5'],
            ['nombre' => 'tomasvruizr', 'apellido' => '', 'telefono' => '+56931035395', 'email' => 'tomasvruizr@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'martinoficial2121', 'apellido' => '', 'telefono' => '984732235', 'email' => 'martinoficial2121@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'krishna.leiva', 'apellido' => '', 'telefono' => '940225182', 'email' => 'krishna.leiva@derecho.uchile.cl', 'area_privada' => '5'],
            ['nombre' => 'l.grand.ormeno', 'apellido' => '', 'telefono' => '+56994157773', 'email' => 'l.grand.ormeno@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'camila.aguila', 'apellido' => '', 'telefono' => '+56989335120', 'email' => 'camila.aguila@ug.uchile.cl', 'area_privada' => '5'],
            ['nombre' => 'consuelo.araneda', 'apellido' => '', 'telefono' => '964360499', 'email' => 'consuelo.araneda@derecho.uchile.cl', 'area_privada' => '5'],
            ['nombre' => 'vicente.bustos8', 'apellido' => '', 'telefono' => '963550356', 'email' => 'vicente.bustos8@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'sergiozapata.rojas', 'apellido' => '', 'telefono' => '+56976760896', 'email' => 'sergiozapata.rojas@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'ppibanezg', 'apellido' => '', 'telefono' => '944112538', 'email' => 'ppibanezg@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'nicolas.delcampo', 'apellido' => '', 'telefono' => '987223772', 'email' => 'nicolas.delcampo@derecho.uchile.cl', 'area_privada' => '5'],
            ['nombre' => 'mariaisabel.arroyo', 'apellido' => '', 'telefono' => '56935708099', 'email' => 'mariaisabel.arroyo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'carlostellohernandez94', 'apellido' => '', 'telefono' => '964012511', 'email' => 'carlostellohernandez94@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Lmonasterioc', 'apellido' => '', 'telefono' => '56998799435', 'email' => 'Lmonasterioc@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'claudia.kuhn', 'apellido' => '', 'telefono' => '952070200', 'email' => 'claudia.kuhn@prodigio.tech', 'area_privada' => '5'],
            ['nombre' => 'minna.campusano', 'apellido' => '', 'telefono' => '939680542', 'email' => 'minna.campusano@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'juanguzman.abogado', 'apellido' => '', 'telefono' => '99224359', 'email' => 'juanguzman.abogado@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'maximasotomayorr', 'apellido' => '', 'telefono' => '+56951589765', 'email' => 'maximasotomayorr@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'A.cuevasv', 'apellido' => '', 'telefono' => '94294877', 'email' => 'A.cuevasv@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'm.arr', 'apellido' => '', 'telefono' => '993555555', 'email' => 'm.arr@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'mtbarraza', 'apellido' => '', 'telefono' => '+56956447702', 'email' => 'mtbarraza@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'paulmcnabmurillo', 'apellido' => '', 'telefono' => '977066428', 'email' => 'paulmcnabmurillo@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'gabizamoranop04', 'apellido' => '', 'telefono' => '996108833', 'email' => 'gabizamoranop04@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Bpolanco', 'apellido' => '', 'telefono' => '934157806', 'email' => 'Bpolanco@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'Rosario.rios.c', 'apellido' => '', 'telefono' => '+56957328584', 'email' => 'Rosario.rios.c@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'Cicarrascoc', 'apellido' => '', 'telefono' => '56957657193', 'email' => 'Cicarrascoc@uc.cl', 'area_privada' => '5'],
            ['nombre' => 'RIVASCARLOS830', 'apellido' => '', 'telefono' => '983394813', 'email' => 'RIVASCARLOS830@GMAIL.COM', 'area_privada' => '5'],
            ['nombre' => 'acaqueob', 'apellido' => '', 'telefono' => '974811232', 'email' => 'acaqueob@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'johana.gomez', 'apellido' => '', 'telefono' => '962554980', 'email' => 'johana.gomez@lizamabogados.cl', 'area_privada' => '5'],
            ['nombre' => 'cortezserrano98', 'apellido' => '', 'telefono' => '949703558', 'email' => 'cortezserrano98@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'diego.lizama', 'apellido' => '', 'telefono' => '989044696', 'email' => 'diego.lizama@lizamaycia.cl', 'area_privada' => '5'],
            ['nombre' => 'geramolina.91', 'apellido' => '', 'telefono' => '968338556', 'email' => 'geramolina.91@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'franciscapalaneck', 'apellido' => '', 'telefono' => '956582096', 'email' => 'franciscapalaneck@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'ly.yamsan', 'apellido' => '', 'telefono' => '+56963716420', 'email' => 'ly.yamsan@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'rossiermarion.c', 'apellido' => '', 'telefono' => '964356109', 'email' => 'rossiermarion.c@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'marcela.miranda.abogada', 'apellido' => '', 'telefono' => '977072905', 'email' => 'marcela.miranda.abogada@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'yazmin.aliste', 'apellido' => '', 'telefono' => '+56956031941', 'email' => 'yazmin.aliste@derecho.uchile.cl', 'area_privada' => '5'],
            ['nombre' => 'patriciabellom', 'apellido' => '', 'telefono' => '+56973771037', 'email' => 'patriciabellom@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'fortunyrodrigo', 'apellido' => '', 'telefono' => '976693949', 'email' => 'fortunyrodrigo@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'cristian.arostica', 'apellido' => '', 'telefono' => '953585655', 'email' => 'cristian.arostica@viggo.cl', 'area_privada' => '5'],
            ['nombre' => 'Javijavi1123', 'apellido' => '', 'telefono' => '982119536', 'email' => 'Javijavi1123@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'juan.rebolledo', 'apellido' => '', 'telefono' => '993179682', 'email' => 'juan.rebolledo@saamterminals.cl', 'area_privada' => '5'],
            ['nombre' => 'denise.lara.c', 'apellido' => '', 'telefono' => '998412922', 'email' => 'denise.lara.c@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'orlando.hidalgo', 'apellido' => '', 'telefono' => '993468932', 'email' => 'orlando.hidalgo@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Agustín', 'apellido' => 'Marletta', 'telefono' => null, 'email' => 'agustin.marletta@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Nataly', 'apellido' => 'De La Hoz', 'telefono' => '56976182741', 'email' => 'natalydelahozmoraga@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'Martín', 'apellido' => 'Pelayo', 'telefono' => null, 'email' => 'martin.pelayo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Klaus', 'apellido' => 'Wennrich', 'telefono' => null, 'email' => 'klaus.wennrich@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Admin', 'apellido' => '', 'telefono' => null, 'email' => 'administracion@academializama.cl', 'area_privada' => '5'],
            ['nombre' => 'herman.cea69', 'apellido' => '', 'telefono' => '978518992', 'email' => 'herman.cea69@gmail.com', 'area_privada' => '5'],
            ['nombre' => 'ANASTASIANEYY', 'apellido' => '', 'telefono' => '966070241', 'email' => 'ANASTASIANEYY@GMAIL.COM', 'area_privada' => '5'],
            ['nombre' => 'benjamin.perez.oyarzo', 'apellido' => '', 'telefono' => '932718285', 'email' => 'benjamin.perez.oyarzo@gmail.com', 'area_privada' => '5'],
            // Contactos SAAM Terminals y otros faltantes (45 más)
            ['nombre' => 'Belén', 'apellido' => 'Salazar', 'telefono' => null, 'email' => 'Belen.Salazar@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Mariela', 'apellido' => 'Castro', 'telefono' => '989228170', 'email' => 'mariela.castro@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Fabiana', 'apellido' => 'Gomez', 'telefono' => null, 'email' => 'fabiana.gomez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Nicole', 'apellido' => 'Indo', 'telefono' => null, 'email' => 'nicole.indo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Maria Eugenia', 'apellido' => 'Salvatierra', 'telefono' => null, 'email' => 'maria.salvatierra@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jorge', 'apellido' => 'Carvajal', 'telefono' => null, 'email' => 'jorge.carvajal@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Guiliano', 'apellido' => 'Villablanca', 'telefono' => null, 'email' => 'gulianno.villablanca@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Rodrigo', 'apellido' => 'Galleguillos', 'telefono' => null, 'email' => 'rodrigo.galleguillos@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Diego', 'apellido' => 'Correa', 'telefono' => null, 'email' => 'diego.correa@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jairo', 'apellido' => 'Quintero', 'telefono' => null, 'email' => 'jairo.quintero@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Antonia', 'apellido' => 'Hidalgo', 'telefono' => null, 'email' => 'antonia.hidalgo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Javiera', 'apellido' => 'Vargas', 'telefono' => null, 'email' => 'javiera.vargas@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Maria', 'apellido' => 'Salazar', 'telefono' => null, 'email' => 'maria.salazar@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Daniela', 'apellido' => 'Urtubia', 'telefono' => null, 'email' => 'daniela.urtubia@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Daniela', 'apellido' => 'Reinoso', 'telefono' => null, 'email' => 'daniela.reinoso@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Damian', 'apellido' => 'Gutierrez', 'telefono' => null, 'email' => 'damian.gutierrez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jonathan', 'apellido' => 'Castillo', 'telefono' => null, 'email' => 'jonathan.castillo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Valentina', 'apellido' => 'Marin', 'telefono' => null, 'email' => 'valentina.marin@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Benjamin', 'apellido' => 'Llambías', 'telefono' => null, 'email' => 'benjamin.llambias@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Francisca', 'apellido' => 'Mezzano', 'telefono' => null, 'email' => 'francisca.mezzano@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Francisca', 'apellido' => 'Palaneck', 'telefono' => null, 'email' => 'francisca.palaneck@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Evelyn', 'apellido' => 'Peña', 'telefono' => null, 'email' => 'evelyn.pena@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'María José', 'apellido' => 'Vega', 'telefono' => null, 'email' => 'mariajose.vega@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Valeria', 'apellido' => 'Tapia', 'telefono' => null, 'email' => 'valeria.tapia@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Diego', 'apellido' => 'Accevedo', 'telefono' => null, 'email' => 'diego.acevedo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Barbara', 'apellido' => 'González', 'telefono' => null, 'email' => 'barbara.gonzalez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Luis', 'apellido' => 'Gutierrez', 'telefono' => null, 'email' => 'luis.gutierrez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Macarena', 'apellido' => 'Muñoz', 'telefono' => null, 'email' => 'macarena.munoz@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Constanza', 'apellido' => 'Castro', 'telefono' => null, 'email' => 'constanza.castro@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Camila', 'apellido' => 'Martínez', 'telefono' => null, 'email' => 'camila.martinez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Francisca', 'apellido' => 'Ovalle', 'telefono' => null, 'email' => 'francisca.ovalle@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Matías', 'apellido' => 'Vial', 'telefono' => null, 'email' => 'matias.vial@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Luis', 'apellido' => 'Prado', 'telefono' => null, 'email' => 'luis.Prado@hgt.com', 'area_privada' => '5'],
            ['nombre' => 'Margarita', 'apellido' => 'Rivas', 'telefono' => null, 'email' => 'margarita.rivas@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jeannette', 'apellido' => 'Guajardo', 'telefono' => null, 'email' => 'jeannette.guajardo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Daniel', 'apellido' => 'Campusano', 'telefono' => null, 'email' => 'daniel.campusano@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jacqueline', 'apellido' => 'Castillo', 'telefono' => null, 'email' => 'jacqueline.castillo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Denisse', 'apellido' => 'Espinoza', 'telefono' => null, 'email' => 'denisse.espinoza@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Priscila', 'apellido' => 'Cerda', 'telefono' => null, 'email' => 'priscila.cerda@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jorge', 'apellido' => 'Cuevas', 'telefono' => null, 'email' => 'jorge.cuevas@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Andy', 'apellido' => 'Rubench', 'telefono' => null, 'email' => 'andy.allendes@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Jose Luis', 'apellido' => 'Jimenez', 'telefono' => null, 'email' => 'joseluis.jimenez@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Juan Luis', 'apellido' => 'Rebolledo', 'telefono' => null, 'email' => 'juan.rebolledo@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Carlos', 'apellido' => 'Blanco', 'telefono' => null, 'email' => 'carlos.blanco@saamterminals.com', 'area_privada' => '5'],
            ['nombre' => 'Rene', 'apellido' => 'Lopez', 'telefono' => null, 'email' => 'rene.lopez@saamterminals.com', 'area_privada' => '5'],
        ];
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
        if (! $user->wasRecentlyCreated)
        {
            $user->update(['password' => bcrypt($password)]);
        }

        // Asignar role global de admin (Spatie)
        if (! $user->hasRole('admin'))
        {
            $user->assignRole('admin');
            $this->command->info("   → Role global 'admin' asignado a {$email}");
        }

        $team = \App\Models\Team::find($teamId);

        if ($team)
        {
            // Verificar si el usuario ya pertenece al team
            $membership = \DB::table('team_user')
                ->where('team_id', $teamId)
                ->where('user_id', $user->id)
                ->first();

            if (! $membership)
            {
                // Agregar el usuario al team con rol de admin
                $team->users()->attach($user, ['role' => 'admin']);
                $this->command->info("✅ Usuario {$email} agregado al team como administrador");
            } else
            {
                // Actualizar el rol a admin si ya pertenece al team
                \DB::table('team_user')
                    ->where('team_id', $teamId)
                    ->where('user_id', $user->id)
                    ->update(['role' => 'admin']);
                $this->command->info("✅ Usuario {$email} ya pertenecía al team, rol actualizado a administrador");
            }

            // Asignar este team como current_team_id si no tiene uno
            if (! $user->current_team_id)
            {
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
                'phone' => 34722372858,
                'password' => bcrypt('password'),  // Contraseña por defecto si se crea
            ],
        );
        $user->update(['phone' => 34722372858]);

        // Asignar role global de admin (Spatie)
        if (! $user->hasRole('admin'))
        {
            $user->assignRole('admin');
            $this->command->info("   → Role global 'admin' asignado a {$email}");
        }

        // Obtener o crear el team
        $team = \App\Models\Team::find($teamId);

        if (! $team)
        {
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

        if (! $membership)
        {
            // Agregar el usuario al team con rol de admin
            $team->users()->attach($user, ['role' => 'admin']);
            $this->command->info("✅ Usuario {$email} agregado al team como administrador");
        } else
        {
            // Actualizar el rol a admin si ya pertenece al team
            \DB::table('team_user')
                ->where('team_id', $teamId)
                ->where('user_id', $user->id)
                ->update(['role' => 'admin']);
            $this->command->info("✅ Usuario {$email} ya pertenecía al team, rol actualizado a administrador");
        }

        // Asignar este team como current_team_id si no tiene uno
        if (! $user->current_team_id)
        {
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

        if ($module)
        {
            $module->update(['status' => 1]);
            $this->command->info('✅ Módulo Academy activado (status=1)');
        } else
        {
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

        if ($module && $team)
        {
            // Verificar si ya está asignado
            $exists = \DB::table('module_team')
                ->where('module_id', $module->id)
                ->where('team_id', $teamId)
                ->exists();

            if (! $exists)
            {
                \DB::table('module_team')->insert([
                    'module_id' => $module->id,
                    'team_id' => $teamId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info('✅ Módulo Academy asignado al team Lizama');
            } else
            {
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

        if (! $team)
        {
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

        foreach ($moduleKeys as $key)
        {
            $module = \App\Models\Module::where('key', $key)->first();

            if ($module)
            {
                // Verificar si ya está asignado
                $exists = \DB::table('module_team')
                    ->where('module_id', $module->id)
                    ->where('team_id', $teamId)
                    ->exists();

                if (! $exists)
                {
                    \DB::table('module_team')->insert([
                        'module_id' => $module->id,
                        'team_id' => $teamId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $assignedCount++;
                } else
                {
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
        foreach ($permissions as $permissionName)
        {
            \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => $permissionName],
                ['guard_name' => 'web'],
            );
        }

        $this->command->info('✅ Permisos de Academy verificados/creados');

        // Asignar permisos al role admin
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        if ($adminRole)
        {
            $adminRole->syncPermissions(array_merge(
                $adminRole->permissions->pluck('name')->toArray(),
                $permissions,
            ));
            $this->command->info('✅ Permisos de Academy asignados a role "admin"');
        }

        // Asignar permisos al role student
        $studentRole = \Spatie\Permission\Models\Role::where('name', 'student')->first();
        if ($studentRole)
        {
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

        if (! $academyModule)
        {
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

        foreach ($subcategories as $name => $description)
        {
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
