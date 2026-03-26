@extends('layouts/layoutMaster')

@section('title', __('Team files'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.team-file-restore-version-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById(btn.getAttribute('data-form-id'));
                if (!form) {
                    return;
                }
                Swal.fire({
                    title: @json(__('Restore previous version?')),
                    text: @json(__('The current file will be archived and replaced by the selected version.')),
                    icon: 'question',
                    showCancelButton: true,
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary me-2',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    confirmButtonText: @json(__('Restore')),
                    cancelButtonText: @json(__('Cancel')),
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endsection

@section('content')
@php
    $historyActionLabels = [
        'uploaded' => __('Uploaded'),
        'replaced' => __('Replaced'),
        'updated' => __('Updated'),
        'deleted' => __('Deleted'),
        'restored' => __('Restored'),
    ];
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Team files') }}/</span> {{ $data->title }}</h4>
        <p class="text-muted">{{ __('Version history and actions for this file.') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $data)
            <a href="{{ route('team-file.edit', $data) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>{{ __('Edit') }}
            </a>
        @endcan
        @can('view', $data)
            @if($data->getFirstMedia('file'))
                <a href="{{ route('team-file.download', $data) }}" class="btn btn-info waves-effect waves-light">
                    <i class="ti ti-download me-1"></i>{{ __('Download') }}
                </a>
            @endif
        @endcan
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('File details') }}</h5>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">{{ __('Title') }}</div>
                <div>{{ $data->title }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">{{ __('Visibility') }}</div>
                <div>{{ $data->visibility?->label() ?? '—' }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">{{ __('Category') }}</div>
                <div>{{ $data->category?->name ?? '—' }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">{{ __('Current file') }}</div>
                <div>{{ $data->getFirstMedia('file')?->file_name ?? '—' }}</div>
            </div>
            <div class="col-md-12">
                <div class="text-muted small">{{ __('Description') }}</div>
                <div>{{ $data->description ?: '—' }}</div>
            </div>
            @if($data->visibility?->value === \App\Enums\MultimediaVisibility::PUBLIC->value && !empty($publicShareUrl))
            <div class="col-md-12">
                <div class="text-muted small">{{ __('Share link') }}</div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="{{ $publicShareUrl }}" target="_blank" rel="noopener noreferrer">{{ $publicShareUrl }}</a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('History') }}</h5>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('File') }}</th>
                        <th>{{ __('User') }}</th>
                        <th class="text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($histories as $history)
                        <tr>
                            <td>{{ optional($history->created_at)->format('d-m-Y H:i') }}</td>
                            <td>{{ $historyActionLabels[$history->action] ?? ucfirst($history->action) }}</td>
                            <td>{{ $history->file_name ?: '—' }}</td>
                            <td>{{ $history->user?->name ?? '—' }}</td>
                            <td class="text-center">
                                @if($history->archived_media_id)
                                    @can('update', $data)
                                        <form id="team-file-restore-history-{{ $history->id }}" method="POST" action="{{ route('team-file.restore-version', [$data, $history]) }}" class="d-inline">
                                            @csrf
                                            <button type="button" class="btn btn-sm btn-label-primary team-file-restore-version-btn" data-form-id="team-file-restore-history-{{ $history->id }}">
                                                <i class="ti ti-history-toggle me-1"></i>{{ __('Restore') }}
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">{{ __('No records found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
