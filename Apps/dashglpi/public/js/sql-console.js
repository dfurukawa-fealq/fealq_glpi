const sqlConsoleState = {
    dataset: (typeof DASHGLPI_SQL_BOOTSTRAP === 'object' && DASHGLPI_SQL_BOOTSTRAP) ? DASHGLPI_SQL_BOOTSTRAP : {},
    lastResult: null,
    writePreview: null,
    pendingConfirmAction: null,
};

function escHtml(value) {
    const element = document.createElement('div');
    element.textContent = value ?? '';
    return element.innerHTML;
}

function initTheme() {
    const savedTheme = localStorage.getItem('glpi-theme');
    if (savedTheme === 'light') {
        document.body.classList.add('light-mode');
    }
    updateThemeIcon();
}

function toggleTheme() {
    document.body.classList.toggle('light-mode');
    localStorage.setItem('glpi-theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
    updateThemeIcon();
}

function updateThemeIcon() {
    document.querySelectorAll('.theme-toggle i').forEach((icon) => {
        icon.className = document.body.classList.contains('light-mode') ? 'fas fa-sun' : 'fas fa-moon';
    });
}

function isMobileViewport() {
    return window.matchMedia('(max-width: 768px)').matches;
}

function bindResponsiveNavigation() {
    if (document.body.dataset.responsiveNavBound === '1') {
        return;
    }

    document.body.dataset.responsiveNavBound = '1';
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileMenu();
        }
    });

    document.addEventListener('click', (event) => {
        if (!isMobileViewport()) {
            return;
        }

        const sidebar = document.getElementById('sidebar');
        if (!sidebar || sidebar.classList.contains('collapsed')) {
            return;
        }

        if (event.target.closest('.menu-link, .menu-sublink')) {
            closeMobileMenu();
        }
    });
}

function syncResponsiveNavigation() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    if (!sidebar || !mainContent) {
        return;
    }

    if (isMobileViewport()) {
        if (sidebar.dataset.mobilePrepared !== '1') {
            sidebar.dataset.mobilePrepared = '1';
            sidebar.dataset.preMobileCollapsed = sidebar.classList.contains('collapsed') ? '1' : '0';
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            document.body.classList.add('menu-closed');
            document.body.classList.remove('mobile-menu-open');
        }
        return;
    }

    if (sidebar.dataset.mobilePrepared === '1') {
        const shouldStayCollapsed = sidebar.dataset.preMobileCollapsed === '1';
        sidebar.classList.toggle('collapsed', shouldStayCollapsed);
        mainContent.classList.toggle('expanded', shouldStayCollapsed);
        document.body.classList.toggle('menu-closed', shouldStayCollapsed);
        document.body.classList.remove('mobile-menu-open');
        delete sidebar.dataset.mobilePrepared;
        delete sidebar.dataset.preMobileCollapsed;
        return;
    }

    const collapsed = sidebar.classList.contains('collapsed');
    mainContent.classList.toggle('expanded', collapsed);
    document.body.classList.toggle('menu-closed', collapsed);
    document.body.classList.remove('mobile-menu-open');
}

function initResponsiveNavigation() {
    bindResponsiveNavigation();
    syncResponsiveNavigation();
    window.addEventListener('resize', syncResponsiveNavigation);
}

function toggleMenu(forceOpen = null) {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    if (!sidebar || !mainContent) {
        return;
    }

    const shouldOpen = forceOpen === null
        ? sidebar.classList.contains('collapsed')
        : Boolean(forceOpen);

    if (isMobileViewport()) {
        sidebar.classList.toggle('collapsed', !shouldOpen);
        mainContent.classList.add('expanded');
        document.body.classList.toggle('menu-closed', !shouldOpen);
        document.body.classList.toggle('mobile-menu-open', shouldOpen);
        return;
    }

    sidebar.classList.toggle('collapsed', !shouldOpen);
    mainContent.classList.toggle('expanded', !shouldOpen);
    document.body.classList.toggle('menu-closed', !shouldOpen);
    document.body.classList.remove('mobile-menu-open');
}

function closeMobileMenu() {
    if (!isMobileViewport()) {
        return;
    }

    const sidebar = document.getElementById('sidebar');
    if (!sidebar || sidebar.classList.contains('collapsed')) {
        document.body.classList.remove('mobile-menu-open');
        document.body.classList.add('menu-closed');
        return;
    }

    toggleMenu(false);
}

function sqlConsoleDataset() {
    return sqlConsoleState.dataset || {};
}

function sqlConsoleLimitOptions() {
    const options = sqlConsoleDataset().limit_options;
    return Array.isArray(options) && options.length ? options : [25, 50, 100, 200, 500];
}

function sqlConsoleDefaultLimit() {
    return Number(sqlConsoleDataset().default_limit || 100);
}

function sqlConsoleWriteEnabled() {
    return Boolean(sqlConsoleDataset().write_mode_enabled);
}

function sqlConsoleMaintenance() {
    const maintenance = sqlConsoleDataset().maintenance;
    return (maintenance && typeof maintenance === 'object') ? maintenance : {};
}

function sqlConsoleGlpiCacheClearAvailable() {
    return Boolean(sqlConsoleMaintenance().clear_glpi_cache_available);
}

function sqlConsoleWriteStatementTypes() {
    const types = sqlConsoleDataset().write_statement_types;
    if (!Array.isArray(types) || !types.length) {
        return ['UPDATE', 'INSERT', 'DELETE'];
    }

    return types
        .map((type) => String(type || '').trim().toUpperCase())
        .filter(Boolean);
}

function sqlConsoleEditor() {
    const editor = document.getElementById('sqlEditor');
    return editor instanceof HTMLTextAreaElement ? editor : null;
}

function sqlConsoleCurrentSql() {
    return sqlConsoleEditor()?.value.trim() || '';
}

function sqlConsoleFirstToken(sql) {
    if (typeof sql !== 'string') {
        return '';
    }

    let clean = sql.replace(/^\uFEFF/, '');
    let previous = null;

    while (clean !== previous) {
        previous = clean;
        clean = clean.replace(/^\s+/, '');
        clean = clean.replace(/^--[^\n]*(?:\n|$)/, '');
        clean = clean.replace(/^#[^\n]*(?:\n|$)/, '');
        clean = clean.replace(/^\/\*[\s\S]*?\*\//, '');
    }

    const match = clean.match(/^([a-z_][a-z0-9_]*)/i);
    return match ? match[1].toUpperCase() : '';
}

function sqlConsoleLikelyWriteStatement(sql) {
    const firstToken = sqlConsoleFirstToken(sql);
    return sqlConsoleWriteStatementTypes().includes(firstToken) ? firstToken : '';
}

function setSqlStatus(message, type = '') {
    const status = document.getElementById('sqlStatus');
    if (!status) {
        return;
    }

    status.textContent = message || '';
    status.className = 'admin-status sql-console-status' + (type ? ` ${type}` : '');
}

function setButtonBusy(button, label, iconClass = 'fas fa-spinner fa-spin') {
    if (!button) {
        return () => {};
    }

    const icon = button.querySelector('i');
    const span = button.querySelector('span');
    const snapshot = {
        disabled: button.hasAttribute('disabled'),
        iconClass: icon ? icon.className : '',
        label: span ? span.textContent : '',
    };

    button.setAttribute('disabled', 'disabled');
    if (icon) {
        icon.className = iconClass;
    }
    if (span) {
        span.textContent = label;
    }

    return () => {
        if (!snapshot.disabled) {
            button.removeAttribute('disabled');
        }
        if (icon) {
            icon.className = snapshot.iconClass;
        }
        if (span) {
            span.textContent = snapshot.label;
        }
        updateRunButtonState();
        updateWriteConfirmState();
    };
}

function populateLimitOptions() {
    const select = document.getElementById('sqlRowLimit');
    if (!select) {
        return;
    }

    const preferredValue = String(select.value || sqlConsoleDefaultLimit());
    const currentValue = sqlConsoleLimitOptions().some((value) => String(value) === preferredValue)
        ? preferredValue
        : String(sqlConsoleDefaultLimit());

    select.innerHTML = sqlConsoleLimitOptions().map((value) => `
        <option value="${value}" ${String(value) === currentValue ? 'selected' : ''}>${value} linhas</option>
    `).join('');
}

function renderRules() {
    const list = document.getElementById('sqlRuleList');
    if (!list) {
        return;
    }

    const rules = Array.isArray(sqlConsoleDataset().rules) ? sqlConsoleDataset().rules : [];
    list.innerHTML = rules.map((rule) => `<li>${escHtml(rule)}</li>`).join('');
}

function renderPresets() {
    const list = document.getElementById('sqlPresetList');
    if (!list) {
        return;
    }

    const presets = Array.isArray(sqlConsoleDataset().presets) ? sqlConsoleDataset().presets : [];
    if (!presets.length) {
        list.innerHTML = '<div class="sql-console-empty">Nenhum preset disponivel.</div>';
        return;
    }

    list.innerHTML = presets.map((preset, index) => `
        <button class="sql-console-list-item" type="button" data-sql-preset-index="${index}">
            <strong>${escHtml(preset.label || 'Preset')}</strong>
            <span>${escHtml(preset.description || '')}</span>
        </button>
    `).join('');
}

function renderHistory() {
    const list = document.getElementById('sqlHistoryList');
    if (!list) {
        return;
    }

    const history = Array.isArray(sqlConsoleDataset().history) ? sqlConsoleDataset().history : [];
    if (!history.length) {
        list.innerHTML = '<div class="sql-console-empty">Nenhuma execucao auditada ainda.</div>';
        return;
    }

    list.innerHTML = history.map((item, index) => {
        const status = String(item.status || 'success');
        const badgeLabel = status === 'blocked' ? 'Bloqueada' : (status === 'error' ? 'Erro' : 'OK');
        const isWrite = String(item.execution_mode || '') === 'write';
        const rowInfo = isWrite
            ? `${Number(item.affected_rows || 0)} linha(s) afetada(s)`
            : `${Number(item.rows_returned || 0)} linha(s)`;
        const meta = [
            String(item.statement_type || '').trim(),
            rowInfo,
            `${Number(item.duration_ms || 0)} ms`,
            item.executed_at || '',
        ].filter(Boolean).join(' | ');

        return `
            <button class="sql-console-list-item sql-console-history-item" type="button" data-sql-history-index="${index}">
                <div class="sql-console-history-top">
                    <strong>${escHtml(item.statement_preview || 'Consulta')}</strong>
                    <span class="table-badge sql-console-history-badge ${escHtml(status)}">${escHtml(badgeLabel)}</span>
                </div>
                <span>${escHtml(meta)}</span>
            </button>
        `;
    }).join('');
}

function setResultSummary(message) {
    const summary = document.getElementById('sqlResultSummary');
    if (summary) {
        summary.textContent = message;
    }
}

function resultActionButtons() {
    return {
        copy: document.getElementById('sqlCopyButton'),
        csv: document.getElementById('sqlCsvButton'),
    };
}

function setResultActionsEnabled(enabled) {
    const buttons = resultActionButtons();
    [buttons.copy, buttons.csv].forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        if (enabled) {
            button.removeAttribute('disabled');
            return;
        }

        button.setAttribute('disabled', 'disabled');
    });
}

function setResultMeta(meta = null) {
    const container = document.getElementById('sqlMeta');
    if (!container) {
        return;
    }

    if (!meta) {
        container.innerHTML = '';
        return;
    }

    const isWrite = String(meta.execution_mode || '') === 'write';
    const items = [
        `Tipo: ${meta.statement_type || '-'}`,
        isWrite
            ? `Afetadas: ${Number(meta.affected_rows || 0)}`
            : `Linhas: ${Number(meta.rows_returned || 0)}`,
        !isWrite ? `Limite: ${Number(meta.limit || 0)}` : 'Escrita controlada',
        `Duracao: ${Number(meta.duration_ms || 0)} ms`,
        isWrite
            ? 'Alteracao confirmada'
            : (meta.rows_limited ? 'Resultado truncado no limite escolhido' : 'Resultado completo'),
        meta.executed_at ? `Executado em ${meta.executed_at}` : '',
    ].filter(Boolean);

    container.innerHTML = items.map((item) => `<span class="sql-console-meta-chip">${escHtml(item)}</span>`).join('');
}

function renderResultPlaceholder(message) {
    sqlConsoleState.lastResult = null;
    const table = document.getElementById('sqlResultsTable');
    const head = document.getElementById('sqlResultsHead');
    const body = document.getElementById('sqlResultsBody');
    if (table) {
        table.style.setProperty('--sql-console-column-count', '1');
    }
    if (head) {
        head.innerHTML = '';
    }
    if (body) {
        body.innerHTML = `<tr><td class="table-empty">${escHtml(message)}</td></tr>`;
    }
    setResultSummary(message);
    setResultMeta(null);
    setResultActionsEnabled(false);
}

function renderMutationResult(result) {
    const table = document.getElementById('sqlResultsTable');
    const head = document.getElementById('sqlResultsHead');
    const body = document.getElementById('sqlResultsBody');
    if (!table || !head || !body) {
        return;
    }

    table.style.setProperty('--sql-console-column-count', '4');

    const mutation = result.mutation || {};
    const meta = result.meta || {};
    const targetTables = Array.isArray(mutation.target_tables) && mutation.target_tables.length
        ? mutation.target_tables.join(', ')
        : 'Nao identificado';

    head.innerHTML = `
        <tr>
            <th>Operacao</th>
            <th>Tabela(s)</th>
            <th>Linhas afetadas</th>
            <th>Executado em</th>
        </tr>
    `;
    body.innerHTML = `
        <tr>
            <td>${escHtml(mutation.statement_type || result.query?.statement_type || '-')}</td>
            <td class="sql-console-mutation-cell">
                <strong>${escHtml(targetTables)}</strong>
                <span>${escHtml(result.query?.preview || '')}</span>
            </td>
            <td>${escHtml(String(Number(mutation.affected_rows || meta.affected_rows || 0)))}</td>
            <td>${escHtml(meta.executed_at || '-')}</td>
        </tr>
    `;

    setResultSummary([
        `${Number(mutation.affected_rows || meta.affected_rows || 0)} linha(s) afetada(s)`,
        `${Number(meta.duration_ms || 0)} ms`,
        'alteracao aplicada',
    ].join(' | '));
    setResultMeta({
        statement_type: result.query?.statement_type || mutation.statement_type || '',
        ...meta,
    });

    setResultActionsEnabled(true);
}

function renderReadResult(result) {
    const table = document.getElementById('sqlResultsTable');
    const head = document.getElementById('sqlResultsHead');
    const body = document.getElementById('sqlResultsBody');
    if (!table || !head || !body) {
        return;
    }

    const columns = Array.isArray(result.columns) ? result.columns : [];
    const rows = Array.isArray(result.rows) ? result.rows : [];
    const meta = result.meta || {};

    if (!columns.length) {
        renderResultPlaceholder('A consulta nao retornou colunas.');
        return;
    }

    table.style.setProperty('--sql-console-column-count', String(Math.max(columns.length, 1)));

    head.innerHTML = `<tr>${columns.map((column) => `
        <th title="${escHtml(String(column))}">${escHtml(column)}</th>
    `).join('')}</tr>`;

    if (!rows.length) {
        body.innerHTML = `<tr><td colspan="${columns.length}" class="table-empty">Consulta executada sem linhas de retorno.</td></tr>`;
    } else {
        const renderCell = (value) => {
            if (value === null) {
                return `
                    <td title="NULL">
                        <span class="sql-console-cell-text sql-console-null">NULL</span>
                    </td>
                `;
            }

            const rawValue = String(value);
            return `
                <td title="${escHtml(rawValue)}">
                    <span class="sql-console-cell-text">${escHtml(rawValue)}</span>
                </td>
            `;
        };

        body.innerHTML = rows.map((row) => `
            <tr>
                ${row.map((value) => renderCell(value)).join('')}
            </tr>
        `).join('');
    }

    setResultSummary([
        `${Number(meta.rows_returned || 0)} linha(s)`,
        `${Number(meta.duration_ms || 0)} ms`,
        meta.rows_limited ? 'resultado truncado' : 'resultado completo',
    ].join(' | '));
    setResultMeta({
        statement_type: result.query?.statement_type || '',
        ...meta,
    });

    setResultActionsEnabled(true);
}

function renderSqlResult(result) {
    sqlConsoleState.lastResult = result;

    if (result && result.mutation) {
        renderMutationResult(result);
        return;
    }

    renderReadResult(result);
}

async function refreshSqlDataset() {
    const response = await fetch('/ajax/sql-console.php', {
        method: 'GET',
        headers: { Accept: 'application/json' },
    });
    const data = await response.json();
    if (!response.ok || data.ok === false) {
        throw new Error(data.error || 'Falha ao recarregar o console SQL.');
    }

    sqlConsoleState.dataset = data;
    populateLimitOptions();
    renderPresets();
    renderHistory();
    renderRules();
    updateWriteModeIndicators();
    updateMaintenanceActions();
    renderDiagnostics();
    updateRunButtonState();

    if (!sqlConsoleWriteEnabled() && sqlConsoleState.writePreview) {
        invalidateWritePreview({ silent: true, keepStatus: true });
    }
}

function sqlConsolePreviewMatchesEditor() {
    return Boolean(
        sqlConsoleState.writePreview
        && typeof sqlConsoleState.writePreview.sql === 'string'
        && sqlConsoleState.writePreview.sql === sqlConsoleCurrentSql()
    );
}

function clearWritePreviewInputs() {
    const reviewed = document.getElementById('sqlWriteReviewed');
    const input = document.getElementById('sqlWriteConfirmationInput');
    if (reviewed instanceof HTMLInputElement) {
        reviewed.checked = false;
    }
    if (input instanceof HTMLInputElement) {
        input.value = '';
        input.classList.remove('is-valid', 'is-invalid');
    }
}

function updateWriteModeIndicators() {
    const badge = document.getElementById('sqlWriteModeBadge');
    const banner = document.getElementById('sqlWriteModeBanner');
    if (!badge || !banner) {
        return;
    }

    const enabled = sqlConsoleWriteEnabled();
    const flagName = String(sqlConsoleDataset().write_flag_env || 'DASHGLPI_SQL_WRITE_ENABLED');

    badge.className = `sql-console-mode-badge ${enabled ? 'write' : 'read'}`;
    badge.textContent = enabled ? 'Escrita controlada ativa' : 'Somente leitura';

    banner.className = `sql-console-write-banner is-visible ${enabled ? 'write-enabled' : 'write-disabled'}`;
    banner.innerHTML = enabled
        ? `
            <i class="fas fa-shield-alt"></i>
            <span>UPDATE, INSERT e DELETE estao liberados somente com previa obrigatoria, frase de confirmacao e auditoria completa.</span>
        `
        : `
            <i class="fas fa-lock"></i>
            <span>Escrita bloqueada. Habilite a flag <strong>${escHtml(flagName)}</strong> para liberar UPDATE, INSERT e DELETE.</span>
        `;
}

function updateMaintenanceActions() {
    const button = document.getElementById('sqlClearGlpiCacheButton');
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const available = sqlConsoleGlpiCacheClearAvailable();
    const command = String(sqlConsoleMaintenance().clear_glpi_cache_command || '');

    if (available) {
        button.removeAttribute('disabled');
        button.title = command !== ''
            ? `Equivale a ${command}`
            : 'Limpa o cache do GLPI no container principal.';
        return;
    }

    button.setAttribute('disabled', 'disabled');
    button.title = 'Bridge do GLPI indisponivel para limpar o cache.';
}

function updateRunButtonState() {
    const button = document.getElementById('sqlRunButton');
    if (!button) {
        return;
    }

    const icon = button.querySelector('i');
    const label = button.querySelector('span');
    const sql = sqlConsoleCurrentSql();
    const statementType = sqlConsoleLikelyWriteStatement(sql);
    const isWrite = statementType !== '';
    const writeEnabled = sqlConsoleWriteEnabled();

    button.classList.toggle('danger', isWrite && !writeEnabled);
    button.classList.toggle('primary', !isWrite || writeEnabled);

    if (!icon || !label) {
        return;
    }

    if (isWrite && !writeEnabled) {
        icon.className = 'fas fa-lock';
        label.textContent = 'Escrita bloqueada';
        button.title = 'Modo de escrita desabilitado neste ambiente.';
        return;
    }

    if (isWrite) {
        icon.className = 'fas fa-shield-alt';
        label.textContent = sqlConsolePreviewMatchesEditor() ? 'Atualizar previa' : 'Gerar previa';
        button.title = `${statementType} exige previa e confirmacao digitada.`;
        return;
    }

    icon.className = 'fas fa-play';
    label.textContent = 'Executar';
    button.removeAttribute('title');
}

function updateWriteConfirmState() {
    const preview = sqlConsoleState.writePreview;
    const confirmButton = document.getElementById('sqlWriteConfirmButton');
    const reviewed = document.getElementById('sqlWriteReviewed');
    const input = document.getElementById('sqlWriteConfirmationInput');

    if (!(confirmButton instanceof HTMLButtonElement) || !(reviewed instanceof HTMLInputElement) || !(input instanceof HTMLInputElement)) {
        return;
    }

    const expected = String(preview?.confirmation_phrase || '').trim().toUpperCase();
    const actual = input.value.trim().toUpperCase();
    const matches = expected !== '' && actual === expected;
    const canConfirm = Boolean(preview && sqlConsolePreviewMatchesEditor() && reviewed.checked && matches);

    input.classList.toggle('is-valid', actual !== '' && matches);
    input.classList.toggle('is-invalid', actual !== '' && !matches);

    if (canConfirm) {
        confirmButton.removeAttribute('disabled');
    } else {
        confirmButton.setAttribute('disabled', 'disabled');
    }
}

function renderWritePreview(preview) {
    const panel = document.getElementById('sqlWritePreviewPanel');
    const title = document.getElementById('sqlWritePreviewTitle');
    const summary = document.getElementById('sqlWritePreviewSummary');
    const meta = document.getElementById('sqlWritePreviewMeta');
    const statement = document.getElementById('sqlWritePreviewStatement');
    const warnings = document.getElementById('sqlWritePreviewWarnings');
    const phrase = document.getElementById('sqlWriteConfirmationPhrase');

    if (!panel || !title || !summary || !meta || !statement || !warnings || !phrase) {
        return;
    }

    const targetTables = Array.isArray(preview.target_tables) && preview.target_tables.length
        ? preview.target_tables
        : [];
    const tableLabel = targetTables.length ? targetTables.join(', ') : 'tabela alvo nao identificada';
    const ttlMinutes = Math.max(1, Math.ceil(Number(preview.expires_in_seconds || 0) / 60));
    const warningItems = Array.isArray(preview.warnings) && preview.warnings.length
        ? preview.warnings
        : ['Revise a query antes de confirmar a escrita.'];

    title.textContent = `Confirmar ${String(preview.statement_type || 'alteracao')}`;
    summary.textContent = `Tabela(s) alvo: ${tableLabel}. A alteracao so sera executada apos a confirmacao digitada.`;
    meta.innerHTML = [
        `${ttlMinutes} min para expirar`,
        'Fluxo auditado',
        'Banco real do GLPI',
    ].map((item) => `<span class="sql-console-meta-chip">${escHtml(item)}</span>`).join('');
    statement.textContent = String(preview.statement_preview || sqlConsoleCurrentSql());
    warnings.innerHTML = warningItems.map((item) => `
        <li class="sql-console-write-warning">
            <i class="fas fa-triangle-exclamation"></i>
            <span>${escHtml(item)}</span>
        </li>
    `).join('');
    phrase.textContent = String(preview.confirmation_phrase || '');

    clearWritePreviewInputs();
    panel.hidden = false;
    updateWriteConfirmState();
    updateRunButtonState();

    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function invalidateWritePreview(options = {}) {
    const { silent = false, keepStatus = false } = options;
    const panel = document.getElementById('sqlWritePreviewPanel');
    const title = document.getElementById('sqlWritePreviewTitle');
    const summary = document.getElementById('sqlWritePreviewSummary');
    const meta = document.getElementById('sqlWritePreviewMeta');
    const statement = document.getElementById('sqlWritePreviewStatement');
    const warnings = document.getElementById('sqlWritePreviewWarnings');
    const phrase = document.getElementById('sqlWriteConfirmationPhrase');

    sqlConsoleState.writePreview = null;

    if (panel) {
        panel.hidden = true;
    }
    if (title) {
        title.textContent = 'Confirmar alteracao';
    }
    if (summary) {
        summary.textContent = '';
    }
    if (meta) {
        meta.innerHTML = '';
    }
    if (statement) {
        statement.textContent = '';
    }
    if (warnings) {
        warnings.innerHTML = '';
    }
    if (phrase) {
        phrase.textContent = '';
    }

    clearWritePreviewInputs();
    updateWriteConfirmState();
    updateRunButtonState();

    if (!silent && !keepStatus) {
        setSqlStatus('Previa de escrita cancelada.', '');
    }
}

async function postSqlConsole(action, payload = {}) {
    const response = await fetch('/ajax/sql-console.php', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action,
            csrf_token: typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '',
            ...payload,
        }),
    });

    const data = await response.json();
    if (!response.ok || data.ok === false) {
        throw new Error(data.error || 'Falha ao executar a consulta.');
    }

    return data;
}

async function requestWritePreview() {
    const sql = sqlConsoleCurrentSql();
    const runButton = document.getElementById('sqlRunButton');

    if (sql === '') {
        setSqlStatus('Informe uma consulta SQL para executar.', 'error');
        return;
    }

    if (!sqlConsoleWriteEnabled()) {
        setSqlStatus('Modo de escrita desabilitado neste ambiente.', 'error');
        return;
    }

    const restoreButton = setButtonBusy(runButton, 'Gerando previa...', 'fas fa-shield-alt');
    setSqlStatus('Gerando previa de escrita...', '');

    try {
        const data = await postSqlConsole('preview_write', { sql });
        sqlConsoleState.writePreview = {
            ...data.preview,
            sql,
        };
        renderWritePreview(sqlConsoleState.writePreview);
        setSqlStatus('Previa gerada. Revise a query e confirme a frase obrigatoria.', 'success');
    } catch (error) {
        setSqlStatus(error.message || 'Falha ao gerar a previa de escrita.', 'error');
        throw error;
    } finally {
        restoreButton();
    }
}

async function executeReadQuery() {
    const editor = sqlConsoleEditor();
    const limit = document.getElementById('sqlRowLimit');
    const runButton = document.getElementById('sqlRunButton');
    if (!editor || !(limit instanceof HTMLSelectElement)) {
        return;
    }

    const sql = editor.value.trim();
    if (sql === '') {
        setSqlStatus('Informe uma consulta SQL para executar.', 'error');
        return;
    }

    const restoreButton = setButtonBusy(runButton, 'Executando...', 'fas fa-spinner fa-spin');
    setSqlStatus('Executando consulta...', '');

    try {
        const data = await postSqlConsole('execute', {
            sql,
            limit: Number(limit.value || sqlConsoleDefaultLimit()),
        });

        sqlConsoleState.dataset = {
            ...sqlConsoleDataset(),
            history: data.history || sqlConsoleDataset().history || [],
        };
        renderHistory();
        renderSqlResult(data);
        setSqlStatus('Consulta executada com sucesso.', 'success');
    } catch (error) {
        setSqlStatus(error.message || 'Falha ao executar a consulta.', 'error');
        renderResultPlaceholder('Nao foi possivel carregar o resultado.');
        try {
            await refreshSqlDataset();
        } catch (refreshError) {
            console.error(refreshError);
        }
    } finally {
        restoreButton();
    }
}

async function confirmWriteExecution() {
    const preview = sqlConsoleState.writePreview;
    const limit = document.getElementById('sqlRowLimit');
    const input = document.getElementById('sqlWriteConfirmationInput');
    const confirmButton = document.getElementById('sqlWriteConfirmButton');

    if (!preview) {
        setSqlStatus('Gere uma previa antes de confirmar a escrita.', 'error');
        return;
    }

    if (!(limit instanceof HTMLSelectElement) || !(input instanceof HTMLInputElement)) {
        return;
    }

    if (!sqlConsolePreviewMatchesEditor()) {
        setSqlStatus('A query mudou apos a previa. Gere uma nova previa antes de executar.', 'error');
        invalidateWritePreview({ silent: true, keepStatus: true });
        return;
    }

    updateWriteConfirmState();
    if (confirmButton instanceof HTMLButtonElement && confirmButton.hasAttribute('disabled')) {
        setSqlStatus('Revise a query e digite a frase de confirmacao exatamente como exibida.', 'error');
        return;
    }

    const restoreButton = setButtonBusy(confirmButton, 'Aplicando...', 'fas fa-spinner fa-spin');
    setSqlStatus('Executando alteracao controlada...', '');

    try {
        const data = await postSqlConsole('execute', {
            sql: sqlConsoleCurrentSql(),
            limit: Number(limit.value || sqlConsoleDefaultLimit()),
            confirm_write: true,
            preview_token: String(preview.token || ''),
            confirmation_text: input.value,
        });

        invalidateWritePreview({ silent: true, keepStatus: true });
        sqlConsoleState.dataset = {
            ...sqlConsoleDataset(),
            history: data.history || sqlConsoleDataset().history || [],
        };
        renderHistory();
        renderSqlResult(data);
        setSqlStatus('Alteracao executada com sucesso.', 'success');
    } catch (error) {
        setSqlStatus(error.message || 'Falha ao executar a alteracao SQL.', 'error');
        try {
            await refreshSqlDataset();
        } catch (refreshError) {
            console.error(refreshError);
        }
    } finally {
        restoreButton();
    }
}

async function executeSql() {
    const sql = sqlConsoleCurrentSql();
    if (sql === '') {
        setSqlStatus('Informe uma consulta SQL para executar.', 'error');
        return;
    }

    if (sqlConsoleLikelyWriteStatement(sql)) {
        await requestWritePreview();
        return;
    }

    await executeReadQuery();
}

function applyPreset(index) {
    const presets = Array.isArray(sqlConsoleDataset().presets) ? sqlConsoleDataset().presets : [];
    const preset = presets[index];
    const editor = sqlConsoleEditor();
    if (!preset || !editor) {
        return;
    }

    invalidateWritePreview({ silent: true, keepStatus: true });
    editor.value = String(preset.sql || '');
    editor.focus();
    updateRunButtonState();
    setSqlStatus(`Preset "${preset.label || 'consulta'}" carregado.`, '');
}

function applyHistory(index) {
    const history = Array.isArray(sqlConsoleDataset().history) ? sqlConsoleDataset().history : [];
    const item = history[index];
    const editor = sqlConsoleEditor();
    if (!item || !editor) {
        return;
    }

    invalidateWritePreview({ silent: true, keepStatus: true });
    editor.value = String(item.sql_text || '');
    editor.focus();
    updateRunButtonState();
    setSqlStatus('Consulta do historico carregada no editor.', '');
}

function clearSqlEditor() {
    const editor = sqlConsoleEditor();
    if (!editor) {
        return;
    }

    invalidateWritePreview({ silent: true, keepStatus: true });
    editor.value = '';
    sqlConsoleState.lastResult = null;
    updateRunButtonState();
    setSqlStatus('Editor limpo.', '');
    renderResultPlaceholder('Execute uma consulta para ver os dados.');
}

function formatSql() {
    const editor = sqlConsoleEditor();
    if (!editor) {
        return;
    }

    const raw = editor.value.trim();
    if (raw === '') {
        return;
    }

    const keywords = [
        'SELECT',
        'FROM',
        'WHERE',
        'GROUP BY',
        'ORDER BY',
        'LEFT JOIN',
        'RIGHT JOIN',
        'INNER JOIN',
        'LIMIT',
        'HAVING',
        'UNION ALL',
        'UNION',
        'WITH',
        'UPDATE',
        'SET',
        'INSERT INTO',
        'VALUES',
        'DELETE FROM',
        'AND',
        'OR',
    ];
    let formatted = raw.replace(/\s+/g, ' ');

    keywords.forEach((keyword) => {
        const pattern = new RegExp(`\\s+${keyword.replace(/\s+/g, '\\s+')}\\s+`, 'gi');
        const replacement = keyword === 'AND' || keyword === 'OR'
            ? `\n  ${keyword} `
            : `\n${keyword} `;
        formatted = formatted.replace(pattern, replacement);
    });

    invalidateWritePreview({ silent: true, keepStatus: true });
    editor.value = formatted.trim();
    updateRunButtonState();
    setSqlStatus('Consulta formatada localmente.', '');
}

function exportResultCsv() {
    const escapeCsv = (value) => {
        if (value === null || value === undefined) {
            return '';
        }
        const text = String(value).replace(/"/g, '""');
        return `"${text}"`;
    };

    const matrix = getResultExportMatrix();
    if (!matrix) {
        return;
    }

    const lines = [
        matrix.columns.map(escapeCsv).join(','),
        ...matrix.rows.map((row) => row.map(escapeCsv).join(',')),
    ];

    const blob = new Blob([`\uFEFF${lines.join('\r\n')}`], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'dashglpi-sql-console.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);

    setSqlStatus('Resultado exportado em CSV.', 'success');
}

function getResultExportMatrix() {
    const result = sqlConsoleState.lastResult;
    if (!result) {
        return null;
    }

    if (result.mutation) {
        const mutation = result.mutation || {};
        const meta = result.meta || {};
        return {
            columns: ['Operacao', 'Tabelas', 'Linhas afetadas', 'Executado em'],
            rows: [[
                String(mutation.statement_type || result.query?.statement_type || '-'),
                Array.isArray(mutation.target_tables) && mutation.target_tables.length
                    ? mutation.target_tables.join(', ')
                    : 'Nao identificado',
                String(Number(mutation.affected_rows || meta.affected_rows || 0)),
                String(meta.executed_at || '-'),
            ]],
        };
    }

    if (!Array.isArray(result.columns) || !Array.isArray(result.rows) || !result.columns.length) {
        return null;
    }

    return {
        columns: result.columns.map((column) => String(column ?? '')),
        rows: result.rows.map((row) => Array.isArray(row)
            ? row.map((value) => value === null ? 'NULL' : String(value))
            : []),
    };
}

async function copyResultToClipboard() {
    const matrix = getResultExportMatrix();
    if (!matrix) {
        return;
    }

    const text = [
        matrix.columns.join('\t'),
        ...matrix.rows.map((row) => row.join('\t')),
    ].join('\n');

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }

        setSqlStatus('Resultado copiado para a area de transferencia.', 'success');
    } catch (error) {
        setSqlStatus('Nao foi possivel copiar o resultado.', 'error');
    }
}

function confirmOptimizeHeavyTables() {
    const command = String(sqlConsoleMaintenance().optimize_heavy_tables_command || '');
    openSqlConfirmModal(
        'Otimizar tabelas pesadas agora? Isso roda OPTIMIZE TABLE em glpi_logs, glpi_tickets e glpi_queuednotifications e remove logs com mais de 90 dias.',
        command,
        () => { optimizeHeavyTables().catch((error) => console.error(error)); }
    );
}

async function optimizeHeavyTables() {
    const button = document.getElementById('sqlOptimizeTablesButton');
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const restoreButton = setButtonBusy(button, 'Otimizando...', 'fas fa-spinner fa-spin');
    setSqlStatus('Otimizando tabelas pesadas...', '');

    try {
        const data = await postSqlConsole('optimize_heavy_tables');
        const removedLogs = Number(data.removed_logs || 0);
        const removedLabel = removedLogs === 1 ? '1 registro de log removido' : `${removedLogs} registros de log removidos`;

        setSqlStatus(`${String(data.message || 'Tabelas pesadas otimizadas com sucesso.')} ${removedLabel}.`, 'success');
        if (Array.isArray(data.history)) {
            sqlConsoleState.dataset.history = data.history;
            renderHistory();
        }
    } catch (error) {
        setSqlStatus(error.message || 'Falha ao otimizar tabelas pesadas.', 'error');
        throw error;
    } finally {
        restoreButton();
    }
}

function renderDiagnostics() {
    const banner = document.getElementById('sqlTimezoneBanner');
    if (!banner) {
        return;
    }

    const diagnostics = sqlConsoleDataset().diagnostics || {};
    banner.hidden = diagnostics.timezone_named_support !== false;
}

async function retestTimezoneDiagnostic() {
    const button = document.getElementById('sqlTimezoneRetestButton');
    const restoreButton = setButtonBusy(button, 'Testando...', 'fas fa-spinner fa-spin');

    try {
        const data = await postSqlConsole('check_diagnostics');
        sqlConsoleState.dataset.diagnostics = data.diagnostics || {};
        renderDiagnostics();
        setSqlStatus(
            sqlConsoleState.dataset.diagnostics.timezone_named_support
                ? 'Suporte a timezone nomeado confirmado no MySQL.'
                : 'Timezone nomeado ainda indisponivel no MySQL.',
            sqlConsoleState.dataset.diagnostics.timezone_named_support ? 'success' : 'error'
        );
    } catch (error) {
        setSqlStatus(error.message || 'Falha ao testar diagnostico de timezone.', 'error');
    } finally {
        restoreButton();
    }
}

function confirmClearGlpiCache() {
    if (!sqlConsoleGlpiCacheClearAvailable()) {
        setSqlStatus('Bridge do GLPI indisponivel para limpar o cache.', 'error');
        return;
    }

    const command = String(sqlConsoleMaintenance().clear_glpi_cache_command || 'rm -rf /var/glpi/files/_cache/*');
    openSqlConfirmModal(
        'Limpar o cache do GLPI agora? Use esta ação após restaurar template, regra ou configuração sensível.',
        command,
        () => { clearGlpiCache().catch((error) => console.error(error)); }
    );
}

async function clearGlpiCache() {
    const button = document.getElementById('sqlClearGlpiCacheButton');
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const restoreButton = setButtonBusy(button, 'Limpando cache...', 'fas fa-spinner fa-spin');
    setSqlStatus('Limpando cache do GLPI...', '');

    try {
        const data = await postSqlConsole('clear_glpi_cache');
        const removedEntries = Number(data.removed_entries || 0);
        const cachePath = String(data.cache_path || '/var/glpi/files/_cache');
        const removedLabel = removedEntries === 1 ? '1 item removido' : `${removedEntries} itens removidos`;
        const clearedAt = data.cleared_at ? ` em ${data.cleared_at}` : '';

        setSqlStatus(`${String(data.message || 'Cache do GLPI limpo com sucesso.')} ${removedLabel} de ${cachePath}${clearedAt}.`, 'success');
    } catch (error) {
        setSqlStatus(error.message || 'Falha ao limpar o cache do GLPI.', 'error');
        throw error;
    } finally {
        restoreButton();
    }
}

function openSqlConfirmModal(message, command, onConfirm) {
    const modal = document.getElementById('sqlConfirmModal');
    const messageEl = document.getElementById('sqlConfirmModalMessage');
    const commandEl = document.getElementById('sqlConfirmModalCommand');
    if (!modal) {
        return;
    }

    sqlConsoleState.pendingConfirmAction = typeof onConfirm === 'function' ? onConfirm : null;

    if (messageEl) {
        messageEl.textContent = message || 'Confirmar ação?';
    }
    if (commandEl) {
        if (command) {
            commandEl.textContent = command;
            commandEl.hidden = false;
        } else {
            commandEl.textContent = '';
            commandEl.hidden = true;
        }
    }

    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
}

function closeSqlConfirmModal() {
    const modal = document.getElementById('sqlConfirmModal');
    if (!modal) {
        return;
    }

    sqlConsoleState.pendingConfirmAction = null;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
}

function bindSqlConsoleActions() {
    document.getElementById('sqlRunButton')?.addEventListener('click', () => {
        executeSql().catch((error) => console.error(error));
    });
    document.getElementById('sqlRefreshHistory')?.addEventListener('click', async () => {
        setSqlStatus('Atualizando historico...', '');
        try {
            await refreshSqlDataset();
            setSqlStatus('Historico atualizado.', 'success');
        } catch (error) {
            setSqlStatus(error.message || 'Falha ao atualizar historico.', 'error');
        }
    });
    document.getElementById('sqlFormatButton')?.addEventListener('click', formatSql);
    document.getElementById('sqlClearButton')?.addEventListener('click', clearSqlEditor);
    document.getElementById('sqlClearGlpiCacheButton')?.addEventListener('click', confirmClearGlpiCache);
    document.getElementById('sqlOptimizeTablesButton')?.addEventListener('click', confirmOptimizeHeavyTables);
    document.getElementById('sqlTimezoneRetestButton')?.addEventListener('click', () => {
        retestTimezoneDiagnostic().catch((error) => console.error(error));
    });

    document.getElementById('sqlConfirmModalCancel')?.addEventListener('click', closeSqlConfirmModal);
    document.getElementById('sqlConfirmModalConfirm')?.addEventListener('click', () => {
        const action = sqlConsoleState.pendingConfirmAction;
        closeSqlConfirmModal();
        if (action) {
            action();
        }
    });
    document.getElementById('sqlConfirmModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'sqlConfirmModal') {
            closeSqlConfirmModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.getElementById('sqlConfirmModal')?.classList.contains('active')) {
            closeSqlConfirmModal();
        }
    });
    document.getElementById('sqlCsvButton')?.addEventListener('click', exportResultCsv);
    document.getElementById('sqlCopyButton')?.addEventListener('click', () => {
        copyResultToClipboard().catch((error) => console.error(error));
    });
    document.getElementById('sqlWriteCancelButton')?.addEventListener('click', () => {
        invalidateWritePreview();
    });
    document.getElementById('sqlWriteConfirmButton')?.addEventListener('click', () => {
        confirmWriteExecution().catch((error) => console.error(error));
    });

    document.getElementById('sqlPresetList')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-sql-preset-index]');
        if (!button) {
            return;
        }
        applyPreset(Number(button.dataset.sqlPresetIndex || 0));
    });

    document.getElementById('sqlHistoryList')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-sql-history-index]');
        if (!button) {
            return;
        }
        applyHistory(Number(button.dataset.sqlHistoryIndex || 0));
    });

    const editor = sqlConsoleEditor();
    if (editor) {
        editor.addEventListener('input', () => {
            if (sqlConsoleState.writePreview && !sqlConsolePreviewMatchesEditor()) {
                invalidateWritePreview({ silent: true, keepStatus: true });
            } else {
                updateWriteConfirmState();
                updateRunButtonState();
            }
        });

        editor.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                executeSql().catch((error) => console.error(error));
                return;
            }

            if (event.key === 'Tab') {
                event.preventDefault();
                const start = editor.selectionStart;
                const end = editor.selectionEnd;
                editor.value = `${editor.value.slice(0, start)}    ${editor.value.slice(end)}`;
                editor.selectionStart = editor.selectionEnd = start + 4;
                updateWriteConfirmState();
                updateRunButtonState();
            }
        });
    }

    document.getElementById('sqlWriteReviewed')?.addEventListener('change', updateWriteConfirmState);
    document.getElementById('sqlWriteConfirmationInput')?.addEventListener('input', updateWriteConfirmState);
}

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initResponsiveNavigation();
    populateLimitOptions();
    renderRules();
    renderPresets();
    renderHistory();
    updateWriteModeIndicators();
    updateMaintenanceActions();
    renderDiagnostics();
    updateRunButtonState();
    renderResultPlaceholder('Execute uma consulta para ver os dados.');
    bindSqlConsoleActions();
});
