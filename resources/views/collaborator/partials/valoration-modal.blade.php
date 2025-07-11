<!-- Valoration Modal -->
<div class="modal fade" id="valorationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Valorar colaborador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="valoration_id" class="form-label">Selecciona una valoración</label>
                    <select class="form-select" id="valoration_id" name="valoration_id">
                        @foreach(\App\Models\ContactValoration::getOptions() as $id => $name)
                            <option value="{{ $id }}" {{ $collaborator->valoration_id == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveValoration">Guardar</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Save valoration
        document.getElementById('saveValoration').addEventListener('click', function() {
            const valorationId = document.getElementById('valoration_id').value;
            const collaboratorId = {{ $collaborator->id }};
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            fetch(`/collaborator/${collaboratorId}/update-valoration`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    valoration_id: valorationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the modal
                    const modalElement = document.getElementById('valorationModal');
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    modal.hide();
                    
                    // Update the badge
                    const valoration = data.valoration;
                    const badgeClass = valoration.name === 'Lista negra' ? 'danger' : 
                                     (valoration.name === 'Top' ? 'warning' : 'primary');
                    
                    const badgeHtml = `
                        <span class="badge bg-label-${badgeClass} rounded-pill">
                            ${valoration.icon} ${valoration.name}
                        </span>
                    `;
                    
                    // Find the badge element (it's the first badge after the h4)
                    const badgeContainer = document.querySelector('.d-flex.align-items-center.flex-column.mb-3');
                    const badgeElement = badgeContainer.querySelector('.badge');
                    badgeElement.outerHTML = badgeHtml;
                    
                    // Show success notification
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Valoración actualizada correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert('Valoración actualizada correctamente');
                    }
                }
            })
            .catch(error => {
                console.error('Error updating valoration:', error);
                alert('Error al actualizar la valoración');
            });
        });
    });
</script>
@endpush 