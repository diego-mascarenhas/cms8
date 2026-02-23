@props(['submit', 'id' => null])

<div class="card" @if($id) id="{{ $id }}" @endif>
  <h5 class="card-header">
    {{ $title }}
  </h5>
  <div class="card-body">
    <form wire:submit.prevent="{{ $submit }}">

      <p class="card-text text-muted">
        {{ $description }}
      </p>

      {{ $form }}

      @if (isset($actions))
        <div class="d-flex justify-content-end">
          {{ $actions }}
        </div>
      @endif
    </form>
  </div>
</div>
