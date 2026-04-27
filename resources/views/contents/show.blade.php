@extends('layouts/layoutMaster')

@section('title', __('app.Content'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ __('app.Contents') }}</h4>
        <p class="text-muted">{{ __('app.Content details') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $content)
        <a href="{{ route('contents.edit', $content->id) }}" class="btn btn-primary waves-effect waves-light">
            <i class="ti ti-edit me-1"></i>{{ __('app.Edit') }}
        </a>
        @endcan
        <a href="{{ route('contents.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('app.Back') }}
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        @php
            $cfv = $content->sectionCategory?->contentFormVisibility() ?? \App\Support\ContentsSectionCategoryData::defaultContentFormVisibility();
            $supportedLocaleLabels = \App\Support\ContentsSectionCategoryData::supportedLocaleLabels();
            $enabledLocaleCodes = $content->sectionCategory?->contentFormLocales() ?? ['es'];
            $enabledLocales = [];
            foreach ($enabledLocaleCodes as $localeCode)
            {
                if (isset($supportedLocaleLabels[$localeCode]))
                {
                    $enabledLocales[$localeCode] = $supportedLocaleLabels[$localeCode];
                }
            }
            if ($enabledLocales === [])
            {
                $enabledLocales = ['es' => $supportedLocaleLabels['es'] ?? 'Español'];
            }
            $activeLocale = array_key_first($enabledLocales) ?: 'es';
            $coverData = is_array($content->data ?? null) ? ($content->data['cover'] ?? null) : null;
            $coverImageUrl = is_array($coverData) ? ($coverData['url'] ?? null) : null;
            $coverVariants = is_array($coverData) && is_array($coverData['variants'] ?? null) ? $coverData['variants'] : [];
            $coverMaxWidth = is_array($coverData) && isset($coverData['max_width']) ? (int) $coverData['max_width'] : null;
            $coverMaxHeight = is_array($coverData) && isset($coverData['max_height']) ? (int) $coverData['max_height'] : null;
            $coverBoxStyleParts = ['max-width: 100%'];
            if ($coverMaxWidth && $coverMaxWidth > 0)
            {
                $coverBoxStyleParts[] = 'width: '.$coverMaxWidth.'px';
            }
            if ($coverMaxHeight && $coverMaxHeight > 0)
            {
                $coverBoxStyleParts[] = 'height: '.$coverMaxHeight.'px';
            }
            $coverBoxStyle = implode('; ', $coverBoxStyleParts);
            $sectionSlug = is_array($content->sectionCategory->data ?? null) ? ($content->sectionCategory->data['slug'] ?? null) : null;
        @endphp
        @if(is_string($sectionSlug) && $sectionSlug !== '')
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('app.Section slug') }}</h5>
                </div>
                <div class="card-body">
                    <code>{{ $sectionSlug }}</code>
                </div>
            </div>
        @endif
        <div class="card mb-4">
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    @foreach($enabledLocales as $localeCode => $localeName)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $localeCode === $activeLocale ? 'active' : '' }}"
                                data-bs-toggle="tab"
                                data-bs-target="#content-locale-{{ $localeCode }}"
                                type="button"
                                role="tab"
                                aria-controls="content-locale-{{ $localeCode }}"
                                aria-selected="{{ $localeCode === $activeLocale ? 'true' : 'false' }}">
                                {{ $localeName }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    @foreach($enabledLocales as $localeCode => $localeName)
                        @php
                            $title = $content->getTranslatable('title', $localeCode);
                            $subtitle = $content->getTranslatable('subtitle', $localeCode);
                            $url = $content->getTranslatable('url', $localeCode);
                            $mainContent = $content->getTranslatable('content', $localeCode);
                            $seoTitle = $content->getTranslatable('seo_title', $localeCode);
                            $seoKeywords = $content->getTranslatable('seo_keywords', $localeCode);
                            $seoDescription = $content->getTranslatable('seo_description', $localeCode);
                        @endphp
                        <div class="tab-pane fade {{ $localeCode === $activeLocale ? 'show active' : '' }}" id="content-locale-{{ $localeCode }}" role="tabpanel">
                            @if(($cfv['show_title'] ?? true) && $title)
                                <div class="mb-3">
                                    <h6 class="mb-1">{{ __('app.Title') }}</h6>
                                    <div>{{ $title }}</div>
                                </div>
                            @endif

                            @if(($cfv['show_subtitle'] ?? true) && $subtitle)
                                <div class="mb-3">
                                    <h6 class="mb-1">{{ __('app.Subtitle') }}</h6>
                                    <p class="text-muted mb-0">{{ $subtitle }}</p>
                                </div>
                            @endif

                            @if(($cfv['show_url'] ?? true) && $url)
                                <div class="mb-3">
                                    <h6 class="mb-1">{{ __('app.URL') }}</h6>
                                    <div>{{ $url }}</div>
                                </div>
                            @endif

                            @if(($cfv['show_main_content'] ?? true) && $mainContent)
                                <div class="mb-3">
                                    <h6 class="mb-2">{{ __('app.Main content') }}</h6>
                                    <div class="content-body">
                                        {!! $mainContent !!}
                                    </div>
                                </div>
                            @endif

                            @if(($cfv['show_seo'] ?? true) && ($seoTitle || $seoKeywords || $seoDescription))
                                <div class="mb-3">
                                    <h6 class="mb-2">{{ __('app.SEO') }}</h6>
                                    @if($seoTitle)
                                        <div class="mb-2">
                                            <div class="small text-muted">Title</div>
                                            <div>{{ $seoTitle }}</div>
                                        </div>
                                    @endif
                                    @if($seoKeywords)
                                        <div class="mb-2">
                                            <div class="small text-muted">Keywords</div>
                                            <div>{{ $seoKeywords }}</div>
                                        </div>
                                    @endif
                                    @if($seoDescription)
                                        <div>
                                            <div class="small text-muted">Description</div>
                                            <div>{{ $seoDescription }}</div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @if(($cfv['show_featured'] ?? true) && ($coverImageUrl || $coverVariants !== []))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Images</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @if($coverImageUrl)
                            <div class="col-md-6">
                                <div class="border rounded p-2 h-100">
                                    <div class="small text-muted mb-2">Cover</div>
                                    <div class="border rounded d-flex align-items-center justify-content-center overflow-hidden bg-body" style="{{ $coverBoxStyle }}">
                                        <img src="{{ $coverImageUrl }}" alt="Cover image" class="img-fluid rounded" style="max-height: 100%; object-fit: contain;">
                                    </div>
                                </div>
                            </div>
                        @endif
                        @foreach($coverVariants as $variantKey => $variantData)
                            @php
                                $variantUrl = is_array($variantData) ? ($variantData['url'] ?? null) : null;
                            @endphp
                            @if(is_string($variantUrl) && $variantUrl !== '')
                                <div class="col-md-6">
                                    <div class="border rounded p-2 h-100">
                                        <div class="small text-muted mb-2">Variant: {{ $variantKey }}</div>
                                        <img src="{{ $variantUrl }}" alt="Variant {{ $variantKey }}" class="img-fluid rounded">
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
    <div class="col-md-4">
        @php
            $mcpPromptLines = [
                'Fetch content data from Humano MCP.',
                '',
                'Tool:',
                'fetch-humano-contents',
                '',
                'Payload:',
                '{',
                '  "section_slug": "'.(is_string($sectionSlug) && $sectionSlug !== '' ? $sectionSlug : '').'",',
                '  "status": 3,',
                '  "locale": "'.$activeLocale.'",',
                '  "per_page": 100,',
                '  "page": 1',
                '}',
                '',
                'Frontend note:',
                'When generating frontend code, load API URL and token from .env (no hardcoded credentials).',
                'Render cover inside a reserved box using cover.max_width and cover.max_height, with object-fit: contain.',
            ];
            $mcpPromptText = implode("\n", $mcpPromptLines);
            $mcpPromptByIdLines = [
                'Fetch content data from Humano MCP.',
                '',
                'Tool:',
                'fetch-humano-contents',
                '',
                'Payload:',
                '{',
                '  "id": '.$content->id.',',
                '  "locale": "'.$activeLocale.'",',
                '}',
                '',
                'Frontend note:',
                'When generating frontend code, load API URL and token from .env (no hardcoded credentials).',
                'Render cover inside a reserved box using cover.max_width and cover.max_height, with object-fit: contain.',
            ];
            $mcpPromptByIdText = implode("\n", $mcpPromptByIdLines);
        @endphp
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('app.Details') }}</h5>
            </div>
            <div class="card-body">
                @php
                    $statusLabels = [
                        1 => __('app.Draft'),
                        2 => __('app.Pending'),
                        3 => __('app.Published'),
                        4 => __('app.Archived'),
                    ];
                    $statusClasses = [
                        1 => 'bg-label-secondary',
                        2 => 'bg-label-warning',
                        3 => 'bg-label-success',
                        4 => 'bg-label-info',
                    ];
                @endphp
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('app.Section') }}</dt>
                    <dd class="col-sm-8">{{ $content->sectionCategory->name }}</dd>

                    <dt class="col-sm-4">{{ __('app.Category') }}</dt>
                    <dd class="col-sm-8">{{ $content->category ? $content->category->name : __('app.No category') }}</dd>

                    <dt class="col-sm-4">{{ __('app.Status') }}</dt>
                    <dd class="col-sm-8">
                        <span class="badge {{ $statusClasses[$content->status] ?? 'bg-label-secondary' }}">
                            {{ $statusLabels[$content->status] ?? __('app.Unknown') }}
                        </span>
                    </dd>

                    @if(($cfv['show_featured'] ?? true))
                        <dt class="col-sm-4">{{ __('app.Featured') }}</dt>
                        <dd class="col-sm-8">
                            <div class="d-flex flex-wrap gap-1">
                                @if($content->featured)
                                    <span class="badge bg-label-success">{{ __('app.Featured') }}</span>
                                @endif
                                @if($content->featured_slide)
                                    <span class="badge bg-label-info">{{ __('app.Featured Slide') }}</span>
                                @endif
                                @if($content->featured_modal)
                                    <span class="badge bg-label-warning">{{ __('app.Featured Modal') }}</span>
                                @endif
                                @if(! $content->featured && ! $content->featured_slide && ! $content->featured_modal)
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </dd>
                    @endif

                </dl>
                @if(auth()->check() && $content->team_id)
                    @php($contentsApiCacheGeneration = \App\Support\TeamContentsApiCache::currentGeneration((int) $content->team_id))
                    <div class="pt-2 mt-2 border-top border-light">
                        <span class="d-block text-end text-muted user-select-all" title="{{ __('app.Team contents API cache generation hint') }}" aria-label="{{ __('app.Contents API cache version', ['n' => $contentsApiCacheGeneration]) }}" style="font-size: 0.65rem; opacity: 0.38; letter-spacing: 0.06em; font-variant-numeric: tabular-nums;">{{ __('app.Contents API cache version', ['n' => $contentsApiCacheGeneration]) }}</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h5 class="mb-0 d-flex align-items-center gap-2">
                    MCP Prompt
                    <a href="{{ route('help.index') }}#cursor-mcp-setup" class="text-muted" title="Ver ayuda MCP" aria-label="Ver ayuda MCP">
                        <i class="ti ti-help-circle"></i>
                    </a>
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="small text-muted">Global (section_slug)</span>
                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 waves-effect" data-copy-target="#mcp-content-prompt">
                            <i class="ti ti-copy me-1"></i>Copiar
                        </button>
                    </div>
                    <textarea id="mcp-content-prompt" class="form-control font-monospace" rows="12" readonly>{{ $mcpPromptText }}</textarea>
                </div>

                <div>
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="small text-muted">Detalle (id exacto)</span>
                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 waves-effect" data-copy-target="#mcp-content-id-prompt">
                            <i class="ti ti-copy me-1"></i>Copiar
                        </button>
                    </div>
                    <textarea id="mcp-content-id-prompt" class="form-control font-monospace" rows="12" readonly>{{ $mcpPromptByIdText }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mcpPromptEl = document.getElementById('mcp-content-prompt');
    const mcpPromptByIdEl = document.getElementById('mcp-content-id-prompt');
    const localeTabButtons = document.querySelectorAll('[data-bs-target^="#content-locale-"]');

    function updateMcpPromptLocale(localeCode) {
        if (!localeCode) {
            return;
        }

        [mcpPromptEl, mcpPromptByIdEl].forEach(function(promptEl) {
            if (!promptEl) {
                return;
            }

            promptEl.value = promptEl.value.replace(
                /"locale":\s*"[^"]*"/,
                '"locale": "' + localeCode + '"'
            );
        });
    }

    localeTabButtons.forEach(function(tabButton) {
        tabButton.addEventListener('shown.bs.tab', function() {
            const target = tabButton.getAttribute('data-bs-target') || '';
            const localeCode = target.replace('#content-locale-', '').trim();
            updateMcpPromptLocale(localeCode);
        });
    });

    document.querySelectorAll('[data-copy-target]').forEach(function(button) {
        button.addEventListener('click', async function() {
            const selector = button.getAttribute('data-copy-target');
            const source = selector ? document.querySelector(selector) : null;
            if (!source) {
                return;
            }

            const text = source.value || source.textContent || '';
            try {
                await navigator.clipboard.writeText(text);
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Copiado';
                setTimeout(function() {
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-primary');
                    button.innerHTML = '<i class="ti ti-copy me-1"></i>Copiar';
                }, 1600);
            } catch (error) {
                alert('No se pudo copiar automáticamente. Copia manual desde el bloque.');
            }
        });
    });
});
</script>
@if(app()->environment('production'))
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Track content view in Google Analytics (only in production)
    if (typeof gtag !== 'undefined') {
        gtag('event', 'content_view', {
            'content_id': {{ $content->id }},
            'content_title': '{{ addslashes($content->getTranslatable('title') ?? '') }}',
            'content_category': '{{ addslashes($content->sectionCategory->name ?? '') }}',
            'content_status': '{{ $content->status }}',
            'event_category': 'Content',
            'event_label': 'View'
        });
    }
});
</script>
@endif
@endsection
