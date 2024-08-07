@extends('layouts/layoutMaster')

@section('title', 'Helpdesk')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/app-chat.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/bootstrap-maxlength/bootstrap-maxlength.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('js/helpdesk-chat.js')}}"></script>
@endsection

@section('content')
<div class="app-chat card overflow-hidden">
  <div class="row g-0">
    <!-- Chat History -->
    <div class="col app-chat-history bg-body">
      <div class="chat-history-wrapper">
        <div class="chat-history-header border-bottom">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex overflow-hidden align-items-center">
              <div class="flex-shrink-0 avatar">
                <img src="{{asset('assets/img/avatars/guru-meditating.jpg')}}" alt="Avatar" class="rounded-circle" data-bs-toggle="sidebar" data-overlay data-target="#app-chat-sidebar-right">
              </div>
              <div class="chat-contact-info flex-grow-1 ms-2">
                <h6 class="m-0">Helpdesk</h6>
                <small class="user-status text-muted">ChatBot</small>
              </div>
            </div>
          </div>
        </div>
        <div class="chat-history-body bg-body">
          <ul class="list-unstyled chat-history">
            <div id="chat-messages" class="chat-container"></div>
          </ul>
        </div>
        <!-- Chat message form -->
        <div class="chat-history-footer shadow-sm">
          <form id="chat-form" method="POST" class="form-send-message d-flex justify-content-between align-items-center ">
            @csrf
            <input id="chat-input" class="form-control message-input border-0 me-3 shadow-none" placeholder="Type your message here">
            <div class="message-actions d-flex align-items-center">
              <i class="speech-to-text ti ti-microphone ti-sm cursor-pointer text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="In premium version"></i>
              <label for="attach-doc" class="form-label mb-0">
                <i class="ti ti-photo ti-sm cursor-pointer mx-3 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="In premium version"></i>
                <!-- <input type="file" id="attach-doc" hidden> -->
              </label>
              <button class="btn btn-primary d-flex send-msg-btn">
                <i class="ti ti-send me-md-1 me-0"></i>
                <span class="align-middle d-md-inline-block d-none">Send</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- /Chat History -->

    <div class="app-overlay"></div>
  </div>
</div>

<script src="{{ asset(mix('assets/vendor/libs/jquery/jquery.js')) }}"></script>
<script src="{{ asset('js/openai.js') }}"></script>

@include('layouts/sections/footer/footer')

@endsection