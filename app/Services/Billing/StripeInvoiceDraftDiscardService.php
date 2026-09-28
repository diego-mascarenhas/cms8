<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use App\Models\TeamUsageInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeInvoiceDraftDiscardService
{
    public const DRAFT_STATUS = 9;

    public function __construct(
        private readonly TeamUsageInvoiceStripeGateway $usageStripeGateway,
    ) {}

    public function canDiscard(User $user, Invoice $invoice): bool
    {
        if (! $user->currentTeam || ! $user->ownsTeam($user->currentTeam))
        {
            return false;
        }

        if ((int) $invoice->team_id !== (int) $user->currentTeam->id)
        {
            return false;
        }

        if ((int) $invoice->status !== self::DRAFT_STATUS)
        {
            return false;
        }

        return $this->stripeInvoiceId($invoice) !== null;
    }

    /**
     * Delete the Stripe draft and remove the Humano invoice (+ sync / usage rows).
     *
     * @throws ValidationException
     */
    public function discard(User $user, Invoice $invoice): void
    {
        if (! $this->canDiscard($user, $invoice))
        {
            throw ValidationException::withMessages([
                'invoice' => [__('Only the team owner can delete Stripe draft invoices.')],
            ]);
        }

        $stripeInvoiceId = $this->stripeInvoiceId($invoice);
        if ($stripeInvoiceId === null)
        {
            throw ValidationException::withMessages([
                'invoice' => [__('This invoice is not a Stripe draft.')],
            ]);
        }

        $this->deleteStripeDraft($invoice, $stripeInvoiceId);

        DB::transaction(function () use ($invoice, $stripeInvoiceId): void
        {
            TeamUsageInvoice::query()
                ->where('stripe_invoice_id', $stripeInvoiceId)
                ->delete();

            InvoiceSync::query()
                ->where('provider', 'stripe')
                ->where('external_id', $stripeInvoiceId)
                ->delete();

            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    public function stripeInvoiceId(Invoice $invoice): ?string
    {
        $externalId = trim((string) $invoice->source_reference_id);
        if ($externalId === '' || ! str_starts_with($externalId, 'in_'))
        {
            return null;
        }

        if (strtolower((string) $invoice->source_provider) === 'stripe')
        {
            return $externalId;
        }

        // Some imported drafts may omit source_provider but still carry an in_ id.
        return $externalId;
    }

    private function deleteStripeDraft(Invoice $invoice, string $stripeInvoiceId): void
    {
        try
        {
            if ($this->isUsageDraft($stripeInvoiceId))
            {
                $this->usageStripeGateway->deleteDraftInvoice($stripeInvoiceId);

                return;
            }

            $secret = $this->resolveStripeSecret($invoice);
            if ($secret === '')
            {
                throw ValidationException::withMessages([
                    'invoice' => [__('Stripe is not configured for this team.')],
                ]);
            }

            $this->makeClient($secret)->invoices->delete($stripeInvoiceId);
        } catch (ValidationException $exception)
        {
            throw $exception;
        } catch (ApiErrorException $exception)
        {
            if ($this->isAlreadyGone($exception))
            {
                Log::info('Stripe draft already deleted; continuing local cleanup', [
                    'invoice_id' => $invoice->id,
                    'stripe_invoice_id' => $stripeInvoiceId,
                ]);

                return;
            }

            throw ValidationException::withMessages([
                'invoice' => [__('Could not delete the Stripe draft: :message', [
                    'message' => $exception->getMessage(),
                ])],
            ]);
        } catch (Throwable $exception)
        {
            throw ValidationException::withMessages([
                'invoice' => [__('Could not delete the Stripe draft: :message', [
                    'message' => $exception->getMessage(),
                ])],
            ]);
        }
    }

    private function isUsageDraft(string $stripeInvoiceId): bool
    {
        try
        {
            return TeamUsageInvoice::query()
                ->where('stripe_invoice_id', $stripeInvoiceId)
                ->exists();
        } catch (Throwable)
        {
            return false;
        }
    }

    private function resolveStripeSecret(Invoice $invoice): string
    {
        $team = Team::query()->find($invoice->team_id);
        if ($team instanceof Team)
        {
            $teamSecret = trim((string) $team->getSetting('stripe_secret'));
            if ($teamSecret !== '')
            {
                return $teamSecret;
            }
        }

        return trim((string) config('cashier.secret'));
    }

    protected function makeClient(string $secret): StripeClient
    {
        return new StripeClient($secret);
    }

    private function isAlreadyGone(ApiErrorException $exception): bool
    {
        $code = (string) $exception->getStripeCode();

        return $code === 'resource_missing'
            || str_contains(strtolower($exception->getMessage()), 'no such invoice');
    }
}
