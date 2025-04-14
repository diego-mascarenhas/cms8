<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stripe\Invoice;
use Stripe\Stripe;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Team;
use App\Models\User;

class ProcessQuarterInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $quarter;
    protected $year;
    protected $team;
    protected $user;
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(int $quarter, int $year, Team $team, User $user)
    {
        $this->quarter = $quarter;
        $this->year = $year;
        $this->team = $team;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->team->getSetting('stripe_secret')) {
            Log::error('Stripe secret not found for team ' . $this->team->id);
            return;
        }

        try {
            // Set Stripe API key
            Stripe::setApiKey($this->team->getSetting('stripe_secret'));
            
            // Get all invoices (limited to last 100)
            $invoices = Invoice::all([
                'limit' => 100
            ]);
            
            // Filter invoices by the specified quarter and year
            $quarterInvoices = [];
            foreach ($invoices->data as $invoice) {
                $invoiceDate = Carbon::createFromTimestamp($invoice->created);
                $invoiceQuarter = ceil($invoiceDate->format('n') / 3);
                $invoiceYear = $invoiceDate->format('Y');
                
                if ($invoiceQuarter == $this->quarter && $invoiceYear == $this->year) {
                    $quarterInvoices[] = $invoice;
                }
            }
            
            if (empty($quarterInvoices)) {
                Log::info("No invoices found for Q{$this->quarter} {$this->year}");
                return;
            }
            
            // Create a unique directory name for this batch of PDFs
            $jobId = uniqid();
            $tempDirName = "invoices_{$jobId}";
            $userDirName = "user_{$this->user->id}";
            
            Log::info("Processing invoices for Q{$this->quarter} {$this->year}, Job ID: {$jobId}");
            
            // Create directories for user and job
            Storage::disk('public')->makeDirectory("downloads/{$userDirName}");
            Storage::disk('public')->makeDirectory("downloads/{$userDirName}/{$tempDirName}");
            
            // Download PDFs to the temp directory
            $pdfCount = 0;
            foreach ($quarterInvoices as $invoice) {
                try {
                    $pdfUrl = null;
                    
                    // Check if invoice_pdf exists and is not null
                    if (!empty($invoice->invoice_pdf)) {
                        $pdfUrl = $invoice->invoice_pdf;
                    } else {
                        // Try to generate a PDF if not available directly
                        try {
                            $pdf = $invoice->pdf(['type' => 'invoice']);
                            if (!empty($pdf->url)) {
                                $pdfUrl = $pdf->url;
                                Log::info("Generated PDF for invoice {$invoice->number}");
                            }
                        } catch (\Exception $pdfEx) {
                            Log::warning("Could not generate PDF for invoice {$invoice->number}: " . $pdfEx->getMessage());
                        }
                    }
                    
                    // Process PDF if URL is available
                    if (!empty($pdfUrl)) {
                        $pdfContent = @file_get_contents($pdfUrl);
                        
                        if ($pdfContent !== false) {
                            $filename = "factura_{$invoice->number}.pdf";
                            $path = "downloads/{$userDirName}/{$tempDirName}/{$filename}";
                            
                            // Store the PDF
                            $stored = Storage::disk('public')->put($path, $pdfContent);
                            Log::info("PDF stored: " . ($stored ? 'success' : 'failed') . " - Path: {$path}");
                            
                            if ($stored) {
                                $pdfCount++;
                            }
                        } else {
                            Log::warning("Could not download PDF for invoice {$invoice->number}: Content empty");
                        }
                    } else {
                        Log::warning("Invoice {$invoice->number} doesn't have a PDF URL");
                    }
                } catch (\Exception $e) {
                    Log::warning("Error processing PDF for invoice {$invoice->number}: " . $e->getMessage());
                    // Continue with the next invoice
                }
            }
            
            // Check if we have any PDFs to include in the ZIP
            if ($pdfCount === 0) {
                // Clean up the temp directory
                Storage::disk('public')->deleteDirectory("downloads/{$userDirName}/{$tempDirName}");
                Log::error("No PDFs could be downloaded for Q{$this->quarter} {$this->year}");
                return;
            }
            
            // Create ZIP file
            $zipFilename = "facturas_Q{$this->quarter}_{$this->year}.zip";
            $zipPath = storage_path("app/public/downloads/{$userDirName}/{$zipFilename}");
            
            Log::info("Creating ZIP file at: {$zipPath}");
            
            try {
                $zip = new \ZipArchive();
                $openResult = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                
                if ($openResult !== true) {
                    throw new \Exception("Cannot create ZIP file: " . $zipPath . ", error code: " . $openResult);
                }
                
                // Get all PDF files from the temporary directory
                $files = Storage::disk('public')->files("downloads/{$userDirName}/{$tempDirName}");
                Log::info("Found " . count($files) . " PDFs to add to ZIP");
                
                foreach ($files as $file) {
                    $fullPath = storage_path('app/public/' . $file);
                    $relativePath = basename($fullPath);
                    
                    $zip->addFile($fullPath, $relativePath);
                }
                
                $zip->close();
                
                // Clean up the temp directory after creating the ZIP
                Storage::disk('public')->deleteDirectory("downloads/{$userDirName}/{$tempDirName}");
                
                // Store download path in database or notify user
                Log::info("ZIP created successfully for user {$this->user->id}: " . $zipFilename);
                
                // Update user notification or database record to indicate completion
                // You will need to have a way to notify users that their ZIP file is ready
                // This could be via notifications, email, or a database record
                
            } catch (\Exception $zipEx) {
                Log::error("Error creating ZIP file: " . $zipEx->getMessage());
                // Clean up the temp directory
                Storage::disk('public')->deleteDirectory("downloads/{$userDirName}/{$tempDirName}");
            }
            
        } catch (\Exception $e) {
            Log::error('Error in ProcessQuarterInvoices job: ' . $e->getMessage());
        }
    }
} 