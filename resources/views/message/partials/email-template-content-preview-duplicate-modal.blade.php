@props([
    'dupModalDomId',
    'duplicateFormId',
    'defaultDuplicateTemplateName',
    'messageId' => null,
])

<div
    class="modal fade"
    id="{{ $dupModalDomId }}"
    tabindex="-1"
    aria-labelledby="{{ $dupModalDomId }}-title"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $dupModalDomId }}-title">{{ __('app.email_template_duplicate_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cerrar') }}"></button>
            </div>
            <div class="modal-body">
                <label for="{{ $dupModalDomId }}-name" class="form-label">{{ __('app.email_template_duplicate_modal_name_label') }}</label>
                <input
                    type="text"
                    class="form-control @error('duplicate_template_name') is-invalid @enderror"
                    id="{{ $dupModalDomId }}-name"
                    name="duplicate_template_name"
                    form="{{ $duplicateFormId }}"
                    required
                    minlength="3"
                    maxlength="75"
                    value="{{ old('duplicate_template_name', $defaultDuplicateTemplateName) }}"
                    autocomplete="off"
                >
                @error('duplicate_template_name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @if ($messageId)
                    <input type="hidden" name="message_id" form="{{ $duplicateFormId }}" value="{{ $messageId }}">
                @endif
                <p class="text-muted small mb-0 mt-2">
                    @if ($messageId)
                        {{ __('app.email_template_duplicate_modal_hint') }}
                    @else
                        {{ __('app.email_template_duplicate_modal_hint_draft') }}
                    @endif
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-label-secondary waves-effect" data-bs-dismiss="modal">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" form="{{ $duplicateFormId }}" class="btn btn-sm btn-primary waves-effect waves-light">
                    {{ __('app.email_template_duplicate_modal_submit') }}
                </button>
            </div>
        </div>
    </div>
</div>
