@extends('layouts/layoutMaster')

@section('title', $survey->title)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Encuestas') }}/</span> {{ $survey->title }}</h4>
        <p class="text-muted">
            {{ $survey->code }}
            @if ($survey->expires_at)
                · {{ __('Vence') }} {{ $survey->expires_at->format('d/m/Y') }}
            @endif
            · {{ $survey->responses->count() }} {{ __('respuestas') }}
        </p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3">
        @if ($survey->certificate_url)
        <a href="{{ route('survey.certificate', $survey) }}" class="btn btn-label-primary"><i class="ti ti-certificate me-1"></i>{{ __('Certificado') }}</a>
        @endif
        <a href="{{ route('survey.export', $survey) }}" class="btn btn-label-primary"><i class="ti ti-download me-1"></i>{{ __('Exportar CSV') }}</a>
        @can('update', $survey)
        <a href="{{ route('survey.edit', $survey) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>{{ __('Editar') }}</a>
        @endcan
    </div>
</div>

@php
    $certified = $survey->responses->where('certified', true);
@endphp
@if ($survey->certificate_url && $certified->isNotEmpty())
<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">{{ __('Certificados') }} ({{ $certified->count() }})</h5>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Nombre') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($certified as $response)
                    <tr>
                        <td>{{ $response->user->name }}</td>
                        <td>{{ $response->user->email }}</td>
                        <td class="text-end">
                            <a href="{{ route('survey.certificate.admin', [$survey, $response]) }}">{{ __('Descargar') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@foreach ($results as $field)
<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-1">{{ $field['label'] }}</h5>
        <p class="text-muted mb-3">
            {{ $field['total'] }} {{ __('respuestas') }}
            @if ($field['anonymous'])
                · {{ __('Anónima') }}
            @endif
            @if ($field['for_certificate'])
                · {{ __('Certificación') }}
            @endif
        </p>
        @foreach ($field['options'] as $option)
        <div class="d-flex justify-content-between">
            <span>{{ $option['label'] }}</span>
            <span>{{ $option['count'] }} ({{ $option['percent'] }}%)</span>
        </div>
        <div class="progress mb-2" style="height: 8px;">
            <div class="progress-bar" role="progressbar" style="width: {{ $option['percent'] }}%"></div>
        </div>
        @if (! $field['anonymous'] && count($option['voters']))
        <p class="text-muted small mb-3">{{ implode(', ', $option['voters']) }}</p>
        @endif
        @endforeach
    </div>
</div>
@endforeach
@endsection
