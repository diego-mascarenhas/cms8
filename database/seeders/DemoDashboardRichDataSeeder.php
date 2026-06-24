<?php

namespace Database\Seeders;

use App\Enums\ContactInteractionType;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactInteraction;
use App\Models\ContactSentimentHistory;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDailyPerformanceInsight;
use App\Services\DailyTeamDigestMetricsCollector;
use App\Services\UserDailyPerformanceInsightService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rich dashboard demo: volume contacts, interaction activity, emotional balance, admin insight card.
 *
 * Run after {@see DemoPerformanceInsightsSeeder} from {@see TeamDemoSeeder::finalizeDemoPresentationData}.
 * Fresh install: applied automatically at the end of {@see TeamDemoSeeder} (migrate:fresh --seed).
 * Standalone: php artisan db:seed --class=DemoDashboardRichDataSeeder
 */
class DemoDashboardRichDataSeeder extends Seeder
{
    private const int TARGET_CONTACTS = 120;

    private const int TARGET_INTERACTIONS = 280;

    private const int TARGET_FINALIZED = 7;

    private const int TARGET_CLIENTE = 10;

    /** @var list<int> Weighted sentiment ids (more positive/neutral for demo charts) */
    private const SENTIMENT_POOL = [5, 5, 4, 4, 4, 3, 3, 3, 2, 2, 1];

    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if ($team === null)
        {
            $this->command?->warn('DemoDashboardRichDataSeeder: team "Demo" not found — skip.');

            return;
        }

        $admin = User::query()->where('email', 'admin@humano.app')->first();
        $ownerId = (int) ($admin?->id ?? User::query()->value('id') ?? 1);

        $this->command?->info('📊 Seeding dashboard-rich demo data (contacts, activity, sentiment, insight)...');

        $team->enableModule('performance_insights');
        $team->enableModule('contacts');
        $team->refresh();

        $this->ensureDemoContactCategories($team);

        $contactsAdded = $this->seedBulkContacts($team, $ownerId);
        $interactionsAdded = $this->seedContactInteractions($team, $ownerId);
        $sentimentsAdded = $this->seedContactSentiments($team);
        $categoriesAssigned = $this->assignContactCategories($team);
        $finalizedSet = $this->ensureFinalizedContacts($team);
        $clientesSet = $this->ensureClienteContacts($team);
        $this->spreadContactCreatedDates($team);

        $this->ensureAdminTodayInsight($team, $admin);

        $this->command?->info(sprintf(
            '✅ Dashboard demo data: +%d contacts, +%d interactions, +%d sentiment rows, %d categorías, %d finalizados, %d clientes CRM, insight for admin.',
            $contactsAdded,
            $interactionsAdded,
            $sentimentsAdded,
            $categoriesAssigned,
            $finalizedSet,
            $clientesSet,
        ));
    }

    private function seedBulkContacts(Team $team, int $ownerId): int
    {
        $existing = Contact::withoutGlobalScopes()->where('team_id', $team->id)->count();
        $toCreate = max(0, self::TARGET_CONTACTS - $existing);

        if ($toCreate === 0)
        {
            return 0;
        }

        $firstNames = ['Alejandro', 'Valentina', 'Mateo', 'Lucía', 'Hugo', 'Irene', 'Adrián', 'Paula', 'Marcos', 'Sara', 'Gonzalo', 'Alba', 'Víctor', 'Noelia', 'Iván', 'Celia', 'Rafael', 'Inés', 'Guillermo', 'Aitana'];
        $surnames = ['Romero', 'Navarro', 'Serrano', 'Gil', 'Blanco', 'Vega', 'Molina', 'Prieto', 'Cruz', 'Reyes', 'Flores', 'Cabrera', 'Peña', 'León', 'Herrera', 'Aguilar', 'Soto', 'Méndez', 'Guerrero', 'Cano'];
        $profiles = ['Director comercial', 'Responsable de compras', 'CEO', 'CTO', 'Gerente', 'Fundador', 'COO', 'Director marketing'];

        $created = 0;

        for ($i = 0; $i < $toCreate; $i++)
        {
            $n = $existing + $i + 1;
            $first = $firstNames[$i % count($firstNames)];
            $last = $surnames[($i + 3) % count($surnames)];
            $email = sprintf('demo.contact.%04d@cliente-bulk.demo', $n);
            $createdAt = now()->subDays($i % 14)->setTime(9 + ($i % 8), ($i * 13) % 60);

            $contact = Contact::withoutGlobalScopes()->updateOrCreate(
                ['email' => $email, 'team_id' => $team->id],
                [
                    'name' => $first,
                    'surname' => $last,
                    'phone' => '3460040'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
                    'profile' => $profiles[$i % count($profiles)],
                    'creator_id' => $ownerId,
                    'responsible_id' => $ownerId,
                    'status_id' => ($i % 4 === 0) ? 1 : (($i % 3) + 2),
                    'country' => 724,
                    'language' => 'es',
                    'engagment' => collect(['cold', 'temperate', 'hot'])->random(),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ],
            );

            $this->attachDemoCategory($team, $contact, $i);

            $created++;
        }

        return $created;
    }

    private function seedContactInteractions(Team $team, int $ownerId): int
    {
        $existing = ContactInteraction::query()
            ->whereHas('contact', fn ($q) => $q->where('team_id', $team->id))
            ->count();

        $toCreate = max(0, self::TARGET_INTERACTIONS - $existing);

        if ($toCreate === 0)
        {
            return 0;
        }

        $contactIds = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->pluck('id')
            ->all();

        if ($contactIds === [])
        {
            return 0;
        }

        $types = [
            ContactInteractionType::Call,
            ContactInteractionType::Email,
            ContactInteractionType::WhatsApp,
            ContactInteractionType::Meeting,
            ContactInteractionType::Note,
        ];

        $subjects = [
            'Seguimiento comercial',
            'Consulta sobre propuesta',
            'Revisión de contrato',
            'Demo del producto',
            'Soporte post-venta',
            'Renovación anual',
            'Feedback del cliente',
            'Coordinación de reunión',
        ];

        $created = 0;

        for ($i = 0; $i < $toCreate; $i++)
        {
            $contactId = $contactIds[$i % count($contactIds)];
            $dayOffset = (int) round(($i / max(1, $toCreate - 1)) * 29);
            $occurredAt = now()->subDays($dayOffset)->setTime(9 + ($i % 9), ($i * 11) % 60);
            $type = $types[$i % count($types)];

            ContactInteraction::query()->create([
                'contact_id' => $contactId,
                'user_id' => $ownerId,
                'type' => $type,
                'subject' => $subjects[$i % count($subjects)],
                'body' => 'Actividad demo registrada automáticamente para el panel del dashboard.',
                'occurred_at' => $occurredAt,
            ]);

            $created++;
        }

        return $created;
    }

    private function seedContactSentiments(Team $team): int
    {
        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->get(['id']);

        $created = 0;

        foreach ($contacts as $index => $contact)
        {
            $hasRecent = ContactSentimentHistory::query()
                ->where('contact_id', $contact->id)
                ->exists();

            if ($hasRecent)
            {
                continue;
            }

            $sentimentId = self::SENTIMENT_POOL[$index % count(self::SENTIMENT_POOL)];
            $recordedAt = now()->subDays($index % 14)->subHours($index % 8);

            ContactSentimentHistory::query()->create([
                'contact_id' => $contact->id,
                'sentiment_id' => $sentimentId,
                'notes' => 'Balance emocional demo (seeder).',
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Assign lead created_at with uneven daily counts (0–15), quieter on weekends.
     */
    private function spreadContactCreatedDates(Team $team): void
    {
        $leads = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', 1)
            ->orderBy('id')
            ->get(['id']);

        if ($leads->isEmpty())
        {
            return;
        }

        $dayOffsets = $this->buildLeadDayOffsetSlots($leads->count());

        foreach ($leads as $index => $contact)
        {
            $dayOffset = $dayOffsets[$index] ?? 0;
            $createdAt = now()->subDays($dayOffset)->setTime(8 + ($index % 10), ($index * 17) % 60);

            DB::table('contacts')
                ->where('id', $contact->id)
                ->update([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
        }
    }

    /**
     * @return list<int> Day offsets (0 = today) repeated per lead slot.
     */
    private function buildLeadDayOffsetSlots(int $leadCount): array
    {
        $weights = $this->dailyLeadCountsForChart(30);
        $peakTarget = min(15, max(8, (int) round($leadCount * 0.22)));
        $peakOffset = $this->peakRecentWeekdayOffset($weights);

        $slots = array_fill(0, min($peakTarget, $leadCount), $peakOffset);
        $remaining = max(0, $leadCount - count($slots));

        if ($remaining > 0)
        {
            $weights[$peakOffset] = max(0, ($weights[$peakOffset] ?? 0) - $peakTarget);
            $slots = array_merge($slots, $this->allocateSlotsByWeight($weights, $remaining));
        }

        shuffle($slots);

        return $slots;
    }

    /**
     * @param  array<int, int>  $weights
     * @return list<int>
     */
    private function allocateSlotsByWeight(array $weights, int $budget): array
    {
        if ($budget === 0)
        {
            return [];
        }

        $total = max(1, array_sum($weights));
        $slots = [];
        $assigned = 0;
        $offsets = array_keys($weights);

        foreach ($offsets as $index => $dayOffset)
        {
            $count = $weights[$dayOffset];
            $isLast = $index === count($offsets) - 1;
            $share = $isLast
                ? max(0, $budget - $assigned)
                : max(0, (int) round($count / $total * $budget));

            $assigned += $share;

            for ($i = 0; $i < $share; $i++)
            {
                $slots[] = $dayOffset;
            }
        }

        while (count($slots) < $budget)
        {
            $slots[] = $this->nextRecentWeekdayOffset(count($slots));
        }

        while (count($slots) > $budget)
        {
            array_shift($slots);
        }

        return $slots;
    }

    /**
     * @param  array<int, int>  $weights
     */
    private function peakRecentWeekdayOffset(array $weights): int
    {
        $peakOffset = 0;
        $peakWeight = -1;

        foreach ($weights as $offset => $weight)
        {
            if ($offset > 7)
            {
                continue;
            }

            $date = now()->copy()->subDays($offset)->startOfDay();

            if ($date->isWeekend())
            {
                continue;
            }

            if ($weight > $peakWeight)
            {
                $peakWeight = $weight;
                $peakOffset = $offset;
            }
        }

        return $peakOffset;
    }

    /**
     * @return array<int, int> Keys = day offset (0 today), values = lead count.
     */
    private function dailyLeadCountsForChart(int $days): array
    {
        $weekdayCounts = [15, 6, 2, 0, 11, 8, 4, 9, 3, 14, 7, 5, 1, 10, 12, 6, 0, 13, 4, 8];
        $weekendCounts = [0, 0, 1, 0, 2, 0];

        $counts = [];
        $weekdayIndex = 0;
        $weekendIndex = 0;

        for ($offset = $days - 1; $offset >= 0; $offset--)
        {
            $date = now()->copy()->subDays($offset)->startOfDay();

            if ($date->isWeekend())
            {
                $counts[$offset] = $weekendCounts[$weekendIndex % count($weekendCounts)];
                $weekendIndex++;
            } else
            {
                $counts[$offset] = $weekdayCounts[$weekdayIndex % count($weekdayCounts)];
                $weekdayIndex++;
            }
        }

        $recentBoost = [11, 14, 9, 12, 8, 10, 15, 6];
        foreach ($counts as $offset => $count)
        {
            if ($offset > 7)
            {
                continue;
            }

            $date = now()->copy()->subDays($offset)->startOfDay();
            if ($date->isWeekend())
            {
                continue;
            }

            $counts[$offset] = $count + ($recentBoost[$offset] ?? 6);
        }

        return $counts;
    }

    private function nextRecentWeekdayOffset(int $seed): int
    {
        for ($offset = $seed % 8; $offset >= 0; $offset--)
        {
            if (! now()->copy()->subDays($offset)->isWeekend())
            {
                return $offset;
            }
        }

        return $this->nextWeekdayOffset($seed);
    }

    private function nextWeekdayOffset(int $seed): int
    {
        for ($offset = $seed % 30; $offset < 30; $offset++)
        {
            if (! now()->copy()->subDays($offset)->isWeekend())
            {
                return $offset;
            }
        }

        return 3;
    }

    private function ensureFinalizedContacts(Team $team): int
    {
        $finalizedStatusId = (int) (ContactStatus::query()->where('name', 'Finalizado')->value('id') ?? 6);

        $existing = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', $finalizedStatusId)
            ->count();

        $needed = max(0, self::TARGET_FINALIZED - $existing);

        if ($needed === 0)
        {
            return 0;
        }

        $preferredEmails = [
            'antonio.romero@cliente11.com',
            'cristina.navarro@cliente12.com',
            'francisco.serrano@cliente13.com',
            'lucia.blanco@cliente14.com',
            'javier.castro@cliente15.com',
            'elena.iglesias@cliente16.com',
            'roberto.vargas@cliente17.com',
        ];

        $preferredIds = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('email', $preferredEmails)
            ->where('status_id', '!=', $finalizedStatusId)
            ->pluck('id');

        $fallbackIds = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', '!=', $finalizedStatusId)
            ->whereNotIn('email', ['admin@humano.app'])
            ->whereNotIn('id', $preferredIds)
            ->orderBy('id')
            ->limit(max(0, $needed - $preferredIds->count()))
            ->pluck('id');

        $contactIds = $preferredIds->merge($fallbackIds)->take($needed);

        if ($contactIds->isEmpty())
        {
            return 0;
        }

        Contact::withoutGlobalScopes()
            ->whereIn('id', $contactIds)
            ->update(['status_id' => $finalizedStatusId]);

        return $contactIds->count();
    }

    private function ensureClienteContacts(Team $team): int
    {
        $clienteStatusId = (int) (ContactStatus::query()->where('name', 'Cliente')->value('id') ?? 5);
        $finalizadoStatusId = (int) (ContactStatus::query()->where('name', 'Finalizado')->value('id') ?? 6);

        $enterprises = Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('type_id', 1)
            ->where('status_id', 2)
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($enterprises->isEmpty())
        {
            return 0;
        }

        Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', $clienteStatusId)
            ->orderBy('id')
            ->get(['id', 'current_enterprise_id'])
            ->each(function (Contact $contact, int $index) use ($enterprises): void
            {
                $enterprise = $enterprises[$index % $enterprises->count()];
                $this->linkContactToEnterprise($contact, $enterprise);
            });

        $currentClienteCount = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', $clienteStatusId)
            ->count();

        if ($currentClienteCount > self::TARGET_CLIENTE)
        {
            $excessIds = Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('status_id', $clienteStatusId)
                ->orderByDesc('id')
                ->limit($currentClienteCount - self::TARGET_CLIENTE)
                ->pluck('id');

            Contact::withoutGlobalScopes()
                ->whereIn('id', $excessIds)
                ->update(['status_id' => 2, 'current_enterprise_id' => null]);
        }

        $needed = max(0, self::TARGET_CLIENTE - Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', $clienteStatusId)
            ->count());

        if ($needed === 0)
        {
            return Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('status_id', $clienteStatusId)
                ->count();
        }

        $preferredEmails = [
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
        ];

        $candidates = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereNotIn('status_id', [$clienteStatusId, $finalizadoStatusId])
            ->whereNotIn('email', ['admin@humano.app'])
            ->where(function ($query) use ($preferredEmails): void
            {
                $query->whereIn('email', $preferredEmails)
                    ->orWhere('email', 'like', '%@cliente%.demo');
            })
            ->orderBy('id')
            ->limit($needed)
            ->get();

        if ($candidates->count() < $needed)
        {
            $fallback = Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereNotIn('status_id', [$clienteStatusId, $finalizadoStatusId])
                ->whereNotIn('email', ['admin@humano.app'])
                ->whereNotIn('id', $candidates->pluck('id'))
                ->orderBy('id')
                ->limit($needed - $candidates->count())
                ->get();

            $candidates = $candidates->merge($fallback);
        }

        foreach ($candidates as $index => $contact)
        {
            $enterprise = $enterprises[$index % $enterprises->count()];
            $contact->update([
                'status_id' => $clienteStatusId,
                'current_enterprise_id' => $enterprise->id,
            ]);
            $this->linkContactToEnterprise($contact, $enterprise);
        }

        return Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', $clienteStatusId)
            ->count();
    }

    private function linkContactToEnterprise(Contact $contact, Enterprise $enterprise): void
    {
        if ((int) $contact->current_enterprise_id !== (int) $enterprise->id)
        {
            $contact->update(['current_enterprise_id' => $enterprise->id]);
        }

        DB::table('contact_enterprise')->updateOrInsert(
            ['contact_id' => $contact->id, 'enterprise_id' => $enterprise->id],
            [
                'position' => $contact->profile ?: 'Contacto comercial',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function assignContactCategories(Team $team): int
    {
        $assigned = 0;

        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->orderBy('id')
            ->get(['id']);

        foreach ($contacts as $index => $contact)
        {
            if ($contact->categories()->exists())
            {
                continue;
            }

            $this->attachDemoCategory($team, $contact, $index);
            $assigned++;
        }

        return $assigned;
    }

    private function attachDemoCategory(Team $team, Contact $contact, int $index): void
    {
        $moduleId = Module::query()->where('key', 'contacts')->value('id');

        if (! $moduleId)
        {
            return;
        }

        $categoryNames = ['Referido', 'Tester', 'Staff', 'Developer'];
        $categoryName = $categoryNames[$index % count($categoryNames)];

        $category = Category::query()
            ->where('team_id', $team->id)
            ->where('module_id', $moduleId)
            ->where('name', $categoryName)
            ->first();

        if ($category === null)
        {
            return;
        }

        if (! $contact->categories()->where('category_id', $category->id)->exists())
        {
            $contact->categories()->attach($category->id);
        }
    }

    private function ensureDemoContactCategories(Team $team): void
    {
        $moduleId = Module::query()->where('key', 'contacts')->value('id');

        if (! $moduleId)
        {
            return;
        }

        $definitions = [
            ['name' => 'Staff', 'description' => 'Contactos internos del equipo'],
            ['name' => 'Tester', 'description' => 'Contactos de prueba o testing'],
            ['name' => 'Referido', 'description' => 'Contactos referidos por clientes'],
            ['name' => 'Developer', 'description' => 'Desarrolladores o equipo técnico'],
        ];

        foreach ($definitions as $definition)
        {
            Category::query()->updateOrCreate(
                [
                    'name' => $definition['name'],
                    'module_id' => $moduleId,
                    'team_id' => $team->id,
                ],
                [
                    'description' => $definition['description'],
                    'parent_id' => null,
                    'status' => 1,
                ],
            );
        }
    }

    private function ensureAdminTodayInsight(Team $team, ?User $admin): void
    {
        if ($admin === null || ! UserDailyPerformanceInsight::userEligibleForEvaluation($admin))
        {
            return;
        }

        config([
            'daily_performance_insight.use_llm' => false,
            'daily_performance_insight.send_email' => false,
        ]);

        $insightService = app(UserDailyPerformanceInsightService::class);
        $insight = $insightService->ensureTodayRecord($admin, $team, null, now(), true, 'es');

        $digest = app(DailyTeamDigestMetricsCollector::class)->collect($admin, $team, now());
        $highlights = $digest['highlights'] ?? [];

        if ($highlights === [])
        {
            $highlights = [
                'WhatsApp sin leer y emails prioritarios en bandeja demo.',
                'Clientes con facturas vencidas requieren seguimiento hoy.',
                'Agenda con reuniones comerciales confirmadas para esta tarde.',
            ];
        }

        $contactCount = Contact::withoutGlobalScopes()->where('team_id', $team->id)->count();
        $interactionCount = ContactInteraction::query()
            ->whereHas('contact', fn ($q) => $q->where('team_id', $team->id))
            ->where('occurred_at', '>=', now()->subDays(7))
            ->count();

        $insight->update([
            'headline' => 'Prioriza📌',
            'focus' => 'Cerrar seguimientos comerciales pendientes hoy',
            'message' => sprintf(
                'Buen ritmo en el equipo Demo: %d contactos activos y %d interacciones esta semana. Revisa primero los mensajes sin leer y las facturas en rojo; después confirma las reuniones de la agenda. Un bloque de 30 minutos en la lista de contactos calientes te deja el día encaminado.',
                $contactCount,
                $interactionCount,
            ),
            'performance_ratio' => min(92.0, max(55.0, (float) $insight->performance_ratio)),
            'context_snapshot' => array_merge($digest, [
                'highlights' => array_slice($highlights, 0, 4),
                'digest_version' => DailyTeamDigestMetricsCollector::DIGEST_VERSION,
                'insight_source' => 'demo_seeder',
            ]),
        ]);
    }
}
