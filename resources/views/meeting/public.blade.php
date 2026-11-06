@extends('layouts/blankLayout')

@section('title', $meeting->title)

@section('content')
<div class="container py-4">
    <h3 class="mb-1">{{ $meeting->title }}</h3>
    <p class="text-muted mb-4">Lo conversado en esta reunión.</p>

    @php
        $summary = is_array($meeting->summary) ? $meeting->summary : null;
    @endphp
    @if ($summary)
        <div class="card mb-4">
            <h5 class="card-header">Resumen</h5>
            <div class="card-body">
                @include('meeting.partials.summary', ['summary' => $summary])
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body d-flex flex-column gap-4">
            @forelse ($meeting->turns ?? [] as $turn)
                @php
                    $contact = isset($turn['contact_id']) ? ($contacts[$turn['contact_id']] ?? null) : null;
                    $speakerName = $contact
                        ? trim($contact->name.' '.($contact->surname ?? ''))
                        : ($turn['label'] ?? 'Persona');
                    $avatar = $contact
                        ? $contact->avatarUrl(40)
                        : \App\Helpers\AvatarHelper::generate($speakerName, 40);
                @endphp
                <div class="d-flex gap-3">
                    <span class="position-relative d-inline-flex flex-shrink-0">
                        <img src="{{ $avatar }}" alt="" class="rounded-circle" width="40" height="40" style="object-fit:cover">
                    </span>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <strong>{{ $speakerName }}</strong>
                            <span class="text-muted">{{ gmdate('i:s', (int) ($turn['start_seconds'] ?? 0)) }}</span>
                        </div>
                        <p class="mb-0 mt-2">{{ $turn['text'] ?? '' }}</p>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">Todavía no hay una conversación para mostrar.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
