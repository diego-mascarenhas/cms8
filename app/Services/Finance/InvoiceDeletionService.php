<?php

namespace App\Services\Finance;

use App\Mail\InvoiceDeletedMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvoiceDeletionService
{
    /**
     * Soft-delete an invoice and email the team owner when an admin removes it.
     */
    public function delete(User $actor, Invoice $invoice): void
    {
        if (! $actor->can('delete', $invoice))
        {
            throw ValidationException::withMessages([
                'invoice' => __('You are not allowed to delete this invoice.'),
            ]);
        }

        if ((int) $invoice->team_id !== (int) ($actor->currentTeam?->id))
        {
            throw ValidationException::withMessages([
                'invoice' => __('You are not allowed to delete this invoice.'),
            ]);
        }

        $invoice->loadMissing(['team.owner', 'enterprise', 'currency']);

        $team = $invoice->team;
        $owner = $team?->owner;
        $snapshot = [
            'number' => (string) ($invoice->number ?: '#'.$invoice->id),
            'enterprise' => (string) ($invoice->enterprise?->name ?: '—'),
            'amount' => $this->formatAmount($invoice),
            'operation' => (string) ($invoice->operation ?: '—'),
            'team' => (string) ($team?->name ?: '—'),
            'deleted_by_name' => trim((string) $actor->name) !== '' ? (string) $actor->name : (string) $actor->email,
            'deleted_by_email' => (string) $actor->email,
        ];

        $invoice->delete();

        $ownerEmail = trim((string) ($owner?->email ?? ''));
        if ($ownerEmail === '' || (int) $owner->id === (int) $actor->id)
        {
            return;
        }

        Mail::to($ownerEmail)->send(new InvoiceDeletedMail(
            invoiceNumber: $snapshot['number'],
            enterpriseName: $snapshot['enterprise'],
            amountLabel: $snapshot['amount'],
            operationLabel: $snapshot['operation'],
            teamName: $snapshot['team'],
            deletedByName: $snapshot['deleted_by_name'],
            deletedByEmail: $snapshot['deleted_by_email'],
        ));
    }

    private function formatAmount(Invoice $invoice): string
    {
        $amount = number_format((float) $invoice->total_amount, 2, ',', '.');
        $symbol = trim((string) ($invoice->currency?->symbol ?: $invoice->currency?->code ?: '€'));

        return $amount.' '.$symbol;
    }
}
