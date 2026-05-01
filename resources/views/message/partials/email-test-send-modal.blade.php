@props([
    'messageId',
])

@php
    $testSendModalId = 'email-test-send-modal-'.$messageId;
    $testSendUrl = route('message.test', $messageId);
@endphp

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
                    {{ __('Se enviará un correo de prueba usando la configuración del equipo a la cuenta con la que iniciaste sesión:') }}
                </p>
                <p class="mb-0 fw-semibold">{{ auth()->user()?->email ?? '—' }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary waves-effect" data-bs-dismiss="modal">
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    class="btn btn-primary waves-effect waves-light"
                    data-email-test-send-submit
                    data-submit-url="{{ $testSendUrl }}"
                    data-submit-label="{{ __('Enviar') }}"
                >
                    {{ __('Enviar') }}
                </button>
            </div>
        </div>
    </div>
</div>

@once('message-email-test-send-modal-script')
    @push('scripts')
        <script>
            (function ()
            {
                window.openEmailTestSendModal = function (modalId)
                {
                    var el = document.getElementById(modalId);
                    if (! el || typeof bootstrap === 'undefined')
                    {
                        return;
                    }

                    bootstrap.Modal.getOrCreateInstance(el).show();
                };

                function csrfHeader()
                {
                    var m = document.querySelector('meta[name="csrf-token"]');

                    return m ? m.getAttribute('content') : '';
                }

                function hideAlert(modalEl)
                {
                    var a = modalEl.querySelector('[data-email-test-send-alert]');
                    if (! a)
                    {
                        return;
                    }

                    a.textContent = '';
                    a.classList.add('d-none');
                    a.classList.remove('alert-danger', 'alert-success');
                }

                function showAlert(modalEl, variant, message)
                {
                    var a = modalEl.querySelector('[data-email-test-send-alert]');
                    if (! a)
                    {
                        return;
                    }

                    a.textContent = message;
                    a.classList.remove('d-none');
                    a.classList.remove('alert-danger', 'alert-success');
                    a.classList.add(variant === 'success' ? 'alert-success' : 'alert-danger');
                }

                document.querySelectorAll('[id^="email-test-send-modal-"]').forEach(function (modalEl)
                {
                    modalEl.addEventListener('show.bs.modal', function ()
                    {
                        hideAlert(modalEl);
                        var submitBtn = modalEl.querySelector('[data-email-test-send-submit]');
                        if (submitBtn)
                        {
                            submitBtn.disabled = false;
                            var label = submitBtn.getAttribute('data-submit-label') || @json(__('Enviar'));
                            submitBtn.textContent = label;
                        }
                    });
                });

                document.addEventListener('click', function (e)
                {
                    var btn = e.target.closest('[data-email-test-send-submit]');
                    if (! btn || btn.disabled)
                    {
                        return;
                    }

                    var modalEl = btn.closest('.modal');
                    if (! modalEl)
                    {
                        return;
                    }

                    var url = btn.getAttribute('data-submit-url');
                    if (! url)
                    {
                        return;
                    }

                    hideAlert(modalEl);

                    var label = btn.getAttribute('data-submit-label') || @json(__('Enviar'));
                    var sending = @json(__('Enviando…'));

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + sending;

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfHeader(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                        .then(function (res)
                        {
                            return res.text().then(function (raw)
                            {
                                try
                                {
                                    return { ok: res.ok, data: JSON.parse(raw) };
                                }
                                catch (parseErr)
                                {
                                    return {
                                        ok: false,
                                        data: {
                                            success: false,
                                            message: raw.slice(0, 400) || @json(__('Respuesta no válida del servidor.')),
                                        },
                                    };
                                }
                            });
                        })
                        .catch(function ()
                        {
                            return { ok: false, data: { success: false, message: @json(__('Error de red al enviar el correo de prueba.')) } };
                        })
                        .then(function (packet)
                        {
                            btn.disabled = false;
                            btn.textContent = label;

                            var data = packet.data || {};

                            if (packet.ok && data.success)
                            {
                                var inst = typeof bootstrap !== 'undefined' ? bootstrap.Modal.getInstance(modalEl) : null;
                                if (inst)
                                {
                                    inst.hide();
                                }

                                var successBody = data.email
                                    ? (@json(__('Correo de prueba enviado exitosamente a')) + ' ' + data.email)
                                    : (data.message || @json(__('Correo de prueba enviado exitosamente.')));

                                if (typeof Swal !== 'undefined')
                                {
                                    Swal.fire({
                                        title: @json(__('Enviado')),
                                        text: successBody,
                                        icon: 'success',
                                        customClass: {
                                            confirmButton: 'btn btn-success waves-effect waves-light',
                                        },
                                        buttonsStyling: false,
                                    });
                                } else
                                {
                                    showAlert(modalEl, 'success', successBody);
                                }

                                return;
                            }

                            var err = (data && data.message) ? data.message : @json(__('No se pudo enviar el correo de prueba.'));
                            showAlert(modalEl, 'danger', err);
                        });
                });
            })();
        </script>
    @endpush
@endonce
