// ==================== SELF-SERVICE ACTIONS (Helpdesk) ====================
// Extraído de script.js pelo PLAN-20260703-013 (Fase 3.3) — carregado logo
// após script.js (e demais módulos) via <script> separado.

function initSelfServiceActions() {
    DashAttendance.init(); // PLAN-20260905-001
    document.addEventListener('click', event => {
        const rowFollowupBtn = event.target.closest('[data-followup-ticket]');
        if (rowFollowupBtn) {
            openTicketDetailModal(Number(rowFollowupBtn.getAttribute('data-followup-ticket')), { focus: 'followup' });
            return;
        }

        const rowCancelBtn = event.target.closest('[data-cancel-ticket]');
        if (rowCancelBtn) {
            openTicketDetailModal(Number(rowCancelBtn.getAttribute('data-cancel-ticket')), { focus: 'cancel' });
            return;
        }

        const rowSolutionBtn = event.target.closest('[data-solution-ticket]');
        if (rowSolutionBtn) {
            openTicketDetailModal(Number(rowSolutionBtn.getAttribute('data-solution-ticket')), { focus: 'solution' });
            return;
        }

        const rowSatisfactionBtn = event.target.closest('[data-satisfaction-ticket]');
        if (rowSatisfactionBtn) {
            openTicketDetailModal(Number(rowSatisfactionBtn.getAttribute('data-satisfaction-ticket')), { focus: 'satisfaction' });
            return;
        }

        const detailRow = event.target.closest('[data-ticket-detail]');
        if (detailRow && !event.target.closest('button, a')) {
            // Problema/Manutenção abrem o modal em modo somente leitura (PLAN-20260709-019).
            openTicketDetailModal(Number(detailRow.getAttribute('data-ticket-detail')), {
                itemtype: detailRow.getAttribute('data-itemtype') || 'ticket',
                trigger: detailRow,
            });
        }
    });

    const detailModal = document.getElementById('ticketDetailModal');
    if (detailModal) {
        detailModal.addEventListener('click', event => {
            if (event.target === detailModal) closeTicketDetailModal();
        });
        detailModal.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', closeTicketDetailModal);
        });
    }

    document.getElementById('followupForm')?.addEventListener('submit', submitFollowup);
    initFollowupUpload();

    document.getElementById('ticketDetailCancelToggle')?.addEventListener('click', () => {
        const section = document.getElementById('ticketDetailCancelSection');
        section?.classList.remove('is-hidden');
        scrollTicketDetailTarget(section, { behavior: 'smooth', block: 'center' });
    });
    document.getElementById('ticketDetailCancelForm')?.addEventListener('submit', submitCancelTicket);

    document.getElementById('satisfactionForm')?.addEventListener('submit', submitSatisfaction);
    document.getElementById('solutionApproveBtn')?.addEventListener('click', () => submitSolutionDecision('approve'));
    document.getElementById('solutionRefuseBtn')?.addEventListener('click', showSolutionRefuseForm);
    document.getElementById('solutionRefuseForm')?.addEventListener('submit', event => {
        event.preventDefault();
        submitSolutionDecision('refuse');
    });
}

function closeTicketDetailModal() {
    const modal = document.getElementById('ticketDetailModal');
    if (!modal || !DashAttendance.canClose()) return;
    DashAttendance.generation++;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    modal.querySelectorAll('.ticket-create-combobox-options:not([hidden]), .attendance-actor-options:not([hidden])').forEach(box => { box.hidden = true; });
    document.body.classList.remove('mobile-ticket-detail-active');
    unlockTicketDetailPageScroll();
    DashAttendance.restoreFocus();
}

function lockTicketDetailPageScroll() {
    if (document.body.classList.contains('ticket-detail-scroll-locked')) return;
    const scrollY = window.scrollY || document.documentElement.scrollTop || 0;
    document.body.dataset.ticketDetailScrollY = String(scrollY);
    document.body.style.top = `-${scrollY}px`;
    document.body.classList.add('ticket-detail-scroll-locked');
}

function unlockTicketDetailPageScroll() {
    const wasLocked = document.body.classList.contains('ticket-detail-scroll-locked');
    const scrollY = Number(document.body.dataset.ticketDetailScrollY || 0);
    document.body.classList.remove('ticket-detail-scroll-locked');
    document.body.style.top = '';
    delete document.body.dataset.ticketDetailScrollY;
    if (wasLocked) window.scrollTo(0, scrollY);
}

function setSelfServiceStatus(elementId, message, type) {
    const status = document.getElementById(elementId);
    if (!status) return;
    status.textContent = message || '';
    status.className = 'assignment-status' + (type ? ' ' + type : '');
}

// -------- Acompanhamento (timeline completa) --------
function initFollowupUpload() {
    const fileInput = document.getElementById('followupAttachments');
    const uploadZone = document.getElementById('followupUploadZone');
    const attachmentsList = document.getElementById('followupAttachmentsList');
    const form = document.getElementById('followupForm');

    document.getElementById('followupPickFiles')?.addEventListener('click', (event) => {
        event.preventDefault();
        fileInput?.click();
    });
    fileInput?.addEventListener('change', (event) => {
        const added = addFollowupFiles(event.currentTarget?.files);
        if (added > 0) {
            setSelfServiceStatus('followupStatus', `${added} anexo(s) preparado(s) para o envio.`, '');
        }
    });
    attachmentsList?.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-followup-attachment-remove]');
        if (!removeButton) return;
        event.preventDefault();
        removeFollowupAttachmentById(removeButton.getAttribute('data-followup-attachment-remove'));
    });

    if (uploadZone) {
        uploadZone.addEventListener('click', (event) => {
            if (event.target.closest('button')) return;
            fileInput?.click();
        });
        uploadZone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput?.click();
            }
        });
        ['dragenter', 'dragover'].forEach((eventName) => {
            uploadZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                uploadZone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
            uploadZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                if (eventName === 'dragleave' && uploadZone.contains(event.relatedTarget)) return;
                uploadZone.classList.remove('is-dragover');
            });
        });
        uploadZone.addEventListener('drop', (event) => {
            const files = Array.from(event.dataTransfer?.files || []);
            const added = addFollowupFiles(files);
            if (added > 0) {
                setSelfServiceStatus('followupStatus', `${added} anexo(s) adicionado(s) ao acompanhamento.`, '');
            }
        });
    }

    form?.addEventListener('paste', (event) => {
        const clipboardItems = Array.from(event.clipboardData?.items || []);
        const pastedFiles = clipboardItems
            .filter((item) => item.kind === 'file')
            .map((item) => item.getAsFile())
            .filter((file) => file instanceof File);
        if (!pastedFiles.length) return;

        event.preventDefault();
        const added = addFollowupFiles(pastedFiles);
        if (added > 0) {
            setSelfServiceStatus('followupStatus', `${added} imagem(ns) adicionada(s) ao acompanhamento.`, '');
        }
    });
}

function syncFollowupAttachmentsInput() {
    const input = document.getElementById('followupAttachments');
    if (!(input instanceof HTMLInputElement) || typeof DataTransfer === 'undefined') return;

    const dataTransfer = new DataTransfer();
    DashState.followupCreateState.attachments.forEach((entry) => {
        if (entry?.file instanceof File) dataTransfer.items.add(entry.file);
    });
    input.files = dataTransfer.files;
}

function updateFollowupUploadHelp() {
    const uploadHelp = document.getElementById('followupUploadHelp');
    if (!uploadHelp) return;

    const attachmentCount = DashState.followupCreateState.attachments.length;
    const countLabel = attachmentCount > 0
        ? `${attachmentCount} anexo(s) pronto(s) para envio.`
        : 'Adicione imagens e arquivos do acompanhamento.';
    const maxLabel = DashState.followupCreateState.uploadMaxLabel
        ? `Limite configurado para anexos: ${DashState.followupCreateState.uploadMaxLabel}.`
        : 'Os anexos seguem o limite configurado para atendimento.';

    uploadHelp.textContent = `${countLabel} ${maxLabel}`;
}

function renderFollowupAttachments() {
    const container = document.getElementById('followupAttachmentsList');
    if (!container) return;

    if (!DashState.followupCreateState.attachments.length) {
        container.innerHTML = '<div class="ticket-create-attachments-empty">Nenhum anexo selecionado.</div>';
        updateFollowupUploadHelp();
        return;
    }

    container.innerHTML = DashState.followupCreateState.attachments.map((entry) => {
        const file = entry.file;
        const isImage = String(file?.type || '').startsWith('image/');
        const preview = isImage && entry.previewUrl
            ? `<img src="${escHtml(entry.previewUrl)}" alt="${escHtml(String(file?.name || 'Imagem anexada'))}">`
            : `<span class="ticket-create-attachment-ext">${escHtml(ticketCreateFileTypeLabel(file))}</span>`;

        return `
            <article class="ticket-create-attachment-card">
                <button class="ticket-create-attachment-remove" type="button" data-followup-attachment-remove="${escHtml(String(entry.id || ''))}" aria-label="Remover anexo">
                    <i class="fas fa-times"></i>
                </button>
                <div class="ticket-create-attachment-preview">${preview}</div>
                <div class="ticket-create-attachment-body">
                    <div class="ticket-create-attachment-name" title="${escHtml(String(file?.name || ''))}">${escHtml(String(file?.name || 'Arquivo'))}</div>
                    <div class="ticket-create-attachment-meta">${escHtml(ticketCreateFormatFileSize(file?.size || 0))}</div>
                </div>
            </article>
        `;
    }).join('');

    updateFollowupUploadHelp();
}

function addFollowupFiles(files) {
    const incomingFiles = Array.from(files || []).filter((file) => file instanceof File);
    if (!incomingFiles.length) return 0;

    const existingKeys = new Set(DashState.followupCreateState.attachments.map((entry) => entry.key));
    let added = 0;

    incomingFiles.forEach((file) => {
        const fileKey = ticketCreateFileKey(file);
        if (existingKeys.has(fileKey)) return;

        existingKeys.add(fileKey);
        DashState.followupCreateState.attachments.push({
            id: `followup-file-${DashState.followupAttachmentSequence++}`,
            key: fileKey,
            file,
            previewUrl: String(file.type || '').startsWith('image/') ? URL.createObjectURL(file) : '',
        });
        added += 1;
    });

    syncFollowupAttachmentsInput();
    renderFollowupAttachments();
    return added;
}

function removeFollowupAttachmentById(attachmentId) {
    const nextAttachments = [];
    DashState.followupCreateState.attachments.forEach((entry) => {
        if (String(entry.id) === String(attachmentId)) {
            releaseTicketCreateAttachment(entry);
            return;
        }
        nextAttachments.push(entry);
    });
    DashState.followupCreateState.attachments = nextAttachments;
    syncFollowupAttachmentsInput();
    renderFollowupAttachments();
}

function clearFollowupAttachments() {
    DashState.followupCreateState.attachments.forEach(releaseTicketCreateAttachment);
    DashState.followupCreateState.attachments = [];
    syncFollowupAttachmentsInput();
    renderFollowupAttachments();
}

async function openTicketDetailModal(ticketId, options = {}) {
    const modal = document.getElementById('ticketDetailModal');
    const hidden = document.getElementById('ticketDetailTicketId');
    if (!modal || !hidden || !ticketId || !DashAttendance.canClose()) return;
    DashAttendance.reset(ticketId, options.trigger);

    // Problema/Manutenção abrem o mesmo modal em modo somente leitura
    // (PLAN-20260709-019, Fase D / Decisão 3).
    const itemtype = options.itemtype && options.itemtype !== '' ? String(options.itemtype) : 'ticket';
    const isReadonlyType = itemtype !== 'ticket';
    DashState.ticketDetailItemtype = itemtype;
    const typeInfo = (typeof itilTypeInfo === 'function') ? itilTypeInfo(itemtype) : null;
    const typeLabel = typeInfo?.label || 'Chamado';
    document.querySelector('#ticketDetailModal .ticket-detail-col-followup')?.classList.toggle('is-hidden', isReadonlyType);

    hidden.value = String(ticketId);
    document.getElementById('ticketDetailTitle').textContent = `${typeLabel} #${ticketId}`;
    document.getElementById('ticketDetailSubtitle').textContent = 'Carregando...';
    document.getElementById('ticketDetailMeta').innerHTML = '';
    document.getElementById('ticketDetailContent').textContent = '';

    document.getElementById('followupContent').value = '';
    clearFollowupAttachments();
    setSelfServiceStatus('followupStatus', '', '');
    document.getElementById('followupTimeline').innerHTML = '<div class="followup-empty">Carregando...</div>';

    const cancelReasonEl = document.getElementById('ticketDetailCancelReason');
    if (cancelReasonEl) cancelReasonEl.value = '';
    setSelfServiceStatus('ticketDetailCancelStatus', '', '');

    document.getElementById('solutionRefuseForm')?.classList.add('is-hidden');
    const solutionRefuseReasonEl = document.getElementById('solutionRefuseReason');
    if (solutionRefuseReasonEl) solutionRefuseReasonEl.value = '';
    setSelfServiceStatus('solutionStatus', '', '');

    const satisfactionCommentEl = document.getElementById('satisfactionComment');
    if (satisfactionCommentEl) satisfactionCommentEl.value = '';
    const satisfactionStarsEl = document.getElementById('satisfactionStars');
    if (satisfactionStarsEl) satisfactionStarsEl.innerHTML = '';
    setSelfServiceStatus('satisfactionStatus', '', '');

    ['ticketDetailCancelSection', 'solutionSection', 'satisfactionSection'].forEach(id => {
        document.getElementById(id)?.classList.add('is-hidden');
    });

    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mobile-ticket-detail-active');
    lockTicketDetailPageScroll();
    modal.querySelector('[data-modal-close]')?.focus({ preventScroll: true });

    if (isReadonlyType) {
        // O payload do detalhe já traz os acompanhamentos (leitura SQL direta);
        // o endpoint de followup do bridge continua exclusivo dos chamados.
        await loadTicketDetail(ticketId, itemtype);
    } else {
        await Promise.all([
            loadTicketDetail(ticketId, itemtype),
            loadFollowupTimeline(ticketId),
        ]);
    }

    if (Number(DashAttendance.ticket?.id) !== ticketId && !isReadonlyType) return;
    if (options.attendanceStatus) DashAttendance.focusStatus(Number(options.attendanceStatus));
    else focusTicketDetailSection(options.focus);
}

async function loadTicketDetail(ticketId, itemtype = 'ticket') {
    const generation = DashAttendance.generation;
    try {
        const typeQuery = itemtype && itemtype !== 'ticket' ? `&itemtype=${encodeURIComponent(itemtype)}` : '';
        const res = await fetch(`${PLUGIN_ROOT}/ajax/ticket_detail.php?ticket_id=${encodeURIComponent(ticketId)}${typeQuery}`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!DashAttendance.isCurrent(generation, ticketId)) return;
        if (!data.ok) throw new Error(data.error || 'Erro ao carregar chamado.');
        renderTicketDetail(data.ticket || {});
        if ((data.ticket?.itemtype || 'ticket') !== 'ticket') {
            renderFollowupTimeline(Array.isArray(data.ticket?.followups) ? data.ticket.followups : []);
        }
    } catch (error) {
        if (DashAttendance.isCurrent(generation, ticketId)) document.getElementById('ticketDetailSubtitle').textContent = error.message || 'Erro ao carregar chamado.';
    }
}

function renderTicketDetail(ticket) {
    const status = Number(ticket.status);
    const detailLabel = ticket.type_label || 'Chamado';
    document.getElementById('ticketDetailTitle').textContent = `#${ticket.id} — ${ticket.name || detailLabel}`;
    document.getElementById('ticketDetailSubtitle').textContent = ticket.status_label || getStatusLabel(status);
    document.getElementById('ticketDetailContent').textContent = ticket.content || 'Sem descrição.';

    const meta = [
        ['Categoria', ticket.category],
        ['Prioridade', ticket.priority ? `Prioridade ${ticket.priority}` : '-'],
        ['Requerente', ticket.requester_name],
        ['Técnico', ticket.technician_name],
        ['Criado em', formatDateTime(ticket.date)],
        ['Atualizado em', formatDateTime(ticket.date_mod)],
    ];
    document.getElementById('ticketDetailMeta').innerHTML = meta.map(([label, value]) => `
        <div class="ticket-detail-meta-item">
            <span>${escHtml(label)}</span>
            <strong>${escHtml(value || '-')}</strong>
        </div>
    `).join('');

    if (ticket.capabilities) {
        DashAttendance.render(ticket);
        if (ticket.capabilities.mode === 'self_service' && Number(ticket.satisfaction_pending) === 1) loadSatisfactionCatalog(ticket.id);
        return;
    }

    if (DASHGLPI_IS_HELPDESK_VIEW && (ticket.itemtype || 'ticket') === 'ticket') {
        document.getElementById('ticketDetailCancelToggle')?.classList.toggle('is-hidden', status === 6);
        document.getElementById('solutionSection')?.classList.toggle('is-hidden', status !== 5);

        const satisfactionPending = status === 6 && Number(ticket.satisfaction_pending) === 1;
        document.getElementById('satisfactionSection')?.classList.toggle('is-hidden', !satisfactionPending);
        if (satisfactionPending) {
            loadSatisfactionCatalog(ticket.id);
        }
    }
}

function focusTicketDetailSection(focus) {
    if (!focus) return;

    if (focus === 'cancel') {
        document.getElementById('ticketDetailCancelSection')?.classList.remove('is-hidden');
    }

    const map = {
        followup: 'followupContent',
        cancel: 'ticketDetailCancelSection',
        solution: 'solutionSection',
        satisfaction: 'satisfactionSection',
        actors: 'attendanceActors',
    };
    if (focus === 'actors') document.getElementById('attendanceProperties').open = true;
    const el = document.getElementById(map[focus]);
    if (!el) return;
    scrollTicketDetailTarget(el, { behavior: 'smooth', block: 'center' });
    if (typeof el.focus === 'function') el.focus({ preventScroll: true });
}

function scrollTicketDetailTarget(target, options = {}) {
    if (!target) return;
    const modal = document.getElementById('ticketDetailModal');
    const columns = document.getElementById('ticketDetailBody');
    const scrollParent = target.closest('.ticket-detail-col') || columns || modal;
    if (!scrollParent) return;

    const behavior = options.behavior || 'smooth';
    const block = options.block || 'nearest';
    const targetRect = target.getBoundingClientRect();
    const parentRect = scrollParent.getBoundingClientRect();
    let top = scrollParent.scrollTop + targetRect.top - parentRect.top;
    if (block === 'center') {
        top -= Math.max(0, (parentRect.height - targetRect.height) / 2);
    } else if (block === 'end') {
        top -= Math.max(0, parentRect.height - targetRect.height);
    }
    scrollParent.scrollTo({ top: Math.max(0, top), behavior });
}

async function loadFollowupTimeline(ticketId) {
    return DashAttendance.loadTimeline();
}

function renderFollowupTimeline(followups) {
    const timeline = document.getElementById('followupTimeline');
    if (!timeline) return;

    if (!followups.length) {
        timeline.innerHTML = '<div class="followup-empty">Nenhum acompanhamento registrado ainda.</div>';
        return;
    }

    timeline.replaceChildren();
    followups.forEach(item => {
        const article = document.createElement('article');
        article.className = 'followup-item' + (Number(item.is_private) ? ' is-private' : '');
        const kind = item.itemtype === 'TicketTask' ? 'Tarefa' : item.itemtype === 'ITILSolution' ? 'Solução' : Number(item.is_private) ? 'Nota interna — restrita' : 'Resposta';
        article.innerHTML = `<div class="followup-item-header"><strong>${escHtml(item.author || 'Usuário')}</strong><span>${escHtml(kind)}</span><time>${escHtml(formatDateTime(item.date))}</time></div><div class="followup-item-content"></div><div class="attendance-documents"></div>`;
        DashAttendance.renderContent(article.querySelector('.followup-item-content'), item);
        article.querySelector('.attendance-documents').innerHTML = (item.documents || []).map(doc => DashAttendance.documentHTML(doc)).join('');
        if (item.itemtype === 'TicketTask') {
            const detail = document.createElement('small'); detail.textContent = `${Math.round(Number(item.duration || 0) / 60)} min · ${['Informação', 'A fazer', 'Feita'][Number(item.state)] || ''}`; article.append(detail);
        }
        timeline.append(article);
    });
}

async function submitFollowup(event) {
    return DashAttendance.submit(event);
}

// -------- Cancelar Chamado --------
async function submitCancelTicket(event) {
    event.preventDefault();
    return DashAttendance.mutate('ticket_cancel.php', {
        reason: document.getElementById('ticketDetailCancelReason')?.value.trim() || '',
    }, 'ticketDetailCancelStatus');
}

// -------- Validar a Solução (Aprovar/Recusar) --------
function showSolutionRefuseForm() {
    document.getElementById('solutionRefuseForm')?.classList.remove('is-hidden');
}

async function submitSolutionDecision(action) {
    const reason = document.getElementById('solutionRefuseReason')?.value.trim() || '';
    if (action === 'refuse' && !reason) {
        setSelfServiceStatus('solutionStatus', 'Explique o motivo da recusa.', 'error');
        return;
    }
    return DashAttendance.mutate('ticket_solution.php', { action, reason }, 'solutionStatus');
}

// -------- Pesquisa de Satisfação --------
async function loadSatisfactionCatalog(ticketId) {
    const generation = DashAttendance.generation;
    setSelfServiceStatus('satisfactionStatus', 'Carregando...', '');
    document.getElementById('satisfactionStars').innerHTML = '';

    try {
        const res = await fetch(`${PLUGIN_ROOT}/ajax/ticket_satisfaction.php?ticket_id=${encodeURIComponent(ticketId)}`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!DashAttendance.isCurrent(generation, ticketId)) return;
        if (!data.ok) throw new Error(data.error || 'Erro ao carregar pesquisa de satisfação.');

        const satisfaction = data.satisfaction || {};
        setSelfServiceStatus('satisfactionStatus', satisfaction.is_answered ? 'Esta pesquisa já foi respondida.' : '', '');
        renderSatisfactionStars(Number(satisfaction.max_rate) || 5, Number(satisfaction.satisfaction) || 0);
    } catch (error) {
        if (DashAttendance.isCurrent(generation, ticketId)) setSelfServiceStatus('satisfactionStatus', error.message || 'Erro ao carregar pesquisa de satisfação.', 'error');
    }
}

function renderSatisfactionStars(maxRate, selected) {
    const container = document.getElementById('satisfactionStars');
    if (!container) return;

    container.innerHTML = Array.from({ length: maxRate }, (_, index) => {
        const value = index + 1;
        return `<button type="button" class="satisfaction-star${value <= selected ? ' is-active' : ''}" data-satisfaction-rate="${value}"><i class="fas fa-star"></i></button>`;
    }).join('');
    container.dataset.selected = String(selected || 0);

    container.querySelectorAll('[data-satisfaction-rate]').forEach(btn => {
        btn.addEventListener('click', () => {
            const value = Number(btn.getAttribute('data-satisfaction-rate'));
            container.dataset.selected = String(value);
            container.querySelectorAll('[data-satisfaction-rate]').forEach(other => {
                other.classList.toggle('is-active', Number(other.getAttribute('data-satisfaction-rate')) <= value);
            });
        });
    });
}

async function submitSatisfaction(event) {
    event.preventDefault();
    const rate = Number(document.getElementById('satisfactionStars')?.dataset.selected || 0);
    if (rate <= 0) {
        setSelfServiceStatus('satisfactionStatus', 'Selecione uma nota antes de enviar.', 'error');
        return;
    }
    return DashAttendance.mutate('ticket_satisfaction.php', {
        satisfaction: rate, comment: document.getElementById('satisfactionComment')?.value.trim() || '',
    }, 'satisfactionStatus');
}
