<div class="card" x-data x-init="$wire.on('scroll-to-bottom', () => $nextTick(() => document.getElementById('assistant-chat-messages')?.scrollTo(0, 1e9)))">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('Asistente') }}</h5>
        @if(count($messages) > 0)
            <button type="button" class="btn btn-sm btn-label-secondary" wire:click="clearChat">
                <i class="ti ti-refresh me-1"></i>{{ __('Nueva conversación') }}
            </button>
        @endif
    </div>
    <div class="card-body p-0 d-flex flex-column" style="min-height: 360px;">
        <div class="flex-grow-1 overflow-auto p-3" style="max-height: 420px;" id="assistant-chat-messages">
            @if(count($messages) === 0)
                <p class="text-muted text-center py-4 mb-0">
                    {{ __('Escribe tu necesidad o problema. Te enrutaré al flujo más adecuado (estrategia, email, notas, proyecto, etc.).') }}
                </p>
            @else
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
                <div class="mb-2">
                    <input type="text" class="form-control" wire:model="input" placeholder="{{ __('Escribe tu mensaje, o sube imagen/audio...') }}" @if($loading) disabled @endif>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="small text-muted"><i class="ti ti-photo me-1"></i>{{ __('Imagen') }}:</span>
                    <input type="file" wire:model="image" accept="image/*" class="form-control form-control-sm" style="max-width: 140px;">
                    <span class="small text-muted"><i class="ti ti-microphone me-1"></i>{{ __('Audio') }}:</span>
                    <input type="file" wire:model="audio" accept="audio/*,.mp3,.wav,.m4a,.webm,.ogg" class="form-control form-control-sm" style="max-width: 140px;">
                    @if($image)
                        <span class="badge bg-label-info">{{ $image->getClientOriginalName() }}</span>
                        <button type="button" class="btn btn-sm btn-icon btn-label-secondary" wire:click="$set('image', null)" aria-label="{{ __('Quitar imagen') }}"><i class="ti ti-x"></i></button>
                    @endif
                    @if($audio)
                        <span class="badge bg-label-info">{{ $audio->getClientOriginalName() }}</span>
                        <button type="button" class="btn btn-sm btn-icon btn-label-secondary" wire:click="$set('audio', null)" aria-label="{{ __('Quitar audio') }}"><i class="ti ti-x"></i></button>
                    @endif
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <label class="mb-0 small d-flex align-items-center gap-1">
                        <input type="checkbox" wire:model="respondWithAudio" class="form-check-input">
                        {{ __('Respuesta en voz') }}
                    </label>
                    <button type="submit" class="btn btn-primary ms-auto" @if($loading) disabled @endif>
                        <span wire:loading.remove wire:target="sendMessage"><i class="ti ti-send me-1"></i>{{ __('Enviar') }}</span>
                        <span wire:loading wire:target="sendMessage" class="spinner-border spinner-border-sm" role="status"></span>
                    </button>
                </div>
            </form>
        </div>
        @php
            $lastPrompt = collect($messages)->reverse()->first(fn ($m) => ($m['role'] ?? '') === 'assistant' && !empty($m['routed_to']));
        @endphp
        @if($lastPrompt)
        <div class="px-3 pb-2 pt-0 border-top small text-muted">
            <i class="ti ti-route me-1"></i>{{ __('Resolviendo con') }}: <strong>{{ $lastPrompt['routed_to'] }}</strong>
        </div>
        @endif
    </div>
</div>

