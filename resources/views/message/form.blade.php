@extends('layouts/layoutMaster')

@php
    $messageFpLocale = strtolower(substr(str_replace('_', '-', app()->getLocale()), 0, 2));
    $messageFpLocaleBundle = in_array($messageFpLocale, ['es', 'fr', 'de', 'it', 'pt'], true);
    $scheduleMin = \Carbon\Carbon::now(config('app.timezone'))->format('Y-m-d H:i');
    $rawScheduleAt = old('schedule_send_at');
    if ($rawScheduleAt === null || $rawScheduleAt === '')
    {
        $rawScheduleAt = (isset($data->scheduled_send_at) && $data->scheduled_send_at)
            ? \Carbon\Carbon::parse($data->scheduled_send_at)->timezone(config('app.timezone'))->format('Y-m-d H:i')
            : '';
    }
    $messageScheduleInputValue = '';
    if ($rawScheduleAt !== '')
    {
        try
        {
            $messageScheduleInputValue = \Carbon\Carbon::parse($rawScheduleAt)->timezone(config('app.timezone'))->format('Y-m-d H:i');
        }
        catch (\Throwable $e)
        {
            $messageScheduleInputValue = '';
        }
    }
@endphp

@section('title', __('Messages'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<style>
    .message-template-quill-wrap .ql-image-upload {
        width: auto;
        padding: 0 0.35rem;
    }

    .message-template-quill-wrap .ql-image-upload .ti {
        font-size: 1rem;
        line-height: 1;
        vertical-align: middle;
    }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@if ($messageFpLocaleBundle)
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/{{ $messageFpLocale }}.js"></script>
@endif
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>

<script>
// Delete message function with Sweet Alert
function deleteMessage(messageId) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¿Deseas eliminar este mensaje?",
        icon: 'warning',
        showCancelButton: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-label-secondary'
        },
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Create a form to submit the delete request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/message/${messageId}`;

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            form.appendChild(csrfToken);

            // Add method override for DELETE
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';
            form.appendChild(methodInput);

            // Submit the form
            document.body.appendChild(form);
            form.submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var confirmBtn = document.getElementById('message-schedule-confirm-btn');
    var scheduleInput = document.getElementById('message-schedule-at-input');
    var storeForm = document.getElementById('message-store-form');
    var helper = document.getElementById('message-schedule-submit-helper');
    var scheduleModal = document.getElementById('messageScheduleModal');
    if (!confirmBtn || !scheduleInput || !storeForm || !helper || !scheduleModal) {
        return;
    }
    var fpLocaleKey = scheduleInput.getAttribute('data-fp-locale') || '';
    var fpOpts = {
        enableTime: true,
        time_24hr: true,
        dateFormat: 'Y-m-d H:i',
        minuteIncrement: 1,
        allowInput: false,
        clickOpens: true,
        monthSelectorType: 'static'
    };
    var minDt = scheduleInput.getAttribute('data-min-datetime');
    if (minDt) {
        fpOpts.minDate = minDt;
    }
    if (fpLocaleKey && typeof flatpickr !== 'undefined' && flatpickr.l10ns && flatpickr.l10ns[fpLocaleKey]) {
        fpOpts.locale = flatpickr.l10ns[fpLocaleKey];
    }
    if (window.jQuery && window.jQuery.fn.flatpickr) {
        window.jQuery(scheduleInput).flatpickr(fpOpts);
    } else if (typeof flatpickr === 'function') {
        flatpickr(scheduleInput, fpOpts);
    } else if (window.flatpickr) {
        window.flatpickr(scheduleInput, fpOpts);
    }
    confirmBtn.addEventListener('click', function () {
        if (!scheduleInput.value) {
            var requiredMsg = scheduleModal.getAttribute('data-msg-required') || '';
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', text: requiredMsg, buttonsStyling: false, customClass: { confirmButton: 'btn btn-primary' } });
            } else {
                window.alert(requiredMsg);
            }
            return;
        }
        storeForm.querySelectorAll('input[name="schedule_send_at"]').forEach(function (el) {
            el.remove();
        });
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'schedule_send_at';
        hidden.value = scheduleInput.value;
        storeForm.appendChild(hidden);
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(scheduleModal);
            if (inst) {
                scheduleModal.addEventListener('hidden.bs.modal', function onScheduleModalHidden() {
                    scheduleModal.removeEventListener('hidden.bs.modal', onScheduleModalHidden);
                    helper.click();
                });
                inst.hide();

                return;
            }
        }
        helper.click();
    });
});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Messages') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }} News</h4>
        <p class="text-muted small mb-0">{{ __('app.message_form_subtitle') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3 align-items-center">
        <div class="btn-group">
            <button type="submit" form="message-store-form" name="save_intent" value="save" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i>{{ __('Save') }}
            </button>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden">{{ __('app.message_save_options_dropdown') }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <button type="submit" form="message-store-form" name="save_intent" value="save_send" class="dropdown-item">
                        <i class="ti ti-send me-1"></i>{{ __('app.message_save_and_send') }}
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#messageScheduleModal">
                        <i class="ti ti-calendar-time me-1"></i>{{ __('app.message_save_and_schedule') }}
                    </button>
                </li>
            </ul>
        </div>
        @if(isset($data->id))
            <button type="button" class="btn btn-danger" onclick="deleteMessage({{ $data->id }})">
                <i class="ti ti-trash me-1"></i>{{ __('Delete') }}
            </button>
        @endif
        <a href="{{ route('message.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('app.Back') }}
        </a>
    </div>
</div>

<form id="message-store-form" action="{{ route('message.store') }}" method="POST">
	@csrf
	@if ($errors->has('save_intent'))
		<div class="alert alert-danger mb-3" role="alert">{{ $errors->first('save_intent') }}</div>
	@endif
	@if ($errors->has('schedule_send_at'))
		<div class="alert alert-danger mb-3" role="alert">{{ $errors->first('schedule_send_at') }}</div>
	@endif
	<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
	<input type="hidden" name="type_id" value="{{ old('type_id', $data->type_id ?? 1) }}">

	@php
		$removeMailTemplate = $removeMailTemplate ?? false;
		$templateSelectDisabled = (isset($data->hasDeliveries) && $data->hasDeliveries);
	@endphp

	<div id="message-form-template-id-slot"></div>

	<div class="card mb-4">
		<h5 class="card-header">{{ __('app.message_form_card_title') }}</h5>
		<div class="card-body">
		<div class="row g-3">
			<div class="col-12">
				<x-input-general id="name" label="{{ __('Subject') }} (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-6">
				@php
					$messageCategorySelected = old(
						'message_category_ids',
						isset($data->contactCategories)
							? $data->contactCategories->pluck('id')->all()
							: [],
					);
				@endphp
				<x-module-categories-select
					id="message_category_ids"
					name="message_category_ids[]"
					errorKey="message_category_ids"
					label="{{ __('app.Tags') }}"
					moduleKey="contacts"
					:selected="$messageCategorySelected"
					:multiple="true"
					:allowEmpty="true"
					:emptyText="__('app.message_form_categories_all')"
					:disabled="isset($data->hasDeliveries) && $data->hasDeliveries"
				/>
				@if(isset($data->hasDeliveries) && $data->hasDeliveries)
					<div class="form-text text-warning mt-1">
						<i class="ti ti-alert-triangle me-1"></i>No se puede cambiar la categoría porque el mensaje ya tiene entregas creadas.
					</div>
				@endif
			</div>
			<div class="col-md-6">
				<x-input-select
					id="contact_status_id"
					label="{{ __('Estado del contacto') }}"
					:options="$data->contactStatuses ?? []"
					value="{{ old('contact_status_id', $data->contact_status_id ?? '') }}"
					:placeholder="__('app.message_form_contact_status_all')"
					:disabled="isset($data->hasDeliveries) && $data->hasDeliveries"
				/>
				@if(isset($data->hasDeliveries) && $data->hasDeliveries)
					<div class="form-text text-warning mt-1">
						<i class="ti ti-alert-triangle me-1"></i>No se puede cambiar el estado porque el mensaje ya tiene entregas creadas.
					</div>
				@endif
			</div>
			@php
				$messageTemplateSelectRequired = empty($removeMailTemplate) && empty($templateSelectDisabled);
			@endphp
			<div class="col-md-12" id="message-form-template-field-wrapper">
				<x-input-select
					id="template_id"
					label="{{ __('Plantilla') }}"
					:options="$data->templates ?? []"
					value="{{ old('template_id', $removeMailTemplate ? '' : ($data->template_id ?? '')) }}"
					:placeholder="$removeMailTemplate ? __('app.message_form_template_none') : null"
					:required="$messageTemplateSelectRequired"
					:allowClear="! $messageTemplateSelectRequired"
					:disabled="$templateSelectDisabled"
				/>
				@if ($messageTemplateSelectRequired)
					<div class="form-text mt-1">{{ __('app.message_form_template_required_help') }}</div>
				@endif
			</div>
			<div class="col-md-12">
				<x-input-textarea id="text" label="{{ __('app.Preview') }} (*)" value="{{ old('text', $data->text?? '') }}" />
				<div class="form-text mt-1">
					{{ __('app.message_form_alt_text_help') }}
				</div>
			</div>
		</div>
		</div>
	</div>

	<div
		id="message-email-template-preview-mount"
		@if (filled($emailPreviewBundleHtml ?? null))
			data-dynamic-preview="1"
			data-loaded-template-id="{{ (int) old('template_id', $data->template_id ?? 0) }}"
		@endif
	>
		@if (filled($emailPreviewBundleHtml ?? null))
			{!! $emailPreviewBundleHtml !!}
		@endif
	</div>

	@if (isset($data->id))
		<input type="hidden" name="status_id" value="{{ (((int) old('status_id', (int) ($data->status_id ?? 0))) === 1) ? 1 : 0 }}">
	@endif
	<input type="hidden" name="show_unsubscribe" value="{{ (int) (bool) old('show_unsubscribe', data_get($data, 'show_unsubscribe', 1)) }}">
	<input type="hidden" name="enable_open_tracking" value="{{ (int) (bool) old('enable_open_tracking', data_get($data, 'enable_open_tracking', 1)) }}">
	<input type="hidden" name="enable_click_tracking" value="{{ (int) (bool) old('enable_click_tracking', data_get($data, 'enable_click_tracking', 1)) }}">
	<button type="submit" name="save_intent" value="save_schedule" id="message-schedule-submit-helper" class="d-none" tabindex="-1" aria-hidden="true"></button>
</form>

<div class="modal fade" id="messageScheduleModal" tabindex="-1" aria-labelledby="messageScheduleModalLabel" aria-hidden="true" data-msg-required="{{ e(__('app.message_schedule_validation_required')) }}">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="messageScheduleModalLabel">{{ __('app.message_schedule_modal_title') }}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
			</div>
			<div class="modal-body">
				<label for="message-schedule-at-input" class="form-label">{{ __('app.message_schedule_modal_datetime_label') }}</label>
				<input
					type="text"
					class="form-control"
					id="message-schedule-at-input"
					value="{{ $messageScheduleInputValue }}"
					data-min-datetime="{{ $scheduleMin }}"
					data-fp-locale="{{ $messageFpLocaleBundle ? $messageFpLocale : '' }}"
					autocomplete="off"
					readonly
					required
				>
				<div class="form-text">{{ __('app.message_schedule_modal_help') }}</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('app.message_schedule_modal_cancel') }}</button>
				<button type="button" class="btn btn-primary" id="message-schedule-confirm-btn">{{ __('app.message_schedule_modal_confirm') }}</button>
			</div>
		</div>
	</div>
</div>

	<form
		id="message-email-template-duplicate-form"
		method="post"
		class="d-none"
		aria-hidden="true"
		action="#"
	>
		@csrf
		<input type="hidden" name="return_url" value="{{ isset($data->id) ? route('message.edit', $data->id) : request()->fullUrl() }}">
		<input type="hidden" name="template_html" id="message-email-template-duplicate-template-html" value="">
	</form>
	<form
		id="message-update-template-form"
		method="post"
		action="{{ route('message.sync-template-html') }}"
		class="d-none"
		aria-hidden="true"
	>
		@csrf
		<input type="hidden" name="template_id" id="message-update-template-template-id" value="">
		<input type="hidden" name="message_id" id="message-update-template-message-id" value="">
		<input type="hidden" name="return_url" id="message-update-template-return-url" value="{{ isset($data->id) ? route('message.edit', $data->id) : request()->fullUrl() }}">
		<input type="hidden" name="template_html" id="message-update-template-html" value="">
	</form>
	<form
		id="message-open-visual-editor-form"
		method="post"
		action="{{ route('message.sync-template-html-open-editor') }}"
		class="d-none"
		aria-hidden="true"
	>
		@csrf
		<input type="hidden" name="template_id" id="message-open-visual-editor-template-id" value="">
		<input type="hidden" name="message_id" id="message-open-visual-editor-message-id" value="">
		<input type="hidden" name="return_url" id="message-open-visual-editor-return-url" value="">
		<input type="hidden" name="template_html" id="message-open-visual-editor-template-html" value="">
	</form>
@push('scripts')
@include('message.partials.email-test-send-modal-script')
<script>
(function ()
{
    var mount = document.getElementById('message-email-template-preview-mount');
    var previewUrl = @json(route('message.template-email-preview'));
    var messageFormMessageId = @json(isset($data->id) ? (int) $data->id : null);
    var templateLockDeliveries = @json((bool) (isset($data->hasDeliveries) && $data->hasDeliveries));
    var defaultTemplateId = @json((int) ($messageFormDefaultTemplateId ?? 0));
    var allowNoTemplate = @json((bool) ($removeMailTemplate ?? false));

    if (! mount)
    {
        return;
    }

    var templateSlot = document.getElementById('message-form-template-id-slot');
    var duplicateForm = document.getElementById('message-email-template-duplicate-form');
    var initialDuplicateActionUrl = @json($emailPreviewDuplicateActionUrl ?? null);

    if (duplicateForm && initialDuplicateActionUrl)
    {
        duplicateForm.setAttribute('action', initialDuplicateActionUrl);
    }

    if (mount.dataset.dynamicPreview === '1' && mount.querySelector('#message-template-html-quill-editor'))
    {
        if (window.humaBindEmailTestSendModals)
        {
            window.humaBindEmailTestSendModals();
        }
        if (window.humaInitMessageTemplateHtmlQuill)
        {
            window.humaInitMessageTemplateHtmlQuill(mount);
        }
    }

    function humaCleanupBootstrapModalBackdrop()
    {
        document.querySelectorAll('.modal-backdrop').forEach(function (el)
        {
            el.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
    }

    window.humaReadMessageTemplateHtmlFromComposer = function (root)
    {
        root = root || document.getElementById('message-email-template-preview-mount') || document;
        if (window.humaSyncMessageTemplateHtmlQuill)
        {
            window.humaSyncMessageTemplateHtmlQuill();
        }
        var quillEditor = root.querySelector('#message-template-html-quill-editor .ql-editor');
        if (quillEditor && String(quillEditor.innerHTML || '').trim() !== '')
        {
            return quillEditor.innerHTML;
        }
        var ta = root.querySelector('#message-template-html-body');
        if (ta && String(ta.value || '').trim() !== '')
        {
            return ta.value;
        }
        if (window.humaMessageTemplateQuillInstance && window.humaMessageTemplateQuillInstance.root)
        {
            return window.humaMessageTemplateQuillInstance.root.innerHTML || '';
        }

        return '';
    };

    function humaSyncDuplicateTemplateHtmlField()
    {
        var htmlInput = document.getElementById('message-email-template-duplicate-template-html');
        if (! htmlInput)
        {
            return;
        }
        htmlInput.value = window.humaReadMessageTemplateHtmlFromComposer(mount);
    }

    if (duplicateForm)
    {
        duplicateForm.addEventListener('submit', function (e)
        {
            var action = duplicateForm.getAttribute('action') || '';
            if (action === '' || action === '#')
            {
                return;
            }
            if (duplicateForm.dataset.humaDupProceed === '1')
            {
                delete duplicateForm.dataset.humaDupProceed;
                humaSyncDuplicateTemplateHtmlField();
                humaCleanupBootstrapModalBackdrop();
                return;
            }
            var dupModal = document.getElementById('message-email-template-duplicate-modal');
            if (! dupModal || ! dupModal.classList.contains('show'))
            {
                humaSyncDuplicateTemplateHtmlField();
                return;
            }
            e.preventDefault();
            humaSyncDuplicateTemplateHtmlField();
            function proceedDuplicateSubmit()
            {
                humaCleanupBootstrapModalBackdrop();
                duplicateForm.dataset.humaDupProceed = '1';
                duplicateForm.submit();
            }
            if (typeof bootstrap === 'undefined' || ! bootstrap.Modal)
            {
                proceedDuplicateSubmit();
                return;
            }
            var inst = bootstrap.Modal.getInstance(dupModal);
            if (! inst)
            {
                proceedDuplicateSubmit();
                return;
            }
            dupModal.addEventListener('hidden.bs.modal', function onDupModalHidden()
            {
                dupModal.removeEventListener('hidden.bs.modal', onDupModalHidden);
                proceedDuplicateSubmit();
            });
            inst.hide();
        });
    }

    function restoreTemplateFieldUi()
    {
        if (templateSlot)
        {
            templateSlot.innerHTML = '';
        }
        if (duplicateForm)
        {
            duplicateForm.setAttribute('action', '#');
        }
    }

    function clearDynamicPreview()
    {
        mount.innerHTML = '';
        mount.dataset.dynamicPreview = '0';
        delete mount.dataset.loadedTemplateId;
        if (window.humaMessageTemplateQuillInstance)
        {
            window.humaMessageTemplateQuillInstance = null;
        }
        restoreTemplateFieldUi();
    }

    function removeStaleEmailTestSendModalsOutsidePreviewMount()
    {
        var selector = messageFormMessageId
            ? '#email-test-send-modal-' + messageFormMessageId
            : '[id^="email-test-send-modal-draft-"]';

        document.querySelectorAll(selector).forEach(function (el)
        {
            if (mount.contains(el))
            {
                return;
            }

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal)
            {
                var inst = bootstrap.Modal.getInstance(el);

                if (inst)
                {
                    inst.hide();
                    inst.dispose();
                }
            }

            el.remove();
        });
    }

    var previewFetchToken = 0;

    function humaDestroyMessageTemplateHtmlQuillIn(root)
    {
        if (window.humaMessageTemplateQuillInstance)
        {
            window.humaMessageTemplateQuillInstance = null;
        }
        root = root || mount;
        if (! root)
        {
            return;
        }
        root.querySelectorAll('[data-quill-bound="1"]').forEach(function (el)
        {
            delete el.dataset.quillBound;
            el.innerHTML = '';
            el.className = 'message-template-html-quill-root';
        });
    }

    function loadEmailTemplatePreview(templateId)
    {
        if (mount.dataset.dynamicPreview === '1' && mount.dataset.loadedTemplateId === String(templateId))
        {
            return;
        }

        var fetchToken = ++previewFetchToken;

        var params = new URLSearchParams({ template_id: String(templateId), return_url: window.location.href.split('#')[0] });
        if (messageFormMessageId)
        {
            params.set('message_id', String(messageFormMessageId));
        }
        var nameEl = document.getElementById('name');
        if (nameEl && nameEl.value && String(nameEl.value).trim())
        {
            params.set('context_name', String(nameEl.value).trim());
        }

        mount.dataset.dynamicPreview = 'loading';

        fetch(previewUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res)
            {
                if (! res.ok)
                {
                    throw new Error('bad status');
                }
                return res.json();
            })
            .then(function (data)
            {
                if (fetchToken !== previewFetchToken)
                {
                    return;
                }
                if (! data || typeof data.html !== 'string')
                {
                    throw new Error('bad payload');
                }
                removeStaleEmailTestSendModalsOutsidePreviewMount();
                humaDestroyMessageTemplateHtmlQuillIn(mount);
                mount.innerHTML = data.html;
                mount.dataset.dynamicPreview = '1';
                mount.dataset.loadedTemplateId = String(templateId);
                var frame = mount.querySelector('[data-email-template-preview-frame="1"]');
                if (frame && typeof data.preview_html === 'string')
                {
                    frame.srcdoc = data.preview_html;
                }
                if (duplicateForm && data.duplicate_action_url)
                {
                    duplicateForm.setAttribute('action', data.duplicate_action_url);
                }
                if (window.humaBindEmailTestSendModals)
                {
                    window.humaBindEmailTestSendModals();
                }
                if (window.humaInitMessageTemplateHtmlQuill)
                {
                    window.humaInitMessageTemplateHtmlQuill(mount);
                }
            })
            .catch(function ()
            {
                if (mount.dataset.dynamicPreview === 'loading')
                {
                    mount.innerHTML = '';
                    mount.dataset.dynamicPreview = '0';
                    delete mount.dataset.loadedTemplateId;
                    restoreTemplateFieldUi();
                }
            });
    }

    window.jQuery(function ($)
    {
        var $tpl = $('#template_id');
        if (! $tpl.length)
        {
            return;
        }

        function humaSyncTemplateIdQueryParam(templateId)
        {
            if (! templateId)
            {
                return;
            }
            try
            {
                var url = new URL(window.location.href, window.location.origin);
                if (url.searchParams.get('template_id') === String(templateId))
                {
                    return;
                }
                url.searchParams.set('template_id', String(templateId));
                window.history.replaceState({}, '', url.pathname + url.search + (url.hash || ''));
            }
            catch (urlErr)
            {
                /* ignore */
            }
        }

        function evaluate()
        {
            if (templateLockDeliveries)
            {
                return;
            }

            var tid = ($tpl.val() || '').toString().trim();
            if (! tid)
            {
                if (! allowNoTemplate && defaultTemplateId > 0)
                {
                    $tpl.val(String(defaultTemplateId)).trigger('change');
                    return;
                }
                clearDynamicPreview();
                return;
            }

            humaSyncTemplateIdQueryParam(tid);
            loadEmailTemplatePreview(parseInt(tid, 10));
        }

        $tpl.on('change select2:select', evaluate);
        window.setTimeout(function ()
        {
            var queryTid = '';
            try
            {
                queryTid = (new URLSearchParams(window.location.search).get('template_id') || '').trim();
            }
            catch (queryErr)
            {
                queryTid = '';
            }
            if (queryTid && ($tpl.val() || '').toString().trim() !== queryTid)
            {
                $tpl.val(queryTid).trigger('change');

                return;
            }
            evaluate();
        }, 0);
    });
})();
</script>
<script>
window.humaMessageTemplateQuillUploadUrl = @json(route('laravel-grapesjs.asset.store'));
window.humaMessageTemplateQuillLabels = {
    imageUrl: @json(__('app.message_quill_image_url')),
    imageUpload: @json(__('app.message_quill_image_upload')),
    imageUploadFailed: @json(__('app.message_quill_image_upload_failed')),
};
</script>
<script>
(function ()
{
    window.humaMessageTemplateQuillInstance = null;

    function humaGetMessageTemplateQuillCsrfToken()
    {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function humaInsertImageIntoQuill(quill, url)
    {
        if (! quill || ! url)
        {
            return;
        }

        var range = quill.getSelection(true);
        var index = range ? range.index : quill.getLength();

        quill.insertEmbed(index, 'image', url, 'user');
        quill.setSelection(index + 1, 0, 'silent');
    }

    function humaUploadMessageTemplateQuillImage(quill)
    {
        var uploadUrl = window.humaMessageTemplateQuillUploadUrl;
        var labels = window.humaMessageTemplateQuillLabels || {};

        if (! uploadUrl)
        {
            return;
        }

        var input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/png,image/jpeg,image/gif,image/webp,image/svg+xml');
        input.style.display = 'none';
        document.body.appendChild(input);

        input.addEventListener('change', function ()
        {
            var file = input.files && input.files[0];
            input.remove();

            if (! file)
            {
                return;
            }

            var formData = new FormData();
            formData.append('file[]', file);

            fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': humaGetMessageTemplateQuillCsrfToken(),
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(function (response)
                {
                    if (! response.ok)
                    {
                        throw new Error('upload_failed');
                    }

                    return response.json();
                })
                .then(function (payload)
                {
                    var urls = payload && payload.data ? payload.data : [];
                    var url = Array.isArray(urls) ? urls[0] : null;

                    if (! url)
                    {
                        throw new Error('upload_empty');
                    }

                    humaInsertImageIntoQuill(quill, url);
                })
                .catch(function ()
                {
                    window.alert(labels.imageUploadFailed || 'Could not upload the image.');
                });
        });

        input.click();
    }

    function humaBindMessageTemplateQuillImageUpload(quill, mountEl)
    {
        if (! quill || ! mountEl)
        {
            return;
        }

        var toolbarEl = mountEl.querySelector('.ql-toolbar');
        if (! toolbarEl || toolbarEl.querySelector('.ql-image-upload'))
        {
            return;
        }

        var labels = window.humaMessageTemplateQuillLabels || {};
        var imageButton = toolbarEl.querySelector('button.ql-image');
        var formats = document.createElement('span');
        formats.className = 'ql-formats';

        var uploadButton = document.createElement('button');
        uploadButton.type = 'button';
        uploadButton.className = 'ql-image-upload';
        uploadButton.setAttribute('title', labels.imageUpload || 'Upload image');
        uploadButton.innerHTML = '<i class="ti ti-upload" aria-hidden="true"></i>';
        uploadButton.addEventListener('click', function (event)
        {
            event.preventDefault();
            humaUploadMessageTemplateQuillImage(quill);
        });

        formats.appendChild(uploadButton);

        if (imageButton && imageButton.parentElement)
        {
            imageButton.setAttribute('title', labels.imageUrl || 'Insert image from URL');
            imageButton.parentElement.insertAdjacentElement('afterend', formats);
        }
        else
        {
            toolbarEl.appendChild(formats);
        }
    }

    window.humaSyncMessageTemplateHtmlQuill = function ()
    {
        if (! window.humaMessageTemplateQuillInstance)
        {
            return;
        }
        var ta = document.getElementById('message-template-html-body');
        if (! ta)
        {
            return;
        }
        ta.value = window.humaMessageTemplateQuillInstance.root.innerHTML;
    };

    /**
     * @param {ParentNode|Document|null} root
     */
    window.humaDestroyMessageTemplateHtmlQuillIn = function (root)
    {
        if (window.humaMessageTemplateQuillInstance)
        {
            window.humaMessageTemplateQuillInstance = null;
        }
        root = root || document;
        root.querySelectorAll('#message-template-html-quill-editor[data-quill-bound="1"]').forEach(function (el)
        {
            delete el.dataset.quillBound;
            el.innerHTML = '';
            el.className = 'message-template-html-quill-root';
        });
    };

    window.humaInitMessageTemplateHtmlQuill = function (root)
    {
        root = root || document;
        if (typeof Quill === 'undefined')
        {
            return;
        }
        var ta = root.querySelector('#message-template-html-body');
        var mountEl = root.querySelector('#message-template-html-quill-editor');
        if (! ta || ! mountEl)
        {
            return;
        }
        if (mountEl.dataset.quillBound === '1')
        {
            window.humaDestroyMessageTemplateHtmlQuillIn(root);
            mountEl = root.querySelector('#message-template-html-quill-editor');
            ta = root.querySelector('#message-template-html-body');
            if (! ta || ! mountEl)
            {
                return;
            }
        }
        mountEl.dataset.quillBound = '1';
        window.humaMessageTemplateQuillInstance = null;

        var readonly = ta.hasAttribute('readonly');
        var toolbar = readonly
            ? false
            : [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['link', 'image'],
                [{ color: [] }, { background: [] }],
                ['blockquote', 'code-block'],
                ['clean'],
            ];

        var quill = new Quill(mountEl, {
            theme: 'snow',
            modules: {
                toolbar: toolbar,
            },
            bounds: mountEl,
        });

        var jsonEl = root.querySelector('#message-template-html-initial-json');
        var initialHtml = '';
        if (jsonEl && jsonEl.textContent)
        {
            try
            {
                initialHtml = JSON.parse(jsonEl.textContent);
            }
            catch (e)
            {
                initialHtml = '';
            }
        }
        if (typeof initialHtml !== 'string')
        {
            initialHtml = '';
        }
        if (initialHtml.trim() === '' && (ta.value || '').trim() !== '')
        {
            initialHtml = ta.value;
        }

        initialHtml = initialHtml.trim();
        if (initialHtml !== '' && initialHtml !== '<p><br></p>' && initialHtml !== '<p></p>')
        {
            if (quill.clipboard && typeof quill.clipboard.dangerouslyPasteHTML === 'function')
            {
                quill.setContents([], 'silent');
                quill.clipboard.dangerouslyPasteHTML(0, initialHtml);
            }
            else
            {
                quill.root.innerHTML = initialHtml;
            }
        }

        if (readonly)
        {
            quill.enable(false);
        }
        else
        {
            humaBindMessageTemplateQuillImageUpload(quill, mountEl);
        }

        window.humaMessageTemplateQuillInstance = quill;

        quill.on('text-change', function ()
        {
            if (! readonly)
            {
                ta.value = quill.root.innerHTML;
            }
        });

        ta.value = quill.root.innerHTML;
    };

    document.addEventListener('DOMContentLoaded', function ()
    {
        if (window.humaInitMessageTemplateHtmlQuill)
        {
            window.humaInitMessageTemplateHtmlQuill(document);
        }

        var storeForm = document.getElementById('message-store-form');
        if (storeForm)
        {
            storeForm.addEventListener('submit', function ()
            {
                if (window.humaSyncMessageTemplateHtmlQuill)
                {
                    window.humaSyncMessageTemplateHtmlQuill();
                }
            });
        }
    });

    document.addEventListener('click', function (e)
    {
        var btn = e.target.closest('[data-huma-open-visual-editor]');
        if (! btn)
        {
            return;
        }
        var editorUrl = btn.getAttribute('data-editor-url') || '';
        if (! editorUrl || editorUrl === '#')
        {
            return;
        }
        e.preventDefault();
        var form = document.getElementById('message-open-visual-editor-form');
        var ta = document.getElementById('message-template-html-body');
        if (! form || ! ta)
        {
            window.location.href = editorUrl;

            return;
        }
        if (window.humaSyncMessageTemplateHtmlQuill)
        {
            window.humaSyncMessageTemplateHtmlQuill();
        }
        var tid = (btn.getAttribute('data-template-id') || '').trim();
        var mid = (btn.getAttribute('data-message-id') || '').trim();
        var templateIdInput = document.getElementById('message-open-visual-editor-template-id');
        var messageIdInput = document.getElementById('message-open-visual-editor-message-id');
        var returnInput = document.getElementById('message-open-visual-editor-return-url');
        var htmlInput = document.getElementById('message-open-visual-editor-template-html');
        if (! templateIdInput || ! messageIdInput || ! returnInput || ! htmlInput)
        {
            window.location.href = editorUrl;

            return;
        }
        templateIdInput.value = tid;
        if (mid)
        {
            messageIdInput.setAttribute('name', 'message_id');
            messageIdInput.value = mid;
        }
        else
        {
            messageIdInput.removeAttribute('name');
            messageIdInput.value = '';
        }
        var composerHtml = window.humaReadMessageTemplateHtmlFromComposer
            ? window.humaReadMessageTemplateHtmlFromComposer(btn.closest('.email-template-content-preview') || document.getElementById('message-email-template-preview-mount'))
            : (ta.value || '');
        htmlInput.value = composerHtml;
        try
        {
            var returnUrl = new URL(window.location.href.split('#')[0], window.location.origin);
            if (tid)
            {
                returnUrl.searchParams.set('template_id', tid);
            }
            returnInput.value = returnUrl.pathname + returnUrl.search;
        }
        catch (err1)
        {
            returnInput.value = window.location.href.split('#')[0];
        }
        form.submit();
    });

    document.addEventListener('click', function (e)
    {
        var btn = e.target.closest('[data-huma-update-template]');
        if (! btn)
        {
            return;
        }
        e.preventDefault();
        var form = document.getElementById('message-update-template-form');
        var ta = document.getElementById('message-template-html-body');
        if (! form || ! ta)
        {
            return;
        }

        function submitUpdateTemplate()
        {
            var previewRoot = btn.closest('.email-template-content-preview') || mount;
            var composerHtml = window.humaReadMessageTemplateHtmlFromComposer
                ? window.humaReadMessageTemplateHtmlFromComposer(previewRoot)
                : ((ta && ta.value) ? ta.value : '');

            var tid = (btn.getAttribute('data-template-id') || '').trim();
            if (! tid && window.jQuery)
            {
                var $tpl = window.jQuery('#template_id');
                if ($tpl.length && $tpl.val())
                {
                    tid = String($tpl.val()).trim();
                }
            }
            if (! tid)
            {
                var tplSelect = document.getElementById('template_id');
                if (tplSelect && tplSelect.value)
                {
                    tid = String(tplSelect.value).trim();
                }
            }
            var mid = (btn.getAttribute('data-message-id') || '').trim();
            if (! mid && messageFormMessageId)
            {
                mid = String(messageFormMessageId);
            }
            var templateIdInput = document.getElementById('message-update-template-template-id');
            var messageIdInput = document.getElementById('message-update-template-message-id');
            var htmlInput = document.getElementById('message-update-template-html');
            var returnInput = document.getElementById('message-update-template-return-url');
            if (! templateIdInput || ! messageIdInput || ! htmlInput || ! tid)
            {
                return;
            }
            templateIdInput.value = tid;
            if (mid)
            {
                messageIdInput.setAttribute('name', 'message_id');
                messageIdInput.value = mid;
            }
            else
            {
                messageIdInput.removeAttribute('name');
                messageIdInput.value = '';
            }
            htmlInput.value = composerHtml;
            if (! htmlInput.value || htmlInput.value.replace(/<[^>]*>/g, '').trim() === '')
            {
                if (typeof Swal !== 'undefined')
                {
                    Swal.fire({
                        icon: 'warning',
                        title: @json(__('app.email_template_update_empty')),
                        confirmButtonText: @json(__('OK')),
                    });
                }

                return;
            }
            if (returnInput)
            {
                returnInput.value = window.location.href.split('#')[0];
            }
            form.submit();
        }

        if (typeof Swal === 'undefined')
        {
            if (window.confirm(@json(__('app.email_template_update_confirm_text'))))
            {
                submitUpdateTemplate();
            }

            return;
        }

        Swal.fire({
            title: @json(__('app.email_template_update_confirm_title')),
            text: @json(__('app.email_template_update_confirm_text')),
            icon: 'warning',
            showCancelButton: true,
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-warning me-2',
                cancelButton: 'btn btn-label-secondary',
            },
            confirmButtonText: @json(__('app.email_template_update_button')),
            cancelButtonText: @json(__('Cancel')),
        }).then(function (result)
        {
            if (result.isConfirmed)
            {
                submitUpdateTemplate();
            }
        });
    });
})();
</script>
@endpush
@endsection
