<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Country;
use App\Models\Enterprise;
use App\Models\Team;
use App\Support\CollectionMessagingGuide;
use Carbon\Carbon;
use Stripe\Invoice;
use Stripe\Stripe;

class CollectionAssistantContextService
{
    /**
     * Markdown block appended to the assistant system prompt for invoices:collections when a contact is linked.
     * Includes CRM fields and, when possible, Stripe open/uncollectible invoices and payment links.
     */
    public function buildMarkdownForContact(int $contactId, int $teamId): string
    {
        $team = Team::query()->find($teamId);
        if (! $team)
        {
            return '';
        }

        $contact = Contact::query()
            ->where('team_id', $teamId)
            ->with(['currentEnterprise', 'enterprises'])
            ->find($contactId);

        if (! $contact)
        {
            return '';
        }

        $lines = [];
        $lines[] = '### Contexto del cliente (CRM — datos ya cargados)';
        $lines[] = 'Usá esta información para redactar mensajes de cobranza. **No pidas** al operador que pegue ID de contacto, nombre del cliente ni Stripe Customer ID si ya figuran aquí.';
        $lines[] = '';
        $lines[] = '- **ID contacto:** '.(string) $contact->id;
        $lines[] = '- **Nombre:** '.($contact->name ?: '—');
        $lines[] = '- **Email:** '.($contact->email ?: '—');
        $lines[] = '- **Teléfono:** '.($contact->phone ?: '—');

        $enterprise = $contact->currentEnterprise ?: $contact->enterprises->first();
        if ($enterprise)
        {
            $lines[] = '- **Empresa:** '.($enterprise->name ?: '—');
            $lines[] = '- **Stripe Customer ID:** '.($enterprise->code ?: '—');
        }

        $countryId = $contact->getAttribute('country');
        if ($countryId)
        {
            $countryName = Country::query()->find((int) $countryId)?->name;
            if ($countryName)
            {
                $lines[] = '- **País:** '.$countryName;
            }
        }

        $stripeAppend = $this->buildStripeSection($enterprise, $team);
        if ($stripeAppend !== '')
        {
            $lines[] = '';
            $lines[] = $stripeAppend;
        }

        return implode("\n", $lines);
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
