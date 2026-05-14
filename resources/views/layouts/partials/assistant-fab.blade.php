@if (! request()->routeIs('assistant', 'chatbot'))
<style>
    .assistant-fab-host {
        bottom: 0.875rem;
        right: 1.5rem;
        z-index: 1094;
    }
    .assistant-fab-host .assistant-fab-btn {
        box-shadow:
            0 0.5rem 1.125rem rgba(var(--bs-primary-rgb), 0.42),
            0 0.25rem 0.5rem rgba(0, 0, 0, 0.18),
            0 0.125rem 0.25rem rgba(0, 0, 0, 0.08) !important;
    }
    /* Debugbar is position:fixed bottom:0 with z-index ~1e10 — FAB would sit underneath and disappear */
    body:has(div.phpdebugbar) .assistant-fab-host {
        bottom: 5.5rem;
    }
    #assistant-offcanvas.offcanvas.show ~ .assistant-fab-host .assistant-fab-btn {
        visibility: hidden;
    }
    #assistant-offcanvas .offcanvas-body > .assistant-offcanvas-livewire-root {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    #assistant-offcanvas .assistant-offcanvas-livewire-root > .card {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        border: 0;
        box-shadow: none;
    }
    #assistant-offcanvas .assistant-offcanvas-livewire-root .card-body {
        flex: 1;
        min-height: 0 !important;
        display: flex !important;
        flex-direction: column;
    }
    #assistant-offcanvas #assistant-chat-messages {
        max-height: none !important;
        flex: 1;
        min-height: 0;
    }
</style>

<div
    class="offcanvas offcanvas-end shadow-lg"
    tabindex="-1"
    id="assistant-offcanvas"
    aria-labelledby="assistantOffcanvasLabel"
    style="width: min(100vw, 28rem);"
    data-bs-scroll="true"
>
    <div class="offcanvas-header border-bottom flex-shrink-0">
        <h5 class="offcanvas-title mb-0" id="assistantOffcanvasLabel">{{ __('Asistente') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0 h-100 overflow-hidden">
        <div class="assistant-offcanvas-livewire-root flex-grow-1" style="min-height: 0;">
            {{-- promptKey null = general router (default flow / system routing). --}}
            @livewire('assistant-chat', ['promptKey' => null, 'hideHeader' => true], key('layout-assistant-chat-'.(auth()->id() ?? 'guest')))
        </div>
    </div>
</div>

<div class="assistant-fab-host position-fixed">
    <button
        type="button"
        id="assistant-fab"
        class="btn btn-primary btn-icon rounded-circle assistant-fab-btn"
        style="width: 3.25rem; height: 3.25rem;"
        data-bs-toggle="offcanvas"
        data-bs-target="#assistant-offcanvas"
        aria-controls="assistant-offcanvas"
        title="{{ __('app.assistant_fab_title') }}"
        aria-label="{{ __('app.assistant_fab_title') }}"
    >
        <i class="ti ti-sparkles ti-md"></i>
    </button>
</div>
@endif
