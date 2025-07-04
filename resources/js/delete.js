function deleteRecord(url, element) {
	Swal.fire({
		title: '¿Estás seguro de que deseas eliminar este registro?',
		text: 'Esta acción no se puede deshacer',
		icon: 'warning',
		showCloseButton: false,
		showCancelButton: true,
		confirmButtonColor: '#3085d6',
		cancelButtonColor: '#d33',
		confirmButtonText: 'Sí, eliminar',
		cancelButtonText: 'Cancelar',
	}).then(result => {
		if (result.isConfirmed) {
			fetch(url, {
				method: 'DELETE',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
				},
			})
				.then(response => {
					if (!response.ok) {
						throw new Error('Network response was not ok.');
					}
					return response.json();
				})
				.then(data => {
					console.log('Response data:', data);

					const toastHTML = `
              <div id="toast-container" class="toast-top-right">
                  <div class="toast toast-success" aria-live="polite" style="display: block;">
                      <div class="toast-client">${data.success}</div>
                  </div>
              </div>
              `;
					document.body.insertAdjacentHTML('beforeend', toastHTML);
					var toastElement = document.getElementById('toast-container');
					var toast = new bootstrap.Toast(toastElement, {
						animation: true,
						delay: 3000,
						autohide: true,
					});
					toast.show();

					const row = element.closest('tr');
					if (row) {
						row.style.display = 'none';
					} else {
						console.error('No se encontró la fila correspondiente.');
					}
				})
				.catch(error => {
					console.error('Error:', error);
					Swal.fire('Error', 'Ha ocurrido un error al eliminar el registro', 'error');
				});
		}
	});
}
