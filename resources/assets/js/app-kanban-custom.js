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
                // jKanban adds the .kanban-item wrapper automatically, so we only return the inner content
                // We store task data in the data attributes that will be added to the .kanban-item by jKanban
                let html = `<div class="d-flex justify-content-between flex-wrap align-items-center mb-2 pb-1">`;

		// Category badge (or "Sin categorizar" if no category)
		html += `<div class="item-badges">`;
		if (task.category)
		{
			html += `<div class="badge rounded-pill bg-label-warning category-badge">${task.category.name}</div>`;
		}
		else
		{
			html += `<div class="badge rounded-pill bg-label-secondary category-badge">Sin categorizar</div>`;
		}
		html += `</div>`;

		// Timer button always on the right
		html += renderStartTimer(task.id);

	html += `</div>`;
	html += `<span class="kanban-text">${task.title}</span>`;

		// Add image if attachment exists
		if (task.attachment)
		{
			html += `<img src="${task.attachment}" alt="${task.title}" class="img-fluid rounded kanban-image my-2" style="max-height: 120px; width: 100%; object-fit: cover;">`;
		}

		html += `<div class="d-flex justify-content-between align-items-center flex-wrap mt-2 pt-1">`;
			html += `<div class="d-flex">`;
			if (task.due_date)
			{
				// Calculate days remaining
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				const dueDate = new Date(task.due_date);
				dueDate.setHours(0, 0, 0, 0);
				const daysRemaining = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));

				let badgeColor = 'bg-label-secondary';
				if (daysRemaining < 2)
				{
					badgeColor = 'bg-label-danger';
				}
				else if (daysRemaining <= 7)
				{
					badgeColor = 'bg-label-warning';
				}

				// Format date as DD/MM/YYYY
				const [year, month, day] = task.due_date.split('-');
				const formattedDate = `${day}/${month}/${year}`;

				html += `<span class="d-flex align-items-center me-2"><i class="ti ti-calendar ti-xs me-1"></i><span class="badge ${badgeColor} date-badge">${formattedDate}</span></span>`;
			}
			html += `</div>`;
				if (task.responsible)
				{
                    html += `<div class="avatar-group d-flex align-items-center assigned-avatar">`;
                    html += `<div class="avatar avatar-xs" data-bs-toggle="tooltip" data-bs-placement="top" title="${task.responsible.name}">`;
                    html += `<span class="avatar-initial rounded-circle bg-label-primary pull-up">${task.responsible.name.charAt(0).toUpperCase()}</span>`;
                    html += `</div>`;
					html += `</div>`;
				}
				html += `</div>`;  // Cierra div.d-flex.justify-content-between.align-items-center

                return {
                    id: `task-${task.id}`,
                    title: html,
                    // Store task data for later use (jKanban doesn't support custom data attributes directly)
                _taskData: {
                    taskId: task.id,
                    categoryId: task.category ? task.category.id : '',
                    dueDate: task.due_date || '',
                    responsibleId: task.responsible ? task.responsible.id : '',
                    estimatedHours: task.estimated_hours || '',
                    description: task.description || '',
                    attachment: task.attachment || ''
                }
                };
			})
		};
	});

	// Render start timer button
	function renderStartTimer()
	{
		return (
			"<button class='btn btn-icon btn-sm btn-label-primary start-timer-btn' type='button' title='Iniciar Timer'>" +
			"<i class='ti ti-clock-play'></i>" +
			'</button>'
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
        buttonContent: '+ Agregar Nueva Tarea',
        itemAddOptions: {
            enabled: true,
            content: '+ Agregar Nueva Tarea',
            class: 'kanban-title-button btn',
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
                        // Create the full HTML structure for the new task
                        let html = `<div class="d-flex justify-content-between flex-wrap align-items-center mb-2 pb-1">`;

                        // Category badge (default to "Sin categorizar")
                        html += `<div class="item-badges">`;
                        html += `<div class="badge rounded-pill bg-label-secondary category-badge">Sin categorizar</div>`;
                        html += `</div>`;

                        // Timer button
                        html += renderStartTimer(resp.task?.id || 'new');
                        html += `</div>`;

                        // Task title
                        html += `<span class="kanban-text">${title}</span>`;

                        // Bottom section with due date and responsible
                        html += `<div class="d-flex justify-content-between align-items-center flex-wrap mt-2 pt-1">`;
                        html += `<div class="d-flex">`;
                        html += `<div class="d-flex align-items-center me-3">`;
                        html += `<i class="ti ti-clock me-1"></i>`;
                        html += `<small class="text-muted">Sin fecha</small>`;
                        html += `</div>`;
                        html += `</div>`;
                        html += `<div class="d-flex align-items-center">`;
                        html += `<div class="avatar avatar-sm me-2">`;
                        html += `<span class="avatar-initial rounded-circle bg-label-primary">${users.find(u => u.id === currentUserId)?.name?.charAt(0) || 'U'}</span>`;
                        html += `</div>`;
                        html += `</div>`;
                        html += `</div>`;

                        // Add the new task with full HTML structure
                        kanban.addElement(columnStatusId, {
                            title: html,
                            id: resp.task?.id || `new-${Date.now()}`
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
			// Get task ID from element - el is the .kanban-item itself
			const taskId = el.getAttribute('data-task-id');

			if (!taskId)
			{
				console.error('Task ID not found in element:', el);
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
							// node is the .kanban-item itself
							const id = node.getAttribute('data-task-id');
							return id ? { id: parseInt(id), order: index } : null;
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

	// After jKanban initializes, manually add data attributes to each .kanban-item
	// because jKanban doesn't support custom data attributes directly
	boards.forEach(board => {
		board.item.forEach((taskItem) => {
			const taskElement = document.querySelector(`.kanban-item[data-eid="${taskItem.id}"]`);
			if (taskElement && taskItem._taskData) {
				taskElement.setAttribute('data-task-id', taskItem._taskData.taskId);
				taskElement.setAttribute('data-category-id', taskItem._taskData.categoryId);
				taskElement.setAttribute('data-due-date', taskItem._taskData.dueDate);
				taskElement.setAttribute('data-responsible-id', taskItem._taskData.responsibleId);
				taskElement.setAttribute('data-estimated-hours', taskItem._taskData.estimatedHours);
				taskElement.setAttribute('data-description', taskItem._taskData.description.replace(/"/g, '&quot;'));
				taskElement.setAttribute('data-attachment', taskItem._taskData.attachment);
				console.log('[Kanban] Added data attributes to task:', taskItem._taskData.taskId, taskElement);
			}
		});
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

		// taskElement is the .kanban-item itself
		const taskId = taskElement.getAttribute('data-task-id');

		if (!taskId)
		{
			console.error('Task ID not found in element:', taskElement);
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
		// taskElement is the .kanban-item itself
		const taskDiv = taskElement.classList.contains('kanban-item') ? taskElement : taskElement.querySelector('.kanban-item');
		const taskId = taskDiv ? taskDiv.getAttribute('data-task-id') : null;
		console.log('[Kanban] openOffcanvasFromItem - taskDiv:', taskDiv, 'taskId:', taskId);
		if (!taskId)
		{
			console.error('Task ID not found in element:', taskElement);
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

		// Store taskId in sidebar element for activity loading
		sidebarEl.setAttribute('data-current-task-id', taskId);

        // Prefill fields from data attributes
		const titleEl = taskDiv.querySelector('.kanban-text');
		const inputTitle = sidebarEl.querySelector('#title');
		const inputDue = sidebarEl.querySelector('#due-date');
		const inputEstimated = sidebarEl.querySelector('#estimated-hours');
		const inputDescription = sidebarEl.querySelector('#description');

	inputTitle.value = titleEl ? titleEl.textContent.trim() : '';
	inputDue.value = taskDiv.getAttribute('data-due-date') || '';
	if (inputEstimated) inputEstimated.value = taskDiv.getAttribute('data-estimated-hours') || '';
	if (inputDescription) inputDescription.value = taskDiv.getAttribute('data-description') || '';

	// Handle attachment preview
	const currentAttachment = taskDiv.getAttribute('data-attachment');
	const attachmentInput = sidebarEl.querySelector('#attachments');
	const previewContainer = sidebarEl.querySelector('#attachment-preview');
	const previewImage = sidebarEl.querySelector('#preview-image');
	const removeAttachmentBtn = sidebarEl.querySelector('#remove-attachment');

	// Show existing attachment preview if exists
	if (currentAttachment && previewContainer && previewImage)
	{
		previewImage.src = currentAttachment;
		previewContainer.style.display = 'block';
	}
	else if (previewContainer)
	{
		previewContainer.style.display = 'none';
	}

	// Handle image preview when file is selected
	if (attachmentInput && previewContainer && previewImage)
	{
		// Remove old event listeners by cloning the node
		const newAttachmentInput = attachmentInput.cloneNode(true);
		attachmentInput.parentNode.replaceChild(newAttachmentInput, attachmentInput);

		newAttachmentInput.addEventListener('change', function(e) {
			const file = e.target.files[0];
			if (file && file.type.startsWith('image/')) {
				const reader = new FileReader();
				reader.onload = function(event) {
					previewImage.src = event.target.result;
					previewContainer.style.display = 'block';
				};
				reader.readAsDataURL(file);
			}
		});
	}

	// Handle remove attachment button
	if (removeAttachmentBtn)
	{
		// Remove old event listeners by cloning the node
		const newRemoveBtn = removeAttachmentBtn.cloneNode(true);
		removeAttachmentBtn.parentNode.replaceChild(newRemoveBtn, removeAttachmentBtn);

		newRemoveBtn.addEventListener('click', function() {
			// Use SweetAlert2 for confirmation dialog
			if (typeof Swal !== 'undefined') {
				Swal.fire({
					title: '¿Eliminar imagen?',
					text: 'Esta acción no se puede deshacer',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonColor: '#d33',
					cancelButtonColor: '#3085d6',
					confirmButtonText: 'Sí, eliminar',
					cancelButtonText: 'Cancelar'
				}).then((result) => {
					if (result.isConfirmed) {
						deleteAttachment();
					}
				});
			} else {
				// Fallback to native confirm
				if (confirm('¿Estás seguro de que deseas eliminar la imagen?')) {
					deleteAttachment();
				}
			}

			function deleteAttachment() {
				const input = sidebarEl.querySelector('#attachments');
				const preview = sidebarEl.querySelector('#attachment-preview');
				const img = sidebarEl.querySelector('#preview-image');

				// Send request to backend to delete the attachment
				const formData = new FormData();
				formData.append('id', parseInt(taskId));
				formData.append('title', sidebarEl.querySelector('#title').value);
				formData.append('description', sidebarEl.querySelector('#description') ? sidebarEl.querySelector('#description').value : '');
				formData.append('responsible_id', sidebarEl.querySelector('#responsible') ? sidebarEl.querySelector('#responsible').value : currentUserId);
				formData.append('estimated_hours', sidebarEl.querySelector('#estimated-hours') ? sidebarEl.querySelector('#estimated-hours').value : '');
				formData.append('start_date', sidebarEl.querySelector('#due-date').value);
				formData.append('due_date', sidebarEl.querySelector('#due-date').value);
				formData.append('status_id', taskElement.closest('.kanban-board') ? taskElement.closest('.kanban-board').getAttribute('data-id') : '');
				formData.append('category_id', sidebarEl.querySelector('#label') && sidebarEl.querySelector('#label').value ? sidebarEl.querySelector('#label').value : '');
				formData.append('board_id', boardId);
				formData.append('view', 'kanban');
				formData.append('project_id', projectId || '');
				formData.append('remove_attachment', '1'); // Flag to indicate attachment removal

				fetch('/task', {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': csrfToken,
						'Accept': 'application/json'
					},
					body: formData
				})
				.then(res => res.json())
				.then(data => {
					if (data.success) {
						// Clear UI elements in offcanvas
						if (input) input.value = '';
						if (img) img.src = '';
						if (preview) preview.style.display = 'none';

						// Clear the attachment from the task data
						if (taskDiv) taskDiv.setAttribute('data-attachment', '');

						// Remove image from Kanban card
						const contentDiv = taskElement;
						const imgElement = contentDiv ? contentDiv.querySelector('.kanban-image') : null;
						if (imgElement) {
							imgElement.remove();
						}

						console.log('[Kanban] Attachment deleted successfully');

						// Show success message
						if (typeof Swal !== 'undefined') {
							Swal.fire({
								icon: 'success',
								title: 'Imagen eliminada',
								showConfirmButton: false,
								timer: 1500
							});
						}
					} else {
						if (typeof Swal !== 'undefined') {
							Swal.fire('Error', 'No se pudo eliminar la imagen', 'error');
						} else {
							alert('Error al eliminar la imagen');
						}
					}
				})
				.catch(err => {
					console.error('[Kanban] Error deleting attachment:', err);
					if (typeof Swal !== 'undefined') {
						Swal.fire('Error', 'No se pudo eliminar la imagen', 'error');
					} else {
						alert('Error al eliminar la imagen');
					}
				});
			}
		});
	}

	// Populate select2 with grouped categories
		const labelSelect = sidebarEl.querySelector('#label');
		const currentCategoryId = taskDiv ? taskDiv.getAttribute('data-category-id') : null;
		if (labelSelect && window.$ && $.fn.select2)
		{
			$(labelSelect).empty();
			$(labelSelect).append(new Option('Selecciona una categoría', '', false, false));

			// Wait for kanbanData to be available
			const populateCategories = () => {
				if (!window.kanbanData || !window.kanbanData.categories) {
					console.log('kanbanData not available yet, retrying...');
					setTimeout(populateCategories, 100);
					return;
				}

				console.log('Categories data:', window.kanbanData.categories);

				// Handle grouped categories structure - using prefixed options instead of optgroups
				window.kanbanData.categories.forEach(group => {
					console.log('Processing group:', group);
					if (group.categories && group.categories.length > 0) {
						console.log('Adding group header for:', group.name);

						// Add group header as disabled option
						const groupHeader = new Option(`── ${group.name} ──`, '', false, false);
						groupHeader.disabled = true;
						groupHeader.style.fontWeight = 'bold';
						groupHeader.style.color = '#666';
						$(labelSelect).append(groupHeader);

						// Add subcategories with indentation
						group.categories.forEach(category => {
							console.log('Adding subcategory:', category.name, 'ID:', category.id);
							const isSelected = currentCategoryId && parseInt(currentCategoryId) === category.id;
							const opt = new Option(`  ${category.name}`, category.id, isSelected, isSelected);
							$(labelSelect).append(opt);
						});

						console.log('Group added to select');
					} else {
						console.log('No subcategories found for group:', group.name);
					}
				});

				$(labelSelect).select2({
					dropdownParent: $(sidebarEl),
					placeholder: 'Selecciona una categoría',
					allowClear: true,
					// Add passive event listeners to improve performance
					scrollAfterSelect: false
				});
			};

			populateCategories();
		}

		// Populate select2 with responsible users and pre-select current responsible
		const responsibleSelect = sidebarEl.querySelector('#responsible');
		const currentResponsibleId = taskDiv ? taskDiv.getAttribute('data-responsible-id') : null;
		if (responsibleSelect && window.$ && $.fn.select2)
		{
			$(responsibleSelect).empty();
			$(responsibleSelect).append(new Option('Selecciona un responsable', '', false, false));
			(users || []).forEach(u => {
				const isSelected = currentResponsibleId && parseInt(currentResponsibleId) === u.id;
				const opt = new Option(u.name, u.id, isSelected, isSelected);
				$(responsibleSelect).append(opt);
			});
			$(responsibleSelect).select2({
				dropdownParent: $(sidebarEl),
				placeholder: 'Selecciona un responsable',
				allowClear: false,
				// Add passive event listeners to improve performance
				scrollAfterSelect: false
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

		const saveBtn = sidebarEl.querySelector('#offcanvas-save') || sidebarEl.querySelector('#tab-update .btn.btn-primary');
		console.log('[Kanban] Found elements', {
			inputTitleFound: !!inputTitle,
			inputDueFound: !!inputDue,
			saveBtnFound: !!saveBtn
		});
		const onSave = (ev) => {
			if (ev) { ev.preventDefault(); ev.stopPropagation(); }
			const newTitle = inputTitle.value.trim();
			const newDue = inputDue.value || null;
			const newDescription = inputDescription ? inputDescription.value.trim() : '';
			const newEstimatedHours = inputEstimated ? parseFloat(inputEstimated.value) || null : null;
			const categorySelect = sidebarEl.querySelector('#label');
			const categoryId = categorySelect && categorySelect.value ? parseInt(categorySelect.value) : null;
			const responsibleSelect = sidebarEl.querySelector('#responsible');
			const responsibleId = responsibleSelect && responsibleSelect.value ? parseInt(responsibleSelect.value) : currentUserId;
			const boardEl = taskElement.closest('.kanban-board');
			const statusId = boardEl ? parseInt(boardEl.getAttribute('data-id')) : null;

			// Handle image attachment using FormData
			const attachmentInput = sidebarEl.querySelector('#attachments');
			const hasNewFile = attachmentInput && attachmentInput.files && attachmentInput.files[0];

			console.log('[Kanban] Saving task...', { taskId, newTitle, newDue, newDescription, newEstimatedHours, categoryId, responsibleId, statusId, boardId, projectId, hasNewFile });

			// Use FormData to support file uploads
			const formData = new FormData();
			formData.append('id', parseInt(taskId));
			formData.append('title', newTitle || 'Sin título');
			formData.append('description', newDescription);
			formData.append('responsible_id', responsibleId);
			formData.append('estimated_hours', newEstimatedHours || '');
			formData.append('start_date', newDue);
			formData.append('due_date', newDue);
			formData.append('status_id', statusId);
			formData.append('category_id', categoryId || '');
			formData.append('board_id', boardId);
			formData.append('view', 'kanban');
			formData.append('project_id', projectId || '');

			// Add file if present
			if (hasNewFile)
			{
				formData.append('attachment', attachmentInput.files[0]);
			}

			fetch(storeUrl, {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': csrfToken,
					'Accept': 'application/json'
				},
				body: formData
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

					// Update data attributes
					if (newDue) taskDiv.setAttribute('data-due-date', newDue);
					if (newDescription) taskDiv.setAttribute('data-description', newDescription.replace(/"/g, '&quot;'));
					if (newEstimatedHours) taskDiv.setAttribute('data-estimated-hours', newEstimatedHours);
					if (categoryId) taskDiv.setAttribute('data-category-id', categoryId);
					if (responsibleId) taskDiv.setAttribute('data-responsible-id', responsibleId);

			// Update category badge (always exists now, show "Sin categorizar" if no category)
			let itemBadges = taskDiv.querySelector('.item-badges');
			const categoryBadge = itemBadges ? itemBadges.querySelector('.category-badge') : null;
			const selectedOption = categorySelect ? categorySelect.options[categorySelect.selectedIndex] : null;

			if (categoryBadge)
			{
				if (categoryId && selectedOption && selectedOption.text)
				{
					// Update to selected category
					categoryBadge.textContent = selectedOption.text;
					categoryBadge.className = 'badge rounded-pill bg-label-warning category-badge';
				}
				else
				{
					// No category selected, show "Sin categorizar"
					categoryBadge.textContent = 'Sin categorizar';
					categoryBadge.className = 'badge rounded-pill bg-label-secondary category-badge';
				}
			}
			else if (itemBadges)
			{
				// Create category badge if it doesn't exist
				const newCategoryBadge = document.createElement('div');
				if (categoryId && selectedOption && selectedOption.text)
				{
					newCategoryBadge.className = 'badge rounded-pill bg-label-warning category-badge';
					newCategoryBadge.textContent = selectedOption.text;
				}
				else
				{
					newCategoryBadge.className = 'badge rounded-pill bg-label-secondary category-badge';
					newCategoryBadge.textContent = 'Sin categorizar';
				}
				itemBadges.appendChild(newCategoryBadge);
			}

				// Update date badge with color based on days remaining
					if (newDue)
				{
					// Calculate days remaining
					const today = new Date();
					today.setHours(0, 0, 0, 0);
					const dueDate = new Date(newDue);
					dueDate.setHours(0, 0, 0, 0);
					const daysRemaining = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));

					let badgeColor = 'bg-label-secondary';
					if (daysRemaining < 2)
					{
						badgeColor = 'bg-label-danger';
					}
					else if (daysRemaining <= 7)
					{
						badgeColor = 'bg-label-warning';
					}

					let badge = taskDiv.querySelector('.date-badge');
						if (!badge)
						{
							badge = document.createElement('span');
						badge.className = `badge ${badgeColor} date-badge`;
						const bottomRow = taskDiv.querySelector('.d-flex.justify-content-between.align-items-center');
						if (bottomRow) bottomRow.insertBefore(badge, bottomRow.firstChild);
					}
					else
					{
						// Update existing badge color classes
						badge.className = `badge ${badgeColor} date-badge`;
					}
					// Format date as DD/MM/YYYY
					const [year, month, day] = newDue.split('-');
					const formattedDate = `${day}/${month}/${year}`;
					if (badge) badge.innerHTML = `<i class="ti ti-calendar ti-xs me-1"></i>${formattedDate}`;
				}

					// Update responsible avatar
					if (responsibleId)
					{
						const bottomRow = taskDiv.querySelector('.d-flex.justify-content-between.align-items-center.flex-wrap.mt-2.pt-1');
						let avatarGroup = taskDiv.querySelector('.assigned-avatar');
						let avatar = avatarGroup ? avatarGroup.querySelector('.avatar') : null;
						const selectedResponsible = responsibleSelect ? responsibleSelect.options[responsibleSelect.selectedIndex] : null;
						const responsibleName = selectedResponsible && selectedResponsible.text ? selectedResponsible.text : 'U';
						const initial = responsibleName.charAt(0).toUpperCase();

						if (!avatarGroup && bottomRow)
						{
							// Create avatar group if it doesn't exist
							avatarGroup = document.createElement('div');
							avatarGroup.className = 'avatar-group d-flex align-items-center assigned-avatar';
							bottomRow.appendChild(avatarGroup);
						}

						if (!avatar && avatarGroup)
						{
							// Create avatar if it doesn't exist
							avatar = document.createElement('div');
							avatar.className = 'avatar avatar-xs';
							avatar.setAttribute('data-bs-toggle', 'tooltip');
							avatar.setAttribute('data-bs-placement', 'top');
							avatar.setAttribute('title', responsibleName);
							avatar.innerHTML = `<span class="avatar-initial rounded-circle bg-label-primary pull-up">${initial}</span>`;
							avatarGroup.appendChild(avatar);

							// Initialize tooltip for new avatar
							if (window.bootstrap && bootstrap.Tooltip) {
								new bootstrap.Tooltip(avatar);
							}
						}
						else if (avatar)
						{
							// Update existing avatar
							avatar.setAttribute('title', responsibleName);
							avatar.setAttribute('data-bs-original-title', responsibleName); // Bootstrap stores original title here
							const avatarInitial = avatar.querySelector('.avatar-initial');
							if (avatarInitial) avatarInitial.textContent = initial;

							// Update tooltip instance
							if (window.bootstrap && bootstrap.Tooltip) {
								let tooltipInstance = bootstrap.Tooltip.getInstance(avatar);
								if (tooltipInstance) {
									tooltipInstance.dispose();
								}
								new bootstrap.Tooltip(avatar);
							}
					}
				}

			// Update attachment image
			if (data.attachment)
			{
				console.log('[Kanban] Updating attachment for task', taskId, 'attachment:', data.attachment);
				console.log('[Kanban] taskDiv:', taskDiv);
				console.log('[Kanban] taskElement:', taskElement);

				// Update data attribute on the .kanban-item element
				taskElement.setAttribute('data-attachment', data.attachment);

				// Find the content div inside the kanban-item (where our HTML is)
				// The content is directly inside the .kanban-item, not in a wrapper div
				const contentDiv = taskElement;
				console.log('[Kanban] contentDiv:', contentDiv);

				// Update or create image in the card
				let imgElement = contentDiv ? contentDiv.querySelector('.kanban-image') : null;
				console.log('[Kanban] Found existing image:', imgElement);

				if (!imgElement && contentDiv)
				{
					// Create image element if it doesn't exist
					const textSpan = contentDiv.querySelector('.kanban-text');
					console.log('[Kanban] textSpan:', textSpan);

					if (textSpan)
					{
						imgElement = document.createElement('img');
						imgElement.className = 'img-fluid rounded kanban-image my-2';
						imgElement.style.maxHeight = '120px';
						imgElement.style.width = '100%';
						imgElement.style.objectFit = 'cover';

						// Find the next element after the text span (should be the bottom div)
						const nextElement = textSpan.nextElementSibling;
						if (nextElement)
						{
							// Insert before the bottom div
							textSpan.parentNode.insertBefore(imgElement, nextElement);
						}
						else
						{
							// Insert after the text span if no next element
							textSpan.parentNode.insertBefore(imgElement, textSpan.nextSibling);
						}
						console.log('[Kanban] Created new image element:', imgElement);
					}
					else
					{
						// If no textSpan found, try to find the first div and insert after it
						const firstDiv = contentDiv.querySelector('div');
						if (firstDiv)
						{
							imgElement = document.createElement('img');
							imgElement.className = 'img-fluid rounded kanban-image my-2';
							imgElement.style.maxHeight = '120px';
							imgElement.style.width = '100%';
							imgElement.style.objectFit = 'cover';
							firstDiv.parentNode.insertBefore(imgElement, firstDiv.nextSibling);
							console.log('[Kanban] Created new image element after first div:', imgElement);
						}
					}
				}

				if (imgElement)
				{
					console.log('[Kanban] Setting image src to:', data.attachment);
					imgElement.src = data.attachment;
					imgElement.alt = newTitle;
				}
				else
				{
					console.error('[Kanban] Could not find or create image element');
				}
			}
			else
			{
				// No attachment in response, remove existing image if any
				const contentDiv = taskElement;
				const imgElement = contentDiv ? contentDiv.querySelector('.kanban-image') : null;
				if (imgElement) {
					imgElement.remove();
					console.log('[Kanban] Removed existing image (no attachment in response)');
				}

				// Clear data attribute
				taskElement.setAttribute('data-attachment', '');
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

		// Handle delete button
		const deleteBtn = sidebarEl.querySelector('#offcanvas-delete');
		if (deleteBtn) {
			deleteBtn.removeAttribute('data-bs-dismiss');
			deleteBtn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();

				if (confirm('¿Estás seguro de que deseas eliminar esta tarea?')) {
					fetch(`/task/${taskId}`, {
						method: 'DELETE',
						headers: {
							'X-CSRF-TOKEN': csrfToken,
							'Accept': 'application/json'
						}
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							// Remove element from kanban
							const elementId = taskElement.getAttribute('data-eid');
							if (elementId && kanban) {
								kanban.removeElement(elementId);
							}
							offcanvas.hide();
							console.log('[Kanban] Task deleted successfully');
						} else {
							alert('Error al eliminar la tarea');
						}
					})
					.catch(error => {
						console.error('Error deleting task:', error);
						alert('Error al eliminar la tarea');
					});
				}
			}, { once: true });
		}

		// Reset activity tab to show loading state (fix for problem 3)
		const activityTab = document.querySelector('[data-bs-target="#tab-activity"]');
		const activityContainer = document.querySelector('#activity-log-container');
		if (activityContainer) {
			activityContainer.innerHTML = `
				<div class="text-center py-4">
					<div class="spinner-border spinner-border-sm text-primary" role="status">
						<span class="visually-hidden">Cargando...</span>
					</div>
				</div>
			`;
		}

		// Switch to Edit tab when opening offcanvas
		const editTab = document.querySelector('[data-bs-target="#tab-update"]');
		if (editTab) {
			const bsTab = new bootstrap.Tab(editTab);
			bsTab.show();
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
			if (taskElement) {
				// Use requestAnimationFrame to optimize performance
				requestAnimationFrame(() => {
					openOffcanvasFromItem(taskElement);
				});
			}
		}
	});

	// Click anywhere on a card opens the editor (like the original)
	// Use requestAnimationFrame to optimize performance
	document.addEventListener('click', function (e) {
		// Ignore clicks on the dropdown trigger/menu inside the card
		if (e.target.closest('.kanban-tasks-item-dropdown')) return;
		const item = e.target.closest('.kanban-item');
		if (!item) return;

		// Use requestAnimationFrame to defer the execution and improve performance
		requestAnimationFrame(() => {
			openOffcanvasFromItem(item);
		});
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

	// Load activity log when Activity tab is clicked
	const activityTab = document.querySelector('[data-bs-target="#tab-activity"]');
	if (activityTab)
	{
		activityTab.addEventListener('click', function() {
			loadActivityLog();
		});
	}

	function loadActivityLog()
	{
		const sidebarEl = document.querySelector('.kanban-update-item-sidebar');
		const taskId = sidebarEl ? sidebarEl.getAttribute('data-current-task-id') : null;
		const activityContainer = document.querySelector('#activity-log-container');

		if (!taskId || !activityContainer) return;

		// Show loading spinner
		activityContainer.innerHTML = `
			<div class="text-center py-4">
				<div class="spinner-border spinner-border-sm text-primary" role="status">
					<span class="visually-hidden">Cargando...</span>
				</div>
			</div>
		`;

		// Fetch activities from backend
		fetch(`/task/${taskId}/activities`, {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'X-CSRF-TOKEN': window.kanbanData.csrfToken
			}
		})
		.then(response => response.json())
		.then(activities => {
			if (activities.length === 0)
			{
				activityContainer.innerHTML = `
					<div class="text-center py-4 text-muted">
						<i class="ti ti-info-circle mb-2" style="font-size: 2rem;"></i>
						<p class="mb-0">No hay actividad registrada para esta tarea.</p>
					</div>
				`;
				return;
			}

			// Render activities
			let html = '';
			activities.forEach(activity => {
				const initials = activity.causer ? activity.causer.initials : 'SY';
				const name = activity.causer ? activity.causer.name : 'Sistema';
				const bgColor = activity.causer ? 'bg-label-primary' : 'bg-label-secondary';

				html += `
					<div class="media mb-4 d-flex align-items-start">
						<div class="avatar me-2 flex-shrink-0 mt-1">
							<span class="avatar-initial ${bgColor} rounded-circle">${initials}</span>
						</div>
						<div class="media-body">
							<p class="mb-0">
								<span class="fw-medium">${name}</span>
								${translateActivityDescription(activity.description, activity.properties)}
							</p>
							<small class="text-muted">${activity.created_at}</small>
						</div>
					</div>
				`;
			});

			activityContainer.innerHTML = html;
		})
		.catch(error => {
			console.error('Error loading activities:', error);
			activityContainer.innerHTML = `
				<div class="text-center py-4 text-danger">
					<i class="ti ti-alert-circle mb-2" style="font-size: 2rem;"></i>
					<p class="mb-0">Error al cargar las actividades.</p>
				</div>
			`;
		});
	}

	function translateActivityDescription(description, properties)
	{
		// Translate common activity descriptions to Spanish
		const translations = {
			'created': 'creó la tarea',
			'updated': 'actualizó la tarea',
			'deleted': 'eliminó la tarea'
		};

		return translations[description] || description;
	}
})();

