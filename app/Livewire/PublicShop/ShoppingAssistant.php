<?php

namespace App\Livewire\PublicShop;

use App\Enums\ProductCatalogStatus;
use App\Models\Product;
use App\Models\Team;
use App\Services\BusinessAssistantContextService;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Livewire\Attributes\Locked;
use Livewire\Component;

use function Laravel\Ai\agent;

class ShoppingAssistant extends Component
{
    #[Locked]
    public ?int $teamId = null;

    #[Locked]
    public string $slug = '';

    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public string $input = '';

    public bool $loading = false;

    /** @var array<string, int> */
    public array $cart = [];

    public string $shopperAge = '';

    public string $shopperNotes = '';

    /** @var array<int, int> */
    public array $suggestedProductIds = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $team = Team::findForPublicCatalog($slug);
        if (! $team)
        {
            abort(404);
        }
        $this->teamId = $team->id;
        $config = $team->getDecodedBusinessConfig();
        $shopName = trim((string) ($config['business_name'] ?? $team->name));
        $this->messages[] = [
            'role' => 'assistant',
            'content' => __('public_shop.welcome', ['shop' => $shopName]),
        ];
        $this->messages[] = [
            'role' => 'assistant',
            'content' => __('public_shop.ask_profile'),
        ];
        $this->bindPublicShopCart();
        $this->syncCartArrayFromCart();
    }

    /**
     * cart_storage row id: team + Laravel session (WhatsApp cart uses phone as session key).
     */
    protected function publicShopCartStorageId(): string
    {
        $team = $this->team();

        return 'pubshop_'.($team?->id ?? '0').'_'.session()->getId();
    }

    protected function bindPublicShopCart(): void
    {
        Cart::session($this->publicShopCartStorageId());
    }

    /**
     * @return array<string, int>
     */
    protected function syncCartArrayFromCart(): array
    {
        $this->bindPublicShopCart();
        $next = [];
        foreach (Cart::getContent() as $item)
        {
            $next[(string) $item->id] = (int) $item->quantity;
        }
        $this->cart = $next;

        return $this->cart;
    }

    protected function team(): ?Team
    {
        if ($this->teamId)
        {
            $byId = Team::query()->find($this->teamId);
            if ($byId)
            {
                return $byId;
            }
        }

        return $this->slug !== '' ? Team::findForPublicCatalog($this->slug) : null;
    }

    /**
     * @return Collection<int, Product>
     */
    protected function publishedProducts(): Collection
    {
        $team = $this->team();
        if (! $team)
        {
            return collect();
        }

        return Product::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('catalog_status', ProductCatalogStatus::Publish)
            ->with(['currency', 'category'])
            ->orderBy('name')
            ->get();
    }

    public function sendMessage(): void
    {
        $text = trim($this->input);
        if ($text === '' || $this->loading)
        {
            return;
        }
        $this->input = '';
        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->ingestShopperHints($text);
        $this->loading = true;

        $reply = $this->produceAssistantReply($text);
        $this->messages[] = ['role' => 'assistant', 'content' => $reply['text']];
        $this->suggestedProductIds = $reply['suggest_ids'];
        $this->loading = false;
        $this->dispatch('scroll-to-bottom');
    }

    public function resetConversation(): void
    {
        $team = $this->team();
        if (! $team)
        {
            return;
        }
        $config = $team->getDecodedBusinessConfig();
        $shopName = trim((string) ($config['business_name'] ?? $team->name));
        $this->messages = [
            ['role' => 'assistant', 'content' => __('public_shop.welcome', ['shop' => $shopName])],
            ['role' => 'assistant', 'content' => __('public_shop.ask_profile')],
        ];
        $this->suggestedProductIds = [];
        $this->input = '';
        $this->shopperAge = '';
        $this->shopperNotes = '';
        $this->dispatch('scroll-to-bottom');
    }

    protected function ingestShopperHints(string $text): void
    {
        if (preg_match('/\b(\d{1,2})\s*(?:años|año)\b/iu', $text, $m))
        {
            $this->shopperAge = $m[1];
        } elseif (preg_match('/\bedad\b[^0-9]{0,12}\b(\d{1,2})\b/iu', $text, $m2))
        {
            $this->shopperAge = $m2[1];
        }
        $this->shopperNotes = mb_substr(trim($this->shopperNotes.' '.$text), 0, 600);
    }

    /**
     * @return array{text: string, suggest_ids: array<int, int>}
     */
    protected function produceAssistantReply(string $lastUserMessage): array
    {
        $team = $this->team();
        if (! $team)
        {
            return ['text' => __('public_shop.error'), 'suggest_ids' => []];
        }

        $products = $this->publishedProducts();
        $catalogLines = $products->map(fn (Product $p) => $p->id.'|'.$p->name.'|'.$p->currentSellingPrice().'|'.($p->currency?->code ?? 'ARS'))->implode("\n");
        $config = $team->getDecodedBusinessConfig();
        $businessName = trim((string) ($config['business_name'] ?? $team->name));
        $industry = trim((string) ($config['business_industry'] ?? ''));

        $history = '';
        $tail = array_slice($this->messages, -8);
        foreach ($tail as $m)
        {
            $history .= ($m['role'] === 'user' ? 'Cliente: ' : 'Asistente: ').$m['content']."\n";
        }

        $profile = 'Edad aproximada: '.($this->shopperAge !== '' ? $this->shopperAge : 'no indicada')
            .'. Gustos/notas: '.($this->shopperNotes !== '' ? $this->shopperNotes : 'no indicados');

        $businessAppendix = trim(app(BusinessAssistantContextService::class)->buildMarkdownAppendix($team->id));
        $contextPrefix = "Eres el asistente de compra de \"{$businessName}\" (rubro: {$industry}).";
        if ($businessAppendix !== '')
        {
            $contextPrefix .= "\n\n---\n\n".$businessAppendix;
        }

        $instructions = <<<TXT
{$contextPrefix}

Catálogo (id|nombre|precio|moneda), solo estos existen:
{$catalogLines}

{$profile}

Último mensaje del cliente: {$lastUserMessage}

Historial reciente:
{$history}

Instrucciones:
- Responde en español, tono cercano y breve (máximo 4 frases).
- Si faltan datos para recomendar bien, pregunta de forma natural por edad o gustos/preferencias.
- Si recomiendas productos, termina con UNA línea exacta: [[SUGGEST:1,2,3]] usando hasta 3 IDs del catálogo anteriores (solo números separados por comas).
- No inventes IDs ni productos.
TXT;

        try
        {
            $ag = agent(
                instructions: 'Sigue el bloque de instrucciones que recibes como mensaje del usuario.',
                messages: [],
                tools: [],
            );
            $res = $ag->prompt($instructions, [], Lab::Anthropic);
            $full = trim((string) ($res->text ?? ''));
        } catch (\Throwable $e)
        {
            Log::warning('public_shop.assistant_ai_failed', ['error' => $e->getMessage(), 'team_id' => $this->teamId]);
            $full = $this->fallbackAssistantText($lastUserMessage, $products);
        }

        return $this->parseAssistantResponse($full, $products);
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    protected function fallbackAssistantText(string $lastUserMessage, Collection $products): string
    {
        $keywords = preg_split('/\s+/', mb_strtolower($lastUserMessage)) ?: [];
        $keywords = array_values(array_filter($keywords, fn ($w) => mb_strlen($w) > 2));
        $matches = $products->filter(function (Product $p) use ($keywords)
        {
            $hay = mb_strtolower($p->name.' '.($p->description ?? '').' '.($p->short_description ?? ''));
            foreach ($keywords as $kw)
            {
                if (str_contains($hay, $kw))
                {
                    return true;
                }
            }

            return false;
        })->take(3);

        if ($matches->isEmpty())
        {
            return __('public_shop.fallback_no_match').' [[SUGGEST:]]';
        }

        $ids = $matches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $names = $matches->pluck('name')->implode(', ');

        return __('public_shop.fallback_matches', ['names' => $names]).' [[SUGGEST:'.implode(',', $ids).']]';
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array{text: string, suggest_ids: array<int, int>}
     */
    protected function parseAssistantResponse(string $full, Collection $products): array
    {
        $ids = [];
        if (preg_match('/\[\[SUGGEST:([\d,\s]*)\]\]/', $full, $m))
        {
            $ids = array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', trim($m[1])))));
        }
        $text = trim(preg_replace('/\[\[SUGGEST:[\d,\s]*\]\]\s*$/', '', $full) ?? $full);

        $validIds = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ids = array_values(array_filter($ids, fn ($id) => in_array($id, $validIds, true)));
        $ids = array_slice($ids, 0, 6);

        return ['text' => $text !== '' ? $text : __('public_shop.empty_reply'), 'suggest_ids' => $ids];
    }

    public function addToCart(int $productId): void
    {
        $team = $this->team();
        $products = $this->publishedProducts();
        $product = $products->firstWhere('id', $productId);
        if (! $team || $product === null)
        {
            return;
        }

        $this->bindPublicShopCart();
        $existingItem = Cart::getContent()->firstWhere('id', $productId);

        if ($existingItem)
        {
            Cart::update($productId, [
                'quantity' => [
                    'relative' => false,
                    'value' => $existingItem->quantity + 1,
                ],
            ]);
        } else
        {
            Cart::add([
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->currentSellingPrice(),
                'quantity' => 1,
                'attributes' => [
                    'team_id' => $team->id,
                    'store_id' => $product->store_id,
                    'currency_id' => $product->currency_id,
                    'description' => $product->description,
                    'category_name' => $product->category->name ?? '',
                ],
            ]);
        }

        $this->syncCartArrayFromCart();
    }

    public function decrementCart(int $productId): void
    {
        $this->bindPublicShopCart();
        $item = Cart::getContent()->firstWhere('id', $productId);
        if (! $item)
        {
            $this->syncCartArrayFromCart();

            return;
        }

        $currentQty = (int) $item->quantity;
        $newQty = $currentQty - 1;

        if ($newQty <= 0)
        {
            Cart::remove($productId);
        } else
        {
            Cart::update($productId, [
                'quantity' => [
                    'relative' => false,
                    'value' => $newQty,
                ],
            ]);
        }

        $this->syncCartArrayFromCart();
    }

    public function checkoutWhatsApp()
    {
        $team = $this->team();
        if (! $team)
        {
            return null;
        }
        $digits = $team->catalogCheckoutWhatsAppDigits();
        if (! $digits)
        {
            $this->messages[] = ['role' => 'assistant', 'content' => __('public_shop.no_whatsapp')];

            return null;
        }
        $this->bindPublicShopCart();
        $cartItems = Cart::getContent();

        if ($cartItems->isEmpty())
        {
            $this->messages[] = ['role' => 'assistant', 'content' => __('public_shop.empty_cart')];

            return null;
        }

        $lines = [];
        $products = $this->publishedProducts()->keyBy('id');
        foreach ($cartItems as $row)
        {
            $p = $products->get((int) $row->id);
            if (! $p)
            {
                continue;
            }
            $qty = (int) $row->quantity;
            $price = $p->currentSellingPrice();
            $code = $p->currency?->code ?? 'ARS';
            $lines[] = $qty.' × '.$p->name.' — '.$price.' '.$code;
        }
        $profile = 'Cliente: edad ~'.($this->shopperAge ?: '?').'. Notas: '.($this->shopperNotes ?: '—');
        $body = __('public_shop.wa_order_intro')."\n\n".implode("\n", $lines)."\n\n".$profile;
        $url = 'https://wa.me/'.$digits.'?text='.rawurlencode($body);

        Cart::clear();
        $this->cart = [];

        return redirect()->away($url);
    }

    public function render()
    {
        $team = $this->team();
        $productsById = $this->publishedProducts()->keyBy('id');

        return view('livewire.public-shop.shopping-assistant', [
            'team' => $team,
            'productsById' => $productsById,
        ]);
    }
}
