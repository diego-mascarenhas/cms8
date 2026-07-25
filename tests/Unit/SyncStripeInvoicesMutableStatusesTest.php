<?php

namespace Tests\Unit;

use App\Console\Commands\SyncStripeInvoicesCommand;
use PHPUnit\Framework\TestCase;

class SyncStripeInvoicesMutableStatusesTest extends TestCase
{
    public function test_mutable_invoice_statuses_include_void(): void
    {
        $statuses = SyncStripeInvoicesCommand::mutableInvoiceStatuses();

        $this->assertSame(
            ['draft', 'open', 'paid', 'uncollectible', 'void'],
            $statuses,
        );
    }
}
