<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationMode;
use App\Models\SubscriptionProduct;
use App\Services\StripeAccountResolver;
use App\Services\TeamWhatsAppChatPresentation;
use App\Support\HumanoPublicPaymentLinkCheckout;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Stripe\Stripe;

class RegistrationBillingController extends Controller
{
    public function billing(Request $request): View|RedirectResponse
    {
        $mode = RegistrationMode::fromConfiguration();

        if ($request->user()?->currentTeam?->passesRegistrationBillingGate())
        {
            return redirect()->route('dashboard');
        }

        if ($mode === RegistrationMode::Checkout)
        {
            return redirect()->route('registration.checkout.start');
        }

        if ($mode !== RegistrationMode::Gate)
        {
            return redirect()->route('dashboard');
        }

        $pageConfigs = ['myLayout' => 'blank'];

        return view('auth.registration-billing-gate', [
            'pageConfigs' => $pageConfigs,
            'checkoutProduct' => $this->resolveSubscriptionProduct(),
        ]);
    }

    /**
     * Continue registration payment using the same billing form and Stripe Checkout as subscriptions.
     */
    public function startCheckout(Request $request): RedirectResponse
    {
        if (! RegistrationMode::fromConfiguration()->requiresBillingCompletion())
        {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        if ($user?->currentTeam?->passesRegistrationBillingGate())
        {
            return redirect()->route('dashboard');
        }

        if (! config('cashier.secret'))
        {
            return redirect()->route('registration.billing')
                ->with('error', __('auth.registration.stripe_not_configured'));
        }

        $team = $user->currentTeam;
        if (! $team)
        {
            Log::error('Registration checkout: user has no current team.', ['user_id' => $user->id]);

            return redirect()->route('dashboard')
                ->with('error', __('Could not start checkout. Please contact support.'));
        }

        $product = $this->resolveSubscriptionProduct();
        $stripeProductId = trim(config('registration.stripe_product_id', ''));

        Log::info('Registration debug: startCheckout resolved product context', [
            'user_id' => $user?->id,
            'team_id' => $team->id,
            'registration_mode' => RegistrationMode::fromConfiguration()->value,
            'configured_registration_product_id' => $stripeProductId,
            'resolved_product_id' => $product?->id,
            'resolved_product_stripe_product' => $product?->stripe_product,
            'resolved_product_stripe_price' => $product?->stripe_price,
        ]);

        if (! $product && $stripeProductId === '')
        {
            return redirect()->route('registration.billing')
                ->with('error', __('auth.registration.product_not_configured'));
        }

        $priceId = $product
            ? $this->resolveStripePriceId($product)
            : $this->resolveStripePriceIdForProductId($stripeProductId, 'mailer');

        Log::info('Registration debug: startCheckout resolved price', [
            'team_id' => $team->id,
            'price_id' => $priceId,
            'from_product' => $product !== null,
        ]);

        if (! $priceId || ! str_starts_with($priceId, 'price_'))
        {
            return redirect()->route('registration.billing')
                ->with('error', __('auth.registration.no_valid_price'));
        }

        $billingQuery = ['from_registration' => 1];
        if ($product)
        {
            $billingQuery['product_id'] = $product->id;
        } else
        {
            $billingQuery['price_id'] = $priceId;
        }

        return redirect()->route('subscription.billing-info', $billingQuery);
    }

    public function onboardingQr(Request $request): View|RedirectResponse
    {
        if (RegistrationMode::fromConfiguration()->requiresBillingCompletion())
        {
            $team = $request->user()?->currentTeam;
            if (! $team || ! $team->passesRegistrationBillingGate())
            {
                return redirect()->route('registration.billing');
            }
        }

        $pageConfigs = ['myLayout' => 'blank'];
        $presentation = TeamWhatsAppChatPresentation::resolveForTeam($request->user()?->currentTeam);

        $request->session()->forget(HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA);

        return view('auth.registration-onboarding-qr', array_merge(
            ['pageConfigs' => $pageConfigs],
            $presentation,
        ));
    }

    /**
     * PNG QR that opens Humano Chat (browser) on the phone — used when the WhatsApp Web / Baileys QR is not available (e.g. Twilio driver).
     */
    public function onboardingChatLinkQrImage(Request $request): Response
    {
        abort_unless(Route::has('chat.index'), 404);

        $target = route('chat.index', [], true);

        $qrcode = new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => 5,
        ]));

        $dataUri = $qrcode->render($target);
        $pngBase64 = str_replace('data:image/png;base64,', '', $dataUri);
        $binary = base64_decode((string) $pngBase64, true);

        if ($binary === false || $binary === '')
        {
            abort(500);
        }

        return response($binary, 200)->withHeaders([
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    private function resolveSubscriptionProduct(): ?SubscriptionProduct
    {
        $stripeProductId = trim(config('registration.stripe_product_id', ''));
        if ($stripeProductId === '')
        {
            return null;
        }

        return SubscriptionProduct::query()
            ->where(function ($query) use ($stripeProductId)
            {
                $query->where('stripe_product', $stripeProductId)
                    ->orWhere('stripe_id', $stripeProductId);
            })
            ->first();
    }

    private function resolveStripePriceId(SubscriptionProduct $product): ?string
    {
        if ($product->stripe_price && str_starts_with($product->stripe_price, 'price_'))
        {
            return $product->stripe_price;
        }

        $stripeProductId = $product->stripe_product ?? $product->stripe_id;
        if (! $stripeProductId)
        {
            return null;
        }

        $category = StripeAccountResolver::normalizeCategory($product->category ?? 'mailer');
        Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));

        $prices = \Stripe\Price::all([
            'product' => $stripeProductId,
            'active' => true,
            'limit' => 10,
        ]);

        foreach ($prices->data as $price)
        {
            if ($price->recurring && strtolower((string) $price->currency) === 'eur')
            {
                return $price->id;
            }
        }

        foreach ($prices->data as $price)
        {
            if ($price->recurring)
            {
                return $price->id;
            }
        }

        return $prices->data[0]->id ?? null;
    }

    private function resolveStripePriceIdForProductId(string $stripeProductId, string $category): ?string
    {
        $category = StripeAccountResolver::normalizeCategory($category);
        Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));

        $prices = \Stripe\Price::all([
            'product' => $stripeProductId,
            'active' => true,
            'limit' => 10,
        ]);

        foreach ($prices->data as $price)
        {
            if ($price->recurring && strtolower((string) $price->currency) === 'eur')
            {
                return $price->id;
            }
        }

        foreach ($prices->data as $price)
        {
            if ($price->recurring)
            {
                return $price->id;
            }
        }

        return $prices->data[0]->id ?? null;
    }
}
