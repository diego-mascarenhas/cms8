<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductionHealthTest extends TestCase
{
    /**
     * Test database connection and essential tables
     */
    public function test_database_connection_and_tables(): void
    {
        // Test database connection
        $this->assertNotNull(DB::connection()->getPdo(), 'Database connection failed');

        // Test essential tables exist
        $essentialTables = ['users', 'teams', 'messages', 'message_deliveries', 'contacts'];

        foreach ($essentialTables as $table) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasTable($table),
                "Essential table '{$table}' is missing"
            );
        }
    }

    /**
     * Test application configuration
     */
    public function test_application_configuration(): void
    {
        // APP_KEY must be set
        $this->assertNotEmpty(config('app.key'), 'APP_KEY is not set');

        // APP_URL should be set for production
        $this->assertNotEmpty(config('app.url'), 'APP_URL is not set');

        // Debug should be false in production
        if (config('app.env') === 'production') {
            $this->assertFalse(config('app.debug'), 'APP_DEBUG should be false in production');
        }
    }

    /**
     * Test cache functionality
     */
    public function test_cache_functionality(): void
    {
        $testKey = 'health_check_' . time();
        $testValue = 'test_value_' . rand(1000, 9999);

        // Test cache write
        Cache::put($testKey, $testValue, 60);

        // Test cache read
        $cachedValue = Cache::get($testKey);
        $this->assertEquals($testValue, $cachedValue, 'Cache read/write failed');

        // Clean up
        Cache::forget($testKey);
    }

    /**
     * Test file permissions for critical directories
     */
    public function test_file_permissions(): void
    {
        $criticalPaths = [
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($criticalPaths as $path) {
            $this->assertTrue(
                is_writable($path),
                "Critical path '{$path}' is not writable"
            );
        }
    }

    /**
     * Test session configuration
     */
    public function test_session_configuration(): void
    {
        // Session driver should be set
        $this->assertNotEmpty(config('session.driver'), 'Session driver not configured');

        // Session lifetime should be reasonable
        $lifetime = config('session.lifetime');
        $this->assertGreaterThan(0, $lifetime, 'Session lifetime should be greater than 0');
        $this->assertLessThanOrEqual(1440, $lifetime, 'Session lifetime should not exceed 24 hours');
    }

    /**
     * Test email configuration
     */
    public function test_email_configuration(): void
    {
        // Mail driver should be configured
        $this->assertNotEmpty(config('mail.default'), 'Mail driver not configured');

        // SMTP configuration should be present if using SMTP
        if (config('mail.default') === 'smtp') {
            $this->assertNotEmpty(config('mail.mailers.smtp.host'), 'SMTP host not configured');
        }
    }

    /**
     * Test essential models can be instantiated
     */
    public function test_essential_models(): void
    {
        $models = [
            \App\Models\User::class,
            \App\Models\Team::class,
            \App\Models\Message::class,
            \App\Models\MessageDelivery::class,
            \App\Models\Contact::class,
        ];

        foreach ($models as $modelClass) {
            $this->assertTrue(
                class_exists($modelClass),
                "Essential model '{$modelClass}' does not exist"
            );

            // Test model can be instantiated
            $model = new $modelClass();
            $this->assertInstanceOf($modelClass, $model);
        }
    }

    /**
     * Test tracking routes are accessible
     */
    public function test_tracking_routes(): void
    {
        // Test that tracking routes exist
        $this->assertTrue(
            \Route::has('message.track'),
            'Message tracking route does not exist'
        );

        $this->assertTrue(
            \Route::has('message.track.click'),
            'Message click tracking route does not exist'
        );
    }

    /**
     * Test MessageDelivery tracking functionality
     */
    public function test_message_delivery_tracking(): void
    {
        // Test that MessageDelivery has required methods
        $this->assertTrue(
            method_exists(\App\Models\MessageDelivery::class, 'getTrackingToken'),
            'MessageDelivery missing getTrackingToken method'
        );

        $this->assertTrue(
            method_exists(\App\Models\MessageDelivery::class, 'getTrackingUrl'),
            'MessageDelivery missing getTrackingUrl method'
        );

        $this->assertTrue(
            method_exists(\App\Models\MessageDelivery::class, 'getTextForWhatsApp'),
            'MessageDelivery missing getTextForWhatsApp method'
        );
    }
}
