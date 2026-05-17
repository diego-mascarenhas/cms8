<div class="row g-0">
    @if ($statusMessage)
        <div class="col-12 px-3 pt-2" wire:key="mail-status-{{ md5($statusMessage) }}">
            <div class="alert alert-{{ $statusType === 'success' ? 'success' : 'danger' }} alert-dismissible mb-0 py-2" role="alert">
                {{ $statusMessage }}
                <button type="button" class="btn-close" wire:click="$set('statusMessage', null)"></button>
            </div>
        </div>
    @endif

    <div class="col app-email-sidebar border-end flex-grow-0" id="app-email-sidebar">
        <div class="btn-compost-wrapper d-grid">
            <button class="btn btn-primary btn-compose" data-bs-toggle="modal" data-bs-target="#emailComposeSidebar"
                id="emailComposeSidebarLabel">{{ __('Compose mail') }}</button>
        </div>
        <div class="email-filters py-2">
            <ul class="email-filter-folders list-unstyled mb-4">
                @php
                    $folders = [
                        'inbox' => ['icon' => 'ti-mail', 'label' => __('Inbox'), 'badge' => $folderCounts['inbox_unread'] ?? 0, 'badge_class' => 'bg-label-primary', 'li_class' => 'd-flex justify-content-between'],
                        'sent' => ['icon' => 'ti-send', 'label' => __('Sent'), 'badge' => null, 'li_class' => 'd-flex'],
                        'draft' => ['icon' => 'ti-file', 'label' => __('Draft'), 'badge' => $folderCounts['draft'] ?? 0, 'li_class' => 'd-flex'],
                        'starred' => ['icon' => 'ti-star', 'label' => __('Starred'), 'badge' => $folderCounts['starred'] ?? 0, 'badge_class' => 'bg-label-warning', 'li_class' => 'd-flex justify-content-between'],
                        'spam' => ['icon' => 'ti-shield-x', 'label' => __('Spam'), 'badge' => $folderCounts['spam'] ?? 0, 'li_class' => 'd-flex align-items-center'],
                        'trash' => ['icon' => 'ti-trash', 'label' => __('Trash'), 'badge' => $folderCounts['trash'] ?? 0, 'li_class' => 'd-flex align-items-center'],
                    ];
                @endphp
                @foreach ($folders as $key => $meta)
                    <li class="{{ $meta['li_class'] }} {{ $folder === $key ? 'active' : '' }}"
                        data-target="{{ $key }}" wire:key="folder-{{ $key }}">
                        <a href="javascript:void(0);" class="d-flex flex-wrap align-items-center"
                            wire:click.prevent="setFolder('{{ $key }}')">
                            <i class="ti {{ $meta['icon'] }} ti-sm"></i>
                            <span class="align-middle ms-2">{{ $meta['label'] }}</span>
                        </a>
                        @if (! empty($meta['badge']) && (int) $meta['badge'] > 0)
                            <div class="badge {{ $meta['badge_class'] ?? 'bg-label-secondary' }} rounded-pill badge-center">{{ $meta['badge'] }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if (! empty($sources) && count($sources) > 0)
                <div class="email-filter-labels">
                    <small class="fw-normal text-uppercase text-muted m-4">{{ __('Networks') }}</small>
                    <ul class="list-unstyled mb-0 mt-2">
                        @foreach ($sources as $source)
                            <li data-target="{{ Str::lower($source->name) }}" wire:key="source-{{ $source->id }}">
                                <a href="javascript:void(0);">
                                    <i class="{{ in_array($source->icon, ['fa-envelope', 'fa-phone']) ? 'fas' : 'fab' }} {{ $source->icon }}"
                                        style="color: {{ $source->color }};"></i>
                                    <span class="align-middle ms-2">{{ $source->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="col d-flex flex-grow-1 p-0" style="min-width: 0;">
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
                                        placeholder="{{ __('Search mail') }}" aria-label="{{ __('Search mail') }}"
                                        aria-describedby="email-search" wire:model.live.debounce.300ms="search">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-0 mb-md-2">
                            <span wire:loading wire:target="refreshMailbox" class="spinner-border spinner-border-sm text-primary email-refresh me-2" role="status"></span>
                            <a href="javascript:void(0);" class="text-body" wire:click.prevent="refreshMailbox" wire:loading.remove wire:target="refreshMailbox">
                                <i class="ti ti-rotate-clockwise ti-sm rotate-180 scaleX-n1-rtl cursor-pointer email-refresh me-2"></i>
                            </a>
                            <div class="dropdown d-flex align-self-center">
                                <button class="btn p-0" type="button" id="emailsActions" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-dots-vertical ti-sm"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="emailsActions">
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="markSelectedRead">{{ __('Mark as read') }}</a>
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="markSelectedUnread">{{ __('Mark as unread') }}</a>
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="deleteSelected">{{ __('Delete') }}</a>
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="archiveSelected">{{ __('Archive') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="mx-n3 emails-list-header-hr">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="form-check mb-0 me-2">
                                <input class="form-check-input" type="checkbox" id="email-select-all"
                                    wire:model.live="selectAllOnPage">
                                <label class="form-check-label" for="email-select-all"></label>
                            </div>
                            <i class="ti ti-trash ti-sm email-list-delete cursor-pointer me-2" wire:click="deleteSelected" title="{{ __('Delete') }}"></i>
                            <i class="ti ti-mail-opened ti-sm email-list-read cursor-pointer me-2" wire:click="markSelectedRead" title="{{ __('Mark as read') }}"></i>
                            @if ($folder === 'spam')
                                <i class="ti ti-inbox ti-sm cursor-pointer me-2" wire:click="moveSelectedFromSpam" title="{{ __('Not spam') }}"></i>
                            @else
                                <i class="ti ti-shield-x ti-sm cursor-pointer me-2" wire:click="moveSelectedToSpam" title="{{ __('Mark as spam') }}"></i>
                            @endif
                            <div class="dropdown me-2">
                                <button class="btn p-0" type="button" id="dropdownMenuFolderOne" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-folder ti-sm"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuFolderOne">
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="moveSelectedToSpam">
                                        <i class="ti ti-shield-x ti-xs me-1"></i>
                                        <span class="align-middle">{{ __('Spam') }}</span>
                                    </a>
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="moveSelectedToDraft">
                                        <i class="ti ti-file ti-xs me-1"></i>
                                        <span class="align-middle">{{ __('Draft') }}</span>
                                    </a>
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="deleteSelected">
                                        <i class="ti ti-trash ti-xs me-1"></i>
                                        <span class="align-middle">{{ __('Trash') }}</span>
                                    </a>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn p-0" type="button" id="dropdownLabelOne" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-tag ti-sm"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownLabelOne">
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="toggleStarSelected">
                                        <i class="badge badge-dot bg-warning me-1"></i>
                                        <span class="align-middle">{{ __('Starred') }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="email-pagination d-sm-flex d-none align-items-center flex-wrap justify-content-between justify-sm-content-end">
                            <span class="d-sm-block d-none mx-3 text-muted">{{ $paginationLabel }}</span>
                            <i class="email-prev ti ti-chevron-left ti-sm scaleX-n1-rtl cursor-pointer text-muted me-2 {{ $emailsPage->onFirstPage() ? 'opacity-25 pe-none' : '' }}"
                                wire:click="goToPreviousPage"></i>
                            <i class="email-next ti ti-chevron-right ti-sm scaleX-n1-rtl cursor-pointer {{ ! $emailsPage->hasMorePages() ? 'opacity-25 pe-none' : '' }}"
                                wire:click="goToNextPage"></i>
                        </div>
                    </div>
                </div>
                <hr class="container-m-nx m-0">
                <div class="email-list pt-0" wire:loading.class="opacity-50" wire:target="refreshMailbox,search,setFolder">
                    <ul class="list-unstyled m-0">
                        @forelse ($emailsPage as $email)
                            @php
                                $fromDisplay = $email['from'] ?? __('Unknown');
                                $words = array_filter(explode(' ', strip_tags((string) $fromDisplay)));
                                $initials = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
                                $isRead = (bool) ($email['seen'] ?? false);
                                $isStarred = (bool) ($email['flagged'] ?? false);
                            @endphp
                            <li class="email-list-item {{ $isRead ? 'email-marked-read' : '' }}"
                                wire:key="email-row-{{ $email['id'] }}"
                                data-starred="{{ $isStarred ? 'true' : 'false' }}"
                                wire:click="selectEmail({{ $email['id'] }})">
                                <div class="d-flex align-items-center">
                                    <div class="form-check mb-0" wire:click.stop>
                                        <input class="email-list-item-input form-check-input" type="checkbox"
                                            value="{{ $email['id'] }}" wire:model.live="selectedIds"
                                            id="email-{{ $email['id'] }}">
                                        <label class="form-check-label" for="email-{{ $email['id'] }}"></label>
                                    </div>
                                    <i class="email-list-item-bookmark ti ti-star ti-sm d-sm-inline-block d-none cursor-pointer ms-2 me-3 {{ $isStarred ? 'text-warning' : '' }}"
                                        wire:click.stop="toggleStarFor({{ $email['id'] }})"></i>
                                    <div class="avatar avatar-sm d-block flex-shrink-0 me-sm-3 me-2">
                                        <span class="avatar-initial rounded-circle bg-label-primary">{{ $initials }}</span>
                                    </div>
                                    <div class="email-list-item-content ms-2 ms-sm-0 me-2">
                                        <span class="h6 email-list-item-username me-2">{{ $fromDisplay }}</span>
                                        <span class="email-list-item-subject d-xl-inline-block d-block">{{ $email['subject'] }}</span>
                                    </div>
                                    <div class="email-list-item-meta ms-auto d-flex align-items-center">
                                        <small class="email-list-item-time text-muted">
                                            @php
                                                try {
                                                    echo \Carbon\Carbon::parse($email['date'] ?? 'now')->format('h:i A');
                                                } catch (\Exception $e) {
                                                    echo $email['date'] ?? '—';
                                                }
                                            @endphp
                                        </small>
                                        <ul class="list-inline email-list-item-actions text-nowrap">
                                            <li class="list-inline-item email-read" wire:click.stop="markSingleRead({{ $email['id'] }})">
                                                <i class="ti ti-mail-opened ti-sm"></i>
                                            </li>
                                            <li class="list-inline-item email-delete" wire:click.stop="deleteSingle({{ $email['id'] }})">
                                                <i class="ti ti-trash ti-sm"></i>
                                            </li>
                                            <li class="list-inline-item" wire:click.stop="archiveSingle({{ $email['id'] }})">
                                                <i class="ti ti-archive ti-sm"></i>
                                            </li>
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

        <div class="col app-email-view flex-grow-0 bg-body {{ $selectedEmailId ? 'show' : '' }}" id="app-email-view">
            <div class="card shadow-none border-0 rounded-0 app-email-view-header p-3 py-md-3 py-2">
                <div class="d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center overflow-hidden">
                        <i class="ti ti-chevron-left ti-sm cursor-pointer me-2" wire:click="closeEmailView"></i>
                        <h6 class="text-truncate mb-0 me-2" id="email-view-subject">
                            {{ $this->selectedEmail['subject'] ?? __('Selecciona un mensaje') }}
                        </h6>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="ti ti-printer ti-sm mt-1 cursor-pointer d-sm-block d-none"></i>
                        @if ($this->selectedEmail)
                            <div class="dropdown ms-3">
                                <button class="btn p-0" type="button" id="dropdownMoreOptions" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-dots-vertical ti-sm"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMoreOptions">
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="markSingleRead({{ $selectedEmailId }})">
                                        <i class="ti ti-mail ti-xs me-1"></i><span class="align-middle">{{ __('Mark as read') }}</span>
                                    </a>
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="markSingleUnread({{ $selectedEmailId }})">
                                        <i class="ti ti-mail-opened ti-xs me-1"></i><span class="align-middle">{{ __('Mark as unread') }}</span>
                                    </a>
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="toggleStarFor({{ $selectedEmailId }})">
                                        <i class="ti ti-star ti-sm me-1"></i><span class="align-middle">{{ __('Add star') }}</span>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <hr class="app-email-view-hr mx-n3 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        @if ($selectedEmailId)
                            <i class="ti ti-trash ti-sm cursor-pointer me-3" wire:click="deleteSingle({{ $selectedEmailId }})" title="{{ __('Delete') }}"></i>
                            <i class="ti ti-mail-opened ti-sm cursor-pointer me-3" wire:click="markSingleRead({{ $selectedEmailId }})" title="{{ __('Mark as read') }}"></i>
                            @if ($folder === 'spam')
                                <i class="ti ti-inbox ti-sm cursor-pointer me-3" wire:click="moveSingleFromSpam({{ $selectedEmailId }})" title="{{ __('Not spam') }}"></i>
                            @else
                                <i class="ti ti-shield-x ti-sm cursor-pointer me-3" wire:click="moveSingleToSpam({{ $selectedEmailId }})" title="{{ __('Mark as spam') }}"></i>
                            @endif
                        @endif
                        <div class="dropdown me-3">
                            <button class="btn p-0" type="button" id="dropdownMenuFolderTwo" data-bs-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <i class="ti ti-folder ti-sm"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuFolderTwo">
                                <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="moveSelectedToSpam">
                                    <i class="ti ti-shield-x ti-xs me-1"></i><span class="align-middle">{{ __('Spam') }}</span>
                                </a>
                                <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="moveSelectedToDraft">
                                    <i class="ti ti-pencil ti-xs me-1"></i><span class="align-middle">{{ __('Draft') }}</span>
                                </a>
                                <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="deleteSelected">
                                    <i class="ti ti-trash ti-xs me-1"></i><span class="align-middle">{{ __('Trash') }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap justify-content-end">
                        <span class="d-sm-block d-none mx-3 text-muted">{{ $paginationLabel }}</span>
                        <i class="ti ti-chevron-left ti-sm scaleX-n1-rtl cursor-pointer text-muted me-2 {{ $emailsPage->onFirstPage() ? 'opacity-25 pe-none' : '' }}"
                            wire:click="goToPreviousPage"></i>
                        <i class="ti ti-chevron-right ti-sm scaleX-n1-rtl cursor-pointer {{ ! $emailsPage->hasMorePages() ? 'opacity-25 pe-none' : '' }}"
                            wire:click="goToNextPage"></i>
                    </div>
                </div>
            </div>
            <hr class="m-0">
            <div class="app-email-view-content py-4" wire:key="email-view-content-{{ $selectedEmailId ?? 'none' }}">
                @if (! $this->selectedEmail)
                    <p class="text-center text-muted py-5">{{ __('Haz clic en un mensaje de la lista para ver su contenido.') }}</p>
                @else
                    @php
                        $email = $this->selectedEmail;
                        $fromStr = $email['from'] ?? '';
                        $fromName = trim(preg_replace('/<[^>]+>/', '', $fromStr));
                        $fromEmail = preg_match('/<([^>]+)>/', $fromStr, $m) ? $m[1] : $fromStr;
                        $words = array_filter(explode(' ', $fromName));
                        $initials = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
                        $body = $email['body'] ?? '';
                        try {
                            $dateFormatted = \Carbon\Carbon::parse($email['date'] ?? 'now')->format('M j, Y, g:i A');
                        } catch (\Exception $e) {
                            $dateFormatted = $email['date'] ?? '—';
                        }
                    @endphp
                    <div class="card email-view-body-card mx-sm-4 mx-3">
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
                            </div>
                        </div>
                        <div class="card-body" style="min-height: 120px; white-space: pre-wrap; word-break: break-word;">
                            @if (strlen(trim($body)) > 0)
                                @if (str_contains($body, '<') && str_contains($body, '>'))
                                    <div>{!! $body !!}</div>
                                @else
                                    {{ $body }}
                                @endif
                            @else
                                <p class="text-muted mb-0">{{ __('(Este mensaje no tiene contenido de texto)') }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
