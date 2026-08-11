<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ $page->name }} - Preview</title>

	<style>
		{!! $page->gjs_data['css'] ?? '' !!}
	</style>
</head>
<body>
	@php
		$templateHtml = $page->gjs_data['html'] ?? '';
		$logoUrl = url(\App\Helpers\Helpers::logoAsset('light'));
		$templateHtml = str_replace(\App\Services\TemplateHtmlGenerationService::LOGO_URL_PLACEHOLDER, $logoUrl, $templateHtml);
		// Fix existing templates that used external placeholder images (e.g. via.placeholder.com)
		$templateHtml = preg_replace('#(<img\s[^>]*\ssrc=["\'])https?://[^"\']*placeholder\.com[^"\']*(["\'])#i', '$1' . $logoUrl . '$2', $templateHtml);
	@endphp
	{!! $templateHtml !!}
</body>
</html>

