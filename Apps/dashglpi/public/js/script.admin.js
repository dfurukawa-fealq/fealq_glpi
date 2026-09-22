// ==================== ADMIN REGISTERS ====================
// Extraído de script.js pelo PLAN-20260703-013 (Fase 3.2) — mesma execução
// sequencial, carregado logo após script.js via <script> separado.
// Depende de globais definidos em script.js (PLUGIN_ROOT, escHtml, DASHGLPI_*
// etc.) e é chamado a partir do DOMContentLoaded em script.js (initAdminRegisters()).

function initAdminRegisters() {
    if (!document.getElementById('entitiesSection')) return;

    document.getElementById('entitiesSearchInput')?.addEventListener('input', renderEntitiesTable);
    document.getElementById('groupsSearchInput')?.addEventListener('input', renderGroupsTable);
    document.getElementById('categoriesSearchInput')?.addEventListener('input', renderCategoriesTable);
    document.getElementById('usersSearchInput')?.addEventListener('input', renderUsersTable);
    document.getElementById('usersFilterEntity')?.addEventListener('change', renderUsersTable);
    document.getElementById('usersFilterProfile')?.addEventListener('change', renderUsersTable);
    document.getElementById('usersFilterStatus')?.addEventListener('change', renderUsersTable);
    document.getElementById('usersFilterClear')?.addEventListener('click', clearUserFilters);

    document.querySelectorAll('[data-admin-new]').forEach(button => {
        button.addEventListener('click', () => openAdminForm(button.getAttribute('data-admin-new') || ''));
    });
    document.querySelectorAll('[data-admin-back]').forEach(button => {
        button.addEventListener('click', () => openAdminList(button.getAttribute('data-admin-back') || ''));
    });

    document.getElementById('entitiesTableBody')?.addEventListener('click', event => {
        const closureButton = event.target.closest('[data-entity-solution-closure]');
        if (closureButton) {
            openEntitySolutionClosure(Number(closureButton.getAttribute('data-entity-solution-closure') || 0));
            return;
        }

        const prefixButton = event.target.closest('[data-entity-notification-prefix]');
        if (prefixButton) {
            openEntityNotificationPrefix(Number(prefixButton.getAttribute('data-entity-notification-prefix') || 0));
            return;
        }

        const smtpButton = event.target.closest('[data-entity-smtp]');
        if (smtpButton) {
            openEntitySmtp(Number(smtpButton.getAttribute('data-entity-smtp') || 0));
            return;
        }

        const policyButton = event.target.closest('[data-entity-sla-policy]');
        if (policyButton) {
            openEntitySlaPolicy(Number(policyButton.getAttribute('data-entity-sla-policy') || 0));
            return;
        }

        const editButton = event.target.closest('[data-entity-edit]');
        if (editButton) {
            openEntityEditor(Number(editButton.getAttribute('data-entity-edit') || 0));
            return;
        }

        const cloneButton = event.target.closest('[data-entity-clone]');
        if (cloneButton) {
            cloneEntity(Number(cloneButton.getAttribute('data-entity-clone') || 0));
            return;
        }

        const deleteButton = event.target.closest('[data-entity-delete]');
        if (!deleteButton) return;
        deleteEntity(Number(deleteButton.getAttribute('data-entity-delete') || 0));
    });
    document.getElementById('groupsTableBody')?.addEventListener('click', event => {
        const editButton = event.target.closest('[data-group-edit]');
        if (editButton) {
            openGroupEditor(Number(editButton.getAttribute('data-group-edit') || 0));
            return;
        }

        const cloneButton = event.target.closest('[data-group-clone]');
        if (cloneButton) {
            cloneGroup(Number(cloneButton.getAttribute('data-group-clone') || 0));
            return;
        }

        const deleteButton = event.target.closest('[data-group-delete]');
        if (!deleteButton) return;
        deleteGroup(Number(deleteButton.getAttribute('data-group-delete') || 0));
    });
    document.getElementById('profilesTableBody')?.addEventListener('click', event => {
        const editButton = event.target.closest('[data-profile-edit]');
        if (editButton) {
            openProfileEditor(Number(editButton.getAttribute('data-profile-edit') || 0));
            return;
        }

        const cloneButton = event.target.closest('[data-profile-clone]');
        if (cloneButton) {
            cloneProfile(Number(cloneButton.getAttribute('data-profile-clone') || 0));
            return;
        }

        const deleteButton = event.target.closest('[data-profile-delete]');
        if (!deleteButton) return;
        deleteProfile(Number(deleteButton.getAttribute('data-profile-delete') || 0));
    });
    document.getElementById('profilesSearchInput')?.addEventListener('input', renderProfilesTable);
    document.getElementById('usersTableBody')?.addEventListener('click', event => {
        const cloneButton = event.target.closest('[data-user-clone]');
        if (cloneButton) {
            cloneUser(Number(cloneButton.getAttribute('data-user-clone') || 0));
            return;
        }
        const button = event.target.closest('[data-user-edit]');
        if (!button) return;
        openUserEditor(Number(button.getAttribute('data-user-edit') || 0));
    });

    initUserWizard();
    initUserPasswordTools();
    bindUserTelegramIdField();

    bindAdminForm('entityForm', 'entities.php', 'entityStatus', 'entities');
    bindAdminForm('groupForm', 'groups.php', 'groupStatus', 'groups');
    bindAdminForm('categoryForm', 'categories.php', 'categoryStatus', 'categories');
    bindAdminForm('userForm', 'users.php', 'userStatus', 'users');
    bindAdminForm('profileForm', 'profiles.php', 'profileStatus', 'profiles');
    bindEntityNotificationPrefixForm();
    bindEntitySolutionClosureForm();
    bindEntitySmtpForm();
    bindEntitySlaPolicyForm();
    bindCategoryImportForm();
    bindAdminImportForms();
    bindAdminCsvUploads();
    bindEntityCreateSolutionClosureMode();

    document.getElementById('slaPolicyTtoMode')?.addEventListener('change', updateSlaPolicyFixedFields);
    document.getElementById('slaPolicyTtrMode')?.addEventListener('change', updateSlaPolicyFixedFields);
    document.getElementById('reapplyEntitySlaPolicy')?.addEventListener('click', () => submitEntitySlaPolicy('reapply_open_tickets'));

    loadAdminRegisters('entities');
    loadAdminRegisters('groups');
    loadAdminRegisters('categories');
    loadAdminRegisters('users');
    loadAdminRegisters('profiles');
}

function bindAdminForm(formId, endpoint, statusId, pageId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submit = form.querySelector('button[type="submit"]');
        const status = document.getElementById(statusId);
        setAdminStatus(status, 'Salvando...', '');
        if (submit) submit.disabled = true;

        try {
            const formData = new FormData(form);
            formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

            const response = await fetch(PLUGIN_ROOT + '/ajax/' + endpoint, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await adminReadJson(response);

            applyAdminPayload(data);
            form.reset();
            if (formId === 'entityForm') {
                setEntityFormMode('create');
                if (DashState.adminEntityEditorMode === 'create') {
                    const createGroup = form.querySelector('[name="create_group"]');
                    if (createGroup) createGroup.checked = true;
                    const createPolicy = form.querySelector('[name="create_sla_policy"]');
                    if (createPolicy) createPolicy.checked = true;
                    applyEntityCreateSolutionClosureDefaults();
                }
            }
            if (formId === 'groupForm') {
                setGroupFormMode('create');
            }
            if (formId === 'profileForm') {
                setProfileFormMode('create');
            }
            if (formId === 'userForm') {
                setUserFormMode('create');
                const active = form.querySelector('[name="is_active"]');
                if (active) active.checked = true;
            }
            if (formId === 'categoryForm') {
                resetCategoryDefaults(form);
            }
            populateAdminSelects();
            renderAdminTables();
            setAdminStatus(status, adminSuccessMessage(data, pageId), 'success');
            setTimeout(() => openAdminList(pageId), 450);
        } catch (error) {
            setAdminStatus(status, error.message || 'Erro ao salvar cadastro.', 'error');
        } finally {
            if (submit) submit.disabled = false;
        }
    });
}

async function loadAdminRegisters(pageId, rethrow = false) {
    const endpoints = {
        entities: 'entities.php',
        groups: 'groups.php',
        categories: 'categories.php',
        users: 'users.php',
        profiles: 'profiles.php'
    };
    const endpoint = endpoints[pageId];
    if (!endpoint) return;

    try {
        const response = await fetch(PLUGIN_ROOT + '/ajax/' + endpoint, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        applyAdminPayload(data);
        populateAdminSelects();
        renderAdminTables();
    } catch (error) {
        const bodyIds = {
            entities: 'entitiesTableBody',
            groups: 'groupsTableBody',
            categories: 'categoriesTableBody',
            users: 'usersTableBody',
            profiles: 'profilesTableBody'
        };
        const colspans = {
            entities: 8,
            groups: 5,
            categories: 5,
            users: 7,
            profiles: 5
        };
        const body = document.getElementById(bodyIds[pageId]);
        if (body) {
            body.innerHTML = `<tr><td colspan="${colspans[pageId] || 6}" class="table-empty">${escHtml(error.message || 'Erro ao carregar cadastro')}</td></tr>`;
        }
        if (rethrow) {
            throw error;
        }
    }
}

async function adminReadJson(response) {
    const text = await response.text();
    let data = null;

    try {
        data = text ? JSON.parse(text) : null;
    } catch (error) {
        console.error('Resposta nao JSON do cadastro:', text);
        throw new Error('Resposta invalida do servidor. Verifique os logs do DashGLPI.');
    }

    if (!response.ok || !data || !data.ok) {
        throw new Error(data?.error || data?.message || 'Falha ao processar cadastro.');
    }

    return data;
}

async function openAdminForm(type) {
    const map = {
        entity: 'entityNew',
        entityImport: 'entityImport',
        group: 'groupNew',
        groupImport: 'groupImport',
        category: 'categoryNew',
        categoryImport: 'categoryImport',
        user: 'userNew',
        userImport: 'userImport',
        computerImport: 'computerImport',
        monitorImport: 'monitorImport',
        ticketImport: 'ticketImport',
        profile: 'profileNew',
        profileImport: 'profileImport'
    };
    const pageId = map[type];
    if (!pageId) return;

    const loadMap = {
        entity: 'entities',
        entityImport: 'entities',
        group: 'groups',
        groupImport: 'groups',
        category: 'categories',
        categoryImport: 'categories',
        user: 'users',
        userImport: 'users',
        computerImport: 'assets',
        monitorImport: 'assets',
        ticketImport: 'tickets',
        profile: 'profiles',
        profileImport: 'profiles'
    };
    resetAdminStatus(type);
    showPage(pageId, adminMenuLink(type), { updateHash: false });

    if (type === 'entity') setEntityFormMode('create');
    if (type === 'group') setGroupFormMode('create');
    if (type === 'profile') setProfileFormMode('create');

    try {
        await loadAdminRegisters(loadMap[type], true);
        populateAdminSelects();
        if (type === 'user') {
            prefillUserFormFromFilters();
        }
    } catch (error) {
        setAdminStatus(adminStatusElement(type), error.message || 'Erro ao carregar dados do formulario.', 'error');
    }
}

function prefillUserFormFromFilters() {
    const form = document.getElementById('userForm');
    if (!form || Number(form.elements.id?.value || 0) > 0) return;

    const entityFilter = document.getElementById('usersFilterEntity')?.value ?? '';
    if (entityFilter !== '' && form.elements.entities_id
        && [...form.elements.entities_id.options].some(option => option.value === entityFilter)) {
        form.elements.entities_id.value = entityFilter;
    }

    const profileFilter = document.getElementById('usersFilterProfile')?.value ?? '';
    if (profileFilter !== '' && form.elements.profiles_id
        && [...form.elements.profiles_id.options].some(option => option.value === profileFilter)) {
        form.elements.profiles_id.value = profileFilter;
    }
}

function openAdminList(pageId) {
    const map = {
        entities: 'entities',
        groups: 'groups',
        categories: 'categories',
        users: 'users',
        profiles: 'profiles'
    };
    const target = map[pageId] || pageId;
    showPage(target, adminMenuLink(target));
}

function adminStatusElement(type) {
    const statusIds = {
        entity: 'entityStatus',
        entityImport: 'entityImportStatus',
        entityNotificationPrefix: 'entityNotificationPrefixStatus',
        entitySolutionClosure: 'entitySolutionClosureStatus',
        entitySmtp: 'entitySmtpStatus',
        group: 'groupStatus',
        groupImport: 'groupImportStatus',
        category: 'categoryStatus',
        categoryImport: 'categoryImportStatus',
        user: 'userStatus',
        userImport: 'userImportStatus',
        computerImport: 'computerImportStatus',
        monitorImport: 'monitorImportStatus',
        ticketImport: 'ticketImportStatus',
        profile: 'profileStatus',
        profileImport: 'profileImportStatus'
    };
    return document.getElementById(statusIds[type] || '');
}

function adminMenuLink(type) {
    const map = {
        entity: 'entities',
        entityImport: 'entities',
        group: 'groups',
        groupImport: 'groups',
        category: 'categories',
        categoryImport: 'categories',
        user: 'users',
        userImport: 'users',
        computerImport: 'assets',
        monitorImport: 'assets',
        ticketImport: 'tickets',
        profile: 'profiles',
        profileImport: 'profiles',
        entities: 'entities',
        groups: 'groups',
        categories: 'categories',
        users: 'users',
        profiles: 'profiles'
    };
    const pageId = map[type];
    return pageId ? document.querySelector(`.menu-link[onclick*="${pageId}"], .menu-sublink[onclick*="${pageId}"]`) : null;
}

function resetAdminStatus(type) {
    const statusIds = {
        entity: 'entityStatus',
        entityImport: 'entityImportStatus',
        entityNotificationPrefix: 'entityNotificationPrefixStatus',
        entitySolutionClosure: 'entitySolutionClosureStatus',
        entitySmtp: 'entitySmtpStatus',
        group: 'groupStatus',
        groupImport: 'groupImportStatus',
        category: 'categoryStatus',
        categoryImport: 'categoryImportStatus',
        user: 'userStatus',
        userImport: 'userImportStatus',
        computerImport: 'computerImportStatus',
        monitorImport: 'monitorImportStatus',
        ticketImport: 'ticketImportStatus',
        profile: 'profileStatus',
        profileImport: 'profileImportStatus'
    };
    const formIds = {
        entity: 'entityForm',
        entityImport: 'entityImportForm',
        entityNotificationPrefix: 'entityNotificationPrefixForm',
        entitySolutionClosure: 'entitySolutionClosureForm',
        entitySmtp: 'entitySmtpForm',
        group: 'groupForm',
        groupImport: 'groupImportForm',
        category: 'categoryForm',
        categoryImport: 'categoryImportForm',
        user: 'userForm',
        userImport: 'userImportForm',
        computerImport: 'computerImportForm',
        monitorImport: 'monitorImportForm',
        ticketImport: 'ticketImportForm',
        profile: 'profileForm',
        profileImport: 'profileImportForm'
    };

    setAdminStatus(adminStatusElement(type), '', '');

    const form = document.getElementById(formIds[type]);
    if (!form) return;
    form.reset();
    if (type === 'entity') {
        setEntityFormMode('create');
        const createGroup = form.querySelector('[name="create_group"]');
        if (createGroup) createGroup.checked = true;
        const createPolicy = form.querySelector('[name="create_sla_policy"]');
        if (createPolicy) createPolicy.checked = true;
        applyEntityCreateSolutionClosureDefaults();
    }
    if (type === 'group') {
        setGroupFormMode('create');
    }
    if (type === 'profile') {
        setProfileFormMode('create');
    }
    if (type === 'user') {
        setUserFormMode('create');
        const active = form.querySelector('[name="is_active"]');
        if (active) active.checked = true;
    }
    if (type === 'category') {
        resetCategoryDefaults(form);
    }
    if (type === 'categoryImport') {
        clearCategoryImportPreview();
    }
    if (typeof ADMIN_IMPORT_CONFIG !== 'undefined' && ADMIN_IMPORT_CONFIG[type]) {
        clearAdminImportPreview(type);
    }
    if (type === 'entityNotificationPrefix') {
        DashState.entityNotificationPrefixContext = null;
        updateEntityNotificationPrefixMode();
        refreshEntityNotificationPrefixPreview();
    }
    if (type === 'entitySolutionClosure') {
        DashState.entitySolutionClosureContext = null;
        updateEntitySolutionClosureMode();
        refreshEntitySolutionClosurePreview();
    }
    if (type === 'entitySmtp') {
        DashState.entitySmtpContext = null;
        updateEntitySmtpMode();
        renderEntitySmtpOwner(null);
    }
}

function resetCategoryDefaults(form) {
    const defaults = {
        is_recursive: true,
        is_helpdeskvisible: true,
        is_incident: true,
        is_request: true,
        is_problem: false,
        is_change: false
    };

    Object.entries(defaults).forEach(([name, checked]) => {
        const checkbox = form.querySelector(`input[type="checkbox"][name="${name}"]`);
        if (checkbox) checkbox.checked = checked;
    });
}

function rootEntityOption() {
    return {
        id: 0,
        name: 'Entidade raiz',
        completename: 'Entidade raiz',
        entities_id: 0,
        notification_subject_tag: '',
        autoclose_delay: -10,
        entitysmtp_is_active: 0,
        entitysmtp_host: '',
        entitysmtp_port: 0,
        entitysmtp_encryption: '',
        entitysmtp_username: '',
        entitysmtp_password_configured: 0,
        entitysmtp_effective_origin: 'global',
        entitysmtp_effective_source_entities_id: 0,
        entitysmtp_effective_source_entity_name: 'SMTP global do GLPI',
        entitysmtp_effective_host: '',
        entitysmtp_effective_port: 0,
        entitysmtp_effective_encryption: '',
        entitysmtp_effective_username: '',
        entitysmtp_effective_password_configured: 0,
        is_root: true
    };
}

function entityByIdOrRoot(entitiesId) {
    const id = Number(entitiesId) || 0;
    const entity = DashState.adminEntitiesGlobal.find(item => Number(item.id) === id);
    if (entity) {
        return entity;
    }
    return id === 0 ? rootEntityOption() : null;
}

function adminEntitiesWithRoot() {
    const hasRoot = DashState.adminEntitiesGlobal.some(item => Number(item.id) === 0);
    return hasRoot ? DashState.adminEntitiesGlobal : [rootEntityOption(), ...DashState.adminEntitiesGlobal];
}

async function openEntityNotificationPrefix(entitiesId) {
    const entity = entityByIdOrRoot(entitiesId);
    if (!entity) {
        return;
    }

    resetAdminStatus('entityNotificationPrefix');
    showPage('entityNotificationPrefix', adminMenuLink('entities'), { updateHash: false });
    fillEntityNotificationPrefixForm({
        entity: {
            id: Number(entity.id) || 0,
            name: entity.name || 'Entidade',
            completename: entity.completename || entity.name || 'Entidade',
            parent_id: Number(entity.entities_id) || 0
        },
        local_value: String(entity.notification_subject_tag || '').trim(),
        mode: String(entity.notification_subject_tag || '').trim() !== '' ? 'custom' : 'inherit',
        effective_value: String(entity.notification_subject_tag || '').trim() || 'GLPI',
        effective_origin_label: String(entity.notification_subject_tag || '').trim() !== '' ? 'Valor proprio da entidade' : ((Number(entity.id) || 0) === 0 ? 'Padrao GLPI' : 'Herdado'),
        subject_preview: `[${String(entity.notification_subject_tag || '').trim() || 'GLPI'} #0000025] <assunto do template>`,
        inherited_value: 'GLPI',
        inherited_origin_label: (Number(entity.id) || 0) === 0 ? 'Padrao GLPI' : 'Herdado'
    });
    setAdminStatus(document.getElementById('entityNotificationPrefixStatus'), 'Carregando configuracao da entidade...', '');

    try {
        const response = await fetch(`${PLUGIN_ROOT}/ajax/entity_notification_prefix.php?entities_id=${encodeURIComponent(entitiesId)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        if (!data.prefix) {
            throw new Error('Resposta incompleta do editor de prefixo.');
        }
        fillEntityNotificationPrefixForm(data.prefix);
        renderEntitiesTable();
        setAdminStatus(document.getElementById('entityNotificationPrefixStatus'), '', '');
    } catch (error) {
        setAdminStatus(document.getElementById('entityNotificationPrefixStatus'), error.message || 'Erro ao carregar prefixo da entidade.', 'error');
    }
}

function bindEntityNotificationPrefixForm() {
    const form = document.getElementById('entityNotificationPrefixForm');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await submitEntityNotificationPrefix();
    });

    form.elements.mode?.addEventListener('change', () => {
        updateEntityNotificationPrefixMode();
        refreshEntityNotificationPrefixPreview();
    });
    form.elements.notification_subject_tag?.addEventListener('input', refreshEntityNotificationPrefixPreview);

    updateEntityNotificationPrefixMode();
    refreshEntityNotificationPrefixPreview();
}

function fillEntityNotificationPrefixForm(prefix) {
    const form = document.getElementById('entityNotificationPrefixForm');
    if (!form || !prefix) return;

    DashState.entityNotificationPrefixContext = prefix;
    form.elements.entities_id.value = String(Number(prefix.entity?.id) || 0);
    form.elements.mode.value = prefix.mode === 'custom' ? 'custom' : 'inherit';
    form.elements.notification_subject_tag.value = prefix.local_value || '';

    const label = document.getElementById('entityNotificationPrefixEntityName');
    if (label) {
        label.textContent = prefix.entity?.completename || prefix.entity?.name || 'Entidade';
    }

    updateEntityNotificationPrefixMode();
    refreshEntityNotificationPrefixPreview();
}

function updateEntityNotificationPrefixMode() {
    const form = document.getElementById('entityNotificationPrefixForm');
    const customField = document.getElementById('entityNotificationPrefixCustomField');
    const help = document.getElementById('entityNotificationPrefixModeHelp');
    if (!form || !customField) return;

    const isCustom = form.elements.mode.value === 'custom';
    customField.classList.toggle('muted-field', !isCustom);
    const input = form.elements.notification_subject_tag;
    if (input) {
        input.disabled = !isCustom;
        input.required = isCustom;
    }
    if (help) {
        help.textContent = isCustom
            ? 'Defina o texto que o GLPI deve prefixar antes do assunto do template.'
            : 'Sem valor proprio, a entidade herda o prefixo da hierarquia. Se nada existir, o GLPI usa o padrao.';
    }
}

function refreshEntityNotificationPrefixPreview() {
    const form = document.getElementById('entityNotificationPrefixForm');
    if (!form) return;

    const mode = form.elements.mode.value === 'custom' ? 'custom' : 'inherit';
    const customValue = normalizeAdminTextValue(form.elements.notification_subject_tag.value || '');
    const inheritedValue = normalizeAdminTextValue(DashState.entityNotificationPrefixContext?.inherited_value || 'GLPI') || 'GLPI';
    const inheritedOrigin = DashState.entityNotificationPrefixContext?.inherited_origin_label || 'Padrao GLPI';
    const effectiveValue = mode === 'custom' ? (customValue || '...') : inheritedValue;
    const originLabel = mode === 'custom'
        ? (customValue ? 'Valor proprio da entidade' : 'Preencha um prefixo para salvar')
        : inheritedOrigin;
    const subjectPreview = `[${effectiveValue} #0000025] <assunto do template>`;

    const effectiveInput = document.getElementById('entityNotificationPrefixEffectiveValue');
    const originInput = document.getElementById('entityNotificationPrefixOrigin');
    const subjectInput = document.getElementById('entityNotificationPrefixSubjectPreview');

    if (effectiveInput) effectiveInput.value = effectiveValue;
    if (originInput) originInput.value = originLabel;
    if (subjectInput) subjectInput.value = subjectPreview;
}

async function submitEntityNotificationPrefix() {
    const form = document.getElementById('entityNotificationPrefixForm');
    const status = document.getElementById('entityNotificationPrefixStatus');
    if (!form) return;

    const submit = form.querySelector('button[type="submit"]');
    setAdminStatus(status, 'Atualizando prefixo no GLPI...', '');
    if (submit) submit.disabled = true;

    try {
        const formData = new FormData(form);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/entity_notification_prefix.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        if (!data.prefix) {
            throw new Error('Resposta incompleta ao salvar prefixo da entidade.');
        }
        fillEntityNotificationPrefixForm(data.prefix);
        renderEntitiesTable();
        setAdminStatus(status, 'Prefixo de notificacoes atualizado no GLPI.', 'success');
    } catch (error) {
        setAdminStatus(status, error.message || 'Erro ao atualizar prefixo da entidade.', 'error');
    } finally {
        if (submit) submit.disabled = false;
    }
}

function bindEntityCreateSolutionClosureMode() {
    const mode = document.getElementById('entitySolutionClosureCreateMode');
    if (!mode) return;

    mode.addEventListener('change', updateEntityCreateSolutionClosureMode);
    document.getElementById('entitySolutionClosureCreateDays')?.addEventListener('input', updateEntityCreateSolutionClosureMode);
    applyEntityCreateSolutionClosureDefaults();
}

function applyEntityCreateSolutionClosureDefaults() {
    const mode = document.getElementById('entitySolutionClosureCreateMode');
    const days = document.getElementById('entitySolutionClosureCreateDays');
    if (mode && !mode.value) {
        mode.value = 'inherit';
    }
    if (mode) {
        mode.value = 'inherit';
    }
    if (days) {
        days.value = String(Number(days.value) > 0 ? Number(days.value) : 5);
    }
    updateEntityCreateSolutionClosureMode();
}

function updateEntityCreateSolutionClosureMode() {
    const modeField = document.getElementById('entitySolutionClosureCreateMode');
    const daysField = document.getElementById('entitySolutionClosureCreateDaysField');
    const daysInput = document.getElementById('entitySolutionClosureCreateDays');
    const help = document.getElementById('entitySolutionClosureCreateHelp');
    if (!modeField || !daysField || !daysInput) return;

    const mode = modeField.value || 'inherit';
    const isDays = mode === 'days';
    daysField.classList.toggle('muted-field', !isDays);
    daysInput.disabled = !isDays;
    daysInput.required = isDays;
    if (isDays && !Number(daysInput.value)) {
        daysInput.value = '5';
    }

    if (!help) return;

    help.textContent = ({
        inherit: 'Por padrão a nova entidade herda o comportamento configurado na entidade pai.',
        immediate: 'Ao registrar a solução, o GLPI enviará o ticket diretamente para Fechado.',
        days: `Ao registrar a solução, o ticket ficará Solucionado e fechará automaticamente após ${Number(daysInput.value) || 5} dia(s).`,
        never: 'Ao registrar a solução, o ticket ficará Solucionado e não fechará automaticamente.'
    })[mode] || 'Por padrão a nova entidade herda o comportamento configurado na entidade pai.';
}

async function openEntitySolutionClosure(entitiesId) {
    const entity = entityByIdOrRoot(entitiesId);
    if (!entity) {
        return;
    }

    resetAdminStatus('entitySolutionClosure');
    showPage('entitySolutionClosure', adminMenuLink('entities'), { updateHash: false });
    fillEntitySolutionClosureForm({
        entity: {
            id: Number(entity.id) || 0,
            name: entity.name || 'Entidade',
            completename: entity.completename || entity.name || 'Entidade',
            parent_id: Number(entity.entities_id) || 0,
            is_root: Number(entity.id) === 0
        },
        local_value: Number(entity.autoclose_delay ?? (Number(entity.id) === 0 ? -10 : -2)),
        mode: entitySolutionClosureInfo(entity).mode,
        days: entitySolutionClosureInfo(entity).mode === 'days' ? Number(entity.autoclose_delay) || 0 : 0,
        effective_value: Number(entity.autoclose_delay ?? (Number(entity.id) === 0 ? -10 : -2)),
        effective_mode: entitySolutionClosureInfo(entity).mode === 'inherit' ? 'never' : entitySolutionClosureInfo(entity).mode,
        effective_days: entitySolutionClosureInfo(entity).mode === 'days' ? Number(entity.autoclose_delay) || 0 : 0,
        effective_label: entitySolutionClosureInfo(entity).label,
        effective_origin_label: entitySolutionClosureInfo(entity).mode === 'inherit' ? 'Herdado' : 'Valor proprio da entidade',
        inherited_value: -10,
        inherited_mode: 'never',
        inherited_days: 0,
        inherited_label: 'Nunca',
        inherited_origin_label: Number(entity.id) === 0 ? 'Padrao GLPI' : 'Herdado'
    });
    setAdminStatus(document.getElementById('entitySolutionClosureStatus'), 'Carregando configuracao da entidade...', '');

    try {
        const response = await fetch(`${PLUGIN_ROOT}/ajax/entity_solution_closure.php?entities_id=${encodeURIComponent(entitiesId)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        if (!data.closure) {
            throw new Error('Resposta incompleta do editor de fechamento pós-solucao.');
        }
        fillEntitySolutionClosureForm(data.closure);
        renderEntitiesTable();
        setAdminStatus(document.getElementById('entitySolutionClosureStatus'), '', '');
    } catch (error) {
        setAdminStatus(document.getElementById('entitySolutionClosureStatus'), error.message || 'Erro ao carregar fechamento pós-solucao da entidade.', 'error');
    }
}

function bindEntitySolutionClosureForm() {
    const form = document.getElementById('entitySolutionClosureForm');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await submitEntitySolutionClosure();
    });

    form.elements.mode?.addEventListener('change', () => {
        updateEntitySolutionClosureMode();
        refreshEntitySolutionClosurePreview();
    });
    form.elements.days?.addEventListener('input', refreshEntitySolutionClosurePreview);

    updateEntitySolutionClosureMode();
    refreshEntitySolutionClosurePreview();
}

function fillEntitySolutionClosureForm(closure) {
    const form = document.getElementById('entitySolutionClosureForm');
    if (!form || !closure) return;

    DashState.entitySolutionClosureContext = closure;
    const entityId = Number(closure.entity?.id) || 0;
    const isRoot = Number(closure.entity?.is_root || 0) === 1 || entityId === 0;
    const mode = closure.mode || (isRoot ? 'never' : 'inherit');
    const localDays = Number(closure.days || 0);
    const effectiveDays = Number(closure.effective_days || 0);

    form.elements.entities_id.value = String(entityId);
    form.elements.mode.value = mode;
    form.elements.days.value = String(localDays > 0 ? localDays : (effectiveDays > 0 ? effectiveDays : 5));

    const label = document.getElementById('entitySolutionClosureEntityName');
    if (label) {
        label.textContent = closure.entity?.completename || closure.entity?.name || 'Entidade';
    }

    updateEntitySolutionClosureMode();
    refreshEntitySolutionClosurePreview();
}

function updateEntitySolutionClosureMode() {
    const form = document.getElementById('entitySolutionClosureForm');
    const daysField = document.getElementById('entitySolutionClosureDaysField');
    const help = document.getElementById('entitySolutionClosureModeHelp');
    if (!form || !daysField) return;

    const modeField = form.elements.mode;
    const daysInput = form.elements.days;
    const isRoot = Number(DashState.entitySolutionClosureContext?.entity?.is_root || 0) === 1 || Number(form.elements.entities_id?.value || 0) === 0;
    const inheritOption = modeField?.querySelector('option[value="inherit"]');
    if (inheritOption) {
        inheritOption.disabled = isRoot;
        inheritOption.hidden = isRoot;
    }
    if (isRoot && modeField?.value === 'inherit') {
        modeField.value = DashState.entitySolutionClosureContext?.effective_mode || 'never';
    }

    const mode = modeField?.value || (isRoot ? 'never' : 'inherit');
    const isDays = mode === 'days';
    daysField.classList.toggle('muted-field', !isDays);
    if (daysInput) {
        daysInput.disabled = !isDays;
        daysInput.required = isDays;
        if (isDays && !Number(daysInput.value)) {
            daysInput.value = String(Number(DashState.entitySolutionClosureContext?.effective_days || 0) || 5);
        }
    }

    if (!help) return;
    help.textContent = ({
        inherit: 'Sem valor proprio, a entidade herda o comportamento configurado na hierarquia.',
        immediate: 'Ao registrar a solução, o ticket será fechado imediatamente.',
        days: 'Ao registrar a solução, o ticket ficará Solucionado e será fechado automaticamente depois do prazo informado.',
        never: 'Ao registrar a solução, o ticket ficará Solucionado e não fechará automaticamente.'
    })[mode] || 'Sem valor proprio, a entidade herda o comportamento configurado na hierarquia.';
}

function entitySolutionClosureBehaviorMessage(mode, days) {
    if (mode === 'immediate') {
        return 'Ao adicionar a solução, o ticket vai para Fechado imediatamente.';
    }
    if (mode === 'days') {
        const safeDays = Number(days) || 0;
        return `Ao adicionar a solução, o ticket fica Solucionado e fecha automaticamente após ${safeDays} dia(s).`;
    }
    if (mode === 'never') {
        return 'Ao adicionar a solução, o ticket fica Solucionado e não fecha automaticamente.';
    }
    return 'Ao adicionar a solução, o ticket segue a configuração herdada da hierarquia.';
}

function refreshEntitySolutionClosurePreview() {
    const form = document.getElementById('entitySolutionClosureForm');
    if (!form) return;

    const mode = form.elements.mode?.value || 'inherit';
    const days = Number(form.elements.days?.value || 0);
    const inheritedMode = DashState.entitySolutionClosureContext?.inherited_mode || 'never';
    const inheritedDays = Number(DashState.entitySolutionClosureContext?.inherited_days || 0);
    const effectiveMode = mode === 'inherit' ? inheritedMode : mode;
    const effectiveDays = mode === 'inherit'
        ? inheritedDays
        : (mode === 'days' ? (days || Number(DashState.entitySolutionClosureContext?.effective_days || 0) || 5) : 0);
    const effectiveLabel = mode === 'inherit'
        ? (DashState.entitySolutionClosureContext?.inherited_label || 'Nunca')
        : (effectiveMode === 'immediate'
            ? 'Imediato'
            : (effectiveMode === 'never'
                ? 'Nunca'
                : `${effectiveDays} dia${effectiveDays === 1 ? '' : 's'}`));
    const originLabel = mode === 'inherit'
        ? (DashState.entitySolutionClosureContext?.inherited_origin_label || 'Padrao GLPI')
        : 'Valor proprio da entidade';

    const effectiveInput = document.getElementById('entitySolutionClosureEffectiveValue');
    const originInput = document.getElementById('entitySolutionClosureOrigin');
    const behaviorInput = document.getElementById('entitySolutionClosureBehaviorPreview');

    if (effectiveInput) effectiveInput.value = effectiveLabel;
    if (originInput) originInput.value = originLabel;
    if (behaviorInput) behaviorInput.value = entitySolutionClosureBehaviorMessage(effectiveMode, effectiveDays);
}

async function submitEntitySolutionClosure() {
    const form = document.getElementById('entitySolutionClosureForm');
    const status = document.getElementById('entitySolutionClosureStatus');
    if (!form) return;

    const submit = form.querySelector('button[type="submit"]');
    setAdminStatus(status, 'Atualizando fechamento pós-solucao no GLPI...', '');
    if (submit) submit.disabled = true;

    try {
        const formData = new FormData(form);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/entity_solution_closure.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        if (!data.closure) {
            throw new Error('Resposta incompleta ao salvar fechamento pós-solucao da entidade.');
        }
        fillEntitySolutionClosureForm(data.closure);
        renderEntitiesTable();
        setAdminStatus(status, 'Fechamento pós-solucao atualizado no GLPI.', 'success');
    } catch (error) {
        setAdminStatus(status, error.message || 'Erro ao atualizar fechamento pós-solucao da entidade.', 'error');
    } finally {
        if (submit) submit.disabled = false;
    }
}

async function openEntitySmtp(entitiesId) {
    const entity = entityByIdOrRoot(entitiesId);
    if (!entity) {
        return;
    }

    resetAdminStatus('entitySmtp');
    showPage('entitySmtp', adminMenuLink('entities'), { updateHash: false });
    fillEntitySmtpForm(entitySmtpFallbackState(entity));
    setAdminStatus(document.getElementById('entitySmtpStatus'), 'Carregando SMTP da entidade...', '');

    try {
        const response = await fetch(`${PLUGIN_ROOT}/ajax/entitysmtp.php?entities_id=${encodeURIComponent(entitiesId)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        if (!data.entitysmtp) {
            throw new Error('Resposta incompleta do editor SMTP.');
        }
        fillEntitySmtpForm(data.entitysmtp);
        renderEntitySmtpOwner(data.owner || null);
        renderEntitiesTable();
        setAdminStatus(document.getElementById('entitySmtpStatus'), '', '');
    } catch (error) {
        setAdminStatus(document.getElementById('entitySmtpStatus'), error.message || 'Erro ao carregar SMTP da entidade.', 'error');
    }
}

function entitySmtpFallbackState(entity) {
    return {
        entity: {
            id: Number(entity.id) || 0,
            name: entity.name || 'Entidade',
            completename: entity.completename || entity.name || 'Entidade',
            parent_id: Number(entity.entities_id) || 0
        },
        identity: {
            admin_email: '',
            admin_email_name: '',
            from_email: '',
            from_email_name: '',
            replyto_email: '',
            replyto_email_name: '',
            mailing_signature: ''
        },
        smtp: {
            is_active: Number(entity.entitysmtp_is_active || 0) === 1 ? 1 : 0,
            host: entity.entitysmtp_host || '',
            port: Number(entity.entitysmtp_port || 0) || 587,
            encryption: entity.entitysmtp_encryption || 'tls',
            smtp_username: entity.entitysmtp_username || '',
            smtp_check_certificate: 1,
            password_configured: Number(entity.entitysmtp_password_configured || 0) === 1
        },
        effective_smtp: entitySmtpEffectiveFromEntity(entity)
    };
}

function bindEntitySmtpForm() {
    const form = document.getElementById('entitySmtpForm');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await submitEntitySmtp();
    });

    document.getElementById('entitySmtpIsActive')?.addEventListener('change', updateEntitySmtpMode);
    document.getElementById('entitySmtpEncryption')?.addEventListener('change', updateEntitySmtpPortHint);
    document.getElementById('entitySmtpUsername')?.addEventListener('input', updateEntitySmtpMode);
    document.getElementById('entitySmtpTestButton')?.addEventListener('click', submitEntitySmtpTest);
    document.getElementById('entitySmtpActivateOwner')?.addEventListener('click', () => submitEntitySmtpOwnerAction('activate_owner'));
    document.getElementById('entitySmtpRestoreOwner')?.addEventListener('click', () => submitEntitySmtpOwnerAction('restore_glpi_owner'));
    document.getElementById('entitySmtpFaqToggle')?.addEventListener('click', () => {
        const faq = document.getElementById('entitySmtpFaq');
        if (faq) faq.hidden = !faq.hidden;
    });

    updateEntitySmtpMode();
}

function entitySmtpField(name) {
    const id = ENTITY_SMTP_FIELD_IDS[name] || '';
    return id ? document.getElementById(id) : null;
}

function fillEntitySmtpForm(state) {
    const form = document.getElementById('entitySmtpForm');
    if (!form || !state) return;

    DashState.entitySmtpContext = state;
    entitySmtpField('entities_id').value = String(Number(state.entity?.id) || 0);
    entitySmtpField('admin_email').value = state.identity?.admin_email || '';
    entitySmtpField('admin_email_name').value = state.identity?.admin_email_name || '';
    entitySmtpField('from_email').value = state.identity?.from_email || '';
    entitySmtpField('from_email_name').value = state.identity?.from_email_name || '';
    entitySmtpField('replyto_email').value = state.identity?.replyto_email || '';
    entitySmtpField('replyto_email_name').value = state.identity?.replyto_email_name || '';
    entitySmtpField('mailing_signature').value = state.identity?.mailing_signature || '';
    entitySmtpField('is_active').checked = Number(state.smtp?.is_active || 0) === 1;
    entitySmtpField('host').value = state.smtp?.host || '';
    entitySmtpField('port').value = String(Number(state.smtp?.port || 0) || 587);
    entitySmtpField('encryption').value = ['none', 'tls', 'ssl'].includes(state.smtp?.encryption) ? state.smtp.encryption : 'tls';
    entitySmtpField('smtp_username').value = state.smtp?.smtp_username || '';
    entitySmtpField('smtp_passwd').value = '';
    entitySmtpField('smtp_check_certificate').checked = Number(state.smtp?.smtp_check_certificate ?? 1) === 1;

    const label = document.getElementById('entitySmtpEntityName');
    if (label) {
        label.textContent = state.entity?.completename || state.entity?.name || 'Entidade';
    }

    const passwordHint = document.getElementById('entitySmtpPasswordHint');
    if (passwordHint) {
        passwordHint.textContent = state.smtp?.password_configured
            ? 'Senha configurada. Preencha somente para substituir.'
            : 'Nenhuma senha configurada.';
    }

    renderEntitySmtpEffective(state.effective_smtp || null);
    updateEntitySmtpMode();
}

function renderEntitySmtpEffective(effective) {
    const badge = document.getElementById('entitySmtpEffectiveBadge');
    const summary = document.getElementById('entitySmtpEffectiveSummary');
    const origin = effective?.origin || 'global';
    const host = normalizeAdminTextValue(effective?.host || '');
    const sourceName = normalizeAdminTextValue(effective?.source_entity_name || '');

    if (badge) {
        badge.textContent = entitySmtpEffectiveLabel(effective);
        badge.className = `table-badge ${entitySmtpEffectiveBadgeClass(effective)}`;
    }

    if (summary) {
        if (origin === 'entity') {
            summary.textContent = host !== ''
                ? `Envios desta entidade usam ${host}.`
                : 'Esta entidade possui SMTP próprio ativo.';
        } else if (origin === 'inherited') {
            summary.textContent = `Sem SMTP próprio ativo aqui. Envios usam ${host || 'SMTP herdado'} de ${sourceName || 'entidade pai'}.`;
        } else {
            summary.textContent = 'Sem SMTP próprio ativo nesta entidade ou na hierarquia. Envios usam o SMTP global do GLPI.';
        }
    }
}

function entitySmtpEffectiveLabel(effective) {
    const origin = effective?.origin || 'global';
    if (origin === 'entity') return 'SMTP próprio';
    if (origin === 'inherited') return 'SMTP herdado';
    return 'SMTP global';
}

function entitySmtpEffectiveBadgeClass(effective) {
    const origin = effective?.origin || 'global';
    const passwordPending = normalizeAdminTextValue(effective?.smtp_username || '') !== '' && !effective?.password_configured;
    if (origin === 'entity') {
        return passwordPending ? 'warning' : 'success';
    }
    if (origin === 'inherited') {
        return passwordPending ? 'warning' : 'primary';
    }
    return 'muted';
}

function updateEntitySmtpMode() {
    const form = document.getElementById('entitySmtpForm');
    if (!form) return;

    const active = entitySmtpField('is_active')?.checked;
    ['host', 'port', 'encryption', 'smtp_username', 'smtp_passwd', 'smtp_check_certificate'].forEach((name) => {
        const field = entitySmtpField(name);
        if (!field) return;
        field.disabled = !active;
        const wrapper = field.closest('.admin-field, .admin-check');
        if (wrapper) wrapper.classList.toggle('muted-field', !active);
    });

    const host = entitySmtpField('host');
    const port = entitySmtpField('port');
    const password = entitySmtpField('smtp_passwd');
    const username = entitySmtpField('smtp_username');
    if (host) host.required = !!active;
    if (port) port.required = !!active;
    if (password) {
        password.required = !!active && !DashState.entitySmtpContext?.smtp?.password_configured && normalizeAdminTextValue(username?.value || '') !== '';
    }
}

function updateEntitySmtpPortHint() {
    const port = entitySmtpField('port');
    if (!port || Number(port.value || 0) > 0) return;
    port.value = entitySmtpField('encryption')?.value === 'ssl' ? '465' : '587';
}

function renderEntitySmtpOwner(owner) {
    const badge = document.getElementById('entitySmtpOwnerBadge');
    const summary = document.getElementById('entitySmtpOwnerSummary');
    const activate = document.getElementById('entitySmtpActivateOwner');
    const restore = document.getElementById('entitySmtpRestoreOwner');
    const ownerName = owner?.owner === 'dash' ? 'Dash' : 'GLPI';
    const conflict = !!owner?.conflict;

    if (badge) {
        badge.textContent = conflict ? 'Conflito de fila' : `Dono: ${ownerName}`;
        badge.className = `table-badge ${conflict ? 'danger' : (ownerName === 'Dash' ? 'primary' : 'muted')}`;
    }
    if (summary) {
        const native = owner?.native_task || {};
        const dash = owner?.dash_task || {};
        summary.textContent = conflict
            ? 'As duas ações automáticas estão ativas. Corrija antes de processar a fila.'
            : `Fila sob responsabilidade do ${ownerName}. queuednotification: ${native.state_label || native.state || '-'} / ${native.mode_label || native.mode || '-'}. dashglpi_entitysmtp_queuednotification: ${dash.state_label || dash.state || '-'} / ${dash.mode_label || dash.mode || '-'}.`;
    }
    if (activate) activate.disabled = ownerName === 'Dash' || conflict;
    if (restore) restore.disabled = ownerName === 'GLPI' && !conflict;
}

async function submitEntitySmtp() {
    const form = document.getElementById('entitySmtpForm');
    const status = document.getElementById('entitySmtpStatus');
    if (!form) return;

    const submit = form.querySelector('button[type="submit"]');
    setAdminStatus(status, 'Salvando SMTP da entidade...', '');
    if (submit) submit.disabled = true;

    try {
        const formData = new FormData(form);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
        formData.set('entitysmtp_action', 'save_entity_smtp');
        formData.set('is_active', entitySmtpField('is_active')?.checked ? '1' : '0');
        formData.set('smtp_check_certificate', entitySmtpField('smtp_check_certificate')?.checked ? '1' : '0');

        const response = await fetch(PLUGIN_ROOT + '/ajax/entitysmtp.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        if (!data.entitysmtp) {
            throw new Error('Resposta incompleta ao salvar SMTP da entidade.');
        }
        fillEntitySmtpForm(data.entitysmtp);
        renderEntitySmtpOwner(data.owner || null);
        renderEntitiesTable();
        setAdminStatus(status, 'SMTP da entidade atualizado no GLPI.', 'success');
    } catch (error) {
        setAdminStatus(status, error.message || 'Erro ao salvar SMTP da entidade.', 'error');
    } finally {
        if (submit) submit.disabled = false;
    }
}

async function submitEntitySmtpTest() {
    const form = document.getElementById('entitySmtpForm');
    const status = document.getElementById('entitySmtpStatus');
    const button = document.getElementById('entitySmtpTestButton');
    if (!form) return;

    setAdminStatus(status, 'Enviando e-mail de teste...', '');
    if (button) button.disabled = true;

    try {
        const formData = new FormData();
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
        formData.set('entitysmtp_action', 'test_entity_smtp');
        formData.set('entities_id', entitySmtpField('entities_id')?.value || '0');

        const response = await fetch(PLUGIN_ROOT + '/ajax/entitysmtp.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        if (data.entitysmtp) fillEntitySmtpForm(data.entitysmtp);
        setAdminStatus(status, 'E-mail de teste enviado pelo SMTP da entidade.', 'success');
    } catch (error) {
        setAdminStatus(status, error.message || 'Erro ao testar SMTP da entidade.', 'error');
    } finally {
        if (button) button.disabled = false;
    }
}

async function submitEntitySmtpOwnerAction(action) {
    const status = document.getElementById('entitySmtpStatus');
    const button = action === 'activate_owner'
        ? document.getElementById('entitySmtpActivateOwner')
        : document.getElementById('entitySmtpRestoreOwner');

    setAdminStatus(status, action === 'activate_owner' ? 'Ativando Dash como dono da fila...' : 'Devolvendo fila ao GLPI...', '');
    if (button) button.disabled = true;

    try {
        const formData = new FormData();
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
        formData.set('entitysmtp_action', action);

        const response = await fetch(PLUGIN_ROOT + '/ajax/entitysmtp.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        renderEntitySmtpOwner(data.owner || null);
        setAdminStatus(status, action === 'activate_owner' ? 'Dash assumiu a fila de notificacoes.' : 'Fila devolvida ao GLPI.', 'success');
        if (typeof loadGlpiHealth === 'function') {
            await loadGlpiHealth(true);
        }
    } catch (error) {
        setAdminStatus(status, error.message || 'Erro ao alterar dono da fila.', 'error');
    } finally {
        if (button) button.disabled = false;
    }
}

async function openEntitySlaPolicy(entitiesId) {
    const entity = entityByIdOrRoot(entitiesId);
    if (!entity) {
        return;
    }

    setAdminStatus(document.getElementById('entitySlaPolicyStatus'), '', '');
    showPage('entitySlaPolicy', adminMenuLink('entities'), { updateHash: false });
    fillEntitySlaPolicyForm(entity, slaPolicyByEntity(entitiesId));

    try {
        const response = await fetch(`${PLUGIN_ROOT}/ajax/entity_sla_policy.php?entities_id=${encodeURIComponent(entitiesId)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        const refreshedEntity = entityByIdOrRoot(data.policy?.entities_id ?? entitiesId) || entity;
        fillEntitySlaPolicyForm(refreshedEntity, data.policy || slaPolicyByEntity(entitiesId));
        renderEntitiesTable();
    } catch (error) {
        setAdminStatus(document.getElementById('entitySlaPolicyStatus'), error.message || 'Erro ao carregar politica SLA.', 'error');
    }
}

function fillEntitySlaPolicyForm(entity, policy) {
    const form = document.getElementById('entitySlaPolicyForm');
    if (!form) return;

    const defaults = {
        entities_id: Number(entity.id) || 0,
        is_active: 1,
        tto_mode: 'fixed',
        tto_fixed_key: 'TTO-P1',
        ttr_mode: 'priority',
        ttr_fixed_key: 'TTR-P1'
    };
    const data = { ...defaults, ...(policy || {}) };

    form.elements.entities_id.value = String(data.entities_id);
    form.elements.is_active.checked = Number(data.is_active || 0) === 1;
    form.elements.tto_mode.value = data.tto_mode === 'priority' ? 'priority' : 'fixed';
    form.elements.tto_fixed_key.value = fixedSlaKeyForSelect(form.elements.tto_fixed_key, data.tto_fixed_key, 'TTO-P1');
    form.elements.ttr_mode.value = data.ttr_mode === 'fixed' ? 'fixed' : 'priority';
    form.elements.ttr_fixed_key.value = fixedSlaKeyForSelect(form.elements.ttr_fixed_key, data.ttr_fixed_key, 'TTR-P1');

    const label = document.getElementById('slaPolicyEntityName');
    if (label) {
        label.textContent = entity.completename || entity.name || `Entidade #${data.entities_id}`;
    }

    updateSlaPolicyFixedFields();
}

function fixedSlaKeyForSelect(select, key, fallback) {
    const normalized = String(key || '').toUpperCase();
    if (/^(TTO|TTR)-P[1-9][0-9]*$/.test(normalized) && [...select.options].some(option => option.value === normalized)) {
        return normalized;
    }
    return [...select.options].some(option => option.value === fallback) ? fallback : (select.options[0]?.value || '');
}

function updateSlaPolicyFixedFields() {
    const ttoMode = document.getElementById('slaPolicyTtoMode')?.value || 'fixed';
    const ttrMode = document.getElementById('slaPolicyTtrMode')?.value || 'priority';

    document.querySelectorAll('[data-policy-fixed="tto"]').forEach(field => {
        field.classList.toggle('muted-field', ttoMode !== 'fixed');
    });
    document.querySelectorAll('[data-policy-fixed="ttr"]').forEach(field => {
        field.classList.toggle('muted-field', ttrMode !== 'fixed');
    });
}

function bindEntitySlaPolicyForm() {
    const form = document.getElementById('entitySlaPolicyForm');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await submitEntitySlaPolicy('save_apply');
    });
}

async function submitEntitySlaPolicy(action) {
    const form = document.getElementById('entitySlaPolicyForm');
    const status = document.getElementById('entitySlaPolicyStatus');
    if (!form) return;

    const submit = action === 'save_apply'
        ? form.querySelector('button[type="submit"]')
        : document.getElementById('reapplyEntitySlaPolicy');
    setAdminStatus(status, action === 'save_apply' ? 'Criando/atualizando no GLPI...' : 'Reaplicando em chamados abertos...', '');
    if (submit) submit.disabled = true;

    try {
        const formData = new FormData(form);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
        formData.set('action', action);
        formData.set('is_recursive', '1');

        const response = await fetch(PLUGIN_ROOT + '/ajax/entity_sla_policy.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        if (data.policy) {
            const entity = entityByIdOrRoot(data.policy.entities_id);
            if (entity) fillEntitySlaPolicyForm(entity, data.policy);
        }
        renderEntitiesTable();
        setAdminStatus(status, entitySlaPolicyMessage(data, action), 'success');
        if (action === 'save_apply') {
            await updateSLAData();
        }
    } catch (error) {
        setAdminStatus(status, error.message || 'Erro ao processar politica SLA.', 'error');
    } finally {
        if (submit) submit.disabled = false;
    }
}

function entitySlaPolicyMessage(data, action) {
    if (action === 'reapply_open_tickets') {
        const tickets = data?.result?.tickets || {};
        return `Reaplicado. Aplicados: ${tickets.applied || 0}. Ignorados: ${tickets.ignored || 0}. Erros: ${tickets.errors || 0}.`;
    }

    const rules = Array.isArray(data?.result?.rules) ? data.result.rules.length : 0;
    return `Política SLA atualizada no GLPI. Regras gerenciadas: ${rules}.`;
}

function applyAdminPayload(data) {
    if (Array.isArray(data.entities)) DashState.adminEntitiesGlobal = data.entities;
    if (Array.isArray(data.groups)) DashState.adminGroupsGlobal = data.groups;
    if (Array.isArray(data.categories)) DashState.adminCategoriesGlobal = data.categories;
    if (Array.isArray(data.profiles)) DashState.adminProfilesGlobal = data.profiles;
    if (Array.isArray(data.profiles_full)) DashState.adminProfilesFullGlobal = data.profiles_full;
    if (Array.isArray(data.users)) DashState.adminUsersGlobal = data.users;
    if (Array.isArray(data.policies)) DashState.adminSlaPoliciesGlobal = data.policies;
    // PLAN-20260714-023: mapa users_id => telegram_user_id (menção real no Telegram).
    if (data.telegram_user_ids && typeof data.telegram_user_ids === 'object') {
        DashState.adminTelegramUserIds = data.telegram_user_ids;
    }
}

function populateAdminSelects() {
    const nonRootEntities = DashState.adminEntitiesGlobal.filter(entity => Number(entity.id) !== 0);
    const entityOptions = [
        '<option value="0">Entidade raiz</option>',
        ...nonRootEntities.map(entity => `<option value="${Number(entity.id) || 0}">${escHtml(entity.completename || entity.name)}</option>`)
    ].join('');

    document.querySelectorAll('[data-admin-select="entities"]').forEach(select => {
        const current = select.value;
        select.innerHTML = entityOptions;
        if ([...select.options].some(option => option.value === current)) {
            select.value = current;
        }
    });

    const categorySelect = document.getElementById('categoryParentSelect');
    if (categorySelect) {
        const current = categorySelect.value;
        categorySelect.innerHTML = [
            '<option value="0">Sem categoria pai</option>',
            ...DashState.adminCategoriesGlobal.map(category => {
                const id = Number(category.id) || 0;
                const entity = category.entity_name || entityNameById(category.entities_id);
                const label = `${category.completename || category.name} · ${entity}`;
                return `<option value="${id}">${escHtml(label)}</option>`;
            })
        ].join('');
        if ([...categorySelect.options].some(option => option.value === current)) {
            categorySelect.value = current;
        }
    }

    const profileSelect = document.getElementById('userProfileSelect');
    if (profileSelect) {
        const current = profileSelect.value;
        profileSelect.innerHTML = DashState.adminProfilesGlobal.length
            ? DashState.adminProfilesGlobal.map(profile => `<option value="${Number(profile.id) || 0}">${escHtml(profile.name)}</option>`).join('')
            : '<option value="">Nenhum perfil encontrado</option>';
        if ([...profileSelect.options].some(option => option.value === current)) {
            profileSelect.value = current;
        }
    }

    // Perfil default do import de usuários (PLAN-20260709-018): linhas sem coluna
    // "Perfil" usam este valor; pré-seleciona Self-Service na primeira carga.
    const importProfileSelect = document.getElementById('userImportProfileSelect');
    if (importProfileSelect) {
        const current = importProfileSelect.value;
        importProfileSelect.innerHTML = DashState.adminProfilesGlobal.length
            ? DashState.adminProfilesGlobal.map(profile => `<option value="${Number(profile.id) || 0}">${escHtml(profile.name)}</option>`).join('')
            : '<option value="0">Nenhum perfil encontrado</option>';
        if (current !== '' && [...importProfileSelect.options].some(option => option.value === current)) {
            importProfileSelect.value = current;
        } else {
            const selfService = DashState.adminProfilesGlobal.find(profile => String(profile.name || '').trim().toLowerCase() === 'self-service');
            if (selfService) {
                importProfileSelect.value = String(Number(selfService.id) || 0);
            }
        }
    }

    const groupSelect = document.getElementById('userGroupSelect');
    if (groupSelect) {
        const current = groupSelect.value;
        groupSelect.innerHTML = [
            '<option value="0">Sem grupo</option>',
            ...DashState.adminGroupsGlobal.map(group => `<option value="${Number(group.id) || 0}">${escHtml(group.completename || group.name)}</option>`)
        ].join('');
        if ([...groupSelect.options].some(option => option.value === current)) {
            groupSelect.value = current;
        }
    }

    populateUserFilterSelects();
}

function populateUserFilterSelects() {
    const entityFilter = document.getElementById('usersFilterEntity');
    if (entityFilter) {
        const current = entityFilter.value;
        entityFilter.innerHTML = [
            '<option value="">Todas</option>',
            ...DashState.adminEntitiesGlobal.map(entity => `<option value="${Number(entity.id) || 0}">${escHtml(entity.completename || entity.name || (Number(entity.id) === 0 ? 'Entidade raiz' : 'Entidade'))}</option>`)
        ].join('');
        if ([...entityFilter.options].some(option => option.value === current)) {
            entityFilter.value = current;
        }
    }

    const profileFilter = document.getElementById('usersFilterProfile');
    if (profileFilter) {
        const current = profileFilter.value;
        profileFilter.innerHTML = [
            '<option value="">Todos</option>',
            ...DashState.adminProfilesGlobal.map(profile => `<option value="${Number(profile.id) || 0}">${escHtml(profile.name)}</option>`)
        ].join('');
        if ([...profileFilter.options].some(option => option.value === current)) {
            profileFilter.value = current;
        }
    }
}

function renderAdminTables() {
    renderEntitiesTable();
    renderGroupsTable();
    renderCategoriesTable();
    renderUsersTable();
    renderProfilesTable();
}

function renderEntitiesTable() {
    const body = document.getElementById('entitiesTableBody');
    if (!body) return;

    const search = (document.getElementById('entitiesSearchInput')?.value || '').trim().toLowerCase();
    const allRows = adminEntitiesWithRoot();
    const rows = allRows.filter(entity => {
        const localPrefix = normalizeAdminTextValue(entity.notification_subject_tag || '');
        const prefixStatus = localPrefix !== ''
            ? `custom ${localPrefix}`
            : ((Number(entity.id) || 0) === 0 ? 'padrao glpi' : 'herdado');
        const closureStatus = entitySolutionClosureSearchLabel(entity);
        const smtpStatus = entitySmtpSearchLabel(entity);
        const slaStatus = entitySlaPolicySearchLabel(entity);
        if (!search) return true;
        return [entity.id, entity.name, entity.completename, entity.entities_id, localPrefix, prefixStatus, closureStatus, smtpStatus, slaStatus]
            .some(value => String(value || '').toLowerCase().includes(search));
    });

    setVal('entitiesCount', `${rows.length} de ${allRows.length} entidades`);

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="8" class="table-empty">Nenhuma entidade encontrada</td></tr>';
        return;
    }

    body.innerHTML = rows.map(entity => {
        const parentLabel = (Number(entity.entities_id) || 0) > 0 ? entityNameById(entity.entities_id) : 'Entidade raiz';
        return `
        <tr>
            <td><strong>#${escHtml(entity.id)}</strong></td>
            <td>
                <div class="table-ticket-info">
                    <div class="table-ticket-title">${escHtml(entity.completename || entity.name)}</div>
                    <div class="table-ticket-id">${escHtml(entity.name)}</div>
                </div>
            </td>
            <td data-label="Entidade pai">${escHtml(parentLabel)}</td>
            <td data-label="Prefixo notificações">${renderEntityNotificationPrefixBadge(entity)}</td>
            <td data-label="SMTP">${renderEntitySmtpBadge(entity)}</td>
            <td data-label="Política SLA">${renderEntitySlaPolicyBadge(entity)}</td>
            <td data-label="Fechamento pós-solução">${renderEntitySolutionClosureBadge(entity)}</td>
            <td>
                <div class="entity-table-actions table-action-group table-action-group--compact">
                    <button class="table-action entity-solution-closure-action" type="button" data-entity-solution-closure="${Number(entity.id) || 0}" title="Fechamento pós-solucao">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button class="table-action entity-prefix-action" type="button" data-entity-notification-prefix="${Number(entity.id) || 0}" title="Prefixo de notificacoes">
                        <i class="fas fa-tag"></i>
                    </button>
                    <button class="table-action entity-smtp-action" type="button" data-entity-smtp="${Number(entity.id) || 0}" title="SMTP da entidade">
                        <i class="fas fa-envelope"></i>
                    </button>
                    <button class="table-action sla-policy-action" type="button" data-entity-sla-policy="${Number(entity.id) || 0}" title="Política SLA">
                        <i class="fas fa-business-time"></i>
                    </button>
                    ${(Number(entity.id) || 0) > 0 ? `
                    <button class="table-action" type="button" data-entity-edit="${Number(entity.id) || 0}" title="Editar entidade">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="table-action" type="button" data-entity-clone="${Number(entity.id) || 0}" title="Clonar entidade">
                        <i class="fas fa-clone"></i>
                    </button>
                    <button class="table-action" type="button" data-entity-delete="${Number(entity.id) || 0}" title="Excluir entidade">
                        <i class="fas fa-trash"></i>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `}).join('');
}

function renderEntityNotificationPrefixBadge(entity) {
    const localPrefix = normalizeAdminTextValue(entity.notification_subject_tag || '');
    if (localPrefix !== '') {
        return `<span class="table-badge primary">Custom: ${escHtml(localPrefix)}</span>`;
    }

    if ((Number(entity.id) || 0) === 0) {
        return '<span class="table-badge warning">Padrão GLPI</span>';
    }

    return '<span class="table-badge muted">Herdado</span>';
}

function entitySmtpInfo(entity) {
    const effective = entitySmtpEffectiveFromEntity(entity);
    const host = normalizeAdminTextValue(effective.host || '');
    const sourceName = normalizeAdminTextValue(effective.source_entity_name || '');
    const localHost = normalizeAdminTextValue(entity?.entitysmtp_host || '');
    const passwordPending = normalizeAdminTextValue(effective.smtp_username || '') !== '' && !effective.password_configured;

    if (effective.origin === 'entity') {
        return {
            label: passwordPending ? 'Senha pendente' : 'SMTP próprio',
            badgeClass: passwordPending ? 'warning' : 'success',
            host,
            meta: host,
            searchLabel: `smtp proprio ativo ${host} ${effective.smtp_username || ''}`
        };
    }

    if (effective.origin === 'inherited') {
        return {
            label: passwordPending ? 'Herdado sem senha' : 'SMTP herdado',
            badgeClass: passwordPending ? 'warning' : 'primary',
            host,
            meta: sourceName !== '' ? `${sourceName} · ${host}` : host,
            searchLabel: `smtp herdado ${host} ${sourceName} ${effective.smtp_username || ''}`
        };
    }

    return {
        label: 'SMTP global',
        badgeClass: 'muted',
        host: '',
        meta: localHost !== '' ? `Local inativo · ${localHost}` : '',
        searchLabel: `smtp global fallback sem smtp proprio ${localHost}`
    };
}

function entitySmtpEffectiveFromEntity(entity) {
    if (!entity) {
        return entitySmtpGlobalEffective();
    }

    if (entity.entitysmtp_effective_origin) {
        return {
            origin: entity.entitysmtp_effective_origin || 'global',
            source_entities_id: Number(entity.entitysmtp_effective_source_entities_id || 0),
            source_entity_name: entity.entitysmtp_effective_source_entity_name || 'SMTP global do GLPI',
            host: entity.entitysmtp_effective_host || '',
            port: Number(entity.entitysmtp_effective_port || 0),
            encryption: entity.entitysmtp_effective_encryption || '',
            smtp_username: entity.entitysmtp_effective_username || '',
            password_configured: Number(entity.entitysmtp_effective_password_configured || 0) === 1
        };
    }

    let current = entity;
    const visited = new Set();
    while (current) {
        const id = Number(current.id) || 0;
        if (visited.has(id)) break;
        visited.add(id);

        const host = normalizeAdminTextValue(current.entitysmtp_host || '');
        if (Number(current.entitysmtp_is_active || 0) === 1 && host !== '') {
            return {
                origin: id === (Number(entity.id) || 0) ? 'entity' : 'inherited',
                source_entities_id: id,
                source_entity_name: current.completename || current.name || `Entidade #${id}`,
                host,
                port: Number(current.entitysmtp_port || 0),
                encryption: current.entitysmtp_encryption || '',
                smtp_username: current.entitysmtp_username || '',
                password_configured: Number(current.entitysmtp_password_configured || 0) === 1
            };
        }

        if (id === 0) break;
        const parentId = Number(current.entities_id) || 0;
        if (parentId === id) break;
        current = entityByIdOrRoot(parentId);
    }

    return entitySmtpGlobalEffective();
}

function entitySmtpGlobalEffective() {
    return {
        origin: 'global',
        source_entities_id: 0,
        source_entity_name: 'SMTP global do GLPI',
        host: '',
        port: 0,
        encryption: '',
        smtp_username: '',
        password_configured: false
    };
}

function entitySmtpSearchLabel(entity) {
    const info = entitySmtpInfo(entity);
    return `${info.label} ${info.searchLabel}`;
}

function renderEntitySmtpBadge(entity) {
    const info = entitySmtpInfo(entity);
    const meta = info.meta ? `<span class="table-ticket-id">${escHtml(info.meta)}</span>` : '';
    return `
        <div class="sla-policy-badge-stack">
            <span class="table-badge ${escHtml(info.badgeClass)}">${escHtml(info.label)}</span>
            ${meta}
        </div>
    `;
}

function entitySolutionClosureInfo(entity) {
    const value = Number(entity?.autoclose_delay);
    if (value === -2 && Number(entity?.id || 0) !== 0) {
        return {
            mode: 'inherit',
            label: 'Herdado',
            badgeClass: 'muted',
            searchLabel: 'herdado'
        };
    }

    if (value === 0) {
        return {
            mode: 'immediate',
            label: 'Imediato',
            badgeClass: 'success',
            searchLabel: 'imediato fecha imediatamente'
        };
    }

    if (value === -10) {
        return {
            mode: 'never',
            label: 'Nunca',
            badgeClass: 'warning',
            searchLabel: 'nunca nao fecha automaticamente'
        };
    }

    if (value > 0) {
        const dayLabel = `${value} dia${value === 1 ? '' : 's'}`;
        return {
            mode: 'days',
            label: dayLabel,
            badgeClass: 'primary',
            searchLabel: `${dayLabel} fecha apos ${dayLabel}`
        };
    }

    return {
        mode: 'never',
        label: Number(entity?.id || 0) === 0 ? 'Nunca' : 'Herdado',
        badgeClass: Number(entity?.id || 0) === 0 ? 'warning' : 'muted',
        searchLabel: Number(entity?.id || 0) === 0 ? 'nunca' : 'herdado'
    };
}

function entitySolutionClosureSearchLabel(entity) {
    const info = entitySolutionClosureInfo(entity);
    return `${info.mode} ${info.searchLabel}`;
}

function renderEntitySolutionClosureBadge(entity) {
    const info = entitySolutionClosureInfo(entity);
    return `<span class="table-badge ${escHtml(info.badgeClass)}">${escHtml(info.label)}</span>`;
}

function slaPolicyByEntity(entitiesId) {
    const id = Number(entitiesId) || 0;
    return DashState.adminSlaPoliciesGlobal.find(policy => Number(policy.entities_id) === id) || null;
}

function entitySlaPolicyInfo(entity) {
    const requestedId = Number(entity?.id) || 0;
    let current = entity || entityByIdOrRoot(requestedId);
    const visited = new Set();
    let inactiveDirect = null;

    while (current) {
        const currentId = Number(current.id) || 0;
        if (visited.has(currentId)) {
            break;
        }
        visited.add(currentId);

        const policy = slaPolicyByEntity(currentId);
        if (policy) {
            const active = Number(policy.is_active || 0) === 1;
            const recursive = Number(policy.is_recursive ?? 1) === 1;
            if (active && (currentId === requestedId || recursive)) {
                return {
                    origin: currentId === requestedId ? 'entity' : 'inherited',
                    policy,
                    sourceName: current.completename || current.name || (currentId === 0 ? 'Entidade raiz' : `Entidade #${currentId}`)
                };
            }
            if (currentId === requestedId && !active) {
                inactiveDirect = policy;
            }
        }

        if (currentId === 0) {
            break;
        }

        const parentId = Number(current.entities_id) || 0;
        if (parentId === currentId) {
            break;
        }
        current = entityByIdOrRoot(parentId);
    }

    if (inactiveDirect) {
        return { origin: 'inactive', policy: inactiveDirect, sourceName: '' };
    }

    return { origin: 'none', policy: null, sourceName: '' };
}

function entitySlaPolicySearchLabel(entity) {
    const info = entitySlaPolicyInfo(entity);
    const summary = info.policy ? entitySlaPolicySummary(info.policy) : '';
    return `${info.origin} ${info.sourceName || ''} ${summary}`;
}

function slaDisplayLabel(key) {
    const normalized = String(key || '').toUpperCase();
    if (typeof DASHGLPI_SLA_DISPLAY_LABELS !== 'undefined' && DASHGLPI_SLA_DISPLAY_LABELS?.[normalized]) {
        return DASHGLPI_SLA_DISPLAY_LABELS[normalized];
    }
    return normalized;
}

function entitySlaPolicySummary(policy) {
    if (!policy) {
        return '';
    }

    const tto = policy.tto_mode === 'fixed' ? slaDisplayLabel(policy.tto_fixed_key) : 'TTO por prioridade';
    const ttr = policy.ttr_mode === 'fixed' ? slaDisplayLabel(policy.ttr_fixed_key) : 'TTR por prioridade';
    return `${tto} · ${ttr}`;
}

function renderEntitySlaPolicyBadge(entity) {
    const info = entitySlaPolicyInfo(entity);
    const policy = info.policy;

    if (!policy) {
        return '<span class="table-badge warning">Não configurada</span>';
    }

    if (info.origin === 'inactive') {
        return `
            <div class="sla-policy-badge-stack">
                <span class="table-badge warning">Inativa</span>
                <span class="table-ticket-id">${escHtml(entitySlaPolicySummary(policy))}</span>
            </div>
        `;
    }

    const inherited = info.origin === 'inherited';
    const source = inherited && info.sourceName
        ? `Herdado de ${info.sourceName}`
        : entitySlaPolicySummary(policy);

    return `
        <div class="sla-policy-badge-stack">
            <span class="table-badge ${inherited ? 'muted' : 'success'}">${inherited ? 'Herdada' : 'Ativa'}</span>
            <span class="table-ticket-id">${escHtml(entitySlaPolicySummary(policy))}${inherited ? ` · ${escHtml(source)}` : ''}</span>
        </div>
    `;
}

function renderGroupsTable() {
    const body = document.getElementById('groupsTableBody');
    if (!body) return;

    const search = (document.getElementById('groupsSearchInput')?.value || '').trim().toLowerCase();
    const rows = DashState.adminGroupsGlobal.filter(group => {
        if (!search) return true;
        return [group.id, group.name, group.completename, entityNameById(group.entities_id), group.is_recursive ? 'recursivo' : 'local']
            .some(value => String(value || '').toLowerCase().includes(search));
    });

    setVal('groupsCount', `${rows.length} de ${DashState.adminGroupsGlobal.length} grupos`);

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="5" class="table-empty">Nenhum grupo encontrado</td></tr>';
        return;
    }

    body.innerHTML = rows.map(group => `
        <tr>
            <td><strong>#${escHtml(group.id)}</strong></td>
            <td>
                <div class="table-ticket-info">
                    <div class="table-ticket-title">${escHtml(group.completename || group.name)}</div>
                    <div class="table-ticket-id">${escHtml(group.name)}</div>
                </div>
            </td>
            <td data-label="Entidade">${escHtml(entityNameById(group.entities_id))}</td>
            <td data-pin="status"><span class="table-badge ${Number(group.is_recursive) ? 'success' : 'warning'}">${Number(group.is_recursive) ? 'Sim' : 'Não'}</span></td>
            <td>
                <div class="table-action-group">
                    <button class="table-action" type="button" data-group-edit="${Number(group.id) || 0}" title="Editar grupo">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="table-action" type="button" data-group-clone="${Number(group.id) || 0}" title="Clonar grupo">
                        <i class="fas fa-clone"></i>
                    </button>
                    <button class="table-action" type="button" data-group-delete="${Number(group.id) || 0}" title="Excluir grupo">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function renderCategoriesTable() {
    const body = document.getElementById('categoriesTableBody');
    if (!body) return;

    const search = (document.getElementById('categoriesSearchInput')?.value || '').trim().toLowerCase();
    const rows = DashState.adminCategoriesGlobal.filter(category => {
        if (!search) return true;
        const flags = [
            Number(category.is_recursive) ? 'recursivo' : 'local',
            Number(category.is_helpdeskvisible) ? 'helpdesk' : 'interno',
            Number(category.is_incident) ? 'incidente' : '',
            Number(category.is_request) ? 'requisicao requisição' : '',
            Number(category.is_problem) ? 'problema' : '',
            Number(category.is_change) ? 'mudanca mudança' : ''
        ];
        return [
            category.id,
            category.name,
            category.completename,
            category.entity_name,
            category.parent_name,
            category.itilcategories_id,
            ...flags
        ].some(value => String(value || '').toLowerCase().includes(search));
    });

    setVal('categoriesCount', `${rows.length} de ${DashState.adminCategoriesGlobal.length} categorias`);

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="5" class="table-empty">Nenhuma categoria encontrada</td></tr>';
        return;
    }

    body.innerHTML = rows.map(category => `
        <tr>
            <td><strong>#${escHtml(category.id)}</strong></td>
            <td>
                <div class="table-ticket-info">
                    <div class="table-ticket-title">${escHtml(category.completename || category.name)}</div>
                    <div class="table-ticket-id">${escHtml(category.name)}</div>
                </div>
            </td>
            <td data-label="Entidade">${escHtml(category.entity_name || entityNameById(category.entities_id))}</td>
            <td data-label="Pai">${escHtml(category.parent_name || (Number(category.itilcategories_id) > 0 ? `#${category.itilcategories_id}` : 'Sem pai'))}</td>
            <td data-label="Uso">${renderCategoryFlags(category)}</td>
        </tr>
    `).join('');
}

function renderCategoryFlags(category) {
    const flags = [
        { active: Number(category.is_recursive) === 1, label: 'Recursiva' },
        { active: Number(category.is_helpdeskvisible) === 1, label: 'Helpdesk' },
        { active: Number(category.is_incident) === 1, label: 'Incidente' },
        { active: Number(category.is_request) === 1, label: 'Requisição' },
        { active: Number(category.is_problem) === 1, label: 'Problema' },
        { active: Number(category.is_change) === 1, label: 'Mudança' }
    ];

    return `
        <div class="category-flag-stack">
            ${flags.map(flag => `<span class="table-badge ${flag.active ? 'success' : 'muted'}">${escHtml(flag.label)}</span>`).join('')}
        </div>
    `;
}

function renderUsersTable() {
    const body = document.getElementById('usersTableBody');
    if (!body) return;

    const search = (document.getElementById('usersSearchInput')?.value || '').trim().toLowerCase();
    const entityFilter = document.getElementById('usersFilterEntity')?.value ?? '';
    const profileFilter = document.getElementById('usersFilterProfile')?.value ?? '';
    const statusFilter = document.getElementById('usersFilterStatus')?.value ?? '';

    const rows = DashState.adminUsersGlobal.filter(user => {
        if (entityFilter !== '' && String(Number(user.entities_id) || 0) !== String(entityFilter)) return false;
        if (profileFilter !== '' && String(Number(user.profiles_id) || 0) !== String(profileFilter)) return false;
        if (statusFilter !== '' && String(Number(user.is_active) || 0) !== String(statusFilter)) return false;
        if (!search) return true;
        return [user.id, user.name, user.firstname, user.realname, user.email, user.entity_name, user.profile_name, user.entity_list, user.profile_list, user.group_name]
            .some(value => String(value || '').toLowerCase().includes(search));
    });

    setVal('usersCount', `${rows.length} de ${DashState.adminUsersGlobal.length} usuários`);

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="table-empty">Nenhum usuário encontrado</td></tr>';
        return;
    }

    body.innerHTML = rows.map(user => {
        const displayName = [user.firstname, user.realname].filter(Boolean).join(' ') || user.name;
        const entityName = user.entity_name || ((user.profile_name || user.profile_list) ? (user.entity_list || '-') : '-');
        const profileName = user.profile_name || user.profile_list || '-';
        return `
            <tr>
                <td><strong>#${escHtml(user.id)}</strong></td>
                <td>
                    <div class="table-ticket-info">
                        <div class="table-ticket-title">${escHtml(displayName)}</div>
                        <div class="table-ticket-id">${escHtml(user.name)}</div>
                    </div>
                </td>
                <td>${escHtml(user.email || '-')}</td>
                <td data-label="Entidade">${escHtml(entityName)}</td>
                <td data-label="Perfil">${escHtml(profileName)}</td>
                <td><span class="table-badge ${Number(user.is_active) ? 'success' : 'warning'}">${Number(user.is_active) ? 'Ativo' : 'Inativo'}</span></td>
                <td>
                    <div class="table-action-group">
                        <button class="table-action" type="button" data-user-edit="${Number(user.id) || 0}" title="Editar usuário">
                            <i class="fas fa-pen"></i>
                            <span class="table-action-label">Editar</span>
                        </button>
                        <button class="table-action" type="button" data-user-clone="${Number(user.id) || 0}" title="Copiar usuário">
                            <i class="fas fa-clone"></i>
                            <span class="table-action-label">Copiar</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function userFormElement() {
    return document.getElementById('userForm');
}

function findAdminUserById(userId) {
    return DashState.adminUsersGlobal.find(user => Number(user.id) === Number(userId)) || null;
}

function setUserFormMode(mode = 'create', user = null) {
    const form = userFormElement();
    if (!form) return;

    const isEdit = mode === 'edit' && Number(user?.id || form.elements.id?.value || 0) > 0;
    DashState.adminUserEditorMode = isEdit ? 'edit' : 'create';

    const pageTitle = document.getElementById('userFormPageTitle');
    const subtitleText = document.getElementById('userFormPageSubtitleText');
    const cardTitle = document.getElementById('userFormCardTitle');
    const submitText = document.getElementById('userSubmitButtonText');
    const passwordLabel = document.getElementById('userPasswordLabel');
    const passwordInput = document.getElementById('userPasswordInput');
    const passwordHelp = document.getElementById('userPasswordHelp');

    if (form.elements.id) {
        form.elements.id.value = isEdit ? String(Number(user?.id) || 0) : '0';
    }
    // PLAN-20260714-023: Telegram user ID é salvo à parte (tabela própria do plugin,
    // não campo de glpi_users) — só faz sentido depois que o usuário já existe.
    const telegramField = document.getElementById('userTelegramId');
    if (telegramField) {
        telegramField.disabled = !isEdit;
        if (!isEdit) {
            telegramField.value = '';
            telegramField.placeholder = 'Salve o usuário primeiro para poder cadastrar';
        } else {
            telegramField.placeholder = 'Ex.: 123456789';
        }
    }
    if (pageTitle) pageTitle.textContent = isEdit ? 'Editar Usuário' : 'Novo Usuário';
    if (subtitleText) subtitleText.textContent = isEdit ? 'Atualizar perfil, entidade e dados do usuário' : 'Criar usuário com entidade e perfil';
    if (cardTitle) cardTitle.textContent = isEdit ? 'Editar Dados do Usuário' : 'Dados do Usuário';
    if (submitText) submitText.textContent = isEdit ? 'Salvar Alterações' : 'Cadastrar Usuário';
    if (passwordLabel) passwordLabel.textContent = isEdit ? 'Nova senha' : 'Senha temporária';
    if (passwordInput) {
        passwordInput.required = !isEdit;
        passwordInput.value = '';
        passwordInput.placeholder = isEdit ? 'Deixe em branco para manter a senha atual' : '';
    }
    if (passwordHelp) {
        passwordHelp.textContent = isEdit
            ? 'Opcional na edição. Preencha apenas se quiser trocar a senha.'
            : 'Obrigatória para novos usuários.';
    }

    const meter = document.getElementById('userPasswordMeter');
    if (meter) meter.hidden = true;
    if (passwordInput) passwordInput.type = 'password';
    const toggleIcon = document.getElementById('userPasswordToggle')?.querySelector('i');
    if (toggleIcon) toggleIcon.className = 'fas fa-eye';
    setUserWizardStep(1);
}

function populateUserForm(user) {
    const form = userFormElement();
    if (!form || !user) return;

    form.reset();
    setUserFormMode('edit', user);

    form.elements.login.value = user.name || '';
    form.elements.firstname.value = user.firstname || '';
    form.elements.realname.value = user.realname || '';
    form.elements.email.value = user.email || '';
    form.elements.is_active.checked = Number(user.is_active || 0) === 1;

    const entitiesId = String(Number(user.entities_id) || 0);
    const profilesId = String(Number(user.profiles_id) || 0);
    const groupsId = String(Number(user.groups_id) || 0);

    if ([...form.elements.entities_id.options].some(option => option.value === entitiesId)) {
        form.elements.entities_id.value = entitiesId;
    }
    if ([...form.elements.profiles_id.options].some(option => option.value === profilesId)) {
        form.elements.profiles_id.value = profilesId;
    }
    if ([...form.elements.groups_id.options].some(option => option.value === groupsId)) {
        form.elements.groups_id.value = groupsId;
    }

    // PLAN-20260714-023: pré-preenche com o valor já salvo na tabela do plugin.
    const telegramField = form.elements.telegram_user_id;
    if (telegramField) {
        telegramField.disabled = false;
        telegramField.placeholder = 'Ex.: 123456789';
        telegramField.value = DashState.adminTelegramUserIds[Number(user.id)] || '';
    }
}

/**
 * PLAN-20260714-023: salva o Telegram user ID isoladamente (tabela própria do
 * plugin) ao sair do campo — não passa pelo submit principal do #userForm porque
 * não é campo de glpi_users (o bridge/GLPI-core não sabe nada sobre esse dado).
 */
function bindUserTelegramIdField() {
    const field = document.getElementById('userTelegramId');
    if (!field) return;

    field.addEventListener('change', async () => {
        const form = userFormElement();
        const usersId = Number(form?.elements.id?.value || 0);
        if (usersId <= 0) return;

        const status = document.getElementById('userStatus');
        try {
            const formData = new FormData();
            formData.set('action', 'save_telegram_id');
            formData.set('users_id', String(usersId));
            formData.set('telegram_user_id', field.value.trim());
            formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

            const response = await fetch(PLUGIN_ROOT + '/ajax/users.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await adminReadJson(response);
            applyAdminPayload(data);
            setAdminStatus(status, 'Telegram user ID salvo.', 'success');
        } catch (error) {
            setAdminStatus(status, error.message || 'Erro ao salvar Telegram user ID.', 'error');
        }
    });
}

async function openUserEditor(userId) {
    if (Number(userId) <= 0) return;

    resetAdminStatus('user');
    showPage('userNew', adminMenuLink('users'), { updateHash: false });

    try {
        await loadAdminRegisters('users', true);
        populateAdminSelects();
        const user = findAdminUserById(userId);
        if (!user) {
            throw new Error('Usuário não encontrado para edição.');
        }
        populateUserForm(user);
    } catch (error) {
        setAdminStatus(document.getElementById('userStatus'), error.message || 'Erro ao carregar usuário.', 'error');
    }
}

function clearUserFilters() {
    const entityFilter = document.getElementById('usersFilterEntity');
    const profileFilter = document.getElementById('usersFilterProfile');
    const statusFilter = document.getElementById('usersFilterStatus');
    const search = document.getElementById('usersSearchInput');
    if (entityFilter) entityFilter.value = '';
    if (profileFilter) profileFilter.value = '';
    if (statusFilter) statusFilter.value = '';
    if (search) search.value = '';
    renderUsersTable();
}

async function cloneUser(userId) {
    const user = findAdminUserById(userId);
    if (!user) return;

    const label = [user.firstname, user.realname].filter(Boolean).join(' ') || user.name;
    if (!window.confirm(`Clonar o usuário ${label}? A cópia será criada como inativa para revisão.`)) {
        return;
    }

    const suggested = `${user.name || 'usuario'}_copia`;
    const login = (window.prompt('Informe o login do novo usuário (deve ser único):', suggested) || '').trim();
    if (!login) return;

    try {
        const formData = new FormData();
        formData.set('user_action', 'clone');
        formData.set('id', String(Number(userId) || 0));
        formData.set('login', login);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/users.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        applyAdminPayload(data);
        populateAdminSelects();
        renderAdminTables();

        const newId = data.user?.id || data.result?.user?.id || 0;
        window.alert(`Usuário clonado como inativo (login "${login}"${newId ? `, #${newId}` : ''}). Revise e ative quando estiver pronto.`);
    } catch (error) {
        window.alert(error.message || 'Falha ao clonar usuário.');
    }
}

// ---- Entidade: editar / clonar / excluir ----
function findAdminEntityById(entityId) {
    return DashState.adminEntitiesGlobal.find(entity => Number(entity.id) === Number(entityId)) || null;
}

function setEntityFormMode(mode = 'create', entity = null) {
    const form = document.getElementById('entityForm');
    if (!form) return;

    const isEdit = mode === 'edit' && Number(entity?.id || form.elements.id?.value || 0) > 0;
    DashState.adminEntityEditorMode = isEdit ? 'edit' : 'create';

    const pageTitle = document.getElementById('entityFormPageTitle');
    const subtitleText = document.getElementById('entityFormPageSubtitleText');
    const cardTitle = document.getElementById('entityFormCardTitle');
    const submitText = document.getElementById('entitySubmitButtonText');
    const createOnly = form.querySelector('[data-entity-create-only]');

    if (form.elements.id) {
        form.elements.id.value = isEdit ? String(Number(entity?.id) || 0) : '0';
    }
    if (pageTitle) pageTitle.textContent = isEdit ? 'Editar Entidade' : 'Nova Entidade';
    if (subtitleText) subtitleText.textContent = isEdit ? 'Atualizar dados do cliente/entidade' : 'Criar cliente na árvore de entidades do GLPI';
    if (cardTitle) cardTitle.textContent = isEdit ? 'Editar Dados do Cliente' : 'Dados do Cliente';
    if (submitText) submitText.textContent = isEdit ? 'Salvar Alterações' : 'Cadastrar Cliente';
    if (createOnly) createOnly.hidden = isEdit;
}

function populateEntityForm(entity) {
    const form = document.getElementById('entityForm');
    if (!form || !entity) return;

    form.reset();
    setEntityFormMode('edit', entity);

    form.elements.name.value = entity.name || '';
    const parentId = String(Number(entity.entities_id) || 0);
    if ([...form.elements.parent_id.options].some(option => option.value === parentId)) {
        form.elements.parent_id.value = parentId;
    }
}

async function openEntityEditor(entityId) {
    if (Number(entityId) <= 0) return;

    resetAdminStatus('entity');
    showPage('entityNew', adminMenuLink('entity'), { updateHash: false });

    try {
        await loadAdminRegisters('entities', true);
        populateAdminSelects();
        const entity = findAdminEntityById(entityId);
        if (!entity) {
            throw new Error('Entidade não encontrada para edição.');
        }
        populateEntityForm(entity);
    } catch (error) {
        setAdminStatus(document.getElementById('entityStatus'), error.message || 'Erro ao carregar entidade.', 'error');
    }
}

async function cloneEntity(entityId) {
    const entity = findAdminEntityById(entityId);
    if (!entity) return;

    const label = entity.completename || entity.name;
    if (!window.confirm(`Clonar a entidade ${label}? Grupos, usuários e políticas específicas não são copiados.`)) {
        return;
    }

    const suggested = `${entity.name || 'entidade'} (cópia)`;
    const name = (window.prompt('Informe o nome da nova entidade (deve ser único entre as entidades irmãs):', suggested) || '').trim();
    if (!name) return;

    try {
        const formData = new FormData();
        formData.set('entity_action', 'clone');
        formData.set('id', String(Number(entityId) || 0));
        formData.set('name', name);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/entities.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        applyAdminPayload(data);
        populateAdminSelects();
        renderAdminTables();

        const newId = data.result?.entity?.id || 0;
        window.alert(`Entidade clonada (nome "${name}"${newId ? `, #${newId}` : ''}).`);
    } catch (error) {
        window.alert(error.message || 'Falha ao clonar entidade.');
    }
}

async function deleteEntity(entityId) {
    const entity = findAdminEntityById(entityId);
    if (!entity) return;

    const label = entity.completename || entity.name;
    if (!window.confirm(`Excluir a entidade ${label}? Esta ação não pode ser desfeita. A exclusão será bloqueada se houver entidades-filhas, grupos, usuários ou chamados vinculados.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.set('entity_action', 'delete');
        formData.set('id', String(Number(entityId) || 0));
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/entities.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        applyAdminPayload(data);
        populateAdminSelects();
        renderAdminTables();

        window.alert('Entidade excluída com sucesso.');
    } catch (error) {
        window.alert(error.message || 'Falha ao excluir entidade.');
    }
}

// ---- Grupo: editar / clonar / excluir ----
function findAdminGroupById(groupId) {
    return DashState.adminGroupsGlobal.find(group => Number(group.id) === Number(groupId)) || null;
}

function setGroupFormMode(mode = 'create', group = null) {
    const form = document.getElementById('groupForm');
    if (!form) return;

    const isEdit = mode === 'edit' && Number(group?.id || form.elements.id?.value || 0) > 0;
    DashState.adminGroupEditorMode = isEdit ? 'edit' : 'create';

    const pageTitle = document.getElementById('groupFormPageTitle');
    const subtitleText = document.getElementById('groupFormPageSubtitleText');
    const submitText = document.getElementById('groupSubmitButtonText');

    if (form.elements.id) {
        form.elements.id.value = isEdit ? String(Number(group?.id) || 0) : '0';
    }
    if (pageTitle) pageTitle.textContent = isEdit ? 'Editar Grupo' : 'Novo Grupo';
    if (subtitleText) subtitleText.textContent = isEdit ? 'Atualizar dados do grupo' : 'Criar grupo posicionado em uma entidade';
    if (submitText) submitText.textContent = isEdit ? 'Salvar Alterações' : 'Cadastrar Grupo';
}

function populateGroupForm(group) {
    const form = document.getElementById('groupForm');
    if (!form || !group) return;

    form.reset();
    setGroupFormMode('edit', group);

    form.elements.name.value = group.name || '';
    form.elements.comment.value = group.comment || '';
    form.elements.is_recursive.checked = Number(group.is_recursive || 0) === 1;

    const entitiesId = String(Number(group.entities_id) || 0);
    if ([...form.elements.entities_id.options].some(option => option.value === entitiesId)) {
        form.elements.entities_id.value = entitiesId;
    }
}

async function openGroupEditor(groupId) {
    if (Number(groupId) <= 0) return;

    resetAdminStatus('group');
    showPage('groupNew', adminMenuLink('group'), { updateHash: false });

    try {
        await loadAdminRegisters('groups', true);
        populateAdminSelects();
        const group = findAdminGroupById(groupId);
        if (!group) {
            throw new Error('Grupo não encontrado para edição.');
        }
        populateGroupForm(group);
    } catch (error) {
        setAdminStatus(document.getElementById('groupStatus'), error.message || 'Erro ao carregar grupo.', 'error');
    }
}

async function cloneGroup(groupId) {
    const group = findAdminGroupById(groupId);
    if (!group) return;

    const label = group.completename || group.name;
    if (!window.confirm(`Clonar o grupo ${label}? Membros não são copiados.`)) {
        return;
    }

    const suggested = `${group.name || 'grupo'} (cópia)`;
    const name = (window.prompt('Informe o nome do novo grupo (deve ser único na mesma entidade):', suggested) || '').trim();
    if (!name) return;

    try {
        const formData = new FormData();
        formData.set('group_action', 'clone');
        formData.set('id', String(Number(groupId) || 0));
        formData.set('name', name);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/groups.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        applyAdminPayload(data);
        populateAdminSelects();
        renderAdminTables();

        const newId = data.result?.group?.id || 0;
        window.alert(`Grupo clonado (nome "${name}"${newId ? `, #${newId}` : ''}).`);
    } catch (error) {
        window.alert(error.message || 'Falha ao clonar grupo.');
    }
}

async function deleteGroup(groupId) {
    const group = findAdminGroupById(groupId);
    if (!group) return;

    const label = group.completename || group.name;
    if (!window.confirm(`Excluir o grupo ${label}? Esta ação não pode ser desfeita. A exclusão será bloqueada se houver membros ou chamados vinculados.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.set('group_action', 'delete');
        formData.set('id', String(Number(groupId) || 0));
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/groups.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        applyAdminPayload(data);
        populateAdminSelects();
        renderAdminTables();

        window.alert('Grupo excluído com sucesso.');
    } catch (error) {
        window.alert(error.message || 'Falha ao excluir grupo.');
    }
}

// ---- Perfil: listar / editar / clonar / excluir ----
function renderProfilesTable() {
    const body = document.getElementById('profilesTableBody');
    if (!body) return;

    const search = (document.getElementById('profilesSearchInput')?.value || '').trim().toLowerCase();
    const rows = DashState.adminProfilesFullGlobal.filter(profile => {
        if (!search) return true;
        return [profile.id, profile.name, profile.interface, profile.comment]
            .some(value => String(value || '').toLowerCase().includes(search));
    });

    setVal('profilesCount', `${rows.length} de ${DashState.adminProfilesFullGlobal.length} perfis`);

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="5" class="table-empty">Nenhum perfil encontrado</td></tr>';
        return;
    }

    body.innerHTML = rows.map(profile => `
        <tr>
            <td><strong>#${escHtml(profile.id)}</strong></td>
            <td>${escHtml(profile.name)}</td>
            <td data-pin="status"><span class="table-badge ${profile.interface === 'central' ? 'primary' : 'muted'}">${profile.interface === 'central' ? 'Central' : 'Helpdesk'}</span></td>
            <td data-label="Comentário">${escHtml(profile.comment || '-')}</td>
            <td>
                <div class="table-action-group">
                    <button class="table-action" type="button" data-profile-edit="${Number(profile.id) || 0}" title="Editar perfil">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="table-action" type="button" data-profile-clone="${Number(profile.id) || 0}" title="Clonar perfil">
                        <i class="fas fa-clone"></i>
                    </button>
                    <button class="table-action" type="button" data-profile-delete="${Number(profile.id) || 0}" title="Excluir perfil">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function findAdminProfileById(profileId) {
    return DashState.adminProfilesFullGlobal.find(profile => Number(profile.id) === Number(profileId)) || null;
}

function setProfileFormMode(mode = 'create', profile = null) {
    const form = document.getElementById('profileForm');
    if (!form) return;

    const isEdit = mode === 'edit' && Number(profile?.id || form.elements.id?.value || 0) > 0;
    DashState.adminProfileEditorMode = isEdit ? 'edit' : 'create';

    const pageTitle = document.getElementById('profileFormPageTitle');
    const subtitleText = document.getElementById('profileFormPageSubtitleText');
    const submitText = document.getElementById('profileSubmitButtonText');

    if (form.elements.id) {
        form.elements.id.value = isEdit ? String(Number(profile?.id) || 0) : '0';
    }
    if (pageTitle) pageTitle.textContent = isEdit ? 'Editar Perfil' : 'Novo Perfil';
    if (subtitleText) subtitleText.textContent = isEdit ? 'Atualizar dados do perfil' : 'Criar perfil de acesso do GLPI';
    if (submitText) submitText.textContent = isEdit ? 'Salvar Alterações' : 'Cadastrar Perfil';
}

function populateProfileForm(profile) {
    const form = document.getElementById('profileForm');
    if (!form || !profile) return;

    form.reset();
    setProfileFormMode('edit', profile);

    form.elements.name.value = profile.name || '';
    form.elements.comment.value = profile.comment || '';
    if ([...form.elements.interface.options].some(option => option.value === profile.interface)) {
        form.elements.interface.value = profile.interface;
    }
}

async function openProfileEditor(profileId) {
    if (Number(profileId) <= 0) return;

    resetAdminStatus('profile');
    showPage('profileNew', adminMenuLink('profile'), { updateHash: false });

    try {
        await loadAdminRegisters('profiles', true);
        const profile = findAdminProfileById(profileId);
        if (!profile) {
            throw new Error('Perfil não encontrado para edição.');
        }
        populateProfileForm(profile);
    } catch (error) {
        setAdminStatus(document.getElementById('profileStatus'), error.message || 'Erro ao carregar perfil.', 'error');
    }
}

async function cloneProfile(profileId) {
    const profile = findAdminProfileById(profileId);
    if (!profile) return;

    if (!window.confirm(`Clonar o perfil ${profile.name}? O novo perfil terá as mesmas permissões do perfil de origem.`)) {
        return;
    }

    const suggested = `${profile.name || 'perfil'} (cópia)`;
    const name = (window.prompt('Informe o nome do novo perfil (deve ser único):', suggested) || '').trim();
    if (!name) return;

    try {
        const formData = new FormData();
        formData.set('profile_action', 'clone');
        formData.set('id', String(Number(profileId) || 0));
        formData.set('name', name);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/profiles.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        applyAdminPayload(data);
        renderAdminTables();

        const newId = data.result?.profile?.id || 0;
        window.alert(`Perfil clonado (nome "${name}"${newId ? `, #${newId}` : ''}).`);
    } catch (error) {
        window.alert(error.message || 'Falha ao clonar perfil.');
    }
}

async function deleteProfile(profileId) {
    const profile = findAdminProfileById(profileId);
    if (!profile) return;

    if (!window.confirm(`Excluir o perfil ${profile.name}? Esta ação não pode ser desfeita. A exclusão será bloqueada se o perfil estiver em uso ou marcado como padrão.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.set('profile_action', 'delete');
        formData.set('id', String(Number(profileId) || 0));
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');

        const response = await fetch(PLUGIN_ROOT + '/ajax/profiles.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        applyAdminPayload(data);
        renderAdminTables();

        window.alert('Perfil excluído com sucesso.');
    } catch (error) {
        window.alert(error.message || 'Falha ao excluir perfil.');
    }
}

// ---- Wizard de cadastro de usuário ----
let userWizardStep = 1;
const USER_WIZARD_TOTAL = 3;

function userWizardPanels() {
    return Array.from(document.querySelectorAll('#userForm [data-step-panel]'));
}

function setUserWizardStep(step) {
    const target = Math.min(USER_WIZARD_TOTAL, Math.max(1, Number(step) || 1));
    userWizardStep = target;

    userWizardPanels().forEach(panel => {
        panel.classList.toggle('is-active', Number(panel.getAttribute('data-step-panel')) === target);
    });
    document.querySelectorAll('#userForm [data-step-indicator]').forEach(indicator => {
        const value = Number(indicator.getAttribute('data-step-indicator'));
        indicator.classList.toggle('is-active', value === target);
        indicator.classList.toggle('is-done', value < target);
    });

    const prev = document.getElementById('userWizardPrev');
    const next = document.getElementById('userWizardNext');
    const submit = document.getElementById('userSubmitButton');
    if (prev) prev.hidden = target === 1;
    if (next) next.hidden = target === USER_WIZARD_TOTAL;
    if (submit) submit.hidden = target !== USER_WIZARD_TOTAL;
}

function userWizardStepIsValid(step) {
    const panel = document.querySelector(`#userForm [data-step-panel="${step}"]`);
    if (!panel) return true;
    const controls = Array.from(panel.querySelectorAll('input, select, textarea'));
    for (const control of controls) {
        if (typeof control.reportValidity === 'function' && !control.reportValidity()) {
            return false;
        }
    }
    return true;
}

function initUserWizard() {
    const form = document.getElementById('userForm');
    if (!form) return;

    document.getElementById('userWizardNext')?.addEventListener('click', () => {
        if (!userWizardStepIsValid(userWizardStep)) return;
        setUserWizardStep(userWizardStep + 1);
    });
    document.getElementById('userWizardPrev')?.addEventListener('click', () => {
        setUserWizardStep(userWizardStep - 1);
    });

    setUserWizardStep(1);
}

// ---- Senha guiada ----
function userPasswordStrength(value) {
    const password = String(value || '');
    if (password === '') return { score: 0, label: '', variant: 'weak' };

    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score <= 2) return { score, label: 'Fraca', variant: 'weak' };
    if (score === 3) return { score, label: 'Média', variant: 'medium' };
    return { score, label: 'Forte', variant: 'strong' };
}

function updateUserPasswordMeter() {
    const input = document.getElementById('userPasswordInput');
    const meter = document.getElementById('userPasswordMeter');
    const fill = document.getElementById('userPasswordMeterFill');
    const labelEl = document.getElementById('userPasswordMeterLabel');
    if (!input || !meter || !fill || !labelEl) return;

    const value = input.value || '';
    if (value === '') {
        meter.hidden = true;
        return;
    }

    const strength = userPasswordStrength(value);
    const percent = Math.min(100, Math.max(15, (strength.score / 5) * 100));
    meter.hidden = false;
    fill.style.width = `${percent}%`;
    fill.className = `password-meter-fill ${strength.variant}`;
    labelEl.textContent = strength.label;
    labelEl.className = `password-meter-label ${strength.variant}`;
}

function generateTemporaryPassword() {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnpqrstuvwxyz';
    const digits = '23456789';
    const symbols = '!@#$%&*?';
    const all = upper + lower + digits + symbols;
    const pick = set => set[Math.floor(Math.random() * set.length)];

    let chars = [pick(upper), pick(lower), pick(digits), pick(symbols)];
    for (let i = chars.length; i < 14; i++) {
        chars.push(pick(all));
    }
    for (let i = chars.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join('');
}

function initUserPasswordTools() {
    const form = document.getElementById('userForm');
    const input = document.getElementById('userPasswordInput');
    if (!form || !input) return;

    input.addEventListener('input', updateUserPasswordMeter);

    document.getElementById('userPasswordToggle')?.addEventListener('click', () => {
        const toggle = document.getElementById('userPasswordToggle');
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        const icon = toggle?.querySelector('i');
        if (icon) icon.className = showing ? 'fas fa-eye' : 'fas fa-eye-slash';
    });

    document.getElementById('userPasswordGenerate')?.addEventListener('click', () => {
        input.value = generateTemporaryPassword();
        input.type = 'text';
        const icon = document.getElementById('userPasswordToggle')?.querySelector('i');
        if (icon) icon.className = 'fas fa-eye-slash';
        updateUserPasswordMeter();
    });

    // Guarda de senha forte na criação: executa antes do submit do bindAdminForm.
    form.addEventListener('submit', (event) => {
        if (DashState.adminUserEditorMode !== 'create') return;
        const value = input.value || '';
        if (userPasswordStrength(value).variant === 'weak') {
            event.preventDefault();
            event.stopImmediatePropagation();
            setUserWizardStep(2);
            input.focus();
            setAdminStatus(document.getElementById('userStatus'), 'Defina uma senha mais forte (8+ caracteres com maiúsculas, números e símbolos) ou use "Gerar".', 'error');
        }
    });
}

function entityNameById(id) {
    const entity = DashState.adminEntitiesGlobal.find(item => Number(item.id) === Number(id));
    if (entity) return entity.completename || entity.name;
    return Number(id) > 0 ? `Entidade #${id}` : 'Entidade raiz';
}

function normalizeAdminTextValue(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
}

function setAdminStatus(element, message, type) {
    if (!element) return;
    element.textContent = message;
    element.className = 'admin-status' + (type ? ' ' + type : '');
}

function adminSuccessMessage(data, pageId) {
    const status = data.result?.entity?.status || data.result?.group?.status || data.result?.category?.status || data.result?.user?.status || '';
    if (status === 'existing') {
        if (pageId === 'entities') return 'Entidade já existia no GLPI.';
        if (pageId === 'categories') return 'Categoria já existia no GLPI.';
        return 'Registro já existia no GLPI.';
    }
    if (pageId === 'users' && status === 'updated') {
        return 'Usuário atualizado no GLPI.';
    }
    if (pageId === 'users' && status === 'created') {
        return 'Usuário criado no GLPI.';
    }
    if (pageId === 'users' && status === 'cloned') {
        return 'Usuário clonado no GLPI (inativo para revisão).';
    }
    if (pageId === 'entities' && data.result?.group) {
        return 'Entidade processada e grupo sincronizado no GLPI.';
    }
    if (pageId === 'categories') {
        return 'Categoria processada no GLPI.';
    }
    return 'Cadastro salvo no GLPI.';
}

function bindCategoryImportForm() {
    const form = document.getElementById('categoryImportForm');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await previewCategoryImport();
    });

    document.getElementById('confirmCategoryImport')?.addEventListener('click', confirmCategoryImport);
}

async function previewCategoryImport() {
    const form = document.getElementById('categoryImportForm');
    const status = document.getElementById('categoryImportStatus');
    const submit = form?.querySelector('button[type="submit"]');
    if (!form) return;

    setAdminStatus(status, 'Validando CSV...', '');
    if (submit) submit.disabled = true;

    try {
        const formData = new FormData(form);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
        formData.set('action', 'preview_import');

        const response = await fetch(PLUGIN_ROOT + '/ajax/categories.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        DashState.categoryImportPreviewToken = data.preview?.token || '';
        renderCategoryImportPreview(data.preview || {});
        setAdminStatus(status, 'Prévia gerada. Confira o resumo antes de confirmar.', 'success');
    } catch (error) {
        clearCategoryImportPreview();
        setAdminStatus(status, error.message || 'Erro ao validar CSV.', 'error');
    } finally {
        if (submit) submit.disabled = false;
    }
}

async function confirmCategoryImport() {
    const status = document.getElementById('categoryImportStatus');
    const button = document.getElementById('confirmCategoryImport');
    if (!DashState.categoryImportPreviewToken) {
        setAdminStatus(status, 'Gere uma prévia antes de confirmar.', 'error');
        return;
    }

    setAdminStatus(status, 'Importando categorias no GLPI...', '');
    if (button) button.disabled = true;

    try {
        const formData = new FormData();
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
        formData.set('action', 'confirm_import');
        formData.set('preview_token', DashState.categoryImportPreviewToken);

        const response = await fetch(PLUGIN_ROOT + '/ajax/categories.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        populateAdminSelects();
        renderAdminTables();
        renderCategoryImportResult(data.result || {});
        setAdminStatus(status, categoryImportMessage(data.result), 'success');
        DashState.categoryImportPreviewToken = '';
    } catch (error) {
        setAdminStatus(status, error.message || 'Erro ao importar categorias.', 'error');
    } finally {
        if (button) button.disabled = false;
    }
}

function renderCategoryImportPreview(preview) {
    const wrapper = document.getElementById('categoryImportPreview');
    const fileLabel = document.getElementById('categoryImportPreviewFile');
    const confirm = document.getElementById('confirmCategoryImport');
    if (wrapper) wrapper.hidden = false;
    if (fileLabel) fileLabel.textContent = preview.filename ? `Arquivo: ${preview.filename}` : 'Prévia do CSV selecionado.';
    if (confirm) confirm.hidden = !preview.can_confirm;

    renderCategoryImportSummary(preview.summary || {});
    renderCategoryImportRows(preview.rows || []);
}

function renderCategoryImportResult(result) {
    const confirm = document.getElementById('confirmCategoryImport');
    if (confirm) confirm.hidden = true;
    renderCategoryImportSummary({
        created: result.summary?.created || 0,
        existing: result.summary?.existing || 0,
        ignored: 0,
        errors: result.summary?.errors || 0,
        fallback_root: 0,
        ready: Array.isArray(result.items) ? result.items.length : 0
    });
    renderCategoryImportRows((result.items || []).map(item => ({
        line: item.source_line || '-',
        status: item.status || 'processed',
        full_name: item.full_name || item.category?.name || '',
        entity_name: item.category?.entities_id === 0 ? 'Entidade raiz' : entityNameById(item.category?.entities_id || 0),
        reason: item.error || categoryImportItemReason(item)
    })));
}

function renderCategoryImportSummary(summary) {
    const container = document.getElementById('categoryImportSummary');
    if (!container) return;

    const items = [
        ['Prontas', summary.ready || 0, 'primary'],
        ['A criar', summary.created || 0, 'success'],
        ['Existentes', summary.existing || 0, 'warning'],
        ['Ignoradas', summary.ignored || 0, 'muted'],
        ['Erros', summary.errors || 0, 'danger'],
        ['Fallback raiz', summary.fallback_root || 0, 'warning']
    ];

    container.innerHTML = items.map(([label, value, type]) => `
        <div class="import-summary-item ${type}">
            <strong>${escHtml(value)}</strong>
            <span>${escHtml(label)}</span>
        </div>
    `).join('');
}

function renderCategoryImportRows(rows) {
    const body = document.getElementById('categoryImportPreviewBody');
    if (!body) return;

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="5" class="table-empty">Nenhuma linha para exibir</td></tr>';
        return;
    }

    body.innerHTML = rows.slice(0, 120).map(row => `
        <tr>
            <td><strong>${escHtml(row.line)}</strong></td>
            <td>${categoryImportStatusBadge(row.status)}</td>
            <td>${escHtml(row.full_name || '-')}</td>
            <td>${escHtml(row.entity_name || row.source_entity || 'Entidade raiz')}</td>
            <td>${escHtml(row.reason || '-')}</td>
        </tr>
    `).join('');
}

function categoryImportStatusBadge(status) {
    const map = {
        ready: ['primary', 'Pronta'],
        fallback: ['warning', 'Raiz'],
        warning: ['warning', 'Atenção'],
        ignored: ['muted', 'Ignorada'],
        created: ['success', 'Criada'],
        existing: ['warning', 'Existente'],
        updated: ['success', 'Atualizada'],
        error: ['danger', 'Erro'],
        processed: ['success', 'Processada']
    };
    const item = map[status] || ['primary', status || 'Status'];
    return `<span class="table-badge ${item[0]}">${escHtml(item[1])}</span>`;
}

function categoryImportItemReason(item) {
    const summary = item.summary || {};
    if ((summary.created || 0) > 0) {
        return `Criadas: ${summary.created}. Existentes: ${summary.existing || 0}.`;
    }
    return 'Categoria já existia no GLPI.';
}

function categoryImportMessage(result) {
    const summary = result?.summary || {};
    return `Importação concluída. Criadas: ${summary.created || 0}. Existentes: ${summary.existing || 0}. Erros: ${summary.errors || 0}.`;
}

function clearCategoryImportPreview() {
    DashState.categoryImportPreviewToken = '';
    const wrapper = document.getElementById('categoryImportPreview');
    const confirm = document.getElementById('confirmCategoryImport');
    const summary = document.getElementById('categoryImportSummary');
    const body = document.getElementById('categoryImportPreviewBody');
    const fileLabel = document.getElementById('categoryImportPreviewFile');

    if (wrapper) wrapper.hidden = true;
    if (confirm) {
        confirm.hidden = true;
        confirm.disabled = false;
    }
    if (summary) summary.innerHTML = '';
    if (body) body.innerHTML = '<tr><td colspan="5" class="table-empty">Gere uma prévia para continuar.</td></tr>';
    if (fileLabel) fileLabel.textContent = 'Nenhum arquivo selecionado.';
}

// ==================== IMPORTS CSV GENÉRICOS (PLAN-20260708-017) ====================
// Mesmo fluxo prévia → confirmação do import de Categorias, parametrizado por tipo.
// O import de Categorias mantém as funções próprias acima (sem regressão).

const ADMIN_IMPORT_CONFIG = {
    entityImport: {
        endpoint: 'entities.php',
        listPage: 'entities',
        formId: 'entityImportForm',
        statusId: 'entityImportStatus',
        previewId: 'entityImportPreview',
        fileLabelId: 'entityImportPreviewFile',
        confirmId: 'entityImportConfirm',
        summaryId: 'entityImportSummary',
        bodyId: 'entityImportPreviewBody',
        warningLabel: 'Atenção',
        busyMessage: 'Importando entidades no GLPI...',
        errorMessage: 'Erro ao importar entidades.'
    },
    groupImport: {
        endpoint: 'groups.php',
        listPage: 'groups',
        formId: 'groupImportForm',
        statusId: 'groupImportStatus',
        previewId: 'groupImportPreview',
        fileLabelId: 'groupImportPreviewFile',
        confirmId: 'groupImportConfirm',
        summaryId: 'groupImportSummary',
        bodyId: 'groupImportPreviewBody',
        warningLabel: 'Fallback raiz',
        busyMessage: 'Importando grupos no GLPI...',
        errorMessage: 'Erro ao importar grupos.'
    },
    userImport: {
        endpoint: 'users.php',
        listPage: 'users',
        formId: 'userImportForm',
        statusId: 'userImportStatus',
        previewId: 'userImportPreview',
        fileLabelId: 'userImportPreviewFile',
        confirmId: 'userImportConfirm',
        summaryId: 'userImportSummary',
        bodyId: 'userImportPreviewBody',
        warningLabel: 'Atenção',
        busyMessage: 'Importando usuários no GLPI...',
        errorMessage: 'Erro ao importar usuários.'
    },
    computerImport: {
        endpoint: 'computers.php',
        listPage: 'assets',
        formId: 'computerImportForm',
        statusId: 'computerImportStatus',
        previewId: 'computerImportPreview',
        fileLabelId: 'computerImportPreviewFile',
        confirmId: 'computerImportConfirm',
        summaryId: 'computerImportSummary',
        bodyId: 'computerImportPreviewBody',
        warningLabel: 'Atenção',
        busyMessage: 'Importando computadores no GLPI...',
        errorMessage: 'Erro ao importar computadores.'
    },
    monitorImport: {
        endpoint: 'monitors.php',
        listPage: 'assets',
        formId: 'monitorImportForm',
        statusId: 'monitorImportStatus',
        previewId: 'monitorImportPreview',
        fileLabelId: 'monitorImportPreviewFile',
        confirmId: 'monitorImportConfirm',
        summaryId: 'monitorImportSummary',
        bodyId: 'monitorImportPreviewBody',
        warningLabel: 'Atenção',
        busyMessage: 'Importando monitores no GLPI...',
        errorMessage: 'Erro ao importar monitores.'
    },
    ticketImport: {
        endpoint: 'tickets_import.php',
        listPage: 'tickets',
        formId: 'ticketImportForm',
        statusId: 'ticketImportStatus',
        previewId: 'ticketImportPreview',
        fileLabelId: 'ticketImportPreviewFile',
        confirmId: 'ticketImportConfirm',
        summaryId: 'ticketImportSummary',
        bodyId: 'ticketImportPreviewBody',
        warningLabel: 'Atenção',
        busyMessage: 'Importando tickets no GLPI...',
        errorMessage: 'Erro ao importar tickets.'
    },
    profileImport: {
        endpoint: 'profiles.php',
        listPage: 'profiles',
        formId: 'profileImportForm',
        statusId: 'profileImportStatus',
        previewId: 'profileImportPreview',
        fileLabelId: 'profileImportPreviewFile',
        confirmId: 'profileImportConfirm',
        summaryId: 'profileImportSummary',
        bodyId: 'profileImportPreviewBody',
        warningLabel: 'Atenção',
        busyMessage: 'Importando perfis no GLPI...',
        errorMessage: 'Erro ao importar perfis.'
    }
};

// Dropzone dos imports CSV — mesmo padrão visual/comportamento dos anexos de
// chamado (arrastar, clicar ou botão "Selecionar arquivo"; arquivo único .csv).
function bindAdminCsvUploads() {
    document.querySelectorAll('[data-csv-upload]').forEach(shell => {
        const input = shell.querySelector('input[type="file"]');
        const zone = shell.querySelector('[data-csv-dropzone]');
        const pickButton = shell.querySelector('[data-csv-pick]');
        if (!input || !zone) return;

        const openPicker = () => input.click();

        zone.addEventListener('click', (event) => {
            if (event.target.closest('[data-csv-pick]')) return;
            openPicker();
        });
        pickButton?.addEventListener('click', openPicker);
        zone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openPicker();
            }
        });
        zone.addEventListener('dragover', (event) => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
        zone.addEventListener('drop', (event) => {
            event.preventDefault();
            zone.classList.remove('is-dragover');
            const file = Array.from(event.dataTransfer?.files || [])
                .find(item => item.type === 'text/csv' || /\.csv$/i.test(item.name || ''));
            if (!file) return;
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change'));
        });
        input.addEventListener('change', () => renderAdminCsvUploadFile(shell, input));
        shell.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-csv-remove]');
            if (!removeButton) return;
            event.preventDefault();
            input.value = '';
            renderAdminCsvUploadFile(shell, input);
        });
    });
}

function renderAdminCsvUploadFile(shell, input) {
    const list = shell.querySelector('[data-csv-file-label]');
    if (!list) return;

    const file = input.files && input.files[0];
    if (!file) {
        list.innerHTML = '<div class="ticket-create-attachments-empty">Nenhum arquivo selecionado.</div>';
        return;
    }

    list.innerHTML = `
        <article class="ticket-create-attachment-card">
            <button class="ticket-create-attachment-remove" type="button" data-csv-remove aria-label="Remover arquivo">
                <i class="fas fa-times"></i>
            </button>
            <div class="ticket-create-attachment-preview"><span class="ticket-create-attachment-ext">CSV</span></div>
            <div class="ticket-create-attachment-body">
                <div class="ticket-create-attachment-name" title="${escHtml(file.name)}">${escHtml(file.name)}</div>
                <div class="ticket-create-attachment-meta">${escHtml(adminCsvFileSize(file.size || 0))}</div>
            </div>
        </article>
    `;
}

function adminCsvFileSize(bytes) {
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${bytes} B`;
}

function adminImportTokens() {
    DashState.adminImportTokens = DashState.adminImportTokens || {};
    return DashState.adminImportTokens;
}

function bindAdminImportForms() {
    Object.keys(ADMIN_IMPORT_CONFIG).forEach(type => {
        const config = ADMIN_IMPORT_CONFIG[type];
        const form = document.getElementById(config.formId);
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            await previewAdminImport(type);
        });

        document.getElementById(config.confirmId)?.addEventListener('click', () => confirmAdminImport(type));
    });
}

async function previewAdminImport(type) {
    const config = ADMIN_IMPORT_CONFIG[type];
    const form = document.getElementById(config.formId);
    const status = document.getElementById(config.statusId);
    const submit = form?.querySelector('button[type="submit"]');
    if (!form) return;

    setAdminStatus(status, 'Validando CSV...', '');
    if (submit) submit.disabled = true;

    try {
        const formData = new FormData(form);
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
        formData.set('action', 'preview_import');

        const response = await fetch(PLUGIN_ROOT + '/ajax/' + config.endpoint, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);

        adminImportTokens()[type] = data.preview?.token || '';
        renderAdminImportPreview(type, data.preview || {});
        setAdminStatus(status, 'Prévia gerada. Confira o resumo antes de confirmar.', 'success');
    } catch (error) {
        clearAdminImportPreview(type);
        setAdminStatus(status, error.message || 'Erro ao validar CSV.', 'error');
    } finally {
        if (submit) submit.disabled = false;
    }
}

async function confirmAdminImport(type) {
    const config = ADMIN_IMPORT_CONFIG[type];
    const status = document.getElementById(config.statusId);
    const button = document.getElementById(config.confirmId);
    const token = adminImportTokens()[type] || '';
    if (!token) {
        setAdminStatus(status, 'Gere uma prévia antes de confirmar.', 'error');
        return;
    }

    setAdminStatus(status, config.busyMessage, '');
    if (button) button.disabled = true;

    try {
        const formData = new FormData();
        formData.set('csrf_token', typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '');
        formData.set('action', 'confirm_import');
        formData.set('preview_token', token);

        const response = await fetch(PLUGIN_ROOT + '/ajax/' + config.endpoint, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });
        const data = await adminReadJson(response);
        applyAdminPayload(data);
        populateAdminSelects();
        renderAdminTables();
        renderAdminImportResult(type, data.result || {});
        setAdminStatus(status, adminImportMessage(data.result), 'success');
        adminImportTokens()[type] = '';
    } catch (error) {
        setAdminStatus(status, error.message || config.errorMessage, 'error');
    } finally {
        if (button) button.disabled = false;
    }
}

function renderAdminImportPreview(type, preview) {
    const config = ADMIN_IMPORT_CONFIG[type];
    const wrapper = document.getElementById(config.previewId);
    const fileLabel = document.getElementById(config.fileLabelId);
    const confirm = document.getElementById(config.confirmId);
    if (wrapper) wrapper.hidden = false;
    if (fileLabel) fileLabel.textContent = preview.filename ? `Arquivo: ${preview.filename}` : 'Prévia do CSV selecionado.';
    if (confirm) confirm.hidden = !preview.can_confirm;

    renderAdminImportSummary(type, preview.summary || {});
    renderAdminImportRows(type, preview.rows || []);
}

function renderAdminImportResult(type, result) {
    const config = ADMIN_IMPORT_CONFIG[type];
    const confirm = document.getElementById(config.confirmId);
    if (confirm) confirm.hidden = true;

    renderAdminImportSummary(type, {
        created: result.summary?.created || 0,
        existing: result.summary?.existing || 0,
        ignored: 0,
        errors: result.summary?.errors || 0,
        fallback_root: 0,
        ready: Array.isArray(result.items) ? result.items.length : 0
    });
    renderAdminImportRows(type, (result.items || []).map(item => ({
        line: item.source_line || '-',
        status: item.status || 'processed',
        full_name: item.full_name || item.name || item.login || item.title || '',
        entity_name: adminImportResultDestination(item),
        reason: item.error || adminImportItemReason(item)
    })));
}

function adminImportResultDestination(item) {
    if (typeof item.interface === 'string' && item.interface !== '') {
        return item.interface === 'central' ? 'Central' : 'Helpdesk';
    }
    if (item.entities_id !== undefined && item.entities_id !== null) {
        return Number(item.entities_id) === 0 ? 'Entidade raiz' : entityNameById(item.entities_id);
    }
    return '-';
}

function adminImportItemReason(item) {
    const summary = item.summary || null;
    if (summary) {
        if ((summary.created || 0) > 0) {
            return `Criados: ${summary.created}. Existentes: ${summary.existing || 0}.`;
        }
        return 'Registro já existia no GLPI.';
    }
    if (item.status === 'updated') {
        return 'Registro existente atualizado no GLPI.';
    }
    if (item.status === 'existing') {
        return 'Registro já existia no GLPI.';
    }
    return 'Registro criado no GLPI.';
}

function renderAdminImportSummary(type, summary) {
    const config = ADMIN_IMPORT_CONFIG[type];
    const container = document.getElementById(config.summaryId);
    if (!container) return;

    const items = [
        ['Prontos', summary.ready || 0, 'primary'],
        ['A criar', summary.created || 0, 'success'],
        ['Existentes', summary.existing || 0, 'warning'],
        ['Ignorados', summary.ignored || 0, 'muted'],
        ['Erros', summary.errors || 0, 'danger'],
        [config.warningLabel, summary.fallback_root || 0, 'warning']
    ];

    container.innerHTML = items.map(([label, value, badge]) => `
        <div class="import-summary-item ${badge}">
            <strong>${escHtml(value)}</strong>
            <span>${escHtml(label)}</span>
        </div>
    `).join('');
}

function renderAdminImportRows(type, rows) {
    const config = ADMIN_IMPORT_CONFIG[type];
    const body = document.getElementById(config.bodyId);
    if (!body) return;

    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="5" class="table-empty">Nenhuma linha para exibir</td></tr>';
        return;
    }

    body.innerHTML = rows.slice(0, 120).map(row => `
        <tr>
            <td><strong>${escHtml(row.line)}</strong></td>
            <td>${categoryImportStatusBadge(row.status)}</td>
            <td>${escHtml(row.full_name || '-')}</td>
            <td>${escHtml(row.entity_name || row.source_entity || '-')}</td>
            <td>${escHtml(row.reason || '-')}</td>
        </tr>
    `).join('');
}

function adminImportMessage(result) {
    const summary = result?.summary || {};
    return `Importação concluída. Criados: ${summary.created || 0}. Existentes: ${summary.existing || 0}. Erros: ${summary.errors || 0}.`;
}

function clearAdminImportPreview(type) {
    const config = ADMIN_IMPORT_CONFIG[type];
    if (!config) return;

    adminImportTokens()[type] = '';
    const wrapper = document.getElementById(config.previewId);
    const confirm = document.getElementById(config.confirmId);
    const summary = document.getElementById(config.summaryId);
    const body = document.getElementById(config.bodyId);
    const fileLabel = document.getElementById(config.fileLabelId);

    if (wrapper) wrapper.hidden = true;
    if (confirm) {
        confirm.hidden = true;
        confirm.disabled = false;
    }
    if (summary) summary.innerHTML = '';
    if (body) body.innerHTML = '<tr><td colspan="5" class="table-empty">Gere uma prévia para continuar.</td></tr>';
    if (fileLabel) fileLabel.textContent = 'Nenhum arquivo selecionado.';
}

