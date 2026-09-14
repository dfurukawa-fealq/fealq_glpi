// ==================== SLA MONITOR ====================
// Extraído de script.js pelo PLAN-20260703-013 (Fase 3.3) — carregado logo
// após script.js (e demais módulos) via <script> separado.

function initSLAMonitor() {
    if (!pageAllowed('sla') || !pageSectionExists('sla')) {
        return;
    }

    updateSLAData();
    setInterval(updateSLAData, 60000);
    setInterval(updateSLACountdowns, 1000);
}

async function updateSLAData() {
    const container = document.getElementById('slaList');
    if (!pageAllowed('sla') || !container) {
        return;
    }

    try {
        const response = await fetch(buildSLAListUrl());
        const payload = await response.json();
        const items = Array.isArray(payload) ? payload : (payload.items || []);
        DashState.slaSummaryGlobal = payload.summary || { critical: 0, warning: 0, unassigned: 0, ok: 0, avg_open_seconds: 0 };
        DashState.slaDataGlobal = Array.isArray(items) ? items.map(item => ({
            ...item,
            title: item.name || 'Chamado sem título',
            ticket: `#${item.id}`,
            deadline: parseMysqlDate(item.active_sla_deadline || item.time_to_own),
            activeDeadline: parseMysqlDate(item.active_sla_deadline || item.time_to_own),
            activeCompletion: parseMysqlDate(item.active_sla_completion),
            resolutionDeadline: parseMysqlDate(item.time_to_resolve),
            status: item.sla_status || 'ok'
        })) : [];

        renderSLAList();
        updateSLASummary(DashState.slaSummaryGlobal);
        updateSLACountdowns();
    } catch (error) {
        console.error('Error loading SLA data:', error);
        if (container) {
            container.innerHTML = '<tr><td colspan="7" class="table-empty">Erro ao carregar Monitor de SLA</td></tr>';
        }
    }
}

function buildSLAListUrl() {
    const params = new URLSearchParams({ action: 'sla_list' });
    const statuses = Array.isArray(DashState.slaAdvancedFilterState.statuses) && DashState.slaAdvancedFilterState.statuses.length
        ? DashState.slaAdvancedFilterState.statuses
        : ['1', '2', '3', '4'];
    params.set('statuses', statuses.join(','));
    if (DashState.slaAdvancedFilterState.dateFrom) {
        params.set('date_from', DashState.slaAdvancedFilterState.dateFrom);
    }
    if (DashState.slaAdvancedFilterState.dateTo) {
        params.set('date_to', DashState.slaAdvancedFilterState.dateTo);
    }
    return `${PLUGIN_ROOT}/ajax/dashboard.php?${params.toString()}`;
}

function initSLASearchAndSort() {
    if (!pageAllowed('sla') || !pageSectionExists('sla')) {
        return;
    }

    const input = document.getElementById('slaSearchInput');
    if (input) {
        input.addEventListener('input', renderSLAList);
    }

    document.querySelectorAll('[data-sla-filter]').forEach(button => {
        button.addEventListener('click', () => {
            DashState.slaFilterState = button.getAttribute('data-sla-filter') || 'all';
            document.querySelectorAll('[data-sla-filter]').forEach(item => item.classList.remove('active'));
            button.classList.add('active');
            renderSLAList();
            updateSLACountdowns();
        });
    });

    const advancedToggle = document.getElementById('slaAdvancedFilterToggle');
    const advancedPanel = document.getElementById('slaAdvancedFilters');
    if (advancedToggle && advancedPanel) {
        advancedToggle.addEventListener('click', () => {
            const willOpen = advancedPanel.hidden;
            advancedPanel.hidden = !willOpen;
            advancedToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    }

    document.getElementById('slaApplyAdvancedFilters')?.addEventListener('click', () => {
        readSLAAdvancedFilters();
        updateSLAAdvancedFilterIndicator();
        updateSLAData();
    });

    document.getElementById('slaClearAdvancedFilters')?.addEventListener('click', () => {
        DashState.slaAdvancedFilterState = { statuses: ['1', '2', '3', '4'], dateFrom: '', dateTo: '' };
        syncSLAAdvancedFilterForm();
        updateSLAAdvancedFilterIndicator();
        updateSLAData();
    });

    document.querySelectorAll('#slaSection .sortable-th').forEach(th => {
        th.addEventListener('click', () => {
            const key = th.getAttribute('data-sla-sort');
            if (!key) return;
            if (DashState.slaSortState.key === key) {
                DashState.slaSortState.direction = DashState.slaSortState.direction === 'asc' ? 'desc' : 'asc';
            } else {
                DashState.slaSortState = { key, direction: 'asc' };
            }
            renderSLAList();
            updateSLACountdowns();
        });
    });

    syncSLAAdvancedFilterForm();
    updateSLAAdvancedFilterIndicator();
}

function readSLAAdvancedFilters() {
    const statuses = Array.from(document.querySelectorAll('.sla-status-filter:checked'))
        .map(input => String(input.value || '').trim())
        .filter(Boolean);
    DashState.slaAdvancedFilterState = {
        statuses: statuses.length ? statuses : ['1', '2', '3', '4'],
        dateFrom: document.getElementById('slaDateFrom')?.value || '',
        dateTo: document.getElementById('slaDateTo')?.value || ''
    };
}

function syncSLAAdvancedFilterForm() {
    const selected = new Set(DashState.slaAdvancedFilterState.statuses || ['1', '2', '3', '4']);
    document.querySelectorAll('.sla-status-filter').forEach(input => {
        input.checked = selected.has(String(input.value));
    });
    const dateFrom = document.getElementById('slaDateFrom');
    const dateTo = document.getElementById('slaDateTo');
    if (dateFrom) dateFrom.value = DashState.slaAdvancedFilterState.dateFrom || '';
    if (dateTo) dateTo.value = DashState.slaAdvancedFilterState.dateTo || '';
}

function updateSLAAdvancedFilterIndicator() {
    const button = document.getElementById('slaAdvancedFilterToggle');
    if (!button) return;
    const statuses = (DashState.slaAdvancedFilterState.statuses || []).join(',');
    const hasDefaultStatuses = statuses === '1,2,3,4';
    const hasDate = Boolean(DashState.slaAdvancedFilterState.dateFrom || DashState.slaAdvancedFilterState.dateTo);
    button.classList.toggle('active', !hasDefaultStatuses || hasDate);
}

function renderSLAList() {
    const container = document.getElementById('slaList');
    if (!container) return;

    const search = (document.getElementById('slaSearchInput')?.value || '').trim().toLowerCase();
    const filtered = DashState.slaDataGlobal.filter(item => {
        if (!matchesSLAFilter(item)) return false;
        if (!search) return true;
        return [
            item.id,
            item.name,
            item.category,
            item.entity_name,
            item.technician_name,
            item.group_name,
            item.requester_name,
            item.priority_label,
            item.risk_label,
            item.status_label,
            item.active_sla_kind,
            getSLAStatusLabel(item.status),
            item.date,
            item.time_to_own,
            item.time_to_resolve,
            item.takeintoaccountdate,
            item.solvedate,
            item.closedate
        ].some(value => String(value || '').toLowerCase().includes(search));
    });

    filtered.sort((a, b) => compareSLAValues(a, b, DashState.slaSortState.key, DashState.slaSortState.direction));
    updateSLASortIcons();

    const count = document.getElementById('slaCount');
    if (count) {
        count.textContent = `${filtered.length} de ${DashState.slaDataGlobal.length} chamados`;
    }

    if (!filtered.length) {
        container.innerHTML = '<tr><td colspan="7" class="table-empty">Nenhum chamado encontrado para este filtro</td></tr>';
        return;
    }

    container.innerHTML = filtered.map(item => {
        const ticketId = Number(item.id);
        const expanded = DashState.slaExpandedRows.has(ticketId);
        return `
        <tr class="sla-main-row">
            <td class="sla-id-cell"><strong>#${item.id}</strong></td>
            <td class="sla-title-cell">
                <div class="table-ticket-info">
                    <div class="table-ticket-title">${escHtml(item.title)}</div>
                    <div class="table-ticket-id">${escHtml(item.entity_name || 'Entidade raiz')} · Chamado ${escHtml(item.ticket)} · ${escHtml(item.status_label || getStatusLabel(item.status))}</div>
                </div>
            </td>
            <td data-pin="status">
                <div class="sla-risk-stack">
                    <span class="sla-status-badge ${item.status}">
                        <i class="fas ${getSLAStatusIcon(item.status)}"></i>
                        ${escHtml(getSLAStatusLabel(item.status))}
                    </span>
                </div>
            </td>
            <td data-label="Atribuição">${renderSLAAssignment(item)}</td>
            <td data-label="Progresso">${renderSLAProgress(item)}</td>
            <td data-label="Aberto há"><span class="table-date">${escHtml(formatDuration(item.open_seconds || 0))}</span></td>
            <td>
                <div class="sla-action-stack">
                    <button class="table-action details-action" type="button" data-sla-details="${ticketId}" title="${expanded ? 'Recolher detalhes' : 'Expandir detalhes'}" aria-label="${expanded ? 'Recolher detalhes' : 'Expandir detalhes'} do chamado #${ticketId}" aria-expanded="${expanded ? 'true' : 'false'}" aria-controls="slaDetail-${ticketId}">
                        <i class="fas ${expanded ? 'fa-chevron-up' : 'fa-chevron-down'}"></i>
                    </button>
                    ${renderSLAAssignAction(item)}
                    ${renderTicketReportAction(item.id)}
                    ${renderGLPITicketAction(item.id)}
                </div>
            </td>
        </tr>
        ${renderSLADetailRow(item, expanded)}
    `;
    }).join('');
}

function renderSLAProgress(item) {
    const kind = item.active_sla_kind || (Number(item.is_unassigned || 0) === 1 ? 'TTO' : 'TTR');
    const percent = Math.max(0, Number(item.active_sla_percent ?? item.sla_initial_percent ?? 0));
    const cappedPercent = Math.min(percent, 100);
    const deadline = item.activeDeadline || parseMysqlDate(item.active_sla_deadline || item.time_to_own);
    const completion = item.activeCompletion || parseMysqlDate(item.active_sla_completion);
    const elapsedSeconds = Number(item.active_sla_elapsed_seconds || 0);
    const progress = getSLAProgressState(item, percent, deadline, completion);

    if (progress.state === 'none') {
        return `
            <div class="sla-progress-cell none">
                <div class="sla-progress-empty">${escHtml(kind)} não aplicado</div>
            </div>
        `;
    }

    const timer = completion
        ? `<span class="sla-time ${progress.state}">${escHtml(formatDuration(elapsedSeconds))}</span>`
        : `<span class="sla-time ${progress.state}" data-deadline="${deadline ? deadline.getTime() : ''}">--:--</span>`;
    const timerLabel = completion ? 'Concluído' : (progress.state === 'overdue' ? 'SLA estourado' : 'Tempo restante');

    return `
        <div class="sla-progress-cell ${progress.state}">
            <div class="sla-progress-top">
                <span class="sla-progress-percent"><span class="sla-kind-pill">${escHtml(kind)}</span>${escHtml(formatPercent(percent))}</span>
                <span class="sla-timer-stack">
                    <span class="sla-timer-label">${timerLabel}</span>
                    ${timer}
                </span>
            </div>
            <div class="sla-progress-track" aria-hidden="true">
                <span class="sla-progress-fill" style="width: ${cappedPercent}%"></span>
            </div>
            <div class="sla-progress-label">
                <span class="sla-progress-dot"></span>
                <span>${escHtml(progress.label)}</span>
            </div>
        </div>
    `;
}

function getSLAProgressState(item, percent, deadline, completion = null) {
    if (!deadline) {
        return { state: 'none', label: 'SLA não aplicado' };
    }
    if (Number(item.active_sla_overdue ?? item.sla_initial_overdue ?? 0) === 1 || (!completion && percent >= 100)) {
        return { state: 'overdue', label: completion ? 'Concluído com atraso' : 'SLA estourado' };
    }
    if (completion) {
        return { state: 'low', label: 'Concluído no prazo' };
    }
    if (percent >= 90) {
        return { state: 'critical', label: 'Crítico' };
    }
    if (percent >= 70) {
        return { state: 'warning', label: 'Atenção' };
    }
    return { state: 'low', label: 'Baixo risco' };
}

function renderSLADetailRow(item, expanded = false) {
    const ticketId = Number(item.id);
    return `
        <tr class="sla-detail-row" id="slaDetail-${ticketId}" ${expanded ? '' : 'hidden'}>
            <td colspan="7">
                <div class="sla-detail-panel">
                    ${renderSLAInfoSection(item)}
                    ${renderSLATimingSection('Tempo para assumir/atribuir', 'TTO', item.tto, 'Assumir até', 'Assumido em', 'fa-user-clock')}
                    ${renderSLATimingSection('Tempo para resolver', 'TTR', item.ttr, 'Resolver até', 'Resolvido em', 'fa-check-double')}
                </div>
            </td>
        </tr>
    `;
}

function renderSLAInfoSection(item) {
    return `
        <section class="sla-detail-section">
            <div class="sla-detail-section-title"><i class="fas fa-user"></i> Solicitante</div>
            <div class="sla-detail-grid">
                ${renderSLADetailItem('Requerente', item.requester_name || '-')}
                ${renderSLADetailItem('Categoria', item.category || 'Sem categoria')}
                ${renderSLADetailItem('Prioridade', item.priority_label || '-')}
                ${renderSLADetailItem('Entidade', item.entity_name || 'Entidade raiz')}
            </div>
        </section>
    `;
}

function renderSLATimingSection(title, kind, timing, deadlineLabel, completionLabel, icon) {
    const data = timing || {};
    const completion = data.completion ? formatDateTime(data.completion) : 'Em andamento';
    return `
        <section class="sla-detail-section">
            <div class="sla-detail-section-title"><i class="fas ${icon}"></i> ${escHtml(title)}</div>
            <div class="sla-detail-grid sla-detail-grid-wide">
                ${renderSLADetailItem('SLA', kind)}
                ${renderSLADetailItem('Início em', formatDateTime(data.start) || '-')}
                ${renderSLADetailItem(deadlineLabel, formatDateTime(data.deadline) || '-')}
                ${renderSLADetailItem(completionLabel, completion)}
                ${renderSLADetailItem('Tempo total', formatDuration(data.elapsed_seconds || 0))}
            </div>
        </section>
    `;
}

function renderSLADetailItem(label, value) {
    return `
        <div class="sla-detail-item">
            <span>${escHtml(label)}</span>
            <strong>${escHtml(value || '-')}</strong>
        </div>
    `;
}

function matchesSLAFilter(item) {
    if (DashState.slaFilterState === 'unassigned') {
        return Number(item.is_unassigned || 0) === 1;
    }
    if (DashState.slaFilterState === 'overdue') {
        return Number(item.active_sla_overdue ?? item.sla_initial_overdue ?? 0) === 1;
    }
    if (DashState.slaFilterState === 'high') {
        return Number(item.risk_score || 0) >= 100;
    }
    return true;
}

function renderSLAAssignment(item) {
    if (Number(item.is_unassigned || 0) === 1) {
        return '<span class="sla-unassigned-pill"><i class="fas fa-user-slash"></i> Sem atribuição</span>';
    }

    const tech = item.technician_name && item.technician_name !== '-' ? item.technician_name : '-';
    const group = item.group_name && item.group_name !== '-' ? item.group_name : '-';

    return `
        <div class="sla-assignment-stack">
            <div class="sla-assignment-line"><i class="fas fa-user-cog"></i>${escHtml(tech)}</div>
            <div class="sla-assignment-line"><i class="fas fa-users"></i>${escHtml(group)}</div>
        </div>
    `;
}

function renderSLAAssignAction(item) {
    if (Number(item.is_closed || 0) === 1 || [5, 6].includes(Number(item.status))) {
        return '';
    }

    return `
        <button class="table-action assign-action" type="button" data-sla-assign="${Number(item.id)}" title="Atribuir chamado" aria-label="Atribuir chamado #${Number(item.id)}">
            <i class="fas fa-user-check"></i>
        </button>
    `;
}

function renderGLPITicketAction(ticketId) {
    const url = glpiTicketUrl(ticketId);
    if (!url) {
        return `
            <span class="table-action glpi-action" title="GLPI_PUBLIC_URL não configurado">
                <i class="fas fa-external-link-alt"></i>
            </span>
        `;
    }

    return `
        <a class="table-action glpi-action" href="${escHtml(url)}" target="_blank" rel="noopener" title="Abrir no GLPI" aria-label="Abrir chamado #${Number(ticketId)} no GLPI">
            <i class="fas fa-external-link-alt"></i>
        </a>
    `;
}

function glpiTicketUrl(ticketId) {
    const root = (typeof DASHGLPI_GLPI_ROOT !== 'undefined' ? DASHGLPI_GLPI_ROOT : '').replace(/\/$/, '');
    if (!root) return '';
    return `${root}/front/ticket.form.php?id=${encodeURIComponent(ticketId)}`;
}

function compareSLAValues(a, b, key, direction) {
    const factor = direction === 'asc' ? 1 : -1;
    let av = a[key];
    let bv = b[key];

    if (['id', 'seconds_left', 'risk_score', 'priority', 'open_seconds', 'sla_initial_percent', 'active_sla_percent', 'active_sla_elapsed_seconds'].includes(key)) {
        return ((Number(av) || 0) - (Number(bv) || 0)) * factor;
    }

    if (key === 'date' || key === 'time_to_own' || key === 'time_to_resolve' || key === 'active_sla_deadline') {
        return (parseDateValue(av) - parseDateValue(bv)) * factor;
    }

    if (key === 'sla_status' || key === 'risk_label') {
        const order = { critical: 1, warning: 2, ok: 3 };
        return ((order[a.status] || 9) - (order[b.status] || 9)) * factor;
    }

    return String(av || '').localeCompare(String(bv || ''), 'pt-BR', { sensitivity: 'base' }) * factor;
}

function updateSLASortIcons() {
    document.querySelectorAll('#slaSection .sortable-th').forEach(th => {
        const icon = th.querySelector('i');
        if (!icon) return;
        const active = th.getAttribute('data-sla-sort') === DashState.slaSortState.key;
        icon.className = `fas ${active ? (DashState.slaSortState.direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'}`;
    });
}

function getSLAStatusLabel(status) {
    if (status === 'critical') return 'Crítico';
    if (status === 'warning') return 'Atenção';
    return 'No prazo';
}

function getSLAStatusIcon(status) {
    if (status === 'critical') return 'fa-exclamation-triangle';
    if (status === 'warning') return 'fa-clock';
    return 'fa-check-circle';
}

function updateSLACountdowns() {
    document.querySelectorAll('.sla-time[data-deadline]').forEach(el => {
        const deadline = parseInt(el.getAttribute('data-deadline'));
        const cell = el.closest('.sla-progress-cell');
        const label = cell?.querySelector('.sla-timer-label');
        if (!deadline) {
            el.textContent = '-';
            if (label) label.textContent = 'Tempo restante';
            return;
        }
        const now = Date.now();
        const diff = deadline - now;

        if (diff <= 0) {
            el.textContent = formatCountdownDuration(Math.abs(diff));
            if (label) label.textContent = 'SLA estourado';
            if (cell) {
                cell.classList.remove('low', 'warning', 'critical');
                cell.classList.add('overdue');
            }
            return;
        }

        if (label) label.textContent = 'Tempo restante';
        el.textContent = formatCountdownDuration(diff);
    });
}

function formatCountdownDuration(milliseconds) {
    const safeMs = Math.max(0, Number(milliseconds) || 0);
    const hours = Math.floor(safeMs / 3600000);
    const minutes = Math.floor((safeMs % 3600000) / 60000);
    const seconds = Math.floor((safeMs % 60000) / 1000);

    return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function formatSlaRemainingHhMm(secondsLeft) {
    const totalMinutes = Math.max(0, Math.floor(Math.abs(Number(secondsLeft) || 0) / 60));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${hours}:${String(minutes).padStart(2, '0')}`;
}

function formatSlaPercent(percent) {
    return `${Number(percent || 0).toFixed(1).replace('.', ',')}%`;
}

function updateSLASummary(summary) {
    setVal('slaCritical', summary.critical || 0);
    setVal('slaWarning', summary.warning || 0);
    setVal('slaUnassigned', summary.unassigned || 0);
    setVal('slaOk', summary.ok || 0);
    setVal('slaAverageTime', formatDuration(summary.avg_open_seconds || 0));
}

function initTicketAssignmentModal() {
    if (!pageAllowed('sla') || !pageSectionExists('sla')) {
        return;
    }

    const form = document.getElementById('slaAssignmentForm');
    if (form) {
        form.addEventListener('submit', submitTicketAssignment);
    }

    document.querySelectorAll('[data-assignment-close]').forEach(button => {
        button.addEventListener('click', closeTicketAssignmentModal);
    });

    const modal = document.getElementById('slaAssignmentModal');
    if (modal) {
        modal.addEventListener('click', event => {
            if (event.target === modal) {
                closeTicketAssignmentModal();
            }
        });
    }

    document.addEventListener('click', event => {
        const detailButton = event.target.closest('[data-sla-details]');
        if (detailButton) {
            const ticketId = Number(detailButton.getAttribute('data-sla-details') || 0);
            if (ticketId > 0) {
                if (DashState.slaExpandedRows.has(ticketId)) {
                    DashState.slaExpandedRows.delete(ticketId);
                } else {
                    DashState.slaExpandedRows.add(ticketId);
                }
                renderSLAList();
                updateSLACountdowns();
            }
            return;
        }

        const button = event.target.closest('[data-sla-assign]');
        if (!button) return;
        openTicketAssignmentModal(Number(button.getAttribute('data-sla-assign') || 0));
    });
}

async function openTicketAssignmentModal(ticketId) {
    const modal = document.getElementById('slaAssignmentModal');
    const hidden = document.getElementById('assignmentTicketId');
    const title = document.getElementById('slaAssignmentTitle');
    const subtitle = document.getElementById('slaAssignmentSubtitle');
    const status = document.getElementById('assignmentStatus');
    const item = DashState.slaDataGlobal.find(row => Number(row.id) === Number(ticketId));

    if (!modal || !hidden) return;

    hidden.value = String(ticketId || '');
    if (title) title.textContent = `Atribuir chamado #${ticketId}`;
    if (subtitle) subtitle.textContent = item?.title || 'Selecione um técnico, um grupo ou ambos.';
    setAssignmentStatus('Carregando técnicos e grupos...', '');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');

    try {
        await loadTicketAssignmentOptions();
        populateTicketAssignmentSelects();
        setAssignmentStatus('', '');
    } catch (error) {
        console.error('Erro ao carregar destinos de atribuição:', error);
        setAssignmentStatus(error.message || 'Erro ao carregar técnicos e grupos.', 'error');
    }
}

function closeTicketAssignmentModal() {
    const modal = document.getElementById('slaAssignmentModal');
    const form = document.getElementById('slaAssignmentForm');
    if (modal) {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
    }
    if (form) {
        form.reset();
    }
    setAssignmentStatus('', '');
}

async function loadTicketAssignmentOptions(force = false) {
    if (DashState.ticketAssignmentOptions.loaded && !force) {
        return DashState.ticketAssignmentOptions;
    }

    const response = await fetch(PLUGIN_ROOT + '/ajax/ticket_assignment.php', {
        headers: { 'Accept': 'application/json' }
    });
    const data = await readAssignmentJson(response);
    DashState.ticketAssignmentOptions = {
        users: Array.isArray(data.users) ? data.users : [],
        groups: Array.isArray(data.groups) ? data.groups : [],
        loaded: true
    };
    return DashState.ticketAssignmentOptions;
}

function populateTicketAssignmentSelects() {
    const userSelect = document.getElementById('assignmentUserId');
    const groupSelect = document.getElementById('assignmentGroupId');

    if (userSelect) {
        userSelect.innerHTML = '<option value="0">Não atribuir técnico</option>' + DashState.ticketAssignmentOptions.users.map(user => {
            const label = user.display_name || `${user.firstname || ''} ${user.realname || ''}`.trim() || user.name || `Usuário #${user.id}`;
            return `<option value="${Number(user.id)}">${escHtml(label)}</option>`;
        }).join('');
    }

    if (groupSelect) {
        groupSelect.innerHTML = '<option value="0">Não atribuir grupo</option>' + DashState.ticketAssignmentOptions.groups.map(group => {
            const label = group.completename || group.name || `Grupo #${group.id}`;
            return `<option value="${Number(group.id)}">${escHtml(label)}</option>`;
        }).join('');
    }
}

async function submitTicketAssignment(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const submit = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    const usersId = Number(formData.get('users_id') || 0);
    const groupsId = Number(formData.get('groups_id') || 0);

    if (usersId <= 0 && groupsId <= 0) {
        setAssignmentStatus('Selecione um técnico, um grupo ou ambos.', 'error');
        return;
    }

    formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
    setAssignmentStatus('Atribuindo chamado...', '');
    if (submit) submit.disabled = true;

    try {
        const response = await fetch(PLUGIN_ROOT + '/ajax/ticket_assignment.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        await readAssignmentJson(response);
        setAssignmentStatus('Chamado atribuído com sucesso.', 'success');
        await updateSLAData();
        await loadTicketLists();
        await updateData();
        setTimeout(closeTicketAssignmentModal, 450);
    } catch (error) {
        console.error('Erro ao atribuir chamado:', error);
        setAssignmentStatus(error.message || 'Erro ao atribuir chamado.', 'error');
    } finally {
        if (submit) submit.disabled = false;
    }
}

async function readAssignmentJson(response) {
    const text = await response.text();
    let data = null;

    try {
        data = text ? JSON.parse(text) : null;
    } catch (error) {
        console.error('Resposta nao JSON de atribuicao:', text);
        throw new Error('Resposta invalida do servidor. Verifique os logs do DashGLPI.');
    }

    if (!response.ok || !data || !data.ok) {
        throw new Error(data?.error || data?.message || 'Falha ao processar atribuicao.');
    }

    return data;
}

function setAssignmentStatus(message, type) {
    const status = document.getElementById('assignmentStatus');
    if (!status) return;
    status.textContent = message || '';
    status.className = 'assignment-status' + (type ? ' ' + type : '');
}

function formatPercent(value) {
    const number = Number(value) || 0;
    if (number <= 0) return '0%';
    if (number >= 999) return '999%';
    return `${number.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

function formatDuration(seconds) {
    seconds = Number(seconds) || 0;
    if (seconds <= 0) return '0h';

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.round((seconds % 3600) / 60);

    if (hours <= 0) {
        return `${minutes}min`;
    }

    return minutes > 0 ? `${hours}h ${minutes}min` : `${hours}h`;
}

