<?php

namespace App\Support;

use App\Models\User;

class RevisionAlphaOrganization
{
    public const TEAM_ID = 2;

    public const AGENCY_PERCENT = 30;

    public const ADVISOR_PERCENT = 10;

    public static function teamId(): int
    {
        return (int) config('organization.revision_alpha_team_id', self::TEAM_ID);
    }

    public static function appliesTo(?int $teamId): bool
    {
        return $teamId !== null && $teamId === self::teamId();
    }

    /**
     * @return array<string, array{name: string, color: string}>
     */
    public static function departments(): array
    {
        return [
            'administration' => ['name' => 'Administración', 'color' => '#feff9c'],
            'technical' => ['name' => 'Técnica', 'color' => '#ffc988'],
            'commercial' => ['name' => 'Comercial', 'color' => '#b4ff88'],
            'development' => ['name' => 'Desarrollo', 'color' => '#88e1ff'],
            'advertising' => ['name' => 'Publicidad', 'color' => '#ffb3c6'],
            'media' => ['name' => 'Medio', 'color' => '#d4b3ff'],
            'product' => ['name' => 'Producto', 'color' => '#b3f0e0'],
        ];
    }

    /**
     * @return array<string, array{name: string, area: string, url: string}>
     */
    public static function companies(): array
    {
        return [
            'revision_alpha' => [
                'name' => 'REVISION ALPHA',
                'area' => 'Infraestructura',
                'url' => 'https://revisionalpha.com',
            ],
            'idoneo' => [
                'name' => 'IDONEO',
                'area' => 'Desarrollo SaaS',
                'url' => 'https://www.idoneo.dev',
            ],
            'humano' => [
                'name' => 'HUMANO',
                'area' => 'Consultora tecnológica',
                'url' => 'https://mi.humano.app/slash',
            ],
            'mix' => [
                'name' => 'Mix Vasallo',
                'area' => 'Diseño',
                'url' => 'https://mixvasallo.com',
            ],
            'conga' => [
                'name' => 'CONGA',
                'area' => 'Marketing',
                'url' => 'https://somosconga.com',
            ],
            'fanyion' => [
                'name' => 'FANYION',
                'area' => 'Innovación',
                'url' => 'https://fanyion.com',
            ],
            'wapify' => [
                'name' => 'Wapify',
                'area' => 'E-commerce conversacional',
                'url' => 'https://wapify.com',
            ],
            'global_traffic' => [
                'name' => 'Global Trafic',
                'area' => 'Señalética digital',
                'url' => 'https://globaltraffic.com',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function people(): array
    {
        return [
            [
                'key' => 'magoo',
                'name' => 'Magoo',
                'kind' => 'internal',
                'titles' => [
                    'CEO · REVISION ALPHA',
                    'CEO · IDONEO',
                    'CTO · HUMANO',
                ],
                'company_keys' => ['revision_alpha', 'idoneo', 'humano'],
                'emails' => [],
                'weekly_hours' => 40,
                'work_starts_at' => '09:30',
                'work_ends_at' => '20:00',
                'monthly_salary' => null,
                'summary' => 'Lunes 9:30–11:00 aprobar publicaciones. Soporte 9:30–11 y emails/WhatsApp 11–12 (lun no soporte; mar academia de inglés). Luego programar: lun/jue hasta 20:00; mar/mié hasta 16:00; vie hasta 15:00. Miércoles 13–14 Master Mind (bloquea agenda, no cuenta horas). Martes 9:30–12 inglés (idem).',
            ],
            [
                'key' => 'leticia',
                'name' => 'Leticia',
                'kind' => 'internal',
                'titles' => [
                    'Asistente · REVISION ALPHA',
                ],
                'company_keys' => ['revision_alpha'],
                'emails' => [],
                'weekly_hours' => 25,
                'work_starts_at' => '18:30',
                'work_ends_at' => '23:30',
                'monthly_salary' => null,
                'summary' => 'Pagos, trato con clientes, anota lo relevante y da seguimiento. Horario España: 18:30 a 23:30.',
            ],
            [
                'key' => 'mix',
                'name' => 'Mix Vasallo',
                'kind' => 'alliance',
                'titles' => ['Diseño'],
                'company_keys' => ['mix'],
                'emails' => [],
                'weekly_hours' => null,
                'work_starts_at' => null,
                'work_ends_at' => null,
                'monthly_salary' => null,
                'summary' => 'Marca e identidad visual. Entra cuando el trabajo es diseño, no operación diaria.',
            ],
            [
                'key' => 'conga',
                'name' => 'CONGA',
                'kind' => 'alliance',
                'titles' => ['Marketing'],
                'company_keys' => ['conga'],
                'emails' => [],
                'weekly_hours' => null,
                'work_starts_at' => null,
                'work_ends_at' => null,
                'monthly_salary' => null,
                'summary' => 'Campañas y publicidad. Puede asesorar agencias que venden Idoneo (10%).',
            ],
            [
                'key' => 'fanyion',
                'name' => 'FANYION',
                'kind' => 'alliance',
                'titles' => ['Innovación'],
                'company_keys' => ['fanyion'],
                'emails' => [],
                'weekly_hours' => null,
                'work_starts_at' => null,
                'work_ends_at' => null,
                'monthly_salary' => null,
                'summary' => 'Innovación de proceso. No cubre soporte ni hosting.',
            ],
            [
                'key' => 'global_traffic',
                'name' => 'Global Trafic',
                'kind' => 'alliance',
                'titles' => ['Señalética digital'],
                'company_keys' => ['global_traffic'],
                'emails' => [],
                'weekly_hours' => null,
                'work_starts_at' => null,
                'work_ends_at' => null,
                'monthly_salary' => null,
                'summary' => 'Pantallas y señalética. Fuera del soporte de hosting y del SaaS diario.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function processes(): array
    {
        return array_merge(
            self::administrationProcesses(),
            self::technicalProcesses(),
            self::commercialProcesses(),
            self::developmentProcesses(),
            self::advertisingProcesses(),
            self::mediaProcesses(),
            self::productProcesses(),
        );
    }

    /**
     * @return list<array{person_key: string, role: string, percent: int, scope: string}>
     */
    public static function affiliateAssignments(): array
    {
        return [
            [
                'person_key' => 'magoo',
                'role' => 'Asesor',
                'percent' => self::ADVISOR_PERCENT,
                'scope' => 'Agencias y empresas del holding a las que acompaña en Idoneo / Humano.',
            ],
            [
                'person_key' => 'conga',
                'role' => 'Asesor',
                'percent' => self::ADVISOR_PERCENT,
                'scope' => 'Agencias de marketing que asesora para vender o usar Idoneo Ads, Mailer y Landings.',
            ],
        ];
    }

    public static function personKeyFor(?User $user): ?string
    {
        if ($user === null)
        {
            return null;
        }

        $email = strtolower(trim((string) $user->email));
        $name = mb_strtolower(trim((string) $user->name));

        foreach (self::people() as $person)
        {
            foreach ($person['emails'] as $personEmail)
            {
                if ($email !== '' && $email === strtolower((string) $personEmail))
                {
                    return $person['key'];
                }
            }

            if ($name !== '' && str_contains($name, mb_strtolower((string) $person['name'])))
            {
                return $person['key'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function viewData(?User $user = null): array
    {
        $departments = self::departments();
        $people = self::people();
        $processes = self::processes();
        $companies = self::companies();
        $currentKey = self::personKeyFor($user);

        $peopleByKey = [];
        foreach ($people as $person)
        {
            $peopleByKey[$person['key']] = $person;
        }

        $processesByDepartment = [];
        foreach (array_keys($departments) as $departmentKey)
        {
            $processesByDepartment[$departmentKey] = [];
        }

        foreach ($processes as $process)
        {
            $departmentKey = $process['department'];
            $process['responsible'] = $peopleByKey[$process['responsible_key']] ?? null;
            $process['company'] = $companies[$process['company_key']] ?? null;
            $process['hours_per_month'] = $process['hours_per_month'] ?? self::hoursPerMonthFromSchedules(
                $process['schedules'],
                (bool) ($process['counts_as_work'] ?? true),
            );
            $processesByDepartment[$departmentKey][] = $process;
        }

        $weekByPerson = [];
        $costRows = [];

        foreach ($people as $person)
        {
            $assigned = array_values(array_filter(
                $processes,
                static fn (array $process): bool => $process['responsible_key'] === $person['key'],
            ));

            foreach ($assigned as &$assignedProcess)
            {
                $assignedProcess['responsible'] = $person;
                $assignedProcess['company'] = $companies[$assignedProcess['company_key']] ?? null;
                $assignedProcess['hours_per_month'] = $assignedProcess['hours_per_month'] ?? self::hoursPerMonthFromSchedules(
                    $assignedProcess['schedules'],
                    (bool) ($assignedProcess['counts_as_work'] ?? true),
                );
            }
            unset($assignedProcess);

            $assignedHours = array_sum(array_map(
                static fn (array $process): float => empty($process['counts_as_work'])
                    ? 0.0
                    : (float) ($process['hours_per_month'] ?? 0),
                $assigned,
            ));

            $capacityMonthly = $person['weekly_hours'] ? round(((float) $person['weekly_hours']) * 4.3, 1) : null;
            $salary = $person['monthly_salary'];
            $loadCost = ($salary !== null && $capacityMonthly > 0)
                ? round($salary / $capacityMonthly * $assignedHours, 2)
                : null;

            $weekByPerson[$person['key']] = [
                'person' => $person,
                'processes' => $assigned,
                'grid' => self::weekGrid($assigned, $departments),
                'is_current' => $currentKey === $person['key'],
            ];

            $costRows[] = [
                'person' => $person,
                'assigned_hours' => round($assignedHours, 1),
                'capacity_hours' => $capacityMonthly,
                'salary' => $salary,
                'load_cost' => $loadCost,
            ];
        }

        $affiliateRows = [];
        foreach (self::affiliateAssignments() as $assignment)
        {
            $affiliateRows[] = [
                'assignment' => $assignment,
                'person' => $peopleByKey[$assignment['person_key']] ?? null,
            ];
        }

        return [
            'team_id' => self::teamId(),
            'agency_percent' => self::AGENCY_PERCENT,
            'advisor_percent' => self::ADVISOR_PERCENT,
            'departments' => $departments,
            'companies' => $companies,
            'people' => $people,
            'processes_by_department' => $processesByDepartment,
            'week_by_person' => $weekByPerson,
            'coverage_grid' => self::coverageGrid($processes, $peopleByKey, $departments),
            'cost_rows' => $costRows,
            'affiliate_rows' => $affiliateRows,
            'current_person_key' => $currentKey,
            'weekday_labels' => self::weekdayLabels(),
        ];
    }

    /**
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $schedules
     */
    public static function hoursPerMonthFromSchedules(array $schedules, bool $countsAsWork = true): float
    {
        if (! $countsAsWork)
        {
            return 0.0;
        }

        $weekly = 0.0;

        foreach ($schedules as $schedule)
        {
            $weekly += self::hoursBetween($schedule['starts_at'], $schedule['ends_at']);
        }

        return round($weekly * 4.3, 1);
    }

    /**
     * @return array<int, string>
     */
    public static function weekdayLabels(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $processes
     * @param  array<string, array{name: string, color: string}>  $departments
     * @return array{hours: list<string>, days: array<int, list<array<string, mixed>>>}
     */
    public static function weekGrid(array $processes, array $departments): array
    {
        $days = [];
        foreach (array_keys(self::weekdayLabels()) as $weekday)
        {
            $days[$weekday] = [];
        }

        foreach ($processes as $process)
        {
            if (empty($process['on_calendar']))
            {
                continue;
            }

            foreach ($process['schedules'] as $schedule)
            {
                $days[$schedule['weekday']][] = [
                    'name' => $process['name'],
                    'starts_at' => $schedule['starts_at'],
                    'ends_at' => $schedule['ends_at'],
                    'color' => empty($process['counts_as_work'])
                        ? '#e7e7e7'
                        : ($departments[$process['department']]['color'] ?? '#feff9c'),
                    'department' => $departments[$process['department']]['name'] ?? $process['department'],
                    'counts_as_work' => (bool) ($process['counts_as_work'] ?? true),
                    'is_blocker' => empty($process['counts_as_work']),
                ];
            }
        }

        foreach ($days as $weekday => $blocks)
        {
            usort($blocks, static function (array $a, array $b): int
            {
                return self::minutes($a['starts_at']) <=> self::minutes($b['starts_at']);
            });
            $days[$weekday] = $blocks;
        }

        $hours = [];
        for ($hour = 9; $hour <= 23; $hour++)
        {
            $label = sprintf('%02d:00', $hour);
            $used = false;

            foreach ($days as $blocks)
            {
                foreach ($blocks as $block)
                {
                    if (self::hourOverlapsBlock($label, $block['starts_at'], $block['ends_at']))
                    {
                        $used = true;
                        break 2;
                    }
                }
            }

            if ($used)
            {
                $hours[] = $label;
            }
        }

        return [
            'hours' => $hours,
            'days' => $days,
        ];
    }

    /**
     * 24×7 grid of who is at a computer (work blocks only). Gaps = no coverage.
     *
     * @param  list<array<string, mixed>>  $processes
     * @param  array<string, array<string, mixed>>  $peopleByKey
     * @param  array<string, array{name: string, color: string}>  $departments
     * @return array{hours: list<string>, days: array<int, array<string, array{covered: bool, covers: list<array<string, mixed>>}>>, gap_hours_per_week: float}
     */
    public static function coverageGrid(array $processes, array $peopleByKey, array $departments): array
    {
        $days = [];
        foreach (array_keys(self::weekdayLabels()) as $weekday)
        {
            $days[$weekday] = [];
            for ($hour = 0; $hour < 24; $hour++)
            {
                $label = sprintf('%02d:00', $hour);
                $days[$weekday][$label] = [
                    'covered' => false,
                    'covers' => [],
                ];
            }
        }

        foreach ($processes as $process)
        {
            if (empty($process['on_calendar']) || empty($process['counts_as_work']))
            {
                continue;
            }

            $person = $peopleByKey[$process['responsible_key']] ?? null;
            if ($person === null || ($person['kind'] ?? null) !== 'internal')
            {
                continue;
            }

            foreach ($process['schedules'] as $schedule)
            {
                $weekday = (int) $schedule['weekday'];
                for ($hour = 0; $hour < 24; $hour++)
                {
                    $label = sprintf('%02d:00', $hour);
                    if (! self::hourOverlapsBlock($label, $schedule['starts_at'], $schedule['ends_at']))
                    {
                        continue;
                    }

                    $days[$weekday][$label]['covered'] = true;
                    $days[$weekday][$label]['covers'][] = [
                        'person' => $person['name'],
                        'person_key' => $person['key'],
                        'name' => $process['name'],
                        'color' => $departments[$process['department']]['color'] ?? '#feff9c',
                        'starts_at' => $schedule['starts_at'],
                        'ends_at' => $schedule['ends_at'],
                    ];
                }
            }
        }

        $gapHours = 0;
        foreach ($days as $slots)
        {
            foreach ($slots as $slot)
            {
                if (! $slot['covered'])
                {
                    $gapHours++;
                }
            }
        }

        $hours = [];
        for ($hour = 0; $hour < 24; $hour++)
        {
            $hours[] = sprintf('%02d:00', $hour);
        }

        return [
            'hours' => $hours,
            'days' => $days,
            'gap_hours_per_week' => (float) $gapHours,
        ];
    }

    public static function hourOverlapsBlock(string $hour, string $startsAt, string $endsAt): bool
    {
        $slotStart = self::minutes($hour);
        $slotEnd = $slotStart + 60;
        $blockStart = self::minutes($startsAt);
        $blockEnd = self::minutes($endsAt);

        return $blockStart < $slotEnd && $blockEnd > $slotStart;
    }

    private static function hoursBetween(string $startsAt, string $endsAt): float
    {
        $diff = self::minutes($endsAt) - self::minutes($startsAt);

        return $diff > 0 ? $diff / 60 : 0.0;
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function administrationProcesses(): array
    {
        return [
            self::process('admin-invoices-review', 'Emisión de facturas', 'administration', 'leticia', 'revision_alpha', '4 hs mensuales', [], [
                'Revisar servicios del mes en el CMS (altas, bajas, cambios).',
                'Aprobar lo que se factura y dejar anotado lo que Magoo debe revisar.',
                'Confirmar que cada factura tiene cliente, vencimiento y servicio.',
            ], 'Cuando corresponda en el mes'),
            self::process('admin-invoices-load', 'Subir facturas al sistema', 'administration', 'leticia', 'revision_alpha', 'Viernes 19:00–20:00', [
                ['weekday' => 5, 'starts_at' => '19:00', 'ends_at' => '20:00'],
            ], [
                'Viernes, después de emails y tickets: cargar en el CMS las facturas de compra de la semana.',
                'Adjuntar el PDF o foto.',
                'Avisar si falta dato o si el importe no cierra.',
            ], null, null, true),
            self::process('admin-inbox', 'Emails y tickets', 'administration', 'leticia', 'revision_alpha', 'Lun–vie 18:30–19:00', [
                ['weekday' => 1, 'starts_at' => '18:30', 'ends_at' => '19:00'],
                ['weekday' => 2, 'starts_at' => '18:30', 'ends_at' => '19:00'],
                ['weekday' => 3, 'starts_at' => '18:30', 'ends_at' => '19:00'],
                ['weekday' => 4, 'starts_at' => '18:30', 'ends_at' => '19:00'],
                ['weekday' => 5, 'starts_at' => '18:30', 'ends_at' => '19:00'],
            ], [
                'Primera media hora de la jornada: responder emails y tickets abiertos.',
                'Dejar listo lo urgente antes de los llamados a Argentina.',
                'Lo que no se cierra en 30 minutos queda anotado para después o para Magoo.',
            ], null, null, true),
            self::process('admin-payments-in', 'Ingreso de pagos', 'administration', 'leticia', 'revision_alpha', 'Cuando entra un cobro', [], [
                'Cruzar transferencias y cobros con la factura del cliente.',
                'Registrar el pago en el CMS referenciado al comprobante.',
                'Si no cierra, anotarlo para seguimiento (no dejarlo en el aire).',
            ], 'Cuando entra un cobro'),
            self::process('admin-clients', 'Hablar con clientes y seguimiento', 'administration', 'leticia', 'revision_alpha', 'Cuando entra', [], [
                'Atender consultas de clientes por el canal que entre (email, WhatsApp, teléfono).',
                'Anotar lo relevante para Magoo: impagos, bajas, incidencias, compromisos.',
                'Dar seguimiento al día siguiente: qué quedó pendiente y quién lo tiene.',
            ], 'En cualquier momento de su ventana'),
            self::process('admin-afip', 'Cierre contable de la semana', 'administration', 'leticia', 'revision_alpha', 'Viernes 20:00–21:00', [
                ['weekday' => 5, 'starts_at' => '20:00', 'ends_at' => '21:00'],
            ], [
                'Viernes, después de subir facturas: cuadrar cobros e ingresos del CMS.',
                'Antes del día 10 del mes: subir el lote al Cloud y avisar a la contadora.',
                'Dejar listo el cierre antes del plan de publicaciones (21:00) para que Magoo apruebe el lunes a primera hora.',
            ], null, null, true),
            self::process('admin-cash', 'Arqueo de caja', 'administration', 'magoo', 'revision_alpha', 'Al cerrar la semana', [], [
                'Revisar el cierre que deja Leticia.',
                'Confirmar caja e ingresos antes de abrir la semana siguiente.',
            ]),
            self::process('admin-approve-posts', 'Aprobar publicaciones', 'administration', 'magoo', 'revision_alpha', 'Lunes 09:30–11:00', [
                ['weekday' => 1, 'starts_at' => '09:30', 'ends_at' => '11:00'],
            ], [
                'Lunes 9:30–11:00: revisar el plan de publicaciones que Leticia dejó el viernes a la noche.',
                'Aprobar, corregir o devolver piezas antes de emails/WhatsApp (11:00).',
                'Ese lunes no hay franja de soporte: la prioridad es el plan de publicaciones.',
            ], null, null, true),
            self::process('admin-budgets', 'Aprobación de presupuestos', 'administration', 'magoo', 'revision_alpha', '4 h al mes', [], [
                'Revisar presupuestos de proyecto o compra.',
                'Aprobar o devolver con el motivo.',
            ]),
            self::process('personal-english', 'Academia de inglés', 'administration', 'magoo', 'revision_alpha', 'Martes 09:30–12:00', [
                ['weekday' => 2, 'starts_at' => '09:30', 'ends_at' => '12:00'],
            ], [
                'Bloquea la agenda. No cuenta como horas laborales.',
                'Ese martes no hay franja de soporte ni de emails/WhatsApp.',
            ], null, 0.0, true, false),
            self::process('personal-mastermind', 'Master Mind', 'administration', 'magoo', 'revision_alpha', 'Miércoles 13:00–14:00', [
                ['weekday' => 3, 'starts_at' => '13:00', 'ends_at' => '14:00'],
            ], [
                'Bloquea la agenda. No cuenta como horas laborales.',
            ], null, 0.0, true, false),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function technicalProcesses(): array
    {
        return [
            self::process('tech-support', 'Soporte y migraciones', 'technical', 'magoo', 'revision_alpha', 'Mié–vie 9:30–11:00', [
                ['weekday' => 3, 'starts_at' => '09:30', 'ends_at' => '11:00'],
                ['weekday' => 4, 'starts_at' => '09:30', 'ends_at' => '11:00'],
                ['weekday' => 5, 'starts_at' => '09:30', 'ends_at' => '11:00'],
            ], [
                '9:30–11:00: tickets de soporte, incidencias y migraciones (no lun: aprobar publicaciones; no mar: academia de inglés).',
                'Si no cierra en la franja, queda anotado o pasa a la guardia.',
            ], null, null, true),
            self::process('tech-inbox', 'Emails y WhatsApp', 'technical', 'magoo', 'revision_alpha', 'Lun, mié–vie 11:00–12:00', [
                ['weekday' => 1, 'starts_at' => '11:00', 'ends_at' => '12:00'],
                ['weekday' => 3, 'starts_at' => '11:00', 'ends_at' => '12:00'],
                ['weekday' => 4, 'starts_at' => '11:00', 'ends_at' => '12:00'],
                ['weekday' => 5, 'starts_at' => '11:00', 'ends_at' => '12:00'],
            ], [
                '11:00–12:00: bandeja de email y WhatsApp del día (no martes: academia de inglés).',
                'Responder o asignar; lo técnico largo vuelve a soporte o a programar.',
            ], null, null, true),
            self::process('tech-phone', 'Atención telefónica', 'technical', 'magoo', 'revision_alpha', 'Dentro de soporte', [], [
                'Entra en la franja de soporte y migraciones (9:30–11:00).',
                'Si no se resuelve en el primer contacto, abrir ticket y avisar el siguiente paso.',
            ], 'Dentro de soporte 9:30–11:00'),
            self::process('tech-whatsapp', 'Atención por WhatsApp', 'technical', 'magoo', 'revision_alpha', 'Dentro de emails 11–12', [], [
                'Entra en la franja de emails y WhatsApp (11:00–12:00).',
                'No dejar un chat sin cierre o sin dueño.',
            ], 'Dentro de emails 11:00–12:00'),
            self::process('tech-email', 'Atención por email', 'technical', 'magoo', 'revision_alpha', 'Dentro de emails 11–12', [], [
                'Entra en la franja de emails y WhatsApp (11:00–12:00).',
            ], 'Dentro de emails 11:00–12:00'),
            self::process('tech-oncall', 'Guardia 24x7', 'technical', 'magoo', 'revision_alpha', 'Extra laboral', [], [
                'Incidencias de hardware en datacenter: intervenir según nivel de soporte contratado.',
                'Standard: primera respuesta en 8 h laborables. Premium: 2 h. Business: 30 min. Enterprise: 15 min.',
            ], '24x7'),
            self::process('tech-alerts', 'Control de alertas, backups y firewall', 'technical', 'magoo', 'revision_alpha', 'Cuando salta una alerta', [], [
                'Revisar alertas de hardware, backups cPanel/VMware y firewall.',
                'Si falla un backup, no esperar al cliente: abrir incidencia.',
            ], 'Cuando salta una alerta'),
            self::process('tech-vps', 'VPS, cPanel y VPN', 'technical', 'magoo', 'revision_alpha', 'Bajo demanda', [], [
                'Alta de VPS, tunning cPanel/VPS y usuarios VPN según el procedimiento interno.',
                'Magoo instala sistemas y elige/cambia hardware cuando hace falta.',
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function commercialProcesses(): array
    {
        return [
            self::process('com-calls', 'Llamados a clientes', 'commercial', 'leticia', 'revision_alpha', 'Lun–jue 19:00–20:00', [
                ['weekday' => 1, 'starts_at' => '19:00', 'ends_at' => '20:00'],
                ['weekday' => 2, 'starts_at' => '19:00', 'ends_at' => '20:00'],
                ['weekday' => 3, 'starts_at' => '19:00', 'ends_at' => '20:00'],
                ['weekday' => 4, 'starts_at' => '19:00', 'ends_at' => '20:00'],
            ], [
                'Después de emails y tickets (19:00 ESP = 14:00 ARG): llamar a clientes en Argentina.',
                'Lista: impago, renovación, baja, seguimiento y oportunidades abiertas.',
                'Anotar el resultado y el próximo paso con fecha. Dejar a Magoo solo lo que no se cerró.',
            ], null, null, true),
            self::process('com-prospecting', 'Prospección', 'commercial', 'leticia', 'revision_alpha', 'Mar y jue 20:00–23:00', [
                ['weekday' => 2, 'starts_at' => '20:00', 'ends_at' => '23:00'],
                ['weekday' => 4, 'starts_at' => '21:00', 'ends_at' => '23:00'],
            ], [
                'Después de los llamados: buscar y contactar prospectos (agencias, empresas, referidos).',
                'Registrar cada lead en el CRM con el siguiente paso.',
                'Priorizar quien pueda comprar Idoneo o hosting REVISION ALPHA.',
            ], null, null, true),
            self::process('com-onoff', 'Altas y bajas de servicio', 'commercial', 'leticia', 'revision_alpha', 'Bajo demanda', [], [
                'Alta: cargar el servicio y avisar a Técnica.',
                'Baja: preguntar el motivo, anotarlo, y recién entonces gestionar la baja.',
            ]),
            self::process('com-meetings', 'Reuniones con clientes y proveedores', 'commercial', 'magoo', 'revision_alpha', 'Cuando se agenda', [], [
                'Magoo lleva la reunión. Leticia confirma agenda y deja notas de seguimiento.',
            ]),
            self::process('com-affiliates', 'Programa de afiliados', 'commercial', 'magoo', 'idoneo', 'Cuando hay movimiento', [], [
                'Agencia que vende un producto Idoneo: 30% del cobro.',
                'Quien asesora a esa agencia o empresa: 10%.',
                'Revisar en Afiliados (/billing) qué proyectos y empresas tiene cada uno.',
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function developmentProcesses(): array
    {
        return [
            self::process('dev-code', 'Programar', 'development', 'magoo', 'idoneo', 'Tras emails, hasta fin de jornada', [
                ['weekday' => 1, 'starts_at' => '12:00', 'ends_at' => '20:00'],
                ['weekday' => 2, 'starts_at' => '12:00', 'ends_at' => '16:00'],
                ['weekday' => 3, 'starts_at' => '12:00', 'ends_at' => '13:00'],
                ['weekday' => 3, 'starts_at' => '14:00', 'ends_at' => '16:00'],
                ['weekday' => 4, 'starts_at' => '14:00', 'ends_at' => '20:00'],
                ['weekday' => 5, 'starts_at' => '12:00', 'ends_at' => '15:00'],
            ], [
                'Después de la mañana (aprobar pubs / soporte / emails, según el día): programar. Martes arranca a las 12 tras la academia.',
                'Lunes hasta 20:00. Martes 12:00–16:00. Miércoles 12:00–13:00 y 14:00–16:00 (13:00–14:00 Master Mind). Jueves desde 14:00 tras publicidad, hasta 20:00. Viernes hasta 15:00.',
                'Incluye apps Idoneo, sitios y lo urgente de producción.',
            ], null, null, true),
            self::process('dev-apps', 'Mantenimiento de apps Idoneo', 'development', 'magoo', 'idoneo', 'Dentro de programar', [], [
                'Corregir y desplegar Assistant, Shop, Mailer, Ads, Landings, Affiliates y Communications.',
                'Sale en la franja de Programar.',
            ], 'Dentro de Programar'),
            self::process('dev-sites', 'Sitios propios y de clientes', 'development', 'magoo', 'idoneo', 'Dentro de programar', [], [
                'Cambios en revisionalpha.com, idoneo.dev y humano.app.',
                'Sitios de clientes: solo con pedido claro y responsable de cuenta informado.',
            ], 'Dentro de Programar'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function advertisingProcesses(): array
    {
        return [
            self::process('ads-campaigns', 'Generar publicidad', 'advertising', 'magoo', 'idoneo', 'Jueves 12:00–14:00', [
                ['weekday' => 4, 'starts_at' => '12:00', 'ends_at' => '14:00'],
            ], [
                'Jueves 12:00–14:00: armar o ajustar la publicidad de la semana.',
                'Montar la campaña en Idoneo Ads / Meta / Google. CONGA entra si la pieza es de marketing.',
                'Leticia lo baja a acciones en su plan de marketing.',
            ], null, null, true),
            self::process('ads-plan', 'Plan de marketing', 'advertising', 'leticia', 'revision_alpha', 'Lun y mié 20:00–23:00', [
                ['weekday' => 1, 'starts_at' => '20:00', 'ends_at' => '23:00'],
                ['weekday' => 3, 'starts_at' => '20:00', 'ends_at' => '23:00'],
            ], [
                'Después de los llamados: armar y ejecutar el plan de marketing de la semana.',
                'Lunes: definir qué se promociona, a quién y por qué canal.',
                'Miércoles: preparar acciones; el jueves Magoo genera la publicidad 12:00–14:00.',
            ], null, null, true),
            self::process('ads-product', 'Producto Idoneo Ads', 'advertising', 'magoo', 'idoneo', 'Cuando hay incidencia', [], [
                'Que una agencia pueda conectar cuentas, ver campañas y no perder el hilo del cliente.',
                'Bugs y onboarding de Ads salen de esta franja.',
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mediaProcesses(): array
    {
        return [
            self::process('media-plan', 'Plan de publicaciones', 'media', 'leticia', 'revision_alpha', 'Viernes 21:00–23:00', [
                ['weekday' => 5, 'starts_at' => '21:00', 'ends_at' => '23:00'],
            ], [
                'Viernes a la noche, después de facturas y cierre contable: dejar el plan listo para que Magoo lo apruebe el lunes a primera hora.',
                'Definir qué se publica la semana siguiente (redes, mailer, web) y en qué día sale cada pieza.',
                'Si falta una pieza, avisar a Magoo o a CONGA en el mismo plan.',
            ], null, null, true),
            self::process('media-mailer', 'Mailer y newsletters', 'media', 'magoo', 'idoneo', 'Cuando hay envío', [], [
                'Confeccionar y enviar comunicados / newsletters.',
                'Listas limpias: no mandar a quien pidió baja.',
            ]),
            self::process('media-cms', 'CMS, media y landings', 'media', 'magoo', 'idoneo', 'Cuando hay publicación', [], [
                'Publicar páginas, posts y media del holding.',
                'Landings de captación quedan enlazadas al CRM (contactos).',
            ]),
            self::process('media-comms', 'Comunicaciones de pago', 'media', 'leticia', 'revision_alpha', 'Jueves 20:00–21:00', [
                ['weekday' => 4, 'starts_at' => '20:00', 'ends_at' => '21:00'],
            ], [
                'Jueves, después de los llamados a Argentina: enviar avisos de facturación, vencimiento o cobro pendiente.',
                'Magoo aprueba el texto si cambia el mensaje habitual.',
                'Confirmar que salió y anotar quién no respondió.',
            ], null, null, true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function productProcesses(): array
    {
        return [
            self::process('prod-assistant', 'Producto Assistant / Humano', 'product', 'magoo', 'humano', 'Cuando hay incidencia', [], [
                'WhatsApp, contactos, tareas y tono de marca del equipo cliente.',
                'Si un onboarding se traba, sale en esta franja — no en soporte de hosting.',
            ]),
            self::process('prod-shop', 'Producto Shop / Wapify', 'product', 'magoo', 'idoneo', 'Cuando hay incidencia', [], [
                'Wapify es el producto de tienda y catálogo por WhatsApp, no una persona del equipo.',
                'Magoo entra si el fallo es del SaaS.',
            ]),
            self::process('prod-affiliates', 'Producto Affiliates', 'product', 'magoo', 'idoneo', 'Cuando hay movimiento', [], [
                'Que la agencia vea su 30% y el asesor su 10% por proyecto o empresa.',
                'El detalle operativo vive en /billing; aquí solo se decide si el producto está claro.',
            ]),
        ];
    }

    /**
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $schedules
     * @param  list<string>  $steps
     * @return array<string, mixed>
     */
    private static function process(
        string $key,
        string $name,
        string $department,
        string $responsibleKey,
        string $companyKey,
        string $timeAllocation,
        array $schedules,
        array $steps,
        ?string $availability = null,
        ?float $hoursPerMonth = null,
        bool $onCalendar = false,
        bool $countsAsWork = true,
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'department' => $department,
            'responsible_key' => $responsibleKey,
            'company_key' => $companyKey,
            'time_allocation' => $timeAllocation,
            'availability' => $availability,
            'schedules' => $schedules,
            'steps' => $steps,
            'hours_per_month' => $hoursPerMonth,
            'on_calendar' => $onCalendar,
            'counts_as_work' => $countsAsWork,
        ];
    }
}
