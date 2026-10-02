// Coordenação da modal operacional (PLAN-20260905-001).
const DashAttendance = {
    ticket: null, generation: 0, cursor: null, events: [], catalog: null, busy: false,
    previousFocus: null, initialProperties: '', initialStatus: '', catalogRequest: 0, restoredDraftForTicket: 0,
    fieldComboboxes: {},
    el(id) { return document.getElementById(id); },
    show(id, visible) { this.el(id)?.classList.toggle('is-hidden', !visible); },
    status(id, message, type = '') { setSelfServiceStatus(id, message, type); },
    init() {
        this.el('attendanceExpand')?.addEventListener('click', () => {
            const expanded = this.el('ticketDetailModal').classList.toggle('is-expanded');
            this.el('attendanceExpand').setAttribute('aria-pressed', String(expanded));
        });
        this.el('attendanceSavePropertiesFooter')?.addEventListener('click', () => {
            this.el('attendanceProperties')?.setAttribute('open', '');
            this.el('attendancePropertiesForm')?.requestSubmit();
        });
        this.el('ticketDetailBody')?.addEventListener('click', e => {
            const button = e.target.closest('[data-attendance-jump]');
            if (!button) return;
            this.jumpTo(button.dataset.attendanceJump || '');
        });
        this.el('attendanceKind')?.addEventListener('change', () => this.kindChanged());
        this.el('attendanceEditDescription')?.addEventListener('click', () => {
            this.el('attendanceDescription').value = this.ticket.content_text || '';
            this.show('attendanceDescriptionForm', true);
            this.el('attendanceDescription').focus({ preventScroll: true });
        });
        this.el('attendanceCancelDescription')?.addEventListener('click', () => this.show('attendanceDescriptionForm', false));
        this.el('attendanceDescriptionForm')?.addEventListener('submit', e => {
            e.preventDefault();
            this.mutate('ticket_update.php', { action: 'update_properties', changes: JSON.stringify({ content: this.el('attendanceDescription').value }) }, 'attendanceDescriptionStatus', () => this.show('attendanceDescriptionForm', false));
        });
        this.el('attendancePropertiesForm')?.addEventListener('submit', async e => {
            e.preventDefault();
            const changes = {};
            for (const [field, id] of Object.entries(this.propertyFields)) {
                const control = this.el(id);
                if (!control.disabled && String(control.value) !== String(this.ticket[field] ?? '')) changes[field] = control.value;
            }
            const statusControl = this.el('attendanceStatus');
            const statusChanged = statusControl && !statusControl.disabled && String(statusControl.value) !== String(this.initialStatus);
            if (!Object.keys(changes).length && !statusChanged) {
                this.status('attendancePropertiesStatus', 'Nenhuma alteração para salvar.');
                return;
            }
            const selectedStatus = statusControl?.value || '';
            if (Object.keys(changes).length) {
                await this.mutate('ticket_update.php', { action: 'update_properties', changes: JSON.stringify(changes) }, 'attendancePropertiesStatus');
            }
            if (statusChanged) {
                if (statusControl) statusControl.value = selectedStatus;
                await this.mutate('ticket_update.php', { action: 'update_status', status: selectedStatus,
                    reason: this.el('attendancePendingReason').value, pendingreasons_id: this.el('attendancePendingType').value }, 'attendanceStateStatus');
            }
        });
        this.el('attendanceTake')?.addEventListener('click', () => this.mutate('ticket_update.php', { action: 'take' }, 'attendanceActorStatus'));
        this.el('attendanceActors')?.addEventListener('click', e => {
            const button = e.target.closest('[data-remove-actor]');
            if (!button || !confirm('Remover este ator do chamado?')) return;
            this.mutate('ticket_update.php', { action: 'actor', operation: 'remove', role: button.dataset.role,
                itemtype: button.dataset.itemtype, items_id: button.dataset.id }, 'attendanceActorStatus');
        });
        this.el('attendanceActors')?.addEventListener('input', e => {
            const input = e.target.closest('[data-actor-search]');
            if (input) this.renderActorOptions(input, true);
        });
        this.el('attendanceActors')?.addEventListener('focusin', e => {
            const input = e.target.closest('[data-actor-search]');
            if (input) this.renderActorOptions(input, true);
        });
        this.el('attendanceActors')?.addEventListener('keydown', e => {
            const input = e.target.closest('[data-actor-search]');
            if (!input) return;
            if (e.key === 'Escape') {
                const box = input.closest('.attendance-actor-field')?.querySelector('[data-actor-options]');
                if (box && !box.hidden) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeActorOptions(input);
                }
            }
            if (e.key === 'Enter') {
                const first = input.closest('.attendance-actor-field')?.querySelector('[data-actor-value]');
                if (!first) return;
                e.preventDefault();
                this.addActorFromValue(input.dataset.actorRole, first.dataset.actorValue);
            }
        });
        this.el('attendanceActors')?.addEventListener('mousedown', e => {
            const option = e.target.closest('[data-actor-value]');
            if (!option) return;
            e.preventDefault();
            this.addActorFromValue(option.dataset.actorRole, option.dataset.actorValue);
        });
        document.addEventListener('click', e => {
            if (e.target.closest('#attendanceActors')) return;
            this.el('attendanceActors')?.querySelectorAll('.attendance-actor-options').forEach(box => { box.hidden = true; });
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
        this.initFieldComboboxes();
        this.bindDraftPersistence();
        let timer;
        this.el('attendanceCatalogSearch')?.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => this.loadCatalog(this.el('attendanceCatalogSearch').value), 300);
        });
        this.el('ticketDetailModal')?.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                this.handleEscape();
                return;
            }
            if (e.key !== 'Tab') return;
            const focusable = [...this.el('ticketDetailModal').querySelectorAll('button, input, select, textarea, summary, a[href], [tabindex="0"]')]
                .filter(el => !el.disabled && el.getClientRects().length);
            if (!focusable.length) return;
            if (e.shiftKey && document.activeElement === focusable[0]) { e.preventDefault(); focusable.at(-1).focus({ preventScroll: true }); }
            if (!e.shiftKey && document.activeElement === focusable.at(-1)) { e.preventDefault(); focusable[0].focus({ preventScroll: true }); }
        });
        window.addEventListener('beforeunload', e => {
            if (this.dirty() || this.busy) { e.preventDefault(); e.returnValue = ''; }
        });
    },
    propertyFields: { name: 'attendanceName', type: 'attendanceType', itilcategories_id: 'attendanceCategory',
        urgency: 'attendanceUrgency', impact: 'attendanceImpact', priority: 'attendancePriority' },
    initFieldComboboxes() {
        if (typeof DashFieldCombobox === 'undefined') return;
        this.fieldComboboxes.category = DashFieldCombobox.bind({
            select: this.el('attendanceCategory'),
            input: this.el('attendanceCategorySearch'),
            optionsBox: this.el('attendanceCategoryOptions'),
            toggle: this.el('attendanceCategoryToggle'),
            emptyText: 'Nenhuma categoria encontrada.',
            rootSelector: '[data-dash-field-combobox="attendanceCategory"]',
        });
    },
    syncFieldComboboxes() {
        Object.values(this.fieldComboboxes).forEach(combo => combo?.setOptions());
    },
    propertiesSnapshot() {
        return JSON.stringify(Object.values(this.propertyFields).map(id => this.el(id)?.value));
    },
    draftKey(ticketId = this.ticket?.id || this.el('ticketDetailTicketId')?.value) {
        return `dashglpi.attendance.draft.${Number(ticketId || 0)}`;
    },
    readDraft(ticketId = this.ticket?.id) {
        try {
            const raw = localStorage.getItem(this.draftKey(ticketId));
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    },
    clearDraft(ticketId = this.ticket?.id) {
        try {
            localStorage.removeItem(this.draftKey(ticketId));
        } catch {
            // localStorage pode estar indisponível em navegação privada.
        }
    },
    collectDraft() {
        if (!this.ticket?.id) return null;
        const properties = {};
        for (const [field, id] of Object.entries(this.propertyFields)) {
            properties[field] = this.el(id)?.value || '';
        }
        return {
            saved_at: new Date().toISOString(),
            kind: this.el('attendanceKind')?.value || 'reply',
            content: this.el('followupContent')?.value || '',
            pending_reason: this.el('attendancePendingReason')?.value || '',
            pending_type: this.el('attendancePendingType')?.value || '',
            status: this.el('attendanceStatus')?.value || '',
            solution_type: this.el('attendanceSolutionType')?.value || '',
            task: {
                duration: this.el('attendanceDuration')?.value || '0',
                state: this.el('attendanceTaskState')?.value || '1',
                user: this.el('attendanceTaskUser')?.value || '',
                group: this.el('attendanceTaskGroup')?.value || '',
                category: this.el('attendanceTaskCategory')?.value || '',
                begin: this.el('attendanceTaskBegin')?.value || '',
                end: this.el('attendanceTaskEnd')?.value || '',
            },
            properties,
        };
    },
    saveDraft() {
        const draft = this.collectDraft();
        if (!draft) return;
        const hasText = Boolean(draft.content.trim() || draft.pending_reason.trim());
        const hasProperties = this.ticket && this.initialProperties && this.propertiesSnapshot() !== this.initialProperties;
        const hasTaskFields = Object.values(draft.task).some(value => String(value || '').trim() !== '' && String(value) !== '0' && String(value) !== '1');
        if (!hasText && !hasProperties && !hasTaskFields) {
            this.clearDraft(this.ticket.id);
            return;
        }
        try {
            localStorage.setItem(this.draftKey(this.ticket.id), JSON.stringify(draft));
        } catch {
            // Sem espaço/permissão no navegador: a proteção de beforeunload ainda evita perda acidental.
        }
    },
    bindDraftPersistence() {
        const ids = [
            'attendanceKind', 'followupContent', 'attendancePendingReason', 'attendancePendingType', 'attendanceStatus',
            'attendanceSolutionType', 'attendanceDuration', 'attendanceTaskState', 'attendanceTaskUser',
            'attendanceTaskGroup', 'attendanceTaskCategory', 'attendanceTaskBegin', 'attendanceTaskEnd',
            ...Object.values(this.propertyFields),
        ];
        ids.forEach(id => this.el(id)?.addEventListener('input', () => this.saveDraft()));
        ids.forEach(id => this.el(id)?.addEventListener('change', () => this.saveDraft()));
    },
    restoreDraft() {
        if (!this.ticket?.id || this.restoredDraftForTicket === Number(this.ticket.id)) return;
        const draft = this.readDraft(this.ticket.id);
        if (!draft) return;
        this.restoredDraftForTicket = Number(this.ticket.id);
        if (draft.kind && [...this.el('attendanceKind').options].some(option => option.value === draft.kind)) {
            this.el('attendanceKind').value = draft.kind;
        }
        this.kindChanged();
        this.el('followupContent').value = draft.content || '';
        this.el('attendancePendingReason').value = draft.pending_reason || '';
        if (draft.pending_type) this.el('attendancePendingType').value = draft.pending_type;
        if (draft.status && [...this.el('attendanceStatus').options].some(option => option.value === draft.status)) {
            this.el('attendanceStatus').value = draft.status;
            this.show('attendancePendingFields', draft.status === '4');
        }
        if (draft.solution_type) this.el('attendanceSolutionType').value = draft.solution_type;
        const task = draft.task || {};
        [['attendanceDuration', 'duration'], ['attendanceTaskState', 'state'], ['attendanceTaskUser', 'user'],
            ['attendanceTaskGroup', 'group'], ['attendanceTaskCategory', 'category'], ['attendanceTaskBegin', 'begin'],
            ['attendanceTaskEnd', 'end']].forEach(([id, key]) => {
            if (task[key] !== undefined) this.el(id).value = task[key];
        });
        for (const [field, value] of Object.entries(draft.properties || {})) {
            const control = this.el(this.propertyFields[field]);
            if (!control || value === undefined) continue;
            if (control instanceof HTMLSelectElement && ![...control.options].some(option => option.value === String(value))) {
                control.add(new Option(`#${value}`, String(value)));
            }
            control.value = String(value);
        }
        this.status('followupStatus', 'Rascunho recuperado neste chamado. Revise antes de salvar no GLPI.', 'success');
    },
    jumpTo(section) {
        if (section === 'properties') this.el('attendanceProperties')?.setAttribute('open', '');
        if (section === 'followup') focusTicketDetailSection('followup');
        const target = section === 'properties'
            ? this.el('attendanceProperties')
            : this.el('ticketDetailModal')?.querySelector(`[data-attendance-section="${section}"]`);
        scrollTicketDetailTarget(target, { behavior: 'smooth', block: 'start' });
        this.el('ticketDetailBody')?.querySelectorAll('[data-attendance-jump]').forEach(button => {
            button.classList.toggle('is-active', button.dataset.attendanceJump === section);
        });
    },
    closeOpenPopovers() {
        let closed = false;
        this.el('attendanceActors')?.querySelectorAll('.attendance-actor-options:not([hidden])').forEach(box => {
            box.hidden = true;
            closed = true;
        });
        this.el('ticketDetailModal')?.querySelectorAll('.ticket-create-combobox-options:not([hidden])').forEach(box => {
            box.hidden = true;
            closed = true;
        });
        this.el('ticketDetailModal')?.querySelectorAll('[role="combobox"][aria-expanded="true"]').forEach(input => {
            input.setAttribute('aria-expanded', 'false');
            closed = true;
        });
        return closed;
    },
    handleEscape() {
        const active = document.activeElement;
        if (this.closeOpenPopovers()) {
            if (active instanceof HTMLElement) active.blur();
            return;
        }
        if (active && this.el('ticketDetailModal')?.contains(active) && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)) {
            active.blur();
            return;
        }
        if (!this.el('attendanceDescriptionForm')?.classList.contains('is-hidden')) {
            const changed = this.el('attendanceDescription')?.value !== (this.ticket?.content_text || '');
            if (changed && !confirm('Descartar a edição da descrição?')) return;
            this.show('attendanceDescriptionForm', false);
            this.el('attendanceEditDescription')?.focus({ preventScroll: true });
            return;
        }
        closeTicketDetailModal();
    },
    dirty() {
        if (!this.el('ticketDetailModal')?.classList.contains('active')) return false;
        return Boolean(this.el('followupContent')?.value.trim() || DashState.followupCreateState.attachments.length
            || (!this.el('attendanceDescriptionForm')?.classList.contains('is-hidden') && this.el('attendanceDescription')?.value !== (this.ticket?.content_text || ''))
            || (this.ticket && this.initialProperties && this.propertiesSnapshot() !== this.initialProperties)
            || (this.ticket && this.initialStatus && String(this.el('attendanceStatus')?.value || '') !== String(this.initialStatus))
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
            target.focus({ preventScroll: true });
        }
    },
    reset(ticketId, trigger = null) {
        this.generation++; this.ticket = null; this.cursor = null; this.events = []; this.catalog = null;
        this.catalogRequest++; this.initialProperties = ''; this.initialStatus = '';
        const source = trigger || document.activeElement;
        if (!this.el('ticketDetailModal').contains(source)) this.previousFocus = source;
        this.el('ticketDetailModal').classList.remove('is-operator', 'is-expanded');
        this.el('attendanceExpand').setAttribute('aria-pressed', 'false');
        this.restoredDraftForTicket = 0;
        const columns = this.el('ticketDetailBody');
        columns.insertBefore(document.querySelector('.ticket-detail-col-followup'), this.el('attendanceProperties'));
        columns.insertBefore(document.querySelector('.ticket-detail-rail'), columns.firstElementChild);
        document.querySelector('.ticket-detail-col-main').prepend(this.el('ticketDetailMeta'));
        ['attendanceProperties', 'attendanceDescriptionForm', 'attendanceEditDescription', 'attendanceMore', 'attendanceKindLabel', 'attendanceTaskFields', 'attendanceSolutionFields', 'attendanceGoToComposer', 'attendanceSavePropertiesFooter'].forEach(id => this.show(id, false));
        this.el('attendanceDescriptionDocuments').replaceChildren();
        this.el('attendanceCatalogSearch').value = '';
        this.el('attendanceProperties').open = window.innerWidth > 880;
        this.el('attendancePendingReason').value = '';
        this.el('followupForm').reset();
        this.el('followupForm').querySelectorAll('button, input, select, textarea').forEach(el => { el.disabled = true; });
        this.status('attendanceCatalogStatus', '');
        this.el('attendanceComposerTitle').textContent = 'Novo acompanhamento';
        this.el('attendanceContentLabel').textContent = 'Mensagem';
        this.el('ticketDetailBody')?.querySelectorAll('[data-attendance-jump]').forEach(button => button.classList.remove('is-active'));
        this.el('ticketDetailBody')?.querySelector('[data-attendance-jump="description"]')?.classList.add('is-active');
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
    optionLabel(item) {
        return String(item?.label || item?.completename || item?.display || item?.text || item?.name || '').trim() || '-';
    },
    options(select, values, selected = '', blank = false) {
        select.replaceChildren();
        const blankLabel = select.id === 'attendanceCategory' ? 'Sem categoria' : 'Selecionar...';
        if (blank) select.add(new Option(blankLabel, '0'));
        values.forEach(item => select.add(new Option(this.optionLabel(item), String(item.id))));
        select.value = String(selected);
        if (select.selectedIndex < 0 && select.options.length) select.selectedIndex = 0;
        if (select.id === 'attendanceCategory') {
            const comboOptions = [
                ...(blank ? [{ id: 0, label: blankLabel }] : []),
                ...values.map(item => ({ ...item, label: this.optionLabel(item) })),
            ];
            this.fieldComboboxes.category?.setOptions(comboOptions);
        }
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
        this.show('attendanceSavePropertiesFooter', operator && (cap.edit || cap.priority || cap.status));
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
                if (key === 'itilcategories_id') this.options(this.el(id), [{ id: ticket[key] || 0, label: ticket.category || 'Sem categoria' }], ticket[key] || 0);
                else this.el(id).value = String(ticket[key] ?? '');
            }
        }
        for (const [key, id] of Object.entries(this.propertyFields)) this.el(id).disabled = key === 'priority' ? !cap.priority : !cap.edit;
        this.el('attendancePropertiesForm').querySelector('button').disabled = !cap.edit && !cap.priority && !cap.status;
        this.initialProperties = this.propertiesSnapshot();
        for (const [key, value] of Object.entries(drafts)) {
            const control = this.el(this.propertyFields[key]);
            if (control instanceof HTMLSelectElement && ![...control.options].some(option => option.value === value)) control.add(new Option(`#${value}`, value));
            control.value = value;
        }
        this.syncFieldComboboxes();
        this.show('attendanceTake', cap.take);
        this.show('attendanceStatusForm', cap.status);
        this.options(this.el('attendanceStatus'), ticket.allowed_statuses || [], ticket.status);
        this.initialStatus = String(ticket.status ?? '');
        this.el('attendanceStatus').disabled = !cap.status;
        this.show('attendancePendingFields', this.el('attendanceStatus').value === '4');
        this.renderActorFields();
        this.kindChanged();
        this.restoreDraft();
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
            scrollTicketDetailTarget(this.el('attendancePendingFields'), { behavior: 'smooth', block: 'center' });
            this.el('attendancePendingReason').focus({ preventScroll: true });
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
                if (current && current !== '0' && !values.some(v => String(v.id) === current)) values.unshift({ id: current, label: label || `#${current}` });
                this.options(select, values, current, true);
            });
            this.renderActorFields();
            this.status('attendanceCatalogStatus', Object.values(this.catalog.has_more || {}).some(Boolean) ? 'Há mais opções. Digite um nome para refinar a busca.' : '');
        } catch (error) {
            if (this.isCurrent(generation, id)) this.status('attendanceCatalogStatus', error.message, 'error');
        }
    },
    normalizeTerm(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    },
    actorRoles() {
        const cap = this.ticket?.capabilities || {};
        return [
            { role: 'requester', label: 'Requerente', icon: 'fa-user', canEdit: Boolean(cap.actors) },
            { role: 'observer', label: 'Observador', icon: 'fa-user', canEdit: Boolean(cap.actors) },
            { role: 'assign', label: 'Atribuído', icon: 'fa-user-cog', canEdit: Boolean(cap.assign) },
        ];
    },
    actorValue(itemtype, id) {
        return `${itemtype}:${Number(id || 0)}`;
    },
    actorName(actor) {
        return actor?.name || actor?.text || `${actor?.itemtype || 'User'} #${actor?.items_id || actor?.id || 0}`;
    },
    actorOptions(role) {
        const selected = new Set((this.ticket?.actors?.[role] || []).map(actor => this.actorValue(actor.itemtype || 'User', actor.items_id || actor.id)));
        const users = role === 'assign' ? (this.catalog?.technicians || []) : (this.catalog?.users || []);
        const options = users.map(user => ({ id: Number(user.id), itemtype: 'User', label: this.optionLabel(user), icon: 'fa-user' }));
        if (role === 'assign') {
            options.push(...(this.catalog?.groups || []).map(group => ({
                id: Number(group.id),
                itemtype: 'Group',
                label: this.optionLabel(group),
                icon: 'fa-users',
            })));
        }
        return options.filter(option => option.id > 0 && !selected.has(this.actorValue(option.itemtype, option.id)));
    },
    renderActorFields() {
        const actors = this.ticket?.actors || {};
        const total = Object.values(actors).reduce((sum, list) => sum + (Array.isArray(list) ? list.length : 0), 0);
        this.el('attendanceActors').innerHTML = `
            <div class="attendance-actors-header">
                <span><i class="fas fa-users" aria-hidden="true"></i> Atores</span>
                <strong>${Number(total)}</strong>
            </div>
            ${this.actorRoles().map(config => {
                const list = actors[config.role] || [];
                return `
                    <div class="attendance-actor-field" data-actor-role="${escHtml(config.role)}">
                        <div class="attendance-actor-label">
                            <span>${escHtml(config.label)}</span>
                            <i class="fas ${escHtml(config.icon)}" aria-hidden="true"></i>
                        </div>
                        <div class="attendance-actor-picker${config.canEdit ? '' : ' is-readonly'}">
                            <div class="attendance-actor-tags">
                                ${list.map(actor => `
                                    <span class="attendance-actor-tag">
                                        ${config.canEdit && Number(actor.items_id) > 0 ? `<button type="button" data-remove-actor data-role="${escHtml(config.role)}" data-itemtype="${escHtml(actor.itemtype || 'User')}" data-id="${Number(actor.items_id)}" aria-label="Remover ${escHtml(this.actorName(actor))}">×</button>` : ''}
                                        <i class="fas ${actor.itemtype === 'Group' ? 'fa-users' : 'fa-user'}" aria-hidden="true"></i>
                                        <span>${escHtml(this.actorName(actor))}</span>
                                    </span>
                                `).join('')}
                                ${config.canEdit ? `<input type="search" data-actor-search data-actor-role="${escHtml(config.role)}" autocomplete="off" aria-label="Pesquisar ${escHtml(config.label)}">` : ''}
                            </div>
                            <div class="attendance-actor-options" data-actor-options="${escHtml(config.role)}" hidden></div>
                        </div>
                    </div>
                `;
            }).join('')}
        `;
    },
    renderActorOptions(input, open = true) {
        const role = input.dataset.actorRole || '';
        const box = input.closest('.attendance-actor-field')?.querySelector('[data-actor-options]');
        if (!box) return;
        const terms = this.normalizeTerm(input.value).split(/\s+/).filter(Boolean);
        const filtered = this.actorOptions(role).filter(option => {
            if (!terms.length) return true;
            const haystack = this.normalizeTerm([option.label, option.itemtype].join(' '));
            return terms.every(term => haystack.includes(term));
        }).slice(0, 50);

        box.innerHTML = filtered.length
            ? filtered.map(option => `<button type="button" data-actor-role="${escHtml(role)}" data-actor-value="${escHtml(this.actorValue(option.itemtype, option.id))}"><i class="fas ${escHtml(option.icon)}" aria-hidden="true"></i><span>${escHtml(option.label)}</span></button>`).join('')
            : '<div class="attendance-actor-empty">Nenhum ator encontrado.</div>';
        box.hidden = !open;
    },
    closeActorOptions(input) {
        const box = input?.closest('.attendance-actor-field')?.querySelector('[data-actor-options]');
        if (box) box.hidden = true;
    },
    addActorFromValue(role, value) {
        const [itemtype, rawId] = String(value || '').split(':');
        const id = Number(rawId || 0);
        if (!role || !itemtype || id <= 0) return;
        this.mutate('ticket_update.php', { action: 'actor', operation: 'add', role, itemtype, items_id: id }, 'attendanceActorStatus');
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
            this.clearDraft(id);
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
                this.el('attendancePropertiesForm').querySelector('button').disabled = !cap.edit && !cap.priority && !cap.status;
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
