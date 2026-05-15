@props([
    'messageId' => null,
    'templateId' => null,
    'inlineOnly' => false,
])

@php
    $useDraftTestSend = ! $messageId && $templateId;
    $testSendModalId = $useDraftTestSend
        ? 'email-test-send-modal-draft-'.$templateId
        : 'email-test-send-modal-'.$messageId;
    $testSendUrl = $useDraftTestSend
        ? route('message.test-from-template')
        : route('message.test', $messageId);
@endphp

@if ($inlineOnly)
    @include('message.partials.email-test-send-modal-inner')
@else
    @push('modals')
        @include('message.partials.email-test-send-modal-inner')
    @endpush
@endif
