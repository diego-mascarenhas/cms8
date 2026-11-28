@props(['id' => null, 'icon' => null])
<div {{ $attributes->merge(['class' => 'card']) }} @if($id) id="{{ $id }}" @endif>
  <h5 class="card-header">
    @if ($icon)
      <i class="ti {{ $icon }} me-2"></i>
    @endif
    {{ $title }}
  </h5>
  <div class="card-body">
    <p class="card-text text-muted">
      {{ $description }}
    </p>
    {{ $content }}
  </div>
</div>
