/**
 * App Kanban - Custom Laravel Integration
 */

'use strict';

(function () {
    // Get data from Laravel
    const { statuses, tasksByStatus, boardId, projectId, storeUrl, updateStatusUrl, updateOrderUrl, csrfToken, currentUserId } = window.kanbanData;

	const kanbanWrapper = document.querySelector('.kanban-wrapper');

	// Build boards array for jKanban
	const boards = statuses.map((status) => {
		const tasks = tasksByStatus[status.id] || [];

		return {
			id: status.id.toString(),
			title: status.name,
            item: tasks.map((task) => {
                let html = `<div class="kanban-task" data-task-id="${task.id}">`;
				html += `<div class="d-flex justify-content-between align-items-start mb-2">`;
				html += `<span class="kanban-text fw-semibold">${task.title}</span>`;
				html += renderDropdown();
				html += `</div>`;

				if (task.description)
				{
					html += `<p class="text-muted mb-2 small">${task.description.substring(0, 100)}${task.description.length > 100 ? '...' : ''}</p>`;
				}

				html += `<div class="d-flex justify-content-between align-items-center">`;
				if (task.due_date)
				{
					html += `<span class="badge bg-label-secondary"><i class="ti ti-calendar ti-xs me-1"></i>${task.due_date}</span>`;
				}
				if (task.responsible)
				{
					html += `<div class="avatar avatar-xs" data-bs-toggle="tooltip" title="${task.responsible.name}">`;
					html += `<span class="avatar-initial rounded-circle bg-label-primary">${task.responsible.name.charAt(0).toUpperCase()}</span>`;
					html += `</div>`;
				}
				html += `</div>`;
                html += `</div>`;

                return { id: `task-${task.id}`, title: html };
			})
		};
	});

	// Render item dropdown
	function renderDropdown()
	{
		return (
			"<div class='dropdown kanban-tasks-item-dropdown'>" +
			"<i class='dropdown-toggle ti ti-dots-vertical cursor-pointer' data-bs-toggle='dropdown' aria-haspopup='true' aria-expanded='false'></i>" +
			"<div class='dropdown-menu dropdown-menu-end'>" +
			"<a class='dropdown-item edit-task' href='javascript:void(0)'>Editar</a>" +
			"<a class='dropdown-item delete-task' href='javascript:void(0)'>Eliminar</a>" +
			'</div>' +
			'</div>'
		);
	}

	// Initialize jKanban
    const kanban = new jKanban({
		element: '.kanban-wrapper',
		gutter: '15px',
		widthBoard: '300px',
		boards: boards,
		dragItems: true,
        dragBoards: false,
        // Show an add button per column (Vuexy/jKanban style)
        addItemButton: true,
        buttonContent: '+ Añadir',
        itemAddOptions: {
            enabled: true,
            content: '+ Añadir',
            class: 'kanban-title-button btn btn-sm btn-label-primary',
            footer: false
        },
        // When clicking the add button on a column, show inline form like Vuexy demo
        buttonClick: function (el, columnStatusId) {
            const form = document.createElement('form');
            form.className = 'new-item-form';
            form.innerHTML = `
                <div class="mb-3">
                    <textarea class="form-control add-new-item" rows="2" placeholder="Añade contenido" autofocus required></textarea>
                </div>
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary btn-sm me-2 waves-effect waves-light">Añadir</button>
                    <button type="button" class="btn btn-label-secondary btn-sm cancel-add-item waves-effect waves-light">Cancelar</button>
                </div>
            `;
            kanban.addForm(columnStatusId, form);

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const title = form.querySelector('.add-new-item').value.trim();
                if (!title) return;

                // Create via AJAX on backend
                fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        title: title,
                        description: title,
                        responsible_id: currentUserId,
                        // keep minimal required fields
                        start_date: new Date().toISOString().slice(0, 19).replace('T', ' '),
                        due_date: new Date().toISOString().slice(0, 19).replace('T', ' '),
                        status_id: parseInt(columnStatusId),
                        category_id: null,
                        board_id: boardId,
                        view: 'kanban',
                        project_id: projectId || null
                    })
                })
                    .then(async r => {
                        if (!r.ok) throw new Error('Failed');
                        const data = await r.json().catch(() => ({}));
                        return data;
                    })
                    .then((resp) => {
                        // Add visually to column
                        const currentItems = [].slice.call(document.querySelectorAll(`.kanban-board[data-id="${columnStatusId}"] .kanban-item`));
                        kanban.addElement(columnStatusId, {
                            title: `<span class='kanban-text'>${title}</span>`
                        });
                        // close form
                        form.remove();
                    })
                    .catch(() => {
                        alert('No se pudo crear la tarea');
                    });
            });

            form.querySelector('.cancel-add-item').addEventListener('click', function () {
                form.remove();
            });
        },
		dropEl: function (el, target, source, sibling) {
			// Get task ID from element
			const taskElement = el.querySelector('.kanban-task');
			const taskId = taskElement ? taskElement.getAttribute('data-task-id') : null;

			if (!taskId)
			{
				console.error('Task ID not found');
				return;
			}

			// Get new status ID from target board
			const newStatusId = parseInt(target.parentElement.getAttribute('data-id'));

			// Calculate new order based on position
			const items = Array.from(target.children);
			const newOrder = items.indexOf(el);

			// Send update to backend
			fetch(updateStatusUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': csrfToken,
					'Accept': 'application/json'
				},
				body: JSON.stringify({
					task_id: parseInt(taskId),
					status_id: newStatusId,
					order: newOrder
				})
			})
				.then((response) => response.json())
				.then((data) => {
					if (data.success)
					{
						console.log('Task status updated successfully');
					} else
					{
						console.error('Failed to update task status');
						// Optionally reload the page or revert the change
					}
				})
				.catch((error) => {
					console.error('Error updating task status:', error);
					// Optionally reload the page or revert the change
				});
		}
	});

	// Initialize PerfectScrollbar
	if (kanbanWrapper)
	{
		new PerfectScrollbar(kanbanWrapper);
	}

	// Handle delete task
	document.addEventListener('click', function (e) {
		if (e.target.classList.contains('delete-task') || e.target.closest('.delete-task'))
		{
			e.preventDefault();
			e.stopPropagation();

			const taskElement = e.target.closest('.kanban-item');
			if (!taskElement) return;

			const taskDiv = taskElement.querySelector('.kanban-task');
			const taskId = taskDiv ? taskDiv.getAttribute('data-task-id') : null;

			if (!taskId)
			{
				console.error('Task ID not found');
				return;
			}

			if (confirm('¿Estás seguro de que deseas eliminar esta tarea?'))
			{
				// Send delete request to backend
				fetch(`/task/${taskId}`, {
					method: 'DELETE',
					headers: {
						'X-CSRF-TOKEN': csrfToken,
						'Accept': 'application/json'
					}
				})
					.then((response) => response.json())
					.then((data) => {
						if (data.success)
						{
							// Remove element from kanban
							const elementId = taskElement.getAttribute('data-eid');
							kanban.removeElement(elementId);
						} else
						{
							alert('Error al eliminar la tarea');
						}
					})
					.catch((error) => {
						console.error('Error deleting task:', error);
						alert('Error al eliminar la tarea');
					});
			}
		}
	});

	// Handle edit task
	document.addEventListener('click', function (e) {
		if (e.target.classList.contains('edit-task') || e.target.closest('.edit-task'))
		{
			e.preventDefault();
			e.stopPropagation();

			const taskElement = e.target.closest('.kanban-item');
			if (!taskElement) return;

			const taskDiv = taskElement.querySelector('.kanban-task');
			const taskId = taskDiv ? taskDiv.getAttribute('data-task-id') : null;

			if (!taskId)
			{
				console.error('Task ID not found');
				return;
			}

			// Redirect to edit page
			window.location.href = `/task/${taskId}/edit`;
		}
	});

	// Initialize tooltips
	const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
	tooltipTriggerList.map(function (tooltipTriggerEl) {
		return new bootstrap.Tooltip(tooltipTriggerEl);
	});

	// Prevent dropdown from closing kanban
	const dropdowns = document.querySelectorAll('.kanban-tasks-item-dropdown');
	dropdowns.forEach((dropdown) => {
		dropdown.addEventListener('click', function (e) {
			e.stopPropagation();
		});
	});
})();

