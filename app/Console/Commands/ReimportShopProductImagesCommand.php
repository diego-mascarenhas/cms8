<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductImageService;
use Illuminate\Console\Command;

class ReimportShopProductImagesCommand extends Command
{
    protected $signature = 'shop:reimport-product-images {--team= : Only this team id}';

    protected $description = 'Copy shop product photos into the team folder and register them in the media library';

    public function handle(ProductImageService $images): int
    {
        $teamId = $this->option('team');
        $query = Product::withoutGlobalScopes()
            ->whereNotNull('image')
            ->where('image', '!=', '');

        if (is_string($teamId) && $teamId !== '')
        {
            $query->where('team_id', (int) $teamId);
        }

        $counts = [
            'imported' => 0,
            'registered' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $query->orderBy('id')->each(function (Product $product) use ($images, &$counts): void
        {
            $result = $images->importProduct($product);
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
            $this->line(sprintf(
                '#%d team %d %s',
                $product->id,
                $product->team_id,
                $result['status'],
            ));
        });

        $this->info(sprintf(
            'Imported %d, registered %d, skipped %d, failed %d.',
            $counts['imported'],
            $counts['registered'],
            $counts['skipped'],
            $counts['failed'],
        ));

        return self::SUCCESS;
    }
}
