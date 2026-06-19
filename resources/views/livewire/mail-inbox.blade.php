<div>
    @if ($statusMessage)
        @teleport('#mail-inbox-status')
            <div class="alert alert-{{ $statusType === 'success' ? 'success' : 'danger' }} alert-dismissible mb-3 py-2"
                role="alert"
                wire:key="mail-status-{{ md5($statusMessage) }}"
                x-data="{ show: true, hideTimer: null, removeTimer: null }"
                x-init="
                    hideTimer = setTimeout(() => {
                        show = false;
                        removeTimer = setTimeout(() => $wire.set('statusMessage', null), 300);
                    }, 3000);
                "
                x-show="show"
                x-transition:leave.opacity.duration.300ms>
                {{ $statusMessage }}
                <button type="button" class="btn-close"
                    @click="clearTimeout(hideTimer); clearTimeout(removeTimer); show = false; $wire.set('statusMessage', null)"
                    aria-label="{{ __('Close') }}"></button>
            </div>
        @endteleport
    @endif

    <div class="row g-0">
    <div class="col app-email-sidebar border-end flex-grow-0" id="app-email-sidebar">
        <div class="btn-compost-wrapper d-grid">
            <button class="btn btn-primary btn-compose d-inline-flex align-items-center justify-content-center"
                data-bs-toggle="modal" data-bs-target="#emailComposeSidebar"
                id="emailComposeSidebarLabel">
                <i class="ti ti-pencil ti-sm me-1"></i>{{ __('Compose mail') }}
            </button>
        </div>
        <div class="email-filters py-2">
            <ul class="email-filter-folders list-unstyled mb-4">
                @php
                    $folders = [
                        'inbox' => ['icon' => 'ti-mail', 'label' => __('Inbox'), 'badge' => $folderCounts['inbox_unread'] ?? 0, 'badge_class' => 'bg-label-primary'],
                        'sent' => ['icon' => 'ti-send', 'label' => __('Sent'), 'badge' => null],
                        'draft' => ['icon' => 'ti-file', 'label' => __('Draft'), 'badge' => $folderCounts['draft'] ?? 0],
                        'starred' => ['icon' => 'ti-star', 'label' => __('Starred'), 'badge' => $folderCounts['starred'] ?? 0, 'badge_class' => 'bg-label-warning'],
                        'archive' => ['icon' => 'ti-archive', 'label' => __('Archived'), 'badge' => $folderCounts['archive'] ?? 0],
                        'spam' => ['icon' => 'ti-shield-x', 'label' => __('Spam'), 'badge' => $folderCounts['spam'] ?? 0],
                        'trash' => ['icon' => 'ti-trash', 'label' => __('Trash'), 'badge' => $folderCounts['trash'] ?? 0],
                    ];
                @endphp
                @foreach ($folders as $key => $meta)
                    <li class="d-flex justify-content-between align-items-center gap-2 {{ $folder === $key ? 'active' : '' }}"
                        data-target="{{ $key }}" wire:key="folder-{{ $key }}">
                        <a href="javascript:void(0);" class="d-flex align-items-center flex-grow-1 min-w-0"
                            wire:click.prevent="setFolder('{{ $key }}')">
                            <i class="ti {{ $meta['icon'] }} ti-sm flex-shrink-0"></i>
                            <span class="align-middle ms-2 text-truncate">{{ $meta['label'] }}</span>
                        </a>
                        @if (! empty($meta['badge']) && (int) $meta['badge'] > 0)
                            <div class="badge {{ $meta['badge_class'] ?? 'bg-label-secondary' }} rounded-pill badge-center flex-shrink-0">{{ $meta['badge'] }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

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
                            <i class="ti ti-mail ti-sm email-list-unread cursor-pointer me-2" wire:click="markSelectedUnread" title="{{ __('Mark as unread') }}"></i>
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
                                    @if ($folder !== 'inbox')
                                        <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="moveSelectedToInbox">
                                            <i class="ti ti-mail ti-xs me-1"></i>
                                            <span class="align-middle">{{ __('Inbox') }}</span>
                                        </a>
                                    @endif
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
                            <i class="email-prev ti ti-chevron-left ti-sm scaleX-n1-rtl cursor-pointer text-muted me-2 {{ $senderGroupsPage->onFirstPage() ? 'opacity-25 pe-none' : '' }}"
                                wire:click="goToPreviousPage"></i>
                            <i class="email-next ti ti-chevron-right ti-sm scaleX-n1-rtl cursor-pointer {{ ! $senderGroupsPage->hasMorePages() ? 'opacity-25 pe-none' : '' }}"
                                wire:click="goToNextPage"></i>
                        </div>
                    </div>
                </div>
                <hr class="container-m-nx m-0">
                <div class="email-list pt-0" wire:loading.class="opacity-50" wire:target="refreshMailbox,search,setFolder">
                    <ul class="list-unstyled m-0">
                        @forelse ($senderGroupsPage as $group)
                            @php
                                $fromDisplay = $group['from'] ?? __('Unknown');
                                $words = array_filter(explode(' ', strip_tags((string) $fromDisplay)));
                                $initials = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
                                $hasUnread = (int) ($group['unread_count'] ?? 0) > 0;
                                $isStarred = (bool) ($group['has_starred'] ?? false);
                                $messageCount = (int) ($group['count'] ?? 1);
                                $isExpanded = $expandedSenderKey === ($group['sender_key'] ?? '');
                                $groupEmailIds = $group['email_ids'] ?? [];
                                $groupFullySelected = $groupEmailIds !== []
                                    && count(array_intersect($groupEmailIds, $selectedIds)) === count($groupEmailIds);
                            @endphp
                            <li class="email-list-item {{ $isExpanded ? 'border-start border-3 border-primary' : '' }}"
                                wire:key="sender-group-{{ $group['sender_key'] }}"
                                data-starred="{{ $isStarred ? 'true' : 'false' }}">
                                <div class="d-flex align-items-center"
                                    wire:click="selectEmail({{ $group['latest_email_id'] }})">
                                    <div class="form-check mb-0" wire:click.stop>
                                        <input class="email-list-item-input form-check-input" type="checkbox"
                                            @checked($groupFullySelected)
                                            wire:click="toggleGroupSelection(@js($groupEmailIds))"
                                            id="sender-{{ md5($group['sender_key']) }}">
                                        <label class="form-check-label" for="sender-{{ md5($group['sender_key']) }}"></label>
                                    </div>
                                    @if ($messageCount > 1)
                                        <button type="button"
                                            class="email-sender-expand btn btn-link text-secondary p-0 me-1 border-0 shadow-none"
                                            wire:click.stop="toggleSenderExpand('{{ $group['sender_key'] }}')"
                                            title="{{ __('Expand conversation') }}">
                                            <i class="ti {{ $isExpanded ? 'ti-chevron-down' : 'ti-chevron-right' }} ti-sm"></i>
                                        </button>
                                    @else
                                        <span class="d-inline-block me-1" style="width: 1.5rem;"></span>
                                    @endif
                                    <i class="email-list-item-bookmark ti ti-star ti-sm d-sm-inline-block d-none cursor-pointer ms-1 me-2 {{ $isStarred ? 'text-warning' : '' }}"
                                        wire:click.stop="toggleStarFor({{ $group['latest_email_id'] }})"></i>
                                    <div class="avatar avatar-sm d-block flex-shrink-0 me-sm-3 me-2">
                                        <span class="avatar-initial rounded-circle bg-label-primary">{{ $initials }}</span>
                                    </div>
                                    <div class="email-list-item-content ms-2 ms-sm-0 me-2">
                                        <span class="h6 email-list-item-username me-2 {{ $hasUnread ? 'fw-semibold' : '' }}">{{ $fromDisplay }}</span>
                                        @if ($messageCount > 1)
                                            <span class="badge rounded-pill {{ $hasUnread ? 'bg-label-primary' : 'bg-label-secondary' }} me-2">{{ $messageCount }}</span>
                                        @endif
                                        <span class="email-list-item-subject d-xl-inline-block d-block {{ $hasUnread ? 'fw-semibold' : '' }}">{{ $group['subject'] }}</span>
                                    </div>
                                    <div class="email-list-item-meta ms-auto d-flex align-items-center">
                                        <small class="email-list-item-time text-muted">
                                            {{ $group['date_list'] ?? '—' }}
                                        </small>
                                        <ul class="list-inline email-list-item-actions text-nowrap">
                                            @if ($hasUnread)
                                                <li class="list-inline-item email-read" wire:click.stop="markGroupRead(@js($groupEmailIds))" title="{{ __('Mark as read') }}">
                                                    <i class="ti ti-mail-opened ti-sm"></i>
                                                </li>
                                            @else
                                                <li class="list-inline-item email-unread" wire:click.stop="markGroupUnread(@js($groupEmailIds))" title="{{ __('Mark as unread') }}">
                                                    <i class="ti ti-mail ti-sm"></i>
                                                </li>
                                            @endif
                                            <li class="list-inline-item email-delete cursor-pointer" wire:click.stop="deleteSingle({{ $group['latest_email_id'] }})" title="{{ __('Delete') }}">
                                                <i class="ti ti-trash ti-sm"></i>
                                            </li>
                                            <li class="list-inline-item email-archive cursor-pointer" wire:click.stop="archiveSingle({{ $group['latest_email_id'] }})" title="{{ __('Archive') }}">
                                                <i class="ti ti-archive ti-sm"></i>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                @if ($isExpanded && $messageCount > 1)
                                    <ul class="list-unstyled mb-0 ps-5 border-top">
                                        @foreach ($group['emails'] as $threadEmail)
                                            @php
                                                $threadRead = (bool) ($threadEmail['seen'] ?? false);
                                            @endphp
                                            <li class="py-2 px-3 {{ $selectedEmailId === $threadEmail['id'] ? 'bg-label-primary' : '' }} {{ $threadRead ? 'text-muted' : '' }}"
                                                wire:key="thread-email-{{ $threadEmail['id'] }}"
                                                wire:click.stop="selectEmail({{ $threadEmail['id'] }})"
                                                style="cursor: pointer;">
                                                <div class="d-flex justify-content-between align-items-center gap-2">
                                                    <span class="text-truncate {{ $threadRead ? '' : 'fw-semibold' }}">{{ $threadEmail['subject'] }}</span>
                                                    <small class="text-muted flex-shrink-0">
                                                        {{ $threadEmail['date_short'] ?? '—' }}
                                                    </small>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @empty
                            <li class="email-list-empty text-center">{{ __('No emails available.') }}</li>
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
                            {{ $this->selectedEmail['subject'] ?? __('Select a message') }}
                        </h6>
                    </div>
                    <div class="d-flex align-items-center">
                        @if ($this->selectedEmail)
                            <button type="button" class="btn btn-sm btn-icon btn-text-secondary border-0 shadow-none"
                                wire:click="replyToSelected" title="{{ __('Reply') }}">
                                <i class="ti ti-corner-up-left ti-sm"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-icon btn-text-secondary border-0 shadow-none ms-1"
                                wire:click="forwardSelected" title="{{ __('Forward') }}">
                                <i class="ti ti-corner-up-right ti-sm"></i>
                            </button>
                            <div class="dropdown ms-2">
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
                            @if ($this->selectedEmail['seen'] ?? false)
                                <i class="ti ti-mail ti-sm cursor-pointer me-3" wire:click="markSingleUnread({{ $selectedEmailId }})" title="{{ __('Mark as unread') }}"></i>
                            @else
                                <i class="ti ti-mail-opened ti-sm cursor-pointer me-3" wire:click="markSingleRead({{ $selectedEmailId }})" title="{{ __('Mark as read') }}"></i>
                            @endif
                            <i class="ti ti-trash ti-sm cursor-pointer me-3" wire:click="deleteSingle({{ $selectedEmailId }})" title="{{ __('Delete') }}"></i>
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
                                @if ($folder !== 'inbox')
                                    <a class="dropdown-item" href="javascript:void(0)" wire:click.prevent="moveSelectedToInbox">
                                        <i class="ti ti-mail ti-xs me-1"></i><span class="align-middle">{{ __('Inbox') }}</span>
                                    </a>
                                @endif
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
                        <i class="ti ti-chevron-left ti-sm scaleX-n1-rtl cursor-pointer text-muted me-2 {{ $senderGroupsPage->onFirstPage() ? 'opacity-25 pe-none' : '' }}"
                            wire:click="goToPreviousPage"></i>
                        <i class="ti ti-chevron-right ti-sm scaleX-n1-rtl cursor-pointer {{ ! $senderGroupsPage->hasMorePages() ? 'opacity-25 pe-none' : '' }}"
                            wire:click="goToNextPage"></i>
                    </div>
                </div>
            </div>
            <hr class="m-0">
            <div class="app-email-view-content py-4" wire:key="email-view-content-{{ $selectedEmailId ?? 'none' }}">
                @if (! $this->selectedEmail)
                    <p class="text-center text-muted py-5">{{ __('Click a message in the list to view its content.') }}</p>
                @elseif (count($this->senderThread) > 1)
                    <div class="mx-sm-4 mx-3">
                        <p class="text-muted small mb-3">
                            {{ __('Conversation with :sender', ['sender' => $this->selectedEmail['from'] ?? '']) }}
                            <span class="badge bg-label-secondary ms-1">{{ count($this->senderThread) }}</span>
                        </p>
                        @foreach ($this->senderThread as $threadEmail)
                            @php
                                $fromStr = $threadEmail['from'] ?? '';
                                $fromName = trim(preg_replace('/<[^>]+>/', '', $fromStr));
                                $fromEmail = preg_match('/<([^>]+)>/', $fromStr, $m) ? $m[1] : $fromStr;
                                $body = $threadEmail['body'] ?? '';
                                $isActive = (int) ($threadEmail['id'] ?? 0) === (int) $selectedEmailId;
                            @endphp
                            <div class="card email-view-body-card mb-3 {{ $isActive ? 'border-primary' : '' }}"
                                wire:key="thread-view-{{ $threadEmail['id'] }}"
                                wire:click="selectEmail({{ $threadEmail['id'] }})"
                                style="cursor: pointer;">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap py-3">
                                    <div>
                                        <h6 class="m-0 {{ ($threadEmail['seen'] ?? false) ? 'text-muted' : '' }}">{{ $threadEmail['subject'] }}</h6>
                                        <small class="text-muted">{{ $fromName ?: $fromEmail }}</small>
                                    </div>
                                    <small class="text-muted">{{ $threadEmail['date_display'] ?? '—' }}</small>
                                </div>
                                @if ($isActive)
                                    <div class="card-body" style="min-height: 80px; white-space: pre-wrap; word-break: break-word;">
                                        @if (strlen(trim($body)) > 0)
                                            @if (str_contains($body, '<') && str_contains($body, '>'))
                                                <div>{!! $body !!}</div>
                                            @else
                                                {{ $body }}
                                            @endif
                                        @else
                                            <p class="text-muted mb-0">{{ __('(This message has no text content)') }}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    @php
                        $email = $this->selectedEmail;
                        $fromStr = $email['from'] ?? '';
                        $fromName = trim(preg_replace('/<[^>]+>/', '', $fromStr));
                        $fromEmail = preg_match('/<([^>]+)>/', $fromStr, $m) ? $m[1] : $fromStr;
                        $words = array_filter(explode(' ', $fromName));
                        $initials = strtoupper(substr($words[0] ?? 'U', 0, 1) . substr($words[1] ?? '', 0, 1));
                        $body = $email['body'] ?? '';
                    @endphp
                    <div class="card email-view-body-card mx-sm-4 mx-3">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                            <div class="d-flex align-items-center mb-sm-0 mb-3">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded-circle bg-label-primary">{{ $initials }}</span>
                                </div>
                                <div class="flex-grow-1 ms-1">
                                    <h6 class="m-0">{{ $fromName ?: __('From') }}</h6>
                                    <small class="text-muted">{{ $fromEmail }}</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <p class="mb-0 me-3 text-muted">{{ $email['date_display'] ?? '—' }}</p>
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
                                <p class="text-muted mb-0">{{ __('(This message has no text content)') }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
