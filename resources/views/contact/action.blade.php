<div class="d-flex justify-content-center align-items-center">
    <a href="javascript:;" class="text-body edit-sentiment" data-id="{{ $contact->id }}"><i class="ti ti-mood-happy ti-sm me-2"></i></a>
    @if ($contact->chatIndexUrl() && (auth()->user()->can('chat.list') || auth()->user()->hasAnyRole(['admin', 'collaborator', 'developer', 'technical'])))
        <a href="{{ $contact->chatIndexUrl() }}" class="text-body" title="{{ __('Chat') }}"><i class="ti ti-message-chatbot ti-sm me-2"></i></a>
    @endif
    @can('view', $contact)
        @if ($contact->mailComposeListUrl())
            <a href="{{ $contact->mailComposeListUrl() }}" class="text-body" title="{{ __('Mail') }}"><i class="ti ti-mail ti-sm me-2"></i></a>
        @endif
    @endcan
    @can('update', $contact)
        @if (auth()->user()->currentTeam?->hasModule('list60'))
            @if ($contact->isInList60())
                <a class="text-success"><i class="ti ti-list-check ti-sm me-2"></i></a>
            @else
                <a href="#" class="text-body" onclick="addToList({{ $contact->id }}, this)"><i class="ti ti-list-check ti-sm me-2"></i></a>
            @endif
        @endif
    @endcan

    @can('view', $contact)
        <a href="{{ route('contact.show', $contact->id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endcan
</div>
