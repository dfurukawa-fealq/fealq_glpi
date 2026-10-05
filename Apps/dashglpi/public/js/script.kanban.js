// ==================== KANBAN VIEW ====================
// Extraído de script.js pelo PLAN-20260703-013 (Fase 3.3) — carregado logo
// após script.js (e demais módulos) via <script> separado.

const KANBAN_COLUMNS = [
    { id: 'novo',        label: 'Aberto',                 status: 1, color: 'warning'  },
    { id: 'planejado',   label: 'Planejado',              status: 3, color: 'primary'  },
    { id: 'atendimento', label: 'Em Atendimento',         status: 2, color: 'info'     },
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
        const cards = grouped[col.id] || [];
        const colorVar = col.color === 'muted' ? 'var(--text-muted)' : `var(--${col.color})`;
        return `
        <div class="kanban-column">
            <div class="kanban-column-header">
                <span class="kanban-col-title" style="color:${colorVar}">${escHtml(col.label)}</span>
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
    if (Number(t.is_dashglpi_task) === 1 || t.itemtype === 'dashglpi_task') {
        return kanbanTaskCardHtml(t);
    }

    const tech = t.technician_name && t.technician_name !== '-'
        ? escHtml(t.technician_name)
        : '<em style="opacity:.6">Sem técnico</em>';
    // Problema/Manutenção são somente leitura (Decisão 3): sem drag e sem ações.
    const isReadonlyRow = Number(t.readonly) === 1;
    return `
    <div class="kanban-card kanban-glpi-card" data-ticket-id="${t.id}" draggable="${(DASHGLPI_IS_RESTRICTED_VIEW || isReadonlyRow) ? 'false' : 'true'}" data-ticket-detail="${t.id}" data-itemtype="${escHtml(t.itemtype || 'ticket')}">
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
        <div class="kanban-card-meta">
            <span>${escHtml(t.category || 'Sem categoria')}</span>
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
    const owner = t.owner_name || t.technician_name || '-';
    const description = t.description_excerpt || t.content || '';
    return `
    <div class="kanban-card kanban-task-card" data-kanban-task-id="${Number(t.id)}" draggable="true">
        <div class="kanban-card-header">
            <span class="kanban-card-id">Tarefa #${Number(t.id)}</span>
            <span class="kanban-card-sla is-success" title="Tarefa interna DashGLPI">DashGLPI</span>
            <span class="priority-dot priority-${Number(t.priority) || 3}" title="Prioridade ${Number(t.priority) || 3}"></span>
        </div>
        <p class="kanban-card-title">${escHtml(t.name || '')}</p>
        ${description ? `<p class="kanban-task-description">${escHtml(description)}</p>` : ''}
        <div class="kanban-card-meta">
            <span>${escHtml(t.entity_name || 'Entidade raiz')}</span>
            <span>${escHtml(formatDateTime(t.date) || '-')}</span>
        </div>
        <div class="kanban-card-footer">
            <span class="kanban-card-tech"><i class="fas fa-user-check" aria-hidden="true"></i> ${escHtml(owner)}</span>
        </div>
    </div>`;
}

function initKanbanDragDrop() {
    let dragging = null;

    document.querySelectorAll('#ticketsKanban .kanban-card[draggable="true"]').forEach(card => {
        card.addEventListener('dragstart', e => {
            dragging = {
                id: Number(card.dataset.kanbanTaskId || card.dataset.ticketId || 0),
                type: card.dataset.kanbanTaskId ? 'task' : 'ticket',
            };
            card.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
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
                updateKanbanTaskStatus(dragging.id, newStatus);
            } else if (!DASHGLPI_IS_RESTRICTED_VIEW) {
                updateTicketKanbanStatus(dragging.id, newStatus);
            }
        });
    });
}

function initKanbanCardActions() {
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

function openKanbanTaskCreateModal(status) {
    const modal = document.getElementById('kanbanTaskModal');
    const form = document.getElementById('kanbanTaskForm');
    const statusInput = document.getElementById('kanbanTaskStatus');
    const statusLabel = document.getElementById('kanbanTaskStatusLabel');
    const statusMessage = document.getElementById('kanbanTaskFormStatus');
    if (!modal || !form || !statusInput) return;

    const column = KANBAN_COLUMNS.find(col => Number(col.status) === Number(status)) || KANBAN_COLUMNS[0];
    DashState.kanbanTaskCreateStatus = Number(column.status) || 1;
    form.reset();
    statusInput.value = String(DashState.kanbanTaskCreateStatus);
    if (statusLabel) statusLabel.textContent = column.label;
    if (statusMessage) {
        statusMessage.textContent = '';
        statusMessage.className = 'admin-status';
    }
    modal.hidden = false;
    resetKanbanTaskOwner();
    loadKanbanTaskOwnerOptions();
    setTimeout(() => form.elements.name?.focus(), 50);
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
    modal.addEventListener('click', event => {
        if (event.target === modal) closeKanbanTaskCreateModal();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        await createKanbanTask(form);
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
    document.getElementById('kanbanTaskOwnerOptions')?.addEventListener('click', event => {
        const option = event.target.closest('[data-kanban-owner-id]');
        if (!option) return;
        selectKanbanTaskOwner(Number(option.dataset.kanbanOwnerId), option.dataset.kanbanOwnerName || '');
    });
    document.addEventListener('click', event => {
        if (event.target.closest('.kanban-task-owner-field')) return;
        const options = document.getElementById('kanbanTaskOwnerOptions');
        if (options) options.hidden = true;
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
    selectKanbanTaskOwner(current.id, current.name, false);
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

    const selectedId = Number(document.getElementById('kanbanTaskOwnerId')?.value || 0);
    const current = kanbanCurrentUserOwner();
    const optionsById = new Map();
    if (current.id > 0) optionsById.set(current.id, current);
    (DashState.techniciansCacheGlobal || []).forEach(user => {
        const id = Number(user.id || 0);
        if (id > 0) optionsById.set(id, { id, name: user.name || user.label || user.display || `Usuário #${id}` });
    });

    const terms = normalizeKanbanOwnerTerm(input.value).split(/\s+/).filter(Boolean);
    const filtered = Array.from(optionsById.values())
        .filter(user => user.id !== selectedId)
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
    const hidden = document.getElementById('kanbanTaskOwnerId');
    const tag = document.querySelector('[data-kanban-owner-tag]');
    const tagText = tag?.querySelector('span');
    const input = document.getElementById('kanbanTaskOwnerSearch');
    const options = document.getElementById('kanbanTaskOwnerOptions');
    if (hidden) hidden.value = String(Number(userId) || 0);
    if (tagText) tagText.textContent = userName || `Usuário #${Number(userId) || 0}`;
    if (clearSearch && input) input.value = '';
    if (options) options.hidden = true;
}

async function createKanbanTask(form) {
    const status = document.getElementById('kanbanTaskFormStatus');
    const submit = form.querySelector('button[type="submit"]');
    if (status) {
        status.textContent = 'Criando tarefa...';
        status.className = 'admin-status';
    }
    if (submit) submit.disabled = true;

    try {
        const formData = new FormData(form);
        const fields = {
            action: 'create',
            name: formData.get('name') || '',
            content: formData.get('content') || '',
            priority: formData.get('priority') || '3',
            status: formData.get('status') || String(DashState.kanbanTaskCreateStatus || 1),
            owner_users_id: formData.get('owner_users_id') || String(typeof DASHGLPI_CURRENT_USER_ID !== 'undefined' ? DASHGLPI_CURRENT_USER_ID : 0),
        };
        const data = await dashglpiPostForm(`${PLUGIN_ROOT}/ajax/kanban_tasks.php`, fields, 'Erro de conexão ao criar tarefa.');
        if (!data.ok) throw new Error(data.error || 'Não foi possível criar a tarefa.');
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
