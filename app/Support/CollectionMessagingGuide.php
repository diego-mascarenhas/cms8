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
    public static function hostingCollectionsPromptDefinition(): array
    {
        return [
            'advisor_notes' => 'No envíes todos los mensajes de una sola vez. Guiá al cliente paso a paso: primero saludo y contexto; luego, si acepta avanzar, explicá la opción recomendada (pago con tarjeta vía Stripe); después compartí el o los links de pago; recién al final ofrecé transferencia bancaria si no puede pagar con tarjeta.',
            'steps' => [
                [
                    'key' => 'open',
                    'title' => 'Paso 1 — Apertura (cordial)',
                    'body' => 'Hola {{CONTACT_FIRST_NAME}}, ¿cómo estás? Te escribo por un tema administrativo: registramos facturas impagas correspondientes al servicio de hosting. Cuando puedas, lo vemos juntos para regularizar la situación de la forma más simple para vos.',
                ],
                [
                    'key' => 'card_preference',
                    'title' => 'Paso 2 — Por qué conviene pagar con tarjeta',
                    'body' => 'Para nosotros es la opción que más nos ayuda porque el proceso queda automatizado: el dinero se acredita directamente en la cuenta correcta, sin depender de transferencias entre titulares de distintas empresas. Si te sirve, podemos hacer el pago con tarjeta de crédito o débito a través de Stripe; así simplificamos y aceleramos todo.',
                ],
                [
                    'key' => 'benefits',
                    'title' => 'Paso 3 — Beneficios por pagar con tarjeta (Stripe)',
                    'body' => "Queremos que este cambio sea beneficioso para vos, por eso sumamos bonificaciones exclusivas al pagar con tarjeta (crédito o débito) vía Stripe:\n- 20% de descuento por los próximos 6 meses.\n- Acceso a herramientas de Email Marketing y WhatsApp Marketing.\n- Horas adicionales de consultoría y soporte tecnológico para potenciar tu experiencia.\n- Soporte Premium bonificado: tiempo de respuesta que no supera las 2 horas.\n\nSi abonás con tarjeta en moneda extranjera (por ejemplo euros), por ser residente en Argentina el descuento es del 30% y es permanente.",
                ],
                [
                    'key' => 'links',
                    'title' => 'Paso 4 — Links de pago y documentación',
                    'body' => "Detalle de facturas impagas y montos:\n{{UNPAID_INVOICES_LIST}}\n\nLinks para pagar o revisar en Stripe (hosted invoice / PDF cuando aplique):\n{{PAYMENT_LINKS}}",
                ],
                [
                    'key' => 'suspended',
                    'title' => 'Paso 5 — Servicio suspendido',
                    'body' => 'Te comento que el servicio se encuentra suspendido por la deuda. Al regularizar con tarjeta de crédito, la restauración no debería demorar más de 15 minutos desde el pago acreditado.',
                    'only_if_suspended' => true,
                ],
                [
                    'key' => 'transfer_last',
                    'title' => 'Última instancia — Transferencia bancaria',
                    'body' => '{{BANK_TRANSFER_BLOCK}}',
                ],
                [
                    'key' => 'transfer_delay',
                    'title' => 'Plazos según medio de pago',
                    'body' => 'Si pagás por transferencia bancaria, la activación puede demorar hasta 24 horas hábiles desde que impacta el pago y lo verificamos. Con tarjeta el proceso suele ser inmediato en la plataforma.',
                ],
            ],
        ];
    }

    /**
     * Persist hosting collection JSON prompt to module_prompts (invoices / collections).
     * Used by TeamRevisionAlphaSeeder and optional migrations.
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

        $definition = self::hostingCollectionsPromptDefinition();

        Prompt::withoutGlobalScope('team')->updateOrCreate(
            [
                'team_id' => $teamId,
                'module_id' => $module->id,
                'section_key' => 'collections',
            ],
            [
                'section_label' => 'Cobranzas hosting (Stripe, AR)',
                'prompt_instruction' => json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'helper_text' => 'Saldo: JSON con advisor_notes y steps. Placeholders: {{CONTACT_FIRST_NAME}}, {{UNPAID_INVOICES_LIST}}, {{PAYMENT_LINKS}}, {{BANK_TRANSFER_BLOCK}}. Paso suspendido: only_if_suspended: true.',
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

        return self::hostingCollectionsPromptDefinition();
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
