# Billing Module (Accounting)

## Overview

The Billing module provides comprehensive Stripe invoice management and accounting features for the Humano application. This module was migrated from the `humano-billing` package and integrated into the core application.

## Features

- **Stripe Integration**: Direct integration with Stripe API for invoice management
- **Invoice Listing**: View all invoices grouped by quarter
- **Invoice Details**: View detailed information for each invoice
- **Customer View**: View all invoices for a specific customer
- **Bulk Downloads**: Download invoices by quarter as ZIP files
- **CSV Export**: Export invoice data to CSV format
- **Download Tracking**: Track invoice downloads with user and timestamp information

## Components

### Controller

**AccountingController** (`app/Http/Controllers/AccountingController.php`)

Main controller handling all accounting operations:
- `index()` - List all invoices grouped by quarter
- `showInvoice($id)` - Show invoice details
- `downloadInvoice($id)` - Download single invoice PDF
- `customerInvoices($customerId)` - View customer invoices
- `downloadQuarterInvoices()` - Generate ZIP of quarter invoices
- `downloadQuarterCsv()` - Export quarter invoices to CSV

### Views

Located in `resources/views/accounting/`:

- `index.blade.php` - Main invoice listing with statistics
- `invoice.blade.php` - Invoice detail view
- `customer.blade.php` - Customer invoices view
- `download-processing.blade.php` - Download processing screen

### Models

- **InvoiceDownload** (`app/Models/InvoiceDownload.php`) - Tracks invoice downloads

### Jobs

- **ProcessQuarterInvoices** (`app/Jobs/ProcessQuarterInvoices.php`) - Background job for generating ZIP files of quarterly invoices

## Routes

All routes are protected by `web` and `auth` middleware:

```php
Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
Route::get('/accounting/invoice/{id}', [AccountingController::class, 'showInvoice'])->name('accounting.invoice');
Route::get('/accounting/invoice/{id}/download', [AccountingController::class, 'downloadInvoice'])->name('accounting.invoice.download');
Route::get('/accounting/customer/{id}', [AccountingController::class, 'customerInvoices'])->name('accounting.customer');
Route::get('/accounting/download-quarter', [AccountingController::class, 'downloadQuarterInvoices'])->name('accounting.download-quarter');
Route::get('/accounting/download-quarter-csv', [AccountingController::class, 'downloadQuarterCsv'])->name('accounting.download-quarter-csv');
```

## Configuration

### Stripe Setup

The module requires Stripe API credentials to be configured at the team level:

1. Each team must have `stripe_secret` setting configured
2. Access Team Settings to configure Stripe credentials
3. The setting is accessed via: `auth()->user()->currentTeam->getSetting('stripe_secret')`

### Storage

Invoice ZIP files are stored in:
```
storage/app/public/downloads/user_{user_id}/
```

## Usage

### Viewing Invoices

Navigate to `/accounting` to view all Stripe invoices grouped by quarter. The dashboard shows:
- Total invoices amount
- Paid invoices
- Pending invoices
- Uncollectible invoices

### Downloading Invoices

**Single Invoice:**
Click the download icon next to any invoice to download its PDF.

**Quarterly Download (ZIP):**
1. Click "Generar ZIP" for a specific quarter
2. System will queue a background job to process invoices
3. Once complete, click "Descargar ZIP" to download the archive

**CSV Export:**
Click the "CSV" button for a quarter to export invoice data to CSV format.

### Customer View

Click on a customer name to view all invoices for that specific customer, including:
- Customer information
- Link to CRM if enterprise exists
- Total paid and unpaid amounts
- List of all customer invoices

## Database Schema

### invoice_downloads

Tracks invoice download activity:

```php
- user_id (foreign key to users)
- team_id (foreign key to teams)
- invoice_id (Stripe invoice ID)
- quarter (optional)
- year (optional)
- status (download status)
- file_path (path to downloaded file)
- file_name (name of downloaded file)
- error_message (error message if any)
- ip_address (user IP address)
- user_agent (user browser)
- downloaded_at (timestamp)
```

## Integration with Existing Modules

The Billing module integrates with:

- **Enterprise Module**: Links Stripe customers to CRM enterprises via `code` field
- **Contact Module**: Links to contact details from enterprise
- **Queue System**: Uses Laravel queues for background processing

## Security

- All routes require authentication (`auth` middleware)
- Stripe API key is stored securely at team level
- Only team-specific data is accessible
- Download tracking includes IP and user agent

## Error Handling

The module includes comprehensive error handling:
- API connection errors are logged
- User-friendly error messages are displayed
- Failed downloads are tracked
- Background job failures are logged

## Permissions

The module respects the application's role-based authorization system. While permission entries exist in the seeder (commented out), access control is managed through Laravel Policies and Roles.

## Migration Notes

This module was migrated from the `humano-billing` package with the following changes:

1. Namespace changed from `Idoneo\HumanoBilling` to `App`
2. Views moved from package to core application
3. Routes integrated into main `web.php`
4. Service provider logic integrated into core
5. Models and migrations already existed in core

## Future Enhancements

Potential improvements:
- Real-time notifications when ZIP files are ready
- Advanced filtering and search
- Payment tracking integration
- Invoice creation from within the application
- Multi-currency support display
- Custom invoice templates

## Troubleshooting

### No invoices showing

- Verify Stripe API credentials are configured
- Check that team has `stripe_secret` setting
- Review error logs for API connection issues

### ZIP download not working

- Ensure queue worker is running: `php artisan queue:work`
- Check storage permissions: `storage/app/public/downloads/`
- Review job logs for processing errors

### Customer not linked to CRM

- Verify enterprise `code` field matches Stripe customer ID
- Ensure enterprise exists in the database
