@extends('layouts/layoutMaster')

@section('title', __('Messages'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
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
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Messages') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }} News</h4>
        <p class="text-muted small mb-0">{{ __('app.message_form_subtitle') }}</p>
    </div>
    @if(isset($data->id))
    <div class="d-flex align-content-center flex-wrap gap-3">
        <button type="button" class="btn btn-danger" onclick="deleteMessage({{ $data->id }})">
            <i class="ti ti-trash me-1"></i>{{ __('Delete') }}
        </button>
    </div>
    @endif
</div>

<form id="message-store-form" action="{{ route('message.store') }}" method="POST">
	@csrf
	<input type="hidden" name="id" value="{{ $data->id ?? '' }}">
	<input type="hidden" name="type_id" value="{{ old('type_id', $data->type_id ?? 1) }}">

	@php
		$removeMailTemplate = $removeMailTemplate ?? false;
		$templateSelectDisabled = (isset($data->hasDeliveries) && $data->hasDeliveries);
	@endphp

	<div id="message-form-template-id-slot"></div>

	<div class="card mb-4">
		<h5 class="card-header">{{ __('Messages') }}</h5>
		<div class="card-body">
		<div class="row g-3">
			<div class="col-12">
				<x-input-general id="name" label="{{ __('Subject') }} (*)" value="{{ old('name', $data->name?? '') }}" />
			</div>
			<div class="col-md-6">
				<x-module-categories-select
					id="category_id"
					label="Categoría"
					moduleKey="contacts"
					:selected="old('category_id', $data->category_id ?? '')"
					:allowEmpty="true"
					emptyText="Toda la base de datos"
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
			<div class="col-md-12">
				<x-input-textarea id="text" label="{{ __('Texto alternativo') }} (*)" value="{{ old('text', $data->text?? '') }}" />
				<div class="form-text mt-1">
					{{ __('app.message_form_alt_text_help') }}
				</div>
			</div>
			<div class="col-md-12" id="message-form-template-field-wrapper">
				<x-input-select
					id="template_id"
					label="{{ __('Plantilla') }}"
					:options="$data->templates ?? []"
					value="{{ old('template_id', $removeMailTemplate ? '' : ($data->template_id ?? '')) }}"
					:placeholder="__('app.message_form_template_none')"
					:disabled="$templateSelectDisabled"
				/>
			</div>
		</div>
		</div>
	</div>

	<div id="message-email-template-preview-mount">
	</div>

	@if (isset($data->id))
		<input type="hidden" name="status_id" value="{{ (((int) old('status_id', (int) ($data->status_id ?? 0))) === 1) ? 1 : 0 }}">
	@endif
	<input type="hidden" name="show_unsubscribe" value="{{ (int) (bool) old('show_unsubscribe', data_get($data, 'show_unsubscribe', 1)) }}">
	<input type="hidden" name="enable_open_tracking" value="{{ (int) (bool) old('enable_open_tracking', data_get($data, 'enable_open_tracking', 1)) }}">
	<input type="hidden" name="enable_click_tracking" value="{{ (int) (bool) old('enable_click_tracking', data_get($data, 'enable_click_tracking', 1)) }}">

	<div class="d-flex flex-wrap align-items-center gap-2 pt-2">
		<button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
		<button type="reset" class="btn btn-label-secondary" onclick="location.href='{{ route('message.index') }}'">{{ __('Cancel') }}</button>
	</div>
</form>

	<form
		id="message-email-template-duplicate-form"
		method="post"
		class="d-none"
		aria-hidden="true"
		action="#"
	>
		@csrf
		<input type="hidden" name="return_url" value="{{ isset($data->id) ? route('message.edit', $data->id) : request()->fullUrl() }}">
	</form>
@push('scripts')
<script>
(function ()
{
    var mount = document.getElementById('message-email-template-preview-mount');
    var previewUrl = @json(route('message.template-email-preview'));
    var messageFormMessageId = @json(isset($data->id) ? (int) $data->id : null);
    var templateLockDeliveries = @json((bool) (isset($data->hasDeliveries) && $data->hasDeliveries));

    if (! mount)
    {
        return;
    }

    var templateSlot = document.getElementById('message-form-template-id-slot');
    var duplicateForm = document.getElementById('message-email-template-duplicate-form');

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

    function loadEmailTemplatePreview(templateId)
    {
        if (mount.dataset.dynamicPreview === '1' && mount.dataset.loadedTemplateId === String(templateId))
        {
            return;
        }

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
                if (! data || typeof data.html !== 'string')
                {
                    throw new Error('bad payload');
                }
                removeStaleEmailTestSendModalsOutsidePreviewMount();
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

        function evaluate()
        {
            if (templateLockDeliveries)
            {
                return;
            }

            var tid = ($tpl.val() || '').toString().trim();
            if (! tid)
            {
                clearDynamicPreview();
                return;
            }
            loadEmailTemplatePreview(parseInt(tid, 10));
        }

        $tpl.on('change select2:select', evaluate);
        window.setTimeout(evaluate, 0);
    });
})();
</script>
<script>
(function ()
{
    window.humaMessageTemplateQuillInstance = null;

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
            return;
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
                ['link'],
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
})();
</script>
@endpush
@endsection
