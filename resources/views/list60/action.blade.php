<div class="d-flex justify-content-center align-items-center">
    @role('admin|collaborator|developer|editor|technical')
        <a href="{{ route('contact.show', $contact->id) }}" class="text-body"><i class="ti ti-eye ti-sm me-2"></i></a>
    @endrole
    @can('logInteraction', $contact)
        @php
            $canWhatsapp = (bool) $contact->getWhatsAppNumber();
            $canEmail = is_string($contact->email) && $contact->email !== '' && filter_var($contact->email, FILTER_VALIDATE_EMAIL);
        @endphp
        @if ($canWhatsapp || $canEmail)
            <a
                href="javascript:;"
                class="text-body me-2 js-list60-outreach"
                data-list60-id="{{ $id }}"
                data-contact-name="{{ $contact->name }}"
                data-categories='@json($contact->categories->pluck('name')->values())'
                data-can-whatsapp="{{ $canWhatsapp ? '1' : '0' }}"
                data-can-email="{{ $canEmail ? '1' : '0' }}"
                title="{{ __('app.list60_outreach_modal_title') }}"
            >
                <i class="ti ti-send ti-sm"></i>
            </a>
        @endif
    @endcan
    @role('admin')
        <a
            href="javascript:;"
            class="text-body me-2 js-list60-date"
            data-list60-id="{{ $id }}"
            title="Próximo contacto"
        >
            <i class="ti ti-calendar ti-sm"></i>
        </a>
    @endrole
    @role('admin')
        <a href="#" class="text-danger" onclick="deleteRecord({{ $id }}, this)"><i class="ti ti-x ti-sm"></i></a>
    @endrole
</div>
