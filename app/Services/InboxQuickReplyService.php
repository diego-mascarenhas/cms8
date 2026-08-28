<?php

namespace App\Services;

use App\Enums\ProductCatalogStatus;
use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Team;
use App\Models\User;
use App\Support\NewUserWelcomeEmailNotifier;
use App\Support\PasswordResetFrontendUrl;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class InboxQuickReplyService
{
    /**
     * @return list<array{key: string, slash: string, label: string, hint: string, needs_argument: bool}>
     */
    public function catalog(): array
    {
        return [
            [
                'key' => 'producto',
                'slash' => '/producto',
                'label' => 'Producto',
                'hint' => '/producto SKU o código',
                'needs_argument' => true,
            ],
            [
                'key' => 'list',
                'slash' => '/list',
                'label' => 'Lista de seguimiento',
                'hint' => '/list nota para el seguimiento',
                'needs_argument' => true,
            ],
            [
                'key' => 'recomendar',
                'slash' => '/recomendar',
                'label' => 'Recomendar y sumar puntos',
                'hint' => 'El mismo recorrido. El customer va en el registro',
                'needs_argument' => false,
            ],
            [
                'key' => 'accesos',
                'slash' => '/accesos',
                'label' => 'Accesos',
                'hint' => 'Login solo si todavía no tiene cuenta',
                'needs_argument' => false,
            ],
        ];
    }

    /**
     * @return array{key: string, argument: ?string}|null
     */
    public function parse(string $message): ?array
    {
        $trim = trim($message);
        if ($trim === '' || str_contains($trim, "\n"))
        {
            return null;
        }

        if (preg_match('#^/(enviar-|send-)#iu', $trim) === 1)
        {
            return null;
        }

        if (preg_match('#^/(producto|sku|list|lista|recomendar|onboarding|accesos)(?:\s+(.+))?$#iu', $trim, $match) !== 1)
        {
            return null;
        }

        $key = Str::lower((string) $match[1]);
        if ($key === 'sku')
        {
            $key = 'producto';
        }
        if ($key === 'lista')
        {
            $key = 'list';
        }
        $argument = isset($match[2]) ? trim((string) $match[2]) : '';

        return [
            'key' => $key,
            'argument' => $argument !== '' ? $argument : null,
        ];
    }

    /**
     * @return array{ok: bool, messages: list<string>, media?: string, silent?: bool, notice?: string, error?: string}
     */
    public function resolve(Team $team, string $key, ?string $argument = null, ?Contact $contact = null): array
    {
        return match ($key)
        {
            'producto' => $this->productMessages($team, $argument),
            'list' => app(InboxList60SlashService::class)->enroll(
                $team,
                $argument,
                $contact,
                auth()->user() instanceof User ? auth()->user() : null,
            ),
            'recomendar', 'onboarding' => app(InboxProductOnboardingService::class)->start($contact),
            'accesos' => $this->accessMessages($team, $contact),
            default => ['ok' => false, 'messages' => [], 'error' => 'No conozco ese comando.'],
        };
    }

    /**
     * @return array{ok: bool, messages: list<string>, media?: string, error?: string}
     */
    private function productMessages(Team $team, ?string $argument): array
    {
        $needle = trim((string) $argument);
        if ($needle === '')
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => 'Usá /producto y el SKU o código. Ej: /producto REM-001',
            ];
        }

        $found = $this->findPublishedShopProduct($team, $needle);
        if ($found === null)
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => $this->productNotFoundError($team, $needle),
            ];
        }

        $payload = [
            'ok' => true,
            'messages' => [$this->productBubble($team, $found['product'], $found['variant'])],
        ];
        $media = app(ProductImageService::class)->whatsAppPath($found['product']->image);
        if ($media !== null)
        {
            $payload['media'] = $media;
        }

        return $payload;
    }

    /**
     * @return array{product: Product, variant: ?ProductVariant}|null
     */
    public function findPublishedShopProduct(Team $team, string $needle): ?array
    {
        $normalized = mb_strtolower(trim($needle));
        if ($normalized === '')
        {
            return null;
        }

        $teamIds = $this->shopCatalogTeamIds($team);
        $product = Product::withoutGlobalScope('team')
            ->whereIn('team_id', $teamIds)
            ->where('catalog_status', ProductCatalogStatus::Publish)
            ->where(function ($query) use ($normalized): void
            {
                $query->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) = ?', [$normalized]);
            })
            ->orderByRaw('team_id = ? desc', [(int) $team->id])
            ->with(['currency', 'store', 'stores'])
            ->first();

        if ($product !== null)
        {
            return ['product' => $product, 'variant' => null];
        }

        $variant = ProductVariant::withoutGlobalScope('team')
            ->whereIn('team_id', $teamIds)
            ->whereNotNull('sku')
            ->whereRaw('LOWER(sku) = ?', [$normalized])
            ->orderByRaw('team_id = ? desc', [(int) $team->id])
            ->first();

        if ($variant === null)
        {
            return null;
        }

        $product = Product::withoutGlobalScope('team')
            ->whereIn('team_id', $teamIds)
            ->where('id', $variant->product_id)
            ->where('catalog_status', ProductCatalogStatus::Publish)
            ->with(['currency', 'store', 'stores'])
            ->first();

        if ($product === null)
        {
            return null;
        }

        return ['product' => $product, 'variant' => $variant];
    }

    /**
     * @return list<int>
     */
    private function shopCatalogTeamIds(Team $team): array
    {
        $ids = [(int) $team->id];
        $user = auth()->user();
        if ($user instanceof User)
        {
            foreach ($user->allTeams() as $memberTeam)
            {
                $ids[] = (int) $memberTeam->id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function productNotFoundError(Team $team, string $needle): string
    {
        $normalized = mb_strtolower(trim($needle));
        $draft = Product::withoutGlobalScope('team')
            ->whereIn('team_id', $this->shopCatalogTeamIds($team))
            ->where('catalog_status', '!=', ProductCatalogStatus::Publish)
            ->where(function ($query) use ($normalized): void
            {
                $query->whereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) = ?', [$normalized]);
            })
            ->first();

        if ($draft !== null)
        {
            return 'El producto «'.$needle.'» está en borrador. Publicalo en Shop para poder enviarlo.';
        }

        return 'No encontré un producto publicado con SKU o código «'.$needle.'».';
    }

    private function productBubble(Team $team, Product $product, ?ProductVariant $variant): string
    {
        $name = $variant !== null
            ? $variant->displayName((string) $product->name)
            : (string) $product->name;
        $code = trim((string) ($product->code ?? ''));
        $sku = trim((string) ($variant?->sku ?? $product->defaultVariant()?->sku ?? ''));

        $lines = ['Te paso este: '.$name];
        if ($code !== '')
        {
            $lines[] = 'Código: '.$code;
        }
        if ($sku !== '' && strcasecmp($sku, $code) !== 0)
        {
            $lines[] = 'SKU: '.$sku;
        }

        $price = $this->productPriceLine($product, $variant);
        if ($price !== null)
        {
            $lines[] = $price;
        }

        $blurb = trim(strip_tags((string) ($product->short_description ?: $product->description ?: '')));
        $blurb = preg_replace('/\s+/u', ' ', $blurb) ?? '';
        if ($blurb !== '')
        {
            $lines[] = Str::limit($blurb, 180);
        }

        $catalogTeam = (int) $product->team_id === (int) $team->id
            ? $team
            : Team::withoutGlobalScopes()->find($product->team_id);
        $shopUrl = ($catalogTeam ?? $team)->publicCatalogShopUrl();
        if ($shopUrl !== null)
        {
            $lines[] = $shopUrl;
        }

        return implode("\n", $lines);
    }

    private function productPriceLine(Product $product, ?ProductVariant $variant): ?string
    {
        if (! $product->catalogShowsPrice())
        {
            return null;
        }

        $amount = $variant !== null
            ? $variant->currentSellingPrice()
            : $product->currentSellingPrice();
        $symbol = $product->currency?->symbol ?? '$';

        return 'Precio: '.$symbol.number_format($amount, 2, ',', '.');
    }

    /**
     * @return array{ok: bool, messages: list<string>, error?: string}
     */
    private function accessMessages(Team $team, ?Contact $contact): array
    {
        $bubble = $this->accessBubble($team, $contact);
        if ($bubble === null)
        {
            return [
                'ok' => false,
                'messages' => [],
                'error' => 'Para enviar accesos el contacto necesita un email en la ficha.',
            ];
        }

        return ['ok' => true, 'messages' => [$bubble]];
    }

    private function accessBubble(Team $team, ?Contact $contact): ?string
    {
        if ($contact === null)
        {
            return null;
        }

        $user = $this->ensureContactUser($team, $contact);
        if ($user === null)
        {
            return null;
        }

        $resetUrl = $this->accessResetUrl($user);

        return 'Te paso el acceso para '.$user->email.'. Elegí tu clave en este enlace y entras directo:'."\n".$resetUrl;
    }

    private function ensureContactUser(Team $team, Contact $contact): ?User
    {
        if ($this->contactHasLogin($contact))
        {
            return User::query()->find($contact->user_id);
        }

        $email = trim((string) ($contact->email ?? ''));
        if ($email === '' || NewUserWelcomeEmailNotifier::isPlaceholderInboxEmail($email))
        {
            return null;
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null)
        {
            $contact->update(['user_id' => $existing->id]);
            if (! $existing->teams->contains($team->id))
            {
                $existing->teams()->attach($team->id);
            }

            return $existing;
        }

        $user = User::query()->create([
            'name' => trim((string) $contact->name) !== '' ? trim((string) $contact->name) : 'Cliente',
            'email' => $email,
            'phone' => preg_replace('/[^0-9]/', '', (string) ($contact->phone ?? '')) ?: null,
            'password' => Hash::make(Str::random(24)),
        ]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole('client');
        $user->teams()->attach($team->id);
        $contact->update(['user_id' => $user->id]);

        return $user;
    }

    private function accessResetUrl(User $user): string
    {
        return PasswordResetFrontendUrl::urlForUser($user, Password::broker()->createToken($user));
    }

    private function contactHasLogin(Contact $contact): bool
    {
        return filled($contact->user_id);
    }
}
