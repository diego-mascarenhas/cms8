<?php

namespace Tests\Unit\Support;

use App\Support\MailExchange;
use PHPUnit\Framework\TestCase;

class MailExchangeTest extends TestCase
{
    public function test_google_uses_the_workspace_mx_set(): void
    {
        $records = MailExchange::records('google');

        $this->assertSame('aspmx.l.google.com', $records[0]['target']);
        $this->assertCount(5, $records);
    }

    public function test_outlook_uses_the_given_host(): void
    {
        $records = MailExchange::records('outlook', 'Empresa.mail.protection.outlook.com.');

        $this->assertSame([
            ['priority' => 0, 'target' => 'empresa.mail.protection.outlook.com'],
        ], $records);
    }

    public function test_hosting_mail_does_not_change_mx(): void
    {
        $this->assertSame([], MailExchange::records('hosting'));
    }
}
