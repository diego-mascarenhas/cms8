/**
 * App Kanban - Custom Laravel Integration
 */

'use strict';

(function () {
    // Get data from Laravel
    const { statuses, tasksByStatus, boardId, projectId, storeUrl, updateStatusUrl, updateOrderUrl, csrfToken, currentUserId, users, categories } = window.kanbanData;

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
                // store metadata for offcanvas population
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

			// Send status update to backend
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
					if (!data.success) {
						throw new Error('status update failed');
					}

					// After status update, send full order list for the target column
					const orderPayload = Array.from(target.children)
						.map((node, index) => {
							const inner = node.querySelector('.kanban-task');
							const id = inner ? parseInt(inner.getAttribute('data-task-id')) : null;
							return id ? { id, order: index } : null;
						})
						.filter(Boolean);

					return fetch(updateOrderUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json'
						},
						body: JSON.stringify({ tasks: orderPayload })
					});
				})
				.then((response) => response ? response.json() : { success: true })
				.then((data) => {
					if (!data || data.success) {
						console.log('Task order updated successfully');
					} else {
						console.error('Failed to update task order');
					}
				})
				.catch((error) => {
					console.error('Error updating task status/order:', error);
				});
		}
	});

	// Initialize PerfectScrollbar (guard if library missing)
	if (kanbanWrapper && window.PerfectScrollbar)
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

	// Helper to open the edit offcanvas from a kanban item
	function openOffcanvasFromItem(taskElement)
	{
		const taskDiv = taskElement.querySelector('.kanban-task');
		const taskId = taskDiv ? taskDiv.getAttribute('data-task-id') : null;
		if (!taskId)
		{
			console.error('Task ID not found');
			return;
		}

		const sidebarEl = document.querySelector('.kanban-update-item-sidebar');
		if (!sidebarEl) return;
		if (sidebarEl.parentElement !== document.body)
		{
			document.body.appendChild(sidebarEl);
		}
		const OffcanvasCtor = window.bootstrap && window.bootstrap.Offcanvas ? window.bootstrap.Offcanvas : null;
		let offcanvas = OffcanvasCtor && OffcanvasCtor.getInstance ? OffcanvasCtor.getInstance(sidebarEl) : null;
		if (!offcanvas)
		{
			offcanvas = OffcanvasCtor && OffcanvasCtor.getOrCreateInstance
				? OffcanvasCtor.getOrCreateInstance(sidebarEl, { backdrop: true, scroll: true })
				: new bootstrap.Offcanvas(sidebarEl, { backdrop: true, scroll: true });
		}

		console.log('[Kanban] Offcanvas opening');

        // Prefill fields
		const titleEl = taskDiv.querySelector('.kanban-text');
		const dateBadge = taskDiv.querySelector('.badge');
		const inputTitle = sidebarEl.querySelector('#title');
		const inputDue = sidebarEl.querySelector('#due-date');
		inputTitle.value = titleEl ? titleEl.textContent.trim() : '';
		inputDue.value = dateBadge ? dateBadge.textContent.replace(/^[^\d]*/, '').trim() : '';

		// Populate select2 with categories if present
		const labelSelect = sidebarEl.querySelector('#label');
		if (labelSelect && window.$ && $.fn.select2)
		{
			$(labelSelect).empty();
			// Add empty option
			$(labelSelect).append(new Option('Selecciona una categoría', '', false, false));
			// Add categories from backend
			(categories || []).forEach(c => {
				const opt = new Option(c.name, c.id, false, false);
				$(labelSelect).append(opt);
			});
			$(labelSelect).select2({
				dropdownParent: $(sidebarEl),
				placeholder: 'Selecciona una categoría',
				allowClear: true
			});
		}

		if (window.flatpickr)
        {
            const opts = {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j F, Y'
            };
            try {
                if (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.es) {
                    opts.locale = window.flatpickr.l10ns.es;
                }
            } catch (e) {}
            const datepicker = window.flatpickr(inputDue, opts);

            // Open calendar when clicking the settings/gear button
            const settingsBtn = sidebarEl.querySelector('#due-date-settings');
            if (settingsBtn && !settingsBtn.dataset.fpBound)
            {
                settingsBtn.addEventListener('click', function () {
                    if (datepicker && typeof datepicker.open === 'function') {
                        datepicker.open();
                    }
                });
                settingsBtn.dataset.fpBound = '1';
            }
        }

		// Submit on Enter: intercept form submit and route to onSave
		const formEl = sidebarEl.querySelector('#tab-update form');
		if (formEl && !formEl.dataset.submitBound)
		{
			formEl.addEventListener('submit', function (e) {
				e.preventDefault();
				e.stopPropagation();
				onSave(e);
			}, { once: false });
			formEl.dataset.submitBound = '1';
		}

        // Populate assigned avatars (simple single responsible for now)
        const assignedWrap = sidebarEl.querySelector('.assigned');
        if (assignedWrap)
        {
            assignedWrap.innerHTML = '';
            const name = (titleEl && titleEl.textContent) ? titleEl.textContent.trim().charAt(0).toUpperCase() : 'U';
            const avatar = document.createElement('div');
            avatar.className = 'avatar avatar-sm';
            avatar.innerHTML = `<span class="avatar-initial rounded-circle bg-label-primary">${name}</span>`;
            assignedWrap.appendChild(avatar);
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'btn btn-sm btn-icon btn-label-secondary';
            addBtn.innerHTML = '<i class="ti ti-plus"></i>';
            assignedWrap.appendChild(addBtn);
        }

		const saveBtn = sidebarEl.querySelector('#offcanvas-save') || sidebarEl.querySelector('#tab-update .btn.btn-primary');
		console.log('[Kanban] Found elements', {
			inputTitleFound: !!inputTitle,
			inputDueFound: !!inputDue,
			saveBtnFound: !!saveBtn
		});
		const onSave = (ev) => {
			if (ev) { ev.preventDefault(); ev.stopPropagation(); }
			const newTitle = inputTitle.value.trim();
			const newDue = inputDue.value ? `${inputDue.value} 00:00:00` : null;
			const categorySelect = sidebarEl.querySelector('#label');
			const categoryId = categorySelect && categorySelect.value ? parseInt(categorySelect.value) : null;
			const boardEl = taskElement.closest('.kanban-board');
			const statusId = boardEl ? parseInt(boardEl.getAttribute('data-id')) : null;
			console.log('[Kanban] Saving task...', { taskId, newTitle, newDue, categoryId, statusId, boardId, projectId });

			fetch(storeUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': csrfToken,
					'Accept': 'application/json'
				},
				body: JSON.stringify({
					id: parseInt(taskId),
					title: newTitle || 'Sin título',
					description: newTitle || '',
					responsible_id: currentUserId,
					start_date: newDue || new Date().toISOString().slice(0, 19).replace('T', ' '),
					due_date: newDue || new Date().toISOString().slice(0, 19).replace('T', ' '),
					status_id: statusId,
					category_id: categoryId,
					board_id: boardId,
					view: 'kanban',
					project_id: projectId || null
				})
			})
				.then(async r => {
					const text = await r.text();
					let data;
					try { data = text ? JSON.parse(text) : {}; } catch (e) { data = { raw: text }; }
					if (!r.ok) {
						console.error('[Kanban] Save failed', r.status, data);
						throw new Error('Save failed');
					}
					return data;
				})
				.then((data) => {
					console.log('[Kanban] Saved', data);
					if (newTitle && titleEl) titleEl.textContent = newTitle;
					if (newDue)
					{
						let badge = taskDiv.querySelector('.badge');
						if (!badge)
						{
							badge = document.createElement('span');
							badge.className = 'badge bg-label-secondary';
							taskDiv.querySelector('.d-flex.justify-content-between').appendChild(badge);
						}
						badge.innerHTML = `<i class="ti ti-calendar ti-xs me-1"></i>${newDue.slice(0,10)}`;
					}
					offcanvas.hide();
					if (saveBtn) saveBtn.removeEventListener('click', onSave);
				})
				.catch((err) => { console.error(err); alert('No se pudo guardar'); });
		};
			if (saveBtn) {
				// Prevent bootstrap auto-dismiss before saving
				saveBtn.removeAttribute('data-bs-dismiss');
				saveBtn.addEventListener('click', onSave, { once: true });
			}
		offcanvas.show();
	}

	// Handle edit task option
	document.addEventListener('click', function (e) {
		if (e.target.classList.contains('edit-task') || e.target.closest('.edit-task'))
		{
			e.preventDefault();
			e.stopPropagation();
			const taskElement = e.target.closest('.kanban-item');
			if (taskElement) openOffcanvasFromItem(taskElement);
		}
	});

	// Click anywhere on a card opens the editor (like the original)
	document.addEventListener('click', function (e) {
		// Ignore clicks on the dropdown trigger/menu inside the card
		if (e.target.closest('.kanban-tasks-item-dropdown')) return;
		const item = e.target.closest('.kanban-item');
		if (!item) return;
		openOffcanvasFromItem(item);
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

