<?php

namespace App\Services\Go;

use App\Models\GoLink;
use App\Models\Team;
use Illuminate\Support\Str;

class GoLinkService
{
    private const CODE_ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    private const CODE_LENGTH = 8;

    public function ensureShopCatalogQr(Team $team): ?GoLink
    {
        $targetUrl = $team->publicCatalogShopUrl();
        if ($targetUrl === null || $targetUrl === '')
        {
            return null;
        }

        $link = GoLink::query()
            ->where('team_id', $team->id)
            ->where('type', GoLink::TYPE_SHOP_CATALOG_QR)
            ->first();

        if ($link)
        {
            if ($link->target_url !== $targetUrl || ! $link->active)
            {
                $link->forceFill([
                    'target_url' => $targetUrl,
                    'active' => true,
                ])->save();
            }

            return $link->fresh();
        }

        return GoLink::query()->create([
            'team_id' => $team->id,
            'code' => $this->uniqueCode(),
            'type' => GoLink::TYPE_SHOP_CATALOG_QR,
            'target_url' => $targetUrl,
            'active' => true,
        ]);
    }

    public function findActiveByCode(string $code): ?GoLink
    {
        $code = Str::lower(trim($code));
        if ($code === '')
        {
            return null;
        }

        return GoLink::query()
            ->where('code', $code)
            ->where('active', true)
            ->first();
    }

    public function uniqueCode(): string
    {
        do
        {
            $code = $this->randomCode();
        } while (GoLink::query()->where('code', $code)->exists());

        return $code;
    }

    private function randomCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++)
        {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
