@extends('layouts/layoutMaster')

@section('title', 'AI')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/openaai.css') }}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Helpdesk</h4>
        <p class="text-muted">Manage your messages with ease and keep your audience engaged!</p>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">Messages</h5>
    <div id="chat-container">
        <div id="chat-messages" class="chat-container"></div>
        <form id="chat-form" method="POST" class="form-send-message d-flex justify-content-between align-items-center p-3">
            @csrf
            <input class="form-control message-input border-0 me-3 shadow-none" id="chat-input" placeholder="No tengo tiempo para tus tonterías, ¿qué quieres?" />
            <div class="message-actions d-flex align-items-center">
                <i class="speech-to-text ti ti-microphone ti-sm cursor-pointer"></i>
                <label for="attach-doc" class="form-label mb-0">
                    <i class="ti ti-photo ti-sm cursor-pointer mx-3"></i>
                    <input type="file" id="attach-doc" hidden="">
                </label>
                <button class="btn btn-primary d-flex send-msg-btn waves-effect waves-light" type="submit">
                    <i class="ti ti-send me-md-1 me-0"></i>
                    <span class="align-middle d-md-inline-block d-none">Send</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset(mix('assets/vendor/libs/jquery/jquery.js')) }}"></script>
<script src="{{ asset('js/openai.js') }}"></script>

@endsection