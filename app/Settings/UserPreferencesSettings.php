<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class UserPreferencesSettings extends Settings
{
    /**
     * When true, AI must not reply (opt-out). Default false: no row needed; chat responds.
     */
    public bool $chat_ai_assistance_blocked = false;

    /** Datatables state per table (e.g. columns_order, sort, visible_columns). */
    public array $datatables = [];

    public static function group(): string
    {
        return 'user_'.(auth()->id() ?? 0);
    }
}
