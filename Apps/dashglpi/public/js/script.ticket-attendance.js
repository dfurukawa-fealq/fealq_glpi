// Coordenação da modal operacional (PLAN-20260905-001), sem estado persistido no navegador.
const DashAttendance = {
    ticket: null, generation: 0, cursor: null, events: [], catalog: null, busy: false,
    previousFocus: null, initialProperties: '', catalogRequest: 0,
    el(id) { return document.getElementById(id); },
    show(id, visible) { this.el(id)?.classList.toggle('is-hidden', !visible); },
    status(id, message, type = '') { setSelfServiceStatus(id, message, type); },
    init() {
        this.el('attendanceExpand')?.addEventListener('click', () => {
            const expanded = this.el('ticketDetailModal').classList.toggle('is-expanded');
            this.el('attendanceExpand').setAttribute('aria-pressed', String(expanded));
        });
        this.el('attendanceKind')?.addEventListener('change', () => this.kindChanged());
        this.el('attendanceEditDescription')?.addEventListener('click', () => {
            this.el('attendanceDescription').value = this.ticket.content_text || '';
            this.show('attendanceDescriptionForm', true);
            this.el('attendanceDescription').focus();
        });
        this.el('attendanceCancelDescription')?.addEventListener('click', () => this.show('attendanceDescriptionForm', false));
        this.el('attendanceDescriptionForm')?.addEventListener('submit', e => {
            e.preventDefault();
            this.mutate('ticket_update.php', { action: 'update_properties', changes: JSON.stringify({ content: this.el('attendanceDescription').value }) }, 'attendanceDescriptionStatus', () => this.show('attendanceDescriptionForm', false));
        });
        this.el('attendancePropertiesForm')?.addEventListener('submit', e => {
            e.preventDefault();
            const changes = {};
            for (const [field, id] of Object.entries(this.propertyFields)) {
                const control = this.el(id);
                if (!control.disabled && String(control.value) !== String(this.ticket[field] ?? '')) changes[field] = control.value;
            }
            this.mutate('ticket_update.php', { action: 'update_properties', changes: JSON.stringify(changes) }, 'attendancePropertiesStatus');
        });
        this.el('attendanceTake')?.addEventListener('click', () => this.mutate('ticket_update.php', { action: 'take' }, 'attendanceActorStatus'));
        this.el('attendanceActorRole')?.addEventListener('change', () => this.actorCatalog());
        this.el('attendanceActorType')?.addEventListener('change', () => this.actorCatalog());
        this.el('attendanceActorForm')?.addEventListener('submit', e => {
            e.preventDefault();
            this.mutate('ticket_update.php', { action: 'actor', operation: 'add', role: this.el('attendanceActorRole').value,
                itemtype: this.el('attendanceActorType').value, items_id: this.el('attendanceActorId').value }, 'attendanceActorStatus');
        });
        this.el('attendanceActors')?.addEventListener('click', e => {
            const button = e.target.closest('[data-remove-actor]');
            if (!button || !confirm('Remover este ator do chamado?')) return;
            this.mutate('ticket_update.php', { action: 'actor', operation: 'remove', role: button.dataset.role,
                itemtype: button.dataset.itemtype, items_id: button.dataset.id }, 'attendanceActorStatus');
        });
        this.el('attendanceStatus')?.addEventListener('change', () => this.show('attendancePendingFields', this.el('attendanceStatus').value === '4'));
        this.el('attendanceStatusForm')?.addEventListener('submit', e => {
            e.preventDefault();
            this.mutate('ticket_update.php', { action: 'update_status', status: this.el('attendanceStatus').value,
                reason: this.el('attendancePendingReason').value, pendingreasons_id: this.el('attendancePendingType').value }, 'attendanceStateStatus');
        });
        this.el('attendanceMore')?.addEventListener('click', () => this.loadTimeline(true));
        this.el('attendanceGoToComposer')?.addEventListener('click', () => focusTicketDetailSection('followup'));
        this.el('attendanceReload')?.addEventListener('click', () => this.refresh(true));
        let timer;
        this.el('attendanceCatalogSearch')?.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => this.loadCatalog(this.el('attendanceCatalogSearch').value), 300);
        });
        this.el('ticketDetailModal')?.addEventListener('keydown', e => {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeTicketDetailModal(); }
            if (e.key !== 'Tab') return;
            const focusable = [...this.el('ticketDetailModal').querySelectorAll('button, input, select, textarea, summary, a[href], [tabindex="0"]')]
                .filter(el => !el.disabled && el.getClientRects().length);
            if (!focusable.length) return;
            if (e.shiftKey && document.activeElement === focusable[0]) { e.preventDefault(); focusable.at(-1).focus(); }
            if (!e.shiftKey && document.activeElement === focusable.at(-1)) { e.preventDefault(); focusable[0].focus(); }
        });
        window.addEventListener('beforeunload', e => {
            if (this.dirty() || this.busy) { e.preventDefault(); e.returnValue = ''; }
        });
    },
    propertyFields: { name: 'attendanceName', type: 'attendanceType', itilcategories_id: 'attendanceCategory',
        urgency: 'attendanceUrgency', impact: 'attendanceImpact', priority: 'attendancePriority' },
    propertiesSnapshot() {
        return JSON.stringify(Object.values(this.propertyFields).map(id => this.el(id)?.value));
    },
    dirty() {
        if (!this.el('ticketDetailModal')?.classList.contains('active')) return false;
        return Boolean(this.el('followupContent')?.value.trim() || DashState.followupCreateState.attachments.length
            || (!this.el('attendanceDescriptionForm')?.classList.contains('is-hidden') && this.el('attendanceDescription')?.value !== (this.ticket?.content_text || ''))
            || (this.ticket && this.initialProperties && this.propertiesSnapshot() !== this.initialProperties)
            || this.el('attendancePendingReason')?.value.trim());
    },
    canClose() {
        if (this.busy) { this.status('followupStatus', 'Aguarde a confirmação da operação antes de fechar.'); return false; }
        return !this.dirty() || confirm('Descartar as alterações e os anexos ainda não enviados?');
    },
    restoreFocus() {
        const fallback = document.querySelector(`[data-ticket-detail="${Number(this.ticket?.id || 0)}"]`);
        const target = this.previousFocus?.isConnected ? this.previousFocus : fallback;
        if (target) {
            if (!target.matches('button, input, select, textarea, a[href], [tabindex]')) target.tabIndex = -1;
            target.focus();
        }
    },
    reset(ticketId, trigger = null) {
        this.generation++; this.ticket = null; this.cursor = null; this.events = []; this.catalog = null;
        this.catalogRequest++; this.initialProperties = '';
        const source = trigger || document.activeElement;
        if (!this.el('ticketDetailModal').contains(source)) this.previousFocus = source;
        this.el('ticketDetailModal').classList.remove('is-operator', 'is-expanded');
        this.el('attendanceExpand').setAttribute('aria-pressed', 'false');
        const columns = this.el('ticketDetailBody');
        columns.insertBefore(document.querySelector('.ticket-detail-col-followup'), this.el('attendanceProperties'));
        document.querySelector('.ticket-detail-col-main').prepend(this.el('ticketDetailMeta'));
        ['attendanceProperties', 'attendanceDescriptionForm', 'attendanceEditDescription', 'attendanceMore', 'attendanceKindLabel', 'attendanceTaskFields', 'attendanceSolutionFields', 'attendanceGoToComposer'].forEach(id => this.show(id, false));
        this.el('attendanceDescriptionDocuments').replaceChildren();
        this.el('attendanceCatalogSearch').value = '';
        this.el('attendanceProperties').open = window.innerWidth > 880;
        this.el('attendancePendingReason').value = '';
        this.el('followupForm').reset();
        this.el('followupForm').querySelectorAll('button, input, select, textarea').forEach(el => { el.disabled = true; });
        this.status('attendanceCatalogStatus', '');
        this.el('attendanceComposerTitle').textContent = 'Novo acompanhamento';
        this.el('attendanceContentLabel').textContent = 'Mensagem';
    },
    isCurrent(generation, ticketId) {
        return generation === this.generation && Number(this.el('ticketDetailTicketId')?.value) === Number(ticketId) && this.el('ticketDetailModal').classList.contains('active');
    },
    renderContent(element, item) {
        if (!element) return;
        if (item.content_format === 'sanitized_html' && typeof item.content_html === 'string') {
            element.innerHTML = item.content_html; // Somente HTML sanitizado pelo bridge nativo.
            element.classList.add('attendance-rich-content');
        } else {
            element.textContent = item.content_text || item.content || 'Sem descrição.';
            element.classList.remove('attendance-rich-content');
        }
        if (item.content_truncated) {
            const note = document.createElement('p'); note.textContent = 'Conteúdo extenso: visualização limitada.'; element.append(note);
        }
    },
    documentHTML(doc) {
        const id = Number(doc.id), ticketId = Number(this.ticket?.id || this.el('ticketDetailTicketId').value);
        const base = `${PLUGIN_ROOT}/front/document-preview.php?ticket_id=${ticketId}&document_id=${id}`;
        return `<a class="attendance-document" href="${base}&download=1" target="_blank" rel="noopener noreferrer">${doc.is_image ? `<img src="${base}" loading="lazy" alt="${escHtml(doc.name || 'Imagem')}">` : '<i class="fas fa-paperclip" aria-hidden="true"></i>'}<span>${escHtml(doc.name || 'Documento')}</span></a>`;
    },
    options(select, values, selected = '', blank = false) {
        select.replaceChildren();
        if (blank) select.add(new Option('Selecionar...', '0'));
        values.forEach(item => select.add(new Option(item.name, String(item.id))));
        select.value = String(selected);
        if (select.selectedIndex < 0 && select.options.length) select.selectedIndex = 0;
    },
    render(ticket) {
        const preserve = this.ticket?.id === ticket.id && this.initialProperties && this.propertiesSnapshot() !== this.initialProperties;
        const drafts = {};
        if (preserve) {
            for (const [key, id] of Object.entries(this.propertyFields)) {
                if (String(this.el(id).value) !== String(this.ticket[key] ?? '')) drafts[key] = this.el(id).value;
            }
        }
        this.ticket = ticket;
        const cap = ticket.capabilities || {}, operator = cap.mode === 'operator';
        this.el('ticketDetailModal').classList.toggle('is-operator', operator);
        this.renderContent(this.el('ticketDetailContent'), ticket);
        this.el('attendanceDescriptionDocuments').innerHTML = (ticket.documents || []).map(d => this.documentHTML(d)).join('');
        if (ticket.documents_truncated) this.el('attendanceDescriptionDocuments').append(document.createTextNode('Há mais documentos; esta visualização mostra os primeiros 100.'));
        this.show('attendanceEditDescription', cap.edit && !ticket.content_truncated);
        this.show('attendanceProperties', operator);
        if (operator) {
            document.querySelector('.ticket-detail-col-main').append(document.querySelector('.ticket-detail-col-followup'));
            this.el('attendancePropertiesMeta').append(this.el('ticketDetailMeta'));
        }
        const canCompose = Boolean(cap.reply || cap.private || cap.task || cap.solution);
        this.show('attendanceGoToComposer', operator && canCompose);
        document.querySelector('.ticket-detail-col-followup').classList.toggle('is-hidden', !canCompose);
        this.el('followupForm').querySelectorAll('button, input, select, textarea').forEach(el => { el.disabled = !canCompose; });
        const kinds = [['reply', 'Resposta ao solicitante'], ['private', 'Nota interna'], ['task', 'Tarefa'], ['solution', 'Solução']]
            .filter(([key]) => cap[key]).map(([id, name]) => ({ id, name }));
        this.options(this.el('attendanceKind'), kinds, this.el('attendanceKind').value || 'reply');
        this.show('attendanceKindLabel', operator);
        this.el('attendanceComposerTitle').textContent = operator ? 'Atender chamado' : 'Novo acompanhamento';
        this.el('attendanceContentLabel').textContent = operator ? 'Descrição do atendimento' : 'Mensagem';
        this.el('followupContent').placeholder = operator ? 'Descreva o atendimento realizado...' : 'Escreva uma atualização sobre o seu chamado...';
        this.show('solutionSection', cap.approve);
        // Cancelamento legado permanece somente na experiência de solicitante.
        this.show('ticketDetailCancelToggle', Boolean(cap.cancel));
        this.show('satisfactionSection', cap.mode === 'self_service' && Number(ticket.satisfaction_pending) === 1);
        this.el('ticketDetailMeta').innerHTML = [
            ['Entidade', ticket.entity_name], ['Categoria', ticket.category], ['Origem', ticket.origin_name],
            ['Criado em', formatDateTime(ticket.date)], ['Atualizado em', formatDateTime(ticket.date_mod)],
            ['Solução', ticket.solvedate ? formatDateTime(ticket.solvedate) : '—'],
            ['Prazo de solução', ticket.time_to_resolve ? formatDateTime(ticket.time_to_resolve) : '—'],
        ].map(([label, value]) => `<div class="ticket-detail-meta-item"><span>${escHtml(label)}</span><strong>${escHtml(value || '—')}</strong></div>`).join('');
        {
            const levels = ['Muito baixa', 'Baixa', 'Média', 'Alta', 'Muito alta', 'Maior'];
            ['attendanceUrgency', 'attendanceImpact', 'attendancePriority'].forEach(id => this.options(this.el(id), levels.slice(0, id === 'attendancePriority' ? 6 : 5).map((name, i) => ({ id: i + 1, name })), ticket[id === 'attendanceUrgency' ? 'urgency' : id === 'attendanceImpact' ? 'impact' : 'priority']));
            for (const [key, id] of Object.entries(this.propertyFields)) {
                if (key === 'itilcategories_id') this.options(this.el(id), [{ id: ticket[key] || 0, name: ticket.category || 'Sem categoria' }], ticket[key] || 0);
                else this.el(id).value = String(ticket[key] ?? '');
            }
        }
        for (const [key, id] of Object.entries(this.propertyFields)) this.el(id).disabled = key === 'priority' ? !cap.priority : !cap.edit;
        this.el('attendancePropertiesForm').querySelector('button').disabled = !cap.edit && !cap.priority;
        this.initialProperties = this.propertiesSnapshot();
        for (const [key, value] of Object.entries(drafts)) {
            const control = this.el(this.propertyFields[key]);
            if (control instanceof HTMLSelectElement && ![...control.options].some(option => option.value === value)) control.add(new Option(`#${value}`, value));
            control.value = value;
        }
        this.show('attendanceTake', cap.take);
        this.show('attendanceActorForm', cap.assign || cap.actors);
        this.show('attendanceStatusForm', cap.status);
        this.options(this.el('attendanceActorRole'), [['assign', 'Técnico / grupo'], ['requester', 'Requerente'], ['observer', 'Observador']]
            .filter(([key]) => key === 'assign' ? cap.assign : cap.actors).map(([id, name]) => ({ id, name })), this.el('attendanceActorRole').value);
        this.options(this.el('attendanceStatus'), ticket.allowed_statuses || [], ticket.status);
        this.show('attendancePendingFields', this.el('attendanceStatus').value === '4');
        const roleNames = { requester: 'Requerentes', observer: 'Observadores', assign: 'Técnicos e grupos' };
        this.el('attendanceActors').innerHTML = Object.entries(ticket.actors || {}).map(([role, actors]) => `<div class="attendance-actor-list"><strong>${roleNames[role] || escHtml(role)}</strong>${actors.map(actor => `<div><span>${escHtml(actor.name || actor.text || `${actor.itemtype} #${actor.items_id}`)}</span>${(role === 'assign' ? cap.assign : cap.actors) && Number(actor.items_id) > 0 ? `<button type="button" class="page-action-btn" data-remove-actor data-role="${role}" data-itemtype="${escHtml(actor.itemtype)}" data-id="${Number(actor.items_id)}" aria-label="Remover ${escHtml(actor.name || 'ator')}">×</button>` : ''}</div>`).join('') || '<span>Nenhum</span>'}</div>`).join('');
        this.kindChanged();
        if (ticket.upload_max_label) { DashState.followupCreateState.uploadMaxLabel = ticket.upload_max_label; updateFollowupUploadHelp(); }
        if (operator && !this.catalog) this.loadCatalog();
    },
    kindChanged() {
        const kind = this.el('attendanceKind').value;
        this.show('attendanceTaskFields', kind === 'task'); this.show('attendanceSolutionFields', kind === 'solution');
        const label = { reply: 'Enviar resposta', private: 'Registrar nota interna', task: 'Registrar tarefa', solution: 'Registrar solução' }[kind] || 'Enviar acompanhamento';
        this.el('followupForm').querySelector('button[type="submit"]').textContent = label;
        this.el('followupForm').classList.toggle('is-private', kind === 'private');
    },
    focusStatus(status) {
        const cap = this.ticket?.capabilities || {};
        if (status === 5 && cap.solution) {
            this.el('attendanceKind').value = 'solution';
            this.kindChanged();
            focusTicketDetailSection('followup');
        } else if (status === 4 && cap.status && (this.ticket.allowed_statuses || []).some(value => Number(value.id) === 4)) {
            this.el('attendanceProperties').open = true;
            this.el('attendanceStatus').value = '4';
            this.show('attendancePendingFields', true);
            this.el('attendancePendingReason').focus();
        } else if (status === 6 && cap.approve) {
            focusTicketDetailSection('solution');
        } else {
            this.status('followupStatus', 'Esta mudança de status não está disponível para seu perfil no estado atual do chamado.', 'error');
        }
    },
    async loadCatalog(query = '') {
        if (!this.ticket) return;
        const generation = this.generation, id = this.ticket.id, request = ++this.catalogRequest;
        this.status('attendanceCatalogStatus', 'Carregando opções...');
        try {
            const res = await fetch(`${PLUGIN_ROOT}/ajax/ticket_attendance.php?ticket_id=${id}&q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!this.isCurrent(generation, id) || request !== this.catalogRequest) return;
            if (!data.ok) throw new Error(data.error || 'Não foi possível carregar as opções.');
            this.catalog = data.catalog;
            document.querySelectorAll('[data-attendance-catalog]').forEach(select => {
                const current = select.value, label = select.selectedOptions[0]?.textContent;
                const values = [...(this.catalog[select.dataset.attendanceCatalog] || [])];
                if (current && current !== '0' && !values.some(v => String(v.id) === current)) values.unshift({ id: current, name: label || `#${current}` });
                this.options(select, values, current, true);
            });
            this.actorCatalog();
            this.status('attendanceCatalogStatus', Object.values(this.catalog.has_more || {}).some(Boolean) ? 'Há mais opções. Digite um nome para refinar a busca.' : '');
        } catch (error) {
            if (this.isCurrent(generation, id)) this.status('attendanceCatalogStatus', error.message, 'error');
        }
    },
    actorCatalog() {
        const role = this.el('attendanceActorRole').value;
        if (role !== 'assign') this.el('attendanceActorType').value = 'User';
        this.el('attendanceActorType').disabled = role !== 'assign';
        const key = this.el('attendanceActorType').value === 'Group' ? 'groups' : role === 'assign' ? 'technicians' : 'users';
        const select = this.el('attendanceActorId'); select.dataset.attendanceCatalog = key;
        this.options(select, this.catalog?.[key] || [], '', true);
    },
    async loadTimeline(append = false) {
        const id = Number(this.el('ticketDetailTicketId').value), generation = this.generation;
        const cursor = append ? this.cursor : '';
        const button = this.el('attendanceMore'); button.disabled = true;
        try {
            const res = await fetch(`${PLUGIN_ROOT}/ajax/ticket_followup.php?ticket_id=${id}&cursor=${encodeURIComponent(cursor || '')}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!this.isCurrent(generation, id)) return;
            if (!data.ok) throw new Error(data.error || 'Erro ao carregar histórico.');
            const events = Array.isArray(data.followups) ? data.followups : [];
            this.events = append ? [...this.events, ...events] : events;
            this.events = [...new Map(this.events.map(e => [`${e.itemtype}:${e.id}`, e])).values()];
            this.cursor = data.next_cursor;
            renderFollowupTimeline(this.events);
            if (data.documents_truncated) this.el('followupTimeline').append(document.createTextNode('Há mais anexos nesta página; a visualização foi limitada a 100 documentos.'));
            this.show('attendanceMore', Boolean(this.cursor));
            DashState.followupCreateState.uploadMaxLabel = data.upload_max_label || ''; updateFollowupUploadHelp();
        } catch (error) {
            if (this.isCurrent(generation, id)) {
                if (!append) this.el('followupTimeline').textContent = error.message;
                else this.status('followupStatus', error.message, 'error');
            }
        } finally { if (this.isCurrent(generation, id)) button.disabled = false; }
    },
    async refresh(preserve = false) {
        const id = Number(this.el('ticketDetailTicketId').value), generation = this.generation;
        if (!id) return;
        if (!preserve) this.initialProperties = '';
        await Promise.all([loadTicketDetail(id, DashState.ticketDetailItemtype || 'ticket'),
            DashState.ticketDetailItemtype === 'ticket' ? this.loadTimeline() : Promise.resolve()]);
        if (this.isCurrent(generation, id) && !preserve) await loadTicketLists();
    },
    async mutate(endpoint, payload, statusId, onSuccess = null, files = null) {
        if (this.busy || !this.ticket) return;
        this.busy = true;
        const id = this.ticket.id, generation = this.generation;
        const form = new FormData();
        Object.entries({ ...payload, ticket_id: id, revision: this.ticket.revision, csrf_token: DASHGLPI_CSRF_TOKEN }).forEach(([key, value]) => form.set(key, String(value)));
        (files || []).forEach(entry => { if (entry.file instanceof File) form.append('attachments[]', entry.file); });
        this.el('ticketDetailModal').setAttribute('aria-busy', 'true');
        const enabledButtons = [...this.el('ticketDetailModal').querySelectorAll('button')].filter(button => !button.disabled);
        enabledButtons.forEach(button => { button.disabled = true; });
        this.status(statusId, 'Salvando...');
        let saved = false;
        try {
            const res = await fetch(`${PLUGIN_ROOT}/ajax/${endpoint}`, { method: 'POST', body: form });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'GLPI não confirmou a operação.');
            saved = true;
            if (!this.isCurrent(generation, id)) return;
            onSuccess?.();
            if (statusId === 'attendanceStateStatus') this.el('attendancePendingReason').value = '';
            await this.refresh(statusId !== 'attendancePropertiesStatus');
            await loadTicketLists();
            this.status(statusId, 'Alteração registrada no GLPI.', 'success');
        } catch (error) {
            if (this.isCurrent(generation, id)) {
                this.status(statusId, `${error.message} ${saved ? 'A gravação foi confirmada; recarregue os dados.' : 'Seu texto foi preservado. Recarregue os dados e confira o histórico antes de reenviar.'}`, 'error');
            }
        } finally {
            this.busy = false;
            this.el('ticketDetailModal').removeAttribute('aria-busy');
            enabledButtons.forEach(button => { button.disabled = false; });
            if (this.ticket) {
                const cap = this.ticket.capabilities || {};
                this.el('attendancePropertiesForm').querySelector('button').disabled = !cap.edit && !cap.priority;
                this.el('followupForm').querySelector('button[type="submit"]').disabled = !cap.reply && !cap.private && !cap.task && !cap.solution;
            }
        }
    },
    submit(event) {
        event.preventDefault();
        const kind = this.el('attendanceKind').value || 'reply';
        const endpoint = kind === 'task' ? 'ticket_task.php' : kind === 'solution' ? 'ticket_solution.php' : 'ticket_followup.php';
        const dateValue = id => this.el(id).value ? `${this.el(id).value.replace('T', ' ')}:00` : '';
        const payload = { action: 'add', content: this.el('followupContent').value, is_private: kind === 'private' ? 1 : 0,
            duration_minutes: this.el('attendanceDuration').value, state: this.el('attendanceTaskState').value,
            users_id_tech: this.el('attendanceTaskUser').value, groups_id_tech: this.el('attendanceTaskGroup').value,
            taskcategories_id: this.el('attendanceTaskCategory').value, begin: dateValue('attendanceTaskBegin'), end: dateValue('attendanceTaskEnd'),
            solutiontypes_id: this.el('attendanceSolutionType').value };
        if (!payload.content.trim()) { this.status('followupStatus', 'Preencha a descrição do atendimento.', 'error'); return; }
        this.mutate(endpoint, payload, 'followupStatus', () => { this.el('followupContent').value = ''; clearFollowupAttachments(); }, DashState.followupCreateState.attachments);
    },
};

// Atalhos da fila usam a mesma revisão e o mesmo backend da modal.
const dashglpiAttendancePendingUpdates = new Set();
async function dashglpiAttendanceQuickUpdate(ticketId, payload) {
    if (dashglpiAttendancePendingUpdates.has(ticketId)) return { ok: false, error: 'Aguarde a operação em andamento.' };
    dashglpiAttendancePendingUpdates.add(ticketId);
    try {
        const response = await fetch(`${PLUGIN_ROOT}/ajax/ticket_detail.php?ticket_id=${encodeURIComponent(ticketId)}`, { headers: { Accept: 'application/json' } });
        const detail = await response.json();
        if (!response.ok || !detail.ok || !detail.ticket?.revision) throw new Error(detail.error || 'Não foi possível consultar o chamado.');
        const form = new FormData();
        Object.entries({ ...payload, ticket_id: ticketId, revision: detail.ticket.revision, csrf_token: DASHGLPI_CSRF_TOKEN }).forEach(([key, value]) => form.set(key, String(value)));
        const result = await fetch(`${PLUGIN_ROOT}/ajax/ticket_update.php`, { method: 'POST', headers: { Accept: 'application/json' }, body: form });
        return await result.json();
    } finally {
        dashglpiAttendancePendingUpdates.delete(ticketId);
    }
}
