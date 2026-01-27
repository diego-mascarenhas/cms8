<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPaymentsCommand extends Command
{
    protected $signature = 'import:payments';

    protected $description = 'Import payments (movimientos) from legacy database';

    public function handle(): int
    {
        $this->info('💰 Importing payments from remote database...');

        $stats = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'message' => null,
        ];

        try
        {
            // Verify payments table exists
            if (! \Illuminate\Support\Facades\Schema::hasTable('payments'))
            {
                $this->warn('⚠️  Payments table does not exist. Skipping payment import.');
                $this->info('   Run: php artisan vendor:publish --tag="humano-billing-migrations" && php artisan migrate');

                return Command::FAILURE;
            }

            // Test connection
            DB::connection('mysql_legacy')->getPdo();

            // Get the CMS group
            $cmsGroup = env('CMS_GROUP', 502);
            $this->info("   Using CMS_GROUP: {$cmsGroup}");

            // Use team_id directly
            $teamId = env('CMS_TEAM_ID', 2);

            // Payment type mapping from legacy to new IDs
            $paymentTypeMap = [
                1 => 1,  // Cash
                2 => 2,  // Bank Transfer
                3 => 3,  // Bank Deposit
                4 => 4,  // Check
                5 => 5,  // Debit
                10 => 6,  // Credit Card
                7 => 7,  // PayPal
                17 => 8,  // Stripe
                6 => 12,  // MercadoPago
                13 => 12,  // MercadoPago
                14 => 12,  // MercadoPago
            ];

            $query = DB::connection('mysql_legacy')
                ->table('movimientos')
                ->leftJoin('facturas', 'movimientos.id_factura', '=', 'facturas.id')
                ->leftJoin('empresas_fiscales', 'facturas.id_empresa_fiscal', '=', 'empresas_fiscales.id')
                ->where('movimientos.grupo', $cmsGroup)
                ->where('movimientos.estado', '>', 0)
                ->where(function ($q)
                {
                    // Si tiene factura, la factura debe tener estado > 0
                    // Si no tiene factura, permitir el pago
                    $q
                        ->whereNull('movimientos.id_factura')
                        ->orWhere('facturas.estado', '>', 0);
                })
                ->select(
                    'movimientos.*',
                    'empresas_fiscales.id_empresa as enterprise_id',
                    'facturas.id_empresa_fiscal',
                );

            $payments = $query->get();

            if ($payments->isEmpty())
            {
                $stats['message'] = 'No payments found matching the criteria.';
                $this->warn($stats['message']);

                return Command::SUCCESS;
            }

            $this->info("   Found {$payments->count()} payments to import");
            $bar = $this->output->createProgressBar($payments->count());
            $bar->start();

            // Get default account for team, create if doesn't exist
            $defaultTeamAccount = DB::table('payment_accounts')->where('team_id', $teamId)->first();
            
            if (!$defaultTeamAccount)
            {
                $this->warn("   ⚠️  No payment account found for team {$teamId}, creating default...");
                
                // Create a default payment account
                $defaultAccountId = DB::table('payment_accounts')->insertGetId([
                    'team_id' => $teamId,
                    'code' => 'DEFAULT',
                    'name' => 'Cuenta por Defecto',
                    'symbol' => '$',
                    'currency_id' => 840, // USD
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $defaultTeamAccount = DB::table('payment_accounts')->where('id', $defaultAccountId)->first();
                $this->info("   ✅ Created default payment account (ID: {$defaultAccountId})");
            }

            $skipped = 0;
            foreach ($payments as $payment)
            {
                try
                {
                    // Get account ID - if not exists, use default account for this team
                    $accountId = $payment->id_cuenta;
                    if (! $accountId || ! DB::table('payment_accounts')->where('id', $accountId)->exists())
                    {
                        // Use the default team account
                        $accountId = $defaultTeamAccount->id;
                    }

                    // Map legacy payment type ID to new ID
                    $legacyTypeId = $payment->id_forma_pago ?? 1;
                    $typeId = $paymentTypeMap[$legacyTypeId] ?? 1;  // Default to Cash if not mapped

                    // Determine transaction type: I=Income, E=Expense (default to expense if unknown)
                    $transactionType = 'expense';
                    if (isset($payment->transaccion))
                    {
                        $transactionType = strtoupper($payment->transaccion) === 'I' ? 'income' : 'expense';
                    }

                    // Get amount from 'valor' field
                    $amount = $payment->valor ?? 0;

                    // Get enterprise_id from multiple sources
                    $enterpriseId = null;

                    // 1. Try from the JOIN result
                    if ($payment->enterprise_id)
                    {
                        if (DB::table('enterprises')->where('id', $payment->enterprise_id)->exists())
                        {
                            $enterpriseId = $payment->enterprise_id;
                        }
                    }

                    // 2. If still null, try to get from invoice
                    $invoiceId = $payment->id_factura;
                    if (! $enterpriseId && $invoiceId)
                    {
                        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
                        if ($invoice && $invoice->enterprise_id)
                        {
                            $enterpriseId = $invoice->enterprise_id;
                        }
                        // If invoice doesn't exist, set invoiceId to null
                        if (! $invoice)
                        {
                            $invoiceId = null;
                        }
                    }

                    // 3. If still null and we have id_empresa_fiscal, try to find the enterprise
                    if (! $enterpriseId && isset($payment->id_empresa_fiscal))
                    {
                        $enterpriseFromFiscal = DB::table('enterprises')
                            ->where('id', $payment->id_empresa_fiscal)
                            ->where('team_id', $teamId)
                            ->first();
                        if ($enterpriseFromFiscal)
                        {
                            $enterpriseId = $enterpriseFromFiscal->id;
                        }
                    }

                    $existingPayment = Payment::withoutGlobalScopes()->where('id', $payment->id)->first();

                    $paymentData = [
                        'team_id' => $teamId,
                        'enterprise_id' => $enterpriseId,
                        'invoice_id' => $invoiceId,
                        'transaction_type' => $transactionType,
                        'date' => $payment->fecha ? \Carbon\Carbon::parse($payment->fecha)->format('Y-m-d') : now()->format('Y-m-d'),
                        'amount' => $amount,
                        'type_id' => $typeId,
                        'account_id' => $accountId,
                        'remarks' => $payment->observaciones ?? null,
                        'status' => $payment->estado ?? 1,
                        'created_at' => $payment->fecha_alta ?? now(),
                        'updated_at' => $payment->fecha_modificacion ?? now(),
                    ];

                    if ($existingPayment)
                    {
                        $existingPayment->update($paymentData);
                        $stats['updated']++;
                    } else
                    {
                        Payment::withoutGlobalScopes()->create(array_merge(['id' => $payment->id], $paymentData));
                        $stats['imported']++;
                    }
                    $bar->advance();
                } catch (\Exception $e)
                {
                    $skipped++;
                    $stats['skipped']++;
                    if ($skipped <= 10)
                    {
                        $this->newLine();
                        $this->warn("     Skipped payment {$payment->id}: ".$e->getMessage());
                    }
                    $bar->advance();
                }
            }

            $bar->finish();
            $this->newLine();

            if ($skipped > 0)
            {
                $this->warn("   ⚠️  Skipped {$skipped} payments due to errors");
            }

            $this->info("✅ Imported {$stats['imported']} payments, updated {$stats['updated']}");
            
            return Command::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('⚠️  Could not import payments: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
