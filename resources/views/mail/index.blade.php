@extends('layouts/layoutMaster')

@section('title', 'Chat')

@section('vendor-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('page-style')
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-email.css') }}" />
@endsection

@section('vendor-script')
    <script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
@endsection

@section('page-script')
    <script src="{{ asset('assets/js/app-email.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if (! empty($mailComposePrefill))
            window.setTimeout(function () {
                var $sel = window.jQuery && window.jQuery('#emailContacts');
                if ($sel && $sel.length) {
                    $sel.trigger('change');
                }
                var elOpen = document.getElementById('emailComposeSidebar');
                if (elOpen && window.bootstrap && window.bootstrap.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(elOpen).show();
                }
            }, 200);
            @endif

            var suggestUrl = @json(route('mail.compose-suggest'));
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var csrf = tokenEl ? tokenEl.getAttribute('content') : '';
            var contactIdEl = document.getElementById('mailComposeContactId');
            var contactId = (contactIdEl && contactIdEl.value) ? parseInt(contactIdEl.value, 10) : null;
            if (contactId !== null && Number.isNaN(contactId)) {
                contactId = null;
            }
            var flowSel = document.getElementById('mailComposeFlowRoutingKey');
            var hintEl = document.getElementById('mailComposeAiHint');
            var suggestBtn = document.getElementById('mailComposeSuggestBtn');
            var busy = false;

            function mailComposeRecipientSummary() {
                var $el = window.jQuery && window.jQuery('#emailContacts');
                if (!$el || !$el.length) {
                    return '';
                }
                var vals = $el.val();
                if (!vals) {
                    return '';
                }
                return Array.isArray(vals) ? vals.join(', ') : String(vals);
            }

            function setMailComposeBodyText(text) {
                var plain = text || '';
                var root = document.querySelector('.email-editor');
                if (root && window.Quill && typeof window.Quill.find === 'function') {
                    var q = window.Quill.find(root);
                    if (q) {
                        q.setText(plain);
                        return;
                    }
                }
                var ed = document.querySelector('.email-editor .ql-editor');
                if (ed) {
                    ed.innerText = plain;
                }
            }

            function runMailComposeSuggest(flowKey) {
                if (busy || !csrf) {
                    return;
                }
                busy = true;
                if (suggestBtn) {
                    suggestBtn.disabled = true;
                }
                var fk = flowKey && String(flowKey).trim() !== '' ? String(flowKey).trim() : null;
                var payload = {
                    flow_routing_key: fk,
                    hint: hintEl && hintEl.value ? hintEl.value.trim() : '',
                    recipient_summary: mailComposeRecipientSummary(),
                    contact_id: contactId
                };
                fetch(suggestUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                }).then(function (r) {
                    return r.json();
                }).then(function (data) {
                    if (data.success && data.response) {
                        setMailComposeBodyText(data.response);
                    } else if (data.message) {
                        alert(data.message);
                    }
                }).catch(function () {
                    alert(@json(__('Error de conexión')));
                }).finally(function () {
                    busy = false;
                    if (suggestBtn) {
                        suggestBtn.disabled = false;
                    }
                });
            }

            if (flowSel) {
                flowSel.addEventListener('change', function () {
                    var v = flowSel.value ? String(flowSel.value).trim() : '';
                    if (v !== '') {
                        runMailComposeSuggest(v);
                    }
                });
            }
            if (suggestBtn) {
                suggestBtn.addEventListener('click', function () {
                    var v = flowSel && flowSel.value ? String(flowSel.value).trim() : '';
                    runMailComposeSuggest(v);
                });
            }
        });
    </script>
@endsection

@section('content')
    @if (session('mail_error'))
        <div class="alert alert-danger alert-dismissible mb-3" role="alert">
            {{ session('mail_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('mail_success'))
        <div class="alert alert-success alert-dismissible mb-3" role="alert">
            {{ session('mail_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="app-email card">
        <div class="row g-0">
            <!-- Email Sidebar -->
            <div class="col app-email-sidebar border-end flex-grow-0" id="app-email-sidebar">
                <div class="btn-compost-wrapper d-grid">
                    <button class="btn btn-primary btn-compose" data-bs-toggle="modal" data-bs-target="#emailComposeSidebar"
                        id="emailComposeSidebarLabel">Compose</button>
                </div>
                <!-- Email Filters -->
                <div class="email-filters py-2">
                    <!-- Email Filters: Folder -->
                    <ul class="email-filter-folders list-unstyled mb-4">
                        <li class="active d-flex justify-content-between" data-target="inbox">
                            <a href="javascript:void(0);" class="d-flex flex-wrap align-items-center">
                                <i class="ti ti-mail ti-sm"></i>
                                <span class="align-middle ms-2">Inbox</span>
                            </a>
                            <div class="badge bg-label-primary rounded-pill badge-center">4</div>
                        </li>
                        <li class="d-flex" data-target="sent">
                            <a href="javascript:void(0);" class="d-flex flex-wrap align-items-center">
                                <i class="ti ti-send ti-sm"></i>
                                <span class="align-middle ms-2">Sent</span>
                            </a>
                        </li>
                        <li class="d-flex" data-target="draft">
                            <a href="javascript:void(0);" class="d-flex flex-wrap align-items-center">
                                <i class="ti ti-file ti-sm"></i>
                                <span class="align-middle ms-2">Draft</span>
                            </a>
                        </li>
                        <li class="d-flex justify-content-between" data-target="starred">
                            <a href="javascript:void(0);" class="d-flex flex-wrap align-items-center">
                                <i class="ti ti-star ti-sm"></i>
                                <span class="align-middle ms-2">Starred</span>
                            </a>
                            <div class="badge bg-label-warning rounded-pill badge-center">10</div>
                        </li>
                        <li class="d-flex align-items-center" data-target="spam">
                            <a href="javascript:void(0);" class="d-flex flex-wrap align-items-center">
                                <i class="ti ti-info-circle ti-sm"></i>
                                <span class="align-middle ms-2">Spam</span>
                            </a>
                        </li>
                        <li class="d-flex align-items-center" data-target="trash">
                            <a href="javascript:void(0);" class="d-flex flex-wrap align-items-center">
                                <i class="ti ti-trash ti-sm"></i>
                                <span class="align-middle ms-2">Trash</span>
                            </a>
                        </li>
                    </ul>
                    <!-- Email Filters: Sources -->
                    <div class="email-filter-labels">
                        <small class="fw-normal text-uppercase text-muted m-4">Redes</small>
                        <ul class="list-unstyled mb-0 mt-2">
                            @foreach($sources as $source)
                            <li data-target="{{ Str::lower($source->name) }}">
                                <a href="javascript:void(0);">
                                    <i class="{{ in_array($source->icon, ['fa-envelope', 'fa-phone']) ? 'fas' : 'fab' }} {{ $source->icon }}" 
                                       style="color: {{ $source->color }};">
                                    </i>
                                    <span class="align-middle ms-2">{{ $source->name }}</span>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <!--/ Email Filters -->
                </div>
            </div>
            <!--/ Email Sidebar -->

            @livewire('mail-inbox', ['emails' => $emails->values()->all()])
            <!-- Email View -->
        </div>

        <!-- Compose Email -->
        <div class="app-email-compose modal" id="emailComposeSidebar" tabindex="-1"
            aria-labelledby="emailComposeSidebarLabel" aria-hidden="true">
            <div class="modal-dialog m-0 me-md-4 mb-4 modal-lg">
                <div class="modal-content p-0">
                    <div class="modal-header py-3 bg-body">
                        <h5 class="modal-title fs-5">Compose Mail</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body flex-grow-1 pb-sm-0 p-4 py-2">
                        <form class="email-compose-form">
                            <input type="hidden" id="mailComposeContactId" value="{{ $mailComposeContactId ?? '' }}">
                            <div class="email-compose-to d-flex justify-content-between align-items-center">
                                <label class="form-label mb-0" for="emailContacts">To:</label>
                                <div class="select2-primary border-0 shadow-none flex-grow-1 mx-2">
                                    <select class="select2 select-email-contacts form-select" id="emailContacts"
                                        name="emailContacts" multiple>
                                        @if (! empty($mailComposePrefill) && ! empty($mailComposePrefill['email']))
                                            <option value="{{ e($mailComposePrefill['email']) }}" selected
                                                data-avatar="1.png">{{ e($mailComposePrefill['email']) }}</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="email-compose-toggle-wrapper">
                                    <a class="email-compose-toggle-cc" href="javascript:void(0);">Cc |</a>
                                    <a class="email-compose-toggle-bcc" href="javascript:void(0);">Bcc</a>
                                </div>
                            </div>

                            <div class="email-compose-cc d-none">
                                <hr class="container-m-nx my-2">
                                <div class="d-flex align-items-center">
                                    <label for="email-cc" class="form-label mb-0">Cc: </label>
                                    <input type="text" class="form-control border-0 shadow-none flex-grow-1 mx-2"
                                        id="email-cc" placeholder="someone@email.com">
                                </div>
                            </div>
                            <div class="email-compose-bcc d-none">
                                <hr class="container-m-nx my-2">
                                <div class="d-flex align-items-center">
                                    <label for="email-bcc" class="form-label mb-0">Bcc: </label>
                                    <input type="text" class="form-control border-0 shadow-none flex-grow-1 mx-2"
                                        id="email-bcc" placeholder="someone@email.com">
                                </div>
                            </div>
                            <hr class="container-m-nx my-2">
                            <div class="mb-3">
                                <label class="form-label" for="mailComposeFlowRoutingKey">{{ __('Assistant flow prompt') }}</label>
                                <div class="d-flex flex-wrap gap-2 align-items-start align-items-md-center">
                                    <select class="form-select flex-grow-1" id="mailComposeFlowRoutingKey"
                                        style="min-width: 220px;">
                                        <option value="">{{ __('Automatic (detect from message)') }}</option>
                                        @foreach (($assistantFlowPrompts ?? collect()) as $flowPrompt)
                                            <option value="{{ e($flowPrompt['routing_key']) }}">{{ e($flowPrompt['section_label']) }}
                                                ({{ e($flowPrompt['routing_key']) }})</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="mailComposeSuggestBtn">
                                        {{ __('Suggest message') }}</button>
                                </div>
                                <label class="form-label mt-2 mb-0" for="mailComposeAiHint">{{ __('Brief instructions (optional)') }}</label>
                                <textarea class="form-control form-control-sm mt-1" id="mailComposeAiHint" rows="2"
                                    placeholder=""></textarea>
                            </div>
                            <hr class="container-m-nx my-2">
                            <div class="email-compose-subject d-flex align-items-center mb-2">
                                <label for="email-subject" class="form-label mb-0">Subject:</label>
                                <input type="text" class="form-control border-0 shadow-none flex-grow-1 mx-2"
                                    id="email-subject" placeholder="Project Details">
                            </div>
                            <div class="email-compose-message container-m-nx">
                                <div class="d-flex justify-content-end">
                                    <div class="email-editor-toolbar border-bottom-0 w-100">
                                        <span class="ql-formats me-0">
                                            <button class="ql-bold"></button>
                                            <button class="ql-italic"></button>
                                            <button class="ql-underline"></button>
                                            <button class="ql-list" value="ordered"></button>
                                            <button class="ql-list" value="bullet"></button>
                                            <button class="ql-link"></button>
                                            <button class="ql-image"></button>
                                        </span>
                                    </div>
                                </div>
                                <div class="email-editor"></div>
                            </div>
                            <hr class="container-m-nx mt-0 mb-2">
                            <div class="email-compose-actions d-flex justify-content-between align-items-center mt-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="btn-group">
                                        <button type="reset" class="btn btn-primary" data-bs-dismiss="modal"
                                            aria-label="Close"><i class="ti ti-send ti-xs me-1"></i>Send</button>
                                        <button type="button"
                                            class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <span class="visually-hidden">Send Options</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="javascript:void(0);">Schedule send</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0);">Save draft</a></li>
                                        </ul>
                                    </div>
                                    <label for="attach-file"><i class="ti ti-paperclip cursor-pointer ms-2"></i></label>
                                    <input type="file" name="file-input" class="d-none" id="attach-file">
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="dropdown">
                                        <button class="btn p-0" type="button" id="dropdownMoreActions"
                                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="ti ti-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMoreActions">
                                            <li><button type="button" class="dropdown-item">Add Label</button></li>
                                            <li><button type="button" class="dropdown-item">Plain text mode</button></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><button type="button" class="dropdown-item">Print</button></li>
                                            <li><button type="button" class="dropdown-item">Check Spelling</button></li>
                                        </ul>
                                    </div>
                                    <button type="reset" class="btn" data-bs-dismiss="modal" aria-label="Close"><i
                                            class="ti ti-trash"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Compose Email -->
    </div>
@endsection
