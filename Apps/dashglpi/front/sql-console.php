<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/sql_console.php';

dashglpi_require_admin();

$settings = dashglpi_get_settings('reports');
$appName = (string) $settings['app_name'];
$bootstrapData = dashglpi_sql_console_dataset();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Console SQL - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="<?= htmlspecialchars(dashglpi_asset_url('vendor/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(dashglpi_asset_url('vendor/css/fontawesome.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(dashglpi_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script>
        const DASHGLPI_CSRF_TOKEN = <?= json_encode(dashglpi_csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const DASHGLPI_SQL_BOOTSTRAP = <?= json_encode($bootstrapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
    </script>
</head>
<body class="light-mode">
    <button class="floating-menu-btn" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <?php dashglpi_render_sidebar('sql_console', 'sql_console', 'Admin SQL'); ?>

    <main class="main-content" id="mainContent">
        <section class="page-section active">
            <header class="page-header">
                <div class="page-title-wrapper">
                    <h1>Console SQL</h1>
                    <div class="page-subtitle sql-console-page-subtitle">
                        <i class="fas fa-shield-alt"></i>
                        <span>Base atual: <?= htmlspecialchars((string) ($bootstrapData['current_database'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="sql-console-mode-badge" id="sqlWriteModeBadge"></span>
                    </div>
                </div>
                <div class="header-actions sql-console-header-actions">
                    <button class="page-action-btn" type="button" id="sqlRefreshHistory">
                        <i class="fas fa-rotate-right"></i>
                        <span>Atualizar</span>
                    </button>
                    <button class="page-action-btn" type="button" id="sqlFormatButton">
                        <i class="fas fa-align-left"></i>
                        <span>Formatar</span>
                    </button>
                    <button class="page-action-btn" type="button" id="sqlClearButton">
                        <i class="fas fa-eraser"></i>
                        <span>Limpar</span>
                    </button>
                    <button class="page-action-btn" type="button" id="sqlClearGlpiCacheButton">
                        <i class="fas fa-broom"></i>
                        <span>Limpar cache GLPI</span>
                    </button>
                    <button class="page-action-btn" type="button" id="sqlOptimizeTablesButton">
                        <i class="fas fa-gauge-high"></i>
                        <span>Otimizar Tabelas Pesadas</span>
                    </button>
                </div>
            </header>

            <div class="sql-console-diagnostic-banner" id="sqlTimezoneBanner" hidden>
                <i class="fas fa-triangle-exclamation"></i>
                <span class="sql-console-diagnostic-text">
                    Suporte a timezone nomeado desativado no MySQL (CONVERT_TZ retornou NULL). Correcoes de fuso
                    horario em datas dependem disso. Requer carregar as tabelas <code>mysql.time_zone*</code> via
                    <code>mysql_tzinfo_to_sql</code> com acesso shell ao servidor de banco — nao e possivel corrigir
                    pela aplicacao.
                </span>
                <button class="page-action-btn page-action-btn--compact" type="button" id="sqlTimezoneRetestButton">
                    <i class="fas fa-rotate-right"></i>
                    <span>Testar novamente</span>
                </button>
            </div>

            <div class="sql-console-grid">
                <div class="sql-console-main">
                    <section class="glass-card sql-console-editor-card">
                        <div class="sql-console-toolbar">
                            <div class="sql-console-select-shell">
                                <label for="sqlRowLimit">Limite de linhas</label>
                                <select id="sqlRowLimit" class="sql-console-select"></select>
                            </div>
                            <div class="sql-console-toolbar-hint">
                                <i class="fas fa-keyboard"></i>
                                <span>`Ctrl+Enter` ou `Cmd+Enter` executa SELECT e gera previa para escrita.</span>
                            </div>
                        </div>

                        <div class="sql-console-write-banner" id="sqlWriteModeBanner"></div>

                        <label class="sql-console-editor-label" for="sqlEditor">Consulta</label>
                        <textarea
                            id="sqlEditor"
                            class="sql-console-editor"
                            spellcheck="false"
                            placeholder="SELECT id, name, status FROM glpi_tickets WHERE is_deleted = 0 ORDER BY date DESC LIMIT 20;"
                        ></textarea>

                        <div class="sql-console-editor-actions">
                            <div class="sql-console-editor-actions-copy">
                                <strong>Execucao da query atual</strong>
                                <span>SELECT executa na hora. Escrita gera previa obrigatoria.</span>
                            </div>
                            <button class="page-action-btn primary sql-console-run-btn" type="button" id="sqlRunButton">
                                <i class="fas fa-play"></i>
                                <span>Executar</span>
                            </button>
                        </div>

                        <div class="admin-status sql-console-status" id="sqlStatus">Pronto para executar.</div>
                        <div class="sql-console-meta" id="sqlMeta"></div>
                    </section>

                    <section class="glass-card sql-console-write-panel" id="sqlWritePreviewPanel" hidden>
                        <div class="sql-console-write-header">
                            <div class="sql-console-write-heading">
                                <span class="sql-console-write-badge">Previa obrigatoria</span>
                                <h3 class="sql-console-write-title" id="sqlWritePreviewTitle">Confirmar alteracao</h3>
                                <p class="sql-console-write-summary" id="sqlWritePreviewSummary"></p>
                            </div>
                            <div class="sql-console-write-meta" id="sqlWritePreviewMeta"></div>
                        </div>

                        <div class="sql-console-write-preview" id="sqlWritePreviewStatement"></div>

                        <ul class="sql-console-write-warnings" id="sqlWritePreviewWarnings"></ul>

                        <label class="sql-console-write-check" for="sqlWriteReviewed">
                            <input type="checkbox" id="sqlWriteReviewed">
                            <span>Revisei a query e confirmo que a alteracao sera aplicada no banco real do GLPI.</span>
                        </label>

                        <div class="sql-console-write-controls">
                            <div class="sql-console-write-confirmation">
                                <label for="sqlWriteConfirmationInput">Digite a frase de confirmacao</label>
                                <div class="sql-console-write-phrase" id="sqlWriteConfirmationPhrase"></div>
                                <input
                                    type="text"
                                    id="sqlWriteConfirmationInput"
                                    class="sql-console-text-input"
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder="Digite a frase exatamente como exibida"
                                >
                            </div>

                            <div class="sql-console-write-actions">
                                <button class="page-action-btn" type="button" id="sqlWriteCancelButton">
                                    <i class="fas fa-xmark"></i>
                                    <span>Cancelar</span>
                                </button>
                                <button class="page-action-btn danger" type="button" id="sqlWriteConfirmButton" disabled>
                                    <i class="fas fa-triangle-exclamation"></i>
                                    <span>Aplicar alteracao</span>
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="glass-card table-card sql-console-results-card">
                        <div class="table-header">
                            <h3 class="table-title">Resultado</h3>
                            <div class="table-header-actions sql-console-result-header-actions">
                                <div class="sql-console-result-toolbar">
                                    <button class="page-action-btn page-action-btn--compact" type="button" id="sqlCopyButton" disabled>
                                        <i class="fas fa-copy"></i>
                                        <span>Copiar</span>
                                    </button>
                                    <button class="page-action-btn page-action-btn--compact" type="button" id="sqlCsvButton" disabled>
                                        <i class="fas fa-file-csv"></i>
                                        <span>Exportar CSV</span>
                                    </button>
                                </div>
                                <div class="sql-console-result-summary" id="sqlResultSummary">Nenhuma consulta executada.</div>
                            </div>
                        </div>
                        <div class="table-responsive sql-console-results-wrap">
                            <table class="custom-table sql-console-table" id="sqlResultsTable">
                                <thead id="sqlResultsHead"></thead>
                                <tbody id="sqlResultsBody">
                                    <tr><td class="table-empty">Execute uma consulta para ver os dados.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <aside class="sql-console-sidebar">
                    <section class="glass-card sql-console-side-card">
                        <div class="sql-console-side-header">
                            <h3>Consultas rapidas</h3>
                            <span>Presets</span>
                        </div>
                        <div class="sql-console-list" id="sqlPresetList"></div>
                    </section>

                    <section class="glass-card sql-console-side-card">
                        <div class="sql-console-side-header">
                            <h3>Historico</h3>
                            <span>Ultimas execucoes</span>
                        </div>
                        <div class="sql-console-list" id="sqlHistoryList"></div>
                    </section>

                    <section class="glass-card sql-console-side-card">
                        <div class="sql-console-side-header">
                            <h3>Regras</h3>
                            <span>Seguranca</span>
                        </div>
                        <ul class="sql-console-rules" id="sqlRuleList"></ul>
                    </section>
                </aside>
            </div>
        </section>
    </main>

    <div class="sql-console-confirm-modal" id="sqlConfirmModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="sqlConfirmModalTitle">
        <div class="sql-console-confirm-dialog" role="document">
            <div class="sql-console-confirm-header">
                <h3 class="sql-console-confirm-title" id="sqlConfirmModalTitle">Confirmar ação</h3>
            </div>
            <div class="sql-console-confirm-body">
                <p class="sql-console-confirm-message" id="sqlConfirmModalMessage"></p>
                <pre class="sql-console-confirm-command" id="sqlConfirmModalCommand" hidden></pre>
            </div>
            <div class="sql-console-confirm-actions">
                <button class="page-action-btn" type="button" id="sqlConfirmModalCancel">
                    <i class="fas fa-xmark"></i>
                    <span>Cancelar</span>
                </button>
                <button class="page-action-btn danger" type="button" id="sqlConfirmModalConfirm">
                    <i class="fas fa-check"></i>
                    <span>Confirmar</span>
                </button>
            </div>
        </div>
    </div>

    <script src="<?= htmlspecialchars(dashglpi_asset_url('js/sql-console.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
