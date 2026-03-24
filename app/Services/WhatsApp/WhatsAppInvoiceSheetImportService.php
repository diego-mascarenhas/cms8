<?php

namespace App\Services\WhatsApp;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports invoice rows from the same CSV shape as tasks, but creates Invoice + InvoiceItem records.
 * Requires a leading line or prefix "invoice.store" so the message is not mistaken for a task import.
 * Rows whose "Cliente" does not match an enterprise in the team are assigned to a placeholder enterprise
 * and saved with status Borrador (9).
 */
class WhatsAppInvoiceSheetImportService
{
    private const MAX_ROWS = 100;

    private const DRAFT_STATUS = 9;

    private const PLACEHOLDER_ENTERPRISE_CODE = '__SHEET_IMPORT_NO_CLIENT__';

    /**
     * If the message is an invoice sheet import (with invoice.store prefix), process it and return the WhatsApp reply; otherwise null.
     */
    public function tryHandle(string $body, ?User $user, int $teamId): ?string
    {
        if ($user === null || $teamId < 1)
        {
            return null;
        }

        if (! $this->bodyHasInvoiceStorePrefix($body))
        {
            return null;
        }

        $normalized = str_replace("\r\n", "\n", trim($body));
        $normalized = str_replace("\r", "\n", $normalized);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $normalized = $this->stripLeadingInvoiceStoreCommand($normalized);

        $parsed = (new WhatsAppConceptoSheetParser)->parse($normalized);
        if ($parsed === null)
        {
            return null;
        }

        if (! $user->teams()->where('teams.id', $teamId)->exists())
        {
            return 'Tu usuario no pertenece a este equipo. No se importaron facturas.';
        }

        if (! $this->userCanImportInvoicesForTeam($user, $teamId))
        {
            return 'No tenés permiso para crear facturas en Humano. Pedile a un administrador del equipo que te asigne el permiso correspondiente.';
        }

        /** @var array<int, array<string, string>> $rows */
        $rows = $parsed['rows'];
        if (count($rows) > self::MAX_ROWS)
        {
            return 'El archivo tiene demasiadas filas (máximo '.self::MAX_ROWS.'). Dividilo en partes más chicas.';
        }

        $typeId = $this->resolveDefaultInvoiceTypeId();
        $draftEnterpriseId = $this->resolveOrCreateDraftEnterpriseId($teamId);

        $createdSummaries = [];
        $draftCount = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $teamId, $typeId, $draftEnterpriseId, &$createdSummaries, &$draftCount, &$errors): void
        {
            foreach ($rows as $index => $row)
            {
                $lineNum = $index + 2;
                try
                {
                    $concepto = trim($row['concepto'] ?? '');
                    if ($concepto === '')
                    {
                        continue;
                    }

                    $clienteLabel = trim($row['cliente'] ?? '');
                    $enterpriseId = $this->resolveEnterpriseIdForCliente($teamId, $clienteLabel);
                    $isDraftClient = $enterpriseId === null;
                    if ($isDraftClient)
                    {
                        $enterpriseId = $draftEnterpriseId;
                        $draftCount++;
                    }

                    [$date, $dueDate] = $this->resolveInvoiceDates($row['fecha_envio'] ?? '');
                    [$gross, $total, $taxPercentage] = $this->resolveAmounts($row);

                    $status = $this->resolveInvoiceStatus($row['estado'] ?? '', $isDraftClient);

                    $number = 'WA-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));

                    $invoice = Invoice::withoutGlobalScopes()->create([
                        'team_id' => $teamId,
                        'enterprise_id' => $enterpriseId,
                        'billing_id' => null,
                        'type_id' => $typeId,
                        'operation' => 'sell',
                        'number' => $number,
                        'date' => $date,
                        'due_date' => $dueDate,
                        'gross_amount' => $gross,
                        'discount' => 0,
                        'total_amount' => $total,
                        'balance' => $total,
                        'status' => $status,
                    ]);

                    $itemDescription = $this->buildItemDescription($row);
                    InvoiceItem::query()->create([
                        'invoice_id' => $invoice->id,
                        'category_id' => null,
                        'description' => $itemDescription,
                        'quantity' => 1,
                        'unit_price' => $gross,
                        'discount' => 0,
                        'tax_percentage' => $taxPercentage,
                    ]);

                    $createdSummaries[] = [
                        'title' => Str::limit($concepto, 80, '…'),
                        'draft' => $isDraftClient || $status === self::DRAFT_STATUS,
                    ];
                } catch (\Throwable $e)
                {
                    $errors[] = "Fila {$lineNum}: ".$e->getMessage();
                }
            }
        });

        if ($createdSummaries === [] && $errors === [])
        {
            return 'No encontré filas con Concepto relleno. Revisá el formato y volvé a enviarlo.';
        }

        $lines = ['✅ Se crearon '.count($createdSummaries).' factura(s) en Humano.'];
        if ($draftCount > 0)
        {
            $lines[] = '• '.$draftCount.' sin cliente reconocido: quedaron en estado Borrador (empresa placeholder del equipo).';
        }
        foreach (array_slice($createdSummaries, 0, 12) as $s)
        {
            $suffix = $s['draft'] ? ' (borrador)' : '';
            $lines[] = '• '.$s['title'].$suffix;
        }
        if (count($createdSummaries) > 12)
        {
            $lines[] = '… y '.(count($createdSummaries) - 12).' más.';
        }
        if ($errors !== [])
        {
            $lines[] = '';
            $lines[] = 'Advertencias:';
            foreach (array_slice($errors, 0, 5) as $err)
            {
                $lines[] = $err;
            }
        }

        return implode("\n", $lines);
    }

    private function userCanImportInvoicesForTeam(User $user, int $teamId): bool
    {
        if ($user->can('invoice.store'))
        {
            return true;
        }

        $team = Team::withoutGlobalScopes()->find($teamId);
        if ($team !== null && (int) $team->user_id === (int) $user->id)
        {
            return true;
        }

        if ($user->hasRole(['admin', 'root']))
        {
            return true;
        }

        $membership = $user->teams()->where('teams.id', $teamId)->first();
        $pivotRole = strtolower((string) ($membership?->pivot?->role ?? ''));

        return in_array($pivotRole, ['admin', 'editor'], true);
    }

    private function bodyHasInvoiceStorePrefix(string $body): bool
    {
        $normalized = str_replace("\r\n", "\n", trim($body));
        $normalized = str_replace("\r", "\n", $normalized);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;

        return (bool) preg_match('/^\s*invoice\.store(?:\s+|\s*$|\s*\R)/iu', $normalized);
    }

    private function stripLeadingInvoiceStoreCommand(string $body): string
    {
        $body = preg_replace('/^\s*invoice\.store(?:\s+|\s*\R)/iu', '', $body) ?? $body;
        $body = preg_replace('/^\s*invoice\.store\s*$/ium', '', $body) ?? $body;

        return trim($body);
    }

    private function resolveDefaultInvoiceTypeId(): int
    {
        $id = \App\Models\InvoiceType::query()->orderBy('id')->value('id');

        return $id !== null ? (int) $id : 1;
    }

    private function resolveOrCreateDraftEnterpriseId(int $teamId): int
    {
        $enterprise = Enterprise::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'code' => self::PLACEHOLDER_ENTERPRISE_CODE,
            ],
            [
                'name' => '[Importación] Sin cliente',
                'type_id' => 1,
                'status_id' => 1,
            ],
        );

        return (int) $enterprise->id;
    }

    private function resolveEnterpriseIdForCliente(int $teamId, string $clienteLabel): ?int
    {
        $n = trim($clienteLabel);
        if ($n === '')
        {
            return null;
        }

        $lower = mb_strtolower($n);

        $byName = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereRaw('LOWER(name) = ?', [$lower])
            ->orderBy('id')
            ->first();

        if ($byName !== null)
        {
            return (int) $byName->id;
        }

        $byCode = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->whereRaw('LOWER(code) = ?', [$lower])
            ->orderBy('id')
            ->first();

        if ($byCode !== null)
        {
            return (int) $byCode->id;
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string} date, due_date
     */
    private function resolveInvoiceDates(string $fechaRaw): array
    {
        $today = now()->toDateString();
        $parsed = $this->parseSpanishDate($fechaRaw);
        if ($parsed === null)
        {
            return [$today, Carbon::parse($today)->addDays(30)->toDateString()];
        }

        return [$parsed, Carbon::parse($parsed)->addDays(30)->toDateString()];
    }

    private function parseSpanishDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '')
        {
            return null;
        }

        foreach (['j/n/Y', 'd/m/Y', 'j-n-Y', 'd-m-Y', 'j/n/y', 'd/m/y'] as $fmt)
        {
            try
            {
                $c = Carbon::createFromFormat($fmt, $raw);
                if ($c instanceof Carbon)
                {
                    return $c->toDateString();
                }
            } catch (\Throwable)
            {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: float, 1: float, 2: float} gross, total, tax_percentage
     */
    private function resolveAmounts(array $row): array
    {
        $gross = $this->parseMoney($row['importe'] ?? '0');
        if ($gross < 0)
        {
            $gross = 0;
        }

        $taxPercentage = $this->parseTaxPercentage($row['iva'] ?? '');
        $total = round($gross * (1 + ($taxPercentage / 100)), 2);

        return [$gross, $total, $taxPercentage];
    }

    private function parseMoney(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '')
        {
            return 0.0;
        }

        $clean = preg_replace('/[^\d,.\-]/', '', $raw) ?? '';
        $clean = str_replace(',', '.', $clean);
        if (preg_match('/^-?\d+(\.\d+)?$/', $clean) !== 1)
        {
            return (float) preg_replace('/[^\d.\-]/', '', $raw);
        }

        return (float) $clean;
    }

    private function parseTaxPercentage(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '')
        {
            return 0.0;
        }

        if (preg_match('/-?\d+([.,]\d+)?/', $raw, $m) === 1)
        {
            return (float) str_replace(',', '.', $m[0]);
        }

        return 0.0;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function buildItemDescription(array $row): string
    {
        $lines = [trim($row['concepto'] ?? '')];
        $propuesta = trim($row['propuesta'] ?? '');
        if ($propuesta !== '')
        {
            $lines[] = 'Propuesta: '.$propuesta;
        }
        $nota = trim($row['nota'] ?? '');
        if ($nota !== '')
        {
            $lines[] = 'Nota: '.$nota;
        }
        $irpf = trim($row['irpf'] ?? '');
        if ($irpf !== '')
        {
            $lines[] = 'IRPF (referencia importación): '.$irpf;
        }

        return implode("\n", array_filter($lines, static fn (string $l): bool => $l !== ''));
    }

    private function resolveInvoiceStatus(string $estadoRaw, bool $forceDraftEnterprise): int
    {
        if ($forceDraftEnterprise)
        {
            return self::DRAFT_STATUS;
        }

        $e = $this->normalizeLabel($estadoRaw);
        if ($e === '')
        {
            return 1;
        }

        if (str_contains($e, 'anul'))
        {
            return 3;
        }
        if (str_contains($e, 'enviad') || str_contains($e, 'impr') || str_contains($e, 'complet') || str_contains($e, 'hecho'))
        {
            return 2;
        }
        if (str_contains($e, 'borrador'))
        {
            return self::DRAFT_STATUS;
        }

        return 1;
    }

    private function normalizeLabel(string $s): string
    {
        $s = mb_strtolower(trim($s));
        static $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return $s;
    }
}
