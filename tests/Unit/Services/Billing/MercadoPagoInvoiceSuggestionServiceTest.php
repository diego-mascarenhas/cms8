<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Invoice;
use App\Services\Billing\MercadoPagoInvoiceSuggestionService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MercadoPagoInvoiceSuggestionServiceTest extends TestCase
{
    public function test_suggests_exact_and_combo_matches(): void
    {
        $service = new MercadoPagoInvoiceSuggestionService;

        $invoices = new Collection([
            $this->fakeInvoice(1, 'A-1', 1000),
            $this->fakeInvoice(2, 'A-2', 500),
            $this->fakeInvoice(3, 'A-3', 1500),
            $this->fakeInvoice(4, 'A-4', 200),
        ]);

        $suggestions = $service->suggest($invoices, 1500.0);

        $ids = array_map(fn (array $row) => $row['invoice_ids'], $suggestions);

        $this->assertContains([3], $ids);
        $this->assertContains([1, 2], $ids);
    }

    private function fakeInvoice(int $id, string $number, float $balance): Invoice
    {
        $invoice = new Invoice;
        $invoice->id = $id;
        $invoice->number = $number;
        $invoice->balance = $balance;
        $invoice->date = now();

        return $invoice;
    }
}
