@props(['id' => null])
<div class="card" @if($id) id="{{ $id }}" @endif>
  <h5 class="card-header">
    {{ $title }}
  </h5>
  <div class="card-body">
    <p class="card-text text-muted">
      {{ $description }}
    </p>
    {{ $content }}
  </div>
</div>
