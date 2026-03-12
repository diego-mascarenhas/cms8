@php
    $lastRouted = collect($messages)->reverse()->first(fn ($m) => ($m['role'] ?? '') === 'assistant' && !empty($m['routed_to']));
    $currentFlow = $lastRouted['routed_to'] ?? __('General');
@endphp
<div class="card" x-data x-init="$wire.on('scroll-to-bottom', () => $nextTick(() => document.getElementById('assistant-chat-messages')?.scrollTo(0, 1e9)))">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('Asistente') }} · <span class="text-muted fw-normal">{{ $currentFlow }}</span></h5>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <label class="mb-0 small d-flex align-items-center gap-2">
                <span>{{ __('Respuesta por voz') }}</span>
                <input type="checkbox" wire:model="respondWithAudio" class="form-check-input">
            </label>
            @if(count($messages) > 0)
                <button type="button" class="btn btn-sm btn-label-secondary" wire:click="clearChat">
                    <i class="ti ti-refresh me-1"></i>{{ __('Nueva conversación') }}
                </button>
            @endif
        </div>
    </div>
    <div class="card-body p-0 d-flex flex-column" style="min-height: 360px;">
        <div class="flex-grow-1 overflow-auto p-3" style="max-height: 420px;" id="assistant-chat-messages">
            {{-- Welcome box: always visible --}}
            @if(count($messages) === 0)
                <div class="mb-3 d-flex justify-content-start" x-data="{ step: 'waiting' }" x-init="setTimeout(() => step = 'welcome', 2000); setTimeout(() => step = 'ready', 4500)">
                    <div class="bg-label-primary rounded p-3 shadow-sm me-md-5" style="max-width: 85%;">
                        <p class="mb-0 small text-body" x-show="step === 'waiting'" x-transition style="display: none;">...</p>
                        <p class="mb-1 fw-medium text-body" x-show="step === 'welcome' || step === 'ready'" x-transition style="display: none;">{{ __('¡Bienvenido!') }}</p>
                        <p class="mb-0 small text-body" x-show="step === 'ready'" x-transition style="display: none;">{{ __('Puedes consultarme lo que necesites, que intentaré ayudarte en lo que pueda.') }}</p>
                    </div>
                </div>
            @else
                <div class="mb-3 d-flex justify-content-start">
                    <div class="bg-label-primary rounded p-3 shadow-sm me-md-5" style="max-width: 85%;">
                        <p class="mb-1 fw-medium text-body">{{ __('¡Bienvenido!') }}</p>
                        <p class="mb-0 small text-body">{{ __('Puedes consultarme lo que necesites, que intentaré ayudarte en lo que pueda.') }}</p>
                    </div>
                </div>
            @endif

            @if(count($messages) > 0)
                @foreach($messages as $index => $msg)
                    <div wire:key="msg-{{ $index }}" class="mb-3 d-flex {{ $msg['role'] === 'user' ? 'justify-content-end' : '' }}">
                        <div class="{{ $msg['role'] === 'user' ? 'bg-primary text-white' : 'bg-label-primary' }} rounded p-3 shadow-sm {{ $msg['role'] === 'user' ? '' : 'me-md-5' }}" style="max-width: 85%;">
                            @if($msg['role'] === 'assistant' && !empty($msg['routed_to']))
                                <span class="badge bg-secondary mb-2">{{ $msg['routed_to'] }}</span>
                            @endif
                            @if($msg['role'] === 'user')
                                <span class="text-break">{{ e($msg['content']) }}</span>
                            @else
                                <div class="assistant-content text-break small">
                                    {!! \Illuminate\Support\Str::markdown($msg['content']) !!}
                                </div>
                                @if(!empty($msg['audio_base64']) && !empty($msg['audio_mime']))
                                    <div class="mt-2">
                                        <audio controls class="w-100" style="max-height: 40px;">
                                            <source src="data:{{ $msg['audio_mime'] }};base64,{{ $msg['audio_base64'] }}" type="{{ $msg['audio_mime'] }}">
                                            {{ __('Tu navegador no soporta la reproducción de audio.') }}
                                        </audio>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

            @if($loading)
                <div class="d-flex justify-content-start mb-3">
                    <div class="bg-label-primary rounded p-3">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        {{ __('Pensando...') }}
                    </div>
                </div>
            @endif
        </div>
        <div class="p-3 border-top">
            <form wire:submit="sendMessage">
                <div class="d-flex align-items-center gap-1 mb-2">
                    <input type="file" id="assistant-chat-image" wire:model="image" accept="image/*,.jpg,.jpeg,.png,.gif,.webp,.bmp,.svg" class="d-none">
                    <input type="file" id="assistant-chat-audio" wire:model="audio" accept="audio/*,.mp3,.wav,.m4a,.webm,.ogg" class="d-none">
                    <button type="button" class="btn btn-icon btn-label-secondary flex-shrink-0" onclick="document.getElementById('assistant-chat-image').click()" title="{{ __('Subir imagen') }}" aria-label="{{ __('Subir imagen') }}" @if($loading) disabled @endif>
                        <i class="ti ti-photo"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-label-secondary flex-shrink-0" onclick="document.getElementById('assistant-chat-audio').click()" title="{{ __('Subir audio') }}" aria-label="{{ __('Subir audio') }}" @if($loading) disabled @endif>
                        <i class="ti ti-microphone"></i>
                    </button>
                    <input type="text" id="assistant-chat-input" class="form-control flex-grow-1" wire:model="input" placeholder="{{ __('Escribe tu mensaje...') }}" @if($loading) disabled @endif>
                    <button type="submit" class="btn btn-primary btn-icon flex-shrink-0" @if($loading) disabled @endif aria-label="{{ __('Enviar') }}">
                        <span wire:loading.remove wire:target="sendMessage"><i class="ti ti-send"></i></span>
                        <span wire:loading wire:target="sendMessage" class="spinner-border spinner-border-sm" role="status"></span>
                    </button>
                </div>
                @if($image || $audio)
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if($image)
                        <span class="badge bg-label-info">{{ $image->getClientOriginalName() }}</span>
                        <button type="button" class="btn btn-sm btn-icon btn-label-secondary" wire:click="$set('image', null)" aria-label="{{ __('Quitar imagen') }}"><i class="ti ti-x"></i></button>
                    @endif
                    @if($audio)
                        <span class="badge bg-label-info">{{ $audio->getClientOriginalName() }}</span>
                        <button type="button" class="btn btn-sm btn-icon btn-label-secondary" wire:click="$set('audio', null)" aria-label="{{ __('Quitar audio') }}"><i class="ti ti-x"></i></button>
                    @endif
                </div>
                @endif
            </form>
        </div>
    </div>
</div>

