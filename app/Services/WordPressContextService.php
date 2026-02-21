<?php

namespace App\Services;

use App\Models\Team;
use App\Models\WordPressSyncPage;
use App\Models\WordPressSyncPost;
use App\Models\WordPressSyncProduct;

class WordPressContextService
{
    public function __construct(
        protected WordPressService $wp,
        protected ?Team $team = null,
    ) {}

    public static function forTeam(Team $team): self
    {
        return new self(new WordPressService($team), $team);
    }

    /**
     * Build a Markdown context string with WordPress content (posts, pages, products).
     * Reads from synced tables when available; falls back to live API otherwise.
     */
    public function buildContext(): string
    {
        if (! $this->wp->isConfigured())
        {
            return '_WordPress no está configurado para este equipo._';
        }

        if ($this->team !== null)
        {
            $fromDb = $this->buildContextFromSync();
            if ($fromDb !== null)
            {
                return $fromDb;
            }
        }

        return $this->buildContextFromApi();
    }

    /**
     * Build context from synced DB tables. Returns null if no synced data.
     */
    protected function buildContextFromSync(): ?string
    {
        $teamId = $this->team->id;

        $pages = WordPressSyncPage::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->orderBy('title')
            ->get();

        $posts = WordPressSyncPost::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->orderBy('title')
            ->get();

        $products = WordPressSyncProduct::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        if ($pages->isEmpty() && $posts->isEmpty() && $products->isEmpty())
        {
            return null;
        }

        $lines = ['## Contenido del sitio WordPress', ''];

        $siteUrl = $this->wp->getSiteUrl();
        if ($siteUrl)
        {
            array_unshift($lines, "**URL del sitio:** {$siteUrl}", '');
        }

        if ($pages->isNotEmpty())
        {
            $lines[] = '### Páginas';
            foreach ($pages as $page)
            {
                $lines[] = "- **{$page->title}** (ID: {$page->wp_id}, estado: {$page->status})";
            }
            $lines[] = '';
        }

        if ($posts->isNotEmpty())
        {
            $lines[] = '### Entradas';
            foreach ($posts as $post)
            {
                $lines[] = "- **{$post->title}** (ID: {$post->wp_id}, estado: {$post->status})";
            }
            $lines[] = '';
        }

        if ($products->isNotEmpty())
        {
            $lines[] = '### Productos (WooCommerce)';
            foreach ($products as $product)
            {
                $price = $product->price ? " — {$product->price} {$product->currency}" : '';
                $lines[] = "- **{$product->name}** (ID: {$product->wp_id}, estado: {$product->status}{$price})";
                if (! empty($product->description))
                {
                    $desc = strip_tags($product->description);
                    $desc = strlen($desc) > 500 ? substr($desc, 0, 497).'…' : $desc;
                    $lines[] = '  '.str_replace("\n", ' ', $desc);
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Build context from live WordPress API (fallback when no sync data).
     */
    protected function buildContextFromApi(): string
    {
        $lines = ['## Contenido del sitio WordPress', ''];

        $pages = $this->wp->getPages(1, 30);
        if (! empty($pages))
        {
            $lines[] = '### Páginas';
            foreach ($pages as $page)
            {
                $title = strip_tags($page['title']['rendered'] ?? 'Sin título');
                $status = $page['status'] ?? '?';
                $id = $page['id'] ?? '?';
                $lines[] = "- **{$title}** (ID: {$id}, estado: {$status})";
            }
            $lines[] = '';
        }

        $posts = $this->wp->getPosts(1, 30);
        if (! empty($posts))
        {
            $lines[] = '### Entradas';
            foreach ($posts as $post)
            {
                $title = strip_tags($post['title']['rendered'] ?? 'Sin título');
                $status = $post['status'] ?? '?';
                $id = $post['id'] ?? '?';
                $lines[] = "- **{$title}** (ID: {$id}, estado: {$status})";
            }
            $lines[] = '';
        }

        $products = $this->wp->getProducts(1, 30);
        if (! empty($products))
        {
            $lines[] = '### Productos (WooCommerce)';
            foreach ($products as $product)
            {
                $title = strip_tags($product['name'] ?? 'Sin nombre');
                $status = $product['status'] ?? '?';
                $id = $product['id'] ?? '?';
                $currency = $product['currency'] ?? '';
                $price = isset($product['price']) ? " — {$product['price']} {$currency}" : '';
                $lines[] = "- **{$title}** (ID: {$id}, estado: {$status}{$price})";
                $desc = $product['short_description'] ?? $product['description'] ?? null;
                if (! empty($desc))
                {
                    $desc = strip_tags($desc);
                    $desc = strlen($desc) > 500 ? substr($desc, 0, 497).'…' : $desc;
                    $lines[] = '  '.str_replace("\n", ' ', $desc);
                }
            }
            $lines[] = '';
        }

        if (count($lines) <= 2)
        {
            return '_No se encontró contenido en el sitio WordPress._';
        }

        $siteUrl = $this->wp->getSiteUrl();
        if ($siteUrl)
        {
            array_unshift($lines, "**URL del sitio:** {$siteUrl}", '');
        }

        return implode("\n", $lines);
    }
}
