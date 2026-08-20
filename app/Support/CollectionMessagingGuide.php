<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;

class CollectionMessagingGuide
{
    /**
     * JSON structure stored in module_prompts.prompt_instruction (invoices / collections).
     *
     * @return array{
     *     advisor_notes: string,
     *     steps: list<array{key: string, title: string, body: string, only_if_suspended?: bool}>
     * }
     */
    public static function collectionsPromptDefinition(): array
    {
        return [
            'advisor_notes' => 'No envíes todos los mensajes de una sola vez. Primero identificá al contacto y leé las facturas impagas. Saludá, mostrá el saldo real y pasá el link de cada invoice. No inventes importes ni URLs. Transferencia solo si el equipo tiene datos bancarios y el cliente no puede pagar la factura.',
            'steps' => [
                [
                    'key' => 'open',
                    'title' => 'Paso 1 — Apertura',
                    'body' => 'Hola {{CONTACT_FIRST_NAME}}, te escribo por las facturas pendientes. Cuando puedas lo vemos y lo regularizamos por el link de cada invoice.',
                ],
                [
                    'key' => 'invoices',
                    'title' => 'Paso 2 — Facturas impagas',
                    'body' => "Este es el detalle que figura en invoices:\n{{UNPAID_INVOICES_LIST}}",
                ],
                [
                    'key' => 'links',
                    'title' => 'Paso 3 — Cómo pagar',
                    'body' => "Links de pago o PDF de cada factura:\n{{PAYMENT_LINKS}}",
                ],
                [
                    'key' => 'suspended',
                    'title' => 'Paso 4 — Servicio suspendido o en riesgo',
                    'body' => 'El servicio figura suspendido o en mora por esas facturas. Al pagar por el link de la invoice, la reactivación suele ser inmediata cuando el cobro se acredita.',
                    'only_if_suspended' => true,
                ],
                [
                    'key' => 'transfer_last',
                    'title' => 'Última instancia — Transferencia',
                    'body' => '{{BANK_TRANSFER_BLOCK}}',
                ],
            ],
        ];
    }

    /**
     * @deprecated Use {@see collectionsPromptDefinition()}
     *
     * @return array{advisor_notes: string, steps: list<array{key: string, title: string, body: string, only_if_suspended?: bool}>}
     */
    public static function hostingCollectionsPromptDefinition(): array
    {
        return self::collectionsPromptDefinition();
    }

    /**
     * Instruction for the invoices:collections assistant flow. Looks up the contact and uses
     * real invoice data from context (Stripe appendix). Do not invent amounts or URLs.
     */
    public static function collectionsAssistantInstruction(): string
    {
        return <<<'PROMPT'
# Flujo: cobranzas

Cobrás saldos pendientes. Los importes, vencimientos y links salen de las **facturas (invoices)** del contacto. Nunca los inventes.

## Cómo arrancar

1. Si no hay ficha en contexto, **search_contacts** por nombre, teléfono o email. Si hay varios, pedí un dato para desambiguar y usá **get_contact_detail**.
2. Leé el bloque de invoices impagas del contexto. Si no hay facturas, decilo en una frase: no hay saldo en invoices para esa persona.
3. Redactá o enviá el recordatorio solo con esos datos: número, importe, moneda, vencimiento y link de pago.

## Mensaje al cliente

- Un canal por turno: WhatsApp corto, o email con asunto.
- Tono firme y respetuoso. Una sola llamada a la acción: pagar por el link de la factura.
- Si hay varias invoices, listalas y pasá cada link. No inventes URLs.
- Transferencia solo si el operador la pide y el contexto trae datos bancarios del equipo.

## Herramientas

- search_contacts, get_contact_detail
- send_whatsapp_message cuando el operador pida enviarlo (no en vista previa)
- create_contact_interaction para dejar constancia de la gestión

## Límites

- No inventes importes, moneda, número de factura, fechas ni URLs.
- No amenaces con juicios ni cortes de servicio salvo que el operador lo pida y el contexto lo confirme (por ejemplo suscripción en mora).
PROMPT;
    }

    /**
     * Persist the generic collections flow on invoices:collections.
     */
    public static function syncHostingCollectionsPromptForTeam(int $teamId): bool
    {
        $module = Module::query()->where('key', 'invoices')->first();
        if (! $module)
        {
            return false;
        }

        if (! Team::query()->whereKey($teamId)->exists())
        {
            return false;
        }

        Prompt::withoutGlobalScope('team')->updateOrCreate(
            [
                'team_id' => $teamId,
                'module_id' => $module->id,
                'section_key' => 'collections',
            ],
            [
                'section_label' => 'Cobranzas',
                'prompt_instruction' => self::collectionsAssistantInstruction(),
                'helper_text' => 'Cobranzas: buscar el contacto y usar las facturas (invoices) reales. No inventes importes ni links.',
                'order' => 1,
                'is_active' => true,
            ],
        );

        return true;
    }

    /**
     * Remove hosting collection prompt row for rollback / tests.
     */
    public static function deleteHostingCollectionsPromptForTeam(int $teamId): void
    {
        $module = Module::query()->where('key', 'invoices')->first();
        if (! $module)
        {
            return;
        }

        Prompt::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('module_id', $module->id)
            ->where('section_key', 'collections')
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $stripeData
     * @return array{
     *     advisor_notes: string,
     *     steps: list<array{key: string, title: string, body: string}>,
     *     bank_transfer_block: string,
     *     full_copy: string
     * }|null
     */
    public static function build(Contact $contact, array $stripeData, ?int $teamId = null): ?array
    {
        $unpaid = $stripeData['unpaid_invoices'] ?? [];
        if ($unpaid === [])
        {
            return null;
        }

        $firstName = trim(explode(' ', (string) $contact->name, 2)[0]);
        $serviceSuspended = self::subscriptionLooksSuspended($stripeData['subscriptions'] ?? []);

        $lines = self::invoiceLines($unpaid);
        $paymentLinks = self::paymentLinks($unpaid);
        $bankBlock = self::bankTransferBodyForTeam($teamId);

        $definition = self::resolvePromptDefinition($teamId);
        $advisorNotes = $definition['advisor_notes'];
        $rawSteps = $definition['steps'];

        $steps = [];
        foreach ($rawSteps as $step)
        {
            if (! empty($step['only_if_suspended']) && ! $serviceSuspended)
            {
                continue;
            }

            $body = (string) $step['body'];
            $body = str_replace(
                '{{CONTACT_FIRST_NAME}}',
                $firstName,
                $body,
            );
            $body = str_replace('{{UNPAID_INVOICES_LIST}}', $lines, $body);
            $body = str_replace('{{PAYMENT_LINKS}}', $paymentLinks, $body);
            $body = str_replace('{{BANK_TRANSFER_BLOCK}}', $bankBlock, $body);
            $body = self::trimParagraph($body);

            $steps[] = [
                'key' => (string) $step['key'],
                'title' => (string) $step['title'],
                'body' => $body,
            ];
        }

        $fullCopy = $advisorNotes."\n\n"
            .implode("\n\n---\n\n", array_map(static fn (array $s): string => $s['title']."\n\n".$s['body'], $steps));

        return [
            'advisor_notes' => $advisorNotes,
            'steps' => $steps,
            'bank_transfer_block' => $bankBlock,
            'full_copy' => $fullCopy,
        ];
    }

    /**
     * @return array{advisor_notes: string, steps: list<array<string, mixed>>}
     */
    protected static function resolvePromptDefinition(?int $teamId): array
    {
        if ($teamId && $teamId > 0)
        {
            $prompt = Prompt::forTeam($teamId)
                ->forModule('invoices')
                ->where('section_key', 'collections')
                ->where('is_active', true)
                ->first();

            if ($prompt && trim($prompt->prompt_instruction) !== '')
            {
                $decoded = json_decode($prompt->prompt_instruction, true);
                if (is_array($decoded) && isset($decoded['advisor_notes'], $decoded['steps']) && is_array($decoded['steps']))
                {
                    return [
                        'advisor_notes' => (string) $decoded['advisor_notes'],
                        'steps' => $decoded['steps'],
                    ];
                }
            }
        }

        return self::collectionsPromptDefinition();
    }

    /**
     * @param  list<array<string, mixed>>  $subscriptions
     */
    protected static function subscriptionLooksSuspended(array $subscriptions): bool
    {
        foreach ($subscriptions as $sub)
        {
            $status = $sub['status'] ?? '';
            if (in_array($status, ['past_due', 'unpaid'], true))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $unpaid
     */
    protected static function invoiceLines(array $unpaid): string
    {
        $out = [];
        foreach ($unpaid as $inv)
        {
            $num = $inv['number'] ?? $inv['id'] ?? '—';
            $date = $inv['date'] ?? '—';
            $amount = $inv['amount'] ?? '—';
            $currency = $inv['currency'] ?? '';
            $out[] = '• Factura '.$num.' — '.$date.' — '.$amount.' '.$currency;
        }

        return implode("\n", $out);
    }

    /**
     * @param  list<array<string, mixed>>  $unpaid
     */
    protected static function paymentLinks(array $unpaid): string
    {
        $lines = [];
        foreach ($unpaid as $inv)
        {
            $num = $inv['number'] ?? $inv['id'] ?? '—';
            $hosted = $inv['hosted_invoice_url'] ?? null;
            $pdf = $inv['pdf'] ?? null;
            $dash = $inv['dashboard_url'] ?? null;
            $parts = [];
            if ($hosted)
            {
                $parts[] = 'Link de pago: '.$hosted;
            }
            if ($pdf)
            {
                $parts[] = 'PDF: '.$pdf;
            }
            if ($dash)
            {
                $parts[] = 'Panel Stripe: '.$dash;
            }
            if ($parts === [])
            {
                $lines[] = '• Factura '.$num.': (sin links en la respuesta de Stripe; revisá el dashboard).';
            } else
            {
                $lines[] = '• Factura '.$num.":\n  ".implode("\n  ", $parts);
            }
        }

        return implode("\n\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $unpaid
     */
    public static function invoiceLinesForContext(array $unpaid): string
    {
        return self::invoiceLines($unpaid);
    }

    /**
     * @param  list<array<string, mixed>>  $unpaid
     */
    public static function paymentLinksForContext(array $unpaid): string
    {
        return self::paymentLinks($unpaid);
    }

    /**
     * Bank details from team setting `collection_bank_transfer` (JSON), seeded / editable per team.
     */
    protected static function bankTransferBodyForTeam(?int $teamId): string
    {
        $bank = [];

        if ($teamId && $teamId > 0)
        {
            $team = Team::query()->find($teamId);
            if ($team)
            {
                $stored = $team->getSetting('collection_bank_transfer');
                if (is_array($stored))
                {
                    $bank = array_filter(
                        $stored,
                        static fn ($value): bool => $value !== null && $value !== '',
                    );
                }
            }
        }

        $holder = $bank['account_holder'] ?? '';
        $cuit = $bank['cuit'] ?? '';
        $cbu = $bank['cbu'] ?? '';
        $alias = $bank['alias'] ?? '';

        return self::trimParagraph(
            'Si no podés usar tarjeta, como última opción podés abonar por transferencia. Datos:'
            ."\n\n"
            .'Titular: '.$holder."\n"
            .'CUIT/CUIL: '.$cuit."\n"
            .'CBU: '.$cbu."\n"
            .'Alias: '.$alias."\n\n"
            .'Enviá el comprobante por este mismo canal para acelerar la imputación.',
        );
    }

    protected static function trimParagraph(string $text): string
    {
        return trim(preg_replace("/[ \t]+/u", ' ', $text));
    }
}
