<?php

namespace Tests\Unit;

use Tests\TestCase;

class VerticalMenuMessagesMailerTest extends TestCase
{
    public function test_messages_menu_item_targets_mailer_message_list(): void
    {
        $path = resource_path('menu/verticalMenu.json');
        $this->assertFileExists($path);

        /** @var array{menu: array<int, array<string, mixed>>} $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $messages = null;
        $campaigns = null;
        foreach ($data['menu'] as $item)
        {
            if (($item['name'] ?? null) === 'Messages')
            {
                $messages = $item;
            }
            if (($item['name'] ?? null) === 'Campaigns')
            {
                $campaigns = $item;
            }
        }

        $this->assertNotNull($campaigns);
        $this->assertSame('campaigns', $campaigns['url']);
        $this->assertSame('campaigns', $campaigns['slug']);
        $this->assertSame('campaigns', $campaigns['module_key']);

        $this->assertNotNull($messages);
        $this->assertSame('message/list', $messages['url']);
        $this->assertSame('mailer', $messages['module_key']);
        $this->assertSame('message.index', $messages['slug']);
        $this->assertStringContainsString('ti-send', (string) ($messages['icon'] ?? ''));

        $chatMenuItems = array_filter(
            $data['menu'],
            static fn (array $item): bool => ($item['name'] ?? null) === 'Chat',
        );
        $this->assertCount(0, $chatMenuItems);

        $affiliates = null;
        foreach ($data['menu'] as $item)
        {
            if (($item['name'] ?? null) === 'Affiliates')
            {
                $affiliates = $item;
                break;
            }
        }

        $this->assertNotNull($affiliates);
        $this->assertSame('billing', $affiliates['url']);
        $this->assertSame('billing.index', $affiliates['slug']);
        $this->assertSame('affiliates', $affiliates['module_key']);
        $this->assertStringContainsString('ti-affiliate', (string) ($affiliates['icon'] ?? ''));
    }
}
