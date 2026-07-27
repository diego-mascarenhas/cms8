<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class MercadoPagoSettlementEnrichScheduleTest extends TestCase
{
    public function test_settlement_enrich_schedule_runs_when_enabled(): void
    {
        config(['services.mercadopago.settlement_enrich_schedule_enabled' => true]);

        $event = $this->settlementEnrichScheduleEvent();

        $this->assertNotNull($event);
        $this->assertTrue($event->filtersPass($this->app));
    }

    public function test_settlement_enrich_schedule_skips_when_disabled(): void
    {
        config(['services.mercadopago.settlement_enrich_schedule_enabled' => false]);

        $event = $this->settlementEnrichScheduleEvent();

        $this->assertNotNull($event);
        $this->assertFalse($event->filtersPass($this->app));
    }

    private function settlementEnrichScheduleEvent(): mixed
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event)
        {
            if (($event->description ?? null) === 'mercadopago:enrich-settlement-payers --recent-days=90 --poll=120'
                || str_contains((string) $event->command, 'mercadopago:enrich-settlement-payers'))
            {
                return $event;
            }
        }

        return null;
    }
}
