<div class="d-inline-flex">
  <a href="{{ route('language-variants.edit', $variant->id) }}" class="btn btn-sm btn-icon item-edit me-1">
    <i class="ti ti-edit ti-sm me-2"></i>
  </a>
  <form action="{{ route('language-variants.destroy', $variant->id) }}" method="POST" class="delete-form">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-icon item-delete"
      onclick="return confirm('¿Está seguro de eliminar esta variante?')">
      <i class="ti ti-trash ti-sm"></i>
    </button>
  </form>
</div>