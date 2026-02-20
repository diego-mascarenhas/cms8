<?php

namespace App\Services;

use App\Models\Team;

class WordPressContextService
{
    public function __construct(
        protected WordPressService $wp,
    ) {}

    public static function forTeam(Team $team): self
    {
        return new self(new WordPressService($team));
    }

    /**
     * Build a Markdown context string with WordPress content (posts, pages, products).
     * Used to replace {{WORDPRESS_CONTEXT}} in prompt instructions.
     */
    public function buildContext(): string
    {
        if (! $this->wp->isConfigured())
        {
            return '_WordPress no está configurado para este equipo._';
        }

        $lines = ['## Contenido del sitio WordPress', ''];

        // Pages
        $pages = $this->wp->getPages(perPage: 30);
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

        // Posts
        $posts = $this->wp->getPosts(perPage: 30);
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

        // WooCommerce products (optional — only if the endpoint is available)
        $products = $this->wp->getProducts(perPage: 30);
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
