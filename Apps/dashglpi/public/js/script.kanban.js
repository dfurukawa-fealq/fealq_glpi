// ==================== KANBAN VIEW ====================
// Extraído de script.js pelo PLAN-20260703-013 (Fase 3.3) — carregado logo
// após script.js (e demais módulos) via <script> separado.

const KANBAN_COLUMNS = [
    { id: 'novo',        label: 'Aberto',                 status: 1, color: 'warning'  },
    { id: 'planejado',   label: 'Planejado',              status: 3, color: 'primary'  },
    { id: 'atendimento', label: 'Em Andamento',           status: 2, color: 'info'     },
    { id: 'pendente',    label: 'Pendente',               status: 4, color: 'danger'   },
    { id: 'solucionado', label: 'Solucionado',            status: 5, color: 'success'  },
    { id: 'fechado',     label: 'Fechado',                status: 6, color: 'muted'    },
];

function kanbanTicketColumn(ticket) {
    // Problema/Manutenção têm status próprios (7-12) mapeados server-side para a
    // coluna equivalente via registry (PLAN-20260709-019 — kanban_status).
    const status = Number(ticket.kanban_status ?? ticket.status);
    const col = KANBAN_COLUMNS.find(c => c.status === status);
    return col ? col.id : null;
}

function isDashglpiKanbanTask(item) {
    return Number(item?.is_dashglpi_task) === 1 || item?.itemtype === 'dashglpi_task';
}

function kanbanPathParts(value) {
    return String(value || '')
        .split('>')
        .map(part => part.trim())
        .filter(Boolean);
}

function kanbanLastPathPart(value, fallback = '-') {
    const parts = kanbanPathParts(value);
    return parts.length ? parts[parts.length - 1] : fallback;
}

function kanbanCategoryChipsHtml(value) {
    const parts = kanbanPathParts(value);
    const chips = parts.length ? parts : ['Sem Categoria'];
    return `<div class="kanban-card-category-chips">${chips.map(part => `<span title="${escHtml(part)}">${escHtml(part)}</span>`).join('')}</div>`;
}

function sortKanbanColumnCards(cards) {
    return [...cards].sort((a, b) => {
        if (isDashglpiKanbanTask(a) && isDashglpiKanbanTask(b)) {
            const aSeq = Number(a.nseq || 0);
            const bSeq = Number(b.nseq || 0);
            if (aSeq !== bSeq) return aSeq - bSeq;
            return Number(a.id || 0) - Number(b.id || 0);
        }
        return 0;
    });
}

function renderKanbanBoard() {
    const board = document.getElementById('ticketsKanban');
    if (!board) return;

    const filtered = [
        ...filteredTicketsForCurrentFilters('ticketsKanbanSearch'),
        ...filteredKanbanTasksForCurrentFilters('ticketsKanbanSearch'),
    ];

    const visibleColumns = (DASHGLPI_IS_RESTRICTED_VIEW && !DASHGLPI_IS_HELPDESK_VIEW)
        ? KANBAN_COLUMNS.filter(c => c.id !== 'fechado')
        : KANBAN_COLUMNS;

    const grouped = {};
    visibleColumns.forEach(col => { grouped[col.id] = []; });
    filtered.forEach(ticket => {
        const colId = kanbanTicketColumn(ticket);
        if (colId && grouped[colId]) grouped[colId].push(ticket);
    });

    board.innerHTML = `<div class="kanban-board">${visibleColumns.map(col => {
        const cards = sortKanbanColumnCards(grouped[col.id] || []);
        return `
        <div class="kanban-column">
            <div class="kanban-column-header">
                <span class="kanban-col-title kanban-col-title-${Number(col.status)}">${escHtml(col.label)}</span>
                <div class="kanban-col-actions">
                    <span class="kanban-col-count">${cards.length}</span>
                    <button type="button" class="kanban-add-task-btn" data-kanban-add-status="${col.status}" title="Nova tarefa em ${escHtml(col.label)}" aria-label="Nova tarefa em ${escHtml(col.label)}">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="kanban-swimlane" id="kanban-col-${col.id}" data-status="${col.status}" data-droppable="true">
                ${cards.map(t => kanbanCardHtml(t, col)).join('')}
                ${cards.length === 0 ? '<div class="kanban-empty">Vazio</div>' : ''}
            </div>
        </div>`;
    }).join('')}</div>`;

    initKanbanDragDrop();
    initKanbanCardActions();
}

function kanbanCardHtml(t, col) {
    if (isDashglpiKanbanTask(t)) {
        return kanbanTaskCardHtml(t);
    }

    const statusClass = `kanban-status-${Number(col?.status || t.kanban_status || t.status || 0)}`;
    const tech = t.technician_name && t.technician_name !== '-'
        ? escHtml(t.technician_name)
        : '<em style="opacity:.6">Sem técnico</em>';
    const entityFullLabel = t.entity_name || 'Entidade raiz';
    const entityDisplayLabel = kanbanLastPathPart(entityFullLabel, 'Entidade raiz');
    const categoryChips = kanbanCategoryChipsHtml(t.category || '');
    // Problema/Manutenção são somente leitura (Decisão 3): sem drag e sem ações.
    const isReadonlyRow = Number(t.readonly) === 1;
    return `
    <div class="kanban-card kanban-glpi-card ${statusClass}" data-ticket-id="${t.id}" draggable="${(DASHGLPI_IS_RESTRICTED_VIEW || isReadonlyRow) ? 'false' : 'true'}" data-ticket-detail="${t.id}" data-itemtype="${escHtml(t.itemtype || 'ticket')}">
        <div class="kanban-card-header">
            <span class="kanban-card-id">#${t.id}</span>
            ${isReadonlyRow && t.status_label ? `<span class="kanban-card-sla" title="Status">${escHtml(t.status_label)}</span>` : ''}
            ${(!DASHGLPI_IS_RESTRICTED_VIEW && t.active_sla_kind) ? `<span class="kanban-card-sla is-${healthRiskBadgeClass(t.sla_status)}" title="SLA ${escHtml(t.active_sla_kind)}">
                <i class="fas fa-clock"></i> ${escHtml(t.active_sla_kind)} - ${formatSlaRemainingHhMm(t.seconds_left)} / ${formatSlaPercent(t.active_sla_percent)}
            </span>` : ''}
            ${Number(t.notification_failed) === 1 ? '<i class="fas fa-triangle-exclamation notification-failure-icon" title="Falha no envio da notificação"></i>' : ''}
            <span class="priority-dot priority-${t.priority}" title="Prioridade ${t.priority}"></span>
        </div>
        <p class="kanban-card-title">${escHtml(t.name || '')}</p>
        ${categoryChips}
        <div class="kanban-card-meta">
            <span title="${escHtml(entityFullLabel)}">${escHtml(entityDisplayLabel)}</span>
            <span>${escHtml(formatDateTime(t.date) || '-')}</span>
        </div>
        <div class="kanban-card-footer">
            <span class="kanban-card-tech">${tech}</span>
            ${isReadonlyRow ? '' : (DASHGLPI_IS_HELPDESK_VIEW ? `
            <div class="kanban-card-actions">
                ${renderSelfServiceActions(t)}
            </div>` : (DASHGLPI_IS_RESTRICTED_VIEW ? '' : `
            <div class="kanban-card-actions">
                <button data-take-ticket="${t.id}" title="Assumir chamado"><i class="fas fa-user-check"></i></button>
                <button data-assign-ticket="${t.id}" title="Atribuir técnico"><i class="fas fa-user-plus"></i></button>
            </div>`))}
        </div>
    </div>`;
}

function kanbanTaskCardHtml(t) {
    const owner = kanbanTaskOwnerLabel(t);
    const nseq = Number(t.nseq || 0);
    const statusClass = `kanban-status-${Number(t.kanban_status || t.status || 1)}`;
    const entityFullLabel = t.entity_name || 'Entidade raiz';
    const entityDisplayLabel = kanbanLastPathPart(entityFullLabel, 'Entidade raiz');
    const categoryChips = kanbanCategoryChipsHtml(t.category || t.category_name || '');
    return `
    <div class="kanban-card kanban-task-card ${statusClass}" data-kanban-task-id="${Number(t.id)}" data-kanban-task-detail="${Number(t.id)}" data-kanban-nseq="${nseq}" draggable="true" tabindex="0" role="button" aria-label="Editar tarefa #${Number(t.id)}">
        <div class="kanban-card-header">
            <span class="kanban-card-id">Tarefa #${Number(t.id)}</span>
            <span class="kanban-card-seq" title="Sequência na lista">Seq ${nseq}</span>
            <span class="kanban-card-sla is-success" title="Tarefa interna DashGLPI">DashGLPI</span>
            ${Number(t.is_private || 0) === 1 ? '<span class="kanban-card-private" title="Tarefa privada"><i class="fas fa-lock" aria-hidden="true"></i></span>' : ''}
            <span class="priority-dot priority-${Number(t.priority) || 3}" title="Prioridade ${Number(t.priority) || 3}"></span>
        </div>
        <p class="kanban-card-title">${escHtml(t.name || '')}</p>
        ${categoryChips}
        <div class="kanban-card-meta">
            <span title="${escHtml(entityFullLabel)}">${escHtml(entityDisplayLabel)}</span>
            <span>${escHtml(formatDateTime(t.date) || '-')}</span>
        </div>
        <div class="kanban-card-footer">
            <span class="kanban-card-tech"><i class="fas fa-user-check" aria-hidden="true"></i> ${escHtml(owner)}</span>
        </div>
    </div>`;
}

function kanbanTaskOwnerLabel(task) {
    const owners = Array.isArray(task?.owners) ? task.owners : [];
    const names = owners
        .map(owner => String(owner?.name || '').trim())
        .filter(Boolean);
    if (!names.length) return task.owner_name || task.technician_name || '-';
    if (names.length <= 2) return names.join(', ');
    return `${names.slice(0, 2).join(', ')} +${names.length - 2}`;
}

function initKanbanDragDrop() {
    let dragging = null;

    document.querySelectorAll('#ticketsKanban .kanban-card[draggable="true"]').forEach(card => {
        card.addEventListener('dragstart', e => {
            dragging = {
                id: Number(card.dataset.kanbanTaskId || card.dataset.ticketId || 0),
                type: card.dataset.kanbanTaskId ? 'task' : 'ticket',
            };
            if (dragging.type === 'task') card.dataset.kanbanJustDragged = '1';
            card.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            if (card.dataset.kanbanJustDragged === '1') {
                setTimeout(() => { delete card.dataset.kanbanJustDragged; }, 0);
            }
            dragging = null;
        });
    });

    document.querySelectorAll('#ticketsKanban .kanban-swimlane[data-droppable="true"]').forEach(lane => {
        lane.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            lane.classList.add('drag-over');
        });
        lane.addEventListener('dragleave', () => lane.classList.remove('drag-over'));
        lane.addEventListener('drop', e => {
            e.preventDefault();
            lane.classList.remove('drag-over');
            if (!dragging || !dragging.id) return;
            const newStatus = Number(lane.dataset.status);
            if (!newStatus) return;
            if (dragging.type === 'task') {
                reorderKanbanTask(dragging.id, newStatus, kanbanTaskOrderForDrop(lane, dragging.id, e.clientY));
            } else if (!DASHGLPI_IS_RESTRICTED_VIEW) {
                updateTicketKanbanStatus(dragging.id, newStatus);
            }
        });
    });
}

function kanbanTaskOrderForDrop(lane, movedTaskId, clientY) {
    const taskCards = [...lane.querySelectorAll('.kanban-task-card[data-kanban-task-id]:not(.is-dragging)')];
    const afterElement = taskCards.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = clientY - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset, element: child };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;

    const ids = taskCards
        .map(card => Number(card.dataset.kanbanTaskId || 0))
        .filter(id => id > 0 && id !== Number(movedTaskId));
    const insertAt = afterElement
        ? ids.indexOf(Number(afterElement.dataset.kanbanTaskId || 0))
        : ids.length;
    ids.splice(insertAt >= 0 ? insertAt : ids.length, 0, Number(movedTaskId));

    return ids;
}

function initKanbanCardActions() {
    document.querySelectorAll('#ticketsKanban [data-kanban-task-detail]').forEach(card => {
        card.addEventListener('click', event => {
            if (event.target.closest('button, a')) return;
            if (card.dataset.kanbanJustDragged === '1') return;
            openKanbanTaskEditModal(Number(card.dataset.kanbanTaskDetail || 0));
        });
        card.addEventListener('keydown', event => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            openKanbanTaskEditModal(Number(card.dataset.kanbanTaskDetail || 0));
        });
    });

    if (!DASHGLPI_IS_RESTRICTED_VIEW) {
        document.querySelectorAll('#ticketsKanban [data-take-ticket]').forEach(btn => {
            btn.addEventListener('click', () => takeKanbanTicket(Number(btn.dataset.takeTicket)));
        });

        document.querySelectorAll('#ticketsKanban [data-assign-ticket]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                openTicketDetailModal(Number(btn.dataset.assignTicket), { focus: 'actors', trigger: btn });
            });
        });
    }

    document.querySelectorAll('#ticketsKanban [data-kanban-add-status]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            openKanbanTaskCreateModal(Number(btn.dataset.kanbanAddStatus) || 1);
        });
    });
}

function setKanbanTaskModalMode(mode, status = 1) {
    const title = document.getElementById('kanbanTaskModalTitle');
    const submitText = document.getElementById('kanbanTaskSubmitText');
    const taskId = document.getElementById('kanbanTaskId');
    const statusInput = document.getElementById('kanbanTaskStatus');
    const statusLabel = document.getElementById('kanbanTaskStatusLabel');
    const column = KANBAN_COLUMNS.find(col => Number(col.status) === Number(status)) || KANBAN_COLUMNS[0];

    DashState.kanbanTaskModalMode = mode === 'edit' ? 'edit' : 'create';
    DashState.kanbanTaskCreateStatus = Number(column.status) || 1;
    if (title) title.textContent = mode === 'edit' ? 'Editar Tarefa' : 'Nova Tarefa';
    if (submitText) submitText.textContent = mode === 'edit' ? 'Salvar Tarefa' : 'Criar Tarefa';
    if (taskId && mode !== 'edit') taskId.value = '';
    if (statusInput) statusInput.value = String(DashState.kanbanTaskCreateStatus);
    if (statusLabel) statusLabel.textContent = column.label;
}

function openKanbanTaskCreateModal(status) {
    const modal = document.getElementById('kanbanTaskModal');
    const form = document.getElementById('kanbanTaskForm');
    const statusMessage = document.getElementById('kanbanTaskFormStatus');
    if (!modal || !form) return;

    form.reset();
    setKanbanTaskModalMode('create', status);
    if (statusMessage) {
        statusMessage.textContent = '';
        statusMessage.className = 'admin-status';
    }
    modal.hidden = false;
    resetKanbanTaskOwner();
    loadKanbanTaskEntityOptions();
    loadKanbanTaskOwnerOptions();
    setTimeout(() => form.elements.name?.focus(), 50);
}

async function openKanbanTaskEditModal(taskId) {
    const modal = document.getElementById('kanbanTaskModal');
    const form = document.getElementById('kanbanTaskForm');
    const statusMessage = document.getElementById('kanbanTaskFormStatus');
    if (!modal || !form || !taskId) return;

    form.reset();
    setKanbanTaskModalMode('edit', 1);
    document.getElementById('kanbanTaskId').value = String(taskId);
    if (statusMessage) {
        statusMessage.textContent = 'Carregando tarefa...';
        statusMessage.className = 'admin-status';
    }
    modal.hidden = false;
    resetKanbanTaskOwner();
    await Promise.all([
        loadKanbanTaskOwnerOptions(),
        loadKanbanTaskEntityOptions(),
    ]);

    try {
        const data = await dashglpiPostForm(`${PLUGIN_ROOT}/ajax/kanban_tasks.php`, {
            action: 'detail',
            task_id: String(taskId),
        }, 'Erro de conexão ao consultar tarefa.');
        if (!data.ok) throw new Error(data.error || 'Não foi possível consultar a tarefa.');
        fillKanbanTaskForm(data.task || {});
        if (statusMessage) {
            statusMessage.textContent = '';
            statusMessage.className = 'admin-status';
        }
        setTimeout(() => form.elements.name?.focus(), 50);
    } catch (error) {
        if (statusMessage) {
            statusMessage.textContent = error.message || 'Erro ao consultar tarefa.';
            statusMessage.className = 'admin-status error';
        } else {
            alert(error.message);
        }
    }
}

function fillKanbanTaskForm(task) {
    const form = document.getElementById('kanbanTaskForm');
    if (!form) return;
    setKanbanTaskModalMode('edit', Number(task.status || task.kanban_status || 1));
    document.getElementById('kanbanTaskId').value = String(Number(task.id) || '');
    form.elements.name.value = task.name || '';
    form.elements.content.value = task.content || '';
    form.elements.priority.value = String(Number(task.priority) || 3);
    form.elements.status.value = String(Number(task.status || task.kanban_status || 1));
    if (form.elements.is_private) form.elements.is_private.checked = Number(task.is_private || 0) === 1;
    setKanbanTaskEntity(Number(task.entities_id || 0));
    setKanbanTaskOwners(
        Array.isArray(task.owners) && task.owners.length
            ? task.owners
            : [{ id: Number(task.owner_users_id || 0), name: task.owner_name || task.technician_name || '-' }],
        false
    );
}

async function loadKanbanTaskEntityOptions() {
    const hidden = document.getElementById('kanbanTaskEntity');
    if (!hidden) return;

    if (Array.isArray(DashState.kanbanTaskEntities) && DashState.kanbanTaskEntities.length) {
        selectKanbanTaskEntity(Number(hidden.value || DashState.kanbanTaskDefaultEntityId || 0), false);
        return;
    }

    try {
        const params = new URLSearchParams({ action: 'catalog' });
        const response = await fetch(`${PLUGIN_ROOT}/ajax/ticket_create.php?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data?.ok) throw new Error(data?.error || 'Erro ao carregar entidades.');
        const catalog = data.catalog || {};
        DashState.kanbanTaskEntities = Array.isArray(catalog.entities) ? catalog.entities : [];
        DashState.kanbanTaskDefaultEntityId = Number(catalog.selected_entity_id ?? catalog.default_entity_id ?? 0);
        selectKanbanTaskEntity(Number(hidden.value || DashState.kanbanTaskDefaultEntityId || 0), false);
    } catch (error) {
        console.error('Error loading task entities:', error);
        DashState.kanbanTaskEntities = [{ id: 0, label: 'Entidade raiz' }];
        selectKanbanTaskEntity(0, false);
    }
}

function kanbanTaskEntityLabel(entity) {
    return String(entity?.label || entity?.completename || entity?.name || 'Entidade');
}

function normalizeKanbanEntityTerm(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function kanbanTaskEntityOption(entityId) {
    const entities = Array.isArray(DashState.kanbanTaskEntities) && DashState.kanbanTaskEntities.length
        ? DashState.kanbanTaskEntities
        : [{ id: 0, label: 'Entidade raiz' }];
    return entities.find(entity => Number(entity?.id || 0) === Number(entityId))
        || entities.find(entity => Number(entity?.id || 0) === Number(DashState.kanbanTaskDefaultEntityId || 0))
        || entities[0]
        || { id: 0, label: 'Entidade raiz' };
}

function renderKanbanTaskEntityOptions(query = '', open = false) {
    const box = document.getElementById('kanbanTaskEntityOptions');
    const input = document.getElementById('kanbanTaskEntitySearch');
    const hidden = document.getElementById('kanbanTaskEntity');
    if (!box || !input || !hidden) return;

    const terms = normalizeKanbanEntityTerm(query).split(/\s+/).filter(Boolean);
    const entities = Array.isArray(DashState.kanbanTaskEntities) ? DashState.kanbanTaskEntities : [];
    const filtered = entities
        .filter(entity => {
            if (!terms.length) return true;
            const haystack = normalizeKanbanEntityTerm([
                entity?.label,
                entity?.completename,
                entity?.name,
            ].join(' '));
            return terms.every(term => haystack.includes(term));
        })
        .slice(0, 60);

    if (!filtered.length) {
        box.innerHTML = '<div class="ticket-create-combobox-empty">Nenhum resultado encontrado.</div>';
    } else {
        const selectedValue = String(hidden.value || '0');
        box.innerHTML = filtered.map(entity => {
            const value = String(Number(entity.id || 0));
            const active = value === selectedValue ? ' is-selected' : '';
            return `<button type="button" class="ticket-create-combobox-option${active}" role="option" data-kanban-entity-id="${escHtml(value)}">${escHtml(kanbanTaskEntityLabel(entity))}</button>`;
        }).join('');
    }

    box.hidden = !open;
    input.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function selectKanbanTaskEntity(entityId, close = true) {
    const hidden = document.getElementById('kanbanTaskEntity');
    const input = document.getElementById('kanbanTaskEntitySearch');
    if (!hidden || !input) return;
    const option = kanbanTaskEntityOption(entityId);
    hidden.value = String(Number(option?.id || 0));
    input.value = kanbanTaskEntityLabel(option);
    renderKanbanTaskEntityOptions(input.value, false);
    if (close) closeKanbanTaskEntityOptions();
}

function closeKanbanTaskEntityOptions() {
    const box = document.getElementById('kanbanTaskEntityOptions');
    const input = document.getElementById('kanbanTaskEntitySearch');
    if (box) box.hidden = true;
    if (input) input.setAttribute('aria-expanded', 'false');
}

function commitKanbanTaskEntityText() {
    const input = document.getElementById('kanbanTaskEntitySearch');
    if (!input) return;
    const typed = normalizeKanbanEntityTerm(input.value || '');
    const exact = (DashState.kanbanTaskEntities || []).find(entity => normalizeKanbanEntityTerm(kanbanTaskEntityLabel(entity)) === typed);
    if (exact) {
        selectKanbanTaskEntity(Number(exact.id || 0), false);
    }
}

function setKanbanTaskEntity(entityId) {
    selectKanbanTaskEntity(Number(entityId) || 0, false);
}

function closeKanbanTaskCreateModal() {
    const modal = document.getElementById('kanbanTaskModal');
    if (modal) modal.hidden = true;
}

function initKanbanTaskModal() {
    const modal = document.getElementById('kanbanTaskModal');
    const form = document.getElementById('kanbanTaskForm');
    if (!modal || !form || modal.dataset.bound === '1') return;
    modal.dataset.bound = '1';

    modal.querySelectorAll('[data-kanban-task-close]').forEach(button => {
        button.addEventListener('click', closeKanbanTaskCreateModal);
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        await saveKanbanTask(form);
    });

    const ownerSearch = document.getElementById('kanbanTaskOwnerSearch');
    ownerSearch?.addEventListener('input', () => renderKanbanTaskOwnerOptions(true));
    ownerSearch?.addEventListener('focus', () => renderKanbanTaskOwnerOptions(true));
    ownerSearch?.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        const first = document.querySelector('#kanbanTaskOwnerOptions [data-kanban-owner-id]');
        if (!first) return;
        event.preventDefault();
        selectKanbanTaskOwner(Number(first.dataset.kanbanOwnerId), first.dataset.kanbanOwnerName || '');
    });
    document.getElementById('kanbanTaskOwnerTags')?.addEventListener('click', event => {
        const button = event.target.closest('[data-kanban-owner-remove]');
        if (!button) return;
        event.preventDefault();
        removeKanbanTaskOwner(Number(button.dataset.kanbanOwnerRemove || 0));
    });
    document.getElementById('kanbanTaskOwnerOptions')?.addEventListener('click', event => {
        const option = event.target.closest('[data-kanban-owner-id]');
        if (!option) return;
        selectKanbanTaskOwner(Number(option.dataset.kanbanOwnerId), option.dataset.kanbanOwnerName || '');
    });

    const entityInput = document.getElementById('kanbanTaskEntitySearch');
    const entityOptions = document.getElementById('kanbanTaskEntityOptions');
    entityInput?.addEventListener('focus', () => renderKanbanTaskEntityOptions(entityInput.value || '', true));
    entityInput?.addEventListener('input', () => renderKanbanTaskEntityOptions(entityInput.value || '', true));
    entityInput?.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeKanbanTaskEntityOptions();
            return;
        }
        if (event.key !== 'Enter') return;
        const first = entityOptions?.querySelector('[data-kanban-entity-id]');
        if (!first) return;
        event.preventDefault();
        selectKanbanTaskEntity(Number(first.dataset.kanbanEntityId || 0), true);
    });
    entityOptions?.addEventListener('mousedown', event => {
        const option = event.target.closest('[data-kanban-entity-id]');
        if (!option) return;
        event.preventDefault();
        selectKanbanTaskEntity(Number(option.dataset.kanbanEntityId || 0), true);
    });
    document.getElementById('kanbanTaskEntityToggle')?.addEventListener('click', () => renderKanbanTaskEntityOptions('', true));

    document.addEventListener('click', event => {
        if (event.target.closest('.kanban-task-owner-field')) return;
        const options = document.getElementById('kanbanTaskOwnerOptions');
        if (options) options.hidden = true;
    });
    document.addEventListener('click', event => {
        if (event.target.closest('[data-kanban-task-entity-combobox]')) return;
        closeKanbanTaskEntityOptions();
    });
}

function normalizeKanbanOwnerTerm(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function kanbanCurrentUserOwner() {
    return {
        id: Number(typeof DASHGLPI_CURRENT_USER_ID !== 'undefined' ? DASHGLPI_CURRENT_USER_ID : 0),
        name: String(typeof DASHGLPI_CURRENT_USER_DISPLAY !== 'undefined' ? DASHGLPI_CURRENT_USER_DISPLAY : 'usuário atual'),
    };
}

function resetKanbanTaskOwner() {
    const current = kanbanCurrentUserOwner();
    setKanbanTaskOwners([current], false);
}

async function loadKanbanTaskOwnerOptions() {
    if (Array.isArray(DashState.techniciansCacheGlobal) && DashState.techniciansCacheGlobal.length) {
        renderKanbanTaskOwnerOptions(false);
        return;
    }

    try {
        const response = await fetch(`${dashboardDataUrl('technicians_list')}`, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(data?.error || 'Erro ao carregar responsáveis.');
        DashState.techniciansCacheGlobal = Array.isArray(data) ? data : [];
        renderKanbanTaskOwnerOptions(false);
    } catch (error) {
        console.error('Error loading task owners:', error);
        DashState.techniciansCacheGlobal = [kanbanCurrentUserOwner()];
    }
}

function renderKanbanTaskOwnerOptions(open = true) {
    const input = document.getElementById('kanbanTaskOwnerSearch');
    const box = document.getElementById('kanbanTaskOwnerOptions');
    if (!input || !box) return;

    const selectedIds = new Set(kanbanSelectedTaskOwners().map(owner => Number(owner.id || 0)));
    const current = kanbanCurrentUserOwner();
    const optionsById = new Map();
    if (current.id > 0) optionsById.set(current.id, current);
    (DashState.techniciansCacheGlobal || []).forEach(user => {
        const id = Number(user.id || 0);
        if (id > 0) optionsById.set(id, { id, name: user.name || user.label || user.display || `Usuário #${id}` });
    });

    const terms = normalizeKanbanOwnerTerm(input.value).split(/\s+/).filter(Boolean);
    const filtered = Array.from(optionsById.values())
        .filter(user => !selectedIds.has(user.id))
        .filter(user => {
            if (!terms.length) return true;
            const haystack = normalizeKanbanOwnerTerm(user.name);
            return terms.every(term => haystack.includes(term));
        })
        .slice(0, 50);

    box.innerHTML = filtered.length
        ? filtered.map(user => `<button type="button" data-kanban-owner-id="${Number(user.id)}" data-kanban-owner-name="${escHtml(user.name)}"><i class="fas fa-user" aria-hidden="true"></i><span>${escHtml(user.name)}</span></button>`).join('')
        : '<div class="attendance-actor-empty">Nenhum responsável encontrado.</div>';
    box.hidden = !open;
}

function selectKanbanTaskOwner(userId, userName, clearSearch = true) {
    const selected = kanbanSelectedTaskOwners();
    const id = Number(userId) || 0;
    if (id > 0 && !selected.some(owner => Number(owner.id) === id)) {
        selected.push({ id, name: userName || `Usuário #${id}` });
    }
    setKanbanTaskOwners(selected, clearSearch);
}

function removeKanbanTaskOwner(userId) {
    const selected = kanbanSelectedTaskOwners().filter(owner => Number(owner.id) !== Number(userId));
    setKanbanTaskOwners(selected.length ? selected : [kanbanCurrentUserOwner()]);
}

function kanbanSelectedTaskOwners() {
    if (!Array.isArray(DashState.kanbanTaskSelectedOwners)) {
        DashState.kanbanTaskSelectedOwners = [];
    }
    return DashState.kanbanTaskSelectedOwners;
}

function setKanbanTaskOwners(owners, clearSearch = true) {
    const current = kanbanCurrentUserOwner();
    const normalized = [];
    (Array.isArray(owners) ? owners : []).forEach(owner => {
        const id = Number(owner?.id || owner?.users_id || 0);
        if (id <= 0 || normalized.some(item => item.id === id)) return;
        normalized.push({ id, name: String(owner?.name || owner?.label || owner?.display || `Usuário #${id}`) });
    });
    if (!normalized.length && current.id > 0) {
        normalized.push(current);
    }

    DashState.kanbanTaskSelectedOwners = normalized;
    const primary = normalized[0] || { id: 0, name: '' };
    const hidden = document.getElementById('kanbanTaskOwnerId');
    const hiddenList = document.getElementById('kanbanTaskOwnerIds');
    const tags = document.getElementById('kanbanTaskOwnerTags');
    const input = document.getElementById('kanbanTaskOwnerSearch');
    const options = document.getElementById('kanbanTaskOwnerOptions');
    if (hidden) hidden.value = String(Number(primary.id) || 0);
    if (hiddenList) hiddenList.value = normalized.map(owner => Number(owner.id)).join(',');
    if (tags) {
        tags.querySelectorAll('[data-kanban-owner-tag]').forEach(tag => tag.remove());
        normalized.forEach(owner => {
            const tag = document.createElement('span');
            tag.className = 'attendance-actor-tag';
            tag.dataset.kanbanOwnerTag = '1';
            tag.innerHTML = `<i class="fas fa-user" aria-hidden="true"></i><span></span>${
                normalized.length > 1
                    ? `<button type="button" data-kanban-owner-remove="${Number(owner.id)}" aria-label="Remover responsável">&times;</button>`
                    : ''
            }`;
            const label = tag.querySelector('span');
            if (label) label.textContent = owner.name || `Usuário #${Number(owner.id) || 0}`;
            tags.insertBefore(tag, input || null);
        });
    }
    if (clearSearch && input) input.value = '';
    if (options) options.hidden = true;
    renderKanbanTaskOwnerOptions(false);
}

async function saveKanbanTask(form) {
    const status = document.getElementById('kanbanTaskFormStatus');
    const submit = form.querySelector('button[type="submit"]');
    const mode = DashState.kanbanTaskModalMode === 'edit' ? 'edit' : 'create';
    const taskId = Number(document.getElementById('kanbanTaskId')?.value || 0);
    if (status) {
        status.textContent = mode === 'edit' ? 'Salvando tarefa...' : 'Criando tarefa...';
        status.className = 'admin-status';
    }
    if (submit) submit.disabled = true;

    try {
        commitKanbanTaskEntityText();
        const formData = new FormData(form);
        const fields = {
            action: mode === 'edit' ? 'update' : 'create',
            task_id: String(taskId || ''),
            name: formData.get('name') || '',
            content: formData.get('content') || '',
            entities_id: formData.get('entities_id') || '0',
            is_private: form.elements.is_private?.checked ? '1' : '0',
            priority: formData.get('priority') || '3',
            status: formData.get('status') || String(DashState.kanbanTaskCreateStatus || 1),
            owner_users_id: formData.get('owner_users_id') || String(typeof DASHGLPI_CURRENT_USER_ID !== 'undefined' ? DASHGLPI_CURRENT_USER_ID : 0),
            owner_user_ids: JSON.stringify(kanbanSelectedTaskOwners().map(owner => Number(owner.id)).filter(id => id > 0)),
        };
        if (mode === 'edit' && taskId <= 0) throw new Error('Tarefa inválida.');
        const data = await dashglpiPostForm(
            `${PLUGIN_ROOT}/ajax/kanban_tasks.php`,
            fields,
            mode === 'edit' ? 'Erro de conexão ao salvar tarefa.' : 'Erro de conexão ao criar tarefa.'
        );
        if (!data.ok) throw new Error(data.error || (mode === 'edit' ? 'Não foi possível salvar a tarefa.' : 'Não foi possível criar a tarefa.'));
        closeKanbanTaskCreateModal();
        await loadKanbanTasks();
        renderKanbanBoard();
    } catch (error) {
        if (status) {
            status.textContent = error.message;
            status.className = 'admin-status error';
        } else {
            alert(error.message);
        }
    } finally {
        if (submit) submit.disabled = false;
    }
}

async function updateKanbanTaskStatus(taskId, newStatus) {
    try {
        const data = await dashglpiPostForm(`${PLUGIN_ROOT}/ajax/kanban_tasks.php`, {
            action: 'update_status',
            task_id: String(taskId),
            status: String(newStatus),
        }, 'Erro de conexão ao mover tarefa.');
        if (!data.ok) throw new Error(data.error || 'Não foi possível mover a tarefa.');
        await loadKanbanTasks();
        renderKanbanBoard();
    } catch (error) {
        await loadKanbanTasks();
        renderKanbanBoard();
        alert(error.message);
    }
}

async function reorderKanbanTask(taskId, newStatus, orderedIds) {
    try {
        const data = await dashglpiPostForm(`${PLUGIN_ROOT}/ajax/kanban_tasks.php`, {
            action: 'reorder',
            task_id: String(taskId),
            status: String(newStatus),
            ordered_ids: JSON.stringify(orderedIds || []),
        }, 'Erro de conexão ao reordenar tarefa.');
        if (!data.ok) throw new Error(data.error || 'Não foi possível reordenar a tarefa.');
        await loadKanbanTasks();
        renderKanbanBoard();
    } catch (error) {
        await loadKanbanTasks();
        renderKanbanBoard();
        alert(error.message);
    }
}

document.addEventListener('DOMContentLoaded', initKanbanTaskModal);

async function updateTicketKanbanStatus(ticketId, newStatus) {
    if ([4, 5, 6].includes(newStatus)) {
        await openTicketDetailModal(ticketId, { attendanceStatus: newStatus });
        return;
    }
    try {
        const data = await dashglpiAttendanceQuickUpdate(ticketId, {
            action: 'update_status',
            status: newStatus,
        });
        if (data.ok) {
            await loadTicketLists();
        } else {
            // Ressincroniza o board: se o chamado não existe mais (card obsoleto vindo de
            // cache de borda em aba antiga), recarregar faz o card fantasma sumir.
            await loadTicketLists();
            alert('Erro ao mover chamado: ' + (data.error || 'Desconhecido') + '\nA lista foi atualizada.');
        }
    } catch {
        alert('Erro de conexão ao mover chamado.');
    }
}

async function takeKanbanTicket(ticketId) {
    const userId = (typeof DASHGLPI_CURRENT_USER_ID !== 'undefined') ? DASHGLPI_CURRENT_USER_ID : 0;
    if (!userId) { alert('Usuário não identificado.'); return; }
    await assignKanbanTicket(ticketId, userId, true);
}

async function assignKanbanTicket(ticketId, userId, take = false) {
    try {
        const data = await dashglpiAttendanceQuickUpdate(ticketId, {
            action: take ? 'take' : 'assign',
            user_id: userId,
        });
        if (data.ok) {
            await loadTicketLists();
        } else {
            // Mesmo tratamento de card obsoleto do updateTicketKanbanStatus (mesmo endpoint/risco).
            await loadTicketLists();
            alert('Erro ao atribuir técnico: ' + (data.error || 'Desconhecido') + '\nA lista foi atualizada.');
        }
    } catch {
        alert('Erro de conexão ao atribuir técnico.');
    }
}
