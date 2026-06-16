@php
    $marginClass = $margin ?? 'me-3';
    $photoUrl = $avatar['photo_url'] ?? null;
    $initials = $avatar['initials'] ?? '?';
    $icon = $avatar['icon'] ?? null;
    $labelClass = $avatar['label_class'] ?? 'bg-label-primary';
@endphp
<div class="user-avatar flex-shrink-0 {{ $marginClass }}">
    <div class="avatar avatar-sm">
        @if (!empty($photoUrl))
            <img src="{{ $photoUrl }}" alt="" class="rounded-circle">
        @elseif (!empty($icon))
            <span class="avatar-initial rounded-circle {{ $labelClass }}">
                <i class="ti ti-{{ $icon }} ti-sm"></i>
            </span>
        @else
            <span class="avatar-initial rounded-circle {{ $labelClass }}">{{ $initials }}</span>
        @endif
    </div>
</div>
