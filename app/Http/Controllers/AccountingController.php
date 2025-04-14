<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use App\Models\Enterprise;
use App\Models\InvoiceDownload;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessQuarterInvoices;

class AccountingController extends Controller
{
    /**
     * Display a listing of invoices.
     */
    public function index()
    {
        $team = auth()->user()->currentTeam;
        
        // Set default values
        $stripeData = [
            'invoices' => [],
            'metrics' => [
                'total_paid' => '0.00',
                'unpaid' => '0.00'
            ]
        ];
        
        if (!$team->getSetting('stripe_secret')) {
            return view('accounting.index', compact('stripeData'));
        }
        
        try {
            // Set Stripe API key
            Stripe::setApiKey($team->getSetting('stripe_secret'));
            
            // Get all invoices (limited to last 100)
            $invoices = Invoice::all([
                'limit' => 100
            ]);
            
            // Process invoices
            foreach ($invoices->data as $invoice) {
                // Determine correct amount to display based on status
                $amount = $invoice->status === 'paid' ? $invoice->amount_paid : $invoice->amount_due;
                
                // Create Carbon date for further processing
                $invoiceDate = Carbon::createFromTimestamp($invoice->created);
                
                // Determine quarter
                $quarter = ceil($invoiceDate->format('n') / 3);
                $year = $invoiceDate->format('Y');
                $quarterLabel = "Q{$quarter} {$year}";
                
                $stripeData['invoices'][] = [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'customer_name' => $invoice->customer_name,
                    'customer_email' => $invoice->customer_email,
                    'amount' => $amount / 100, // Convert from cents
                    'currency' => strtoupper($invoice->currency),
                    'status' => $invoice->status,
                    'date' => $invoiceDate->format('d/m/Y'),
                    'quarter' => $quarterLabel,
                    'quarter_sort' => ($year * 10) + $quarter, // For easier sorting
                    'timestamp' => $invoice->created, // Original timestamp
                    'pdf' => $invoice->invoice_pdf,
                    'customer_id' => $invoice->customer
                ];
            }
            
            // Sort invoices by number
            usort($stripeData['invoices'], function($a, $b) {
                return strcmp($a['number'], $b['number']);
            });
            
            // Group invoices by quarter
            $groupedInvoices = [];
            foreach ($stripeData['invoices'] as $invoice) {
                $groupedInvoices[$invoice['quarter']] = $groupedInvoices[$invoice['quarter']] ?? [];
                $groupedInvoices[$invoice['quarter']][] = $invoice;
            }
            
            // Sort quarters in descending order (most recent first)
            uksort($groupedInvoices, function($a, $b) {
                $aComponents = explode(' ', $a);
                $bComponents = explode(' ', $b);
                
                $aYear = (int)$aComponents[1];
                $bYear = (int)$bComponents[1];
                
                if ($aYear !== $bYear) {
                    return $bYear - $aYear; // Descending by year
                }
                
                $aQuarter = (int)substr($aComponents[0], 1);
                $bQuarter = (int)substr($bComponents[0], 1);
                
                return $bQuarter - $aQuarter; // Descending by quarter
            });
            
            $stripeData['grouped_invoices'] = $groupedInvoices;
            
            // Calculate metrics
            $totalPaid = 0;
            $totalUnpaid = 0;
            $paidInvoices = 0;
            $unpaidInvoices = 0;
            
            foreach ($invoices->data as $invoice) {
                if ($invoice->status === 'paid') {
                    $totalPaid += $invoice->amount_paid / 100;
                    $paidInvoices++;
                } else if ($invoice->status === 'open') {
                    $totalUnpaid += $invoice->amount_due / 100;
                    $unpaidInvoices++;
                }
            }
            
            $stripeData['metrics'] = [
                'total_paid' => number_format($totalPaid, 2),
                'unpaid' => number_format($totalUnpaid, 2),
                'total_invoices' => $paidInvoices,
                'unpaid_invoices' => $unpaidInvoices
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error fetching Stripe invoices: ' . $e->getMessage());
            session()->flash('error', 'Error al cargar datos de Stripe: ' . $e->getMessage());
        }
        
        return view('accounting.index', compact('stripeData'));
    }
    
    /**
     * Display details for a specific invoice.
     */
    public function showInvoice($id)
    {
        $team = auth()->user()->currentTeam;
        
        if (!$team->getSetting('stripe_secret')) {
            return redirect()->route('accounting.index')
                ->with('error', 'API de Stripe no configurada');
        }
        
        try {
            // Set Stripe API key
            Stripe::setApiKey($team->getSetting('stripe_secret'));
            
            // Get invoice details
            $invoice = Invoice::retrieve([
                'id' => $id,
                'expand' => ['customer', 'charge']
            ]);
            
            $invoiceData = [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer_name' => $invoice->customer->name,
                'customer_email' => $invoice->customer->email,
                'amount' => $invoice->amount_paid / 100,
                'amount_paid' => $invoice->amount_paid,
                'subtotal' => $invoice->subtotal,
                'total' => $invoice->total,
                'currency' => strtoupper($invoice->currency),
                'status' => $invoice->status,
                'date' => Carbon::createFromTimestamp($invoice->created)->format('d/m/Y'),
                'pdf' => $invoice->invoice_pdf,
                'customer_id' => $invoice->customer->id,
                'items' => []
            ];
            
            // Process line items
            foreach ($invoice->lines->data as $item) {
                $invoiceData['items'][] = [
                    'description' => $item->description,
                    'amount' => $item->amount / 100,
                    'quantity' => $item->quantity,
                    'period_start' => $item->period->start ? Carbon::createFromTimestamp($item->period->start)->format('d/m/Y') : null,
                    'period_end' => $item->period->end ? Carbon::createFromTimestamp($item->period->end)->format('d/m/Y') : null
                ];
            }
            
            // Look for a matching enterprise
            $enterprise = Enterprise::where('code', $invoice->customer->id)->first();
            
        } catch (\Exception $e) {
            \Log::error('Error fetching Stripe invoice details: ' . $e->getMessage());
            return redirect()->route('accounting.index')
                ->with('error', 'Error al cargar detalles de la factura: ' . $e->getMessage());
        }
        
        return view('accounting.invoice', compact('invoiceData', 'enterprise'));
    }
    
    /**
     * Track invoice download and redirect to PDF URL.
     */
    public function downloadInvoice($id)
    {
        $team = auth()->user()->currentTeam;
        $user = auth()->user();
        
        if (!$team->getSetting('stripe_secret')) {
            return redirect()->route('accounting.index')
                ->with('error', 'API de Stripe no configurada');
        }
        
        try {
            // Set Stripe API key
            Stripe::setApiKey($team->getSetting('stripe_secret'));
            
            // Get invoice details
            $invoice = Invoice::retrieve([
                'id' => $id
            ]);
            
            if (empty($invoice->invoice_pdf)) {
                return redirect()->route('accounting.invoice', $id)
                    ->with('error', 'PDF no disponible para esta factura');
            }
            
            // Get date information for the invoice
            $invoiceDate = Carbon::createFromTimestamp($invoice->created);
            $quarter = ceil($invoiceDate->format('n') / 3);
            $year = $invoiceDate->format('Y');
            
            // Record the download
            InvoiceDownload::create([
                'user_id' => $user->id,
                'team_id' => $team->id,
                'invoice_id' => $id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'downloaded_at' => now(),
            ]);
            
            // Redirect to the PDF
            return redirect($invoice->invoice_pdf);
            
        } catch (\Exception $e) {
            \Log::error('Error downloading invoice PDF: ' . $e->getMessage());
            return redirect()->route('accounting.invoice', $id)
                ->with('error', 'Error al descargar PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Display invoices for a specific customer.
     */
    public function customerInvoices($customerId)
    {
        $team = auth()->user()->currentTeam;
        
        if (!$team->getSetting('stripe_secret')) {
            return redirect()->route('accounting.index')
                ->with('error', 'API de Stripe no configurada');
        }
        
        // Find the enterprise
        $enterprise = Enterprise::where('code', $customerId)->first();
        
        // Set default values
        $stripeData = [
            'customer' => null,
            'invoices' => [],
            'metrics' => [
                'total_paid' => '0.00',
                'unpaid' => '0.00'
            ]
        ];
        
        try {
            // Set Stripe API key
            Stripe::setApiKey($team->getSetting('stripe_secret'));
            
            // Get customer
            $customer = Customer::retrieve([
                'id' => $customerId,
                'expand' => ['subscriptions']
            ]);
            
            $stripeData['customer'] = [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'created' => Carbon::createFromTimestamp($customer->created)->format('d/m/Y')
            ];
            
            // Get customer invoices
            $invoices = Invoice::all([
                'customer' => $customerId,
                'limit' => 100
            ]);
            
            // Process invoices
            foreach ($invoices->data as $invoice) {
                $stripeData['invoices'][] = [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'amount' => $invoice->amount_paid / 100,
                    'currency' => strtoupper($invoice->currency),
                    'status' => $invoice->status,
                    'date' => Carbon::createFromTimestamp($invoice->created)->format('d/m/Y'),
                    'pdf' => $invoice->invoice_pdf
                ];
            }
            
            // Calculate metrics
            $totalPaid = 0;
            $totalUnpaid = 0;
            
            foreach ($invoices->data as $invoice) {
                if ($invoice->status === 'paid') {
                    $totalPaid += $invoice->amount_paid / 100;
                } else if ($invoice->status === 'open') {
                    $totalUnpaid += $invoice->amount_due / 100;
                }
            }
            
            $stripeData['metrics'] = [
                'total_paid' => number_format($totalPaid, 2),
                'unpaid' => number_format($totalUnpaid, 2)
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error fetching customer invoices: ' . $e->getMessage());
            session()->flash('error', 'Error al cargar facturas del cliente: ' . $e->getMessage());
        }
        
        return view('accounting.customer', compact('stripeData', 'enterprise'));
    }
    
    /**
     * Download invoices for a specific quarter as ZIP file.
     */
    public function downloadQuarterInvoices(Request $request)
    {
        $team = auth()->user()->currentTeam;
        $user = auth()->user();
        $quarter = $request->quarter;
        $year = $request->year;
        
        if (empty($quarter) || empty($year)) {
            return redirect()->route('accounting.index')
                ->with('error', 'Trimestre o año no especificado');
        }
        
        if (!$team->getSetting('stripe_secret')) {
            return redirect()->route('accounting.index')
                ->with('error', 'API de Stripe no configurada');
        }
        
        // Dispatch job to process quarter invoices
        ProcessQuarterInvoices::dispatch($quarter, $year, $team, $user);
        
        return redirect()->route('accounting.index')
            ->with('success', 'La solicitud de descarga de facturas ha sido puesta en cola. Recibirá una notificación cuando esté lista.');
    }
} 