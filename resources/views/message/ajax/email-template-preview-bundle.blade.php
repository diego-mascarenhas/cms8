@props([
    'previewHtml',
    'grapesEditorUrl',
    'templateLabel' => null,
    'messageId' => null,
    'templateId' => null,
    'templateHashedId' => null,
    'duplicateFormId' => 'message-email-template-duplicate-form',
    'duplicateModalId' => 'message-email-template-duplicate-modal',
    'removeTemplateUrl' => null,
    'useMailHtmlTextarea' => false,
    'mailHtmlTextareaValue' => '',
    'mailHtmlTextareaReadonly' => false,
])

@php
    $dupModalDomId = $duplicateModalId ?? 'message-email-template-duplicate-modal';
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
    'previewFrameId' => null,
    'parentSyncsPreview' => true,
    'templateHashedId' => $templateHashedId,
    'duplicateFormId' => $duplicateFormId,
    'duplicateModalId' => $duplicateModalId,
    'removeTemplateUrl' => $removeTemplateUrl,
    'emailTestSendModalInline' => true,
    'useMailHtmlTextarea' => $useMailHtmlTextarea,
    'mailHtmlTextareaValue' => $mailHtmlTextareaValue,
    'mailHtmlTextareaReadonly' => $mailHtmlTextareaReadonly,
])

@if (filled($templateHashedId) && filled($duplicateFormId))
    @include('message.partials.email-template-content-preview-duplicate-modal', [
        'dupModalDomId' => $dupModalDomId,
        'duplicateFormId' => $duplicateFormId,
        'defaultDuplicateTemplateName' => $defaultDuplicateTemplateName,
        'messageId' => $messageId,
    ])
@endif
