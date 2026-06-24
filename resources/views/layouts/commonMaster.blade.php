<!DOCTYPE html>
@php
$menuFixed = ($configData['layout'] === 'vertical') ? ($menuFixed ?? '') : (($configData['layout'] === 'front') ? '' : $configData['headerType']);
$navbarType = ($configData['layout'] === 'vertical') ? ($configData['navbarType'] ?? '') : (($configData['layout'] === 'front') ? '' : '');
$isFront = ($isFront ?? '') == true ? 'Front' : '';
$contentLayout = (isset($container) ? (($container === 'container-xxl') ? "layout-compact" : "layout-wide") : "");
@endphp

<html lang="{{ \App\Support\ApplicationLocales::htmlLang(session()->get('locale')) }}" class="{{ $configData['style'] }}-style {{($contentLayout ?? '')}} {{ ($navbarType ?? '') }} {{ ($menuFixed ?? '') }} {{ $menuCollapsed ?? '' }} {{ $footerFixed ?? '' }} {{ $customizerHidden ?? '' }}" dir="{{ $configData['textDirection'] }}" data-theme="{{ $configData['theme'] }}" data-assets-path="{{ asset('/assets') . '/' }}" data-base-url="{{url('/')}}" data-framework="laravel" data-template="{{ $configData['layout'] . '-menu-' . $configData['theme'] . '-' . $configData['style'] }}">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  @php
    $templateName = (string) (config('variables.templateName') ?: 'Humano');
    $seoPageTitle = trim((string) $__env->yieldContent('title'));
    $seoOgTitle = trim((string) $__env->yieldContent('ogTitle'));
    if ($seoOgTitle === '') {
        $seoOgTitle = $seoPageTitle !== ''
            ? $seoPageTitle.' | '.$templateName
            : $templateName;
    }
    $seoMetaDescription = trim((string) $__env->yieldContent('metaDescription'));
    if ($seoMetaDescription === '') {
        $seoMetaDescription = (string) (config('variables.templateDescription') ?: '');
    }
    $documentTitle = $seoPageTitle !== '' ? $seoPageTitle.' | '.$templateName : $templateName;
  @endphp

  <title>{{ $documentTitle }}</title>
  <meta name="description" content="{{ $seoMetaDescription }}" />
  <meta name="keywords" content="{{ config('variables.templateKeyword') ? config('variables.templateKeyword') : '' }}">
  <!-- laravel CRUD token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <!-- Canonical SEO -->
  <link rel="canonical" href="{{ config('variables.productPage') ? config('variables.productPage') : '' }}">
  <!-- Favicon -->
  @include('layouts.partials.favicon')

  @php
    $includeSharePreview = (bool) ($includeSharePreview ?? false);
  @endphp
  <!-- Open Graph / Facebook / WhatsApp -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ url()->current() }}" />
  <meta property="og:title" content="{{ $seoOgTitle }}" />
  <meta property="og:description" content="{{ $seoMetaDescription }}" />
  @if ($includeSharePreview)
    @include('layouts.partials.share-preview-meta')
  @else
    <meta name="twitter:card" content="summary" />
  @endif
  <meta property="og:site_name" content="{{ config('variables.templateName') }}" />
  <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}" />

  <!-- Twitter Card -->
  <meta name="twitter:url" content="{{ url()->current() }}" />
  <meta name="twitter:title" content="{{ $seoOgTitle }}" />
  <meta name="twitter:description" content="{{ $seoMetaDescription }}" />
  @if(config('variables.twitterUrl'))
  <meta name="twitter:site" content="{{ config('variables.twitterUrl') }}" />
  @endif



  <!-- Include Styles -->
  <!-- $isFront is used to append the front layout styles only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/styles' . $isFront)

  <!-- Include Scripts for customizer, helper, analytics, config -->
  <!-- $isFront is used to append the front layout scriptsIncludes only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/scriptsIncludes' . $isFront)

  <!-- Google Analytics - Only in production -->
  @if(app()->environment('production') && config('app.google_analytics_id'))
  <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.google_analytics_id') }}"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ config('app.google_analytics_id') }}');
  </script>
  @endif
</head>

<body>


  <!-- Layout Content -->
  @yield('layoutContent')
  <!--/ Layout Content -->



  <!-- Include Scripts -->
  <!-- $isFront is used to append the front layout scripts only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/scripts' . $isFront)

  @stack('scripts')

  @auth
    @include('layouts.partials.assistant-fab')
  @endauth
</body>

</html>
