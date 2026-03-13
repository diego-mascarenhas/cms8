<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class UserPreferencesSettings extends Settings
{
    public bool $chat_ai_toggle_default = true;

    /** @var array<string, mixed> Datatables state per table (e.g. columns_order, sort, visible_columns). */
    public array $datatables = [];

    public static function group(): string
    {
        return 'user_'.(auth()->id() ?? 0);
    }
}
