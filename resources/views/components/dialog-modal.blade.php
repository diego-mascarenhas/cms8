@props(['id' => null, 'maxWidth' => null, 'centered' => false])

<x-modal :id="$id" :maxWidth="$maxWidth" :centered="$centered" {{ $attributes }}>
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">{{ $title }}</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
      </button>
    </div>
    <div class="modal-body">
      {{ $content }}
    </div>
    <div class="modal-footer">
      {{ $footer }}
    </div>
  </div>
</x-modal>
