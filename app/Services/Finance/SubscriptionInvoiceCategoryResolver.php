<?php

namespace App\Services\Finance;

use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Module;
use App\Models\ServiceSync;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class SubscriptionInvoiceCategoryResolver
{
    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @return array<int, array{category_id: int|null, category_name: string|null, type: string|null, group: string|null}>
     */
    public function hintsFor(Collection $items): array
    {
        $items->loadMissing('invoice');

        $hints = [];

        foreach ($items->groupBy(fn (InvoiceItem $item): int => (int) ($item->invoice?->team_id ?? 0)) as $teamId => $teamItems)
        {
            if ((int) $teamId < 1)
            {
                continue;
            }

            $catalog = $this->catalog((int) $teamId);
            $subscriptions = $this->subscriptionsFor($teamItems);

            foreach ($teamItems as $item)
            {
                $invoice = $item->invoice;
                $reference = trim((string) ($invoice?->source_reference_id ?? ''));
                $sync = $reference !== '' ? ($subscriptions[$reference] ?? null) : null;
                $signals = $this->signals($sync);
                $currentId = $item->category_id ? (int) $item->category_id : null;
                $replace = $currentId === null || in_array($currentId, $catalog['generic_ids'], true);
                $matched = $replace
                    ? $this->matchInCatalog($catalog, $signals, (string) $item->description, (string) ($sync?->plan_name ?? ''))
                    : null;

                if ($signals['type'] === null && $signals['group'] === null && $matched === null)
                {
                    continue;
                }

                $hints[(int) $item->id] = [
                    'category_id' => $matched['id'] ?? null,
                    'category_name' => $matched['name'] ?? null,
                    'type' => $signals['type'],
                    'group' => $signals['group'],
                ];
            }
        }

        return $hints;
    }

    public function matchCategoryId(int $teamId, ?ServiceSync $sync, string $description): ?int
    {
        $matched = $this->matchInCatalog(
            $this->catalog($teamId),
            $this->signals($sync),
            $description,
            (string) ($sync?->plan_name ?? ''),
        );

        return $matched['id'] ?? null;
    }

    public function isGenericParent(int $categoryId): bool
    {
        return Category::query()
            ->where('parent_id', $categoryId)
            ->where('status', '>', 0)
            ->exists();
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @return array<string, ServiceSync>
     */
    private function subscriptionsFor(Collection $items): array
    {
        $references = $items
            ->map(fn (InvoiceItem $item): string => trim((string) ($item->invoice?->source_reference_id ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($references->isEmpty())
        {
            return [];
        }

        $teamIds = $items
            ->map(fn (InvoiceItem $item): int => (int) ($item->invoice?->team_id ?? 0))
            ->filter()
            ->unique()
            ->values();

        $invoiceSyncs = InvoiceSync::query()
            ->where('provider', 'stripe')
            ->whereIn('team_id', $teamIds->all())
            ->whereIn('external_id', $references->all())
            ->get(['external_id', 'stripe_subscription_id']);

        $subscriptionIds = $invoiceSyncs->pluck('stripe_subscription_id')->filter()->unique()->values();
        if ($subscriptionIds->isEmpty())
        {
            return [];
        }

        $subscriptions = ServiceSync::query()
            ->whereIn('team_id', $teamIds->all())
            ->whereIn('stripe_id', $subscriptionIds->all())
            ->get()
            ->keyBy(fn (ServiceSync $sync): string => (string) $sync->stripe_id);

        $byInvoice = [];
        foreach ($invoiceSyncs as $invoiceSync)
        {
            $subscription = $subscriptions->get((string) $invoiceSync->stripe_subscription_id);
            if ($subscription instanceof ServiceSync)
            {
                $byInvoice[(string) $invoiceSync->external_id] = $subscription;
            }
        }

        return $byInvoice;
    }

    /**
     * @return array{
     *     generic_ids: list<int>,
     *     leaves: list<array{id: int, name: string, tokens: list<string>, parent_name: string}>
     * }
     */
    private function catalog(int $teamId): array
    {
        $moduleId = Module::query()->where('key', 'services')->value('id');

        $categories = Category::query()
            ->where('status', '>', 0)
            ->where(function ($query) use ($teamId): void
            {
                $query->whereNull('team_id')->orWhere('team_id', $teamId);
            })
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->get(['id', 'name', 'parent_id']);

        $byId = $categories->keyBy(fn (Category $category): int => (int) $category->id);
        $childParentIds = $categories
            ->pluck('parent_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $leaves = [];
        foreach ($categories as $category)
        {
            if (in_array((int) $category->id, $childParentIds, true))
            {
                continue;
            }

            $parent = $category->parent_id ? $byId->get((int) $category->parent_id) : null;
            $root = $parent instanceof Category ? $this->rootCategory($parent, $byId) : $category;

            $leaves[] = [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'tokens' => $this->tokens((string) $category->name),
                'parent_name' => (string) $root->name,
            ];
        }

        return [
            'generic_ids' => array_values(array_map('intval', $childParentIds)),
            'leaves' => $leaves,
        ];
    }

    /**
     * @param  Collection<int|string, Category>  $byId
     */
    private function rootCategory(Category $category, Collection $byId): Category
    {
        $current = $category;
        $guard = 0;

        while ($current->parent_id && $guard < 6)
        {
            $parent = $byId->get((int) $current->parent_id);
            if (! $parent instanceof Category)
            {
                break;
            }

            $current = $parent;
            $guard++;
        }

        return $current;
    }

    /**
     * @return array{type: string|null, category: string|null, group: string|null}
     */
    private function signals(?ServiceSync $sync): array
    {
        $metadata = is_array($sync?->raw_payload) ? Arr::get($sync->raw_payload, 'metadata', []) : [];
        $metadata = is_array($metadata) ? $metadata : [];
        $dataCategory = $this->normalize((string) data_get($sync?->data, 'category', ''));
        $type = $this->normalize((string) ($metadata['type'] ?? ''));
        $category = $this->normalize((string) ($metadata['category'] ?? ''));

        if ($type === '' && $dataCategory !== '' && $this->groupNameForType($dataCategory) !== null)
        {
            $type = $dataCategory;
        }

        if ($category === '' && $dataCategory !== '' && $dataCategory !== $type)
        {
            $category = $dataCategory;
        }

        if ($category !== '' && $this->groupNameForType($category) !== null)
        {
            $category = null;
        }

        $type = $type !== '' ? $type : null;
        $category = $category !== '' ? $category : null;

        return [
            'type' => $type,
            'category' => $category,
            'group' => $type !== null ? $this->groupNameForType($type) : null,
        ];
    }

    /**
     * @param  array{generic_ids: list<int>, leaves: list<array{id: int, name: string, tokens: list<string>, parent_name: string}>}  $catalog
     * @param  array{type: string|null, category: string|null, group: string|null}  $signals
     * @return array{id: int, name: string}|null
     */
    private function matchInCatalog(array $catalog, array $signals, string $description, string $planName): ?array
    {
        $candidates = $catalog['leaves'];

        if ($signals['group'] !== null)
        {
            $group = $this->normalize($signals['group']);
            $candidates = array_values(array_filter(
                $candidates,
                fn (array $leaf): bool => $this->normalize($leaf['parent_name']) === $group,
            ));
        }

        if ($signals['type'] === 'vps')
        {
            $candidates = array_values(array_filter(
                $candidates,
                fn (array $leaf): bool => in_array('vps', $leaf['tokens'], true),
            ));
        }

        if ($candidates === [])
        {
            return null;
        }

        if ($signals['category'] !== null)
        {
            $exact = $this->exactLeaf($candidates, $signals['category']);
            if ($exact !== null)
            {
                return $exact;
            }

            $fromMetadata = $this->bestLeaf($candidates, $signals['category']);
            if ($fromMetadata !== null)
            {
                return $fromMetadata;
            }
        }

        foreach ([$this->productLabel($description), $this->planLabel($planName)] as $label)
        {
            if ($label === null)
            {
                continue;
            }

            $matched = $this->bestLeaf($candidates, $label);
            if ($matched !== null)
            {
                return $matched;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int, name: string, tokens: list<string>, parent_name: string}>  $candidates
     * @return array{id: int, name: string}|null
     */
    private function exactLeaf(array $candidates, string $label): ?array
    {
        $normalized = $this->normalize($label);

        foreach ($candidates as $leaf)
        {
            if ($this->normalize($leaf['name']) === $normalized)
            {
                return [
                    'id' => $leaf['id'],
                    'name' => $leaf['name'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int, name: string, tokens: list<string>, parent_name: string}>  $candidates
     * @return array{id: int, name: string}|null
     */
    private function bestLeaf(array $candidates, string $label): ?array
    {
        $wanted = $this->tokens($label);
        if ($this->isGenericLabel($wanted))
        {
            return null;
        }

        $best = null;
        $bestExtra = null;
        $tied = false;

        foreach ($candidates as $leaf)
        {
            $missing = array_diff($wanted, $leaf['tokens']);
            if ($missing !== [])
            {
                continue;
            }

            $extra = count(array_diff($leaf['tokens'], $wanted));
            if ($best === null || $extra < $bestExtra)
            {
                $best = $leaf;
                $bestExtra = $extra;
                $tied = false;

                continue;
            }

            if ($extra === $bestExtra)
            {
                $tied = true;
            }
        }

        if ($best === null || $tied)
        {
            return null;
        }

        return [
            'id' => $best['id'],
            'name' => $best['name'],
        ];
    }

    private function productLabel(string $description): ?string
    {
        $description = trim($description);
        if ($description === '')
        {
            return null;
        }

        if (preg_match('/^(?:\d+\s*[×x]\s*)?(.+?)(?:\s*\(at\b.*)?$/iu', $description, $matches) !== 1)
        {
            return null;
        }

        $label = trim((string) ($matches[1] ?? ''));

        return $this->isGenericLabel($this->tokens($label)) ? null : $label;
    }

    private function planLabel(string $planName): ?string
    {
        $tokens = array_values(array_filter(
            $this->tokens($planName),
            fn (string $token): bool => ! str_contains($token, '.'),
        ));

        if ($this->isGenericLabel($tokens))
        {
            return null;
        }

        return implode(' ', $tokens);
    }

    private function groupNameForType(string $type): ?string
    {
        return match ($this->normalize($type))
        {
            'hosting', 'cpanel', 'vps', 'dominio', 'dominios', 'domain', 'ssl' => 'Hosting',
            'emailer', 'mail', 'smtp' => 'Emailer',
            'streaming' => 'Streaming',
            'datacenter' => 'Datacenter',
            default => null,
        };
    }

    /**
     * @param  list<string>  $tokens
     */
    private function isGenericLabel(array $tokens): bool
    {
        if ($tokens === [])
        {
            return true;
        }

        $generic = ['hosting', 'vps', 'dominio', 'dominios', 'emailer', 'servicio', 'servicios', 'plan'];

        return count(array_diff($tokens, $generic)) === 0;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $parts = preg_split('/\s+/', $this->normalize($value)) ?: [];
        $stopwords = ['de', 'y', 'la', 'el', 'los', 'las', 'con', 'para', 'por', 'en', 'del', 'al', 'the', 'of', 'and', 'a'];

        return array_values(array_filter(
            $parts,
            fn (string $token): bool => $token !== '' && ! in_array($token, $stopwords, true),
        ));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9.]+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
