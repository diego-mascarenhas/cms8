<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Team;
use App\Support\CollectionMessagingGuide;
use Carbon\Carbon;
use Stripe\Invoice;
use Stripe\Stripe;

class CollectionAssistantContextService
{
    public function __construct(
        protected ContactAssistantContextService $contactAssistantContext,
    ) {}

    /**
     * Full collections context: CRM summary (shared) plus Stripe invoices appendix.
     * Prefer using {@see ContactAssistantContextService::buildMarkdownSummary} + {@see buildStripeAppendixForContact} from the chat layer to avoid duplicating the CRM block.
     */
    public function buildMarkdownForContact(int $contactId, int $teamId): string
    {
        $summary = $this->contactAssistantContext->buildMarkdownSummary($contactId, $teamId);
        $stripe = $this->buildStripeAppendixForContact($contactId, $teamId);

        if ($summary === '' && $stripe === '')
        {
            return '';
        }

        if ($summary === '')
        {
            return $stripe;
        }

        if ($stripe === '')
        {
            return $summary;
        }

        return $summary."\n\n---\n\n".$stripe;
    }

    /**
     * Stripe open/uncollectible invoices + links only (for invoices:collections flow).
     */
    public function buildStripeAppendixForContact(int $contactId, int $teamId): string
    {
        $team = Team::query()->find($teamId);
        if (! $team)
        {
            return '';
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->with(['currentEnterprise', 'enterprises'])
            ->find($contactId);

        if (! $contact)
        {
            return '';
        }

        $enterprise = $contact->currentEnterprise ?: $contact->enterprises->first();

        return $this->buildStripeSection($enterprise, $team);
    }

    private function buildStripeSection(?Enterprise $enterprise, Team $team): string
    {
        if (! $enterprise || ! $enterprise->code)
        {
            return '*(No hay Stripe Customer ID en la empresa vinculada al contacto; no se puede cargar el saldo desde Stripe automáticamente.)*';
        }

        $secret = $team->getSetting('stripe_secret');
        if (! $secret)
        {
            return '*(El equipo no tiene configurada la clave secreta de Stripe; no se puede cargar el saldo automáticamente.)*';
        }

        try
        {
            Stripe::setApiKey($secret);

            $openInvoices = Invoice::all([
                'customer' => $enterprise->code,
                'limit' => 20,
                'status' => 'open',
            ]);
            $uncollectibleInvoices = Invoice::all([
                'customer' => $enterprise->code,
                'limit' => 20,
                'status' => 'uncollectible',
            ]);

            $unpaid = [];
            foreach (array_merge($openInvoices->data, $uncollectibleInvoices->data) as $invoice)
            {
                $unpaid[] = [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'amount' => ($invoice->amount_due ?? $invoice->amount_remaining ?? 0) / 100,
                    'currency' => strtoupper((string) $invoice->currency),
                    'status' => $invoice->status,
                    'date' => Carbon::createFromTimestamp($invoice->created)->format('d/m/Y'),
                    'pdf' => $invoice->invoice_pdf,
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'dashboard_url' => 'https://dashboard.stripe.com/invoices/'.$invoice->id,
                ];
            }

            if ($unpaid === [])
            {
                return '**Stripe:** no hay facturas abiertas/incobrables para este cliente.';
            }

            $out = [];
            $out[] = '### Facturación (Stripe, en tiempo real)';
            $out[] = '**Facturas impagas / pendientes:**';
            $out[] = CollectionMessagingGuide::invoiceLinesForContext($unpaid);
            $out[] = '';
            $out[] = '**Links de pago y documentos:**';
            $out[] = CollectionMessagingGuide::paymentLinksForContext($unpaid);

            return implode("\n", $out);
        } catch (\Throwable $e)
        {
            \Illuminate\Support\Facades\Log::warning('CollectionAssistantContextService Stripe fetch failed', [
                'contact_enterprise' => $enterprise->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return '*(No se pudo leer Stripe automáticamente: '.$e->getMessage().')*';
        }
    }
}
