@extends('layouts/layoutMaster')

@section('title', $account->name)

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Cuentas de pago') }}/</span> {{ $account->name }}</h4>
        <p class="text-muted">{{ __('Movimientos de la cuenta') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @can('update', $account)
            <button type="button" class="btn btn-success waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#uploadStatementModal">
                <i class="ti ti-upload me-1"></i>{{ __('Subir extracto') }}
            </button>
            <a href="{{ route('payment-account.edit', $account) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-edit me-1"></i>{{ __('Edit') }}
            </a>
        @endcan
        <a href="{{ route('payment-account.index') }}" class="btn btn-label-secondary waves-effect waves-light">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="mb-2 {{ $balance >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($balance, 2) }}
                </h4>
                <p class="mb-0 fw-medium">{{ $account->name }}</p>
                <small class="text-muted">{{ $account->code }}</small>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @forelse ($statements as $statement)
                    @if ($statement->isDownloadable())
                        <a
                            href="{{ route('payment-account.statements.download', [$account, $statement]) }}"
                            class="btn btn-sm btn-label-secondary d-inline-flex align-items-center gap-1"
                            title="{{ $statement->original_filename }} · {{ $statement->periodLabel() }}"
                        >
                            <i class="{{ $statement->fileIcon() }}"></i>
                            <span>{{ $statement->periodLabel() }}</span>
                        </a>
                    @else
                        <span class="badge bg-label-secondary" title="{{ $statement->original_filename }}">
                            {{ $statement->periodLabel() }}
                        </span>
                    @endif
                @empty
                    <span class="text-muted small">{{ __('Sin extractos subidos') }}</span>
                @endforelse
                <span class="avatar ms-md-2">
                    <span class="avatar-initial bg-label-secondary rounded">
                        {{ strtoupper((string) ($account->currency?->code ?? '')) }}
                    </span>
                </span>
            </div>
        </div>
    </div>
</div>

@if ($statements->isNotEmpty())
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ __('Validación de extractos') }}</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Periodo') }}</th>
                        <th>{{ __('Archivo') }}</th>
                        <th class="text-center">{{ __('Líneas') }}</th>
                        <th class="text-center">{{ __('Coinciden') }}</th>
                        <th class="text-center">{{ __('Solo extracto') }}</th>
                        <th class="text-center">{{ __('Solo pagos') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statements as $statement)
                        @php($summary = $statement->validation_summary ?? [])
                        <tr>
                            <td>{{ $statement->periodLabel() }}</td>
                            <td>
                                @if ($statement->isDownloadable())
                                    <a href="{{ route('payment-account.statements.download', [$account, $statement]) }}" class="text-body">
                                        {{ $statement->original_filename }}
                                    </a>
                                @else
                                    {{ $statement->original_filename ?? '—' }}
                                @endif
                            </td>
                            <td class="text-center">{{ $summary['line_count'] ?? $statement->lines_count }}</td>
                            <td class="text-center text-success">{{ $summary['matched'] ?? '—' }}</td>
                            <td class="text-center text-warning">{{ $summary['statement_only'] ?? '—' }}</td>
                            <td class="text-center text-danger">{{ $summary['payment_only'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ __('Movimientos') }}</h5>
    </div>
    <div class="card-body">
        {{ $dataTable->table(['class' => 'table table-hover dt-responsive nowrap w-100']) }}
    </div>
</div>

@can('update', $account)
<div class="modal fade" id="uploadStatementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('payment-account.statements.store', $account) }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Subir extractos bancarios') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    {{ __('Podés subir varios CSV o PDF. El mes se detecta del nombre o del contenido; también podés forzar el periodo.') }}
                </p>
                <div class="mb-3">
                    <label for="statement-files" class="form-label">{{ __('Archivos') }} <span class="text-danger">*</span></label>
                    <input type="file" name="files[]" id="statement-files" class="form-control" accept=".csv,.txt,.pdf" multiple required>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="period_year" class="form-label">{{ __('Año') }}</label>
                        <input type="number" name="period_year" id="period_year" class="form-control" min="2000" max="2100" value="{{ old('period_year') }}" placeholder="{{ now()->year }}">
                    </div>
                    <div class="col-md-6">
                        <label for="period_month" class="form-label">{{ __('Mes') }}</label>
                        <select name="period_month" id="period_month" class="form-select">
                            <option value="">{{ __('Automático') }}</option>
                            @for ($month = 1; $month <= 12; $month++)
                                <option value="{{ $month }}" @selected((string) old('period_month') === (string) $month)>
                                    {{ sprintf('%02d', $month) }}
                                </option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-upload me-1"></i>{{ __('Subir') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
