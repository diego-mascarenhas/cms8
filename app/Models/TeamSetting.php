<?php

namespace App\Models;

use App\Support\ShopCatalogApiCache;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class TeamSetting extends Model
{
    /** @var list<string> */
    private const SHOP_CATALOG_SETTING_KEYS = [
        'business_config',
        'public_catalog_enabled',
    ];

    protected $fillable = [
        'team_id',
        'key',
        'value',
        'type',
        'group',
        'is_encrypted',
        'description',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    protected static function booted(): void
    {
        $bumpCatalog = function (self $setting): void
        {
            if (! $setting->team_id || ! in_array($setting->key, self::SHOP_CATALOG_SETTING_KEYS, true))
            {
                return;
            }

            ShopCatalogApiCache::bumpTeam((int) $setting->team_id);
        };

        static::saved($bumpCatalog);
        static::deleted($bumpCatalog);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value)
        {
            try
            {
                $value = Crypt::decryptString($value);
            } catch (DecryptException $e)
            {
                Log::warning('TeamSetting decryption failed (key changed or corrupted)', [
                    'team_id' => $this->team_id,
                    'key' => $this->key,
                    'exception' => $e->getMessage(),
                ]);
                $value = null;
            }
        }

        switch ($this->type)
        {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    public function setValueAttribute($value)
    {
        if ($this->type === 'json' && is_array($value))
        {
            $value = json_encode($value);
        }

        if ($this->is_encrypted && $value)
        {
            $value = Crypt::encryptString($value);
        }

        $this->attributes['value'] = $value;
    }
}
