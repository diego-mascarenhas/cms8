@php
  $ogImagePath = config('variables.ogImage', 'assets/logo.png');
  $ogImageUrl = str_starts_with($ogImagePath, 'http') ? $ogImagePath : url('/'.ltrim($ogImagePath, '/'));
@endphp
<meta property="og:image" content="{{ $ogImageUrl }}" />
<meta property="og:image:secure_url" content="{{ $ogImageUrl }}" />
<meta property="og:image:width" content="{{ config('variables.ogImageWidth', 552) }}" />
<meta property="og:image:height" content="{{ config('variables.ogImageHeight', 552) }}" />
<meta property="og:image:alt" content="{{ config('variables.ogImageAlt', config('variables.templateName')) }}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:image" content="{{ $ogImageUrl }}" />
<meta name="twitter:image:alt" content="{{ config('variables.ogImageAlt', config('variables.templateName')) }}" />
