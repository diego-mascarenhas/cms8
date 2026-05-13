@props([
    'previewHtml',
    'grapesEditorUrl',
    'templateLabel' => null,
    'messageId' => null,
    'templateId' => null,
    'previewFrameId' => null,
    'parentSyncsPreview' => false,
    'templateHashedId' => null,
    'duplicateFormId' => null,
    'duplicateModalId' => null,
    'removeTemplateUrl' => null,
    'useMailHtmlTextarea' => false,
    'mailHtmlTextareaValue' => '',
    'mailHtmlTextareaReadonly' => false,
])

@php
    $dupModalDomId = $duplicateModalId ?? 'email-template-duplicate-modal';
    $defaultDuplicateTemplateName = filled($templateLabel)
        ? \Illuminate\Support\Str::limit(rtrim($templateLabel).' ('.__('app.email_template_copy_suffix').')', 75, '')
        : __('app.email_template_duplicate_default_name');
@endphp

@include('message.partials.email-template-content-preview-card', [
    'previewHtml' => $previewHtml,
    'grapesEditorUrl' => $grapesEditorUrl,
    'templateLabel' => $templateLabel,
    'messageId' => $messageId,
    'templateId' => $templateId,
    'previewFrameId' => $previewFrameId,
    'parentSyncsPreview' => $parentSyncsPreview,
    'templateHashedId' => $templateHashedId,
    'duplicateFormId' => $duplicateFormId,
    'duplicateModalId' => $duplicateModalId,
    'removeTemplateUrl' => $removeTemplateUrl,
    'useMailHtmlTextarea' => $useMailHtmlTextarea,
    'mailHtmlTextareaValue' => $mailHtmlTextareaValue,
    'mailHtmlTextareaReadonly' => $mailHtmlTextareaReadonly,
])

@if (filled($templateHashedId) && filled($duplicateFormId))
    @push('modals')
        @include('message.partials.email-template-content-preview-duplicate-modal', [
            'dupModalDomId' => $dupModalDomId,
            'duplicateFormId' => $duplicateFormId,
            'defaultDuplicateTemplateName' => $defaultDuplicateTemplateName,
            'messageId' => $messageId,
        ])
    @endpush

    @if ($errors->has('duplicate_template_name') || $errors->has('message_id'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function ()
                {
                    var modalEl = document.getElementById(@json($dupModalDomId));
                    if (! modalEl || typeof bootstrap === 'undefined' || ! bootstrap.Modal)
                    {
                        return;
                    }
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });
            </script>
        @endpush
    @endif
@endif

@once('message-email-remove-template-swal')
    @push('scripts')
        <script>
            (function ()
            {
                document.addEventListener('click', function (e)
                {
                    var link = e.target.closest('a[data-message-remove-template-confirm]');
                    if (! link)
                    {
                        return;
                    }

                    e.preventDefault();
                    e.stopPropagation();

                    var url = link.getAttribute('href');
                    if (! url)
                    {
                        return;
                    }

                    function go()
                    {
                        window.location.href = url;
                    }

                    if (typeof Swal === 'undefined')
                    {
                        if (window.confirm(@json(__('app.message_remove_mail_template_swal_text'))))
                        {
                            go();
                        }

                        return;
                    }

                    Swal.fire({
                        title: @json(__('app.message_remove_mail_template_swal_title')),
                        text: @json(__('app.message_remove_mail_template_swal_text')),
                        icon: 'warning',
                        showCancelButton: true,
                        buttonsStyling: false,
                        customClass: {
                            confirmButton: 'btn btn-danger me-2',
                            cancelButton: 'btn btn-label-secondary',
                        },
                        confirmButtonText: @json(__('app.message_remove_mail_template_swal_confirm')),
                        cancelButtonText: @json(__('Cancel')),
                        allowOutsideClick: true,
                        allowEscapeKey: true,
                    }).then(function (result)
                    {
                        if (result.isConfirmed)
                        {
                            go();
                        }
                    });
                });
            })();
        </script>
    @endpush
@endonce
