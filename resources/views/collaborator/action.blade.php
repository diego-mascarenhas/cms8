<div class="d-flex justify-content-center align-items-center">
    @if(!$contact->user_id)
        <!-- Pending acceptance - show accept button -->
        @if (auth()->user()->can('collaborator.edit'))
            <a href="{{ route('collaborator.accept', $contact->id) }}" class="btn btn-sm btn-success" title="{{ __('Aceptar colaborador') }}">
                <i class="ti ti-check me-1"></i>{{ __('Aceptar') }}
            </a>
        @endif
    @else
        <!-- Normal actions for accepted collaborators -->
        @if (auth()->user()->can('collaborator.edit'))
            <a href="{{ route('collaborator.edit', $contact->id) }}" class="text-body me-2"><i class="ti ti-edit ti-sm"></i></a>
        @endif
        
        @if (auth()->user()->can('collaborator.show'))
            <a href="{{ route('collaborator.show', $contact->id) }}" class="text-body me-2"><i class="ti ti-eye ti-sm"></i></a>
        @endif
        
        <!-- Three dots dropdown menu -->
        @if (auth()->user()->can('collaborator.edit'))
            <div class="dropdown">
                <button class="btn btn-sm btn-icon dropdown-toggle hide-arrow" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti ti-dots-vertical ti-sm"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="markAsWatch({{ $contact->id }})">
                            <i class="ti ti-eye me-2"></i>
                            {{ __('Marcar como ojo') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="sendToBlacklist({{ $contact->id }})">
                            <i class="ti ti-user-x me-2"></i>
                            {{ __('Mandar a Lista negra') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);" onclick="sendNotification({{ $contact->id }})">
                            <i class="ti ti-bell me-2"></i>
                            {{ __('Mandar notificación') }}
                        </a>
                    </li>
                </ul>
            </div>
        @endif
    @endif
</div> 