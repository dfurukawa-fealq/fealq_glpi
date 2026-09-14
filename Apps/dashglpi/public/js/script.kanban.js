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

    const search = (document.getElementById('ticketsSearchInput')?.value || '').trim().toLowerCase();
    const filtered = DashState.ticketsDataGlobal.filter(ticket => {
        if (!search) return true;
        return [ticket.id, ticket.name, ticket.category, ticket.technician_name, ticket.requester_name, ticket.stage]
            .some(v => String(v || '').toLowerCase().includes(search));
    });

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
                <span class="kanban-col-count">${cards.length}</span>
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
    const tech = t.technician_name && t.technician_name !== '-'
        ? escHtml(t.technician_name)
        : '<em style="opacity:.6">Sem técnico</em>';
    // Problema/Manutenção são somente leitura (Decisão 3): sem drag e sem ações.
    const isReadonlyRow = Number(t.readonly) === 1;
    return `
    <div class="kanban-card" data-ticket-id="${t.id}" draggable="${(DASHGLPI_IS_RESTRICTED_VIEW || isReadonlyRow) ? 'false' : 'true'}" data-ticket-detail="${t.id}" data-itemtype="${escHtml(t.itemtype || 'ticket')}">
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

function initKanbanDragDrop() {
    if (DASHGLPI_IS_RESTRICTED_VIEW) return;

    let draggingId = null;

    document.querySelectorAll('#ticketsKanban .kanban-card[draggable="true"]').forEach(card => {
        card.addEventListener('dragstart', e => {
            draggingId = card.dataset.ticketId;
            card.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            draggingId = null;
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
            if (!draggingId) return;
            const newStatus = Number(lane.dataset.status);
            if (!newStatus) return;
            updateTicketKanbanStatus(Number(draggingId), newStatus);
        });
    });
}

function initKanbanCardActions() {
    if (DASHGLPI_IS_RESTRICTED_VIEW) return;

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
