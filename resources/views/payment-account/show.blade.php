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
        @endcan
        @if ($statements->isNotEmpty())
            <a href="{{ route('payment-account.statements.reconcile', [$account, $statements->first()]) }}" class="btn btn-primary waves-effect waves-light">
                <i class="ti ti-checkbox me-1"></i>{{ __('Conciliar') }}
            </a>
        @endif
        @can('update', $account)
            <a href="{{ route('payment-account.edit', $account) }}" class="btn btn-label-primary waves-effect waves-light">
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
<div class="card mb-4" id="statement-files">
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
                        <th class="text-center" title="{{ __('Líneas del extracto') }}">{{ __('Líneas') }}</th>
                        <th class="text-center" title="{{ __('Coinciden con un pago') }}">{{ __('Pares') }}</th>
                        <th class="text-center" title="{{ __('Están en el extracto y no en los pagos') }}">{{ __('Banco') }}</th>
                        <th class="text-center" title="{{ __('Están en los pagos y no en el extracto') }}">{{ __('Pagos') }}</th>
                        <th class="text-end" title="{{ __('Suma de los pagos del período') }}">{{ __('Libro') }}</th>
                        <th class="text-end" title="{{ __('Importe del extracto') }}">{{ __('Saldo') }}</th>
                        <th class="text-end">{{ __('Diferencia') }}</th>
                        <th class="text-end">{{ __('Acciones') }}</th>
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
                            <td class="text-end">{{ isset($summary['book_total']) ? number_format((float) $summary['book_total'], 2, ',', '.') : '—' }}</td>
                            <td class="text-end">{{ isset($summary['statement_total']) && $summary['statement_total'] !== null ? number_format((float) $summary['statement_total'], 2, ',', '.') : '—' }}</td>
                            <td class="text-end {{ ($summary['balanced'] ?? false) ? 'text-success' : 'text-danger' }}">
                                @if (! array_key_exists('difference', $summary) || $summary['difference'] === null)
                                    —
                                @elseif ($summary['balanced'] ?? false)
                                    {{ __('Empatado') }}
                                @else
                                    {{ number_format((float) $summary['difference'], 2, ',', '.') }}
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    <a href="{{ route('payment-account.statements.reconcile', [$account, $statement]) }}" class="btn btn-sm btn-label-primary">
                                        {{ __('Conciliar') }}
                                    </a>
                                    @if ($statement->isDownloadable())
                                        <a href="{{ route('payment-account.statements.download', [$account, $statement]) }}" class="btn btn-sm btn-label-secondary">
                                            {{ __('Descargar') }}
                                        </a>
                                    @endif
                                    @can('update', $account)
                                        <form method="POST" action="{{ route('payment-account.statements.destroy', [$account, $statement]) }}" onsubmit="return confirm(@json(__('¿Eliminar este extracto y sus líneas?')));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-label-danger">{{ __('Eliminar') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
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
    @include('payment-account.partials.upload-statement-modal', [
        'account' => $account,
        'defaultYear' => now()->year,
        'defaultPeriod' => 'm:'.now()->month,
    ])
@endcan
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
