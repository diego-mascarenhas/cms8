<?php

namespace Tests\Unit;

use App\Services\ControlPanel\CpanelConnector;
use PHPUnit\Framework\TestCase;

class CpanelRuntimeInterpreterTest extends TestCase
{
    public function test_php_version_reads_the_whm_vhost_list(): void
    {
        $connector = new CpanelConnector;

        $version = $connector->interpretPhpVersionPayload([
            'data' => [
                'versions' => [
                    ['vhost' => 'otro.test', 'version' => 'ea-php81'],
                    ['vhost' => 'lizama.test', 'version' => 'ea-php82'],
                ],
            ],
        ], 'lizama.test');

        $this->assertSame('8.2', $version);
        $this->assertSame('8.3', $connector->interpretPhpVersion('ea-php83'));
    }

    public function test_site_type_comes_from_the_public_files(): void
    {
        $connector = new CpanelConnector;

        $this->assertSame('WordPress', $connector->interpretSiteFiles(['index.php', 'wp-config.php']));
        $this->assertSame('Laravel', $connector->interpretSiteFiles(['artisan', 'public']));
        $this->assertSame('HTML estático', $connector->interpretSiteFiles(['index.html']));
        $this->assertNull($connector->interpretSiteFiles(['index.php']));
    }
}
