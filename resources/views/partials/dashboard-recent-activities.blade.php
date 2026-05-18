@php
    $fillHeight = $fillHeight ?? false;
    $defaultPanel = 'contacts-trend';
@endphp
<div class="card {{ $fillHeight ? 'h-100 mb-0 d-flex flex-column' : '' }}" id="dashboardContactPanelCard">
    <div class="card-header d-flex align-items-center justify-content-between py-3 flex-wrap gap-2 flex-shrink-0">
        <div class="card-title mb-0">
            <h5 class="mb-0" id="dashboardContactPanelTitle">
                <i class="ti ti-target ti-xs me-1" id="dashboardContactPanelIcon"></i>
                <span id="dashboardContactPanelTitleText">{{ __('app.dashboard_panel_contacts_trend_title') }}</span>
            </h5>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                <small class="text-muted" id="dashboardContactPanelSubtitle">{{ __('app.dashboard_contacts_chart_subtitle_30') }}</small>
                <small id="dashboardContactPanelMonthChange" class="d-none" aria-live="polite"></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <div class="d-none align-items-center gap-2" id="dashboardLatestContactsPager" aria-hidden="true">
                <button type="button" class="btn btn-sm btn-label-secondary" id="dashboardLatestContactsPrev" disabled>
                    <i class="ti ti-chevron-left ti-xs me-1"></i>{{ __('app.dashboard_latest_contacts_prev') }}
                </button>
                <button type="button" class="btn btn-sm btn-label-secondary" id="dashboardLatestContactsNext" disabled>
                    {{ __('app.dashboard_latest_contacts_next') }}<i class="ti ti-chevron-right ti-xs ms-1"></i>
                </button>
            </div>
            @can('contact.list')
                <a href="{{ route('contact-list') }}" class="btn btn-sm btn-label-primary" id="dashboardContactPanelListLink">
                    <i class="ti ti-list ti-xs me-1"></i>{{ __('app.dashboard_contacts_view_list') }}
                </a>
            @endcan
        </div>
    </div>
    <div class="card-body pt-2 {{ $fillHeight ? 'flex-grow-1 min-h-0 d-flex flex-column' : '' }}">
        <div class="dashboard-contact-panel {{ $defaultPanel === 'contacts-trend' ? '' : 'd-none' }} {{ $fillHeight ? 'flex-grow-1 min-h-0' : '' }}" data-panel="contacts-trend">
            <div id="dashboardContactsTrendChart" style="min-height: 200px;"></div>
        </div>

        <div class="dashboard-contact-panel {{ $defaultPanel === 'status-breakdown' ? '' : 'd-none' }} {{ $fillHeight ? 'flex-grow-1 min-h-0' : '' }}" data-panel="status-breakdown">
            <div id="dashboardContactStatusChart" style="min-height: 220px;"></div>
        </div>

        <div class="dashboard-contact-panel dashboard-latest-contacts-panel {{ $defaultPanel === 'latest-contacts' ? '' : 'd-none' }} {{ $fillHeight ? 'flex-grow-1 min-h-0' : '' }}" data-panel="latest-contacts">
            <div class="table-responsive flex-grow-1 min-h-0">
                <table id="dashboardLatestContactsTable" class="table table-hover table-sm dashboard-latest-contacts-table w-100">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($latestRegisteredContacts as $contact)
                            <tr>
                                <td class="text-truncate" style="max-width: 220px;">
                                    @can('contact.show')
                                        <a href="{{ route('contact.show', $contact->id) }}" class="text-body text-decoration-none fw-normal">
                                            {{ trim($contact->name.' '.$contact->surname) }}
                                        </a>
                                    @else
                                        <span class="text-body fw-normal">{{ trim($contact->name.' '.$contact->surname) }}</span>
                                    @endcan
                                </td>
                                <td>
                                    @if ($contact->status)
                                        <span class="badge rounded-pill {{ $contact->status->label_class }}">
                                            {{ $contact->status->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap small text-muted" data-order="{{ $contact->created_at->timestamp }}">
                                    {{ $contact->created_at->isoFormat('D MMM YYYY, HH:mm') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
