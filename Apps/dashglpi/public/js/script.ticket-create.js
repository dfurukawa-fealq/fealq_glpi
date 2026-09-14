// ==================== TICKET CREATE ====================
// Extraído de script.js pelo PLAN-20260703-013 (Fase 3.3) — carregado logo
// após script.js (e demais módulos) via <script> separado.

function initTicketCreateSection() {
    const form = document.getElementById('ticketCreateForm');
    if (!form) {
        return;
    }

    const fileInput = document.getElementById('ticketCreateAttachments');
    const uploadZone = document.getElementById('ticketCreateUploadZone');
    const attachmentsList = document.getElementById('ticketCreateAttachmentsList');

    form.addEventListener('submit', handleTicketCreateSubmit);
    form.addEventListener('paste', handleTicketCreatePaste);
    document.getElementById('ticketCreateReset')?.addEventListener('click', () => {
        resetTicketCreateForm();
    });
    document.getElementById('ticketCreateAgain')?.addEventListener('click', () => {
        resetTicketCreateForm();
    });
    document.getElementById('ticketCreateEntity')?.addEventListener('change', (event) => {
        loadTicketCreateCatalog({
            force: true,
            entitiesId: Number(event.target.value || 0),
            type: Number(document.getElementById('ticketCreateType')?.value || 1),
        });
    });
    document.getElementById('ticketCreateType')?.addEventListener('change', (event) => {
        loadTicketCreateCatalog({
            force: true,
            entitiesId: Number(document.getElementById('ticketCreateEntity')?.value || 0),
            type: Number(event.target.value || 1),
        });
    });
    document.getElementById('ticketCreatePickFiles')?.addEventListener('click', (event) => {
        event.preventDefault();
        fileInput?.click();
    });
    fileInput?.addEventListener('change', handleTicketCreateFileSelection);
    attachmentsList?.addEventListener('click', handleTicketCreateAttachmentListClick);

    if (uploadZone) {
        uploadZone.addEventListener('click', (event) => {
            if (event.target.closest('button')) {
                return;
            }
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
                if (eventName === 'dragleave' && uploadZone.contains(event.relatedTarget)) {
                    return;
                }
                uploadZone.classList.remove('is-dragover');
            });
        });
        uploadZone.addEventListener('drop', (event) => {
            const files = Array.from(event.dataTransfer?.files || []);
            const added = addTicketCreateFiles(files);
            if (added > 0) {
                setTicketCreateStatus(`${added} anexo(s) adicionado(s) ao chamado.`, 'success');
            }
        });
    }

    renderTicketCreateAttachments();
    updateTicketCreateUploadHelp();
}

function ticketCreateFileKey(file) {
    return `${String(file?.name || '')}::${Number(file?.size || 0)}::${Number(file?.lastModified || 0)}`;
}

function ticketCreateFileTypeLabel(file) {
    const extension = String(file?.name || '').split('.').pop() || '';
    if (extension !== '') {
        return extension.toUpperCase();
    }
    if (String(file?.type || '').startsWith('image/')) {
        return 'IMG';
    }
    return 'FILE';
}

function ticketCreateFormatFileSize(bytes) {
    const size = Number(bytes || 0);
    if (size <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = size;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }
    const decimals = value >= 10 || unitIndex === 0 ? 0 : 1;
    return `${value.toFixed(decimals)} ${units[unitIndex]}`;
}

function releaseTicketCreateAttachment(entry) {
    if (entry?.previewUrl) {
        URL.revokeObjectURL(entry.previewUrl);
    }
}

function syncTicketCreateAttachmentsInput() {
    const input = document.getElementById('ticketCreateAttachments');
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    if (typeof DataTransfer === 'undefined') {
        return;
    }

    const dataTransfer = new DataTransfer();
    DashState.ticketCreateState.attachments.forEach((entry) => {
        if (entry?.file instanceof File) {
            dataTransfer.items.add(entry.file);
        }
    });
    input.files = dataTransfer.files;
}

function updateTicketCreateUploadHelp() {
    const uploadHelp = document.getElementById('ticketCreateUploadHelp');
    if (!uploadHelp) {
        return;
    }

    const attachmentCount = DashState.ticketCreateState.attachments.length;
    const countLabel = attachmentCount > 0
        ? `${attachmentCount} anexo(s) pronto(s) para envio.`
        : 'Adicione imagens e arquivos do chamado.';
    const maxLabel = DashState.ticketCreateState.catalog?.upload_max_label
        ? `Limite configurado para anexos: ${DashState.ticketCreateState.catalog.upload_max_label}.`
        : 'Os anexos seguem o limite configurado para atendimento.';

    uploadHelp.textContent = `${countLabel} ${maxLabel}`;
}

function renderTicketCreateAttachments() {
    const container = document.getElementById('ticketCreateAttachmentsList');
    if (!container) {
        return;
    }

    if (!DashState.ticketCreateState.attachments.length) {
        container.innerHTML = '<div class="ticket-create-attachments-empty">Nenhum anexo selecionado.</div>';
        updateTicketCreateUploadHelp();
        return;
    }

    container.innerHTML = DashState.ticketCreateState.attachments.map((entry) => {
        const file = entry.file;
        const isImage = String(file?.type || '').startsWith('image/');
        const preview = isImage && entry.previewUrl
            ? `<img src="${escHtml(entry.previewUrl)}" alt="${escHtml(String(file?.name || 'Imagem anexada'))}">`
            : `<span class="ticket-create-attachment-ext">${escHtml(ticketCreateFileTypeLabel(file))}</span>`;

        return `
            <article class="ticket-create-attachment-card">
                <button class="ticket-create-attachment-remove" type="button" data-ticket-attachment-remove="${escHtml(String(entry.id || ''))}" aria-label="Remover anexo">
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

    updateTicketCreateUploadHelp();
}

function addTicketCreateFiles(files) {
    const incomingFiles = Array.from(files || []).filter((file) => file instanceof File);
    if (!incomingFiles.length) {
        return 0;
    }

    const existingKeys = new Set(DashState.ticketCreateState.attachments.map((entry) => entry.key));
    let added = 0;

    incomingFiles.forEach((file) => {
        const fileKey = ticketCreateFileKey(file);
        if (existingKeys.has(fileKey)) {
            return;
        }

        existingKeys.add(fileKey);
        DashState.ticketCreateState.attachments.push({
            id: `ticket-file-${DashState.ticketCreateAttachmentSequence++}`,
            key: fileKey,
            file,
            previewUrl: String(file.type || '').startsWith('image/') ? URL.createObjectURL(file) : '',
        });
        added += 1;
    });

    syncTicketCreateAttachmentsInput();
    renderTicketCreateAttachments();
    return added;
}

function removeTicketCreateAttachmentById(attachmentId) {
    const nextAttachments = [];
    DashState.ticketCreateState.attachments.forEach((entry) => {
        if (String(entry.id) === String(attachmentId)) {
            releaseTicketCreateAttachment(entry);
            return;
        }
        nextAttachments.push(entry);
    });
    DashState.ticketCreateState.attachments = nextAttachments;
    syncTicketCreateAttachmentsInput();
    renderTicketCreateAttachments();
}

function clearTicketCreateAttachments() {
    DashState.ticketCreateState.attachments.forEach(releaseTicketCreateAttachment);
    DashState.ticketCreateState.attachments = [];
    syncTicketCreateAttachmentsInput();
    renderTicketCreateAttachments();
}

function handleTicketCreateFileSelection(event) {
    const input = event.currentTarget;
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const added = addTicketCreateFiles(input.files);
    if (added > 0) {
        setTicketCreateStatus(`${added} anexo(s) preparado(s) para o envio.`, 'success');
    }
}

function handleTicketCreateAttachmentListClick(event) {
    const removeButton = event.target.closest('[data-ticket-attachment-remove]');
    if (!removeButton) {
        return;
    }

    event.preventDefault();
    removeTicketCreateAttachmentById(removeButton.getAttribute('data-ticket-attachment-remove'));
}

function handleTicketCreatePaste(event) {
    const clipboardItems = Array.from(event.clipboardData?.items || []);
    if (!clipboardItems.length) {
        return;
    }

    const pastedFiles = clipboardItems
        .filter((item) => item.kind === 'file')
        .map((item) => item.getAsFile())
        .filter((file) => file instanceof File);

    if (!pastedFiles.length) {
        return;
    }

    event.preventDefault();
    const added = addTicketCreateFiles(pastedFiles);
    if (added > 0) {
        setTicketCreateStatus(`${added} imagem(ns) adicionada(s) ao chamado.`, 'success');
    }
}

function ticketCreateCatalogUrl(options = {}) {
    const entitySelect = document.getElementById('ticketCreateEntity');
    const typeSelect = document.getElementById('ticketCreateType');
    const params = new URLSearchParams({
        action: 'catalog',
        entities_id: String(options.entitiesId ?? Number(entitySelect?.value || DashState.ticketCreateState.catalog?.default_entity_id || 0)),
        type: String(options.type ?? Number(typeSelect?.value || DashState.ticketCreateState.catalog?.selected_type || 1)),
    });
    return `${PLUGIN_ROOT}/ajax/ticket_create.php?${params.toString()}`;
}

async function loadTicketCreateCatalog(options = {}) {
    const form = document.getElementById('ticketCreateForm');
    if (!form || DashState.ticketCreateState.loading) {
        return;
    }

    if (DashState.ticketCreateState.loaded && !options.force) {
        return;
    }

    DashState.ticketCreateState.loading = true;
    setTicketCreateStatus('Carregando opcoes do chamado...', '');

    try {
        const response = await fetch(ticketCreateCatalogUrl(options));
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Erro ao carregar opcoes do chamado.');
        }

        DashState.ticketCreateState.loaded = true;
        DashState.ticketCreateState.catalog = data.catalog || {};
        renderTicketCreateCatalog(DashState.ticketCreateState.catalog);
        setTicketCreateStatus('', '');
    } catch (error) {
        setTicketCreateStatus(error.message || 'Erro ao carregar opcoes do chamado.', 'error');
    } finally {
        DashState.ticketCreateState.loading = false;
    }
}

function renderTicketCreateCatalog(catalog) {
    const entitySelect = document.getElementById('ticketCreateEntity');
    const typeSelect = document.getElementById('ticketCreateType');
    const urgencySelect = document.getElementById('ticketCreateUrgency');
    const categorySelect = document.getElementById('ticketCreateCategory');
    if (!entitySelect || !typeSelect || !urgencySelect || !categorySelect) {
        return;
    }

    fillSelectOptions(entitySelect, catalog.entities || [], catalog.selected_entity_id);
    fillSelectOptions(typeSelect, catalog.types || [], catalog.selected_type);
    fillSelectOptions(urgencySelect, catalog.urgencies || [], catalog.default_urgency || 3);

    const previousCategoryId = Number(categorySelect.value || 0);
    const availableCategories = Array.isArray(catalog.categories) ? catalog.categories : [];
    const selectedCategoryId = availableCategories.some((item) => Number(item.id) === previousCategoryId)
        ? previousCategoryId
        : 0;
    fillSelectOptions(
        categorySelect,
        availableCategories,
        selectedCategoryId,
        { id: 0, label: availableCategories.length ? 'Sem categoria' : 'Nenhuma categoria disponivel' }
    );

    const entityField = entitySelect.closest('.admin-field');
    if (entityField) {
        entityField.hidden = !catalog.show_entity_selector;
    }

    updateTicketCreateUploadHelp();

    const requester = document.getElementById('ticketCreateRequester');
    if (requester) {
        requester.textContent = catalog.requester?.display || catalog.requester?.name || 'Usuario logado';
    }

    const profile = document.getElementById('ticketCreateProfile');
    if (profile) {
        profile.textContent = catalog.profile_name || 'Perfil de atendimento';
    }

    const entityScope = document.getElementById('ticketCreateEntityScope');
    if (entityScope) {
        entityScope.textContent = catalog.show_entity_selector
            ? `${(catalog.entities || []).length} entidades disponiveis`
            : ((catalog.entities || [])[0]?.label || 'Entidade efetiva');
    }
}

function fillSelectOptions(select, options, selectedValue, placeholder = null) {
    if (!select) {
        return;
    }

    const fragments = [];
    if (placeholder) {
        fragments.push(`<option value="${escHtml(String(placeholder.id ?? 0))}">${escHtml(String(placeholder.label ?? 'Selecione'))}</option>`);
    }

    fragments.push((options || []).map((option) => `
        <option value="${escHtml(String(option.id ?? 0))}">${escHtml(String(option.label ?? option.name ?? option.completename ?? '-'))}</option>
    `).join(''));

    select.innerHTML = fragments.join('');
    select.value = String(selectedValue ?? '');
    if (select.value !== String(selectedValue ?? '')) {
        select.value = select.options.length > 0 ? select.options[0].value : '';
    }
}

function setTicketCreateStatus(message, type) {
    const status = document.getElementById('ticketCreateStatus');
    if (!status) {
        return;
    }

    status.textContent = message || '';
    status.className = 'ticket-create-status admin-status';
    if (type) {
        status.classList.add(type);
    }
}

function hideTicketCreateSuccess() {
    const box = document.getElementById('ticketCreateSuccess');
    if (box) {
        box.hidden = true;
    }
}

async function resetTicketCreateForm() {
    const form = document.getElementById('ticketCreateForm');
    if (!form) {
        return;
    }

    form.reset();
    clearTicketCreateAttachments();
    hideTicketCreateSuccess();
    setTicketCreateStatus('', '');
    DashState.ticketCreateState.lastTicket = null;
    await loadTicketCreateCatalog({
        force: true,
        entitiesId: Number(DashState.ticketCreateState.catalog?.default_entity_id || 0),
        type: Number(DashState.ticketCreateState.catalog?.selected_type || 1),
    });
}

async function handleTicketCreateSubmit(event) {
    event.preventDefault();
    if (DashState.ticketCreateState.submitting) {
        return;
    }

    const form = event.currentTarget;
    const submitButton = document.getElementById('ticketCreateSubmit');
    if (!(form instanceof HTMLFormElement) || !submitButton) {
        return;
    }

    DashState.ticketCreateState.submitting = true;
    submitButton.disabled = true;
    setTicketCreateStatus('Registrando chamado...', '');

    try {
        const formData = new FormData(form);
        formData.set('action', 'create');
        formData.set('csrf_token', DASHGLPI_CSRF_TOKEN);

        const response = await fetch(`${PLUGIN_ROOT}/ajax/ticket_create.php`, {
            method: 'POST',
            body: formData,
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Erro ao criar chamado.');
        }

        const createdTicket = data.ticket || null;
        DashState.ticketCreateState.lastTicket = createdTicket;
        hideTicketCreateSuccess();
        await resetTicketCreateForm();

        const successBox = document.getElementById('ticketCreateSuccess');
        const successCopy = document.getElementById('ticketCreateSuccessCopy');
        const ticketId = Number(createdTicket?.id || 0);
        const sla = data.sla || createdTicket?.sla || null;

        if (successCopy) {
            successCopy.textContent = ticketCreateSuccessCopy(ticketId, sla);
        }

        if (successBox) {
            successBox.hidden = false;
        }

        setTicketCreateStatus(data.message || 'Chamado criado com sucesso.', 'success');
    } catch (error) {
        setTicketCreateStatus(error.message || 'Erro ao criar chamado.', 'error');
    } finally {
        submitButton.disabled = false;
        DashState.ticketCreateState.submitting = false;
    }
}

function ticketCreateSuccessCopy(ticketId, sla) {
    const prefix = ticketId > 0
        ? `Chamado #${ticketId} criado com sucesso.`
        : 'Chamado criado com sucesso.';
    const warnings = Array.isArray(sla?.warnings)
        ? sla.warnings.map(item => String(item || '').trim()).filter(Boolean)
        : [];

    if (sla && sla.applied) {
        const keys = [sla.tto_key, sla.ttr_key].filter(Boolean).join(' / ');
        const source = String(sla.source_entity_name || '').trim();
        const inherited = sla.origin === 'inherited' && source !== ''
            ? ` herdado de ${source}`
            : ' da entidade';
        const detail = keys !== ''
            ? ` SLA ${keys}${inherited} aplicado.`
            : ` SLA${inherited} aplicado.`;
        return warnings.length
            ? `${prefix}${detail} Aviso: ${warnings.join(' ')}`
            : `${prefix}${detail}`;
    }

    if (warnings.length) {
        return `${prefix} Aviso SLA: ${warnings.join(' ')}`;
    }

    return `${prefix} Seu atendimento foi registrado e sera acompanhado pela equipe.`;
}

