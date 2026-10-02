// ==================== GLOBAL VARIABLES ====================
const PLUGIN_ROOT = (typeof DASHGLPI_ROOT !== 'undefined') ? DASHGLPI_ROOT : '';
const HOURLY_RANGE_OPTIONS = [1, 3, 6, 12, 24, 48];
const FUNCTIONAL_PAGE_KEYS = ['dashboard', 'tickets', 'ticketsKanban', 'sla', 'ranking', 'assets'];
const AUXILIARY_HASH_PAGE_KEYS = ['ticketNew', 'computerImport', 'monitorImport', 'ticketImport'];
const TV_ROTATION_PAGE_KEYS = ['dashboard', 'sla', 'ranking', 'assets'];
const ALLOWED_FUNCTIONAL_PAGES = Array.isArray(typeof DASHGLPI_ALLOWED_PAGES !== 'undefined' ? DASHGLPI_ALLOWED_PAGES : null)
    ? DASHGLPI_ALLOWED_PAGES
        .filter((pageId) => FUNCTIONAL_PAGE_KEYS.includes(pageId))
        .concat(DASHGLPI_ALLOWED_PAGES.includes('tickets') ? ['ticketsKanban'] : [])
    : [...FUNCTIONAL_PAGE_KEYS];
const DEFAULT_FUNCTIONAL_PAGE = typeof DASHGLPI_DEFAULT_PAGE === 'string' && DASHGLPI_DEFAULT_PAGE !== ''
    ? DASHGLPI_DEFAULT_PAGE
    : (ALLOWED_FUNCTIONAL_PAGES[0] || 'dashboard');
const TICKET_REPORTS_ENABLED = typeof DASHGLPI_TICKET_REPORTS_ENABLED === 'undefined'
    ? true
    : Boolean(DASHGLPI_TICKET_REPORTS_ENABLED);
const MY_TASKS_FILTER_LOCKED = typeof DASHGLPI_LOCK_MY_TASKS_FILTER === 'undefined'
    ? false
    : Boolean(DASHGLPI_LOCK_MY_TASKS_FILTER);

/**
 * Helper de fetch AJAX para o bridge cliente (Padrão duplicado C do PLAN-20260703-013).
 * Injeta csrf_token, faz POST x-www-form-urlencoded e retorna o JSON já decodificado.
 * Em falha de rede, rejeita com um Error cujo `.message` é o texto de fallback pedido
 * (para o call site poder `alert(err.message)` no mesmo padrão usado hoje).
 *
 * Fase 1 do PLAN-20260703-013: helper introduzido, nenhuma chamada existente migrada ainda.
 */
async function dashglpiPostForm(url, fields, networkErrorMessage = 'Erro de conexão.') {
    const body = new URLSearchParams({
        ...fields,
        csrf_token: typeof DASHGLPI_CSRF_TOKEN !== 'undefined' ? DASHGLPI_CSRF_TOKEN : '',
    });

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body,
        });
        return await res.json();
    } catch {
        throw new Error(networkErrorMessage);
    }
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

function isFunctionalPage(pageId) {
    return FUNCTIONAL_PAGE_KEYS.includes(String(pageId || ''));
}

function pageSectionExists(pageId) {
    return document.getElementById(`${pageId}Section`) !== null;
}

function pageAllowed(pageId) {
    if (!isFunctionalPage(pageId)) {
        return true;
    }
    if (String(pageId || '') === 'ticketsKanban') {
        return ALLOWED_FUNCTIONAL_PAGES.includes('tickets') || ALLOWED_FUNCTIONAL_PAGES.includes('ticketsKanban');
    }
    return ALLOWED_FUNCTIONAL_PAGES.includes(String(pageId || ''));
}

function pageMenuLink(pageId) {
    return document.querySelector(`.menu-link[data-page="${pageId}"], .menu-sublink[data-page="${pageId}"]`);
}

function ticketReportsEnabled() {
    return TICKET_REPORTS_ENABLED;
}

function auxiliaryHashPages() {
    return AUXILIARY_HASH_PAGE_KEYS.filter((pageId) => pageSectionExists(pageId));
}

function hashNavigablePages() {
    return [...dashboardHashPages(), ...auxiliaryHashPages()]
        .filter((pageId, index, items) => pageId !== '' && items.indexOf(pageId) === index);
}

function currentVisiblePageId() {
    const activeSection = document.querySelector('.page-section.active[id$="Section"]');
    if (!activeSection) return '';
    return activeSection.id.replace(/Section$/, '');
}

function defaultLandingPage() {
    if (pageAllowed(DEFAULT_FUNCTIONAL_PAGE) && pageSectionExists(DEFAULT_FUNCTIONAL_PAGE)) {
        return DEFAULT_FUNCTIONAL_PAGE;
    }

    const firstAllowedFunctional = ALLOWED_FUNCTIONAL_PAGES.find((pageId) => pageSectionExists(pageId));
    if (firstAllowedFunctional) {
        return firstAllowedFunctional;
    }

    const navigablePages = dashboardHashPages();
    return navigablePages[0] || 'dashboard';
}

function tvRotationPages() {
    return TV_ROTATION_PAGE_KEYS.filter((pageId) => pageAllowed(pageId) && pageSectionExists(pageId));
}

// Namespace único para o estado mutável do dashboard (PLAN-20260703-013, Fase 3.4).
// Consolida as variáveis soltas da antiga seção GLOBAL VARIABLES para reduzir
// acoplamento oculto entre os módulos carregados via <script> sequenciais.
const DashState = {
    lineChart: undefined,
    barChart: undefined,
    monthlyChart: undefined,
    notificationChart: undefined,
    healthQueueChart: undefined,
    tvMode: false,
    notificationCount: 0,
    dashboardPeriodDays: 30,
    createdTicketsRangeHours: 1,
    notificationRangeHours: 1,
    myTasksOnly: false,
    dashboardNotificationsGlobal: [],
    dismissedNotificationIds: new Set(),
    glpiHealthDataGlobal: null,
    glpiHealthLoadPromise: null,
    dashboardDataLoadPromise: null,
    dashboardDataAbortController: null,
    dashboardDataRequestSequence: 0,
    dashboardDataLastRequestKey: '',
    dashboardDataLoadedOnce: false,
    assetsDataGlobal: [],
    ticketsDataGlobal: [],
    ticketsSortState: { key: 'date', direction: 'desc' },
    ticketsPaginationState: { page: 1, pageSize: 10 },
    ticketStatusFilter: ['1', '3', '2', '4', '5', '6'],
    ticketsView: 'list',
    slaDataGlobal: [],
    slaSummaryGlobal: { critical: 0, warning: 0, unassigned: 0, ok: 0, avg_open_seconds: 0 },
    slaSortState: { key: 'risk_score', direction: 'desc' },
    slaFilterState: 'all',
    slaAdvancedFilterState: { statuses: ['1', '2', '3', '4'], dateFrom: '', dateTo: '' },
    slaExpandedRows: new Set(),
    ticketAssignmentOptions: { users: [], groups: [], loaded: false },
    adminEntitiesGlobal: [],
    adminGroupsGlobal: [],
    adminCategoriesGlobal: [],
    adminProfilesGlobal: [],
    adminProfilesFullGlobal: [],
    adminUsersGlobal: [],
    adminTelegramUserIds: {}, // PLAN-20260714-023: users_id => telegram_user_id
    adminSlaPoliciesGlobal: [],
    techniciansCacheGlobal: null,
    entityNotificationPrefixContext: null,
    entitySolutionClosureContext: null,
    entitySmtpContext: null,
    categoryImportPreviewToken: '',
    adminUserEditorMode: 'create',
    adminEntityEditorMode: 'create',
    adminGroupEditorMode: 'create',
    adminProfileEditorMode: 'create',
    ticketCreateAttachmentSequence: 0,
    ticketCreateState: { loaded: false, loading: false, submitting: false, catalog: null, lastTicket: null, attachments: [] },
    followupAttachmentSequence: 0,
    followupCreateState: { attachments: [], uploadMaxLabel: '' },
};

const TICKETS_PAGE_SIZE_DEFAULT = 10;
const TICKETS_PAGE_SIZE_OPTIONS = [10, 20, 50, 100];
const TICKET_STATUS_FILTER_ALL = ['1', '3', '2', '4', '5', '6'];
const TICKET_STATUS_FILTER_DEFAULT = TICKET_STATUS_FILTER_ALL.slice();
const TICKETS_FILTERS_STORAGE_VERSION = '20261002-all-tickets-default';
const TICKET_STATUS_FILTER_LABELS = {
    1: 'Aberto',
    3: 'Planejado',
    2: 'Em Andamento',
    4: 'Pendente',
    5: 'Solucionando',
    6: 'Fechado',
};

const ENTITY_SMTP_FIELD_IDS = {
    entities_id: 'entitySmtpEntityId',
    admin_email: 'entitySmtpAdminEmail',
    admin_email_name: 'entitySmtpAdminName',
    from_email: 'entitySmtpFromEmail',
    from_email_name: 'entitySmtpFromName',
    replyto_email: 'entitySmtpReplyEmail',
    replyto_email_name: 'entitySmtpReplyName',
    mailing_signature: 'entitySmtpSignature',
    is_active: 'entitySmtpIsActive',
    host: 'entitySmtpHost',
    port: 'entitySmtpPort',
    encryption: 'entitySmtpEncryption',
    smtp_username: 'entitySmtpUsername',
    smtp_passwd: 'entitySmtpPassword',
    smtp_check_certificate: 'entitySmtpCheckCertificate',
};

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initResponsiveNavigation();
    initCharts();
    initNotifications();
    initDashboardPeriodFilters();
    initCreatedTicketsRangeFilters();
    initNotificationRangeFilters();
    initMyTasksFilters();
    initSLAMonitor();
    initTicketSearchAndSort();
    initSLASearchAndSort();
    initTicketAssignmentModal();
    initSelfServiceActions();
    initChangePasswordForm();
    initAdminRegisters();
    initTicketCreateSection();
    initHashNavigation();
    openTicketDeepLinkFromQuery();
    refreshActiveDashboardPageData();

    updateClock();
    setInterval(updateClock, 1000);
    setInterval(refreshActiveDashboardPageData, 60000);
});

// ==================== THEME ====================
function initTheme() {
    const savedTheme = localStorage.getItem('glpi-theme');
    document.body.classList.toggle('light-mode', savedTheme !== 'dark');
    updateThemeIcon();
}

function toggleTheme() {
    document.body.classList.toggle('light-mode');
    const isLight = document.body.classList.contains('light-mode');
    localStorage.setItem('glpi-theme', isLight ? 'light' : 'dark');
    updateThemeIcon();

    if (DashState.lineChart) DashState.lineChart.destroy();
    if (DashState.barChart) DashState.barChart.destroy();
    if (DashState.monthlyChart) DashState.monthlyChart.destroy();
    if (DashState.notificationChart) DashState.notificationChart.destroy();
    if (DashState.healthQueueChart) DashState.healthQueueChart.destroy();
    initCharts();
    refreshActiveDashboardPageData();
}

function updateThemeIcon() {
    document.querySelectorAll('.theme-toggle i').forEach((icon) => {
        icon.className = document.body.classList.contains('light-mode') ? 'fas fa-sun' : 'fas fa-moon';
    });
}

// ==================== MENU ====================
function isMobileViewport() {
    return window.matchMedia('(max-width: 768px)').matches;
}

function updateScreenMode() {
    const mode = isMobileViewport() ? 'mobile' : 'web';
    window.DASHGLPI_SCREEN_MODE = mode;
    document.documentElement.dataset.screenMode = mode;
}

function syncResponsiveNavigation() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    if (!sidebar || !mainContent) return;

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
    updateScreenMode();
    bindResponsiveNavigation();
    syncResponsiveNavigation();
    window.addEventListener('resize', () => {
        updateScreenMode();
        syncResponsiveNavigation();
    });
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

        const navTarget = event.target.closest('.menu-link, .menu-sublink');
        if (navTarget && !navTarget.matches('.menu-group-summary')) {
            closeMobileMenu();
        }
    });
}

function toggleMenu(forceOpen = null) {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    if (!sidebar || !mainContent) return;

    const shouldOpen = forceOpen === null
        ? sidebar.classList.contains('collapsed')
        : Boolean(forceOpen);

    if (isMobileViewport()) {
        sidebar.classList.toggle('collapsed', !shouldOpen);
        mainContent.classList.add('expanded');
        document.body.classList.toggle('menu-closed', !shouldOpen);
        document.body.classList.toggle('mobile-menu-open', shouldOpen);
    } else {
        sidebar.classList.toggle('collapsed', !shouldOpen);
        mainContent.classList.toggle('expanded', !shouldOpen);
        document.body.classList.toggle('menu-closed', !shouldOpen);
        document.body.classList.remove('mobile-menu-open');
    }

    setTimeout(() => {
        if (DashState.lineChart) DashState.lineChart.resize();
        if (DashState.barChart) DashState.barChart.resize();
        if (DashState.monthlyChart) DashState.monthlyChart.resize();
        if (DashState.notificationChart) DashState.notificationChart.resize();
        if (DashState.healthQueueChart) DashState.healthQueueChart.resize();
    }, 350);
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

function resizeDashboardCharts(delay = 150) {
    setTimeout(() => {
        if (DashState.lineChart) DashState.lineChart.resize();
        if (DashState.barChart) DashState.barChart.resize();
        if (DashState.monthlyChart) DashState.monthlyChart.resize();
        if (DashState.notificationChart) DashState.notificationChart.resize();
        if (DashState.healthQueueChart) DashState.healthQueueChart.resize();
    }, delay);
}

function toggleRecentActivity() {
    const card = document.getElementById('recentActivityCard');
    const body = document.getElementById('recentActivityBody');
    const button = document.getElementById('toggleRecentActivity');
    if (!card || !body || !button) return;

    const willExpand = body.hidden;
    body.hidden = !willExpand;
    card.classList.toggle('is-collapsed', !willExpand);
    button.setAttribute('aria-expanded', willExpand ? 'true' : 'false');

    const icon = button.querySelector('i');
    const label = button.querySelector('span');
    if (icon) icon.className = willExpand ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    if (label) label.textContent = willExpand ? 'Recolher' : 'Expandir';

    resizeDashboardCharts(120);
}

function toggleHealthTableSection(sectionKey) {
    const card = document.getElementById(`health${sectionKey}Card`);
    const body = document.getElementById(`health${sectionKey}Panel`);
    const button = document.getElementById(`toggleHealth${sectionKey}`);
    if (!card || !body || !button) return;

    const willExpand = body.hidden;
    body.hidden = !willExpand;
    card.classList.toggle('is-collapsed', !willExpand);
    button.setAttribute('aria-expanded', willExpand ? 'true' : 'false');

    const icon = button.querySelector('i');
    const label = button.querySelector('span');
    if (icon) icon.className = willExpand ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    if (label) label.textContent = willExpand ? 'Recolher' : 'Expandir';
}

// ==================== MOBILE FAB CONTEXTUAL ====================
const MOBILE_FAB_CONFIG = {
    users: { label: 'Novo Usuário', adminType: 'user' },
    entities: { label: 'Nova Entidade', adminType: 'entity' },
    profiles: { label: 'Novo Perfil', adminType: 'profile' },
    categories: { label: 'Nova Categoria', adminType: 'category' },
    groups: { label: 'Novo Grupo', adminType: 'group' }
};
const MOBILE_FAB_DEFAULT_PAGES = ['tickets'];

function updateMobileFab(pageId) {
    const btn = document.getElementById('mobileNewTicketBtn');
    if (!btn) return;

    const config = MOBILE_FAB_CONFIG[pageId];
    document.body.classList.toggle(
        'mobile-fab-hidden',
        !config && !MOBILE_FAB_DEFAULT_PAGES.includes(pageId)
    );

    const label = btn.querySelector('span');
    if (config) {
        btn.title = config.label;
        if (label) label.textContent = config.label;
        btn.href = '#' + pageId;
        btn.onclick = (event) => {
            event.preventDefault();
            openAdminForm(config.adminType);
        };
    } else {
        btn.title = 'Novo Chamado';
        if (label) label.textContent = 'Novo Chamado';
        btn.href = btn.dataset.defaultHref || btn.href;
        btn.onclick = null;
    }
}

// ==================== PAGE NAVIGATION ====================
function showPage(pageId, linkElement = null, options = {}) {
    if ((!pageAllowed(pageId) || !pageSectionExists(pageId)) && options.fallback !== false) {
        const fallbackPageId = defaultLandingPage();
        if (fallbackPageId && fallbackPageId !== pageId) {
            return showPage(fallbackPageId, pageMenuLink(fallbackPageId), { ...options, fallback: false });
        }
    }

    document.querySelectorAll('.page-section').forEach(section => {
        section.classList.remove('active');
    });

    const targetSection = document.getElementById(pageId + 'Section');
    if (!targetSection) return false;
    targetSection.classList.add('active');
    document.documentElement.dataset.screenType = targetSection.dataset.screenType || String(pageId);
    document.body.classList.toggle('mobile-tickets-active', pageId === 'tickets');

    document.querySelectorAll('.menu-link, .menu-sublink').forEach(link => {
        link.classList.remove('active');
    });
    if (!linkElement) {
        linkElement = pageMenuLink(pageId);
    }
    if (linkElement) {
        linkElement.classList.add('active');
        const parentGroup = linkElement.closest('.menu-group');
        if (parentGroup) {
            parentGroup.open = true;
        }
    }

    document.body.classList.toggle('mobile-ticket-new-active', pageId === 'ticketNew');
    updateMobileFab(pageId);

    if (options.updateHash !== false && hashNavigablePages().includes(pageId)) {
        const nextHash = pageId === 'dashboard' ? '' : '#' + pageId;
        const nextUrl = window.location.pathname + nextHash;
        if (window.location.hash !== nextHash) {
            window.history.replaceState(null, '', nextUrl);
        }
    }

    // Gatilhos específicos por página
    if (pageId === 'dashboard') {
        updateData();
    }
    if (pageId === 'ranking') {
        renderLeaderboard();
    }
    if (pageId === 'health') {
        loadGlpiHealth(true);
    }
    if (pageId === 'tickets') {
        loadTicketLists();
    }
    if (pageId === 'ticketsKanban') {
        loadTicketLists().then(() => renderKanbanBoard());
    }
    if (pageId === 'assets') {
        loadFullAssets();
    }
    if (pageId === 'ticketNew') {
        loadTicketCreateCatalog();
    }
    if (['entities', 'groups', 'categories', 'users', 'profiles'].includes(pageId)) {
        loadAdminRegisters(pageId);
    }

    closeMobileMenu();
    resizeDashboardCharts(150);
    return false;
}

function dashboardHashPages() {
    return Array.from(document.querySelectorAll('.menu-link[data-page], .menu-sublink[data-page]'))
        .map((link) => String(link.dataset.page || ''))
        .filter((pageId, index, items) => pageId !== '' && pageSectionExists(pageId) && items.indexOf(pageId) === index);
}

function initHashNavigation() {
    openPageFromHash();
    window.addEventListener('hashchange', openPageFromHash);
}

function openPageFromHash() {
    const pageId = (window.location.hash || '').replace('#', '');
    const fallbackPageId = defaultLandingPage();

    if (pageId === '') {
        showPage(fallbackPageId, pageMenuLink(fallbackPageId), { updateHash: false });
        return;
    }

    if (!hashNavigablePages().includes(pageId) || !pageAllowed(pageId)) {
        showPage(fallbackPageId, pageMenuLink(fallbackPageId));
        return;
    }

    showPage(pageId, pageMenuLink(pageId), { updateHash: false });
}

/**
 * Deep-link vindo de alertas externos (Teams/WhatsApp/Telegram — ver
 * dashglpi_dashboard_ticket_url() no backend): ?ticket_id=123 na URL abre o
 * chamado direto, sem precisar navegar manualmente até a lista de Chamados.
 * Remove o parâmetro da URL depois de abrir, para não reabrir em refresh/voltar.
 */
function openTicketDeepLinkFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const ticketId = Number(params.get('ticket_id'));
    if (!ticketId || !pageAllowed('tickets')) {
        return;
    }

    showPage('tickets', pageMenuLink('tickets'));
    openTicketDetailModal(ticketId);

    params.delete('ticket_id');
    const nextSearch = params.toString();
    const nextUrl = window.location.pathname + (nextSearch ? '?' + nextSearch : '') + window.location.hash;
    window.history.replaceState(null, '', nextUrl);
}

// ==================== TICKET CREATE ====================
// Movido para public/js/script.ticket-create.js (PLAN-20260703-013, Fase 3.3).

// ==================== CLOCK ====================
function updateClock() {
    const now = new Date();
    const clock = document.getElementById('clock');
    if (!clock) return;
    clock.textContent = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');
}

// ==================== TV MODE ====================
function toggleTVMode() {
    setTVMode(!DashState.tvMode);
}

function exitTVMode() {
    setTVMode(false);
}

function setTVMode(enabled) {
    DashState.tvMode = Boolean(enabled);
    document.body.classList.toggle('tv-mode', DashState.tvMode);

    if (DashState.tvMode) {
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        }
        startTVRotation();
    } else {
        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen();
        }
        stopTVRotation();
    }
}

let tvRotationInterval;
function startTVRotation() {
    const pages = tvRotationPages();
    if (pages.length === 0) {
        return;
    }

    stopTVRotation();

    if (pages.length === 1) {
        showPage(pages[0], pageMenuLink(pages[0]));
        return;
    }

    let currentIndex = Math.max(0, pages.indexOf(currentVisiblePageId()));

    tvRotationInterval = setInterval(() => {
        currentIndex = (currentIndex + 1) % pages.length;
        const pageId = pages[currentIndex];
        showPage(pageId, pageMenuLink(pageId));
    }, 15000);
}

function stopTVRotation() {
    clearInterval(tvRotationInterval);
}

// ==================== DATA UPDATE ====================
function dashboardPeriodParam() {
    const allowed = [7, 30, 90, 180];
    return allowed.includes(Number(DashState.dashboardPeriodDays)) ? Number(DashState.dashboardPeriodDays) : 30;
}

function normalizeHourlyRange(value, fallback = 1) {
    const parsedValue = Number(value);
    return HOURLY_RANGE_OPTIONS.includes(parsedValue) ? parsedValue : fallback;
}

function createdTicketsRangeParam() {
    return normalizeHourlyRange(DashState.createdTicketsRangeHours, 1);
}

function notificationRangeParam() {
    return normalizeHourlyRange(DashState.notificationRangeHours, 1);
}

function dashboardDataUrl(action) {
    const params = new URLSearchParams({
        action,
        period_days: String(dashboardPeriodParam()),
        created_tickets_range_hours: String(createdTicketsRangeParam()),
        notification_range_hours: String(notificationRangeParam()),
        my_tasks: (MY_TASKS_FILTER_LOCKED || DashState.myTasksOnly) ? '1' : '0',
        // Cache-buster defensivo: garante unicidade da URL mesmo que uma camada de borda
        // ignore os cabeçalhos no-store do origin (ver dashglpi_json).
        _ts: String(Date.now())
    });
    return `${PLUGIN_ROOT}/ajax/dashboard.php?${params.toString()}`;
}

function refreshActiveDashboardPageData() {
    const activePageId = currentVisiblePageId();
    if (activePageId === 'health') {
        loadGlpiHealth(true);
        return;
    }

    if (activePageId === 'dashboard' || activePageId === '') {
        updateData();
    }
}

function initDashboardPeriodFilters() {
    document.querySelectorAll('[data-dashboard-period]').forEach(button => {
        button.addEventListener('click', () => {
            const nextPeriod = Number(button.getAttribute('data-dashboard-period') || 30);
            if (nextPeriod === DashState.dashboardPeriodDays) return;
            DashState.dashboardPeriodDays = nextPeriod;
            document.querySelectorAll('[data-dashboard-period]').forEach(item => {
                item.classList.toggle('active', Number(item.getAttribute('data-dashboard-period')) === DashState.dashboardPeriodDays);
            });
            updateData({ force: true });
        });
    });
}

function initCreatedTicketsRangeFilters() {
    document.querySelectorAll('[data-created-tickets-range]').forEach(button => {
        button.addEventListener('click', () => {
            const nextRange = normalizeHourlyRange(button.getAttribute('data-created-tickets-range'), 1);
            if (nextRange === DashState.createdTicketsRangeHours) return;
            DashState.createdTicketsRangeHours = nextRange;
            updateCreatedTicketsRangeButtons();
            updateData({ force: true });
        });
    });
}

function initNotificationRangeFilters() {
    document.querySelectorAll('[data-notification-range]').forEach(button => {
        button.addEventListener('click', () => {
            const nextRange = normalizeHourlyRange(button.getAttribute('data-notification-range'), 1);
            if (nextRange === DashState.notificationRangeHours) return;
            DashState.notificationRangeHours = nextRange;
            updateNotificationRangeButtons();
            refreshActiveDashboardPageData();
        });
    });
}

function updateCreatedTicketsRangeButtons() {
    document.querySelectorAll('[data-created-tickets-range]').forEach(item => {
        item.classList.toggle('active', Number(item.getAttribute('data-created-tickets-range')) === DashState.createdTicketsRangeHours);
    });
}

function updateNotificationRangeButtons() {
    document.querySelectorAll('[data-notification-range]').forEach(item => {
        item.classList.toggle('active', Number(item.getAttribute('data-notification-range')) === DashState.notificationRangeHours);
    });
}

function initMyTasksFilters() {
    const savedVersion = localStorage.getItem('dashglpi-tickets-filter-version');
    if (savedVersion !== TICKETS_FILTERS_STORAGE_VERSION) {
        if (!MY_TASKS_FILTER_LOCKED) {
            localStorage.setItem('dashglpi-my-tasks-only', '0');
        }
        localStorage.setItem('dashglpi-tickets-status-filter', JSON.stringify(TICKET_STATUS_FILTER_DEFAULT));
        localStorage.setItem('dashglpi-tickets-filter-version', TICKETS_FILTERS_STORAGE_VERSION);
    }

    const savedValue = localStorage.getItem('dashglpi-my-tasks-only');
    DashState.myTasksOnly = MY_TASKS_FILTER_LOCKED ? true : (savedValue === null ? false : savedValue === '1');
    if (MY_TASKS_FILTER_LOCKED) {
        localStorage.setItem('dashglpi-my-tasks-only', '1');
    }
    syncMyTasksFilters();

    document.querySelectorAll('[data-my-tasks-filter]').forEach(input => {
        input.addEventListener('change', () => {
            if (MY_TASKS_FILTER_LOCKED) {
                DashState.myTasksOnly = true;
                localStorage.setItem('dashglpi-my-tasks-only', '1');
                syncMyTasksFilters();
                return;
            }
            DashState.myTasksOnly = input.checked;
            localStorage.setItem('dashglpi-my-tasks-only', DashState.myTasksOnly ? '1' : '0');
            syncMyTasksFilters();
            DashState.ticketsPaginationState.page = 1;
            updateData({ force: true });
            loadTicketLists();
        });
    });
}

function syncMyTasksFilters() {
    if (MY_TASKS_FILTER_LOCKED) {
        DashState.myTasksOnly = true;
    }
    document.querySelectorAll('[data-my-tasks-filter]').forEach(input => {
        input.checked = DashState.myTasksOnly;
        input.disabled = MY_TASKS_FILTER_LOCKED;
    });
}

async function updateData(options = {}) {
    if (!pageAllowed('dashboard') || !pageSectionExists('dashboard')) {
        return null;
    }

    const requestUrl = dashboardDataUrl('dashboard_data');
    const force = Boolean(options.force);

    if (DashState.dashboardDataLoadPromise && !force && requestUrl === DashState.dashboardDataLastRequestKey) {
        return DashState.dashboardDataLoadPromise;
    }

    if (DashState.dashboardDataAbortController) {
        DashState.dashboardDataAbortController.abort();
    }

    const requestId = ++DashState.dashboardDataRequestSequence;
    const abortController = new AbortController();
    DashState.dashboardDataAbortController = abortController;
    DashState.dashboardDataLastRequestKey = requestUrl;

    DashState.dashboardDataLoadPromise = (async () => {
        try {
            const response = await fetch(requestUrl, { signal: abortController.signal });
            const data = await response.json();
            if (!response.ok || !data || !data.cards_top || !data.cards_bottom || !data.charts) {
                throw new Error(data?.error || 'Resposta inválida ao carregar a visão geral.');
            }

            if (requestId !== DashState.dashboardDataRequestSequence) {
                return null;
            }

            renderDashboardData(data);
            DashState.dashboardDataLoadedOnce = true;
            return data;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return null;
            }

            console.error('Error updating data:', error);
            if (!DashState.dashboardDataLoadedOnce) {
                renderDashboardFallback();
            }
            return null;
        } finally {
            if (requestId === DashState.dashboardDataRequestSequence) {
                DashState.dashboardDataLoadPromise = null;
                DashState.dashboardDataAbortController = null;
            }
        }
    })();

    return DashState.dashboardDataLoadPromise;
}

function renderDashboardData(data) {
    if (data.period && data.period.days) {
        DashState.dashboardPeriodDays = Number(data.period.days) || DashState.dashboardPeriodDays;
        document.querySelectorAll('[data-dashboard-period]').forEach(item => {
            item.classList.toggle('active', Number(item.getAttribute('data-dashboard-period')) === DashState.dashboardPeriodDays);
        });
    }
    if (data.charts?.created_tickets?.range_hours) {
        DashState.createdTicketsRangeHours = normalizeHourlyRange(data.charts.created_tickets.range_hours, DashState.createdTicketsRangeHours);
        updateCreatedTicketsRangeButtons();
    }
    if (data.notification_queue?.chart?.range_hours) {
        DashState.notificationRangeHours = normalizeHourlyRange(data.notification_queue.chart.range_hours, DashState.notificationRangeHours);
        updateNotificationRangeButtons();
    }

    const cardsTop = data.cards_top || {};
    const cardsBottom = data.cards_bottom || {};
    setVal('top-total', cardsTop.total ?? 0);
    setVal('top-andamento', cardsTop.andamento ?? 0);
    setVal('top-taxa', `${cardsTop.taxa ?? 0}%`);
    setVal('top-sla', cardsTop.sla ?? 0);
    setVal('top-tempo', `${cardsTop.tempo_medio ?? 0}h`);
    setVal('top-reabertos', cardsTop.reabertos ?? 0);
    setVal('bot-abertos', cardsBottom.abertos ?? 0);
    setVal('bot-atribuidos', cardsBottom.atribuidos ?? 0);
    setVal('bot-pendentes', cardsBottom.pendentes ?? 0);
    setVal('bot-finalizados', cardsBottom.finalizados ?? 0);
    updateNotificationQueueCards(data.notification_queue || null);
    renderItilObjectsKpis(data.itil_objects || {});

    const charts = data.charts || {};
    if (DashState.lineChart) {
        const createdTicketPoints = Array.isArray(charts.created_tickets?.points)
            ? charts.created_tickets.points
            : Array.isArray(charts.trend_line)
                ? charts.trend_line.map(x => ({ label: x.dia, total: x.total }))
                : [];
        DashState.lineChart.data.labels = createdTicketPoints.map(x => x.label);
        DashState.lineChart.data.datasets[0].data = createdTicketPoints.map(x => x.total);
        DashState.lineChart.update('none');
    }

    if (DashState.barChart) {
        const categoryPoints = Array.isArray(charts.cat_bar) ? charts.cat_bar : [];
        DashState.barChart.data.labels = categoryPoints.map(x => x.nome);
        DashState.barChart.data.datasets[0].data = categoryPoints.map(x => x.total);
        DashState.barChart.update('none');
    }

    if (DashState.monthlyChart) {
        const monthlyOpened = Array.isArray(charts.monthly_opened) ? charts.monthly_opened : [];
        const monthlySolved = Array.isArray(charts.monthly_solved) ? charts.monthly_solved : [];
        const processed = processMonthlyData(monthlyOpened, monthlySolved, dashboardPeriodParam());
        DashState.monthlyChart.data.labels = processed.labels;
        DashState.monthlyChart.data.datasets[0].data = processed.opened;
        DashState.monthlyChart.data.datasets[1].data = processed.solved;
        applyItilObjectsMonthlySeries(data.itil_objects || {});
        DashState.monthlyChart.update('none');
    }

    if (DashState.notificationChart) {
        const queuePoints = Array.isArray(data.notification_queue?.chart?.points)
            ? data.notification_queue.chart.points
            : [];
        DashState.notificationChart.data.labels = queuePoints.map(x => x.label);
        DashState.notificationChart.data.datasets[0].data = queuePoints.map(x => x.total);
        DashState.notificationChart.update('none');
    }

    const monthlyTitle = document.getElementById('monthlyChartTitle');
    if (monthlyTitle) {
        monthlyTitle.textContent = `Abertos vs Solucionados (${data.period?.label || '30 dias'})`;
    }
    renderDashboardNotifications(data.notifications || []);
    renderRecentActivity(data.recent_tickets || []);
    if (pageAllowed('tickets')) {
        loadTicketLists();
    }
}

// KPIs segmentados de Problema/Manutenção (PLAN-20260709-019). setVal é no-op
// quando o card não existe (perfil sem direito GLPI não recebe os cards no HTML).
function renderItilObjectsKpis(itilObjects) {
    Object.entries(itilObjects || {}).forEach(([key, info]) => {
        setVal(`itil-${key}-abertos`, info.abertos ?? 0);
        setVal(`itil-${key}-vencidos`, info.vencidos ?? 0);
        setVal(`itil-${key}-finalizados`, info.finalizados ?? 0);
    });
}

// Séries mensais de Problema/Manutenção no gráfico "Abertos vs Solucionados" —
// aditivas: as duas séries de chamados (datasets 0 e 1) ficam intactas (Decisão 5).
const ITIL_MONTHLY_SERIES_COLORS = {
    problem: { border: '#a855f7', background: 'rgba(168, 85, 247, 0.15)' },
    change: { border: '#f59e0b', background: 'rgba(245, 158, 11, 0.15)' },
};

function applyItilObjectsMonthlySeries(itilObjects) {
    if (!DashState.monthlyChart) return;

    const entries = Object.entries(itilObjects || {});
    entries.forEach(([key, info], index) => {
        const aligned = processMonthlyData(
            Array.isArray(info.monthly_opened) ? info.monthly_opened : [],
            [],
            dashboardPeriodParam()
        );
        const datasetIndex = 2 + index;
        const colors = ITIL_MONTHLY_SERIES_COLORS[key] || ITIL_MONTHLY_SERIES_COLORS.problem;
        if (!DashState.monthlyChart.data.datasets[datasetIndex]) {
            DashState.monthlyChart.data.datasets[datasetIndex] = {
                label: '',
                data: [],
                borderColor: colors.border,
                backgroundColor: colors.background,
            };
        }
        DashState.monthlyChart.data.datasets[datasetIndex].label = `${info.label_plural || key} (abertos)`;
        DashState.monthlyChart.data.datasets[datasetIndex].data = aligned.opened;
    });
}

function renderDashboardFallback() {
    setVal('top-total', 0);
    setVal('top-andamento', 0);
    setVal('top-taxa', '0%');
    setVal('top-sla', 0);
    setVal('top-tempo', '0h');
    setVal('top-reabertos', 0);
    setVal('bot-abertos', 0);
    setVal('bot-atribuidos', 0);
    setVal('bot-pendentes', 0);
    setVal('bot-finalizados', 0);
    updateNotificationQueueCards({ pending_count: 0, last_sent_label: '-', attention_active: false });
    renderDashboardNotifications([]);
    renderRecentActivity([]);
}

function updateNotificationQueueCards(queueData) {
    const queue = queueData || {};
    const pendingCount = Number(queue.pending_count ?? 0);
    const hasLastSent = typeof queue.last_sent_at === 'string' && queue.last_sent_at.trim() !== '';
    const fallbackLastSentLabel = typeof queue.last_sent_label === 'string' && queue.last_sent_label.trim() !== ''
        ? queue.last_sent_label
        : '0';
    const lastSentLabel = hasLastSent ? (queue.last_sent_label || '0') : fallbackLastSentLabel;
    const attentionActive = Boolean(queue.attention_active);
    const attentionReason = queue.attention_reason || '';

    setVal('queue-pending-count', pendingCount);
    setVal('queue-last-sent', lastSentLabel);

    const pendingCard = document.getElementById('queuePendingCard');
    if (pendingCard) {
        pendingCard.classList.toggle('is-attention', attentionActive);
        pendingCard.title = attentionReason;
    }

    const lastSentCard = document.getElementById('queueLastSentCard');
    if (lastSentCard) {
        lastSentCard.classList.toggle('is-attention', attentionActive);
        lastSentCard.title = attentionReason || (hasLastSent ? lastSentLabel : '');
    }
}

async function loadGlpiHealth(force = false) {
    const section = document.getElementById('healthSection');
    if (!section) {
        return null;
    }
    if (!force && currentVisiblePageId() !== 'health') {
        return DashState.glpiHealthDataGlobal;
    }
    if (DashState.glpiHealthLoadPromise) {
        return DashState.glpiHealthLoadPromise;
    }

    DashState.glpiHealthLoadPromise = (async () => {
        try {
            const response = await fetch(dashboardDataUrl('glpi_health'));
            const data = await response.json();
            if (!response.ok || !data || !data.overall || !data.queue || !data.crontasks) {
                throw new Error(data?.error || 'Resposta inválida ao carregar a saúde do GLPI.');
            }

            if (data.queue?.chart?.range_hours) {
                DashState.notificationRangeHours = normalizeHourlyRange(data.queue.chart.range_hours, DashState.notificationRangeHours);
                updateNotificationRangeButtons();
            }

            DashState.glpiHealthDataGlobal = data;
            renderGlpiHealth(data);
            return data;
        } catch (error) {
            console.error('Error loading GLPI health:', error);
            renderGlpiHealthError(error);
            return null;
        } finally {
            DashState.glpiHealthLoadPromise = null;
        }
    })();

    return DashState.glpiHealthLoadPromise;
}

function renderGlpiHealth(data) {
    const overall = data?.overall || {};
    const queue = data?.queue || {};
    const crontasks = data?.crontasks || {};
    const mail = data?.mail || {};
    const notificationConfig = data?.notification_config || {};
    const collectors = data?.collectors || {};
    const tickets = data?.tickets || {};
    const entitySmtpOwner = data?.entitysmtp_owner || {};
    const issues = Array.isArray(data?.issues) ? data.issues : [];
    const ticketSummary = tickets.summary || {};

    setHealthOverall(overall, issues);
    renderHealthLinks(data?.links || {});

    setVal('healthKpiQueuePending', Number(queue.pending_count || 0));
    setVal('healthKpiQueueDelay', formatDurationSecondsCompact(queue.delay_seconds || 0));
    setVal(
        'healthKpiCrontaskIssues',
        Object.values(crontasks).filter((task) => task?.monitored !== false && (!task?.available || task?.stale || task?.stuck_running || task?.drift_warning || !task?.recommendation_ok)).length
    );
    setVal('healthKpiCollectorErrors', Number(collectors.summary?.errors || 0));
    setVal('healthKpiSlaOverdue', Number(ticketSummary.critical || 0));
    setVal('healthKpiUnassigned', Number(ticketSummary.unassigned || 0));

    renderHealthQueue(queue);
    renderHealthCrontasks(crontasks, entitySmtpOwner);
    renderHealthMail(mail);
    renderHealthNotificationConfig(notificationConfig);
    renderHealthCollectors(collectors);
    renderHealthCriticalTickets(tickets);
    renderHealthRecentEvents(data?.recent_events || {});
    renderHealthPhpConfig(data?.php_config || {});
    renderHealthMailgateLoop(data?.mailgate_loop || {});
}

function renderHealthPhpConfig(phpConfig) {
    setVal('healthPhpMemoryLimit', phpConfig.memory_limit || '-');
    setVal('healthPhpUploadMaxFilesize', phpConfig.upload_max_filesize || '-');
    setVal('healthPhpPostMaxSize', phpConfig.post_max_size || '-');
    setVal('healthPhpMaxExecutionTime', phpConfig.max_execution_time ? `${phpConfig.max_execution_time}s` : '-');

    if (!phpConfig.available) {
        setHealthCallout(
            'healthPhpConfigCallout',
            'healthPhpConfigCalloutTitle',
            'healthPhpConfigCalloutText',
            'warning',
            'Diagnóstico indisponível',
            'Não foi possível ler as diretivas do PHP no container do GLPI (bridge indisponível).'
        );
        return;
    }

    if (phpConfig.upload_max_filesize_low || phpConfig.memory_limit_low) {
        setHealthCallout(
            'healthPhpConfigCallout',
            'healthPhpConfigCalloutTitle',
            'healthPhpConfigCalloutText',
            'warning',
            'Limites do PHP restritivos',
            phpConfig.upload_max_filesize_low
                ? `Seu GLPI está limitado a uploads de ${phpConfig.upload_max_filesize}. Altere o php.ini do container.`
                : `memory_limit em ${phpConfig.memory_limit}, abaixo do recomendado.`
        );
        return;
    }

    setHealthCallout(
        'healthPhpConfigCallout',
        'healthPhpConfigCalloutTitle',
        'healthPhpConfigCalloutText',
        'ok',
        'Diretivas do PHP adequadas',
        'memory_limit e upload_max_filesize estão dentro do recomendado.'
    );
}

function renderHealthMailgateLoop(mailgateLoop) {
    const body = document.getElementById('healthMailgateLoopBody');
    const count = document.getElementById('healthMailgateLoopCount');
    const items = Array.isArray(mailgateLoop.items) ? mailgateLoop.items : [];

    if (count) {
        count.textContent = `${items.length} par(es)`;
    }

    if (!body) return;
    if (!items.length) {
        body.innerHTML = '<tr><td colspan="4" class="table-empty">Nenhum loop de duplicação detectado nas últimas 24h.</td></tr>';
        return;
    }

    body.innerHTML = items.map((item) => `
        <tr>
            <td><strong>#${escHtml(item.ticket_id)}</strong></td>
            <td><strong>#${escHtml(item.duplicate_id)}</strong></td>
            <td>${escHtml(item.name || '-')}</td>
            <td>${escHtml(formatDateTime(item.date) || '-')}</td>
        </tr>
    `).join('');
}

function renderGlpiHealthError(error) {
    const message = error?.message || 'Erro ao carregar a saúde do GLPI.';
    const overallStatus = document.getElementById('healthOverallStatus');
    const overallCounts = document.getElementById('healthOverallCounts');
    const refreshedAt = document.getElementById('healthRefreshedAt');

    if (overallStatus) {
        overallStatus.className = 'health-overall-pill critical';
        overallStatus.textContent = 'Erro';
    }
    if (overallCounts) {
        overallCounts.textContent = message;
    }
    if (refreshedAt) {
        refreshedAt.textContent = '-';
    }

    setHealthCallout(
        'healthQueueCallout',
        'healthQueueCalloutTitle',
        'healthQueueCalloutText',
        'critical',
        'Falha ao ler a fila',
        message
    );
    setHealthCallout(
        'healthCrontaskCallout',
        'healthCrontaskCalloutTitle',
        'healthCrontaskCalloutText',
        'critical',
        'Falha ao ler ações automáticas',
        message
    );

    const collectorsBody = document.getElementById('healthCollectorsTableBody');
    const ticketsBody = document.getElementById('healthCriticalTicketsBody');
    const eventsBody = document.getElementById('healthRecentEventsBody');
    if (collectorsBody) {
        collectorsBody.innerHTML = `<tr><td colspan="4" class="table-empty">${escHtml(message)}</td></tr>`;
    }
    if (ticketsBody) {
        ticketsBody.innerHTML = `<tr><td colspan="6" class="table-empty">${escHtml(message)}</td></tr>`;
    }
    if (eventsBody) {
        eventsBody.innerHTML = `<tr><td colspan="6" class="table-empty">${escHtml(message)}</td></tr>`;
    }
}

function setHealthOverall(overall, issues) {
    const pill = document.getElementById('healthOverallStatus');
    const counts = document.getElementById('healthOverallCounts');
    const refreshedAt = document.getElementById('healthRefreshedAt');
    const status = String(overall?.status || 'ok');
    const label = ({
        critical: 'Crítico',
        warning: 'Atenção',
        ok: 'Estável'
    })[status] || 'Estável';

    if (pill) {
        pill.className = `health-overall-pill ${status}`;
        pill.textContent = label;
        pill.title = issues.map((issue) => `${issue.title}: ${issue.detail}`).join(' | ');
    }
    if (counts) {
        counts.textContent = `${Number(overall?.critical_count || 0)} críticos • ${Number(overall?.warning_count || 0)} alertas • ${Number(overall?.info_count || 0)} info`;
    }
    if (refreshedAt) {
        refreshedAt.textContent = formatDateTime(overall?.refreshed_at) || '-';
    }
}

function renderHealthLinks(links) {
    setHealthLink('healthLinkCrontask', links.crontask);
    setHealthLink('healthLinkEmail', links.email);
    setHealthLink('healthLinkNotifications', links.notifications);
    setHealthLink('healthLinkTemplates', links.templates);
    setHealthLink('healthLinkCollectors', links.collectors);
    setHealthLink('healthLinkSettingsNotifications', links.settings_notifications);
    setHealthLink('healthLinkSettingsSla', links.settings_sla);
}

function setHealthLink(id, href) {
    const anchor = document.getElementById(id);
    if (!anchor) return;

    const safeHref = typeof href === 'string' && href.trim() !== '' ? href : '#';
    anchor.href = safeHref;
    anchor.classList.toggle('is-disabled', safeHref === '#');
    anchor.setAttribute('aria-disabled', safeHref === '#' ? 'true' : 'false');
}

function renderHealthQueue(queue) {
    setVal('healthQueuePendingCount', Number(queue.pending_count || 0));
    setVal('healthQueueDelaySeconds', formatDurationSecondsCompact(queue.delay_seconds || 0));
    setVal('healthQueueOldestSend', formatDateTime(queue.oldest_send_time) || '-');
    setVal('healthQueueLastSent', queue.last_sent_label || formatDateTime(queue.last_sent_at) || '-');

    if (Number(queue.pending_count || 0) > 0 && queue.attention_active) {
        setHealthCallout(
            'healthQueueCallout',
            'healthQueueCalloutTitle',
            'healthQueueCalloutText',
            'critical',
            'Fila de e-mails atrasada',
            queue.attention_reason || 'Há pendências fora da janela esperada.'
        );
    } else if (Number(queue.pending_count || 0) > 0) {
        setHealthCallout(
            'healthQueueCallout',
            'healthQueueCalloutTitle',
            'healthQueueCalloutText',
            'warning',
            'Fila com pendências controladas',
            queue.attention_reason || 'Existem envios aguardando processamento, sem atraso crítico.'
        );
    } else {
        setHealthCallout(
            'healthQueueCallout',
            'healthQueueCalloutTitle',
            'healthQueueCalloutText',
            'ok',
            'Fila estável',
            'Nenhuma pendência relevante na fila de notificações.'
        );
    }

    if (DashState.healthQueueChart) {
        const points = Array.isArray(queue?.chart?.points) ? queue.chart.points : [];
        DashState.healthQueueChart.data.labels = points.map((point) => point.label);
        DashState.healthQueueChart.data.datasets[0].data = points.map((point) => Number(point.total || 0));
        DashState.healthQueueChart.update('none');
    }
}

function renderHealthCrontasks(crontasks, entitySmtpOwner = {}) {
    const container = document.getElementById('healthCrontaskCards');
    if (!container) return;

    const owner = entitySmtpOwner?.owner === 'dash' ? 'Dash' : 'GLPI';
    const taskKeys = owner === 'Dash'
        ? ['dashglpi_entitysmtp_queuednotification', 'queuednotification', 'slaticket']
        : ['queuednotification', 'dashglpi_entitysmtp_queuednotification', 'slaticket'];
    const tasks = taskKeys
        .map((key) => ({ key, ...(crontasks?.[key] || {}) }))
        .filter((task) => task.key);
    const monitoredTasks = tasks.filter((task) => task.monitored !== false);

    if (!tasks.length) {
        container.innerHTML = '<div class="health-empty-state">Nenhuma ação automática disponível.</div>';
        return;
    }

    const conflict = !!entitySmtpOwner?.conflict;
    const critical = monitoredTasks.some((task) => !task.available || task.stuck_running || task.stale);
    const warning = monitoredTasks.some((task) => task.drift_warning || !task.recommendation_ok);
    if (conflict) {
        setHealthCallout(
            'healthCrontaskCallout',
            'healthCrontaskCalloutTitle',
            'healthCrontaskCalloutText',
            'critical',
            'Conflito no dono da fila',
            'queuednotification e dashglpi_entitysmtp_queuednotification estão ativos ao mesmo tempo.'
        );
    } else if (critical) {
        setHealthCallout(
            'healthCrontaskCallout',
            'healthCrontaskCalloutTitle',
            'healthCrontaskCalloutText',
            'critical',
            'Há ações automáticas em risco',
            'Revise execução recente, frequência configurada e modo CLI.'
        );
    } else if (warning) {
        setHealthCallout(
            'healthCrontaskCallout',
            'healthCrontaskCalloutTitle',
            'healthCrontaskCalloutText',
            'warning',
            'Configuração recomendada pendente',
            'Uma ou mais tarefas divergem do padrão Ativa + CLI + 60s.'
        );
    } else {
        setHealthCallout(
            'healthCrontaskCallout',
            'healthCrontaskCalloutTitle',
            'healthCrontaskCalloutText',
            'ok',
            `Dono da fila: ${owner}`,
            owner === 'Dash'
                ? 'dashglpi_entitysmtp_queuednotification e slaticket estão executando dentro do esperado.'
                : 'queuednotification e slaticket estão executando dentro do esperado.'
        );
    }

    container.innerHTML = tasks.map((task) => {
        const severity = healthCrontaskSeverity(task);
        const executedFrequency = task.executed_frequency_seconds === null || typeof task.executed_frequency_seconds === 'undefined'
            ? '-'
            : formatDurationSecondsCompact(task.executed_frequency_seconds);
        const configuredFrequency = task.configured_frequency_seconds
            ? formatDurationSecondsCompact(task.configured_frequency_seconds)
            : '-';
        const lastRun = task.lastrun ? formatTimeOnly(task.lastrun) : 'Nunca';
        const summary = task.monitored === false
            ? (task.expected_state_label || 'Fora do dono atual')
            : !task.available
            ? 'Não encontrada'
            : task.stuck_running
                ? 'Possível travamento'
                : task.stale
                    ? 'Sem execução recente'
                    : task.drift_warning
                        ? 'Frequência executada divergente'
                        : (!task.recommendation_ok ? 'Fora da recomendação' : 'Operação estável');
        const stateLabel = task.monitored === false && task.expected_state_label
            ? task.expected_state_label
            : (task.state_label || 'N/D');
        const statusLabel = task.monitored === false
            ? 'Não monitorada agora'
            : (task.recommendation_ok ? 'OK' : 'Ajustar CLI/60s');

        return `
            <article class="health-crontask-card ${severity}">
                <div class="health-crontask-card-header">
                    <div>
                        <div class="health-crontask-name">${escHtml(task.key)}</div>
                        <div class="health-crontask-summary">${escHtml(summary)}</div>
                    </div>
                    <span class="health-state-pill ${severity}">${escHtml(stateLabel)}</span>
                </div>
                <div class="health-definition-list compact health-crontask-metrics">
                    <div><dt>Modo</dt><dd>${escHtml(task.mode_label || '-')}</dd></div>
                    <div><dt>Freq. Selecionada</dt><dd>${escHtml(configuredFrequency)}</dd></div>
                    <div><dt>Executada</dt><dd>${escHtml(executedFrequency)}</dd></div>
                    <div><dt>Exec. em:</dt><dd>${escHtml(lastRun)}</dd></div>
                    <div><dt>Lastcode</dt><dd>${escHtml(task.lastcode || '-')}</dd></div>
                    <div><dt>Status</dt><dd>${escHtml(statusLabel)}</dd></div>
                </div>
            </article>
        `;
    }).join('');
}

function renderHealthMail(mail) {
    setVal('healthMailMode', mail.smtp_mode_label || 'PHP');
    setVal('healthMailAdmin', mail.admin_email || '-');
    setVal('healthMailFrom', mail.from_email || '-');
    setVal('healthMailHostPort', [mail.smtp_host || '-', mail.smtp_port || '-'].join(' : '));
    setVal('healthMailUsername', mail.smtp_username || '-');
    setVal('healthMailPasswordFlag', healthFlagLabel(mail.smtp_password_configured, 'Configurada', 'Pendente'));
    setVal('healthMailOauthSecretFlag', healthFlagLabel(mail.smtp_oauth_client_secret_configured, 'Configurado', 'Pendente'));
    setVal('healthMailOauthRefreshFlag', healthFlagLabel(mail.smtp_oauth_refresh_token_configured, 'Configurado', 'Pendente'));

    const isPhpMode = String(mail.smtp_mode || '0') === '0';
    if (!isPhpMode && !mail.credentials_ok) {
        setHealthCallout(
            'healthMailCallout',
            'healthMailCalloutTitle',
            'healthMailCalloutText',
            'warning',
            'Configuração de envio incompleta',
            `Faltam itens obrigatórios: ${(mail.missing_parts || []).join(', ')}.`
        );
    } else if (!mail.sender_login_rule_ok) {
        setHealthCallout(
            'healthMailCallout',
            'healthMailCalloutTitle',
            'healthMailCalloutText',
            'warning',
            'Remetente diferente do login SMTP',
            'Ajuste o e-mail do remetente para usar o mesmo endereço do login SMTP.'
        );
    } else {
        setHealthCallout(
            'healthMailCallout',
            'healthMailCalloutTitle',
            'healthMailCalloutText',
            'ok',
            isPhpMode ? 'Envio via PHP' : 'Configuração de e-mail íntegra',
            isPhpMode
                ? 'O GLPI está usando o modo PHP para disparo de e-mails.'
                : 'Host, login e credenciais persistidas estão consistentes.'
        );
    }
}

function renderHealthNotificationConfig(notificationConfig) {
    setVal('healthNotificationActive', Number(notificationConfig.active_notifications || 0));
    setVal('healthNotificationTemplates', Number(notificationConfig.templates || 0));
    setVal('healthNotificationQueued', Number(notificationConfig.queued || 0));
    setVal(
        'healthNotificationEvent',
        notificationConfig.sla_reminder_event_available
            ? (notificationConfig.sla_reminder_event_label || notificationConfig.sla_reminder_event_key || 'Disponível')
            : 'Indisponível'
    );

    if (Number(notificationConfig.active_notifications || 0) === 0) {
        setHealthCallout(
            'healthNotificationCallout',
            'healthNotificationCalloutTitle',
            'healthNotificationCalloutText',
            'warning',
            'Nenhuma notificação ativa',
            'Revise a ativação das notificações antes de colocar o fluxo em produção.'
        );
    } else if (Number(notificationConfig.templates || 0) === 0 || !notificationConfig.sla_reminder_event_available) {
        setHealthCallout(
            'healthNotificationCallout',
            'healthNotificationCalloutTitle',
            'healthNotificationCalloutText',
            'warning',
            'Catálogo incompleto',
            'Faltam templates ou o evento especial de lembrete SLA não foi localizado.'
        );
    } else {
        setHealthCallout(
            'healthNotificationCallout',
            'healthNotificationCalloutTitle',
            'healthNotificationCalloutText',
            'ok',
            'Catálogo de notificações consistente',
            'Há notificações ativas, templates cadastrados e evento de SLA disponível.'
        );
    }
}

function renderHealthCollectors(collectors) {
    const summary = collectors.summary || {};
    const items = Array.isArray(collectors.items) ? collectors.items : [];
    const body = document.getElementById('healthCollectorsTableBody');

    setVal('healthCollectorsTotal', Number(summary.total || 0));
    setVal('healthCollectorsActive', Number(summary.active || 0));
    setVal('healthCollectorsErrors', Number(summary.errors || 0));

    if (Number(summary.errors || 0) > 0) {
        setHealthCallout(
            'healthCollectorCallout',
            'healthCollectorCalloutTitle',
            'healthCollectorCalloutText',
            'warning',
            'Coletores com falha',
            `${Number(summary.errors || 0)} coletor(es) apresentam erro e precisam de revisão.`
        );
    } else if (Number(summary.total || 0) > 0 && Number(summary.active || 0) === 0) {
        setHealthCallout(
            'healthCollectorCallout',
            'healthCollectorCalloutTitle',
            'healthCollectorCalloutText',
            'warning',
            'Coletores inativos',
            'Existem coletores cadastrados, mas nenhum está ativo no momento.'
        );
    } else {
        setHealthCallout(
            'healthCollectorCallout',
            'healthCollectorCalloutTitle',
            'healthCollectorCalloutText',
            'ok',
            'Coletores consistentes',
            items.length ? 'Os coletores cadastrados não exibem erro no snapshot atual.' : 'Nenhum coletor cadastrado nesta instalação.'
        );
    }

    if (!body) return;
    if (!items.length) {
        body.innerHTML = '<tr><td colspan="4" class="table-empty">Nenhum coletor encontrado.</td></tr>';
        return;
    }

    body.innerHTML = items.slice(0, 5).map((item) => `
        <tr>
            <td>${escHtml(item.name || '-')}</td>
            <td>${healthBadge(Number(item.is_active || 0) === 1 ? 'Ativo' : 'Inativo', Number(item.is_active || 0) === 1 ? 'success' : 'muted')}</td>
            <td>${healthBadge(String(Number(item.errors || 0)), Number(item.errors || 0) > 0 ? 'danger' : 'success')}</td>
            <td>${escHtml(formatDateTime(item.last_collect_date) || '-')}</td>
        </tr>
    `).join('');
}

function renderHealthCriticalTickets(tickets) {
    const summary = tickets.summary || {};
    const rows = Array.isArray(tickets.top_critical) ? tickets.top_critical : [];
    const body = document.getElementById('healthCriticalTicketsBody');

    setVal(
        'healthCriticalTicketsCount',
        `${Number(summary.critical || 0)} críticos • ${Number(summary.warning || 0)} em atenção • ${Number(summary.unassigned || 0)} sem atribuição`
    );

    if (!body) return;
    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="6" class="table-empty">Nenhum chamado crítico encontrado.</td></tr>';
        return;
    }

    body.innerHTML = rows.map((row) => {
        const assignment = [row.technician_name, row.group_name]
            .map((value) => String(value || '').trim())
            .filter((value) => value !== '' && value !== '-')
            .join(' / ') || 'Sem atribuição';
        const ticketUrl = row.ticket_url && row.ticket_url !== '#' ? row.ticket_url : '#';
        return `
            <tr>
                <td><strong>#${escHtml(row.id)}</strong></td>
                <td>
                    <div class="health-ticket-title">${escHtml(row.name || '-')}</div>
                    <div class="health-ticket-meta">${escHtml(row.entity_name || '-')} • ${escHtml(row.category || '-')}</div>
                </td>
                <td>${healthBadge(`${row.risk_label || 'Risco'} (${Number(row.risk_score || 0)})`, healthRiskBadgeClass(row.sla_status || 'warning'))}</td>
                <td>${escHtml(assignment)}</td>
                <td>${escHtml(`${row.active_sla_kind || '-'} • ${Math.round(Number(row.active_sla_percent || 0))}%`)}</td>
                <td>
                    <a class="page-action-btn page-action-btn--compact" href="${escHtml(ticketUrl)}" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Abrir</span>
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}

function renderHealthRecentEvents(recentEvents) {
    const body = document.getElementById('healthRecentEventsBody');
    const count = document.getElementById('healthRecentEventsCount');
    if (!body) return;

    if (!recentEvents.available) {
        if (count) count.textContent = 'Histórico indisponível';
        body.innerHTML = '<tr><td colspan="6" class="table-empty">Histórico indisponível nesta instalação.</td></tr>';
        return;
    }

    const items = Array.isArray(recentEvents.items) ? recentEvents.items : [];
    if (count) {
        count.textContent = `${items.length} evento(s)`;
    }

    if (!items.length) {
        body.innerHTML = '<tr><td colspan="6" class="table-empty">Nenhum evento recente encontrado.</td></tr>';
        return;
    }

    body.innerHTML = items.map((item) => `
        <tr>
            <td>${escHtml(item.task_name || '-')}</td>
            <td>${escHtml(formatDateTime(item.date) || '-')}</td>
            <td>${healthBadge(item.state_label || 'Info', healthEventStateClass(item.state))}</td>
            <td>${escHtml(formatExecutionDuration(item.elapsed))}</td>
            <td>${escHtml(Number(item.volume || 0))}</td>
            <td>${escHtml(item.content || '-')}</td>
        </tr>
    `).join('');
}

function setHealthCallout(wrapperId, titleId, textId, severity, title, text) {
    const wrapper = document.getElementById(wrapperId);
    const titleNode = document.getElementById(titleId);
    const textNode = document.getElementById(textId);
    if (wrapper) {
        wrapper.className = `health-callout ${severity}`;
    }
    if (titleNode) {
        titleNode.textContent = title;
    }
    if (textNode) {
        textNode.textContent = text;
    }
}

function healthCrontaskSeverity(task) {
    if (task?.monitored === false) {
        return 'ok';
    }
    if (!task?.available || task?.stuck_running || task?.stale) {
        return 'critical';
    }
    if (task?.drift_warning || !task?.recommendation_ok) {
        return 'warning';
    }
    return 'ok';
}

function healthFlagLabel(value, yesLabel, noLabel) {
    return value ? yesLabel : noLabel;
}

function healthBadge(label, type) {
    return `<span class="table-badge ${escHtml(type || 'primary')}">${escHtml(label || '')}</span>`;
}

function healthRiskBadgeClass(status) {
    return ({
        critical: 'danger',
        warning: 'warning',
        ok: 'success'
    })[String(status || 'warning')] || 'primary';
}

function healthEventStateClass(state) {
    if (Number(state) === 2) return 'success';
    if (Number(state) === 1) return 'danger';
    return 'primary';
}

function formatDurationSecondsCompact(value) {
    const seconds = Math.max(0, Math.round(Number(value) || 0));
    if (seconds <= 0) return '0 min';
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)} min`;

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.round((seconds % 3600) / 60);
    if (seconds < 86400) {
        return minutes > 0 ? `${hours}h ${minutes}min` : `${hours}h`;
    }

    const days = Math.floor(seconds / 86400);
    const remHours = Math.round((seconds % 86400) / 3600);
    return remHours > 0 ? `${days}d ${remHours}h` : `${days}d`;
}

function formatExecutionDuration(value) {
    const raw = Number(value || 0);
    if (raw <= 0) return '0s';
    if (raw < 1) return `${raw.toFixed(3)}s`;
    return formatDurationSecondsCompact(raw);
}

function renderRecentActivity(items) {
    const recentBody = document.getElementById('recent-tickets-body');
    if (!recentBody) return;

    const tickets = Array.isArray(items) ? items : [];
    if (tickets.length === 0) {
        recentBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">Nenhum chamado encontrado</td></tr>';
        return;
    }

    recentBody.innerHTML = tickets.map(ticket => `
        <tr>
            <td>
                <div class="table-status-cell">
                    <div class="table-status-icon ${getStatusClass(ticket.status)}">
                        <i class="fas ${getStatusIcon(ticket.status)}"></i>
                    </div>
                    <span class="table-status-label ${getStatusClass(ticket.status)}">${escHtml(ticket.status_label || getStatusLabel(ticket.status))}</span>
                </div>
            </td>
            <td>
                <div class="table-ticket-info">
                    <div class="table-ticket-title">${escHtml(ticket.name)}</div>
                    <div class="table-ticket-id">#${ticket.id}</div>
                </div>
            </td>
            <td>
                <span class="table-badge">${escHtml(ticket.category || 'Sem categoria')}</span>
            </td>
            <td>
                <span class="table-date">${escHtml(formatDateTime(ticket.date) || ticket.date || '-')}</span>
            </td>
            <td style="text-align: right;">
                ${pageAllowed('tickets') ? renderTicketReportAction(ticket.id) : '<span class="table-date">-</span>'}
            </td>
        </tr>
    `).join('');
}

// Função auxiliar para alinhar os meses (Evita desalinhamento se um mês tiver 0 dados)
function processMonthlyData(openedData, solvedData, periodDays = 30) {
    const labels = [];
    const dataOpened = [];
    const dataSolved = [];

    const today = new Date();
    const start = new Date(today);
    start.setDate(start.getDate() - Number(periodDays || 30));
    const months = [];
    const cursor = new Date(start.getFullYear(), start.getMonth(), 1);
    const last = new Date(today.getFullYear(), today.getMonth(), 1);

    while (cursor <= last) {
        months.push(new Date(cursor));
        cursor.setMonth(cursor.getMonth() + 1);
    }

    months.forEach(d => {
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const key = `${year}-${month}`;

        labels.push(`${month}/${year.toString().substring(2)}`);

        const foundOpen = openedData.find(item => item.mes_ano === key);
        dataOpened.push(foundOpen ? parseInt(foundOpen.total) : 0);

        const foundSolved = solvedData.find(item => item.mes_ano === key);
        dataSolved.push(foundSolved ? parseInt(foundSolved.total) : 0);
    });

    return { labels: labels, opened: dataOpened, solved: dataSolved };
}

function setVal(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
        element.classList.remove('skeleton');
    }
}

// ==================== CHARTS ====================
// Movido para public/js/script.charts.js (PLAN-20260703-013, Fase 3.3).

// ==================== LOAD TICKET LISTS ====================
// Objeto ITIL selecionado na tela de atendimento (PLAN-20260709-019, Fase C).
function ticketsItemtypeParam() {
    return DashState.ticketsItemtype || 'ticket';
}

function itilTypeInfo(key) {
    const catalog = (typeof DASHGLPI_ITIL_TYPES !== 'undefined' && Array.isArray(DASHGLPI_ITIL_TYPES))
        ? DASHGLPI_ITIL_TYPES
        : [];
    return catalog.find(entry => entry.key === key) || null;
}

async function loadTicketLists() {
    if (!pageAllowed('tickets') || !pageSectionExists('tickets')) {
        DashState.ticketsDataGlobal = [];
        return;
    }

    try {
        const response = await fetch(`${dashboardDataUrl('tickets_list')}&itemtype=${encodeURIComponent(ticketsItemtypeParam())}`);
        const tickets = await response.json();
        if (response.status === 401) {
            throw Object.assign(new Error('Sessão expirada.'), { requiresReload: true });
        }
        if (!response.ok) {
            throw new Error(tickets?.error || 'Erro ao carregar chamados.');
        }
        if (!Array.isArray(tickets)) {
            throw new Error('Resposta inválida ao carregar chamados.');
        }
        DashState.ticketsDataGlobal = Array.isArray(tickets) ? tickets : [];
        renderTicketsTable();
        if (!document.getElementById('ticketsKanban')?.hidden) renderKanbanBoard();
        sessionStorage.removeItem('dashglpi_tickets_reload_attempted');
    } catch (error) {
        console.error('Error loading tickets:', error);
        const fullBody = document.getElementById('tickets-full-body');
        const alreadyTried = sessionStorage.getItem('dashglpi_tickets_reload_attempted') === '1';

        // Falha de rede/CORS aqui costuma ser um proxy de borda (ex.: Akamai EAA) revalidando a
        // sessão antes de deixar a requisição chegar no backend — fetch() não consegue completar
        // esse desafio porque o redirect é cross-origin sem CORS, mas uma navegação completa
        // consegue. Tenta 1x por sessão de aba (sessionStorage evita loop infinito).
        if (!alreadyTried && error?.requiresReload) {
            sessionStorage.setItem('dashglpi_tickets_reload_attempted', '1');
            if (fullBody) {
                fullBody.innerHTML = `<tr><td colspan="${ticketTableColspan()}" class="table-empty">Sessão expirada, recarregando a página...</td></tr>`;
            }
            window.location.reload();
            return;
        }

        if (fullBody) {
            fullBody.innerHTML = `<tr><td colspan="${ticketTableColspan()}" class="table-empty">${escHtml(error.message || 'Erro ao carregar chamados')}</td></tr>`;
        }
    }
}

// ==================== PAGINAÇÃO GENÉRICA ====================
function renderPagination(containerEl, { totalItems, pageSize, currentPage, onPageChange }) {
    if (!containerEl) return;

    if (!totalItems) {
        containerEl.innerHTML = '';
        containerEl.hidden = true;
        return;
    }

    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    const page = Math.min(Math.max(1, currentPage), totalPages);

    containerEl.hidden = false;
    containerEl.innerHTML = `
        <label class="pagination-size">
            <span>Itens por página</span>
            <select id="ticketsPageSizeSelect" aria-label="Itens por página">
                ${TICKETS_PAGE_SIZE_OPTIONS.map(size => `<option value="${size}"${Number(size) === Number(pageSize) ? ' selected' : ''}>${size}</option>`).join('')}
            </select>
        </label>
        <button type="button" class="pagination-btn" data-page-action="prev"${page <= 1 ? ' disabled' : ''} title="Página anterior">
            <i class="fas fa-chevron-left"></i>
        </button>
        <span class="pagination-info">Página ${page} de ${totalPages}</span>
        <button type="button" class="pagination-btn" data-page-action="next"${page >= totalPages ? ' disabled' : ''} title="Próxima página">
            <i class="fas fa-chevron-right"></i>
        </button>
    `;

    containerEl.querySelectorAll('[data-page-action]').forEach(btn => {
        btn.addEventListener('click', () => {
            const nextPage = btn.getAttribute('data-page-action') === 'prev' ? page - 1 : page + 1;
            onPageChange(nextPage);
        });
    });

    containerEl.querySelector('#ticketsPageSizeSelect')?.addEventListener('change', (event) => {
        const value = Number(event.currentTarget.value) || TICKETS_PAGE_SIZE_DEFAULT;
        DashState.ticketsPaginationState.pageSize = TICKETS_PAGE_SIZE_OPTIONS.includes(value) ? value : TICKETS_PAGE_SIZE_DEFAULT;
        localStorage.setItem('dashglpi-tickets-page-size', String(DashState.ticketsPaginationState.pageSize));
        DashState.ticketsPaginationState.page = 1;
        renderTicketsTable();
    });
}

function ticketsPageSize() {
    const saved = Number(localStorage.getItem('dashglpi-tickets-page-size') || DashState.ticketsPaginationState.pageSize || TICKETS_PAGE_SIZE_DEFAULT);
    return TICKETS_PAGE_SIZE_OPTIONS.includes(saved) ? saved : TICKETS_PAGE_SIZE_DEFAULT;
}

function normalizeTicketStatusFilter(values) {
    const allowed = new Set(TICKET_STATUS_FILTER_ALL);
    const normalized = Array.from(values || [])
        .map(value => String(value || '').trim())
        .filter(value => allowed.has(value));
    return Array.from(new Set(normalized));
}

function loadTicketStatusFilter() {
    const raw = localStorage.getItem('dashglpi-tickets-status-filter');
    if (!raw) {
        DashState.ticketStatusFilter = TICKET_STATUS_FILTER_DEFAULT.slice();
        return;
    }

    try {
        const parsed = JSON.parse(raw);
        DashState.ticketStatusFilter = normalizeTicketStatusFilter(Array.isArray(parsed) ? parsed : TICKET_STATUS_FILTER_DEFAULT);
    } catch {
        DashState.ticketStatusFilter = TICKET_STATUS_FILTER_DEFAULT.slice();
    }
}

function saveTicketStatusFilter(values) {
    DashState.ticketStatusFilter = normalizeTicketStatusFilter(values);
    localStorage.setItem('dashglpi-tickets-status-filter', JSON.stringify(DashState.ticketStatusFilter));
}

function syncTicketStatusFilterControls() {
    const selected = new Set(DashState.ticketStatusFilter || []);
    document.querySelectorAll('[data-ticket-status-filter]').forEach(input => {
        input.checked = selected.has(String(input.value || ''));
    });

    const toggle = document.getElementById('ticketsStatusFilterToggle');
    if (toggle) {
        const labels = TICKET_STATUS_FILTER_ALL
            .filter(status => selected.has(status))
            .map(status => TICKET_STATUS_FILTER_LABELS[status])
            .filter(Boolean);
        toggle.classList.toggle('is-active', selected.size !== TICKET_STATUS_FILTER_DEFAULT.length || !TICKET_STATUS_FILTER_DEFAULT.every(status => selected.has(status)));
        toggle.title = labels.length ? `Status: ${labels.join(', ')}` : 'Nenhum status selecionado';
    }
}

function selectedTicketStatusValues() {
    return Array.from(document.querySelectorAll('[data-ticket-status-filter]:checked'))
        .map(input => String(input.value || ''));
}

function initTicketStatusFilter() {
    loadTicketStatusFilter();
    syncTicketStatusFilterControls();

    const toggle = document.getElementById('ticketsStatusFilterToggle');
    const menu = document.getElementById('ticketsStatusFilterMenu');
    if (toggle && menu) {
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const open = menu.hidden;
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', event => event.stopPropagation());
    }

    document.querySelectorAll('[data-ticket-status-filter]').forEach(input => {
        input.addEventListener('change', () => {
            saveTicketStatusFilter(selectedTicketStatusValues());
            syncTicketStatusFilterControls();
            DashState.ticketsPaginationState.page = 1;
            renderTicketsTable();
        });
    });

    document.getElementById('ticketsStatusFilterAll')?.addEventListener('click', () => {
        saveTicketStatusFilter(TICKET_STATUS_FILTER_ALL);
        syncTicketStatusFilterControls();
        DashState.ticketsPaginationState.page = 1;
        renderTicketsTable();
    });

    document.addEventListener('click', () => {
        const currentMenu = document.getElementById('ticketsStatusFilterMenu');
        const currentToggle = document.getElementById('ticketsStatusFilterToggle');
        if (!currentMenu || currentMenu.hidden) return;
        currentMenu.hidden = true;
        currentToggle?.setAttribute('aria-expanded', 'false');
    });
}

function initTicketSearchAndSort() {
    initTicketStatusFilter();

    document.querySelectorAll('[data-tickets-itemtype]').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-tickets-itemtype') || 'ticket';
            if (key === ticketsItemtypeParam()) return;
            DashState.ticketsItemtype = key;
            document.querySelectorAll('[data-tickets-itemtype]').forEach(b => {
                const active = b === btn;
                b.classList.toggle('active', active);
                b.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            DashState.ticketsPaginationState.page = 1;
            loadTicketLists();
        });
    });

    const input = document.getElementById('ticketsSearchInput');
    const mobileInput = document.getElementById('ticketsSearchInputMobile');
    const kanbanInput = document.getElementById('ticketsKanbanSearch');
    if (input) {
        input.addEventListener('input', () => {
            if (mobileInput) mobileInput.value = input.value;
            DashState.ticketsPaginationState.page = 1;
            renderTicketsTable();
            if (!document.getElementById('ticketsKanban')?.hidden) renderKanbanBoard();
        });
    }
    if (mobileInput) {
        mobileInput.addEventListener('input', () => {
            if (input) input.value = mobileInput.value;
            DashState.ticketsPaginationState.page = 1;
            renderTicketsTable();
            if (!document.getElementById('ticketsKanban')?.hidden) renderKanbanBoard();
        });
    }
    if (kanbanInput) {
        kanbanInput.addEventListener('input', () => {
            renderKanbanBoard();
        });
    }

    window.addEventListener('resize', () => {
        if (currentVisiblePageId() !== 'tickets') return;
        renderTicketsTable();
        if (!isMobileViewport() && DashState.ticketsView === 'kanban') renderKanbanBoard();
    });

    document.querySelector('[data-mobile-ticket-search]')?.addEventListener('click', () => {
        const section = document.getElementById('ticketsSection');
        const panel = document.getElementById('ticketsMobileSearchPanel');
        const button = document.querySelector('[data-mobile-ticket-search]');
        if (!section || !panel || !button) return;
        const open = panel.hidden;
        panel.hidden = !open;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        section.classList.toggle('mobile-search-open', open);
        if (open) mobileInput?.focus();
    });
    document.querySelectorAll('#ticketsSection .sortable-th').forEach(th => {
        th.addEventListener('click', () => {
            const key = th.getAttribute('data-sort');
            if (!key) return;
            if (DashState.ticketsSortState.key === key) {
                DashState.ticketsSortState.direction = DashState.ticketsSortState.direction === 'asc' ? 'desc' : 'asc';
            } else {
                DashState.ticketsSortState = { key, direction: 'asc' };
            }
            renderTicketsTable();
        });
    });
}

function syncTicketsView() {
    const mobile = isMobileViewport();
    const table = document.querySelector('#ticketsSection .table-responsive');
    const cards = document.getElementById('ticketsCards');
    const view = 'list';

    if (table) table.hidden = !mobile && view === 'kanban';
    if (cards) cards.hidden = !mobile;

}

function renderTicketsTable() {
    const fullBody = document.getElementById('tickets-full-body');
    if (!fullBody) return;

    syncTicketsView();

    const search = (document.getElementById('ticketsSearchInputMobile')?.value || document.getElementById('ticketsSearchInput')?.value || '').trim().toLowerCase();
    const selectedStatuses = new Set(DashState.ticketStatusFilter || TICKET_STATUS_FILTER_DEFAULT);
    const filtered = DashState.ticketsDataGlobal.filter(ticket => {
        if (!selectedStatuses.has(String(ticket.status || ''))) {
            return false;
        }
        if (!search) return true;
        return [
            ticket.id,
            ticket.name,
            ticket.category,
            ticket.technician_name,
            ticket.requester_name,
            ticket.stage,
            getStatusLabel(ticket.status),
            ticket.date,
            ticket.time_to_resolve
        ].some(value => String(value || '').toLowerCase().includes(search));
    });

    filtered.sort((a, b) => compareTicketValues(a, b, DashState.ticketsSortState.key, DashState.ticketsSortState.direction));
    updateTicketSortIcons();

    const count = document.getElementById('ticketsCount');
    if (count) {
        const typeInfo = itilTypeInfo(ticketsItemtypeParam());
        const unitLabel = typeInfo ? String(typeInfo.label_plural || 'chamados').toLowerCase() : 'chamados';
        count.textContent = `${filtered.length} de ${DashState.ticketsDataGlobal.length} ${unitLabel}`;
    }
    const mobileCount = document.getElementById('ticketsMobileCount');
    if (mobileCount) mobileCount.textContent = filtered.length;

    if (filtered.length === 0) {
        fullBody.innerHTML = `<tr><td colspan="${ticketTableColspan()}" class="table-empty">Nenhum chamado encontrado</td></tr>`;
        renderTicketsCards([]);
        renderPagination(document.getElementById('ticketsPagination'), { totalItems: 0, pageSize: ticketsPageSize(), currentPage: 1, onPageChange: () => {} });
        return;
    }

    const pageSize = ticketsPageSize();
    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    DashState.ticketsPaginationState.page = Math.min(Math.max(1, DashState.ticketsPaginationState.page), totalPages);
    const startIndex = (DashState.ticketsPaginationState.page - 1) * pageSize;
    const pageItems = filtered.slice(startIndex, startIndex + pageSize);
    renderTicketsCards(pageItems);

    renderPagination(document.getElementById('ticketsPagination'), {
        totalItems: filtered.length,
        pageSize,
        currentPage: DashState.ticketsPaginationState.page,
        onPageChange: (nextPage) => {
            DashState.ticketsPaginationState.page = nextPage;
            renderTicketsTable();
        }
    });

    fullBody.innerHTML = renderHelpdeskTicketsRows(pageItems);
    initHelpdeskTicketRowToggles();
}

function renderHelpdeskTicketsRows(tickets) {
    return tickets.map(ticket => {
        const ticketId = Number(ticket.id);
        const actions = [
            Number(ticket.readonly) === 1 ? '' : renderSelfServiceActions(ticket),
            renderTicketReportAction(ticketId, ticket.itemtype || 'ticket'),
        ].join('');
        return `
        <tr data-ticket-detail="${ticketId}" data-itemtype="${escHtml(ticket.itemtype || 'ticket')}" class="is-clickable ticket-helpdesk-main-row" aria-controls="ticket-helpdesk-extra-${ticketId}">
            <td>
                <div class="ticket-helpdesk-id">
                    <button type="button" class="ticket-helpdesk-expand" data-ticket-extra-toggle="${ticketId}" aria-expanded="false" aria-controls="ticket-helpdesk-extra-${ticketId}" title="Mostrar detalhes">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </button>
                    <strong>#${ticketId}</strong>
                </div>
            </td>
            <td>
                <div class="table-ticket-info">
                    <div class="table-ticket-title">
                        ${escHtml(ticket.name)}
                        ${Number(ticket.notification_failed) === 1 ? '<i class="fas fa-triangle-exclamation notification-failure-icon" title="Falha no envio da notificacao"></i>' : ''}
                    </div>
                    <div class="table-ticket-id">${escHtml(ticket.category || 'Sem categoria')}</div>
                </div>
            </td>
            <td data-label="Status" class="ticket-stage-cell">${renderTicketStage(ticket)}</td>
            <td>
                <div class="table-action-group">
                    ${actions || '<span class="table-date">-</span>'}
                </div>
            </td>
        </tr>
        <tr class="ticket-helpdesk-extra-row" id="ticket-helpdesk-extra-${ticketId}" hidden>
            <td colspan="4">
                <div class="ticket-helpdesk-extra-grid">
                    <div><span>Tecnico</span><strong>${escHtml(ticket.technician_name || '-')}</strong></div>
                    <div><span>Requerente</span><strong>${escHtml(ticket.requester_name || '-')}</strong></div>
                    <div><span>Criado em</span><strong>${escHtml(formatDateTime(ticket.date) || '-')}</strong></div>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function initHelpdeskTicketRowToggles() {
    document.querySelectorAll('[data-ticket-extra-toggle]').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            const ticketId = button.getAttribute('data-ticket-extra-toggle');
            const detailRow = document.getElementById(`ticket-helpdesk-extra-${ticketId}`);
            if (!detailRow) return;
            const expanded = detailRow.hidden;
            detailRow.hidden = !expanded;
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.classList.toggle('is-expanded', expanded);
        });
    });
}
function renderTicketsCards(tickets) {
    // Lista de cards acionável para mobile (PLAN-20260831-001).
    const cards = document.getElementById('ticketsCards');
    if (!cards) return;
    cards.hidden = !isMobileViewport();
    if (!isMobileViewport()) return;
    if (!tickets.length) {
        cards.innerHTML = '<div class="tickets-card-empty">Nenhum chamado encontrado</div>';
        return;
    }

    cards.innerHTML = tickets.map((ticket, index) => {
        const readonly = Number(ticket.readonly) === 1;
        const canTake = Number(ticket.can_take) === 1 && !readonly;
        const risk = ticket.sla_status === 'critical' || Number(ticket.active_sla_overdue) === 1 ? 'critical' : (ticket.sla_status === 'warning' ? 'warning' : 'normal');
        const slaOk = ticket.sla_status === 'ok' && Number(ticket.active_sla_overdue) !== 1;
        const slaClass = slaOk ? 'is-ok' : 'is-not-ok';
        const slaLabel = Number(ticket.active_sla_overdue) === 1 ? 'SLA estourado' : (ticket.active_sla_kind ? `${ticket.active_sla_kind} ${formatSlaRemainingHhMm(ticket.seconds_left)}` : 'SLA ok');
        const context = [ticket.technician_name !== '-' ? ticket.technician_name : 'Sem técnico', ticket.category || 'Sem categoria'].join(' · ');
        const separator = index > 0 ? '<div class="ticket-mobile-card-separator" role="separator" aria-hidden="true"></div>' : '';
        return `${separator}<article class="ticket-mobile-card is-${risk}" data-ticket-detail="${Number(ticket.id)}" data-itemtype="${escHtml(ticket.itemtype || 'ticket')}">
            <div class="ticket-mobile-card-top">
                <span class="ticket-mobile-id">#${Number(ticket.id)}</span>
                <span class="ticket-mobile-chip status">${escHtml(ticket.status_label || ticket.stage || '')}</span>
                <span class="ticket-mobile-chip priority">${escHtml(ticket.priority_label || `Prioridade ${Number(ticket.priority) || 0}`)}</span>
                <span class="ticket-mobile-sla ${slaClass}"><i class="fas fa-clock" aria-hidden="true"></i> ${escHtml(slaLabel)}</span>
            </div>
            ${Number(ticket.notification_failed) === 1 ? '<div class="ticket-mobile-alert"><i class="fas fa-bell" aria-hidden="true"></i> Alerta ativo</div>' : ''}
            <h2 class="ticket-mobile-title">${escHtml(ticket.name || '')}</h2>
            <p class="ticket-mobile-description">${escHtml(ticket.description_excerpt || 'Sem descrição disponível.')}</p>
            <div class="ticket-mobile-context"><i class="fas fa-user" aria-hidden="true"></i><span>${escHtml(context)}</span></div>
            <div class="ticket-mobile-meta">
                <span><i class="fas fa-clock" aria-hidden="true"></i> ${escHtml(formatDateTime(ticket.date) || '-')}</span>
                <span><i class="fas fa-comments" aria-hidden="true"></i> ${Number(ticket.followups_count) || 0}</span>
                ${Number(ticket.attachments_count) > 0 ? `<span><i class="fas fa-paperclip" aria-hidden="true"></i> ${Number(ticket.attachments_count)}</span>` : ''}
            </div>
            <div class="ticket-mobile-actions">
                ${readonly ? '' : `<button type="button" class="ticket-mobile-photo" data-mobile-photo-ticket="${Number(ticket.id)}"><i class="fas fa-camera" aria-hidden="true"></i> Foto</button>`}
                ${canTake ? `<button type="button" class="ticket-mobile-take" title="Atribuir o chamado a ${escHtml(typeof DASHGLPI_CURRENT_USER_DISPLAY !== 'undefined' ? DASHGLPI_CURRENT_USER_DISPLAY : 'mim')}" aria-label="Assumir chamado #${Number(ticket.id)} para ${escHtml(typeof DASHGLPI_CURRENT_USER_DISPLAY !== 'undefined' ? DASHGLPI_CURRENT_USER_DISPLAY : 'mim')}" data-mobile-take-ticket="${Number(ticket.id)}"><i class="fas fa-hand-pointer" aria-hidden="true"></i> Assumir para mim</button>` : '<span class="ticket-mobile-assigned">' + escHtml(ticket.technician_name || 'Somente leitura') + '</span>'}
                <button type="button" class="ticket-mobile-detail" data-mobile-open-ticket="${Number(ticket.id)}" aria-label="Abrir chamado #${Number(ticket.id)}"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            </div>
        </article>`;
    }).join('');

    cards.querySelectorAll('[data-mobile-take-ticket]').forEach(button => {
        button.addEventListener('click', async event => {
            event.stopPropagation();
            const ticketId = Number(button.dataset.mobileTakeTicket);
            button.disabled = true;
            button.classList.add('is-loading');
            try {
                const data = await dashglpiAttendanceQuickUpdate(ticketId, { action: 'take' });
                if (!data.ok) throw new Error(data.error || 'Não foi possível assumir o chamado.');
                await loadTicketLists();
            } catch (error) {
                button.disabled = false;
                button.classList.remove('is-loading');
                alert(error.message);
            }
        });
    });
    cards.querySelectorAll('[data-mobile-open-ticket]').forEach(button => {
        button.addEventListener('click', event => {
            event.stopPropagation();
            openTicketDetailModal(Number(button.dataset.mobileOpenTicket));
        });
    });
    cards.querySelectorAll('[data-mobile-photo-ticket]').forEach(button => {
        button.addEventListener('click', event => {
            event.stopPropagation();
            openTicketDetailModal(Number(button.dataset.mobilePhotoTicket), { focus: 'followup' });
        });
    });
}

function ticketTableColspan() {
    return 4;
}

function renderSelfServiceActions(ticket) {
    const status = Number(ticket.status);
    const parts = [
        `<button type="button" class="table-action" data-followup-ticket="${ticket.id}" title="Adicionar Acompanhamento"><i class="fas fa-comment-medical"></i></button>`,
    ];

    if (status !== 6) {
        parts.push(`<button type="button" class="table-action" data-cancel-ticket="${ticket.id}" title="Cancelar Chamado"><i class="fas fa-ban"></i></button>`);
    }
    if (status === 5) {
        parts.push(`<button type="button" class="table-action" data-solution-ticket="${ticket.id}" title="Validar a Solução"><i class="fas fa-clipboard-check"></i></button>`);
    }
    if (status === 6 && Number(ticket.satisfaction_pending) === 1) {
        parts.push(`<button type="button" class="table-action" data-satisfaction-ticket="${ticket.id}" title="Responder Pesquisa de Satisfação"><i class="fas fa-star"></i></button>`);
    }

    return parts.join('');
}

function renderTicketReportAction(ticketId, itemtype = 'ticket') {
    if (!ticketReportsEnabled()) {
        return '';
    }

    const typeQuery = itemtype && itemtype !== 'ticket' ? `&itemtype=${encodeURIComponent(itemtype)}` : '';
    return `
        <a class="table-action report-action" href="/front/ticket-report.php?id=${encodeURIComponent(ticketId)}${typeQuery}" target="_blank" rel="noopener" title="Relatório de Auditoria" aria-label="Relatório de Auditoria do registro #${escHtml(ticketId)}">
            <i class="fas fa-file-alt"></i>
        </a>
    `;
}

function compareTicketValues(a, b, key, direction) {
    const factor = direction === 'asc' ? 1 : -1;
    let av = a[key];
    let bv = b[key];

    if (key === 'id' || key === 'stage_step' || key === 'status') {
        return ((Number(av) || 0) - (Number(bv) || 0)) * factor;
    }

    if (key === 'date' || key === 'time_to_resolve') {
        return (parseDateValue(av) - parseDateValue(bv)) * factor;
    }

    return String(av || '').localeCompare(String(bv || ''), 'pt-BR', { sensitivity: 'base' }) * factor;
}

function updateTicketSortIcons() {
    document.querySelectorAll('#ticketsSection .sortable-th').forEach(th => {
        const icon = th.querySelector('i');
        if (!icon) return;
        const active = th.getAttribute('data-sort') === DashState.ticketsSortState.key;
        icon.className = `fas ${active ? (DashState.ticketsSortState.direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'}`;
    });
}

function renderTicketStage(ticket) {
    const step = Number(ticket.stage_step) || 0;
    const columns = (DASHGLPI_IS_RESTRICTED_VIEW && !DASHGLPI_IS_HELPDESK_VIEW)
        ? KANBAN_COLUMNS.filter(col => col.id !== 'fechado')
        : KANBAN_COLUMNS;
    const total = columns.length;
    const effectiveStep = Math.min(step, total);
    const fillPercent = total > 1 ? (effectiveStep - 1) * 100 / total : 0;

    const points = columns.map((col, index) => {
        const number = index + 1;
        const state = number < step ? 'done' : number === step ? 'active' : 'pending';
        let icon = state === 'pending' ? 'fa-circle' : 'fa-check';
        if (state === 'active') {
            if (col.id === 'planejado') icon = 'fa-calendar';
            else if (col.id === 'pendente') icon = 'fa-exclamation';
        }
        return `
            <div class="ticket-stage-point ${state}">
                <span><i class="fas ${icon}"></i></span>
                <small>${escHtml(col.label)}</small>
            </div>
        `;
    }).join('');

    const currentIndex = Math.max(0, effectiveStep - 1);
    const currentColumn = columns[currentIndex] || columns[0];
    const chipColor = currentColumn.color === 'muted' ? 'var(--text-muted)' : `var(--${currentColumn.color})`;
    const chip = `
        <span class="ticket-stage-chip" style="--chip-color:${chipColor};">
            <i class="fas fa-circle"></i>
            ${escHtml(currentColumn.label)}
        </span>
    `;

    return `
        ${chip}
        <div class="ticket-stage" style="--stage-count:${total}; --stage-fill:${fillPercent}%;">${points}</div>
    `;
}

// ==================== KANBAN VIEW ====================
// Movido para public/js/script.kanban.js (PLAN-20260703-013, Fase 3.3).

// ==================== SELF-SERVICE ACTIONS (Helpdesk) ====================
// Movido para public/js/script.self-service.js (PLAN-20260703-013, Fase 3.3).

// ==================== CYBERPUNK ASSETS & MODAL ====================
async function loadFullAssets() {
    const gridContainer = document.getElementById('assets-grid');
    if (!pageAllowed('assets') || !gridContainer) return;

    try {
        const response = await fetch(PLUGIN_ROOT + '/ajax/dashboard.php?action=assets_list');
        DashState.assetsDataGlobal = await response.json(); // Salva na variável global

        if (!DashState.assetsDataGlobal || DashState.assetsDataGlobal.length === 0) {
            gridContainer.innerHTML = '<div class="text-center p-5 text-muted">Nenhum ativo encontrado no sistema.</div>';
            return;
        }

        gridContainer.innerHTML = DashState.assetsDataGlobal.map((asset, index) => {
            const name = (asset.name || '').toLowerCase();
            const model = (asset.model || '').toLowerCase();

            // Ícone base
            let typeIcon = 'fa-desktop';
            if (name.includes('srv') || name.includes('server')) typeIcon = 'fa-server';
            if (name.includes('nb') || name.includes('laptop') || model.includes('latitude')) typeIcon = 'fa-laptop';

            // Cor de status
            const statusColor = (asset.status === 'Em manutenção') ? '#ffee00' : '#00f3ff';

            // --- LÓGICA DE DISCO PARA O GRID (CORRIGIDA) ---
            let diskHtml = '';

            // Converte para float e garante que é número (se falhar vira 0)
            const diskTotal = parseFloat(asset.disk_total) || 0;
            const diskFree = parseFloat(asset.disk_free) || 0;

            if (diskTotal > 0) {
                const used = diskTotal - diskFree;
                // Evita divisão por zero
                const percent = Math.min((used / diskTotal) * 100, 100).toFixed(0);

                let barColor = 'var(--neon-blue)';
                if(percent > 80) barColor = 'var(--warning)';
                if(percent > 90) barColor = 'var(--danger)';

                diskHtml = `
                    <div style="margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: rgba(255,255,255,0.7); font-family: 'Share Tech Mono';">
                            <span>STORAGE</span>
                            <span>${percent}%</span>
                        </div>
                        <div style="width: 100%; height: 4px; background: rgba(255,255,255,0.1); margin-top: 2px;">
                            <div style="width: ${percent}%; height: 100%; background: ${barColor}; box-shadow: 0 0 5px ${barColor};"></div>
                        </div>
                    </div>
                `;
            } else {
                // Caso não tenha disco (evita o NaN)
                diskHtml = `<div style="margin-top: 10px; font-size: 0.7rem; color: rgba(255,255,255,0.3); font-family: 'Share Tech Mono';">NO DISK DATA</div>`;
            }

            return `
                <div class="asset-card-cyber" onclick="openCyberModal(${index})">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <div class="cyber-card-title">${escHtml(asset.name)}</div>
                            <div class="cyber-card-meta">
                                <i class="fas ${typeIcon}"></i> ${escHtml(asset.model || 'Unknown Unit')}
                            </div>
                        </div>
                        <div style="width: 10px; height: 10px; background: ${statusColor}; border-radius: 50%; box-shadow: 0 0 8px ${statusColor};"></div>
                    </div>

                    ${diskHtml}

                    <div style="margin-top: 10px; font-family: 'Share Tech Mono'; font-size: 0.7rem; color: rgba(255,255,255,0.5); text-align: right;">
                        :: CLICK DETALHES ::
                    </div>
                </div>
            `;
        }).join('');

    } catch (error) {
        console.error('Error loading assets:', error);
        gridContainer.innerHTML = '<div class="text-center p-5 text-danger">Erro ao carregar lista de ativos.</div>';
    }
}

// Função de abrir o Modal (Chamada pelo onclick do HTML gerado acima)
function openCyberModal(index) {
    const asset = DashState.assetsDataGlobal[index];
    if (!asset) return;

    // Preenche IDs básicos
    document.getElementById('modal-id').innerText = (asset.id || '0').toString().padStart(3, '0');
    document.getElementById('modal-name').innerText = asset.name || 'UNKNOWN';
    document.getElementById('modal-sub').innerText = (asset.model || 'GENERIC HARDWARE').toUpperCase();
    document.getElementById('modal-status').innerText = (asset.status || 'ONLINE').toUpperCase();
    document.getElementById('modal-loc').innerText = (asset.location || 'UNKNOWN SECTOR').toUpperCase();

    // OS com Ícone
    const osName = (asset.os_name || 'NO OS DETECTED');
    let osIcon = 'fa-microchip';
    if(osName.toLowerCase().includes('windows')) osIcon = 'fa-windows';
    if(osName.toLowerCase().includes('linux')) osIcon = 'fa-linux';
    if(osName.toLowerCase().includes('apple') || osName.toLowerCase().includes('mac')) osIcon = 'fa-apple';
    document.getElementById('modal-os').innerHTML = `<i class="fab ${osIcon}"></i> ${escHtml(osName)}`;

    // Hardware
    const cpuName = (asset.cpu || 'Generic Processor').replace(/Intel|AMD|Core|Ryzen/gi, '').trim();
    document.getElementById('modal-cpu').innerText = cpuName.substring(0, 20); // Limita tamanho
    document.getElementById('modal-serial').innerText = asset.serial || 'NO-SERIAL-KEY';

    // --- LÓGICA DE DISCO PARA O MODAL (CORRIGIDA) ---
    // parseFloat garante número, || 0 garante que não seja NaN
    const diskTotalMB = parseFloat(asset.disk_total) || 0;
    const diskFreeMB = parseFloat(asset.disk_free) || 0;
    const diskUsedMB = diskTotalMB - diskFreeMB;

    let hddLabel = 'SEM DADOS DE DISCO';
    let hddPercent = 0;

    if (diskTotalMB > 0) {
        // Converte para GB
        const totalGB = (diskTotalMB / 1024).toFixed(0);
        const freeGB = (diskFreeMB / 1024).toFixed(0);

        hddLabel = `${freeGB} GB LIVRES / ${totalGB} GB TOTAL`;
        hddPercent = (diskUsedMB / diskTotalMB) * 100;
    }

    // RAM (Mantém lógica anterior)
    const ramVal = asset.ram_total ? (parseInt(asset.ram_total)/1024).toFixed(0) : 0;
    document.getElementById('modal-ram').innerText = ramVal > 0 ? ramVal + ' GB INSTALADO' : 'N/A';

    // Atualiza Texto do HDD no Modal
    document.getElementById('modal-hdd').innerText = hddLabel;

    // Cálculo visual das barras
    const ramPercent = Math.min((ramVal / 32) * 100, 100) || 10;

    // Exibir Modal
    const modal = document.getElementById('cyber-modal');
    modal.classList.add('active');

    // Animação CSS das barras
    setTimeout(() => {
        document.getElementById('bar-ram').style.width = ramPercent + '%';

        // Barra do HD reflete a PORCENTAGEM DE USO (Quanto maior, mais cheio)
        const hddBar = document.getElementById('bar-hdd');
        hddBar.style.width = hddPercent + '%';

        // Muda cor se estiver cheio
        if(hddPercent > 90) {
            hddBar.style.background = 'var(--danger)';
            hddBar.style.boxShadow = '0 0 10px var(--danger)';
        } else {
            hddBar.style.background = 'linear-gradient(90deg, var(--neon-blue), var(--neon-pink))';
            hddBar.style.boxShadow = '0 0 10px var(--neon-blue)';
        }

    }, 100);
}

function closeCyberModal() {
    const modal = document.getElementById('cyber-modal');
    modal.classList.remove('active');

    // Reseta barras para próxima animação
    document.getElementById('bar-ram').style.width = '0%';
    document.getElementById('bar-hdd').style.width = '0%';
}

// Fechar ao clicar fora (Overlay)
document.addEventListener('click', (e) => {
    if (e.target.id === 'cyber-modal') {
        closeCyberModal();
    }
});

// ==================== SLA MONITOR ====================
// Movido para public/js/script.sla-monitor.js (PLAN-20260703-013, Fase 3.3).

// ==================== ADMIN REGISTERS ====================
// Movido para public/js/script.admin.js (PLAN-20260703-013, Fase 3.2).

function openChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    const form = document.getElementById('changePasswordForm');
    const status = document.getElementById('changePasswordStatus');
    if (!modal) return;
    if (form) {
        form.reset();
        resetChangePasswordVisibility(form);
    }
    if (status) {
        status.textContent = '';
        status.className = 'admin-status';
    }
    modal.hidden = false;
    setTimeout(() => form?.querySelector('input[name="current_password"]')?.focus(), 50);
}

function closeChangePasswordModal() {
    const modal = document.getElementById('changePasswordModal');
    if (modal) modal.hidden = true;
}

function initChangePasswordForm() {
    const form = document.getElementById('changePasswordForm');
    if (!form) return;

    form.querySelectorAll('[data-change-password-toggle]').forEach(button => {
        button.addEventListener('click', () => toggleChangePasswordVisibility(button));
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const status = document.getElementById('changePasswordStatus');
        const submit = form.querySelector('button[type="submit"]');
        if (status) {
            status.textContent = 'Salvando...';
            status.className = 'admin-status';
        }
        if (submit) submit.disabled = true;

        try {
            const data = await dashglpiPostForm(`${PLUGIN_ROOT}/ajax/change_password.php`, {
                current_password: form.current_password.value,
                new_password: form.new_password.value,
                confirm_password: form.confirm_password.value,
            }, 'Nao foi possivel alterar a senha.');
            if (!data.ok) throw new Error(data.error || 'Nao foi possivel alterar a senha.');
            if (status) {
                status.textContent = data.message || 'Senha alterada com sucesso.';
                status.className = 'admin-status success';
            }
            form.reset();
            setTimeout(closeChangePasswordModal, 900);
        } catch (error) {
            if (status) {
                status.textContent = error.message || 'Nao foi possivel alterar a senha.';
                status.className = 'admin-status error';
            }
        } finally {
            if (submit) submit.disabled = false;
        }
    });
}

function toggleChangePasswordVisibility(button) {
    const control = button.closest('.admin-password-control');
    const input = control?.querySelector('input');
    const icon = button.querySelector('i');
    if (!input) return;

    const willShow = input.type === 'password';
    input.type = willShow ? 'text' : 'password';
    button.title = willShow ? 'Ocultar senha' : 'Mostrar senha';
    button.setAttribute('aria-pressed', willShow ? 'true' : 'false');
    if (icon) icon.className = willShow ? 'fas fa-eye-slash' : 'fas fa-eye';
}

function resetChangePasswordVisibility(form) {
    form.querySelectorAll('.admin-password-control').forEach(control => {
        const input = control.querySelector('input');
        const button = control.querySelector('[data-change-password-toggle]');
        const icon = button?.querySelector('i');
        if (input) input.type = 'password';
        if (button) {
            button.title = 'Mostrar senha';
            button.setAttribute('aria-pressed', 'false');
        }
        if (icon) icon.className = 'fas fa-eye';
    });
}
// ==================== NOTIFICATIONS ====================
function initNotifications() {
    renderDashboardNotifications([]);
    initPushNotifications();
}

function renderDashboardNotifications(notifications) {
    DashState.dashboardNotificationsGlobal = Array.isArray(notifications)
        ? notifications.filter(item => !DashState.dismissedNotificationIds.has(String(item.id || '')))
        : [];
    DashState.notificationCount = DashState.dashboardNotificationsGlobal.length;
    updateNotificationBadge();
    renderNotifications(DashState.dashboardNotificationsGlobal);
}

function updateNotificationBadge() {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;
    badge.textContent = String(DashState.notificationCount);
    badge.hidden = DashState.notificationCount <= 0;
}

function renderNotifications(notifications) {
    const list = document.getElementById('notificationList');

    if (notifications.length === 0) {
        list.innerHTML = '<p style="text-align: center; padding: 40px; color: var(--text-muted);">Nenhum alerta operacional no período</p>';
        return;
    }

    list.innerHTML = notifications.map(n => `
                <div class="notification-item ${n.unread ? 'unread' : ''}">
                    <div class="notification-item-header">
                        <div class="notification-icon ${n.type}">
                            <i class="fas ${getNotificationIcon(n.type)}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-text">${escHtml(n.text || '')}</div>
                            <div class="notification-time">${escHtml(n.time || '')}</div>
                        </div>
                    </div>
                </div>
            `).join('');
}

function getNotificationIcon(type) {
    const icons = {
        info: 'fa-info-circle',
        success: 'fa-check-circle',
        warning: 'fa-exclamation-triangle',
        danger: 'fa-exclamation-circle'
    };
    return icons[type] || 'fa-bell';
}

function toggleNotifications() {
    const panel = document.getElementById('notificationPanel');
    panel.classList.toggle('show');
}

function clearNotifications() {
    DashState.dashboardNotificationsGlobal.forEach(item => DashState.dismissedNotificationIds.add(String(item.id || '')));
    renderDashboardNotifications([]);
}

function requestNotificationPermission() {
    return togglePushNotifications();
}

function pushActionButtons() {
    return [...document.querySelectorAll('#pushNotificationsBtn, #mobilePushNotifications')];
}

function setPushButtonState(state, message = '') {
    pushActionButtons().forEach((button) => {
        button.classList.toggle('is-active', state === 'active');
        button.classList.toggle('is-disabled', state === 'unsupported' || state === 'unavailable');
        button.setAttribute('aria-pressed', state === 'active' ? 'true' : 'false');
        if (state === 'active') {
            button.title = 'Desativar notificações Push';
            button.setAttribute('aria-label', 'Desativar notificações Push');
            const label = button.querySelector('span');
            if (label) label.textContent = 'Ativo';
        } else if (message !== '') {
            button.title = message;
            button.setAttribute('aria-label', message);
        }
    });
}

function pushServiceWorkerRegistration() {
    if (window.DASHGLPI_SERVICE_WORKER_REGISTRATION) {
        return window.DASHGLPI_SERVICE_WORKER_REGISTRATION;
    }
    return navigator.serviceWorker.ready;
}

function pushBase64ToUint8Array(value) {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    return Uint8Array.from([...rawData].map(character => character.charCodeAt(0)));
}

async function initPushNotifications() {
    const buttons = pushActionButtons();
    if (!buttons.length) return;
    if (typeof DASHGLPI_PUSH_ENABLED === 'undefined' || !DASHGLPI_PUSH_ENABLED) {
        setPushButtonState('unavailable', 'Push não configurado no servidor');
        return;
    }
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        setPushButtonState('unsupported', 'Este navegador não suporta Push');
        return;
    }

    try {
        const response = await fetch(`${PLUGIN_ROOT}/ajax/push.php?action=status`, { headers: { Accept: 'application/json' } });
        const status = await response.json();
        setPushButtonState(status.subscribed ? 'active' : 'idle');
    } catch {
        setPushButtonState('unavailable', 'Não foi possível consultar o Push');
    }
}

async function togglePushNotifications() {
    if (typeof DASHGLPI_PUSH_ENABLED === 'undefined' || !DASHGLPI_PUSH_ENABLED) {
        alert('As notificações Push ainda não foram configuradas no servidor.');
        return;
    }
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        alert('Este navegador não suporta notificações Push.');
        return;
    }

    const buttons = pushActionButtons();
    buttons.forEach(button => { button.disabled = true; });
    try {
        const registration = await pushServiceWorkerRegistration();
        if (!registration) throw new Error('Service worker indisponível.');
        let subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            const response = await dashglpiPostForm(`${PLUGIN_ROOT}/ajax/push.php?action=unsubscribe`, {
                endpoint: subscription.endpoint,
            }, 'Não foi possível desativar o Push.');
            if (!response.ok) throw new Error(response.error || 'Não foi possível desativar o Push.');
            await subscription.unsubscribe();
            setPushButtonState('idle');
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            throw new Error('Permissão de notificações não concedida.');
        }
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: pushBase64ToUint8Array(DASHGLPI_PUSH_PUBLIC_KEY),
        });
        const response = await dashglpiPostForm(`${PLUGIN_ROOT}/ajax/push.php?action=subscribe`, {
            subscription: JSON.stringify(subscription.toJSON()),
        }, 'Não foi possível ativar o Push.');
        if (!response.ok) throw new Error(response.error || 'Não foi possível ativar o Push.');
        setPushButtonState('active');
    } catch (error) {
        alert(error.message || 'Não foi possível atualizar as notificações Push.');
        setPushButtonState('idle');
    } finally {
        buttons.forEach(button => { button.disabled = false; });
    }
}

// ==================== UTILITY FUNCTIONS ====================
function getStatusClass(status) {
    if (status == 1) return 'success';
    if (status == 5 || status == 6) return 'success';
    if (status == 2 || status == 3) return 'primary';
    if (status == 4) return 'warning';
    return 'primary';
}

function getStatusIcon(status) {
    if (status == 1) return 'fa-plus-circle';
    if (status == 5 || status == 6) return 'fa-check-circle';
    if (status == 2 || status == 3) return 'fa-spinner fa-pulse';
    if (status == 4) return 'fa-pause-circle';
    return 'fa-ticket-alt';
}

function getStatusColor(status) {
    if (status == 1) return '#22c55e';
    if (status == 2 || status == 3) return '#3b82f6';
    if (status == 4) return '#f59e0b';
    if (status == 5 || status == 6) return '#64748b';
    return '#ef4444';
}

function getStatusBg(status) {
    if (status == 1) return 'rgba(34, 197, 94, 0.15)';
    if (status == 2 || status == 3) return 'rgba(59, 130, 246, 0.15)';
    if (status == 4) return 'rgba(245, 158, 11, 0.15)';
    if (status == 5 || status == 6) return 'rgba(100, 116, 139, 0.15)';
    return 'rgba(239, 68, 68, 0.15)';
}

function getStatusLabel(status) {
    const statusMap = {
        1: 'Novo',
        2: 'Em Atendimento',
        3: 'Planejado',
        4: 'Pendente',
        5: 'Solucionado',
        6: 'Fechado'
    };
    return statusMap[status] || 'Outro';
}

function getStageStep(status) {
    if (Number(status) === 1) return 1;
    if (Number(status) === 5 || Number(status) === 6) return 3;
    return 2;
}

function parseMysqlDate(value) {
    if (!value) return null;
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? null : date;
}

function parseDateValue(value) {
    const date = parseMysqlDate(value);
    return date ? date.getTime() : 0;
}

function formatDateTime(value) {
    const date = parseMysqlDate(value);
    if (!date) return '';
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatTimeOnly(value) {
    const date = parseMysqlDate(value);
    if (!date) return '';
    return date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

document.addEventListener('click', (e) => {
    const panel = document.getElementById('notificationPanel');
    const btn = e.target.closest('.icon-btn');

    if (panel && !panel.contains(e.target) && !btn) {
        panel.classList.remove('show');
    }
});

// ==================== GAMIFICAÇÃO: RENDER RANKING ====================
async function renderLeaderboard() {
    const container = document.getElementById('leaderboard-container');
    if (!pageAllowed('ranking') || !container) return;

    try {
        const response = await fetch(PLUGIN_ROOT + '/ajax/dashboard.php?action=get_ranking');
        const technicians = await response.json();

        if (!technicians || technicians.length === 0) {
            container.innerHTML = '<div class="text-center p-5 text-muted">Nenhum chamado finalizado este mês.</div>';
            return;
        }

        const maxPoints = Math.max(...technicians.map(t => t.points)) || 1;

        container.innerHTML = technicians.map((tech, index) => {
            const rank = index + 1;
            const percent = (tech.points / maxPoints) * 100;

            let rankDisplay = `<span style="font-weight: 600; color: var(--text-muted); width: 30px; text-align:center;">#${rank}</span>`;
            if (index === 0) rankDisplay = `<span style="font-size: 1.4rem; width: 30px; text-align:center;">👑</span>`;

            const itemClass = index < 3 ? `rank-${rank}` : 'rank-other';

            return `
                <div class="leaderboard-item ${itemClass}" style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--card-bg); border-bottom: 1px solid var(--border-color); margin-bottom: 8px; border-radius: 12px;">

                    <div>${rankDisplay}</div>

                    <div style="
                        width: 46px;
                        height: 46px;
                        border-radius: 50%;
                        background: ${tech.color}20;
                        color: ${tech.color};
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: 700;
                        border: 2px solid ${tech.color};
                        font-size: 1.1rem;
                        flex-shrink: 0;
                    ">
                        ${tech.avatar}
                    </div>

                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <span style="font-weight: 600; color: var(--text-main); font-size: 0.95rem;">
                                ${escHtml(tech.name)}
                            </span>
                            <span style="
                                background: ${tech.color}15;
                                color: ${tech.color};
                                padding: 2px 8px;
                                border-radius: 12px;
                                font-size: 0.85rem;
                                font-weight: 700;
                            ">
                                ${tech.points} pts
                            </span>
                        </div>

                        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-sec); margin-bottom: 6px;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                            <span>${tech.tickets} Chamados resolvidos</span>
                        </div>

                        <div style="width: 100%; height: 6px; background: var(--glass-bg); border-radius: 10px; overflow: hidden;">
                            <div style="
                                width: 0%;
                                height: 100%;
                                background: ${tech.color};
                                border-radius: 10px;
                                transition: width 1s ease-in-out;
                            " class="progress-bar-anim" data-width="${percent}%"></div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        setTimeout(() => {
            const bars = document.querySelectorAll('.progress-bar-anim');
            bars.forEach(bar => {
                bar.style.width = bar.getAttribute('data-width');
            });
        }, 100);

    } catch (error) {
        console.error('Erro ao carregar ranking:', error);
        container.innerHTML = '<div class="text-center p-5 text-danger">Erro de conexão com a API.</div>';
    }
}

// ==================== LAYOUT STUBS ====================
function saveLayout() {
    // Placeholder — layout persistence not yet implemented
    console.log('Layout saved (stub)');
}

function toggleEditMode() {
    const indicator = document.getElementById('editModeIndicator');
    if (indicator) {
        indicator.style.display = indicator.style.display === 'none' ? 'flex' : 'none';
    }
}
