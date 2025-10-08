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
			if (task.category)
			{
				html += `<div class="item-badges">`;
				html += `<div class="badge rounded-pill bg-label-warning category-badge">${task.category.name}</div>`;
				html += `</div>`;
			}
			html += renderStartTimer();
			html += `</div>`;
			html += `<span class="kanban-text">${task.title}</span>`;

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
                        description: task.description || ''
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

		// Populate select2 with categories if present and pre-select current category
		const labelSelect = sidebarEl.querySelector('#label');
		const currentCategoryId = taskDiv ? taskDiv.getAttribute('data-category-id') : null;
		if (labelSelect && window.$ && $.fn.select2)
		{
			$(labelSelect).empty();
			$(labelSelect).append(new Option('Selecciona una categoría', '', false, false));
			(categories || []).forEach(c => {
				const isSelected = currentCategoryId && parseInt(currentCategoryId) === c.id;
				const opt = new Option(c.name, c.id, isSelected, isSelected);
				$(labelSelect).append(opt);
			});
			$(labelSelect).select2({
				dropdownParent: $(sidebarEl),
				placeholder: 'Selecciona una categoría',
				allowClear: true
			});
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
				allowClear: false
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

		// Handle image preview for attachments
		const attachmentInput = sidebarEl.querySelector('#attachments');
		const previewContainer = sidebarEl.querySelector('#attachment-preview');
		const previewImage = sidebarEl.querySelector('#preview-image');
		const removeAttachmentBtn = sidebarEl.querySelector('#remove-attachment');

		if (attachmentInput && previewContainer && previewImage)
		{
			// Show preview when file is selected
			attachmentInput.addEventListener('change', function(e) {
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

			// Remove preview and clear input
			if (removeAttachmentBtn) {
				removeAttachmentBtn.addEventListener('click', function() {
					attachmentInput.value = '';
					previewImage.src = '';
					previewContainer.style.display = 'none';
				});
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

			console.log('[Kanban] Saving task...', { taskId, newTitle, newDue, newDescription, newEstimatedHours, categoryId, responsibleId, statusId, boardId, projectId });

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
					description: newDescription,
					responsible_id: responsibleId,
					estimated_hours: newEstimatedHours,
					start_date: newDue,
					due_date: newDue,
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

					// Update data attributes
					if (newDue) taskDiv.setAttribute('data-due-date', newDue);
					if (newDescription) taskDiv.setAttribute('data-description', newDescription.replace(/"/g, '&quot;'));
					if (newEstimatedHours) taskDiv.setAttribute('data-estimated-hours', newEstimatedHours);
					if (categoryId) taskDiv.setAttribute('data-category-id', categoryId);
					if (responsibleId) taskDiv.setAttribute('data-responsible-id', responsibleId);

				// Update category badge if changed
				if (categoryId)
				{
					let itemBadges = taskDiv.querySelector('.item-badges');
					const categoryBadge = itemBadges ? itemBadges.querySelector('.category-badge') : null;
					const selectedOption = categorySelect ? categorySelect.options[categorySelect.selectedIndex] : null;

					if (categoryBadge && selectedOption && selectedOption.text)
					{
						categoryBadge.textContent = selectedOption.text;
					}
					else if (!categoryBadge && selectedOption && selectedOption.text)
					{
						// Add category badge if it didn't exist
						const topRow = taskDiv.querySelector('.d-flex.justify-content-between.flex-wrap.align-items-center.mb-2.pb-1');
						if (topRow)
						{
							if (!itemBadges)
							{
								itemBadges = document.createElement('div');
								itemBadges.className = 'item-badges';
								topRow.insertBefore(itemBadges, topRow.firstChild);
							}
							const newCategoryBadge = document.createElement('div');
							newCategoryBadge.className = 'badge rounded-pill bg-label-warning category-badge';
							newCategoryBadge.textContent = selectedOption.text;
							itemBadges.appendChild(newCategoryBadge);
						}
					}
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

