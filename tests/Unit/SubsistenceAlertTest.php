<?php

namespace Tests\Unit;

use App\Services\SubsistenceAlertService;
use App\Support\RevisionAlphaOrganization;
use Carbon\Carbon;
use Tests\TestCase;

class SubsistenceAlertTest extends TestCase
{
    public function test_missed_call_days_turn_marketing_and_conversion_red(): void
    {
        $catalog = RevisionAlphaOrganization::subsistenceCatalog();
        $leticia = collect($catalog['people'])->firstWhere('key', 'leticia');
        $friday = Carbon::parse('2026-10-09 10:00:00', 'Europe/Madrid');

        $banners = collect((new SubsistenceAlertService)->assess($catalog, [
            'calls_by_date' => [],
            'open_leads' => 4,
            'leads_entered_7d' => 1,
            'clients_created_30d' => 0,
            'publications_next_7d' => 0,
        ], $friday, 28, 12, 'EUR')['banners'])->keyBy('key');

        $this->assertSame('danger', $banners['calls']['level']);
        $this->assertSame('danger', $banners['marketing']['level']);
        $this->assertSame('danger', $banners['conversion']['level']);
        $this->assertSame('danger', $banners['salary-leticia']['level']);
        $this->assertStringContainsString('Hoy no hay franja de llamados.', $banners['calls']['body']);
        $this->assertSame('danger', $banners['cushion']['level']);
        $this->assertSame('danger', $banners['capital']['level']);
        $survival = (float) $leticia['calls_hours'] + (float) $leticia['marketing_hours'] + (float) $leticia['conversion_hours'];
        $this->assertStringContainsString(
            number_format(round(12 * $survival, 2), 2, ',', '.'),
            $banners['salary-leticia']['title'],
        );
        $this->assertStringContainsString(
            number_format(round(12 * $leticia['assigned_hours'], 2), 2, ',', '.'),
            $banners['salary-leticia']['body'],
        );
    }

    public function test_a_covered_call_hour_with_a_publication_and_a_new_client_is_green(): void
    {
        $catalog = RevisionAlphaOrganization::subsistenceCatalog();
        $mondayNight = Carbon::parse('2026-10-05 21:00:00', 'Europe/Madrid');

        $banners = collect((new SubsistenceAlertService)->assess($catalog, [
            'calls_by_date' => ['2026-10-05' => 6],
            'open_leads' => 2,
            'leads_entered_7d' => 2,
            'clients_created_30d' => 1,
            'publications_next_7d' => 1,
        ], $mondayNight, 28, 12, 'EUR')['banners'])->keyBy('key');

        $this->assertSame('success', $banners['calls']['level']);
        $this->assertSame('success', $banners['marketing']['level']);
        $this->assertSame('success', $banners['conversion']['level']);
        $this->assertSame('danger', $banners['salary-leticia']['level']);
        $this->assertSame('danger', $banners['cushion']['level']);
        $this->assertStringContainsString('Hoy van 6 de 6.', $banners['calls']['body']);
    }

    public function test_zero_calls_inside_the_window_is_red(): void
    {
        $catalog = RevisionAlphaOrganization::subsistenceCatalog();
        $during = Carbon::parse('2026-10-05 19:20:00', 'Europe/Madrid');

        $banners = collect((new SubsistenceAlertService)->assess($catalog, [
            'calls_by_date' => [],
            'open_leads' => 3,
            'leads_entered_7d' => 0,
            'clients_created_30d' => 0,
            'publications_next_7d' => 0,
        ], $during, 28, 12, 'EUR')['banners'])->keyBy('key');

        $this->assertSame('danger', $banners['calls']['level']);
        $this->assertStringContainsString('Emails y tickets 18:30–19:00', $banners['calls']['body']);
        $this->assertStringContainsString('Plan de marketing 20:00–23:00', $banners['calls']['body']);
    }

    public function test_the_full_role_salary_starts_once_the_cushion_covers_six_months(): void
    {
        $catalog = RevisionAlphaOrganization::subsistenceCatalog();
        $leticia = collect($catalog['people'])->firstWhere('key', 'leticia');
        $mondayNight = Carbon::parse('2026-10-05 21:00:00', 'Europe/Madrid');

        $banners = collect((new SubsistenceAlertService)->assess($catalog, [
            'calls_by_date' => ['2026-10-05' => 6],
            'open_leads' => 0,
            'leads_entered_7d' => 0,
            'clients_created_30d' => 1,
            'publications_next_7d' => 1,
            'cash' => 1000000,
            'monthly_operating_expense' => 1000,
            'share_capital' => 3000,
            'share_capital_minimum' => 3000,
            'cushion_months' => 6,
        ], $mondayNight, 28, 12, 'EUR')['banners'])->keyBy('key');

        $this->assertSame('success', $banners['cushion']['level']);
        $this->assertSame('danger', $banners['capital']['level']);
        $this->assertSame('success', $banners['salary-leticia']['level']);
        $this->assertStringContainsString(
            number_format(round(12 * (float) $leticia['assigned_hours'], 2), 2, ',', '.'),
            $banners['salary-leticia']['title'],
        );
        $this->assertStringContainsString('fianza', $banners['capital']['body']);
    }
}
