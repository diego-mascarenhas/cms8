<div class="d-flex justify-content-center align-items-center">
    @if(!$contact->user_id)
        <!-- Pending acceptance - show accept button -->
        @role('admin')
            <a href="{{ route('collaborator.accept', $contact->id) }}" class="text-success me-2" title="{{ __('Aceptar colaborador') }}"><i class="ti ti-check ti-sm"></i></a>
            <a href="{{ route('collaborator.show', $contact->id) }}" class="text-body me-2" title="{{ __('Ver colaborador') }}"><i class="ti ti-eye ti-sm"></i></a>
        @endrole
    @else
        <!-- Normal actions for accepted collaborators -->
        @role('admin|collaborator')
            <a href="{{ route('collaborator.edit', $contact->id) }}" class="text-body me-2"><i class="ti ti-edit ti-sm"></i></a>
        @endrole

        @role('admin|collaborator')
            <a href="{{ route('collaborator.show', $contact->id) }}" class="text-body me-2"><i class="ti ti-eye ti-sm"></i></a>
        @endrole

        <!-- Three dots dropdown menu -->
        @role('admin')
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
        @endrole
    @endif
</div>
