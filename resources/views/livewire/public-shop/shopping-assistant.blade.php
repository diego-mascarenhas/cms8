<div class="public-shop-livewire">
    <style>
        .public-shop-messages { max-height: min(58vh, 520px); overflow-y: auto; }
        .msg-user { background: var(--bs-primary-bg-subtle); border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 0.75rem; }
        .msg-assistant { background: var(--bs-secondary-bg); border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 0.75rem; white-space: pre-wrap; }
    </style>

    @if (!$team)
        <p class="text-danger">{{ __('public_shop.error') }}</p>
    @else
        @php
            $config = $team->getDecodedBusinessConfig();
            $displayName = trim((string) ($config['business_name'] ?? $team->name));
        @endphp

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="mb-0 text-heading">{{ $displayName }}</h4>
            <a href="{{ url('/') }}" class="btn btn-sm btn-label-secondary waves-effect">
                <i class="ti ti-arrow-left me-1"></i>{{ __('public_shop.back_home') }}
            </a>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('public_shop.assistant_title') }}</h5>
                    </div>
                    <div class="card-body d-flex flex-column" style="min-height: 420px;">
                        <div class="public-shop-messages flex-grow-1 mb-3">
                            @foreach ($messages as $msg)
                                <div class="{{ $msg['role'] === 'user' ? 'msg-user' : 'msg-assistant' }}">
                                    {{ $msg['content'] }}
                                </div>
                            @endforeach
                            @if ($loading)
                                <div class="text-muted small">{{ __('public_shop.thinking') }}</div>
                            @endif
                        </div>

                        @if (count($suggestedProductIds) > 0)
                            <div class="mb-3">
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

                        <form wire:submit="sendMessage" class="mt-auto">
                            <div class="input-group">
                                <input type="text" class="form-control" wire:model="input"
                                    placeholder="{{ __('public_shop.input_placeholder') }}"
                                    maxlength="2000"
                                    autocomplete="off">
                                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                                    {{ __('public_shop.send') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card h-100 border-primary border-opacity-25">
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
    @endif
</div>
