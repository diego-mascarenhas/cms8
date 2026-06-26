<?php

namespace Tests\Unit;

use App\Models\Server;
use PHPUnit\Framework\TestCase;

class ServerHostnameNormalizationTest extends TestCase
{
    public function test_normalize_hostname_strips_https_scheme(): void
    {
        $this->assertSame('huginn.revisionalpha.cloud', Server::normalizeHostname('https://huginn.revisionalpha.cloud'));
    }

    public function test_normalize_hostname_strips_malformed_https_prefix(): void
    {
        $this->assertSame('huginn.revisionalpha.cloud', Server::normalizeHostname('https//huginn.revisionalpha.cloud'));
    }

    public function test_normalize_hostname_strips_path_and_trailing_slash(): void
    {
        $this->assertSame('huginn.revisionalpha.cloud', Server::normalizeHostname('https://huginn.revisionalpha.cloud/'));
    }

    public function test_normalize_hostname_keeps_plain_hostname(): void
    {
        $this->assertSame('cpanel.test', Server::normalizeHostname('cpanel.test'));
    }
}
