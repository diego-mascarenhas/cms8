<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Notification Email
    |--------------------------------------------------------------------------
    |
    | This email address will receive system reports and monitoring alerts.
    |
    */

    'notification_email' => env('NOTIFICATION_EMAIL', 'diego.mascarenhas@icloud.com'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Assistant default team (demo / unauthenticated)
    |--------------------------------------------------------------------------
    |
    | When the user has no current team, the assistant uses this team for
    | WordPress context (e.g. demo). Set ASSISTANT_DEFAULT_TEAM_ID= in .env
    | to disable. Later you can drive this via API token instead.
    |
    */
    'assistant_default_team_id' => env('ASSISTANT_DEFAULT_TEAM_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Assistant chat stub (testing)
    |--------------------------------------------------------------------------
    |
    | When true, the chat assistant returns a stub response instead of calling
    | Claude. Use for testing the flow without API usage. Can be overridden
    | per team via Team Settings > Chat / Asistente > Modo prueba.
    |
    */
    'assistant_chat_stub' => env('ASSISTANT_CHAT_STUB', false),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'es',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'es_ES',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        'path' => storage_path('framework/maintenance.php'),
        'template' => 'pages/misc-under-maintenance',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\TelescopeServiceProvider::class, // Telescope Dashboard
        App\Providers\FortifyServiceProvider::class,
        App\Providers\JetstreamServiceProvider::class,
        App\Providers\MenuServiceProvider::class,
        App\Providers\GrapesJsServiceProvider::class,
        Yajra\DataTables\DataTablesServiceProvider::class,
        Spatie\Permission\PermissionServiceProvider::class,
        App\Providers\BladeServiceProvider::class,
        App\Providers\CashierServiceProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
        'Helper' => App\Helpers\Helpers::class,
        'DataTables' => Yajra\DataTables\Facades\DataTables::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Support Number
    |--------------------------------------------------------------------------
    */
    'whatsapp_support' => env('WHATSAPP_SUPPORT_NUMBER', ''),

    /*
    |--------------------------------------------------------------------------
    | Google Analytics Measurement ID
    |--------------------------------------------------------------------------
    |
    | This value is your Google Analytics Measurement ID (e.g., G-XXXXXXXXXX).
    | Set this in your ".env" file.
    |
    */
    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Public home for guests (URL "/")
    |--------------------------------------------------------------------------
    |
    | Legacy: optional redirect targets if HomeController is used again. Guests
    | visiting "/" see the Humano landing (route humano). Authenticated users
    | are redirected to the dashboard from "/".
    |
    */
    'public_home_route' => env('PUBLIC_HOME_ROUTE'),
    'public_home_path' => env('PUBLIC_HOME_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Wapify: WhatsApp link for QR (chillerlan/php-qrcode) and buttons
    |--------------------------------------------------------------------------
    | Optional full URL for QR payload. If empty, built from phone + text.
    | Default phone 34613194131 when WAPIFY_WHATSAPP_PHONE is not set.
    */
    'wapify_whatsapp_link' => env('WAPIFY_WHATSAPP_LINK', ''),
    'wapify_whatsapp_phone' => env('WAPIFY_WHATSAPP_PHONE', '34613194131'),
    'wapify_whatsapp_text' => env('WAPIFY_WHATSAPP_TEXT', 'Hola!'),

    /*
    |--------------------------------------------------------------------------
    | Local-only: read production data via database.connections.prod_read
    |--------------------------------------------------------------------------
    |
    | When ALLOW_PROD_READ_TOGGLE=true and APP_ENV=local, authenticated users
    | can switch the default DB connection for the request (navbar). Use a
    | read-only database user on production. Never enable outside local.
    |
    */
    'allow_prod_read_toggle' => (bool) env('ALLOW_PROD_READ_TOGGLE', false),

    'prod_read_credentials_configured' => filled(env('DB_PROD_READ_HOST'))
        && (filled(env('DB_PROD_READ_DATABASE')) || filled(env('DB_PROD_READ_URL'))),

];
