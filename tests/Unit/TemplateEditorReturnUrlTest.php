<?php

namespace Tests\Unit;

use App\Support\TemplateEditorReturnUrl;
use PHPUnit\Framework\TestCase;

class TemplateEditorReturnUrlTest extends TestCase
{
    public function test_merge_query_when_path_matches_appends_parameter(): void
    {
        $out = TemplateEditorReturnUrl::mergeQueryWhenPathMatches(
            'https://example.test/message/create?foo=1',
            '/message/create',
            ['template_id' => '99'],
        );

        $this->assertStringContainsString('template_id=99', (string) $out);
        $this->assertStringContainsString('foo=1', (string) $out);
    }

    public function test_merge_query_when_path_mismatch_leaves_url_unchanged(): void
    {
        $url = 'https://example.test/message/5/edit';
        $out = TemplateEditorReturnUrl::mergeQueryWhenPathMatches(
            $url,
            '/message/create',
            ['template_id' => '99'],
        );

        $this->assertSame($url, $out);
    }
}
