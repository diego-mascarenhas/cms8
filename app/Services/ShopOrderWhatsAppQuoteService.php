<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Exceptions\WhatsAppSessionWindowClosedException;
use App\Helpers\WhatsAppOutboundText;
use App\Models\Order;
use App\Models\User;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppCustomerServiceWindow;
use Illuminate\Validation\ValidationException;

class ShopOrderWhatsAppQuoteService
{
    /**
     * Persist optional line edits, then send the current quote to the customer WhatsApp.
     *
     * @param  list<array<string, mixed>>|null  $items
     * @return array{phone: string, message: string}
     */
    public function send(Order $order, User $user, ?array $items = null): array
    {
        if (is_array($items))
        {
            app(ShopOrderItemsService::class)->sync($order, $items);
            $order->refresh()->load(['contact', 'currency', 'store']);
        }

        $phone = $this->resolvePhone($order);
        if ($phone === null)
        {
            throw ValidationException::withMessages([
                'phone' => [__('Este pedido no tiene teléfono de WhatsApp.')],
            ]);
        }

        $message = $this->formatQuote($order);
        if ($message === '')
        {
            throw ValidationException::withMessages([
                'items' => [__('El pedido no tiene ítems para cotizar.')],
            ]);
        }

        try
        {
            app(WhatsAppCustomerServiceWindow::class)->assertOpen($phone);
        } catch (WhatsAppSessionWindowClosedException)
        {
            throw ValidationException::withMessages([
                'channel' => [__('whatsapp.send.error.session_window_closed')],
            ]);
        }

        $gateway = $this->resolveWhatsAppGateway($user);
        if (! $gateway->isConfigured())
        {
            throw ValidationException::withMessages([
                'channel' => [__('whatsapp.send.error.generic')],
            ]);
        }

        if (config('whatsapp.driver') === 'local')
        {
            $status = $gateway->getConnectionStatus();
            if (($status['status'] ?? '') !== 'connected')
            {
                throw ValidationException::withMessages([
                    'channel' => [__('whatsapp.send.error.not_connected')],
                ]);
            }
        }

        $outbound = WhatsAppOutboundText::stripInternalQaMarkers(WhatsAppOutboundText::sanitize($message));
        if ($outbound === '')
        {
            throw ValidationException::withMessages([
                'message' => [__('No se pudo armar la cotización.')],
            ]);
        }

        $gateway->sendMessage($phone, $outbound, [
            'source' => 'shop_order_quote',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ], $user->id);

        return [
            'phone' => $phone,
            'message' => $outbound,
        ];
    }

    public function formatQuote(Order $order): string
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $items = is_array($metadata['items'] ?? null) ? $metadata['items'] : [];
        if ($items === [])
        {
            return '';
        }

        $symbol = $order->currency?->symbol ?: '$';
        $name = trim((string) ($order->contact?->name ?? ''));
        $lines = [
            $name !== '' ? 'Hola '.$name.',' : 'Hola,',
            '',
            '*Cotización actualizada*',
            'Pedido *'.$order->order_number.'*',
            '',
        ];

        $total = 0.0;
        foreach ($items as $item)
        {
            if (! is_array($item))
            {
                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $lineTotal = array_key_exists('line_total', $item)
                ? (float) $item['line_total']
                : $unitPrice * $quantity;
            $total += $lineTotal;
            $productName = trim((string) ($item['name'] ?? 'Producto'));

            $lines[] = '• '.$productName;
            $lines[] = '  '.$quantity.' × '.$this->money($symbol, $unitPrice).' = '.$this->money($symbol, $lineTotal);
        }

        $lines[] = '';
        $lines[] = '*Total: '.$this->money($symbol, $total).'*';

        $fulfillment = is_array($metadata['checkout_offered'] ?? null)
            ? trim((string) ($metadata['checkout_offered']['chosen_fulfillment_label'] ?? ''))
            : '';
        $payment = is_array($metadata['checkout_offered'] ?? null)
            ? trim((string) ($metadata['checkout_offered']['chosen_payment_label'] ?? ''))
            : '';

        if ($fulfillment !== '' || $payment !== '')
        {
            $lines[] = '';
            if ($fulfillment !== '')
            {
                $lines[] = 'Entrega: '.$fulfillment;
            }
            if ($payment !== '')
            {
                $lines[] = 'Pago: '.$payment;
            }
        }

        return implode("\n", $lines);
    }

    private function resolvePhone(Order $order): ?string
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $candidates = [
            $metadata['phone'] ?? null,
            $order->contact?->getWhatsAppNumber(),
            $order->contact?->phone,
        ];

        foreach ($candidates as $candidate)
        {
            $digits = preg_replace('/\D+/', '', (string) $candidate) ?? '';
            if ($digits !== '')
            {
                return $digits;
            }
        }

        return null;
    }

    private function money(string $symbol, float $amount): string
    {
        return $symbol.' '.number_format($amount, 2, ',', '.');
    }

    private function resolveWhatsAppGateway(User $user): WhatsAppGateway
    {
        if (config('whatsapp.driver') === 'local')
        {
            $team = $user->currentTeam;
            $baseUrl = $team?->getWhatsAppServiceBaseUrl();
            if (is_string($baseUrl) && $baseUrl !== '')
            {
                return new LocalWhatsAppGateway(
                    $baseUrl,
                    config('whatsapp.local.webhook_secret'),
                    $team->id,
                );
            }
        }

        return app(WhatsAppGateway::class);
    }
}
