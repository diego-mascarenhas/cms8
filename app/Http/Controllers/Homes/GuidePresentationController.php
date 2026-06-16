<?php

namespace App\Http\Controllers\Homes;

use App\Http\Controllers\Controller;
use App\Support\GuidePresentation;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GuidePresentationController extends Controller
{
    public function show(string $slug): Response
    {
        if (! GuidePresentation::isValid($slug))
        {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $path = GuidePresentation::filePath($slug);

        if (! is_file($path))
        {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $html = file_get_contents($path);

        if ($html === false)
        {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return response($this->injectBaseTag($html), HttpResponse::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private function injectBaseTag(string $html): string
    {
        if (str_contains($html, '<base href='))
        {
            return $html;
        }

        if (! preg_match('/<meta[^>]+name=["\']viewport["\'][^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE))
        {
            return $html;
        }

        $insertAt = $matches[0][1] + strlen($matches[0][0]);
        $baseTag = "\n<base href=\"/homes/humano/presentations/\">";

        return substr_replace($html, $baseTag, $insertAt, 0);
    }
}
