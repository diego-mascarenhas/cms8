<script>
            (function ()
            {
                function csrfHeader()
                {
                    var m = document.querySelector('meta[name="csrf-token"]');

                    return m ? m.getAttribute('content') : '';
                }

                function buildTestSendJsonBody(modalEl, btn)
                {
                    var payload = {};
                    var recInput = modalEl.querySelector('[data-email-test-send-recipients]');
                    if (recInput && recInput.value && String(recInput.value).trim())
                    {
                        payload.test_recipients = String(recInput.value).trim();
                    }

                    var tid = btn.getAttribute('data-email-test-send-template-id');
                    if (! tid)
                    {
                        return JSON.stringify(payload);
                    }

                    var templateId = parseInt(tid, 10);
                    if (isNaN(templateId))
                    {
                        return JSON.stringify(payload);
                    }

                    payload.template_id = templateId;

                    var nameEl = document.querySelector('input[name="name"]');
                    if (nameEl && nameEl.value && String(nameEl.value).trim())
                    {
                        payload.draft_name = String(nameEl.value).trim();
                    }

                    var subjectEl = document.querySelector('input#subject[name="subject"]');
                    if ((! payload.draft_name) && subjectEl && subjectEl.value && String(subjectEl.value).trim())
                    {
                        payload.draft_name = String(subjectEl.value).trim();
                    }

                    var internalTitle = document.querySelector('input#internal-title');
                    if ((! payload.draft_name) && internalTitle && internalTitle.value && String(internalTitle.value).trim())
                    {
                        payload.draft_name = String(internalTitle.value).trim();
                    }

                    var textArea = document.querySelector('textarea#text[name="text"]');
                    if (textArea && textArea.value && String(textArea.value).trim())
                    {
                        payload.fallback_text = String(textArea.value).trim();
                    }

                    return JSON.stringify(payload);
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

                function bindTestSendModals()
                {
                    document.querySelectorAll('[id^="email-test-send-modal-"]').forEach(function (modalEl)
                    {
                        if (modalEl.dataset.emailTestSendBound === '1')
                        {
                            return;
                        }
                        modalEl.dataset.emailTestSendBound = '1';

                        modalEl.addEventListener('show.bs.modal', function ()
                        {
                            hideAlert(modalEl);
                            var recInp = modalEl.querySelector('[data-email-test-send-recipients]');
                            if (recInp && recInp.dataset.defaultRecipients)
                            {
                                recInp.value = recInp.dataset.defaultRecipients;
                            }
                            var submitBtn = modalEl.querySelector('[data-email-test-send-submit]');
                            if (submitBtn)
                            {
                                submitBtn.disabled = false;
                                var label = submitBtn.getAttribute('data-submit-label') || @json(__('Enviar'));
                                submitBtn.textContent = label;
                            }
                        });
                    });
                }

                window.openEmailTestSendModal = function (modalId)
                {
                    var el = document.getElementById(modalId);
                    if (! el || typeof bootstrap === 'undefined')
                    {
                        return;
                    }

                    bootstrap.Modal.getOrCreateInstance(el).show();
                };

                if (document.readyState === 'loading')
                {
                    document.addEventListener('DOMContentLoaded', bindTestSendModals);
                } else
                {
                    bindTestSendModals();
                }

                window.humaBindEmailTestSendModals = bindTestSendModals;

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

                    e.preventDefault();
                    e.stopPropagation();

                    hideAlert(modalEl);

                    var label = btn.getAttribute('data-submit-label') || @json(__('Enviar'));
                    var sending = @json(__('Enviando…'));

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + sending;

                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfHeader(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: buildTestSendJsonBody(modalEl, btn),
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

                                var successBody = (data.emails && data.emails.length)
                                    ? (@json(__('Correo de prueba enviado exitosamente a')) + ' ' + data.emails.join(', '))
                                    : (data.email
                                        ? (@json(__('Correo de prueba enviado exitosamente a')) + ' ' + data.email)
                                        : (data.message || @json(__('Correo de prueba enviado exitosamente.'))));

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
