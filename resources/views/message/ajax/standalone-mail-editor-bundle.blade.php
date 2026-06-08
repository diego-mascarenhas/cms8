@props([
    'previewHtml',
    'messageId' => null,
    'mailHtmlTextareaValue' => '',
])

@include('message.partials.email-template-content-preview-card', [
    'previewHtml' => $previewHtml,
    'grapesEditorUrl' => '#',
    'templateLabel' => null,
    'messageId' => $messageId,
    'templateId' => null,
    'previewFrameId' => null,
    'parentSyncsPreview' => true,
    'templateHashedId' => null,
    'duplicateFormId' => null,
    'duplicateModalId' => null,
    'removeTemplateUrl' => null,
    'emailTestSendModalInline' => true,
    'useMailHtmlTextarea' => true,
    'mailHtmlTextareaValue' => $mailHtmlTextareaValue,
    'mailHtmlTextareaReadonly' => false,
])
