<div
    class="modal fade"
    id="{{ $testSendModalId }}"
    tabindex="-1"
    aria-labelledby="{{ $testSendModalId }}-title"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $testSendModalId }}-title">{{ __('Enviar correo de prueba') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cerrar') }}"></button>
            </div>
            <div class="modal-body">
                <div class="alert d-none mb-3" role="alert" data-email-test-send-alert></div>
                <p class="mb-2">
                    {{ __('app.message_test_send_intro') }}
                </p>
                <label class="form-label mb-1" for="{{ $testSendModalId }}-recipients">{{ __('app.message_test_send_recipients_label') }}</label>
                <input
                    type="text"
                    class="form-control mb-1"
                    id="{{ $testSendModalId }}-recipients"
                    data-email-test-send-recipients
                    data-default-recipients="{{ e(auth()->user()?->email ?? '') }}"
                    value="{{ e(auth()->user()?->email ?? '') }}"
                    autocomplete="email"
                    spellcheck="false"
                >
                <p class="text-muted small mb-0">{{ __('app.message_test_send_recipients_help') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-label-secondary waves-effect" data-bs-dismiss="modal">
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-primary waves-effect waves-light"
                    data-email-test-send-submit
                    data-submit-url="{{ $testSendUrl }}"
                    data-submit-label="{{ __('Enviar') }}"
                    @if ($useDraftTestSend)
                        data-email-test-send-template-id="{{ $templateId }}"
                    @endif
                >
                    {{ __('Enviar') }}
                </button>
            </div>
        </div>
    </div>
</div>
