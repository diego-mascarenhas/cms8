@php
    $displayName = $team ? trim((string) (($team->getDecodedBusinessConfig()['business_name'] ?? '') ?: $team->name)) : '';
@endphp

<div class="public-shop-livewire">
    @if (!$team)
        <div class="authentication-bg min-vh-100 p-4">
            <p class="text-danger mb-0">{{ __('public_shop.error') }}</p>
        </div>
    @else
        <div class="assistant-demo-wrapper authentication-bg">
            <div class="row g-0 assistant-demo-row mx-0">
                <div class="col-12 col-lg-7 p-4 auth-cover-bg auth-cover-bg-color assistant-demo-left">
                    <h4 class="mb-3 flex-shrink-0">{{ $displayName }}</h4>
                    <div class="assistant-chat-wrapper">
                        <div class="card"
                            x-data
                            x-init="$wire.on('scroll-to-bottom', () => $nextTick(() => document.getElementById('public-shop-chat-messages')?.scrollTo(0, 1e9)))">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="mb-0">{{ __('public_shop.assistant_title') }} · <span class="text-muted fw-normal">{{ __('public_shop.flow_label') }}</span></h5>
                                <div class="d-flex align-items-center gap-2 ms-lg-auto">
                                    @if (count($messages) > 2)
                                        <button type="button" class="btn btn-sm btn-label-secondary" wire:click="resetConversation">
                                            <i class="ti ti-refresh me-1"></i>{{ __('Nueva conversación') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body p-0 d-flex flex-column" style="min-height: 360px;">
                                <div class="flex-grow-1 overflow-auto p-3 public-shop-scroll-messages" style="max-height: 420px;" id="public-shop-chat-messages">
                                    @foreach ($messages as $index => $msg)
                                        <div wire:key="ps-msg-{{ $index }}" class="mb-3 d-flex {{ $msg['role'] === 'user' ? 'justify-content-end' : 'justify-content-start' }}">
                                            @if ($msg['role'] === 'user')
                                                <div class="bg-primary text-white rounded p-3 shadow-sm" style="max-width: 85%;">
                                                    <span class="text-break">{{ e($msg['content']) }}</span>
                                                </div>
                                            @else
                                                <div class="bg-label-primary rounded p-3 shadow-sm me-md-5" style="max-width: 85%;">
                                                    <div class="assistant-content text-break small text-body">
                                                        {!! \Illuminate\Support\Str::markdown(\App\Helpers\WhatsAppOutboundText::sanitize((string) $msg['content'])) !!}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($loading)
                                        <div class="d-flex justify-content-start mb-3">
                                            <div class="bg-label-primary rounded p-3 shadow-sm">
                                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                                {{ __('public_shop.thinking') }}
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                @if (count($suggestedProductIds) > 0)
                                    <div class="px-3 pb-2 border-top border-light">
                                        <span class="text-muted small d-block mb-2">{{ __('public_shop.suggested_label') }}</span>
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach ($suggestedProductIds as $pid)
                                                @php $sp = $productsById->get($pid); @endphp
                                                @if ($sp)
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                        wire:click="addToCart({{ (int) $pid }})" wire:key="sug-{{ $pid }}">
                                                        {{ __('public_shop.add') }}: {{ $sp->name }}
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="p-3 border-top mt-auto">
                                    <form wire:submit="sendMessage">
                                        <div class="d-flex align-items-center gap-1">
                                            <input type="text" class="form-control flex-grow-1" wire:model="input"
                                                placeholder="{{ __('public_shop.input_placeholder') }}"
                                                maxlength="2000"
                                                autocomplete="off"
                                                @if ($loading) disabled @endif>
                                            <button type="submit" class="btn btn-primary btn-icon flex-shrink-0" @if ($loading) disabled @endif aria-label="{{ __('public_shop.send') }}">
                                                <span wire:loading.remove wire:target="sendMessage"><i class="ti ti-send"></i></span>
                                                <span wire:loading wire:target="sendMessage" class="spinner-border spinner-border-sm" role="status"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5 p-4 bg-body border-start assistant-demo-right">
                    <div class="card public-shop-cart-panel w-100 border-primary border-opacity-25">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ __('public_shop.cart_title') }}</h5>
                            <span class="badge bg-label-primary">{{ count($cart) }} {{ __('public_shop.lines') }}</span>
                        </div>
                        <div class="card-body">
                            @if ($cart === [])
                                <p class="text-muted mb-0">{{ __('public_shop.cart_empty') }}</p>
                            @else
                                <ul class="list-group list-group-flush mb-3">
                                    @foreach ($cart as $pid => $qty)
                                        @php $p = $productsById->get((int) $pid); @endphp
                                        @if ($p)
                                            <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                                                <div>
                                                    <strong>{{ $p->name }}</strong>
                                                    <div class="small text-muted">
                                                        {{ $p->currentSellingPrice() }} {{ $p->currency?->code ?? 'ARS' }} × {{ $qty }}
                                                    </div>
                                                </div>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary" wire:click="decrementCart({{ (int) $pid }})">−</button>
                                                    <button type="button" class="btn btn-outline-primary" wire:click="addToCart({{ (int) $pid }})">+</button>
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif

                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success waves-effect waves-light"
                                    wire:click="checkoutWhatsApp" @if ($cart === []) disabled @endif>
                                    <i class="ti ti-brand-whatsapp me-1"></i>{{ __('public_shop.checkout_whatsapp') }}
                                </button>
                            </div>

                            @if ($shopperAge !== '' || $shopperNotes !== '')
                                <hr>
                                <p class="small text-muted mb-0">
                                    <strong>{{ __('public_shop.profile_snapshot') }}</strong><br>
                                    @if ($shopperAge !== '')
                                        {{ __('public_shop.age_label') }} {{ $shopperAge }}<br>
                                    @endif
                                    @if ($shopperNotes !== '')
                                        {{ \Illuminate\Support\Str::limit($shopperNotes, 200) }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
