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
            <form wire:submit="sendMessage" class="d-flex gap-2">
                <input type="text" class="form-control" wire:model="input" placeholder="{{ __('Escribe tu mensaje...') }}" @if($loading) disabled @endif>
                <button type="submit" class="btn btn-primary" @if($loading) disabled @endif>
                    <span wire:loading.remove wire:target="sendMessage"><i class="ti ti-send"></i></span>
                    <span wire:loading wire:target="sendMessage" class="spinner-border spinner-border-sm" role="status"></span>
                </button>
            </form>
        </div>
    </div>
</div>

