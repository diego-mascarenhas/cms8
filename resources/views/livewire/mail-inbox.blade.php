<div class="col d-flex flex-grow-1 p-0" style="min-width: 0;">
    <!-- Emails List -->
    <div class="col app-emails-list">
        <div class="shadow-none border-0">
            <div class="emails-list-header p-3 py-lg-3 py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center w-100">
                        <i class="ti ti-menu-2 ti-sm cursor-pointer d-block d-lg-none me-3" data-bs-toggle="sidebar"
                            data-target="#app-email-sidebar" data-overlay></i>
                        <div class="mb-0 mb-lg-2 w-100">
                            <div class="input-group input-group-merge shadow-none">
                                <span class="input-group-text border-0 ps-0" id="email-search">
                                    <i class="ti ti-search"></i>
                                </span>
                                <input type="text" class="form-control email-search-input border-0"
                                    placeholder="Search mail" aria-label="Search mail" aria-describedby="email-search">
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-0 mb-md-2">
                        <a href="{{ route('mail-list') }}" class="text-body" title="{{ __('Sincronizar') }}" aria-label="{{ __('Sincronizar correo') }}">
                            <i class="ti ti-rotate-clockwise ti-sm rotate-180 scaleX-n1-rtl cursor-pointer email-refresh me-2"></i>
                        </a>
                        <div class="dropdown d-flex align-self-center">
                            <button class="btn p-0" type="button" id="emailsActions" data-bs-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <i class="ti ti-dots-vertical ti-sm"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="emailsActions">
                                <a class="dropdown-item" href="javascript:void(0)">Mark as read</a>
                                <a class="dropdown-item" href="javascript:void(0)">Mark as unread</a>
                                <a class="dropdown-item" href="javascript:void(0)">Delete</a>
                                <a class="dropdown-item" href="javascript:void(0)">Archive</a>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="mx-n3 emails-list-header-hr">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="form-check mb-0 me-2">
                            <input class="form-check-input" type="checkbox" id="email-select-all">
                            <label class="form-check-label" for="email-select-all"></label>
                        </div>
                        <i class="ti ti-trash ti-sm email-list-delete cursor-pointer me-2"></i>
                        <i class="ti ti-mail-opened ti-sm email-list-read cursor-pointer me-2"></i>
                        <div class="dropdown me-2">
                            <button class="btn p-0" type="button" id="dropdownMenuFolderOne"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="ti ti-folder ti-sm"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuFolderOne">
                                <a class="dropdown-item" href="javascript:void(0)">
                                    <i class="ti ti-info-circle ti-xs me-1"></i>
                                    <span class="align-middle">Spam</span>
                                </a>
                                <a class="dropdown-item" href="javascript:void(0)">
                                    <i class="ti ti-file ti-xs me-1"></i>
                                    <span class="align-middle">Draft</span>
                                </a>
                                <a class="dropdown-item" href="javascript:void(0)">
                                    <i class="ti ti-trash ti-xs me-1"></i>
                                    <span class="align-middle">Trash</span>
                                </a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn p-0" type="button" id="dropdownLabelOne" data-bs-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <i class="ti ti-tag ti-sm"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownLabelOne">
                                <a class="dropdown-item" href="javascript:void(0)">
                                    <i class="badge badge-dot bg-success me-1"></i>
                                    <span class="align-middle">Workshop</span>
                                </a>
                                <a class="dropdown-item" href="javascript:void(0)">
                                    <i class="badge badge-dot bg-primary me-1"></i>
                                    <span class="align-middle">Company</span>
                                </a>
                                <a class="dropdown-item" href="javascript:void(0)">
                                    <i class="badge badge-dot bg-info me-1"></i>
                                    <span class="align-middle">Important</span>
                                </a>
                                <a class="dropdown-item" href="javascript:void(0)">
                                    <i class="badge badge-dot bg-danger me-1"></i>
                                    <span class="align-middle">Private</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="email-pagination d-sm-flex d-none align-items-center flex-wrap justify-content-between justify-sm-content-end">
                        <span class="d-sm-block d-none mx-3 text-muted">1-10 of 653</span>
                        <i class="email-prev ti ti-chevron-left ti-sm scaleX-n1-rtl cursor-pointer text-muted me-2"></i>
                        <i class="email-next ti ti-chevron-right ti-sm scaleX-n1-rtl cursor-pointer"></i>
                    </div>
                </div>
            </div>
            <hr class="container-m-nx m-0">
            <div class="email-list pt-0">
                <ul class="list-unstyled m-0">
                    @forelse($emails as $index => $email)
                        @php
                            $fromDisplay = is_array($email['from'] ?? '') ? (array_values($email['from'])[0] ?? '') : ($email['from'] ?? __('Unknown'));
                            $fromName = $fromDisplay;
                            $words = array_filter(explode(' ', strip_tags((string) $fromName)));
                            $initials = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
                        @endphp
                        <li class="email-list-item email-marked-read" data-starred="false" data-bs-toggle="sidebar"
                            data-target="#app-email-view" role="button" tabindex="0"
                            wire:click="selectEmail({{ $index }})"
                            wire:key="email-{{ $index }}">
                            <div class="d-flex align-items-center">
                                <div class="form-check mb-0">
                                    <input class="email-list-item-input form-check-input" type="checkbox" id="email-{{ $index }}" wire:click.stop>
                                    <label class="form-check-label" for="email-{{ $index }}"></label>
                                </div>
                                <i class="email-list-item-bookmark ti ti-star ti-sm d-sm-inline-block d-none cursor-pointer ms-2 me-3"></i>
                                <div class="avatar avatar-sm d-block flex-shrink-0 me-sm-3 me-2">
                                    <span class="avatar-initial rounded-circle bg-label-primary">{{ $initials }}</span>
                                </div>
                                <div class="email-list-item-content ms-2 ms-sm-0 me-2">
                                    <span class="h6 email-list-item-username me-2">{{ $fromName }}</span>
                                    <span class="email-list-item-subject d-xl-inline-block d-block">
                                        {{ $email['subject'] ?? '' }}
                                    </span>
                                </div>
                                <div class="email-list-item-meta ms-auto d-flex align-items-center">
                                    @if(!empty($email['attachments'] ?? []))
                                        <span class="email-list-item-attachment ti ti-paperclip ti-xs cursor-pointer me-2"></span>
                                    @endif
                                    <small class="email-list-item-time text-muted text-truncate me-2" style="min-width: 70px; max-width: 100px;">
                                        @php
                                            try {
                                                $emailDate = \Carbon\Carbon::parse($email['date'] ?? 'now')->diffForHumans(\Carbon\Carbon::now(), true);
                                                echo $emailDate . ' ago';
                                            } catch (\Exception $e) {
                                                echo $email['date'] ?? '—';
                                            }
                                        @endphp
                                    </small>
                                    <ul class="list-inline email-list-item-actions text-nowrap mb-0">
                                        <li class="list-inline-item email-read"><i class="ti ti-mail-opened ti-sm"></i></li>
                                        <li class="list-inline-item email-delete"><i class="ti ti-trash ti-sm"></i></li>
                                        <li class="list-inline-item"><i class="ti ti-archive ti-sm"></i></li>
                                    </ul>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="email-list-empty text-center">{{ __('No hay correos disponibles.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="app-overlay"></div>
    </div>

    <!-- Email View -->
    <div class="col app-email-view flex-grow-0 bg-body" id="app-email-view">
        <div class="card shadow-none border-0 rounded-0 app-email-view-header p-3 py-md-3 py-2">
            <div class="d-flex justify-content-between align-items-center py-2">
                <div class="d-flex align-items-center overflow-hidden">
                    <i class="ti ti-chevron-left ti-sm cursor-pointer me-2" data-bs-toggle="sidebar"
                        data-target="#app-email-view"></i>
                    <h6 class="text-truncate mb-0 me-2" id="email-view-subject">
                        @if($this->selectedEmail)
                            {{ $this->selectedEmail['subject'] ?? __('Sin asunto') }}
                        @else
                            {{ __('Selecciona un mensaje') }}
                        @endif
                    </h6>
                </div>
                <div class="d-flex align-items-center">
                    <i class="ti ti-printer ti-sm mt-1 cursor-pointer d-sm-block d-none"></i>
                    <div class="dropdown ms-3">
                        <button class="btn p-0" type="button" id="dropdownMoreOptions"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="ti ti-dots-vertical ti-sm"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMoreOptions">
                            <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-mail ti-xs me-1"></i><span class="align-middle">Mark as read</span></a>
                            <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-mail-opened ti-xs me-1"></i><span class="align-middle">Mark as unread</span></a>
                            <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-star ti-sm me-1"></i><span class="align-middle">Add star</span></a>
                            <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-calendar ti-xs me-1"></i><span class="align-middle">Create Event</span></a>
                            <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-volume-off ti-xs me-1"></i><span class="align-middle">Mute</span></a>
                            <a class="dropdown-item d-sm-none d-block" href="javascript:void(0)"><i class="ti ti-printer ti-xs me-1"></i><span class="align-middle">Print</span></a>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="app-email-view-hr mx-n3 mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="ti ti-trash ti-sm cursor-pointer me-3" data-bs-toggle="sidebar" data-target="#app-email-view"></i>
                    <i class="ti ti-mail-opened ti-sm cursor-pointer me-3"></i>
                    <div class="dropdown me-3">
                        <button class="btn p-0" type="button" id="dropdownMenuFolderTwo" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="ti ti-folder ti-sm"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuFolderTwo">
                            <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-info-circle ti-xs me-1"></i><span class="align-middle">Spam</span></a>
                            <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-pencil ti-xs me-1"></i><span class="align-middle">Draft</span></a>
                            <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-trash ti-xs me-1"></i><span class="align-middle">Trash</span></a>
                        </div>
                    </div>
                    <div class="dropdown me-3">
                        <button class="btn p-0" type="button" id="dropdownLabelTwo" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="ti ti-tag ti-sm"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownLabelTwo">
                            <a class="dropdown-item" href="javascript:void(0)"><i class="badge badge-dot bg-success me-1"></i><span class="align-middle">Workshop</span></a>
                            <a class="dropdown-item" href="javascript:void(0)"><i class="badge badge-dot bg-primary me-1"></i><span class="align-middle">Company</span></a>
                            <a class="dropdown-item" href="javascript:void(0)"><i class="badge badge-dot bg-info me-1"></i><span class="align-middle">Important</span></a>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-wrap justify-content-end">
                    <span class="d-sm-block d-none mx-3 text-muted">1-10 of 653</span>
                    <i class="ti ti-chevron-left ti-sm scaleX-n1-rtl cursor-pointer text-muted me-2"></i>
                    <i class="ti ti-chevron-right ti-sm scaleX-n1-rtl cursor-pointer"></i>
                </div>
            </div>
        </div>
        <hr class="m-0">
        <div class="app-email-view-content py-4">
            @if(!$this->selectedEmail)
                <p class="text-center text-muted py-5">{{ __('Haz clic en un mensaje de la lista para ver su contenido.') }}</p>
            @else
                @php
                    $email = $this->selectedEmail;
                    $fromStr = is_array($email['from'] ?? '') ? (array_values($email['from'])[0] ?? '') : ($email['from'] ?? '');
                    $fromName = trim(preg_replace('/<[^>]+>/', '', $fromStr));
                    $fromEmail = (preg_match('/<([^>]+)>/', $fromStr, $m)) ? $m[1] : $fromStr;
                    $words = array_filter(explode(' ', $fromName));
                    $initials = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
                    $body = $email['body'] ?? '';
                    $dateFormatted = $email['date'] ?? '—';
                    try {
                                        $dateFormatted = \Carbon\Carbon::parse($email['date'] ?? 'now')->format('M j, Y, g:i A');
                    } catch (\Exception $e) {
                                        // keep as is
                    }
                @endphp
                <div class="card email-card-prev mx-sm-4 mx-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <div class="d-flex align-items-center mb-sm-0 mb-3">
                            <div class="avatar flex-shrink-0 me-3">
                                <span class="avatar-initial rounded-circle bg-label-primary">{{ $initials }}</span>
                            </div>
                            <div class="flex-grow-1 ms-1">
                                <h6 class="m-0">{{ $fromName ?: __('De') }}</h6>
                                <small class="text-muted">{{ $fromEmail }}</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <p class="mb-0 me-3 text-muted">{{ $dateFormatted }}</p>
                            <div class="dropdown me-3 d-flex align-self-center">
                                <button class="btn p-0" type="button" id="dropdownEmailOne"
                                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownEmailOne">
                                    <a class="dropdown-item scroll-to-reply" href="javascript:void(0)"><i class="ti ti-corner-up-left me-1"></i><span class="align-middle">{{ __('Reply') }}</span></a>
                                    <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-corner-up-right me-1"></i><span class="align-middle">{{ __('Forward') }}</span></a>
                                    <a class="dropdown-item" href="javascript:void(0)"><i class="ti ti-alert-octagon me-1"></i><span class="align-middle">{{ __('Report') }}</span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body email-view-body-content" style="min-height: 120px; white-space: pre-wrap; word-break: break-word;">
                        @if(strlen(trim($body)) > 0)
                            @if(str_contains($body, '<') && str_contains($body, '>'))
                                <div class="email-body-html">{!! $body !!}</div>
                            @else
                                <div class="email-body-text">{{ $body }}</div>
                            @endif
                        @else
                            <p class="text-muted mb-0">{{ __('(Este mensaje no tiene contenido de texto)') }}</p>
                        @endif
                    </div>
                </div>
            @endif
            <div class="email-reply card mt-4 mx-sm-4 mx-3">
                <h6 class="card-header border-0" id="email-reply-title">{{ __('Reply') }}</h6>
                <div class="card-body pt-0 px-3">
                    <div class="d-flex justify-content-start">
                        <div class="email-reply-toolbar border-0 w-100 ps-0">
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
                    <div class="email-reply-editor"></div>
                    <div class="d-flex justify-content-end align-items-center">
                        <div class="me-3">
                            <label class="cursor-pointer" for="attach-file-1"><i class="ti ti-paperclip me-2"></i><span class="align-middle">Attachments</span></label>
                            <input type="file" name="file-input" class="d-none" id="attach-file-1">
                        </div>
                        <button class="btn btn-primary">
                            <i class="ti ti-send ti-xs me-1"></i>
                            <span class="align-middle">Send</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
