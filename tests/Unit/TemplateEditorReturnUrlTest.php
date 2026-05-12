<?php

namespace Tests\Unit;

use App\Support\TemplateEditorReturnUrl;
use Illuminate\Http\Request;
use Tests\TestCase;

class TemplateEditorReturnUrlTest extends TestCase
{
    public function test_validated_from_request_accepts_relative_path(): void
    {
        $request = Request::create('https://humano.test/template/x/editor', 'GET', [
            'return_url' => '/message/5/edit',
        ]);

        $this->assertSame('/message/5/edit', TemplateEditorReturnUrl::validatedFromRequest($request));
        $this->assertSame('/message/5/edit', TemplateEditorReturnUrl::validatedCandidate($request, '/message/5/edit'));
    }

    public function test_validated_from_request_rejects_protocol_relative_url(): void
    {
        $request = Request::create('https://humano.test/template/x/editor', 'GET', [
            'return_url' => '//evil.example/phish',
        ]);

        $this->assertNull(TemplateEditorReturnUrl::validatedFromRequest($request));
        $this->assertNull(TemplateEditorReturnUrl::validatedCandidate($request, '//evil.example/phish'));
    }

    public function test_validated_from_request_accepts_same_host_absolute_url(): void
    {
        $request = Request::create('https://humano.test/template/x/editor', 'GET', [
            'return_url' => 'https://humano.test/message/list',
        ]);

        $this->assertSame('https://humano.test/message/list', TemplateEditorReturnUrl::validatedFromRequest($request));
        $this->assertSame(
            'https://humano.test/message/list',
            TemplateEditorReturnUrl::validatedCandidate($request, 'https://humano.test/message/list'),
        );
    }

    public function test_validated_from_request_rejects_other_host(): void
    {
        $request = Request::create('https://humano.test/template/x/editor', 'GET', [
            'return_url' => 'https://evil.example/',
        ]);

        $this->assertNull(TemplateEditorReturnUrl::validatedFromRequest($request));
        $this->assertNull(TemplateEditorReturnUrl::validatedCandidate($request, 'https://evil.example/'));
    }

    public function test_editor_route_with_return_appends_query_string(): void
    {
        $base = 'https://humano.test/template/h/editor';
        $out = TemplateEditorReturnUrl::editorRouteWithReturn($base, '/message/1/edit');

        $this->assertStringContainsString('return_url=%2Fmessage%2F1%2Fedit', $out);
    }
}
