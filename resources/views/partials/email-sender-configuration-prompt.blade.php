@php
    $emailSenderTeam = $team ?? auth()->user()->currentTeam ?? auth()->user()->teams->first();
    if ($emailSenderTeam) {
        $emailSenderTeam->unsetRelation('settings');
    }
    $canUpdateEmailSenderTeam = $emailSenderTeam && auth()->user()->can('update', $emailSenderTeam);
    $showEmailSenderBanner = $emailSenderTeam
        && ! $emailSenderTeam->hasOutgoingEmailSenderConfigured();
    $emailSenderDefaultName = env('MAIL_FROM_NAME');
    $emailSenderDefaultAddress = env('MAIL_FROM_ADDRESS');
    $emailSenderCurrentName = $emailSenderTeam?->getSetting('mail_from_name', '');
    $emailSenderCurrentAddress = $emailSenderTeam?->getSetting('mail_from_address', '');
    $wrapEmailSenderInTopRow = $topRow ?? false;
@endphp
@if ($showEmailSenderBanner)
    @if ($wrapEmailSenderInTopRow)
        <div class="row mb-4" id="email-sender-config-banner-row">
            <div class="col-12">
    @endif
    <div class="alert alert-warning mb-0" role="alert" id="email-sender-config-banner">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <i class="ti ti-mail ti-lg me-2 flex-shrink-0"></i>
            <div class="flex-grow-1 min-w-0">
                <span class="fw-medium d-block">{{ __('app.email_sender_banner_title') }}</span>
                <span class="small text-muted">{{ __('app.email_sender_banner_body') }}</span>
            </div>
            @if ($canUpdateEmailSenderTeam)
                <button
                    type="button"
                    class="btn btn-warning btn-sm waves-effect waves-light"
                    data-bs-toggle="modal"
                    data-bs-target="#emailSenderConfigModal"
                >
                    <i class="ti ti-settings ti-sm me-1"></i>{{ __('app.email_sender_banner_button') }}
                </button>
            @else
                <a
                    href="{{ route('team-settings.edit', ['team' => $emailSenderTeam, 'group' => 'email']) }}"
                    class="btn btn-warning btn-sm waves-effect waves-light"
                >
                    <i class="ti ti-settings ti-sm me-1"></i>{{ __('app.email_sender_banner_button') }}
                </a>
            @endif
        </div>
    </div>
    @if ($wrapEmailSenderInTopRow)
            </div>
        </div>
    @endif

    @if ($canUpdateEmailSenderTeam)
        <div class="modal fade" id="emailSenderConfigModal" tabindex="-1" aria-labelledby="emailSenderConfigModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="emailSenderConfigModalLabel">{{ __('app.email_sender_modal_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <form id="emailSenderConfigForm" novalidate>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" id="emailSenderConfigFormError" role="alert"></div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="emailSenderFromName" class="form-label">{{ __('app.email_sender_modal_from_name') }}</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="emailSenderFromName"
                                        name="mail_from_name"
                                        value="{{ old('mail_from_name', $emailSenderCurrentName) }}"
                                        placeholder="{{ $emailSenderDefaultName }}"
                                        required
                                    >
                                    <div class="form-text">{{ __('app.email_sender_modal_from_name_help', ['default' => $emailSenderDefaultName]) }}</div>
                                    <div class="invalid-feedback" id="emailSenderFromNameError"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="emailSenderFromAddress" class="form-label">{{ __('app.email_sender_modal_from_email') }}</label>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="emailSenderFromAddress"
                                        name="mail_from_address"
                                        value="{{ old('mail_from_address', $emailSenderCurrentAddress) }}"
                                        placeholder="{{ $emailSenderDefaultAddress }}"
                                        required
                                    >
                                    <div class="form-text">{{ __('app.email_sender_modal_from_email_help', ['default' => $emailSenderDefaultAddress]) }}</div>
                                    <div class="invalid-feedback" id="emailSenderFromAddressError"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" id="emailSenderConfigSubmit">
                                <i class="ti ti-device-floppy me-1"></i>{{ __('Save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var form = document.getElementById('emailSenderConfigForm');
                var modalEl = document.getElementById('emailSenderConfigModal');
                var bannerRow = document.getElementById('email-sender-config-banner-row');
                var banner = document.getElementById('email-sender-config-banner');
                var formError = document.getElementById('emailSenderConfigFormError');
                var submitBtn = document.getElementById('emailSenderConfigSubmit');
                var saveUrl = @json(route('team-settings.update-email-sender', $emailSenderTeam));
                var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

                if (! form || ! modalEl) {
                    return;
                }

                function clearFieldErrors() {
                    form.querySelectorAll('.is-invalid').forEach(function (el) {
                        el.classList.remove('is-invalid');
                    });
                    ['emailSenderFromNameError', 'emailSenderFromAddressError'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) {
                            el.textContent = '';
                        }
                    });
                    if (formError) {
                        formError.classList.add('d-none');
                        formError.textContent = '';
                    }
                }

                function showValidationErrors(errors) {
                    clearFieldErrors();
                    Object.keys(errors || {}).forEach(function (key) {
                        var messages = errors[key];
                        if (! Array.isArray(messages) || messages.length === 0) {
                            return;
                        }
                        if (key === 'mail_from_name') {
                            var nameInput = document.getElementById('emailSenderFromName');
                            var nameError = document.getElementById('emailSenderFromNameError');
                            if (nameInput) {
                                nameInput.classList.add('is-invalid');
                            }
                            if (nameError) {
                                nameError.textContent = messages[0];
                            }
                        }
                        if (key === 'mail_from_address') {
                            var emailInput = document.getElementById('emailSenderFromAddress');
                            var emailError = document.getElementById('emailSenderFromAddressError');
                            if (emailInput) {
                                emailInput.classList.add('is-invalid');
                            }
                            if (emailError) {
                                emailError.textContent = messages[0];
                            }
                        }
                    });
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    clearFieldErrors();

                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }

                    fetch(saveUrl, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            mail_from_name: document.getElementById('emailSenderFromName')?.value ?? '',
                            mail_from_address: document.getElementById('emailSenderFromAddress')?.value ?? '',
                        }),
                    })
                        .then(function (response) {
                            return response.json().then(function (payload) {
                                return { ok: response.ok, status: response.status, payload: payload };
                            });
                        })
                        .then(function (result) {
                            if (! result.ok) {
                                if (result.status === 422 && result.payload?.errors) {
                                    showValidationErrors(result.payload.errors);
                                    return;
                                }
                                if (formError) {
                                    formError.textContent = result.payload?.message ?? '{{ __('app.email_sender_config_save_failed') }}';
                                    formError.classList.remove('d-none');
                                }
                                return;
                            }

                            if (bannerRow) {
                                bannerRow.remove();
                            } else if (banner) {
                                banner.remove();
                            }

                            var modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) {
                                modal.hide();
                            }
                        })
                        .catch(function () {
                            if (formError) {
                                formError.textContent = '{{ __('app.email_sender_config_save_failed') }}';
                                formError.classList.remove('d-none');
                            }
                        })
                        .finally(function () {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                            }
                        });
                });
            });
        </script>
    @endif
@endif
