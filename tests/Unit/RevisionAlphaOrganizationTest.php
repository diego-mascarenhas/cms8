<?php

namespace Tests\Unit;

use App\Support\RevisionAlphaOrganization;
use Tests\TestCase;

class RevisionAlphaOrganizationTest extends TestCase
{
    public function test_catalog_covers_advertising_media_and_product(): void
    {
        $keys = array_keys(RevisionAlphaOrganization::departments());

        $this->assertContains('advertising', $keys);
        $this->assertContains('media', $keys);
        $this->assertContains('product', $keys);
        $this->assertContains('administration', $keys);
    }

    public function test_affiliate_split_is_thirty_for_agency_and_ten_for_advisor(): void
    {
        $this->assertSame(30, RevisionAlphaOrganization::AGENCY_PERCENT);
        $this->assertSame(10, RevisionAlphaOrganization::ADVISOR_PERCENT);
    }

    public function test_leticia_friday_puts_invoices_then_accounting_before_publication_plan(): void
    {
        $data = RevisionAlphaOrganization::viewData();
        $friday = $data['week_by_person']['leticia']['grid']['days'][5];
        $names = array_column($friday, 'name');

        $this->assertSame([
            'Emails y tickets',
            'Subir facturas al sistema',
            'Cierre contable de la semana',
            'Plan de publicaciones',
        ], $names);
        $this->assertSame('19:00', $friday[1]['starts_at']);
        $this->assertSame('20:00', $friday[1]['ends_at']);
        $this->assertSame('20:00', $friday[2]['starts_at']);
        $this->assertSame('21:00', $friday[2]['ends_at']);
        $this->assertSame('21:00', $friday[3]['starts_at']);
        $this->assertSame('23:00', $friday[3]['ends_at']);
    }

    public function test_magoo_weekday_blocks_match_support_inbox_and_coding_hours(): void
    {
        $data = RevisionAlphaOrganization::viewData();
        $magoo = $data['week_by_person']['magoo'];

        $this->assertSame('09:30', $magoo['person']['work_starts_at']);
        $this->assertSame('20:00', $magoo['person']['work_ends_at']);

        $monday = array_column($magoo['grid']['days'][1], 'name');
        $tuesday = array_column($magoo['grid']['days'][2], 'name');
        $wednesday = array_map(
            fn (array $b): string => $b['name'].' '.$b['starts_at'].'-'.$b['ends_at'],
            $magoo['grid']['days'][3],
        );
        $thursday = array_map(fn (array $b): string => $b['name'].' '.$b['starts_at'].'-'.$b['ends_at'], $magoo['grid']['days'][4]);
        $friday = array_map(fn (array $b): string => $b['name'].' '.$b['ends_at'], $magoo['grid']['days'][5]);

        $this->assertSame(['Aprobar publicaciones', 'Emails y WhatsApp', 'Programar'], $monday);
        $this->assertSame(['Academia de inglés', 'Programar'], $tuesday);
        $this->assertSame([
            'Soporte y migraciones 09:30-11:00',
            'Emails y WhatsApp 11:00-12:00',
            'Programar 12:00-13:00',
            'Master Mind 13:00-14:00',
            'Programar 14:00-16:00',
        ], $wednesday);
        $this->assertContains('Generar publicidad 12:00-14:00', $thursday);
        $this->assertContains('Programar 15:00', $friday);
        $this->assertSame([], $magoo['grid']['days'][6]);

        $this->assertSame('09:30', $magoo['grid']['days'][1][0]['starts_at']);
        $this->assertSame('11:00', $magoo['grid']['days'][1][0]['ends_at']);
        $this->assertTrue($magoo['grid']['days'][3][3]['is_blocker']);
    }

    public function test_personal_blocks_do_not_count_as_work_hours(): void
    {
        $processes = collect(RevisionAlphaOrganization::processes())
            ->keyBy('key');

        $this->assertFalse($processes['personal-english']['counts_as_work']);
        $this->assertFalse($processes['personal-mastermind']['counts_as_work']);
        $this->assertSame(0.0, $processes['personal-english']['hours_per_month']);
        $this->assertSame(0.0, $processes['personal-mastermind']['hours_per_month']);
    }

    public function test_coverage_grid_is_twenty_four_by_seven_and_marks_gaps(): void
    {
        $data = RevisionAlphaOrganization::viewData();
        $coverage = $data['coverage_grid'];

        $this->assertCount(24, $coverage['hours']);
        $this->assertSame('00:00', $coverage['hours'][0]);
        $this->assertSame('23:00', $coverage['hours'][23]);
        $this->assertCount(7, $coverage['days']);
        $this->assertGreaterThan(100, $coverage['gap_hours_per_week']);

        $mondayNine = $coverage['days'][1]['09:00'];
        $this->assertTrue($mondayNine['covered']);
        $this->assertSame('Magoo', $mondayNine['covers'][0]['person']);

        $tuesdayTen = $coverage['days'][2]['10:00'];
        $this->assertFalse($tuesdayTen['covered']);

        $sundayNoon = $coverage['days'][7]['12:00'];
        $this->assertFalse($sundayNoon['covered']);
    }

    public function test_hour_overlap_includes_half_hour_starts(): void
    {
        $this->assertTrue(RevisionAlphaOrganization::hourOverlapsBlock('18:00', '18:30', '19:30'));
        $this->assertFalse(RevisionAlphaOrganization::hourOverlapsBlock('17:00', '18:30', '19:30'));
        $this->assertTrue(RevisionAlphaOrganization::hourOverlapsBlock('09:00', '09:30', '11:00'));
    }

    public function test_retired_administration_processes_are_not_in_the_catalog(): void
    {
        $keys = array_column(RevisionAlphaOrganization::processes(), 'key');

        $this->assertNotContains('admin-debit-create', $keys);
        $this->assertNotContains('admin-debit-send', $keys);
        $this->assertNotContains('admin-debit-receive', $keys);
        $this->assertNotContains('admin-pay-suppliers', $keys);
    }
}
