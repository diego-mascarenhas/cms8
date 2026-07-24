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

    public function test_suggests_four_equal_amount_invoices_matching_payment(): void
    {
        $service = new MercadoPagoInvoiceSuggestionService;

        $invoices = new Collection([
            $this->fakeInvoice(1, '0005-A', 51235.0),
            $this->fakeInvoice(2, '0005-B', 51235.0),
            $this->fakeInvoice(3, '0005-C', 51235.0),
            $this->fakeInvoice(4, '0005-D', 51235.0),
            $this->fakeInvoice(5, '0005-E', 100.0),
        ]);

        $suggestions = $service->suggest($invoices, 204940.0);
        $ids = array_map(fn (array $row) => $row['invoice_ids'], $suggestions);

        $this->assertContains([1, 2, 3, 4], $ids);
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
