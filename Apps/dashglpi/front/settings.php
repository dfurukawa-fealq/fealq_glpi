<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';
require_once __DIR__ . '/../inc/sla_simple.php';
require_once __DIR__ . '/../inc/layout.php';

dashglpi_require_admin();

$settings = dashglpi_get_settings('reports');
$appName = (string) $settings['app_name'];
$slaSettings = dashglpi_get_settings('sla_simple');
$slaEntities = dashglpi_sla_entities();
$slaCalendars = dashglpi_sla_calendars();
$slaDayLabels = [
    1 => 'Segunda',
    2 => 'Terça',
    3 => 'Quarta',
    4 => 'Quinta',
    5 => 'Sexta',
    6 => 'Sábado',
    0 => 'Domingo',
];
$slaTimeUnits = [
    'minute' => 'minutos',
    'hour' => 'horas',
    'day' => 'dias',
    'month' => 'meses',
];
$csrf = dashglpi_csrf_token();
$glpiRoot = rtrim(dashglpi_env('GLPI_PUBLIC_URL', ''), '/');
$settingsSection = (string) ($_GET['section'] ?? 'sla');
$settingsSectionMeta = [
    'sla' => ['label' => 'SLA Simples', 'subtitle' => 'Entidades, calendário comercial e aplicação automática de SLA'],
    'reports' => ['label' => 'Relatórios', 'subtitle' => 'Padrão dos relatórios entregues ao cliente'],
    'profile_access' => ['label' => 'Acesso por Perfil', 'subtitle' => 'Prioridade por perfil GLPI e páginas funcionais liberadas no Dash'],
    'notifications' => ['label' => 'Notificações', 'subtitle' => 'Fluxo em etapas para e-mail, notificações, modelos, fila e limpeza operacional'],
    'alerting' => ['label' => 'Canais de Alerta', 'subtitle' => 'Monitor ativo de SLA e alertas em Teams, WhatsApp e Telegram'],
    'rules' => ['label' => 'Regras de Automação', 'subtitle' => 'Engine dinâmica: 1 gatilho -> N ações -> N destinatários, sem precisar de deploy'],
    'recipients' => ['label' => 'Destinatários', 'subtitle' => 'Apoio avançado para coletores, atalhos e ajustes complementares do GLPI'],
    'general' => ['label' => 'Gerais', 'subtitle' => 'Logo, nome da aplicação e identidade exibida na sidebar'],
];
if (!array_key_exists($settingsSection, $settingsSectionMeta)) {
    $settingsSection = 'sla';
}
$settingsSectionLabel = $settingsSectionMeta[$settingsSection]['label'];
$settingsSectionSubtitle = $settingsSectionMeta[$settingsSection]['subtitle'];
$showGlobalSettingsSave = in_array($settingsSection, ['general', 'reports'], true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="<?= htmlspecialchars(dashglpi_asset_url('vendor/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(dashglpi_asset_url('vendor/css/fontawesome.min.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(dashglpi_asset_url('css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 24px;
        }

        .settings-card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
            color: var(--text-main);
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .settings-card-title i {
            color: var(--primary);
        }

        .settings-row {
            display: grid;
            grid-template-columns: 230px minmax(0, 1fr);
            gap: 22px;
            padding: 22px 0;
            border-top: 1px solid var(--glass-border);
        }

        .settings-row:first-of-type {
            border-top: 0;
            padding-top: 0;
        }

        .settings-label {
            color: var(--text-main);
            font-weight: 800;
        }

        .settings-help {
            margin-top: 6px;
            color: var(--text-muted);
            font-size: .85rem;
            line-height: 1.45;
        }

        .settings-help.attention {
            color: #b45309;
        }

        .settings-help.ok {
            color: #2f6b38;
        }

        .settings-options {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .settings-option {
            display: grid;
            grid-template-columns: 22px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            min-height: 104px;
            padding: 16px;
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            background: var(--glass-bg);
            color: var(--text-main);
            cursor: pointer;
            transition: all .2s ease;
        }

        .settings-option:hover {
            border-color: var(--glass-border-hover);
            background: var(--glass-bg-hover);
            transform: translateY(-2px);
        }

        .settings-option input {
            margin-top: 3px;
            accent-color: var(--primary);
        }

        .settings-option strong {
            display: block;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .settings-option span {
            color: var(--text-muted);
            font-size: .86rem;
            line-height: 1.45;
        }

        .settings-input {
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            color: var(--text-main);
            background: var(--glass-bg);
            outline: none;
        }

        .settings-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .settings-input.mail-rule-error {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .16);
        }

        .logo-config {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 260px;
            gap: 18px;
            align-items: stretch;
        }

        .logo-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 132px;
            padding: 18px;
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            background: var(--glass-bg);
        }

        .logo-preview-dark {
            background: linear-gradient(135deg, #11142f, #282b5f);
        }

        .logo-preview img {
            display: block;
            max-width: 220px;
            max-height: 92px;
            object-fit: contain;
        }

        .settings-status {
            display: none;
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 800;
        }

        .settings-status.ok {
            display: block;
            color: #86efac;
            background: rgba(34, 197, 94, .12);
            border: 1px solid rgba(34, 197, 94, .28);
        }

        .settings-status.error {
            display: block;
            color: #fca5a5;
            background: rgba(239, 68, 68, .12);
            border: 1px solid rgba(239, 68, 68, .28);
        }

        .settings-guide-card,
        .settings-block {
            padding: 22px;
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            background: rgba(255, 255, 255, .03);
        }

        .settings-guide-card {
            display: grid;
            gap: 18px;
        }

        .settings-guide-head h3,
        .settings-block-header h3 {
            margin: 4px 0 0;
            color: var(--text-main);
            font-size: 1.1rem;
            font-weight: 800;
        }

        .settings-guide-head p {
            margin: 10px 0 0;
            color: var(--text-sec);
            line-height: 1.6;
        }

        .settings-guide-kicker,
        .settings-block-kicker {
            color: var(--primary);
            font-size: .74rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .settings-guide-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .settings-guide-item {
            padding: 16px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: var(--glass-bg);
        }

        .settings-guide-item strong {
            display: block;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .settings-guide-item span {
            color: var(--text-muted);
            font-size: .85rem;
            line-height: 1.5;
        }

        .settings-block {
            display: grid;
            gap: 18px;
        }

        .settings-block-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .settings-callout {
            display: grid;
            gap: 8px;
            padding: 16px 18px;
            border: 1px solid rgba(59, 130, 246, .24);
            border-radius: 16px;
            background: rgba(59, 130, 246, .08);
        }

        .settings-callout strong {
            color: var(--text-main);
        }

        .settings-callout span {
            color: var(--text-sec);
            line-height: 1.55;
        }

        .settings-callout.attention {
            border-color: rgba(245, 158, 11, .28);
            background: rgba(245, 158, 11, .10);
        }

        .settings-callout.ok {
            border-color: rgba(34, 197, 94, .28);
            background: rgba(34, 197, 94, .10);
        }

        .settings-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .settings-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            padding: 12px 18px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            color: var(--text-main);
            background: var(--glass-bg);
            text-decoration: none;
            font-weight: 800;
            cursor: pointer;
            transition: all .2s ease;
        }

        .settings-btn:hover {
            color: var(--text-main);
            background: var(--glass-bg-hover);
            transform: translateY(-2px);
        }

        .settings-btn.primary {
            border-color: var(--primary);
            color: #fff;
            background: var(--primary);
            box-shadow: 0 0 22px var(--primary-glow);
        }

        .settings-btn.success {
            border-color: rgba(34, 197, 94, .65);
            color: #fff;
            background: #16a34a;
            box-shadow: 0 0 20px rgba(34, 197, 94, .18);
        }

        .settings-btn.danger {
            border-color: rgba(239, 68, 68, .55);
            color: #fff;
            background: #dc2626;
            box-shadow: 0 0 20px rgba(239, 68, 68, .18);
        }

        .settings-option-inline {
            min-height: 48px;
            padding: 12px;
        }

        .settings-option-inline:hover {
            transform: none;
        }

        .settings-panel[hidden] {
            display: none;
        }

        .profile-access-picker {
            display: grid;
            gap: 8px;
            min-width: 300px;
        }

        .profile-access-current {
            display: grid;
            gap: 4px;
            padding: 14px 16px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: rgba(255, 255, 255, .03);
        }

        .profile-access-current strong {
            color: var(--text-main);
            font-size: .98rem;
        }

        .profile-access-current span {
            color: var(--text-muted);
            font-size: .82rem;
        }

        .profile-access-table-wrap {
            overflow: auto;
        }

        .profile-access-table td {
            vertical-align: top;
        }

        .profile-access-toggle {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
            font-weight: 800;
        }

        .profile-access-toggle input,
        .profile-access-page input {
            accent-color: var(--primary);
        }

        .profile-access-priority {
            max-width: 112px;
        }

        .profile-access-pages {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            min-width: 360px;
        }

        .profile-access-page {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-main);
            background: rgba(255, 255, 255, .03);
            font-size: .82rem;
            font-weight: 700;
        }

        .profile-access-page i {
            color: var(--primary);
            opacity: .9;
        }

        .profile-access-rule-note {
            margin-top: 8px;
            color: var(--text-muted);
            font-size: .78rem;
            line-height: 1.45;
        }

        .profile-access-empty {
            padding: 32px;
            text-align: center;
            color: var(--text-muted);
        }

        .sla-list {
            display: grid;
            gap: 12px;
        }

        .sla-list-row {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            padding: 14px 0;
            border-top: 1px solid var(--glass-border);
        }

        .sla-list-row:first-child {
            border-top: 0;
        }

        .settings-add-row-btn {
            justify-self: start;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 10px 14px;
            border: 1px solid rgba(59, 130, 246, .32);
            border-radius: 12px;
            color: var(--primary);
            background: rgba(59, 130, 246, .08);
            font-weight: 900;
            cursor: pointer;
        }

        .settings-add-row-btn:hover {
            background: rgba(59, 130, 246, .16);
        }

        .sla-row-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
            color: var(--text-main);
            font-weight: 900;
        }

        .sla-row-title small {
            color: var(--text-muted);
            font-size: .78rem;
            font-weight: 700;
        }

        .sla-inline {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
        }

        .sla-time-control {
            display: grid;
            grid-template-columns: 90px 150px;
            gap: 10px;
            align-items: center;
        }

        .settings-select,
        .settings-number,
        .settings-time {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-main);
            background: var(--glass-bg);
            outline: none;
        }

        .settings-number {
            min-width: 0;
        }

        .settings-select:focus,
        .settings-number:focus,
        .settings-time:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .sla-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 92px;
            min-height: 30px;
            padding: 5px 10px;
            border-radius: 999px;
            border: 1px solid var(--glass-border);
            color: var(--text-muted);
            background: rgba(148, 163, 184, .08);
            font-size: .75rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .sla-status-pill.created {
            color: #86efac;
            border-color: rgba(34, 197, 94, .35);
            background: rgba(34, 197, 94, .12);
        }

        .sla-status-pill.updated {
            color: #93c5fd;
            border-color: rgba(59, 130, 246, .35);
            background: rgba(59, 130, 246, .12);
        }

        .sla-status-pill.existing {
            color: #c4b5fd;
            border-color: rgba(139, 92, 246, .32);
            background: rgba(139, 92, 246, .10);
        }

        .sla-status-pill.pending {
            color: #fde68a;
            border-color: rgba(245, 158, 11, .32);
            background: rgba(245, 158, 11, .10);
        }

        .sla-status-pill.error {
            color: #fca5a5;
            border-color: rgba(239, 68, 68, .35);
            background: rgba(239, 68, 68, .12);
        }

        .sla-new-panel {
            display: none;
            margin-top: 12px;
            padding: 14px;
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            background: rgba(255, 255, 255, .03);
        }

        .sla-new-panel.active {
            display: grid;
            gap: 12px;
        }

        .sla-two-cols {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .sla-calendar-grid {
            display: grid;
            gap: 10px;
        }

        .sla-calendar-day {
            display: grid;
            grid-template-columns: 120px 140px 140px auto minmax(0, 1fr);
            gap: 10px;
            align-items: center;
        }

        .sla-calendar-day label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-main);
            font-weight: 800;
        }

        .sla-calendar-day input[type="checkbox"] {
            accent-color: var(--primary);
        }

        .sla-mode-note {
            color: var(--text-muted);
            font-size: .84rem;
            line-height: 1.45;
        }

        .sla-replicate-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-main);
            background: var(--glass-bg);
            font-size: .78rem;
            font-weight: 900;
            cursor: pointer;
        }

        .sla-replicate-btn:hover {
            background: var(--glass-bg-hover);
            border-color: var(--glass-border-hover);
        }

        .settings-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .settings-kpi-grid-four {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 0;
        }

        .settings-kpi {
            min-height: 96px;
            padding: 16px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: var(--glass-bg);
        }

        .settings-kpi-value {
            color: var(--text-main);
            font-size: 1.9rem;
            font-weight: 900;
            line-height: 1;
        }

        .settings-kpi-label {
            margin-top: 8px;
            color: var(--text-muted);
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .settings-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .settings-toolbar.soft {
            padding: 14px 16px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: rgba(255, 255, 255, .02);
        }

        .settings-toolbar-copy {
            display: grid;
            gap: 4px;
        }

        .settings-toolbar-copy strong {
            color: var(--text-main);
        }

        .settings-toolbar-copy span {
            color: var(--text-muted);
            font-size: .82rem;
        }

        .settings-native-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .settings-link-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            padding: 8px 12px;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-main);
            background: var(--glass-bg);
            font-size: .78rem;
            font-weight: 900;
            text-decoration: none;
        }

        .settings-link-btn:hover {
            color: var(--text-main);
            background: var(--glass-bg-hover);
        }

        .settings-create-panel {
            display: none;
            margin-bottom: 18px;
            padding: 16px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: rgba(255, 255, 255, .03);
        }

        .settings-create-panel.active {
            display: grid;
            gap: 14px;
        }

        .settings-accordion {
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            background: var(--glass-bg);
        }

        .settings-accordion summary {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            color: var(--text-main);
            font-weight: 800;
            cursor: pointer;
            list-style: none;
        }

        .settings-accordion summary::-webkit-details-marker {
            display: none;
        }

        .settings-accordion summary::after {
            content: '\f078';
            margin-left: auto;
            color: var(--text-muted);
            font-family: 'Font Awesome 6 Free';
            font-size: .78rem;
            font-weight: 900;
            transition: transform .2s ease;
        }

        .settings-accordion[open] summary::after {
            transform: rotate(180deg);
        }

        .settings-accordion-body {
            padding: 0 18px 18px;
        }

        .settings-advanced-block {
            background: rgba(255, 255, 255, .02);
        }

        .settings-stepbar {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 22px;
        }

        .settings-stepbar-sla {
            grid-template-columns: repeat(7, minmax(0, 1fr));
            margin-bottom: 12px;
        }

        .settings-stepbtn {
            display: grid;
            gap: 4px;
            min-height: 76px;
            padding: 14px 16px;
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            color: var(--text-muted);
            background: var(--glass-bg);
            text-align: left;
            cursor: pointer;
            transition: all .2s ease;
        }

        .settings-stepbtn:hover {
            background: var(--glass-bg-hover);
            transform: translateY(-2px);
        }

        .settings-stepbtn.active {
            border-color: rgba(59, 130, 246, .34);
            background: rgba(59, 130, 246, .12);
            box-shadow: 0 0 0 1px rgba(59, 130, 246, .18);
        }

        .settings-stepbtn strong {
            color: var(--text-main);
            font-size: .9rem;
        }

        .settings-stepbtn span {
            color: var(--text-muted);
            font-size: .78rem;
            line-height: 1.35;
        }

        .settings-step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border: 1px solid rgba(59, 130, 246, .24);
            border-radius: 999px;
            color: var(--primary);
            background: rgba(59, 130, 246, .10);
            font-size: .78rem;
            font-weight: 900;
        }

        .settings-stepbtn.active .settings-step-number {
            color: #fff;
            border-color: var(--primary);
            background: var(--primary);
            box-shadow: 0 0 18px var(--primary-glow);
        }

        .settings-stepbar-sla .settings-stepbtn {
            min-height: 84px;
            padding: 14px 14px 12px;
            align-content: center;
        }

        .settings-stepbar-sla .settings-stepbtn strong {
            font-size: .82rem;
            line-height: 1.25;
        }

        .settings-stepbar-sla .settings-stepbtn .settings-step-desc {
            display: none;
        }

        .settings-step-summary {
            display: grid;
            gap: 4px;
            padding: 12px 16px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: rgba(255, 255, 255, .03);
            margin-bottom: 20px;
        }

        .settings-step-summary strong {
            color: var(--text-main);
            font-size: .9rem;
        }

        .settings-step-summary span {
            color: var(--text-sec);
            font-size: .82rem;
            line-height: 1.45;
        }

        .settings-step-panel[hidden],
        .settings-view-panel[hidden] {
            display: none;
        }

        .settings-subtabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .settings-subtab {
            min-height: 38px;
            padding: 8px 12px;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-sec);
            background: var(--glass-bg);
            font-size: .8rem;
            font-weight: 900;
            cursor: pointer;
        }

        .settings-subtab.active {
            border-color: var(--primary);
            color: #fff;
            background: var(--primary);
        }

        .settings-subpanel[hidden] {
            display: none;
        }

        .settings-searchbar {
            position: relative;
            display: flex;
            align-items: center;
            min-width: min(100%, 380px);
            flex: 1 1 320px;
        }

        .settings-searchbar i {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            pointer-events: none;
        }

        .settings-searchbar .settings-input {
            padding-left: 40px;
        }

        .settings-block-header.compact {
            align-items: center;
        }

        .settings-block-header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .settings-textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px 14px;
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            color: var(--text-main);
            background: var(--glass-bg);
            outline: none;
            resize: vertical;
        }

        .settings-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .settings-inline-stack {
            display: grid;
            gap: 8px;
        }

        .settings-inline-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-sec);
            font-size: .82rem;
            font-weight: 700;
        }

        .settings-inline-check input {
            accent-color: var(--primary);
        }

        .settings-recipient-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 18px;
        }

        .settings-editor-shell {
            display: grid;
            gap: 18px;
        }

        .settings-back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 10px 14px;
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-main);
            background: var(--glass-bg);
            font-weight: 800;
            cursor: pointer;
        }

        .settings-back-btn:hover {
            background: var(--glass-bg-hover);
        }

        .settings-recipient-column {
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .settings-multiselect {
            min-height: 250px;
            padding: 10px 12px;
        }

        .settings-pill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            min-height: 64px;
            padding: 14px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: var(--glass-bg);
        }

        .settings-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 6px 12px;
            border: 1px solid rgba(59, 130, 246, .22);
            border-radius: 999px;
            color: var(--text-main);
            background: rgba(59, 130, 246, .10);
            font-size: .8rem;
            font-weight: 700;
        }

        .settings-pill button {
            border: 0;
            color: var(--text-main);
            background: transparent;
            cursor: pointer;
            font-size: .85rem;
            line-height: 1;
        }

        .settings-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .settings-table th,
        .settings-table td {
            padding: 14px 16px;
            border-top: 1px solid var(--glass-border);
            color: var(--text-sec);
            font-size: .86rem;
            text-align: left;
            vertical-align: middle;
        }

        .settings-table th {
            color: var(--text-muted);
            font-size: .7rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .settings-table-filters th {
            padding-top: 10px;
            padding-bottom: 14px;
            vertical-align: top;
        }

        .settings-table-filter {
            width: 100%;
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-main);
            background: var(--glass-bg);
            font-size: .78rem;
        }

        .settings-table-filter::placeholder {
            color: var(--text-muted);
        }

        .settings-table strong {
            color: var(--text-main);
        }

        .settings-action-small {
            min-height: 32px;
            padding: 6px 10px;
            border: 1px solid var(--glass-border);
            border-radius: 9px;
            color: var(--text-main);
            background: var(--glass-bg);
            font-size: .76rem;
            font-weight: 900;
            cursor: pointer;
        }

        .settings-action-small:hover {
            background: var(--glass-bg-hover);
        }

        .settings-action-small:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .settings-action-small:disabled:hover {
            background: var(--glass-bg);
        }

        .settings-template-cell {
            min-width: 210px;
            display: grid;
            gap: 8px;
            align-items: start;
        }

        .settings-template-cell-main {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .settings-template-name {
            color: var(--text-sec);
            font-weight: 800;
            line-height: 1.35;
        }

        .settings-template-state {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: .68rem;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .settings-template-state.ok {
            color: #15803d;
            background: rgba(34, 197, 94, .12);
            border: 1px solid rgba(34, 197, 94, .24);
        }

        .settings-template-state.warn {
            color: #b45309;
            background: rgba(245, 158, 11, .12);
            border: 1px solid rgba(245, 158, 11, .26);
        }

        .settings-row-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .settings-action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            padding: 0;
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-main);
            background: var(--glass-bg);
            cursor: pointer;
            transition: all .2s ease;
        }

        .settings-action-icon:hover {
            background: var(--glass-bg-hover);
            transform: translateY(-1px);
        }

        .settings-action-icon:disabled,
        .settings-action-icon.disabled {
            opacity: .45;
            cursor: not-allowed;
            transform: none;
        }

        .settings-action-icon:disabled:hover,
        .settings-action-icon.disabled:hover {
            background: var(--glass-bg);
            transform: none;
        }

        .settings-action-icon i {
            font-size: 1rem;
        }

        .settings-action-icon.toggle-on {
            color: #16a34a;
        }

        .settings-action-icon.toggle-off {
            color: var(--text-muted);
        }

        .settings-modal {
            position: fixed;
            inset: 0;
            z-index: 5000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .62);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .settings-modal.active {
            display: flex;
        }

        .settings-modal-dialog {
            width: min(1340px, 100%);
            height: min(860px, calc(100vh - 48px));
            max-height: min(900px, calc(100vh - 48px));
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            background: var(--bg-body);
            box-shadow: 0 24px 80px rgba(0, 0, 0, .42);
            overflow: hidden;
        }

        .settings-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--glass-border);
        }

        .settings-modal-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .settings-modal-title {
            color: var(--text-main);
            font-size: .98rem;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .settings-modal-close {
            min-width: 118px;
        }

        .settings-modal-close:hover {
            background: var(--glass-bg-hover);
        }

        .settings-modal-dialog.compact {
            width: min(560px, 100%);
            height: auto;
            max-height: calc(100vh - 48px);
            grid-template-rows: auto auto;
        }

        .settings-confirm-body {
            display: grid;
            gap: 10px;
            padding: 20px 18px 22px;
        }

        .settings-confirm-message {
            color: var(--text-main);
            font-size: 1.02rem;
            font-weight: 800;
            line-height: 1.5;
        }

        .settings-modal-dialog.info {
            width: min(840px, 100%);
            height: auto;
            max-height: calc(100vh - 48px);
        }

        .settings-info-modal-body {
            display: grid;
            gap: 18px;
            padding: 20px 18px 22px;
            overflow: auto;
        }

        .settings-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .settings-info-field {
            display: grid;
            gap: 8px;
            padding: 14px 16px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: var(--glass-bg);
        }

        .settings-info-field.wide {
            grid-column: 1 / -1;
        }

        .settings-info-label {
            color: var(--text-muted);
            font-size: .74rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .settings-info-value {
            color: var(--text-main);
            font-weight: 700;
            line-height: 1.55;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .settings-info-section {
            display: grid;
            gap: 12px;
        }

        .settings-diagnostic-card {
            display: grid;
            gap: 10px;
            padding: 18px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: rgba(255, 255, 255, .03);
        }

        .settings-diagnostic-card.ok {
            border-color: rgba(34, 197, 94, .32);
            background: rgba(34, 197, 94, .1);
        }

        .settings-diagnostic-card.warn {
            border-color: rgba(245, 158, 11, .34);
            background: rgba(245, 158, 11, .12);
        }

        .settings-diagnostic-title {
            color: var(--text-main);
            font-size: .96rem;
            font-weight: 900;
        }

        .settings-diagnostic-text {
            color: var(--text-sec);
            line-height: 1.6;
        }

        .settings-diagnostic-meta {
            display: grid;
            gap: 8px;
            color: var(--text-main);
            line-height: 1.55;
        }

        .settings-modal-dialog.faq {
            width: min(920px, 100%);
            height: auto;
            max-height: calc(100vh - 48px);
        }

        .settings-faq-body {
            display: grid;
            gap: 18px;
            padding: 20px 22px 24px;
            overflow: auto;
        }

        .settings-faq-intro {
            color: var(--text-sec);
            line-height: 1.6;
        }

        .settings-faq-step {
            display: grid;
            gap: 8px;
            padding: 16px 18px;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: var(--glass-bg);
        }

        .settings-faq-step-title {
            color: var(--text-main);
            font-size: .92rem;
            font-weight: 900;
        }

        .settings-faq-step p,
        .settings-faq-step li {
            color: var(--text-sec);
            line-height: 1.6;
        }

        .settings-faq-step code {
            color: var(--text-main);
            background: rgba(255, 255, 255, .08);
            padding: 1px 5px;
            border-radius: 6px;
        }

        .settings-faq-code {
            margin: 0;
            padding: 12px 14px;
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            background: rgba(0, 0, 0, .28);
            color: var(--text-main);
            font-family: Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: .82rem;
            line-height: 1.55;
            white-space: pre;
            overflow: auto;
        }

        .settings-faq-warning {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            color: var(--text-main);
            font-weight: 700;
            line-height: 1.55;
        }

        .settings-faq-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 16px 22px 0;
        }

        .settings-faq-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            background: transparent;
            color: var(--text-sec);
            font-weight: 800;
            font-size: .84rem;
            cursor: pointer;
        }

        .settings-faq-tab:hover {
            background: var(--glass-bg-hover);
        }

        .settings-faq-tab.active {
            background: var(--glass-bg);
            color: var(--text-main);
            border-color: var(--text-main);
        }

        .settings-faq-section[hidden] {
            display: none;
        }

        .settings-template-modal-body {
            min-height: 0;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(420px, 1fr);
            gap: 20px;
            padding: 18px;
            overflow: hidden;
        }

        .settings-template-editor,
        .settings-template-preview {
            min-height: 0;
            display: grid;
            gap: 12px;
            align-content: start;
            overflow: hidden;
        }

        .settings-field-group {
            display: grid;
            gap: 7px;
        }

        .settings-field-label {
            color: var(--text-muted);
            font-size: .7rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .settings-field-help {
            color: var(--text-sec);
            font-size: .78rem;
            line-height: 1.4;
        }

        .settings-code-mode {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            justify-self: start;
            min-height: 28px;
            padding: 5px 9px;
            border: 1px solid rgba(59, 130, 246, .35);
            border-radius: 8px;
            color: var(--primary);
            background: rgba(59, 130, 246, .08);
            font-size: .72rem;
            font-weight: 900;
        }

        #templateEditorHtml,
        #templateEditorText {
            font-family: Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            line-height: 1.45;
            tab-size: 2;
            resize: none;
            overflow: auto;
        }

        #templateEditorHtml {
            min-height: 300px;
            height: 300px;
        }

        #templateEditorText {
            min-height: 110px;
            height: 110px;
        }

        .settings-template-preview {
            border-left: 1px solid var(--glass-border);
            padding-left: 16px;
            grid-template-rows: auto auto minmax(0, 1fr);
        }

        .settings-preview-label {
            color: var(--text-muted);
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .settings-template-status {
            padding: 0 18px 18px;
        }

        .settings-preview-frame {
            width: 100%;
            min-height: 0;
            height: 100%;
            border: 1px solid rgba(148, 163, 184, .28);
            border-radius: 12px;
            background: #fff;
        }

        @media (max-width: 980px) {
            .settings-row,
            .logo-config {
                grid-template-columns: 1fr;
            }

            .settings-options {
                grid-template-columns: 1fr;
            }

            .settings-guide-grid,
            .settings-recipient-layout {
                grid-template-columns: 1fr;
            }

            .settings-stepbar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .settings-stepbar-sla {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sla-list-row,
            .sla-inline,
            .sla-two-cols,
            .sla-calendar-day {
                grid-template-columns: 1fr;
            }

            .sla-time-control {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            }

            .settings-add-row-btn {
                justify-content: center;
                width: 100%;
            }

            .settings-kpi-grid {
                grid-template-columns: 1fr;
            }

            .settings-block-header {
                flex-direction: column;
            }

            .settings-block-header-actions,
            .settings-modal-actions {
                justify-content: flex-start;
                margin-left: 0;
            }

            .settings-template-modal-body {
                grid-template-columns: 1fr;
            }

            .settings-info-grid {
                grid-template-columns: 1fr;
            }

            .settings-template-preview {
                border-left: 0;
                padding-left: 0;
                border-top: 1px solid var(--glass-border);
                padding-top: 16px;
            }

            .settings-preview-frame {
                min-height: 360px;
            }
        }
    </style>
</head>
<body class="light-mode">
    <button class="floating-menu-btn" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <?php dashglpi_render_sidebar('settings', 'settings', 'Configurações', $settingsSection); ?>

    <main class="main-content" id="mainContent">
        <header class="page-header">
            <div class="page-title-wrapper">
                <h1>Configurações</h1>
                <div class="page-subtitle">
                    <i class="fas fa-sliders-h"></i>
                    <span><?= htmlspecialchars($settingsSectionSubtitle, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="header-actions settings-actions">
                <a class="page-action-btn" href="/front/dashboard.php">
                    <i class="fas fa-arrow-left"></i>
                    <span>Voltar</span>
                </a>
                <?php if ($showGlobalSettingsSave): ?>
                    <button class="page-action-btn primary" type="button" id="saveSettings">
                        <i class="fas fa-save"></i>
                        <span>Salvar</span>
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <form id="settingsForm" class="settings-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <section class="glass-card settings-panel" data-settings-panel="sla"<?= $settingsSection !== 'sla' ? ' hidden' : '' ?>>
                <h2 class="settings-card-title"><i class="fas fa-business-time"></i> SLA Simples</h2>
                <div class="settings-stepbar settings-stepbar-sla" aria-label="Fluxo de SLA">
                    <button class="settings-stepbtn active" type="button" data-sla-step-target="base" data-sla-step-title="1. SLA Simples" data-sla-step-description="Entidade, calendário e pacote SLM.">
                        <span class="settings-step-number">1</span>
                        <strong>SLA Simples</strong>
                        <span class="settings-step-desc">Entidade, calendário e pacote SLM.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-sla-step-target="tto" data-sla-step-title="2. TTO" data-sla-step-description="Tempo para assumir e atribuir.">
                        <span class="settings-step-number">2</span>
                        <strong>TTO</strong>
                        <span class="settings-step-desc">Tempo para assumir e atribuir.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-sla-step-target="ttr" data-sla-step-title="3. TTR" data-sla-step-description="Tempo para resolver por prioridade.">
                        <span class="settings-step-number">3</span>
                        <strong>TTR</strong>
                        <span class="settings-step-desc">Tempo para resolver por prioridade.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-sla-step-target="apply" data-sla-step-title="4. Aplicação automática" data-sla-step-description="Validação, criação e reaplicação dos SLAs base.">
                        <span class="settings-step-number">4</span>
                        <strong>Aplicação automática</strong>
                        <span class="settings-step-desc">Validação, criação e reaplicação.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-sla-step-target="alerts" data-sla-step-title="5. Alerta SLA" data-sla-step-description="Lembrete prévio por TTR antes do vencimento.">
                        <span class="settings-step-number">5</span>
                        <strong>Alerta SLA</strong>
                        <span class="settings-step-desc">Lembrete prévio por TTR.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-sla-step-target="notification" data-sla-step-title="6. Notificação SLA" data-sla-step-description="Template, gatilho e destinatários do lembrete de SLA.">
                        <span class="settings-step-number">6</span>
                        <strong>Notificação SLA</strong>
                        <span class="settings-step-desc">Template, gatilho e destinatários.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-sla-step-target="automation" data-sla-step-title="7. Ações automáticas" data-sla-step-description="Ajuste de `slaticket` e `queuednotification` para execução estável.">
                        <span class="settings-step-number">7</span>
                        <strong>Ações automáticas</strong>
                        <span class="settings-step-desc">`slaticket` e `queuednotification`.</span>
                    </button>
                </div>
                <div class="settings-step-summary" id="slaStepSummary">
                    <strong id="slaStepSummaryTitle">1. SLA Simples</strong>
                    <span id="slaStepSummaryText">Entidade, calendário e pacote SLM.</span>
                </div>

                <div class="settings-step-panel" data-sla-step-panel="base">
                    <div class="settings-row">
                        <div>
                            <div class="settings-label">Entidade</div>
                            <div class="settings-help">Selecione uma entidade existente ou crie uma nova antes de gerar o pacote de SLA.</div>
                        </div>
                        <div>
                            <div class="sla-inline">
                                <select class="settings-select" id="slaEntityMode" name="entity_mode">
                                    <option value="existing">Selecionar entidade existente</option>
                                    <option value="new">+ Nova entidade</option>
                                    <option value="edit">Editar entidade existente</option>
                                </select>
                                <span class="sla-status-pill" data-sla-status="entity">Pendente</span>
                            </div>

                            <div class="sla-new-panel active" id="slaEntityExistingPanel">
                                <select class="settings-select" id="slaEntityId" name="entities_id">
                                    <option value="0" <?= (int) $slaSettings['entities_id'] === 0 ? 'selected' : '' ?>>Entidade raiz</option>
                                    <?php foreach ($slaEntities as $entity): ?>
                                        <option value="<?= (int) $entity['id'] ?>" <?= (int) $slaSettings['entities_id'] === (int) $entity['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) ($entity['completename'] ?: $entity['name']), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sla-new-panel" id="slaEntityNewPanel">
                                <div class="sla-two-cols">
                                    <input
                                        class="settings-input"
                                        id="slaEntityName"
                                        name="entity_new_name"
                                        type="text"
                                        value="<?= htmlspecialchars((string) $slaSettings['entity_new_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Nome da nova entidade"
                                    >
                                    <select class="settings-select" id="slaEntityParentId" name="entity_parent_id">
                                        <option value="0">Sem entidade pai</option>
                                        <?php foreach ($slaEntities as $entity): ?>
                                            <option value="<?= (int) $entity['id'] ?>" <?= (int) $slaSettings['entity_parent_id'] === (int) $entity['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) ($entity['completename'] ?: $entity['name']), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-row">
                        <div>
                            <div class="settings-label">Calendário</div>
                            <div class="settings-help">Use um calendário existente ou crie um calendário comercial simples com grade semanal.</div>
                        </div>
                        <div>
                            <div class="sla-inline">
                                <select class="settings-select" id="slaCalendarMode" name="calendar_mode">
                                    <option value="existing">Selecionar calendário existente</option>
                                    <option value="new">+ Novo calendário</option>
                                    <option value="edit">Editar calendário existente</option>
                                </select>
                                <span class="sla-status-pill" data-sla-status="calendar">Pendente</span>
                            </div>

                            <div class="sla-new-panel active" id="slaCalendarExistingPanel">
                                <select class="settings-select" id="slaCalendarId" name="calendar_id">
                                    <option value="0">Selecione um calendário</option>
                                    <?php foreach ($slaCalendars as $calendar): ?>
                                        <option value="<?= (int) $calendar['id'] ?>" <?= (int) $slaSettings['calendar_id'] === (int) $calendar['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) $calendar['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sla-new-panel" id="slaCalendarNewPanel">
                                <div class="sla-two-cols">
                                    <input
                                        class="settings-input"
                                        id="slaCalendarName"
                                        name="calendar_new_name"
                                        type="text"
                                        value="<?= htmlspecialchars((string) $slaSettings['calendar_new_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="Nome do novo calendário"
                                    >
                                    <label class="settings-option" style="min-height: 48px; padding: 12px;">
                                        <input type="checkbox" id="slaCalendarRecursive" name="calendar_is_recursive" value="1" <?= !empty($slaSettings['calendar_is_recursive']) ? 'checked' : '' ?>>
                                        <span>
                                            <strong>Recursivo</strong>
                                        </span>
                                    </label>
                                </div>

                                <div class="sla-calendar-grid">
                                    <?php foreach ($slaDayLabels as $day => $label): ?>
                                        <?php $segment = $slaSettings['calendar_segments'][$day] ?? ['enabled' => false, 'begin' => '08:00', 'end' => '18:00']; ?>
                                        <div class="sla-calendar-day">
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    name="calendar_day_enabled[<?= (int) $day ?>]"
                                                    value="1"
                                                    <?= !empty($segment['enabled']) ? 'checked' : '' ?>
                                                >
                                                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                            </label>
                                            <input class="settings-time sla-clock-input" type="text" inputmode="numeric" maxlength="5" name="calendar_day_begin[<?= (int) $day ?>]" value="<?= htmlspecialchars((string) $segment['begin'], ENT_QUOTES, 'UTF-8') ?>" placeholder="00:00">
                                            <input class="settings-time sla-clock-input" type="text" inputmode="numeric" maxlength="5" name="calendar_day_end[<?= (int) $day ?>]" value="<?= htmlspecialchars((string) $segment['end'], ENT_QUOTES, 'UTF-8') ?>" placeholder="23:59">
                                            <button class="sla-replicate-btn" type="button" data-replicate-calendar-day title="Replicar horários para todos os dias">
                                                <i class="fas fa-copy"></i>
                                                <span>Replicar</span>
                                            </button>
                                            <span class="sla-mode-note">Período comercial</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-row">
                        <div>
                            <div class="settings-label">Pacote SLM</div>
                            <div class="settings-help">Agrupador oficial do GLPI onde os SLAs serão criados.</div>
                        </div>
                        <div class="sla-inline">
                            <input
                                class="settings-input"
                                name="slm_name"
                                type="text"
                                value="<?= htmlspecialchars((string) $slaSettings['slm_name'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="SLA Simplificado - Nome da entidade"
                            >
                            <span class="sla-status-pill" data-sla-status="slm">Pendente</span>
                        </div>
                    </div>
                </div>

                <div class="settings-step-panel" data-sla-step-panel="tto" hidden>
                    <div class="settings-row">
                        <div>
                            <div class="settings-label">TTO</div>
                            <div class="settings-help">Time to Own: tempo máximo para assumir/atribuir o chamado.</div>
                        </div>
                        <div class="sla-list">
                            <?php foreach (dashglpi_sla_keys_from_settings($slaSettings, 'TTO') as $key): ?>
                                <?php $sla = $slaSettings['slas'][$key]; ?>
                                <div class="sla-list-row">
                                    <div class="sla-row-title">
                                        <span><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></span>
                                        <small>Tempo para atribuir</small>
                                    </div>
                                    <div class="sla-time-control">
                                        <input class="settings-number" type="number" min="1" max="1000" name="sla_number[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" value="<?= (int) $sla['number_time'] ?>">
                                        <select class="settings-select" name="sla_unit[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]">
                                            <?php foreach ($slaTimeUnits as $unit => $label): ?>
                                                <option value="<?= $unit ?>" <?= $sla['definition_time'] === $unit ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <span class="sla-status-pill" data-sla-status="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">Pendente</span>
                                </div>
                            <?php endforeach; ?>
                            <button class="settings-add-row-btn" type="button" data-add-sla-kind="TTO">
                                <i class="fas fa-plus"></i>
                                <span>Novo Registro</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="settings-step-panel" data-sla-step-panel="ttr" hidden>
                    <div class="settings-row">
                        <div>
                            <div class="settings-label">TTR</div>
                            <div class="settings-help">Time to Resolve: tempo máximo para resolver o chamado.</div>
                        </div>
                        <div class="sla-list">
                            <?php foreach (dashglpi_sla_keys_from_settings($slaSettings, 'TTR') as $key): ?>
                                <?php $sla = $slaSettings['slas'][$key]; ?>
                                <div class="sla-list-row">
                                    <div class="sla-row-title">
                                        <span><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></span>
                                        <small>Tempo para resolver</small>
                                    </div>
                                    <div class="sla-time-control">
                                        <input class="settings-number" type="number" min="1" max="1000" name="sla_number[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" value="<?= (int) $sla['number_time'] ?>">
                                        <select class="settings-select" name="sla_unit[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]">
                                            <?php foreach ($slaTimeUnits as $unit => $label): ?>
                                                <option value="<?= $unit ?>" <?= $sla['definition_time'] === $unit ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <span class="sla-status-pill" data-sla-status="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">Pendente</span>
                                </div>
                            <?php endforeach; ?>
                            <button class="settings-add-row-btn" type="button" data-add-sla-kind="TTR">
                                <i class="fas fa-plus"></i>
                                <span>Novo Registro</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="settings-step-panel" data-sla-step-panel="apply" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Aplicação automática</div>
                                <h3>Valide, crie e reaplique os SLAs base</h3>
                            </div>
                        </div>
                        <div class="settings-callout" id="slaBaseCallout">
                            <strong id="slaBaseCalloutTitle">Aguardando leitura do fluxo base</strong>
                            <span id="slaBaseCalloutText">O Dash vai mostrar aqui se entidade, calendário, SLM, SLAs e regras gerenciadas já foram persistidos.</span>
                        </div>
                        <div class="settings-actions">
                            <button class="settings-btn" type="button" id="validateSla">
                                <i class="fas fa-check-circle"></i>
                                <span>Validar no GLPI</span>
                            </button>
                            <button class="settings-btn success" type="button" id="applySla">
                                <i class="fas fa-magic"></i>
                                <span>Criar/Atualizar no GLPI</span>
                            </button>
                            <button class="settings-btn" type="button" id="reapplySla">
                                <i class="fas fa-sync-alt"></i>
                                <span>Reaplicar em abertos</span>
                            </button>
                        </div>
                        <div class="settings-status" id="slaStatus"></div>
                        <div class="table-responsive">
                            <table class="settings-table">
                                <thead>
                                    <tr>
                                        <th>Regra</th>
                                        <th>Prioridade</th>
                                        <th>TTO</th>
                                        <th>TTR</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="slaRulesStatusTable">
                                    <tr><td colspan="5">Carregando regras gerenciadas...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="settings-step-panel" data-sla-step-panel="alerts" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Alerta SLA</div>
                                <h3>Lembrete automático antes do vencimento do TTR</h3>
                            </div>
                        </div>
                        <div class="settings-callout" id="slaReminderCallout">
                            <strong id="slaReminderCalloutTitle">O alerta depende dos TTR já criados</strong>
                            <span id="slaReminderCalloutText">Primeiro aplique o passo 4. Depois configure quanto tempo antes do vencimento cada prioridade deve avisar.</span>
                        </div>
                        <div class="table-responsive">
                            <table class="settings-table">
                                <thead>
                                    <tr>
                                        <th>Chave</th>
                                        <th>TTR base</th>
                                        <th>Ativo</th>
                                        <th>Avisar antes</th>
                                        <th>Unidade</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (dashglpi_sla_keys_from_settings($slaSettings, 'TTR') as $key): ?>
                                        <?php $reminder = $slaSettings['reminders'][$key] ?? ['is_active' => 0, 'offset_value' => 10, 'offset_unit' => 'minute']; ?>
                                        <tr data-sla-reminder-row="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                            <td><strong><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></strong></td>
                                            <td data-sla-reminder-base="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">-</td>
                                            <td>
                                                <label class="settings-inline-check" for="slaReminderActive<?= htmlspecialchars(str_replace('-', '', $key), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input
                                                        id="slaReminderActive<?= htmlspecialchars(str_replace('-', '', $key), ENT_QUOTES, 'UTF-8') ?>"
                                                        type="checkbox"
                                                        name="reminders[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][is_active]"
                                                        value="1"
                                                        <?= !empty($reminder['is_active']) ? 'checked' : '' ?>
                                                    >
                                                    <span>Enviar</span>
                                                </label>
                                            </td>
                                            <td>
                                                <input
                                                    class="settings-number"
                                                    type="number"
                                                    min="1"
                                                    max="1000"
                                                    name="reminders[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][offset_value]"
                                                    value="<?= (int) ($reminder['offset_value'] ?? 10) ?>"
                                                >
                                            </td>
                                            <td>
                                                <select class="settings-select" name="reminders[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][offset_unit]">
                                                    <?php foreach ($slaTimeUnits as $unit => $label): ?>
                                                        <option value="<?= $unit ?>" <?= (string) ($reminder['offset_unit'] ?? 'minute') === $unit ? 'selected' : '' ?>><?= $label ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <span class="sla-status-pill" data-sla-reminder-status="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">Pendente</span>
                                                <div class="settings-help" data-sla-reminder-hint="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="settings-actions">
                            <button class="settings-btn primary" type="button" id="applySlaReminders">
                                <i class="fas fa-bell"></i>
                                <span>Aplicar alertas TTR</span>
                            </button>
                        </div>
                        <div class="settings-status" id="slaReminderStatus"></div>
                    </div>
                </div>

                <div class="settings-step-panel" data-sla-step-panel="notification" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Notificação SLA</div>
                                <h3>Template compartilhado, gatilho fixo e destinatários reais</h3>
                            </div>
                            <div class="settings-native-links">
                                <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/notification.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-bell"></i> Notificações GLPI</a>
                                <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/notificationtemplate.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-file-alt"></i> Modelos GLPI</a>
                            </div>
                        </div>
                        <div class="settings-callout" id="slaNotificationCallout">
                            <strong id="slaNotificationCalloutTitle">Carregando evento especial de SLA</strong>
                            <span id="slaNotificationCalloutText">O itemtype fica fixo em Chamado e o evento é resolvido dinamicamente a partir do catálogo do GLPI.</span>
                        </div>
                        <div class="settings-guide-grid">
                            <div class="settings-guide-item">
                                <strong>Itemtype</strong>
                                <span id="slaNotificationItemtypeLabel">Chamado</span>
                            </div>
                            <div class="settings-guide-item">
                                <strong>Evento</strong>
                                <span id="slaNotificationEventLabel">Carregando...</span>
                            </div>
                            <div class="settings-guide-item">
                                <strong>Template</strong>
                                <span id="slaNotificationTemplateSummary">Ainda não associado.</span>
                            </div>
                        </div>
                        <div class="sla-two-cols">
                            <input class="settings-input" id="slaNotificationName" name="sla_notification[name]" type="text" value="<?= htmlspecialchars((string) ($slaSettings['sla_notification']['name'] ?? 'SLA'), ENT_QUOTES, 'UTF-8') ?>" placeholder="Nome da notificação SLA">
                            <select class="settings-select" id="slaNotificationEntity" name="sla_notification[entities_id]">
                                <option value="0" <?= (int) ($slaSettings['sla_notification']['entities_id'] ?? 0) === 0 ? 'selected' : '' ?>>Entidade raiz</option>
                                <?php foreach ($slaEntities as $entity): ?>
                                    <option value="<?= (int) $entity['id'] ?>" <?= (int) ($slaSettings['sla_notification']['entities_id'] ?? 0) === (int) $entity['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) ($entity['completename'] ?: $entity['name']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input class="settings-input" id="slaNotificationTemplateName" name="sla_notification[template_name]" type="text" value="<?= htmlspecialchars((string) ($slaSettings['sla_notification']['template_name'] ?? 'Notificação por E-mail SLA'), ENT_QUOTES, 'UTF-8') ?>" placeholder="Nome do template">
                            <input class="settings-input" id="slaNotificationTemplateLanguage" name="sla_notification[template_language]" type="text" maxlength="10" value="<?= htmlspecialchars((string) ($slaSettings['sla_notification']['template_language'] ?? 'pt_BR'), ENT_QUOTES, 'UTF-8') ?>" placeholder="pt_BR">
                            <input class="settings-input" id="slaNotificationTemplateSubject" name="sla_notification[template_subject]" type="text" maxlength="255" value="<?= htmlspecialchars((string) ($slaSettings['sla_notification']['template_subject'] ?? 'Notificação de SLA'), ENT_QUOTES, 'UTF-8') ?>" placeholder="Assunto técnico do template">
                            <label class="settings-option settings-option-inline">
                                <input type="checkbox" id="slaNotificationActive" name="sla_notification[is_active]" value="1" <?= !empty($slaSettings['sla_notification']['is_active']) ? 'checked' : '' ?>>
                                <span><strong>Notificação ativa</strong></span>
                            </label>
                        </div>
                        <label class="settings-option settings-option-inline" style="margin-top: 12px;">
                            <input type="checkbox" id="slaNotificationRecursive" name="sla_notification[is_recursive]" value="1" <?= !empty($slaSettings['sla_notification']['is_recursive']) ? 'checked' : '' ?>>
                            <span><strong>Entidades filhas: Sim</strong></span>
                        </label>
                        <input type="hidden" id="slaNotificationTemplateId" name="sla_notification[template_id]" value="<?= (int) ($slaSettings['sla_notification']['template_id'] ?? 0) ?>">
                        <input type="hidden" id="slaNotificationTemplateTranslationId" name="sla_notification[template_translation_id]" value="<?= (int) ($slaSettings['sla_notification']['template_translation_id'] ?? 0) ?>">
                        <input type="hidden" id="slaNotificationTemplateHtml" name="sla_notification[template_content_html]" value="<?= htmlspecialchars((string) ($slaSettings['sla_notification']['template_content_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="slaNotificationTemplateText" name="sla_notification[template_content_text]" value="<?= htmlspecialchars((string) ($slaSettings['sla_notification']['template_content_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="settings-block">
                            <div class="settings-block-header compact">
                                <div>
                                    <div class="settings-block-kicker">Modelo HTML</div>
                                    <h3>Edite o corpo compartilhado do e-mail SLA</h3>
                                </div>
                                <button class="settings-btn" type="button" id="openSlaTemplateEditor">
                                    <i class="fas fa-pen"></i>
                                    <span>Editar HTML</span>
                                </button>
                            </div>
                            <div class="settings-help" id="slaNotificationTemplateHint">O modal reutiliza o mesmo editor visual do fluxo geral de notificações.</div>
                        </div>

                        <div class="settings-block">
                            <div class="settings-block-header">
                                <div>
                                    <div class="settings-block-kicker">Destinatários</div>
                                    <h3>Selecione quem deve receber o lembrete de SLA</h3>
                                </div>
                            </div>
                            <div class="settings-recipient-layout">
                                <div class="settings-recipient-column">
                                    <label class="settings-searchbar" for="slaNotificationRecipientSearch">
                                        <i class="fas fa-search"></i>
                                        <input class="settings-input" id="slaNotificationRecipientSearch" type="search" placeholder="Filtrar destinatários do evento SLA">
                                    </label>
                                    <select class="settings-select settings-multiselect" id="slaNotificationRecipientCatalog" multiple size="10"></select>
                                    <div class="settings-actions" style="margin-top: 12px;">
                                        <button class="settings-btn" type="button" id="addSlaNotificationRecipients">
                                            <i class="fas fa-arrow-right"></i>
                                            <span>Adicionar selecionados</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="settings-recipient-column">
                                    <div class="settings-label">Destinatários selecionados</div>
                                    <div class="settings-help">Eles serão persistidos em <code>glpi_notificationtargets</code> da notificação compartilhada de SLA.</div>
                                    <div class="settings-pill-list" id="slaNotificationSelectedRecipients"></div>
                                    <div class="settings-actions" style="margin-top: 12px;">
                                        <button class="settings-btn" type="button" id="clearSlaNotificationRecipients">
                                            <i class="fas fa-eraser"></i>
                                            <span>Limpar destinatários</span>
                                        </button>
                                    </div>
                                    <div id="slaNotificationRecipientsHidden">
                                        <?php foreach ((array) ($slaSettings['sla_notification']['recipient_keys'] ?? []) as $recipientKey): ?>
                                            <input type="hidden" name="sla_notification[recipient_keys][]" value="<?= htmlspecialchars((string) $recipientKey, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="settings-actions" style="margin-top: 14px;">
                                <button class="settings-btn success" type="button" id="saveSlaNotification">
                                    <i class="fas fa-save"></i>
                                    <span>Salvar notificação SLA</span>
                                </button>
                            </div>
                            <div class="settings-status" id="slaNotificationStatus"></div>
                        </div>
                    </div>
                </div>

                <div class="settings-step-panel" data-sla-step-panel="automation" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Ações automáticas</div>
                                <h3>Garanta que o SLA e o envio de e-mails rodam sem atraso</h3>
                            </div>
                        </div>
                        <div class="settings-guide-grid">
                            <div class="settings-guide-item">
                                <strong>slaticket</strong>
                                <span id="slaAutomationSlaTask">Carregando status da ação automática de SLA.</span>
                            </div>
                            <div class="settings-guide-item">
                                <strong>queuednotification</strong>
                                <span id="slaAutomationQueueTask">Carregando status da fila de e-mails.</span>
                            </div>
                            <div class="settings-guide-item">
                                <strong>Recomendação</strong>
                                <span id="slaAutomationRecommendation">O ideal é manter ambos em Ativa + CLI + 60s.</span>
                            </div>
                        </div>
                        <div class="settings-callout" id="slaAutomationCallout">
                            <strong id="slaAutomationCalloutTitle">Aguardando leitura das ações automáticas</strong>
                            <span id="slaAutomationCalloutText">O Dash vai comparar `slaticket` e `queuednotification` com a configuração recomendada.</span>
                        </div>
                        <div class="settings-actions">
                            <button class="settings-btn primary" type="button" id="applySlaAutomation">
                                <i class="fas fa-bolt"></i>
                                <span>Aplicar recomendação</span>
                            </button>
                        </div>
                        <div class="settings-status" id="slaAutomationStatus"></div>
                    </div>
                </div>
            </section>

            <section class="glass-card settings-panel" data-settings-panel="general"<?= $settingsSection !== 'general' ? ' hidden' : '' ?>>
                <h2 class="settings-card-title"><i class="fas fa-sliders-h"></i> Gerais</h2>

                <div class="settings-row">
                    <div>
                        <div class="settings-label">Texto abaixo da logo</div>
                        <div class="settings-help">Define o texto exibido abaixo da logo na sidebar e o nome da aplicação.</div>
                    </div>
                    <div>
                        <input
                            class="settings-input"
                            id="appName"
                            name="app_name"
                            type="text"
                            maxlength="80"
                            value="<?= htmlspecialchars((string) $settings['app_name'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Fealq - GLPI"
                        >
                    </div>
                </div>

                <div class="settings-row">
                    <div>
                        <div class="settings-label">URL da logo - tema claro</div>
                        <div class="settings-help">Usada na sidebar quando o tema claro estiver ativo e nos materiais gerados pela aplicação.</div>
                    </div>
                    <div class="logo-config">
                        <div>
                            <input
                                class="settings-input"
                                id="logoLightUrl"
                                name="logo_light_url"
                                type="url"
                                value="<?= htmlspecialchars((string) ($settings['logo_light_url'] ?? $settings['logo_url']), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="https://..."
                            >
                            <div class="settings-help">Se ficar vazio ou inválido, o sistema volta para a logo padrão.</div>
                        </div>
                        <div class="logo-preview">
                            <img id="logoLightPreview" src="<?= htmlspecialchars((string) ($settings['logo_light_url'] ?? $settings['logo_url']), ENT_QUOTES, 'UTF-8') ?>" alt="Prévia da logo para tema claro">
                        </div>
                    </div>
                </div>

                <div class="settings-row">
                    <div>
                        <div class="settings-label">URL da logo - tema escuro</div>
                        <div class="settings-help">Usada na sidebar quando o tema escuro estiver ativo.</div>
                    </div>
                    <div class="logo-config">
                        <div>
                            <input
                                class="settings-input"
                                id="logoDarkUrl"
                                name="logo_dark_url"
                                type="url"
                                value="<?= htmlspecialchars((string) ($settings['logo_dark_url'] ?? $settings['logo_url']), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="https://..."
                            >
                            <div class="settings-help">Use uma versão com bom contraste para fundo escuro.</div>
                        </div>
                        <div class="logo-preview logo-preview-dark">
                            <img id="logoDarkPreview" src="<?= htmlspecialchars((string) ($settings['logo_dark_url'] ?? $settings['logo_url']), ENT_QUOTES, 'UTF-8') ?>" alt="Prévia da logo para tema escuro">
                        </div>
                    </div>
                </div>
            </section>

            <section class="glass-card settings-panel" data-settings-panel="reports"<?= $settingsSection !== 'reports' ? ' hidden' : '' ?>>
                <h2 class="settings-card-title"><i class="fas fa-file-alt"></i> Relatórios</h2>

                <div class="settings-row">
                    <div>
                        <div class="settings-label">PDF</div>
                        <div class="settings-help">Fluxo usado para gerar o arquivo enviado ao cliente.</div>
                    </div>
                    <div class="settings-options">
                        <label class="settings-option">
                            <input type="radio" name="pdf" value="browser" <?= $settings['pdf'] === 'browser' ? 'checked' : '' ?>>
                            <span>
                                <strong>Impressão do navegador</strong>
                                <span>Mantém o fluxo atual com o botão Imprimir / Salvar PDF.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="settings-row">
                    <div>
                        <div class="settings-label">Anexos de imagem</div>
                        <div class="settings-help">Controla se imagens anexadas ao chamado aparecem visualmente no relatório.</div>
                    </div>
                    <div class="settings-options">
                        <label class="settings-option">
                            <input type="radio" name="attachments" value="images_inline" <?= $settings['attachments'] === 'images_inline' ? 'checked' : '' ?>>
                            <span>
                                <strong>Mostrar imagens</strong>
                                <span>Exibe previews para PNG, JPG, GIF ou WebP.</span>
                            </span>
                        </label>
                        <label class="settings-option">
                            <input type="radio" name="attachments" value="table_only" <?= $settings['attachments'] === 'table_only' ? 'checked' : '' ?>>
                            <span>
                                <strong>Somente tabela</strong>
                                <span>Lista anexos apenas em formato textual.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="settings-row">
                    <div>
                        <div class="settings-label">Conteúdo de auditoria</div>
                        <div class="settings-help">Define o nível de evidência apresentado no relatório.</div>
                    </div>
                    <div class="settings-options">
                        <label class="settings-option">
                            <input type="radio" name="visibility" value="complete" <?= $settings['visibility'] === 'complete' ? 'checked' : '' ?>>
                            <span>
                                <strong>Completo</strong>
                                <span>Inclui timeline completa, itens privados e logs técnicos.</span>
                            </span>
                        </label>
                        <label class="settings-option">
                            <input type="radio" name="visibility" value="public_only" <?= $settings['visibility'] === 'public_only' ? 'checked' : '' ?>>
                            <span>
                                <strong>Sem privados</strong>
                                <span>Oculta eventos privados da timeline. Logs técnicos continuam disponíveis.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="glass-card settings-panel" data-settings-panel="profile_access"<?= $settingsSection !== 'profile_access' ? ' hidden' : '' ?>>
                <h2 class="settings-card-title"><i class="fas fa-user-shield"></i> Acesso por Perfil</h2>

                <div class="settings-callout">
                    <strong>Perfil efetivo por prioridade</strong>
                    <span>
                        O Dash usa os perfis do GLPI do usuário logado, aplica apenas regras ativas configuradas aqui e escolhe a de menor prioridade.
                        Se não existir regra para nenhum perfil do usuário, o comportamento atual do Dash continua valendo.
                    </span>
                </div>

                <div class="settings-kpi-grid settings-kpi-grid-four" style="margin-top: 18px;">
                    <div class="settings-kpi">
                        <div class="settings-kpi-value" id="profileAccessProfilesTotal">0</div>
                        <div class="settings-kpi-label">Perfis GLPI</div>
                    </div>
                    <div class="settings-kpi">
                        <div class="settings-kpi-value" id="profileAccessRulesTotal">0</div>
                        <div class="settings-kpi-label">Regras cadastradas</div>
                    </div>
                    <div class="settings-kpi">
                        <div class="settings-kpi-value" id="profileAccessActiveRulesTotal">0</div>
                        <div class="settings-kpi-label">Regras ativas</div>
                    </div>
                    <div class="settings-kpi">
                        <div class="settings-kpi-value" id="profileAccessPagesTotal">5</div>
                        <div class="settings-kpi-label">Páginas V1</div>
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-header">
                        <div>
                            <div class="settings-block-kicker">Lista de perfis</div>
                            <h3>Selecione um perfil GLPI e edite apenas a regra dele</h3>
                        </div>
                        <div class="settings-actions">
                            <button class="settings-btn" type="button" id="reloadProfileAccess">
                                <i class="fas fa-rotate-right"></i>
                                <span>Recarregar</span>
                            </button>
                            <button class="settings-btn primary" type="button" id="saveProfileAccess">
                                <i class="fas fa-save"></i>
                                <span>Salvar regras</span>
                            </button>
                        </div>
                    </div>

                    <div class="settings-toolbar soft">
                        <div class="settings-toolbar-copy">
                            <strong>Lista</strong>
                            <span>Escolha o perfil GLPI que deseja configurar no Dash.</span>
                        </div>
                        <div class="profile-access-picker">
                            <label class="settings-field-label" for="profileAccessSelect">Perfil GLPI</label>
                            <select class="settings-select" id="profileAccessSelect">
                                <option value="">Carregando perfis...</option>
                            </select>
                        </div>
                    </div>

                    <div class="profile-access-current" id="profileAccessCurrent">
                        <strong>Nenhum perfil selecionado</strong>
                        <span>Selecione um perfil na lista para editar sua regra.</span>
                    </div>

                    <div class="profile-access-table-wrap">
                        <table class="custom-table profile-access-table">
                            <thead>
                                <tr>
                                    <th>Regra ativa</th>
                                    <th>Prioridade</th>
                                    <th>Páginas liberadas</th>
                                    <th>Campos / filtros</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="profileAccessTableBody">
                                <tr>
                                    <td colspan="5" class="profile-access-empty">Carregando perfil selecionado...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="profile-access-rule-note">
                        Menor número vence. Regras ativas não podem compartilhar a mesma prioridade. Uma regra ativa precisa ter ao menos uma página marcada.
                    </div>
                    <div class="settings-status" id="profileAccessStatus"></div>
                </div>
            </section>

            <section class="glass-card settings-panel" data-settings-panel="notifications"<?= $settingsSection !== 'notifications' ? ' hidden' : '' ?>>
                <h2 class="settings-card-title"><i class="fas fa-bell"></i> Notificações</h2>
                <div class="settings-stepbar" aria-label="Fluxo de notificações">
                    <button class="settings-stepbtn active" type="button" data-notification-step-target="setup">
                        <span class="settings-step-number">1</span>
                        <strong>Configurações iniciais</strong>
                        <span>Conta de disparo, fluxo e atalhos.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-notification-step-target="mail">
                        <span class="settings-step-number">2</span>
                        <strong>Configuração de e-mail</strong>
                        <span>SMTP, OAuth, retries e teste.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-notification-step-target="notifications">
                        <span class="settings-step-number">3</span>
                        <strong>Notificações</strong>
                        <span>Lista, cadastro, filtros e ação em massa.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-notification-step-target="templates">
                        <span class="settings-step-number">4</span>
                        <strong>Modelos</strong>
                        <span>HTML, preview e edição visual.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-notification-step-target="queue">
                        <span class="settings-step-number">5</span>
                        <strong>Automação da fila</strong>
                        <span>queuednotification, CLI e frequência.</span>
                    </button>
                    <button class="settings-stepbtn" type="button" data-notification-step-target="cleanup">
                        <span class="settings-step-number">6</span>
                        <strong>Limpeza padrão</strong>
                        <span>Chamados ativos e revisão em lote.</span>
                    </button>
                </div>

                <div class="settings-step-panel" data-notification-step-panel="setup">
                    <div class="settings-guide-card">
                        <div class="settings-guide-head">
                            <div class="settings-guide-kicker">Configurações iniciais</div>
                            <h3>Prepare o fluxo antes de liberar os gatilhos</h3>
                            <p>Defina a conta de disparo, valide o SMTP, ajuste a fila <strong>queuednotification</strong> e só então revise as notificações e modelos que vão entrar em produção.</p>
                        </div>
                        <div class="settings-guide-grid">
                            <div class="settings-guide-item">
                                <strong>1. Conta de disparo</strong>
                                <span>Configure credenciais do provedor e valide com e-mail de teste para o administrador.</span>
                            </div>
                            <div class="settings-guide-item">
                                <strong>2. Gatilhos e modelos</strong>
                                <span>Revise primeiro o que já existe, depois crie ou edite regras novas com destinatários reais.</span>
                            </div>
                            <div class="settings-guide-item">
                                <strong>3. Limpeza controlada</strong>
                                <span>Desative em lote apenas após filtrar e revisar o conjunto visível de notificações padrão.</span>
                            </div>
                        </div>
                    </div>

                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Atalhos GLPI</div>
                                <h3>Acesse as telas nativas para autorização OAuth e ajuste fino</h3>
                            </div>
                        </div>
                        <div class="settings-native-links">
                            <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/setup.notification.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-sliders-h"></i> Configuração</a>
                            <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/notification.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-bell"></i> Notificações</a>
                            <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/notificationtemplate.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-file-alt"></i> Modelos</a>
                            <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/notificationmailingsetting.form.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-envelope"></i> E-mail</a>
                            <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/crontask.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-stopwatch"></i> Ações automáticas</a>
                        </div>
                    </div>
                </div>

                <div class="settings-step-panel" data-notification-step-panel="mail" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Configuração de e-mail</div>
                                <h3>E-mail e SMTP com foco no disparo</h3>
                            </div>
                        </div>

                        <div class="settings-callout" id="mailPersistedStateCallout">
                            <strong id="mailPersistedStateTitle">O teste usa a configuração já salva no GLPI.</strong>
                            <span id="mailPersistedStateSummary">Carregando snapshot persistido do GLPI.</span>
                        </div>

                        <details class="settings-accordion" id="mailConfigurationAccordion" open>
                            <summary>Configuração de e-mail</summary>
                            <div class="settings-accordion-body">
                                <div class="sla-two-cols">
                                    <input class="settings-input" id="mailAdminEmail" type="email" placeholder="E-mail do administrador">
                                    <input class="settings-input" id="mailAdminName" type="text" placeholder="Nome do administrador">
                                    <input class="settings-input" id="mailFromEmail" type="email" placeholder="E-mail do remetente">
                                    <input class="settings-input" id="mailFromName" type="text" placeholder="Nome do remetente">
                                    <input class="settings-input" id="mailReplyEmail" type="email" placeholder="Reply-To">
                                    <input class="settings-input" id="mailReplyName" type="text" placeholder="Nome do Reply-To">
                                    <input class="settings-input" id="mailNoReplyEmail" type="email" placeholder="No-Reply">
                                    <input class="settings-input" id="mailNoReplyName" type="text" placeholder="Nome do No-Reply">
                                    <select class="settings-select" id="mailSmtpMode">
                                        <option value="0">PHP</option>
                                        <option value="1">SMTP</option>
                                        <option value="4">SMTP+OAUTH</option>
                                    </select>
                                    <input class="settings-input" id="mailRetryTime" type="number" min="0" max="1440" placeholder="Reprocessar fila em minutos">
                                    <input class="settings-input" id="mailMaxRetries" type="number" min="0" max="50" placeholder="Máx. de tentativas">
                                </div>
                                <div class="settings-help" id="mailSenderLoginRuleStatus">Quando o login SMTP for um e-mail, use o mesmo valor em E-mail do remetente para evitar falhas de autenticacao e envio.</div>
                                <div class="sla-two-cols" style="margin-top: 12px;">
                                    <select class="settings-select" id="mailAttachTicketDocuments">
                                        <option value="0">Nenhum documento nas notificações de chamado</option>
                                        <option value="1">Todos os documentos nas notificações de chamado</option>
                                        <option value="2">Somente documentos relacionados ao item que aciona o evento</option>
                                    </select>
                                    <select class="settings-select" id="mailAttachAnonymousDocuments">
                                        <option value="0">Notificações anônimas: não adicionar documentos</option>
                                        <option value="1">Notificações anônimas: adicionar documentos</option>
                                    </select>
                                </div>
                                <textarea class="settings-textarea" id="mailSignature" rows="5" placeholder="Assinatura de e-mail" style="margin-top: 12px;"></textarea>
                            </div>
                        </details>

                        <details class="settings-accordion" id="mailSmtpAccordion">
                            <summary>Servidor SMTP</summary>
                            <div class="settings-accordion-body">
                                <div class="sla-two-cols">
                                    <select class="settings-select" id="mailSmtpCheckCertificate">
                                        <option value="1">Verificar certificado: Sim</option>
                                        <option value="0">Verificar certificado: Não</option>
                                    </select>
                                    <input class="settings-input" id="mailSmtpHost" type="text" placeholder="Servidor SMTP">
                                    <input class="settings-input" id="mailSmtpPort" type="number" min="0" max="65535" placeholder="Porta">
                                    <input class="settings-input" id="mailSmtpSender" type="email" placeholder="Remetente SMTP">
                                </div>

                                <div class="sla-two-cols" id="mailSmtpCredentialsFields" style="margin-top: 12px;">
                                    <input class="settings-input" id="mailSmtpUsername" type="text" placeholder="Login SMTP (opcional)">
                                    <div class="settings-inline-stack">
                                        <input class="settings-input" id="mailSmtpPassword" type="password" placeholder="Senha SMTP (opcional)" autocomplete="new-password">
                                        <label class="settings-inline-check" for="mailClearSmtpPassword">
                                            <input type="checkbox" id="mailClearSmtpPassword">
                                            <span>Limpar senha atual</span>
                                        </label>
                                        <div class="settings-help" id="mailSmtpPasswordHint"></div>
                                    </div>
                                </div>

                                <div id="mailOauthFields" hidden>
                                    <div class="sla-two-cols" style="margin-top: 12px;">
                                        <select class="settings-select" id="mailSmtpOauthProvider"></select>
                                        <input class="settings-input" id="mailSmtpOauthClientId" type="text" placeholder="Client ID">
                                        <div class="settings-inline-stack">
                                            <input class="settings-input" id="mailSmtpOauthClientSecret" type="password" placeholder="Client Secret" autocomplete="new-password">
                                            <div class="settings-help" id="mailSmtpOauthClientSecretHint"></div>
                                        </div>
                                        <input class="settings-input" id="mailSmtpOauthCallbackUrl" type="text" readonly placeholder="Callback URL">
                                    </div>
                                    <div class="settings-help" id="mailOauthRefreshHint"></div>
                                    <div class="sla-two-cols" id="mailOauthAdditionalFields" style="margin-top: 12px;"></div>
                                </div>
                            </div>
                        </details>

                        <div class="settings-actions" style="margin-top: 14px;">
                            <button class="settings-btn success" type="button" id="saveNotificationMail">
                                <i class="fas fa-save"></i>
                                <span>Salvar configuração</span>
                            </button>
                            <button class="settings-btn" type="button" id="reloadNotificationMail">
                                <i class="fas fa-rotate-right"></i>
                                <span>Recarregar do GLPI</span>
                            </button>
                            <button class="settings-btn" type="button" id="sendNotificationTestEmail">
                                <i class="fas fa-paper-plane"></i>
                                <span>Enviar e-mail de teste</span>
                            </button>
                        </div>
                        <div class="settings-status" id="notificationMailStatus"></div>
                    </div>
                </div>

                <div class="settings-step-panel" data-notification-step-panel="notifications" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Notificações</div>
                                <h3>Lista operacional, cadastro e revisão em massa</h3>
                            </div>
                        </div>

                        <div class="settings-view-panel" id="notificationListView">
                            <div class="settings-toolbar soft">
                                <div class="settings-toolbar-copy">
                                    <strong>Revise por coluna antes de agir em lote</strong>
                                    <span>Use os filtros na grade, selecione as linhas visíveis e só então desative o conjunto desejado.</span>
                                </div>
                                <div class="settings-actions">
                                    <button class="settings-btn primary" type="button" id="openNotificationCreate">
                                        <i class="fas fa-plus"></i>
                                        <span>Nova notificação</span>
                                    </button>
                                    <button class="settings-btn" type="button" id="notificationSelectVisible">
                                        <i class="fas fa-check-double"></i>
                                        <span>Selecionar todos</span>
                                    </button>
                                    <button class="settings-btn" type="button" id="notificationDisableSelected">
                                        <i class="fas fa-ban"></i>
                                        <span>Desativar selecionados</span>
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="settings-table">
                                    <thead>
                                        <tr>
                                            <th><input id="notificationSelectAll" type="checkbox" aria-label="Selecionar visíveis"></th>
                                            <th>Nome</th>
                                            <th>Tipo</th>
                                            <th>Evento</th>
                                            <th>Entidade</th>
                                            <th>Modelos</th>
                                            <th>Status</th>
                                            <th>Ação</th>
                                        </tr>
                                        <tr class="settings-table-filters">
                                            <th></th>
                                            <th><input class="settings-table-filter" id="notificationFilterName" type="search" placeholder="Nome"></th>
                                            <th><select class="settings-table-filter" id="notificationFilterType"><option value="">Todos</option></select></th>
                                            <th><select class="settings-table-filter" id="notificationFilterEvent"><option value="">Todos</option></select></th>
                                            <th><select class="settings-table-filter" id="notificationFilterEntity"><option value="">Todas</option></select></th>
                                            <th><select class="settings-table-filter" id="notificationFilterTemplate"><option value="">Todos</option></select></th>
                                            <th><select class="settings-table-filter" id="notificationFilterStatus"><option value="">Todos</option><option value="1" selected>Ativo</option><option value="0">Inativo</option></select></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="notificationsTable">
                                        <tr><td colspan="8">Carregando notificações...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="settings-status" id="notificationListStatus"></div>
                        </div>

                        <div class="settings-view-panel" id="notificationEditorView" hidden>
                            <div class="settings-editor-shell">
                                <div class="settings-toolbar soft">
                                    <div class="settings-toolbar-copy">
                                        <strong>Cadastro da notificação</strong>
                                        <span>Escolha o gatilho, associe o modelo e selecione quem realmente deve receber esse disparo.</span>
                                    </div>
                                    <button class="settings-back-btn" type="button" id="backToNotificationList">
                                        <i class="fas fa-arrow-left"></i>
                                        <span>Voltar</span>
                                    </button>
                                </div>

                                <div class="settings-block">
                                    <input id="notificationId" type="hidden" value="0">
                                    <div class="sla-two-cols">
                                        <input class="settings-input" id="notificationName" type="text" placeholder="Nome da notificação">
                                        <select class="settings-select" id="notificationItemtype"></select>
                                        <select class="settings-select" id="notificationEvent"></select>
                                        <select class="settings-select" id="notificationEntity"></select>
                                        <select class="settings-select" id="notificationTemplate"></select>
                                        <label class="settings-option settings-option-inline">
                                            <input type="checkbox" id="notificationActive" checked>
                                            <span><strong>Ativa</strong></span>
                                        </label>
                                    </div>
                                    <label class="settings-option settings-option-inline" style="margin-top: 12px;">
                                        <input type="checkbox" id="notificationRecursive" checked>
                                        <span><strong>Entidades filhas: Sim</strong></span>
                                    </label>
                                </div>

                                <div class="settings-block">
                                    <div class="settings-block-header">
                                        <div>
                                            <div class="settings-block-kicker">Destinatários</div>
                                            <h3>Catálogo real do GLPI por tipo e evento</h3>
                                        </div>
                                    </div>
                                    <div class="settings-recipient-layout">
                                        <div class="settings-recipient-column">
                                            <label class="settings-searchbar" for="recipientCatalogSearch">
                                                <i class="fas fa-search"></i>
                                                <input class="settings-input" id="recipientCatalogSearch" type="search" placeholder="Filtrar destinatários disponíveis">
                                            </label>
                                            <select class="settings-select settings-multiselect" id="notificationRecipientCatalog" multiple size="10"></select>
                                            <div class="settings-actions" style="margin-top: 12px;">
                                                <button class="settings-btn" type="button" id="addNotificationRecipients">
                                                    <i class="fas fa-arrow-right"></i>
                                                    <span>Adicionar selecionados</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="settings-recipient-column">
                                            <div class="settings-label">Destinatários selecionados</div>
                                            <div class="settings-help">Estes recebedores serão persistidos em <code>glpi_notificationtargets</code> para o gatilho atual.</div>
                                            <div class="settings-pill-list" id="notificationSelectedRecipients"></div>
                                            <div class="settings-actions" style="margin-top: 12px;">
                                                <button class="settings-btn" type="button" id="clearNotificationRecipients">
                                                    <i class="fas fa-eraser"></i>
                                                    <span>Limpar destinatários</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="settings-actions" style="margin-top: 14px;">
                                        <button class="settings-btn success" type="button" id="saveNotification">
                                            <i class="fas fa-save"></i>
                                            <span id="saveNotificationLabel">Salvar notificação</span>
                                        </button>
                                        <button class="settings-btn" type="button" id="resetNotificationForm">
                                            <i class="fas fa-undo"></i>
                                            <span>Limpar cadastro</span>
                                        </button>
                                    </div>
                                    <div class="settings-status" id="notificationStatus"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-step-panel" data-notification-step-panel="templates" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header compact">
                            <div>
                                <div class="settings-block-kicker">Modelos</div>
                                <h3>Catálogo visual do corpo do e-mail</h3>
                            </div>
                            <label class="settings-searchbar" for="notificationTemplateSearch">
                                <i class="fas fa-search"></i>
                                <input class="settings-input" id="notificationTemplateSearch" type="search" placeholder="Pesquisar por ID, modelo, tipo, idioma ou assunto">
                            </label>
                        </div>
                        <div class="table-responsive">
                            <table class="settings-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Modelo</th>
                                        <th>Tipo</th>
                                        <th>Idioma</th>
                                        <th>Assunto</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="notificationTemplatesTable">
                                    <tr><td colspan="6">Carregando modelos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="settings-step-panel" data-notification-step-panel="queue" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Automação da fila</div>
                                <h3>Fila de e-mails e ação automática</h3>
                            </div>
                        </div>
                        <div class="settings-kpi-grid settings-kpi-grid-four">
                            <div class="settings-kpi">
                                <div class="settings-kpi-value" id="notificationQueueModeValue">-</div>
                                <div class="settings-kpi-label">Modo de execução</div>
                            </div>
                            <div class="settings-kpi">
                                <div class="settings-kpi-value" id="notificationQueueFrequencyValue">-</div>
                                <div class="settings-kpi-label">Frequência</div>
                            </div>
                            <div class="settings-kpi">
                                <div class="settings-kpi-value" id="notificationQueuePendingValue">-</div>
                                <div class="settings-kpi-label">Pendentes</div>
                            </div>
                            <div class="settings-kpi">
                                <div class="settings-kpi-value" id="notificationQueueLastRunValue">-</div>
                                <div class="settings-kpi-label">Última execução</div>
                            </div>
                        </div>
                        <div class="settings-callout" id="notificationQueueCallout">
                            <strong id="notificationQueueCalloutTitle">Aguardando leitura da fila</strong>
                            <span id="notificationQueueCalloutText">Buscando status do queuednotification e da fila de e-mails.</span>
                        </div>
                        <div class="settings-actions">
                            <button class="settings-btn primary" type="button" id="applyQueuedNotificationRecommendation">
                                <i class="fas fa-bolt"></i>
                                <span>Aplicar recomendação: Ativa + CLI + 60s</span>
                            </button>
                        </div>
                        <div class="settings-status" id="notificationQueueStatus"></div>
                    </div>
                </div>

                <div class="settings-step-panel" data-notification-step-panel="cleanup" hidden>
                    <div class="settings-block">
                        <div class="settings-block-header">
                            <div>
                                <div class="settings-block-kicker">Limpeza das notificações padrão</div>
                                <h3>Chamado + ativo = revisão em massa</h3>
                            </div>
                            <label class="settings-searchbar" for="notificationCleanupSearch">
                                <i class="fas fa-search"></i>
                                <input class="settings-input" id="notificationCleanupSearch" type="search" placeholder="Pesquisar nas notificações de chamado ativas">
                            </label>
                        </div>
                        <div class="settings-actions" style="margin-bottom: 12px;">
                            <button class="settings-btn" type="button" id="bulkDisableNotifications">
                                <i class="fas fa-ban"></i>
                                <span>Desativar selecionadas</span>
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="settings-table">
                                <thead>
                                    <tr>
                                        <th><input id="cleanupSelectAll" type="checkbox" aria-label="Selecionar visíveis"></th>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Evento</th>
                                        <th>Modelos</th>
                                        <th>Destinatários</th>
                                    </tr>
                                </thead>
                                <tbody id="notificationCleanupTable">
                                    <tr><td colspan="6">Carregando notificações...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="settings-status" id="notificationCleanupStatus"></div>
                    </div>
                </div>
            </section>

            <section class="glass-card settings-panel" data-settings-panel="alerting"<?= $settingsSection !== 'alerting' ? ' hidden' : '' ?>>
                <h2 class="settings-card-title"><i class="fas fa-satellite-dish"></i> Canais de Alerta</h2>

                <input type="hidden" id="alertingCsrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="settings-guide-card">
                    <div class="settings-guide-head">
                        <div class="settings-guide-kicker">Monitor ativo de SLA</div>
                        <h3>Janela limite e canais de disparo</h3>
                        <p>O worker desacoplado varre chamados em status <strong>Novo</strong> sem técnico nem acompanhamento e dispara alertas quando a janela abaixo é ultrapassada. Os segredos (chave da Evolution e token do bot) não são reexibidos: deixe em branco para manter o valor atual.</p>
                    </div>

                    <div id="alertingSnapshot" class="settings-snapshot-row" style="display:flex;gap:16px;flex-wrap:wrap;margin:12px 0;">
                        <span class="badge bg-danger">Crítico: <b data-snap="critical">–</b></span>
                        <span class="badge bg-warning text-dark">Atenção: <b data-snap="warning">–</b></span>
                        <span class="badge bg-success">No prazo: <b data-snap="on_track">–</b></span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="alertingBreach">Limite de estouro (minutos)</label>
                            <input type="number" min="1" max="10080" class="form-control" id="alertingBreach" value="15">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alertingWarn">Aviso antecipado (minutos, 0 = desativado)</label>
                            <input type="number" min="0" max="10080" class="form-control" id="alertingWarn" value="0">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="alertingMaxAge">Idade máxima (horas, 0 = sem teto extra)</label>
                            <input type="number" min="0" max="8760" class="form-control" id="alertingMaxAge" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alertingSinceDate">Rodar desde de (data, vazio = sem piso)</label>
                            <input type="date" class="form-control" id="alertingSinceDate">
                        </div>
                    </div>
                    <p class="form-text" id="alertingCutoffPreview" style="margin-top:4px;"></p>

                    <hr>

                    <!-- Teams -->
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alertingTeamsEnabled">
                        <label class="form-check-label" for="alertingTeamsEnabled"><i class="fab fa-microsoft"></i> Microsoft Teams</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="alertingTeamsWebhook">Incoming Webhook URL</label>
                        <input type="url" class="form-control" id="alertingTeamsWebhook" placeholder="https://outlook.office.com/webhook/...">
                        <div class="settings-field-help">Usado pelos 3 workers automáticos legados (SLA/tarefa/solução). As regras dinâmicas usam as credenciais nomeadas abaixo.</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-test-channel="teams">Testar Teams</button>
                    </div>
                    <div class="settings-credentials-block" data-credentials-channel="teams">
                        <div class="settings-credentials-head">
                            <span>Credenciais nomeadas (Regras)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-credentials-new="teams">+ Nova credencial</button>
                        </div>
                        <div class="settings-credentials-list" data-credentials-list="teams"></div>
                    </div>

                    <hr>

                    <!-- WhatsApp -->
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alertingWaEnabled">
                        <label class="form-check-label" for="alertingWaEnabled"><i class="fab fa-whatsapp"></i> WhatsApp (Evolution API)</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="alertingWaUrl">URL da Evolution API</label>
                            <input type="url" class="form-control" id="alertingWaUrl" placeholder="http://kawa_evolutionapi:8080">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alertingWaInstance">Instância</label>
                            <input type="text" class="form-control" id="alertingWaInstance" placeholder="fealq">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alertingWaKey">API Key <small id="alertingWaKeyHint" class="text-muted"></small></label>
                            <input type="password" class="form-control" id="alertingWaKey" placeholder="deixe em branco para manter">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alertingWaRecipients">Números destino (um por linha, com DDI)</label>
                            <textarea class="form-control" id="alertingWaRecipients" rows="2" placeholder="5511999998888"></textarea>
                        </div>
                    </div>
                    <div class="settings-field-help">Campos acima usados pelos 3 workers automáticos legados. As regras dinâmicas usam as credenciais nomeadas abaixo.</div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-test-channel="whatsapp">Testar WhatsApp</button>
                    <div class="settings-credentials-block" data-credentials-channel="whatsapp">
                        <div class="settings-credentials-head">
                            <span>Credenciais nomeadas (Regras)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-credentials-new="whatsapp">+ Nova credencial</button>
                        </div>
                        <div class="settings-credentials-list" data-credentials-list="whatsapp"></div>
                    </div>

                    <hr>

                    <!-- Telegram -->
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alertingTgEnabled">
                        <label class="form-check-label" for="alertingTgEnabled"><i class="fab fa-telegram"></i> Telegram</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="alertingTgToken">Bot Token <small id="alertingTgTokenHint" class="text-muted"></small></label>
                            <input type="password" class="form-control" id="alertingTgToken" placeholder="deixe em branco para manter">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alertingTgChats">Chat IDs (um por linha)</label>
                            <textarea class="form-control" id="alertingTgChats" rows="2" placeholder="-1001234567890"></textarea>
                        </div>
                    </div>
                    <div class="settings-field-help">Campo acima usado pelos 3 workers automáticos legados. As regras dinâmicas usam as credenciais nomeadas abaixo.</div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-test-channel="telegram">Testar Telegram</button>
                    <div class="settings-credentials-block" data-credentials-channel="telegram">
                        <div class="settings-credentials-head">
                            <span>Credenciais nomeadas (Regras)</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-credentials-new="telegram">+ Nova credencial</button>
                        </div>
                        <div class="settings-credentials-list" data-credentials-list="telegram"></div>
                    </div>

                    <hr>

                    <!-- n8n -->
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alertingN8nEnabled">
                        <label class="form-check-label" for="alertingN8nEnabled"><i class="fas fa-diagram-project"></i> n8n</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="alertingN8nWebhook">Webhook trigger URL</label>
                        <input type="url" class="form-control" id="alertingN8nWebhook" placeholder="https://n8n.exemplo.com.br/webhook/dashglpi-alertas">
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-test-channel="n8n">Testar n8n</button>
                    </div>

                    <div class="mt-4">
                        <button type="button" class="btn btn-primary" id="alertingSaveBtn"><i class="fas fa-save"></i> Salvar canais de alerta</button>
                    </div>
                    <div class="settings-status" id="alertingStatus"></div>
                </div>

                <script>
                (function () {
                    var panel = document.querySelector('[data-settings-panel="alerting"]');
                    if (!panel || panel.dataset.wired === '1') { return; }
                    panel.dataset.wired = '1';

                    var endpoint = '/ajax/messaging_config.php';
                    var csrf = function () { return (document.getElementById('alertingCsrf') || {}).value || ''; };
                    var $ = function (id) { return document.getElementById(id); };
                    var status = $('alertingStatus');

                    function setStatus(msg, ok) {
                        status.textContent = msg;
                        status.className = 'settings-status' + (msg ? (ok ? ' ok' : ' error') : '');
                    }

                    function linesToList(value) {
                        return (value || '').split(/[\s,;]+/).map(function (s) { return s.trim(); }).filter(Boolean);
                    }

                    function renderSnapshot(snap) {
                        if (!snap) { return; }
                        ['critical', 'warning', 'on_track'].forEach(function (k) {
                            var el = panel.querySelector('[data-snap="' + k + '"]');
                            if (el) { el.textContent = snap[k] != null ? snap[k] : '–'; }
                        });
                    }

                    function updateCutoffPreview() {
                        var preview = $('alertingCutoffPreview');
                        if (!preview) { return; }
                        var maxAge = parseInt($('alertingMaxAge').value, 10) || 0;
                        var parts = [];
                        if (maxAge > 0) {
                            var cutoff = new Date(Date.now() - maxAge * 3600000);
                            parts.push('idade máxima ignora chamados abertos antes de ' + cutoff.toLocaleString('pt-BR'));
                        }
                        var since = $('alertingSinceDate').value;
                        if (since) {
                            parts.push('piso ignora chamados abertos antes de ' + since.split('-').reverse().join('/'));
                        }
                        preview.textContent = parts.length ? parts.join(' · ') : '';
                    }

                    function populate(cfg) {
                        $('alertingBreach').value = cfg.sla_threshold_minutes || 15;
                        $('alertingWarn').value = cfg.warn_threshold_minutes || 0;
                        $('alertingMaxAge').value = cfg.max_age_hours || 0;
                        $('alertingSinceDate').value = (cfg.since_date || '').substring(0, 10);
                        updateCutoffPreview();
                        $('alertingTeamsEnabled').checked = !!cfg.teams.enabled;
                        $('alertingTeamsWebhook').value = cfg.teams.webhook_url || '';
                        $('alertingWaEnabled').checked = !!cfg.whatsapp.enabled;
                        $('alertingWaUrl').value = cfg.whatsapp.api_url || '';
                        $('alertingWaInstance').value = cfg.whatsapp.instance || '';
                        $('alertingWaRecipients').value = (cfg.whatsapp.recipients || []).join('\n');
                        $('alertingWaKeyHint').textContent = cfg.whatsapp.api_key_set ? '(configurada)' : '(não definida)';
                        $('alertingTgEnabled').checked = !!cfg.telegram.enabled;
                        $('alertingTgChats').value = (cfg.telegram.chat_ids || []).join('\n');
                        $('alertingTgTokenHint').textContent = cfg.telegram.bot_token_set ? '(configurado)' : '(não definido)';
                        $('alertingN8nEnabled').checked = !!cfg.n8n.enabled;
                        $('alertingN8nWebhook').value = cfg.n8n.webhook_url || '';
                    }

                    function collect() {
                        return {
                            sla_threshold_minutes: parseInt($('alertingBreach').value, 10) || 15,
                            warn_threshold_minutes: parseInt($('alertingWarn').value, 10) || 0,
                            max_age_hours: parseInt($('alertingMaxAge').value, 10) || 0,
                            since_date: $('alertingSinceDate').value || '',
                            teams: {
                                enabled: $('alertingTeamsEnabled').checked,
                                webhook_url: $('alertingTeamsWebhook').value.trim()
                            },
                            whatsapp: {
                                enabled: $('alertingWaEnabled').checked,
                                api_url: $('alertingWaUrl').value.trim(),
                                api_key: $('alertingWaKey').value,
                                instance: $('alertingWaInstance').value.trim(),
                                recipients: linesToList($('alertingWaRecipients').value)
                            },
                            telegram: {
                                enabled: $('alertingTgEnabled').checked,
                                bot_token: $('alertingTgToken').value,
                                chat_ids: linesToList($('alertingTgChats').value)
                            },
                            n8n: {
                                enabled: $('alertingN8nEnabled').checked,
                                webhook_url: $('alertingN8nWebhook').value.trim()
                            }
                        };
                    }

                    function load() {
                        fetch(endpoint, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (data.config) { populate(data.config); }
                                renderSnapshot(data.snapshot);
                            })
                            .catch(function () { setStatus('Falha ao carregar configuração.', false); });
                    }

                    function save() {
                        setStatus('Salvando...', true);
                        var body = new URLSearchParams();
                        body.set('csrf_token', csrf());
                        body.set('messaging_action', 'save');
                        body.set('payload', JSON.stringify(collect()));
                        fetch(endpoint, { method: 'POST', credentials: 'same-origin', body: body })
                            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                            .then(function (res) {
                                if (!res.ok || res.j.error) { setStatus(res.j.error || 'Erro ao salvar.', false); return; }
                                $('alertingWaKey').value = '';
                                $('alertingTgToken').value = '';
                                if (res.j.config) { populate(res.j.config); }
                                renderSnapshot(res.j.snapshot);
                                setStatus('Configuração salva.', true);
                            })
                            .catch(function () { setStatus('Erro de rede ao salvar.', false); });
                    }

                    function test(channel) {
                        setStatus('Enviando teste para ' + channel + '...', true);
                        var body = new URLSearchParams();
                        body.set('csrf_token', csrf());
                        body.set('messaging_action', 'test');
                        body.set('channel', channel);
                        fetch(endpoint, { method: 'POST', credentials: 'same-origin', body: body })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                var res = data.result || {};
                                if (data.ok) { setStatus('Teste enviado com sucesso (' + channel + ').', true); }
                                else { setStatus('Falha no teste (' + channel + '): ' + (res.error || data.error || 'desconhecida'), false); }
                            })
                            .catch(function () { setStatus('Erro de rede no teste.', false); });
                    }

                    // PLAN-20260714-022 (Sub-fase 3): credenciais nomeadas por canal, para
                    // a engine de Regras — separadas dos campos acima (que continuam sendo
                    // o default lido pelos 3 workers automáticos legados, ver inc/rules_engine.php).
                    function renderCredentialsListInto(container, actionType, credentials) {
                        container.innerHTML = '';
                        if (!credentials.length) {
                            var empty = document.createElement('p');
                            empty.className = 'settings-credentials-empty';
                            empty.textContent = 'Nenhuma credencial nomeada ainda.';
                            container.appendChild(empty);
                            return;
                        }
                        var list = document.createElement('ul');
                        list.className = 'rule-recipient-list';
                        credentials.forEach(function (cred) {
                            var li = document.createElement('li');
                            li.className = 'rule-recipient-item';
                            var span = document.createElement('span');
                            span.textContent = cred.name + (cred.is_default ? ' ★' : '') + ' — ' + cred.usage_count + ' regra(s)';
                            li.appendChild(span);
                            var editBtn = document.createElement('button');
                            editBtn.type = 'button';
                            editBtn.className = 'btn btn-sm btn-link p-0 me-2';
                            editBtn.textContent = 'editar';
                            editBtn.addEventListener('click', function () {
                                openChannelCredentialModal(actionType, null, function () { renderCredentialsList(actionType); }, cred);
                            });
                            li.appendChild(editBtn);
                            var delBtn = document.createElement('button');
                            delBtn.type = 'button';
                            delBtn.className = 'btn btn-sm btn-link text-danger p-0';
                            delBtn.textContent = 'excluir';
                            delBtn.addEventListener('click', function () {
                                var message = cred.usage_count > 0
                                    ? 'Excluir a credencial "' + cred.name + '"? ' + cred.usage_count + ' regra(s) usam ela e voltarão a usar o padrão global (Canais de Alerta).'
                                    : 'Excluir a credencial "' + cred.name + '"?';
                                openNotificationConfirmModal(message, function () {
                                    return deleteChannelCredential(cred.id, actionType);
                                }, {
                                    title: 'Excluir credencial',
                                    confirmLabel: 'Excluir',
                                    confirmIcon: 'fa-trash',
                                    confirmTone: 'danger',
                                    cancelAriaLabel: 'Cancelar exclusão'
                                });
                            });
                            li.appendChild(delBtn);
                            list.appendChild(li);
                        });
                        container.appendChild(list);
                    }

                    function renderCredentialsList(actionType) {
                        var container = panel.querySelector('[data-credentials-list="' + actionType + '"]');
                        if (!container) { return; }
                        fetch('/ajax/channel_credentials.php?action_type=' + encodeURIComponent(actionType), {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' }
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) { renderCredentialsListInto(container, actionType, data.credentials || []); })
                            .catch(function () {
                                container.innerHTML = '';
                                var errEl = document.createElement('p');
                                errEl.className = 'settings-credentials-empty';
                                errEl.textContent = 'Falha ao carregar credenciais.';
                                container.appendChild(errEl);
                            });
                    }

                    function deleteChannelCredential(id, actionType) {
                        var body = new URLSearchParams();
                        body.set('csrf_token', csrf());
                        body.set('credential_action', 'delete');
                        body.set('id', id);
                        return fetch('/ajax/channel_credentials.php', { method: 'POST', credentials: 'same-origin', body: body })
                            .then(function (r) { return r.json(); })
                            .then(function () { renderCredentialsList(actionType); });
                    }

                    $('alertingSaveBtn').addEventListener('click', save);
                    panel.querySelectorAll('[data-test-channel]').forEach(function (btn) {
                        btn.addEventListener('click', function () { test(btn.getAttribute('data-test-channel')); });
                    });
                    panel.querySelectorAll('[data-credentials-new]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var actionType = btn.getAttribute('data-credentials-new');
                            openChannelCredentialModal(actionType, null, function () { renderCredentialsList(actionType); });
                        });
                    });
                    $('alertingMaxAge').addEventListener('input', updateCutoffPreview);
                    $('alertingSinceDate').addEventListener('input', updateCutoffPreview);

                    load();
                    ['teams', 'whatsapp', 'telegram'].forEach(renderCredentialsList);
                })();
                </script>
            </section>

            <section class="glass-card settings-panel" data-settings-panel="rules"<?= $settingsSection !== 'rules' ? ' hidden' : '' ?>>
                <h2 class="settings-card-title"><i class="fas fa-diagram-project"></i> Regras de Automação</h2>

                <input type="hidden" id="rulesCsrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="settings-guide-card">
                    <div class="settings-guide-head">
                        <div class="settings-guide-kicker">Engine dinâmica de regras</div>
                        <h3>1 gatilho -> N ações -> N destinatários</h3>
                        <p>Cada regra escolhe um gatilho já suportado pelo worker (SLA ocioso, nova tarefa
                        ou log de tarefa automática do GLPI), e dispara para quantos canais/destinatários
                        você quiser — sem precisar editar código. Os 3 workers fixos do Monitor de SLA
                        (aba "Canais de Alerta") continuam funcionando normalmente; esta engine é aditiva.</p>
                        <p><strong>Segurança:</strong> webhooks só são aceitos de hosts na allowlist
                        (<span id="rulesAllowlistHint">carregando...</span>).</p>
                    </div>

                    <h4 class="mt-3">Nova regra</h4>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="ruleName">Nome</label>
                            <input type="text" class="form-control" id="ruleName" placeholder="Ex.: SLA 20min -> Teams TI">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ruleTriggerType">Gatilho</label>
                            <select class="form-select" id="ruleTriggerType">
                                <option value="sla_breach">Chamado sem ação (SLA)</option>
                                <option value="new_task">Nova tarefa em chamado</option>
                                <option value="new_task_problem">Nova tarefa em problema</option>
                                <option value="new_task_change">Nova tarefa em manutenção</option>
                                <option value="glpi_action_log">Log de tarefa automática do GLPI</option>
                                <option value="ticket_assigned">Chamado Atribuído</option>
                            </select>
                        </div>
                        <div class="col-md-2" data-rule-field="trigger_value">
                            <label class="form-label" for="ruleTriggerValue">Minutos</label>
                            <input type="number" min="1" class="form-control" id="ruleTriggerValue" value="15">
                        </div>
                        <div class="col-md-12 alert alert-warning small mb-0 d-flex align-items-start justify-content-between gap-2" data-rule-field="ticket_assigned" hidden>
                            <div>
                                <i class="fas fa-triangle-exclamation"></i>
                                A menção real (@usuário) no Teams depende de um flow externo no Power
                                Automate/Logic Apps configurado para ler <code>dashglpiMention</code> do payload e
                                postar o Adaptive Card com a menção — cadastrar a regra aqui não cria essa
                                menção sozinha. Use um destinatário Teams cujo host esteja na allowlist
                                (<span id="rulesAllowlistHintInline">carregando...</span>).
                            </div>
                            <button class="settings-btn" type="button" id="ticketAssignedFaqButton">
                                <i class="fas fa-circle-info"></i>
                                <span>Como configurar</span>
                            </button>
                        </div>
                        <div class="col-md-3" data-rule-field="glpi_action_log" hidden>
                            <label class="form-label" for="ruleCrontasksId">ID da tarefa de cron (GLPI)</label>
                            <input type="number" min="1" class="form-control" id="ruleCrontasksId" placeholder="Ex.: 42">
                        </div>
                        <div class="col-md-3" data-rule-field="glpi_action_log" hidden>
                            <label class="form-label" for="ruleMode">Modo</label>
                            <select class="form-select" id="ruleMode">
                                <option value="failure">Só em falha</option>
                                <option value="every">Toda execução</option>
                                <option value="duration_over">Duração acima de um limite</option>
                            </select>
                        </div>
                        <div class="col-md-3" data-rule-field="duration_threshold" hidden>
                            <label class="form-label" for="ruleDurationThreshold">Limite de duração (segundos)</label>
                            <input type="number" min="1" class="form-control" id="ruleDurationThreshold" value="30">
                        </div>
                        <div class="col-md-12">
                            <button type="button" class="btn btn-primary" id="ruleCreateBtn"><i class="fas fa-plus"></i> Criar regra</button>
                        </div>
                    </div>

                    <div class="settings-status" id="rulesStatus"></div>

                    <hr>

                    <h4>Regras existentes</h4>
                    <div class="table-responsive">
                        <table class="custom-table responsive-table" id="rulesTable">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Gatilho</th>
                                    <th>Ativa</th>
                                    <th>Ações/Destinatários</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="rulesTableBody">
                                <tr><td colspan="5">Carregando regras...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <script>
                (function () {
                    var panel = document.querySelector('[data-settings-panel="rules"]');
                    if (!panel || panel.dataset.wired === '1') { return; }
                    panel.dataset.wired = '1';

                    var endpoint = '/ajax/rules_config.php';
                    var csrf = function () { return (document.getElementById('rulesCsrf') || {}).value || ''; };
                    var $ = function (id) { return document.getElementById(id); };
                    var status = $('rulesStatus');
                    var recipientTypeByAction = {};
                    var templateVariables = {};
                    var templateExpandedRules = {};
                    var templateDraftLines = {};
                    var templateLastFocusedIndex = {};
                    var templatePreviewTimers = {};
                    var credentialsCatalog = []; // PLAN-20260714-022 (Sub-fase 2)

                    function setStatus(msg, ok) {
                        status.textContent = msg;
                        status.className = 'settings-status' + (msg ? (ok ? ' ok' : ' error') : '');
                        if (msg) {
                            status.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }

                    function post(action, extra) {
                        var body = new URLSearchParams();
                        body.set('csrf_token', csrf());
                        body.set('rules_action', action);
                        Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });
                        return fetch(endpoint, { method: 'POST', credentials: 'same-origin', body: body })
                            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
                    }

                    /** PLAN-20260714-021 (Fase 4 §3): testa 1 destinatário isolado, sem gravar nada. */
                    function testRecipient(actionType, recipientValue, btn, actionId) {
                        var originalLabel = btn.textContent;
                        btn.disabled = true;
                        btn.textContent = 'testando...';
                        var body = new URLSearchParams();
                        body.set('csrf_token', csrf());
                        body.set('messaging_action', 'test_recipient');
                        body.set('action_type', actionType);
                        body.set('recipient_value', recipientValue);
                        // PLAN-20260714-022: manda o action_id para o backend resolver a
                        // credencial nomeada da ação (se houver) em vez de sempre cair no
                        // default global de Canais de Alerta.
                        body.set('action_id', actionId || '');
                        fetch('/ajax/messaging_config.php', { method: 'POST', credentials: 'same-origin', body: body })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                var res = data.result || {};
                                if (data.ok) {
                                    setStatus('Teste enviado para ' + recipientValue + '.', true);
                                } else {
                                    setStatus('Falha no teste (' + recipientValue + '): ' + (res.error || data.error || 'desconhecida'), false);
                                }
                            })
                            .catch(function () { setStatus('Erro de rede no teste.', false); })
                            .then(function () {
                                btn.disabled = false;
                                btn.textContent = originalLabel;
                            });
                    }

                    function triggerLabel(rule) {
                        if (rule.trigger_type === 'sla_breach') { return 'SLA >= ' + rule.trigger_value + ' min'; }
                        if (rule.trigger_type === 'new_task') { return 'Nova tarefa (janela ' + rule.trigger_value + ' min)'; }
                        if (rule.trigger_type === 'new_task_problem') { return 'Nova tarefa em problema (janela ' + rule.trigger_value + ' min)'; }
                        if (rule.trigger_type === 'new_task_change') { return 'Nova tarefa em manutenção (janela ' + rule.trigger_value + ' min)'; }
                        if (rule.trigger_type === 'glpi_action_log') {
                            var cfg = rule.trigger_config || {};
                            return 'Log cron #' + (cfg.crontasks_id || '?') + ' (' + (cfg.mode || 'failure') + ')';
                        }
                        if (rule.trigger_type === 'ticket_assigned') { return 'Chamado atribuído (janela ' + rule.trigger_value + ' min)'; }
                        return rule.trigger_type;
                    }

                    function recipientPlaceholder(actionType) {
                        if (actionType === 'teams') { return 'https://outlook.office.com/webhook/...'; }
                        if (actionType === 'whatsapp') { return '5511999998888'; }
                        if (actionType === 'telegram') { return '-1001234567890'; }
                        return '';
                    }

                    function recipientInputType(actionType) {
                        if (actionType === 'teams') { return 'url'; }
                        if (actionType === 'whatsapp') { return 'tel'; }
                        return 'text';
                    }

                    var actionLabels = { teams: 'Microsoft Teams', whatsapp: 'WhatsApp', telegram: 'Telegram' };

                    function renderActions(rule) {
                        var wrap = document.createElement('div');
                        wrap.className = 'rule-actions-cell';

                        (rule.actions || []).forEach(function (action) {
                            var box = document.createElement('div');
                            box.className = 'rule-action-box';

                            var head = document.createElement('div');
                            head.className = 'rule-action-head';
                            var label = document.createElement('strong');
                            label.textContent = actionLabels[action.action_type] || action.action_type;
                            head.appendChild(label);
                            var faqActionBtn = document.createElement('button');
                            faqActionBtn.type = 'button';
                            faqActionBtn.className = 'btn btn-sm btn-outline-secondary';
                            faqActionBtn.title = 'Como configurar ' + (actionLabels[action.action_type] || action.action_type);
                            faqActionBtn.innerHTML = '<i class="fas fa-circle-info"></i>';
                            faqActionBtn.addEventListener('click', function () {
                                openChannelsFaqModal(action.action_type);
                            });
                            head.appendChild(faqActionBtn);
                            var delActionBtn = document.createElement('button');
                            delActionBtn.type = 'button';
                            delActionBtn.className = 'btn btn-sm btn-outline-danger';
                            delActionBtn.textContent = 'Remover ação';
                            delActionBtn.addEventListener('click', function () {
                                post('action_delete', { action_id: action.id }).then(function (res) { handleResult(res); });
                            });
                            head.appendChild(delActionBtn);
                            box.appendChild(head);

                            // PLAN-20260714-022 (Sub-fase 2): credencial nomeada da ação
                            // (sem selecionar = usa o default global de Canais de Alerta).
                            var credRow = document.createElement('div');
                            credRow.className = 'rule-add-row';
                            var credSelect = document.createElement('select');
                            credSelect.className = 'form-select form-select-sm';
                            var defaultOpt = document.createElement('option');
                            defaultOpt.value = '';
                            defaultOpt.textContent = 'Usar padrão (Canais de Alerta)';
                            credSelect.appendChild(defaultOpt);
                            credentialsCatalog
                                .filter(function (c) { return c.action_type === action.action_type; })
                                .forEach(function (c) {
                                    var opt = document.createElement('option');
                                    opt.value = String(c.id);
                                    opt.textContent = c.name + (c.is_default ? ' ★' : '');
                                    if (action.credential_id !== null && String(action.credential_id) === String(c.id)) {
                                        opt.selected = true;
                                    }
                                    credSelect.appendChild(opt);
                                });
                            credSelect.addEventListener('change', function () {
                                post('action_set_credential', { action_id: action.id, credential_id: credSelect.value })
                                    .then(function (res) { handleResult(res); });
                            });
                            credRow.appendChild(credSelect);
                            var newCredBtn = document.createElement('button');
                            newCredBtn.type = 'button';
                            newCredBtn.className = 'btn btn-sm btn-outline-primary';
                            newCredBtn.textContent = '+ Nova credencial';
                            newCredBtn.addEventListener('click', function () {
                                openChannelCredentialModal(action.action_type, action.id);
                            });
                            credRow.appendChild(newCredBtn);
                            box.appendChild(credRow);

                            if ((action.recipients || []).length) {
                                var list = document.createElement('ul');
                                list.className = 'rule-recipient-list';
                                action.recipients.forEach(function (recipient) {
                                    var li = document.createElement('li');
                                    li.className = 'rule-recipient-item';
                                    var span = document.createElement('span');
                                    span.textContent = recipient.recipient_value;
                                    span.title = recipient.recipient_value;
                                    li.appendChild(span);
                                    var testRecipientBtn = document.createElement('button');
                                    testRecipientBtn.type = 'button';
                                    testRecipientBtn.className = 'btn btn-sm btn-link p-0 me-2';
                                    testRecipientBtn.textContent = 'testar';
                                    testRecipientBtn.addEventListener('click', function () {
                                        testRecipient(action.action_type, recipient.recipient_value, testRecipientBtn, action.id);
                                    });
                                    li.appendChild(testRecipientBtn);
                                    var delBtn = document.createElement('button');
                                    delBtn.type = 'button';
                                    delBtn.className = 'btn btn-sm btn-link text-danger p-0';
                                    delBtn.textContent = 'remover';
                                    delBtn.addEventListener('click', function () {
                                        post('recipient_delete', { recipient_id: recipient.id }).then(function (res) { handleResult(res); });
                                    });
                                    li.appendChild(delBtn);
                                    list.appendChild(li);
                                });
                                box.appendChild(list);
                            } else {
                                var empty = document.createElement('p');
                                empty.className = 'rule-recipient-empty';
                                empty.textContent = 'Nenhum destinatário ainda.';
                                box.appendChild(empty);
                            }

                            var addRow = document.createElement('div');
                            addRow.className = 'rule-add-row';
                            var input = document.createElement('input');
                            input.type = recipientInputType(action.action_type);
                            input.className = 'form-control form-control-sm';
                            input.placeholder = recipientPlaceholder(action.action_type);
                            var addBtn = document.createElement('button');
                            addBtn.type = 'button';
                            addBtn.className = 'btn btn-sm btn-outline-primary';
                            addBtn.textContent = 'Adicionar destinatário';
                            addBtn.addEventListener('click', function () {
                                if (!input.value.trim()) { return; }
                                post('recipient_save', { action_id: action.id, recipient_value: input.value.trim() })
                                    .then(function (res) { handleResult(res); });
                            });
                            addRow.appendChild(input);
                            addRow.appendChild(addBtn);
                            box.appendChild(addRow);

                            wrap.appendChild(box);
                        });

                        var addActionRow = document.createElement('div');
                        addActionRow.className = 'rule-add-action-row';
                        var select = document.createElement('select');
                        select.className = 'form-select form-select-sm';
                        ['teams', 'whatsapp', 'telegram'].forEach(function (actionType) {
                            var opt = document.createElement('option');
                            opt.value = actionType;
                            opt.textContent = actionLabels[actionType];
                            select.appendChild(opt);
                        });
                        var addActionBtn = document.createElement('button');
                        addActionBtn.type = 'button';
                        addActionBtn.className = 'btn btn-sm btn-outline-secondary';
                        addActionBtn.textContent = 'Adicionar ação';
                        addActionBtn.addEventListener('click', function () {
                            post('action_save', { rule_id: rule.id, action_type: select.value })
                                .then(function (res) { handleResult(res); });
                        });
                        addActionRow.appendChild(select);
                        addActionRow.appendChild(addActionBtn);
                        wrap.appendChild(addActionRow);

                        return wrap;
                    }

                    function renderTemplateLineInputs(container, ruleId) {
                        container.innerHTML = '';
                        var lines = templateDraftLines[ruleId] || [];
                        if (!lines.length) {
                            var empty = document.createElement('p');
                            empty.className = 'rule-template-empty-hint';
                            empty.textContent = 'Nenhuma linha ainda.';
                            container.appendChild(empty);
                            return;
                        }
                        lines.forEach(function (lineValue, index) {
                            var row = document.createElement('div');
                            row.className = 'rule-template-line';

                            var input = document.createElement('input');
                            input.type = 'text';
                            input.className = 'form-control form-control-sm';
                            input.value = lineValue;
                            input.addEventListener('focus', function () {
                                templateLastFocusedIndex[ruleId] = index;
                            });
                            input.addEventListener('input', function () {
                                templateDraftLines[ruleId][index] = input.value;
                                schedulePreview(ruleId);
                            });
                            row.appendChild(input);

                            var removeBtn = document.createElement('button');
                            removeBtn.type = 'button';
                            removeBtn.className = 'btn btn-sm btn-outline-danger';
                            removeBtn.textContent = 'Remover';
                            removeBtn.addEventListener('click', function () {
                                templateDraftLines[ruleId].splice(index, 1);
                                renderTemplateLineInputs(container, ruleId);
                                schedulePreview(ruleId);
                            });
                            row.appendChild(removeBtn);

                            container.appendChild(row);
                        });
                    }

                    function insertVariableIntoLastFocusedLine(ruleId, token) {
                        var lines = templateDraftLines[ruleId] || (templateDraftLines[ruleId] = []);
                        var idx = templateLastFocusedIndex[ruleId];
                        if (idx === undefined || idx === null || !lines.length) {
                            lines.push(token);
                        } else {
                            lines[idx] = (lines[idx] || '') + token;
                        }
                        var row = document.getElementById('ruleTemplate-' + ruleId);
                        var linesWrap = row && row.querySelector('.rule-template-lines');
                        if (linesWrap) {
                            renderTemplateLineInputs(linesWrap, ruleId);
                        }
                        schedulePreview(ruleId);
                    }

                    function schedulePreview(ruleId, immediate) {
                        if (templatePreviewTimers[ruleId]) { clearTimeout(templatePreviewTimers[ruleId]); }
                        var run = function () { fetchPreview(ruleId); };
                        if (immediate) { run(); } else { templatePreviewTimers[ruleId] = setTimeout(run, 400); }
                    }

                    function fetchPreview(ruleId) {
                        var box = document.getElementById('templatePreview-' + ruleId);
                        if (!box) { return; }
                        var lines = (templateDraftLines[ruleId] || []).map(function (l) { return l.trim(); }).filter(Boolean);
                        var payload = { message_template: lines.length ? { lines: lines } : null };
                        post('template_preview', { payload: JSON.stringify(payload) }).then(function (res) {
                            if (!res.ok || res.j.error) {
                                box.textContent = res.j.error || 'Erro ao gerar preview.';
                                return;
                            }
                            var preview = (res.j.preview || {}).teams;
                            box.textContent = (Array.isArray(preview) && preview.length)
                                ? preview.join('\n\n')
                                : 'Sem linhas configuradas — cada canal usará a mensagem padrão dele.';
                        });
                    }

                    function saveTemplate(rule) {
                        var lines = (templateDraftLines[rule.id] || []).map(function (l) { return l.trim(); }).filter(Boolean);
                        var payload = {
                            name: rule.name,
                            trigger_type: rule.trigger_type,
                            trigger_value: rule.trigger_value,
                            is_active: !!(+rule.is_active),
                            trigger_config: rule.trigger_config || {},
                            message_template: lines.length ? { lines: lines } : null
                        };
                        setStatus('Salvando mensagem...', true);
                        post('rule_save', { rule_id: rule.id, payload: JSON.stringify(payload) }).then(function (res) {
                            if (!res.ok || res.j.error) { setStatus(res.j.error || 'Erro ao salvar mensagem.', false); return; }
                            setStatus('Mensagem salva.', true);
                            applyDataset(res.j);
                        });
                    }

                    function buildTemplateRow(rule) {
                        var tr = document.createElement('tr');
                        tr.className = 'rule-template-row';
                        tr.id = 'ruleTemplate-' + rule.id;

                        var td = document.createElement('td');
                        td.colSpan = 5;
                        tr.appendChild(td);

                        if (!templateExpandedRules[rule.id]) {
                            tr.hidden = true;
                            return tr;
                        }

                        if (!templateDraftLines[rule.id]) {
                            var existing = (rule.message_template && Array.isArray(rule.message_template.lines))
                                ? rule.message_template.lines
                                : [];
                            templateDraftLines[rule.id] = existing.slice();
                        }

                        var panel = document.createElement('div');
                        panel.className = 'rule-template-panel';

                        var heading = document.createElement('h5');
                        heading.textContent = 'Mensagem personalizada (opcional)';
                        panel.appendChild(heading);

                        var hint = document.createElement('p');
                        hint.className = 'rule-template-empty-hint';
                        hint.textContent = 'Sem linhas configuradas, cada canal usa a mensagem padrão dele. '
                            + 'Clique em uma variável para inserir na última linha em edição.';
                        panel.appendChild(hint);

                        var chipsRow = document.createElement('div');
                        chipsRow.className = 'rule-template-chips';
                        Object.keys(templateVariables).forEach(function (varName) {
                            var meta = templateVariables[varName] || {};
                            var chip = document.createElement('button');
                            chip.type = 'button';
                            chip.className = 'rule-template-chip';
                            chip.textContent = '{var: ' + varName + '}';
                            chip.title = (meta.label || varName) + (meta.example ? ' — ex.: ' + meta.example : '');
                            chip.setAttribute('aria-label', 'Inserir variável: ' + (meta.label || varName));
                            chip.addEventListener('click', function () {
                                insertVariableIntoLastFocusedLine(rule.id, '{var: ' + varName + '}');
                            });
                            chipsRow.appendChild(chip);
                        });
                        panel.appendChild(chipsRow);

                        var columns = document.createElement('div');
                        columns.className = 'rule-template-columns';

                        var editorCol = document.createElement('div');
                        var linesWrap = document.createElement('div');
                        linesWrap.className = 'rule-template-lines';
                        renderTemplateLineInputs(linesWrap, rule.id);
                        editorCol.appendChild(linesWrap);

                        var addLineBtn = document.createElement('button');
                        addLineBtn.type = 'button';
                        addLineBtn.className = 'btn btn-sm btn-outline-secondary rule-add-template-line-btn mt-2';
                        addLineBtn.textContent = '+ Adicionar linha';
                        addLineBtn.addEventListener('click', function () {
                            if (!templateDraftLines[rule.id]) { templateDraftLines[rule.id] = []; }
                            templateDraftLines[rule.id].push('');
                            renderTemplateLineInputs(linesWrap, rule.id);
                            schedulePreview(rule.id);
                        });
                        editorCol.appendChild(addLineBtn);

                        var saveBtn = document.createElement('button');
                        saveBtn.type = 'button';
                        saveBtn.className = 'btn btn-sm btn-primary mt-3';
                        saveBtn.textContent = 'Salvar mensagem';
                        saveBtn.addEventListener('click', function () { saveTemplate(rule); });
                        editorCol.appendChild(document.createElement('br'));
                        editorCol.appendChild(saveBtn);

                        columns.appendChild(editorCol);

                        var previewCol = document.createElement('div');
                        var previewTitle = document.createElement('strong');
                        previewTitle.textContent = 'Pré-visualização (aproximada)';
                        previewTitle.style.display = 'block';
                        previewTitle.className = 'mb-2';
                        previewCol.appendChild(previewTitle);

                        var previewBox = document.createElement('div');
                        previewBox.className = 'rule-template-preview';
                        previewBox.id = 'templatePreview-' + rule.id;
                        previewBox.setAttribute('aria-live', 'polite');
                        previewBox.textContent = 'Carregando pré-visualização...';
                        previewCol.appendChild(previewBox);

                        columns.appendChild(previewCol);
                        panel.appendChild(columns);
                        td.appendChild(panel);

                        return tr;
                    }

                    /**
                     * dashglpi_rule_update() sobrescreve a regra inteira (não é PATCH parcial) —
                     * então uma edição inline de 1 campo precisa reenviar o payload completo,
                     * partindo do estado atual da regra, ou os demais campos (trigger_config,
                     * message_template) seriam apagados. Mesma lógica de shape do createRule().
                     */
                    function buildRuleUpdatePayload(rule, overrides) {
                        var payload = {
                            name: rule.name,
                            trigger_type: rule.trigger_type,
                            trigger_value: rule.trigger_value,
                            is_active: !!(+rule.is_active)
                        };
                        if (rule.trigger_type === 'glpi_action_log') {
                            var cfg = rule.trigger_config || {};
                            payload.trigger_config = {
                                crontasks_id: cfg.crontasks_id || 0,
                                mode: cfg.mode || 'failure',
                                duration_threshold_s: cfg.duration_threshold_s || 30
                            };
                        }
                        var lines = (rule.message_template && Array.isArray(rule.message_template.lines))
                            ? rule.message_template.lines
                            : [];
                        payload.message_template = lines.length ? { lines: lines } : null;

                        Object.keys(overrides || {}).forEach(function (key) { payload[key] = overrides[key]; });
                        return payload;
                    }

                    function saveRuleField(rule, overrides) {
                        var payload = buildRuleUpdatePayload(rule, overrides);
                        setStatus('Salvando...', true);
                        post('rule_save', { rule_id: rule.id, payload: JSON.stringify(payload) }).then(function (res) {
                            if (!res.ok || res.j.error) { setStatus(res.j.error || 'Erro ao salvar.', false); return; }
                            setStatus('Atualizado.', true);
                            applyDataset(res.j);
                        });
                    }

                    function renderRules(rules) {
                        var tbody = $('rulesTableBody');
                        tbody.innerHTML = '';
                        if (!rules.length) {
                            tbody.innerHTML = '<tr><td colspan="5">Nenhuma regra cadastrada ainda.</td></tr>';
                            return;
                        }

                        rules.forEach(function (rule) {
                            var tr = document.createElement('tr');

                            var nameTd = document.createElement('td');
                            nameTd.setAttribute('data-label', 'Nome');
                            var nameInput = document.createElement('input');
                            nameInput.type = 'text';
                            nameInput.className = 'form-control form-control-sm rule-inline-edit';
                            nameInput.value = rule.name;
                            nameInput.addEventListener('change', function () {
                                var value = nameInput.value.trim();
                                if (!value) { nameInput.value = rule.name; return; }
                                saveRuleField(rule, { name: value });
                            });
                            nameTd.appendChild(nameInput);
                            tr.appendChild(nameTd);

                            var triggerTd = document.createElement('td');
                            triggerTd.setAttribute('data-label', 'Gatilho');
                            var triggerLabelSpan = document.createElement('span');
                            triggerLabelSpan.className = 'rule-trigger-label';
                            triggerLabelSpan.textContent = triggerLabel(rule);
                            triggerTd.appendChild(triggerLabelSpan);
                            // glpi_action_log não usa trigger_value como janela de tempo (usa
                            // crontasks_id/mode em trigger_config) — só os demais gatilhos
                            // ganham o campo editável de minutos.
                            if (rule.trigger_type !== 'glpi_action_log') {
                                var triggerValueInput = document.createElement('input');
                                triggerValueInput.type = 'number';
                                triggerValueInput.min = '1';
                                triggerValueInput.className = 'form-control form-control-sm rule-inline-edit rule-inline-edit-number';
                                triggerValueInput.value = rule.trigger_value;
                                triggerValueInput.title = 'Janela (minutos)';
                                triggerValueInput.addEventListener('change', function () {
                                    var value = parseInt(triggerValueInput.value, 10);
                                    if (!value || value < 1) { triggerValueInput.value = rule.trigger_value; return; }
                                    saveRuleField(rule, { trigger_value: value });
                                });
                                triggerTd.appendChild(triggerValueInput);
                            }
                            tr.appendChild(triggerTd);

                            var activeTd = document.createElement('td');
                            activeTd.setAttribute('data-label', 'Ativa');
                            var toggleWrap = document.createElement('div');
                            toggleWrap.className = 'form-check form-switch';
                            var toggle = document.createElement('input');
                            toggle.type = 'checkbox';
                            toggle.className = 'form-check-input';
                            toggle.checked = !!(+rule.is_active);
                            toggle.addEventListener('change', function () {
                                post('rule_toggle', { rule_id: rule.id, is_active: toggle.checked ? '1' : '' })
                                    .then(function (res) { handleResult(res); });
                            });
                            toggleWrap.appendChild(toggle);
                            activeTd.appendChild(toggleWrap);
                            tr.appendChild(activeTd);

                            var actionsTd = document.createElement('td');
                            actionsTd.setAttribute('data-label', 'Ações/Destinatários');
                            actionsTd.appendChild(renderActions(rule));
                            tr.appendChild(actionsTd);

                            var opsTd = document.createElement('td');
                            opsTd.setAttribute('data-label', '');
                            var opsGroup = document.createElement('div');
                            opsGroup.className = 'table-action-group';
                            var templateBtn = document.createElement('button');
                            templateBtn.type = 'button';
                            templateBtn.className = 'table-action';
                            templateBtn.title = 'Mensagem personalizada';
                            templateBtn.innerHTML = '<i class="fas fa-message"></i><span class="table-action-label">Mensagem</span>';
                            templateBtn.setAttribute('aria-expanded', templateExpandedRules[rule.id] ? 'true' : 'false');
                            templateBtn.setAttribute('aria-controls', 'ruleTemplate-' + rule.id);
                            templateBtn.addEventListener('click', function () {
                                templateExpandedRules[rule.id] = !templateExpandedRules[rule.id];
                                renderRules(rules);
                            });
                            opsGroup.appendChild(templateBtn);
                            var delBtn = document.createElement('button');
                            delBtn.type = 'button';
                            delBtn.className = 'table-action';
                            delBtn.title = 'Excluir regra';
                            delBtn.innerHTML = '<i class="fas fa-trash"></i><span class="table-action-label">Excluir regra</span>';
                            delBtn.addEventListener('click', function () {
                                openNotificationConfirmModal(
                                    'Excluir a regra "' + rule.name + '" e todas as suas ações/destinatários?',
                                    function () { return post('rule_delete', { rule_id: rule.id }).then(function (res) { handleResult(res); }); },
                                    {
                                        title: 'Excluir regra',
                                        confirmLabel: 'Excluir',
                                        confirmIcon: 'fa-trash',
                                        confirmTone: 'danger',
                                        cancelAriaLabel: 'Cancelar exclusão'
                                    }
                                );
                            });
                            opsGroup.appendChild(delBtn);
                            opsTd.appendChild(opsGroup);
                            tr.appendChild(opsTd);

                            tbody.appendChild(tr);
                            tbody.appendChild(buildTemplateRow(rule));
                            if (templateExpandedRules[rule.id]) {
                                schedulePreview(rule.id, true);
                            }
                        });
                    }

                    function handleResult(res) {
                        if (!res.ok || res.j.error) { setStatus(res.j.error || 'Erro na operação.', false); return; }
                        setStatus('Atualizado.', true);
                        applyDataset(res.j);
                    }

                    function applyDataset(data) {
                        // Catálogo precisa estar atualizado ANTES de renderRules() usar
                        // credentialsCatalog no dropdown de credencial de cada ação.
                        if (data.catalog) {
                            recipientTypeByAction = data.catalog.recipient_type_by_action || {};
                            templateVariables = data.catalog.template_variables || {};
                            credentialsCatalog = data.catalog.credentials || [];
                            $('rulesAllowlistHint').textContent = (data.catalog.webhook_host_allowlist || []).join(', ');
                            $('rulesAllowlistHintInline').textContent = (data.catalog.webhook_host_allowlist || []).join(', ');
                        }
                        if (data.rules) { renderRules(data.rules); }
                    }

                    function updateFieldVisibility() {
                        var type = $('ruleTriggerType').value;
                        panel.querySelectorAll('[data-rule-field="trigger_value"]').forEach(function (el) {
                            el.hidden = type === 'glpi_action_log';
                        });
                        panel.querySelectorAll('[data-rule-field="glpi_action_log"]').forEach(function (el) {
                            el.hidden = type !== 'glpi_action_log';
                        });
                        panel.querySelectorAll('[data-rule-field="duration_threshold"]').forEach(function (el) {
                            el.hidden = type !== 'glpi_action_log' || $('ruleMode').value !== 'duration_over';
                        });
                        panel.querySelectorAll('[data-rule-field="ticket_assigned"]').forEach(function (el) {
                            el.hidden = type !== 'ticket_assigned';
                        });
                    }

                    function createRule() {
                        var name = $('ruleName').value.trim();
                        if (!name) { setStatus('Informe um nome para a regra.', false); return; }
                        var triggerType = $('ruleTriggerType').value;

                        var payload = { name: name, trigger_type: triggerType, is_active: true };
                        if (triggerType === 'glpi_action_log') {
                            payload.trigger_value = 1;
                            payload.trigger_config = {
                                crontasks_id: parseInt($('ruleCrontasksId').value, 10) || 0,
                                mode: $('ruleMode').value,
                                duration_threshold_s: parseInt($('ruleDurationThreshold').value, 10) || 30
                            };
                        } else {
                            payload.trigger_value = parseInt($('ruleTriggerValue').value, 10) || 15;
                        }

                        setStatus('Criando regra...', true);
                        post('rule_save', { payload: JSON.stringify(payload) }).then(function (res) {
                            if (!res.ok || res.j.error) { setStatus(res.j.error || 'Erro ao criar regra.', false); return; }
                            $('ruleName').value = '';
                            setStatus('Regra criada.', true);
                            applyDataset(res.j);
                        });
                    }

                    $('ruleTriggerType').addEventListener('change', updateFieldVisibility);
                    $('ruleMode') && $('ruleMode').addEventListener('change', updateFieldVisibility);
                    $('ruleCreateBtn').addEventListener('click', createRule);
                    updateFieldVisibility();
                    // PLAN-20260714-022 (Sub-fase 3): permite o modal global de credencial
                    // (compartilhado com Canais de Alerta) atualizar esta tela após anexar
                    // uma credencial recém-criada a uma ação, sem duplicar post()/applyDataset.
                    window.dashglpiRulesRefresh = function (data) { applyDataset(data); };

                    fetch(endpoint, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (data) { applyDataset(data); })
                        .catch(function () { setStatus('Falha ao carregar regras.', false); });
                })();
                </script>
            </section>

            <section class="glass-card settings-panel" data-settings-panel="recipients"<?= $settingsSection !== 'recipients' ? ' hidden' : '' ?>>
                <h2 class="settings-card-title"><i class="fas fa-users"></i> Destinatários</h2>

                <div class="settings-guide-card">
                    <div class="settings-guide-head">
                        <div class="settings-guide-kicker">Apoio avançado</div>
                        <h3>Os recebedores agora ficam dentro do passo 3 de Notificações</h3>
                        <p>O cadastro do gatilho, o catálogo dinâmico de destinatários e a associação com modelos foram concentrados em <strong>Configurações &gt; Notificações</strong>. Esta área ficou reservada para atalhos complementares e coletores de e-mail.</p>
                    </div>
                    <div class="settings-guide-grid">
                        <div class="settings-guide-item">
                            <strong>Fluxo assistido</strong>
                            <span>Use o passo 3 para criar ou editar a regra e o passo 4 para revisar o HTML do modelo.</span>
                        </div>
                        <div class="settings-guide-item">
                            <strong>Regras especiais</strong>
                            <span>Exceções e comportamentos muito específicos continuam disponíveis nas telas nativas do GLPI.</span>
                        </div>
                        <div class="settings-guide-item">
                            <strong>Coletores</strong>
                            <span>O CRUD dos coletores segue aqui como bloco avançado para não disputar atenção com o fluxo principal.</span>
                        </div>
                    </div>
                </div>

                <div class="settings-block">
                    <div class="settings-block-header">
                        <div>
                            <div class="settings-block-kicker">Atalhos rápidos</div>
                            <h3>Abra o GLPI ou volte para o fluxo assistido</h3>
                        </div>
                    </div>
                    <div class="settings-native-links">
                        <a class="settings-link-btn" href="/front/settings.php?section=notifications"><i class="fas fa-route"></i> Abrir fluxo de notificações</a>
                        <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/notification.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-bell"></i> Notificações no GLPI</a>
                        <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/notificationtemplate.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-file-alt"></i> Modelos no GLPI</a>
                    </div>
                </div>

                <details class="settings-accordion settings-advanced-block" id="collectorsAdvancedPanel">
                    <summary>Coletores de e-mail</summary>
                    <div class="settings-accordion-body">
                        <div class="settings-toolbar">
                            <div class="settings-native-links">
                                <a class="settings-link-btn" href="<?= htmlspecialchars($glpiRoot ? $glpiRoot . '/front/mailcollector.php' : '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fas fa-inbox"></i> Coletores no GLPI</a>
                            </div>
                            <button class="page-action-btn primary" type="button" id="toggleCollectorCreate">
                                <i class="fas fa-plus"></i>
                                <span>Novo coletor</span>
                            </button>
                        </div>

                        <div class="settings-create-panel" id="collectorCreatePanel">
                            <div class="sla-two-cols">
                                <input class="settings-input" id="collectorName" type="text" placeholder="Nome do coletor">
                                <input class="settings-input" id="collectorHost" type="text" placeholder="Host IMAP, ex: imap.exemplo.com">
                                <input class="settings-input" id="collectorLogin" type="text" placeholder="Login">
                                <input class="settings-input" id="collectorPassword" type="password" placeholder="Senha">
                                <input class="settings-input" id="collectorFilesize" type="number" min="1024" value="2097152" placeholder="Tamanho máximo em bytes">
                                <label class="settings-option settings-option-inline">
                                    <input type="checkbox" id="collectorActive" checked>
                                    <span><strong>Ativo</strong></span>
                                </label>
                            </div>
                            <label class="settings-option settings-option-inline" style="margin-top: 12px;">
                                <input type="checkbox" id="collectorUnreadOnly">
                                <span><strong>Coletar apenas mensagens não lidas</strong></span>
                            </label>
                            <div class="settings-actions" style="margin-top: 12px;">
                                <button class="settings-btn success" type="button" id="createCollector">
                                    <i class="fas fa-save"></i>
                                    <span>Criar coletor</span>
                                </button>
                            </div>
                            <div class="settings-status" id="collectorStatus"></div>
                        </div>

                        <div class="settings-toolbar">
                            <label class="settings-searchbar" for="collectorSearch">
                                <i class="fas fa-search"></i>
                                <input class="settings-input" id="collectorSearch" type="search" placeholder="Pesquisar por ID, nome, host, login, status ou erro">
                            </label>
                        </div>

                        <div class="table-responsive">
                            <table class="settings-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Host</th>
                                        <th>Login</th>
                                        <th>Erros</th>
                                        <th>Última coleta</th>
                                        <th>Status</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="collectorsTable">
                                    <tr><td colspan="8">Carregando coletores...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </section>
        </form>

        <div class="settings-modal" id="notificationTemplateModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="notificationTemplateModalTitle">
            <div class="settings-modal-dialog" role="document">
                <div class="settings-modal-header">
                    <div class="settings-modal-title" id="notificationTemplateModalTitle">Editar modelo</div>
                    <div class="settings-modal-actions">
                        <button class="settings-btn success" type="button" id="saveNotificationTemplate">
                            <i class="fas fa-save"></i>
                            <span>Salvar</span>
                        </button>
                        <button class="settings-btn settings-modal-close" type="button" id="closeNotificationTemplateModal" aria-label="Fechar editor de modelo">
                            <i class="fas fa-times"></i>
                            <span>Fechar</span>
                        </button>
                    </div>
                </div>
                <div class="settings-template-modal-body">
                    <div class="settings-template-editor">
                        <input type="hidden" id="templateEditorId">
                        <input type="hidden" id="templateEditorTranslationId">
                        <input type="hidden" id="templateEditorRawName">
                        <input type="hidden" id="templateEditorItemtype">
                        <div class="settings-field-group">
                            <label class="settings-field-label" for="templateEditorName">Modelo</label>
                            <input class="settings-input" id="templateEditorName" type="text" readonly>
                        </div>
                        <div class="sla-two-cols">
                            <div class="settings-field-group">
                                <label class="settings-field-label" for="templateEditorLanguage">Idioma</label>
                                <input class="settings-input" id="templateEditorLanguage" type="text" maxlength="10" placeholder="pt_BR">
                            </div>
                            <div class="settings-field-group">
                                <label class="settings-field-label" for="templateEditorSubject">Assunto</label>
                                <input class="settings-input" id="templateEditorSubject" type="text" maxlength="255" placeholder="Assunto">
                            </div>
                        </div>
                        <div class="settings-field-group">
                            <label class="settings-field-label" for="templateEditorHtml">Corpo do texto HTML do e-mail</label>
                            <div class="settings-code-mode"><i class="fas fa-code"></i> Modo código HTML ativo</div>
                            <textarea class="settings-textarea" id="templateEditorHtml" rows="18" placeholder="Corpo HTML"></textarea>
                            <div class="settings-field-help">Cole aqui o HTML diretamente. Este campo já equivale ao modo &lt;&gt; do editor do GLPI.</div>
                        </div>
                        <div class="settings-field-group">
                            <label class="settings-field-label" for="templateEditorText">Corpo do texto do e-mail</label>
                            <textarea class="settings-textarea" id="templateEditorText" rows="8" placeholder="Corpo texto"></textarea>
                        </div>
                    </div>
                    <div class="settings-template-preview">
                        <div class="settings-preview-label">Preview HTML renderizado</div>
                        <div class="settings-field-help">Visualização aproximada de como o HTML será exibido no e-mail.</div>
                        <iframe class="settings-preview-frame" id="templatePreviewFrame" sandbox="allow-same-origin" title="Preview do modelo de notificação"></iframe>
                    </div>
                </div>
                <div class="settings-status settings-template-status" id="notificationTemplateStatus"></div>
            </div>
        </div>

        <div class="settings-modal" id="notificationInfoModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="notificationInfoModalTitle">
            <div class="settings-modal-dialog info" role="document">
                <div class="settings-modal-header">
                    <div class="settings-modal-title" id="notificationInfoModalTitle">Detalhes da notificação</div>
                    <div class="settings-modal-actions">
                        <button class="settings-btn primary" type="button" id="createIndividualTemplateInfoButton" hidden>
                            <i class="fas fa-file-circle-plus"></i>
                            <span>Criar modelo individual</span>
                        </button>
                        <button class="settings-btn primary" type="button" id="fixFollowupTemplateButton" hidden>
                            <i class="fas fa-wrench"></i>
                            <span>Corrigir follow-up</span>
                        </button>
                        <button class="settings-btn settings-modal-close" type="button" id="closeNotificationInfoModal" aria-label="Fechar detalhes da notificação">
                            <i class="fas fa-times"></i>
                            <span>Fechar</span>
                        </button>
                    </div>
                </div>
                <div class="settings-info-modal-body">
                    <div class="settings-info-grid" id="notificationInfoGrid"></div>
                    <section class="settings-info-section" id="notificationInitialConfigSection" hidden>
                        <div class="settings-preview-label">Configuração inicial</div>
                        <div class="settings-field-help">Essas notificações fazem parte do conjunto padrão recomendado para a primeira configuração.</div>
                        <div class="settings-diagnostic-card" id="notificationInitialConfigCard"></div>
                    </section>
                    <section class="settings-info-section" id="notificationNoiseControlSection" hidden>
                        <div class="settings-preview-label">Controle de ruído</div>
                        <div class="settings-field-help">Ações opcionais para evitar disparos duplicados em eventos genéricos de atualização do chamado.</div>
                        <div class="settings-diagnostic-card" id="notificationNoiseControlCard"></div>
                    </section>
                    <section class="settings-info-section" id="notificationFollowupDiagnosticSection" hidden>
                        <div class="settings-preview-label">Diagnóstico de follow-up</div>
                        <div class="settings-field-help">A correção guiada usa o preset Hafen e religa os eventos Add Followup e Update Followup ao template dedicado de acompanhamento.</div>
                        <div class="settings-diagnostic-card" id="notificationFollowupDiagnosticCard"></div>
                    </section>
                    <div class="settings-status" id="notificationInfoStatus"></div>
                </div>
            </div>
        </div>

        <div class="settings-modal" id="notificationConfirmModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="notificationConfirmModalTitle">
            <div class="settings-modal-dialog compact" role="document">
                <div class="settings-modal-header">
                    <div class="settings-modal-title" id="notificationConfirmModalTitle">Confirmar desativação</div>
                    <div class="settings-modal-actions">
                        <button class="settings-btn danger" type="button" id="confirmNotificationBulkDisable">
                            <i class="fas fa-ban" id="notificationConfirmButtonIcon"></i>
                            <span id="notificationConfirmButtonLabel">Desativar</span>
                        </button>
                        <button class="settings-btn settings-modal-close" type="button" id="closeNotificationConfirmModal" aria-label="Cancelar ação">
                            <i class="fas fa-times"></i>
                            <span>Cancelar</span>
                        </button>
                    </div>
                </div>
                <div class="settings-confirm-body">
                    <div class="settings-confirm-message" id="notificationConfirmMessage">Confirmar ação?</div>
                </div>
            </div>
        </div>

        <div class="settings-modal" id="channelCredentialModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="channelCredentialModalTitle">
            <div class="settings-modal-dialog compact" role="document">
                <div class="settings-modal-header">
                    <div class="settings-modal-title" id="channelCredentialModalTitle">Nova credencial</div>
                    <div class="settings-modal-actions">
                        <button class="settings-btn primary" type="button" id="saveChannelCredentialButton">
                            <i class="fas fa-save"></i>
                            <span>Salvar</span>
                        </button>
                        <button class="settings-btn settings-modal-close" type="button" id="closeChannelCredentialModal" aria-label="Cancelar">
                            <i class="fas fa-times"></i>
                            <span>Cancelar</span>
                        </button>
                    </div>
                </div>
                <div class="settings-confirm-body">
                    <div class="settings-field-group">
                        <label class="settings-field-label" for="credentialName">Nome</label>
                        <input type="text" class="form-control form-control-sm" id="credentialName" placeholder="Ex.: Bot TI">
                    </div>
                    <div class="settings-field-group" data-cred-field="teams" hidden>
                        <label class="settings-field-label" for="credentialWebhookUrl">Webhook URL</label>
                        <input type="url" class="form-control form-control-sm" id="credentialWebhookUrl" placeholder="https://outlook.office.com/webhook/...">
                    </div>
                    <div class="settings-field-group" data-cred-field="whatsapp" hidden>
                        <label class="settings-field-label" for="credentialApiUrl">URL da Evolution API</label>
                        <input type="url" class="form-control form-control-sm" id="credentialApiUrl" placeholder="http://kawa_evolutionapi:8080">
                    </div>
                    <div class="settings-field-group" data-cred-field="whatsapp" hidden>
                        <label class="settings-field-label" for="credentialInstance">Instância</label>
                        <input type="text" class="form-control form-control-sm" id="credentialInstance" placeholder="fealq">
                    </div>
                    <div class="settings-field-group" data-cred-field="whatsapp" hidden>
                        <label class="settings-field-label" for="credentialApiKey">API Key</label>
                        <input type="password" class="form-control form-control-sm" id="credentialApiKey" placeholder="obrigatório ao criar">
                    </div>
                    <div class="settings-field-group" data-cred-field="telegram" hidden>
                        <label class="settings-field-label" for="credentialBotToken">Bot Token</label>
                        <input type="password" class="form-control form-control-sm" id="credentialBotToken" placeholder="obrigatório ao criar">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="credentialIsDefault">
                        <label class="form-check-label" for="credentialIsDefault">Marcar como padrão deste canal</label>
                    </div>
                    <div class="settings-status" id="channelCredentialStatus"></div>
                </div>
            </div>
        </div>

        <div class="settings-modal" id="channelsFaqModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="channelsFaqModalTitle">
            <div class="settings-modal-dialog faq" role="document">
                <div class="settings-modal-header">
                    <div class="settings-modal-title" id="channelsFaqModalTitle">FAQ — Como configurar os canais de alerta</div>
                    <div class="settings-modal-actions">
                        <button class="settings-btn settings-modal-close" type="button" id="closeChannelsFaqModal" aria-label="Fechar instruções">
                            <i class="fas fa-times"></i>
                            <span>Fechar</span>
                        </button>
                    </div>
                </div>
                <div class="settings-faq-tabs">
                    <button class="settings-faq-tab" type="button" data-faq-tab="teams"><i class="fab fa-microsoft"></i> Teams</button>
                    <button class="settings-faq-tab" type="button" data-faq-tab="whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp</button>
                    <button class="settings-faq-tab" type="button" data-faq-tab="telegram"><i class="fab fa-telegram"></i> Telegram</button>
                </div>
                <div class="settings-faq-body">
                    <div class="settings-faq-section" data-faq-section="teams">
                        <p class="settings-faq-intro">
                            O DashGLPI já envia, no gatilho <strong>Chamado Atribuído</strong>, o nome e o e-mail do
                            técnico no campo <code>dashglpiMention</code> do payload Teams. A menção real
                            (<code>@usuário</code>) é construída por um flow externo no
                            <strong>Power Automate / Logic Apps</strong> — isso fica fora do DashGLPI e precisa ser
                            criado uma vez pelo time de infra. Passo a passo abaixo.
                        </p>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">1. Criar o flow (Power Automate)</div>
                            <ol>
                                <li><code>make.powerautomate.com</code> → <em>Meus fluxos</em> → <em>Novo fluxo</em> → <em>Fluxo de nuvem automatizado</em> → gatilho <strong>"Quando uma solicitação HTTP for recebida"</strong>.</li>
                                <li>Em "Usar exemplo de carga JSON para gerar esquema", cole o payload que o <code>teams_channel.php</code> envia:</li>
                            </ol>
                            <pre class="settings-faq-code">{
  "@type": "MessageCard",
  "@context": "https://schema.org/extensions",
  "themeColor": "2A9D8F",
  "summary": "Info - Chamado #1234",
  "title": "ℹ️ Info",
  "sections": [{
    "activityTitle": "**Chamado #1234**",
    "activitySubtitle": "Fealq · Orquestrador de Eventos",
    "facts": [{"title": "Chamado", "value": "#1234"}],
    "markdown": true
  }],
  "potentialAction": [
    {"@type": "OpenUri", "name": "Visualizar Chamado (GLPI)", "targets": [{"os": "default", "uri": "https://glpi.exemplo/front/ticket.form.php?id=1234"}]},
    {"@type": "OpenUri", "name": "Abrir no DashGLPI", "targets": [{"os": "default", "uri": "https://dashglpi.exemplo/front/dashboard.php?ticket_id=1234#tickets"}]}
  ],
  "dashglpiMention": {"name": "Maria Silva", "email": "maria.silva@empresa.com"}
}</pre>
                            <p>Salve — isso gera a URL HTTP POST do gatilho (host <code>*.logic.azure.com</code> ou <code>*.environment.api.powerplatform.com</code>, já cobertos pela allowlist do DashGLPI). Essa URL vai virar o destinatário "Webhook específico" da ação Teams na regra.</p>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">2. Resolver a identidade AAD do técnico</div>
                            <ol>
                                <li>Ação <strong>Office 365 Users → "Obter perfil do usuário (V2)"</strong>, com Usuário (UPN) = <code>triggerBody()?['dashglpiMention']?['email']</code>.</li>
                                <li>Adicione uma <strong>Condição</strong>: se essa ação falhar (e-mail do GLPI não bate com nenhum UPN do Azure AD), poste o card sem menção (só o nome em texto) no ramo "Não" — mesma degradação que o DashGLPI já promete no payload.</li>
                            </ol>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">3. Montar o Adaptive Card com menção real</div>
                            <p>Ação <strong>Microsoft Teams → "Postar cartão adaptável em um bate-papo ou canal"</strong>, com um Adaptive Card dinâmico:</p>
                            <pre class="settings-faq-code">{
  "type": "AdaptiveCard",
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "version": "1.4",
  "body": [
    {
      "type": "TextBlock",
      "wrap": true,
      "text": "&lt;at&gt;@{triggerBody()?['dashglpiMention']?['name']}&lt;/at&gt; — @{triggerBody()?['title']}"
    }
  ],
  "actions": [
    {"type": "Action.OpenUrl", "title": "Visualizar Chamado (GLPI)", "url": "@{first(triggerBody()?['potentialAction'])?['targets'][0]?['uri']}"},
    {"type": "Action.OpenUrl", "title": "Abrir no DashGLPI", "url": "@{last(triggerBody()?['potentialAction'])?['targets'][0]?['uri']}"}
  ],
  "msteams": {
    "entities": [
      {
        "type": "mention",
        "text": "&lt;at&gt;@{triggerBody()?['dashglpiMention']?['name']}&lt;/at&gt;",
        "mentioned": {
          "id": "@{outputs('Obter_perfil_do_usuário_(V2)')?['body/id']}",
          "name": "@{triggerBody()?['dashglpiMention']?['name']}"
        }
      }
    ]
  }
}</pre>
                            <p>O <code>&lt;at&gt;Nome&lt;/at&gt;</code> do <code>TextBlock</code> precisa casar caractere a caractere com o <code>text</code> da entidade em <code>msteams.entities</code> — é assim que o Teams renderiza a menção clicável.</p>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">4. Testar isoladamente</div>
                            <p>Use o botão "Testar" do próprio flow (ou um POST via curl/Postman com o JSON do passo 1) e confirme que a menção aparece de verdade no canal — não só o nome em texto — antes de ligar ao DashGLPI.</p>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">5. Ligar ao DashGLPI</div>
                            <ol>
                                <li>Copie a URL HTTP do gatilho (passo 1).</li>
                                <li>Configurações → Regras → gatilho <strong>"Chamado Atribuído"</strong> → ação Teams → destinatário "Webhook específico" → cole a URL.</li>
                                <li>Ative a regra e atribua um chamado de teste a um técnico com e-mail cadastrado em <code>glpi_useremails</code> igual ao UPN dele no Azure AD.</li>
                            </ol>
                        </div>

                        <div class="settings-faq-warning">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>Se o e-mail do GLPI não bater com o UPN do Azure AD, a menção falha do lado do Power Automate (não é bug do DashGLPI) — valide manualmente antes de ativar em produção. A URL do gatilho HTTP contém uma assinatura (<code>sig=...</code>) que funciona como credencial: não exponha em log/commit.</span>
                        </div>
                    </div>

                    <div class="settings-faq-section" data-faq-section="whatsapp" hidden>
                        <p class="settings-faq-intro">
                            WhatsApp é 100% nativo no DashGLPI via <strong>Evolution API</strong> — sem flow externo.
                            É preciso conectar um número uma vez e preencher as credenciais em
                            <strong>Canais de Alerta</strong> antes de qualquer regra funcionar.
                        </p>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">1. Infra (já no docker-compose)</div>
                            <p>Serviço <code>kawa_evolutionapi</code> (porta padrão <code>8080</code>), com <code>AUTHENTICATION_API_KEY</code> definido no <code>.env</code>.</p>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">2. Conectar uma instância</div>
                            <p>Criar/conectar uma instância na Evolution API (Manager UI ou <code>POST /instance/create</code>) e escanear o QR Code com o WhatsApp que vai disparar as mensagens — precisa ficar com status "open"/conectada.</p>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">3. Configuração global (Canais de Alerta → WhatsApp)</div>
                            <ol>
                                <li>Ativar o switch.</li>
                                <li><strong>URL da Evolution API</strong> (ex.: <code>http://kawa_evolutionapi:8080</code>, rede interna).</li>
                                <li><strong>Instância</strong> (nome criado no passo 2).</li>
                                <li><strong>API Key</strong> (o <code>AUTHENTICATION_API_KEY</code>).</li>
                                <li>Números destino padrão (usados pelos workers fixos de SLA/tarefa) e testar com "Testar WhatsApp".</li>
                            </ol>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">4. Na regra</div>
                            <p>Ação <strong>WhatsApp</strong> → destinatário automático <code>specific_number</code>. Informar o número em <strong>DDI+DDD+número, só dígitos</strong> (ex.: <code>5511999998888</code>, mínimo 10 dígitos).</p>
                        </div>

                        <div class="settings-faq-warning">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>Sem allowlist de host aqui (isso só existe pro webhook do Teams) — a validação é só de formato do número. Não há suporte a menção real de usuário no WhatsApp.</span>
                        </div>
                    </div>

                    <div class="settings-faq-section" data-faq-section="telegram" hidden>
                        <p class="settings-faq-intro">
                            Telegram também é nativo, via <strong>Bot API</strong> — sem flow externo. É preciso criar
                            um bot e descobrir o chat de destino antes de configurar a regra.
                        </p>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">1. Criar o bot</div>
                            <p>Falar com <strong>@BotFather</strong> no Telegram → <code>/newbot</code> → copiar o <strong>bot token</strong>.</p>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">2. Obter o chat_id</div>
                            <p>Adicionar o bot ao grupo/canal (ou iniciar DM com ele) e enviar uma mensagem. Consultar <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> (ou um bot utilitário tipo @get_id_bot) para pegar o <code>chat_id</code> — grupos vêm como número <strong>negativo</strong> (ex.: <code>-1001234567890</code>).</p>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">3. Configuração global (Canais de Alerta → Telegram)</div>
                            <ol>
                                <li>Ativar o switch.</li>
                                <li><strong>Bot Token</strong>.</li>
                                <li>Chat IDs padrão (workers fixos) e testar com "Testar Telegram".</li>
                            </ol>
                        </div>

                        <div class="settings-faq-step">
                            <div class="settings-faq-step-title">4. Na regra</div>
                            <p>Ação <strong>Telegram</strong> → destinatário automático <code>specific_chat</code>. Informar o chat_id (regex <code>^-?\d+$</code>).</p>
                        </div>

                        <div class="settings-faq-warning">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>Sem allowlist de host (a validação é só de formato do chat_id). Não há suporte a menção real de usuário no Telegram.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-status" id="settingsStatus" <?= $showGlobalSettingsSave ? '' : 'hidden' ?>></div>
    </main>

    <script>
        const fallbackLogoLight = '<?= htmlspecialchars(dashglpi_settings_defaults('reports')['logo_light_url'], ENT_QUOTES, 'UTF-8') ?>';
        const fallbackLogoDark = '<?= htmlspecialchars(dashglpi_settings_defaults('reports')['logo_dark_url'], ENT_QUOTES, 'UTF-8') ?>';
        let slaEntities = <?= json_encode($slaEntities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let slaCalendars = <?= json_encode($slaCalendars, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let slaState = {
            settings: <?= json_encode($slaSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            rules_status: { base_ready: false, items: [] },
            reminders: { base_ready: false, items: [] },
            sla_notification: {
                base_ready: false,
                blocked: false,
                event: { available: false, event_key: '', event_label: '' },
                recipient_catalog: {},
                recipient_keys: <?= json_encode(array_values((array) ($slaSettings['sla_notification']['recipient_keys'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                recipient_labels: [],
                notification: {},
                template: {},
                managed_ids: {}
            },
            automation: { queuednotification: {}, slaticket: {}, recommendation_ok: false },
            notification_catalog_subset: { itemtype: 'Ticket', itemtype_label: 'Chamado', special_event: { available: false, event_key: '', event_label: '' }, targets: {} },
            ticket_templates: []
        };
        const settingsCsrfToken = '<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>';
        const activeSettingsSection = '<?= htmlspecialchars($settingsSection, ENT_QUOTES, 'UTF-8') ?>';
        let notificationState = {
            notifications: [],
            templates: [],
            entities: [],
            summary: {},
            mail_settings: {},
            delivery: {},
            catalog: { itemtypes: {}, events_by_itemtype: {}, targets_by_itemtype: {}, oauth: { providers: [], additional_parameters: {} } }
        };
        let notificationMailPersistedState = {};
        let notificationMailDirty = false;
        let collectorState = { collectors: [], summary: {} };
        let profileAccessState = { profiles: [], rules: [], pageCatalog: {}, selectedProfileId: 0 };
        let notificationEditorState = { recipients: [] };
        let notificationEditorInitialized = false;
        let activeSlaStep = 'base';
        let activeNotificationStep = 'setup';
        let slaNotificationRecipients = Array.isArray(slaState.sla_notification?.recipient_keys)
            ? [...slaState.sla_notification.recipient_keys]
            : [];
        let notificationTemplateContext = 'notifications';
        const notificationSelection = new Set();
        const notificationToggleInFlight = new Set();
        const cleanupSelection = new Set();
        const notificationColumnFilters = { name: '', itemtype: '', event: '', entity: '', template: '', status: '1' };
        const settingsFilters = { templates: '', collectors: '', cleanup: '' };
        let pendingNotificationConfirmAction = null;
        let activeNotificationInfoId = 0;
        let notificationTemplatePreviewUrl = '';
        const defaultInitialNotifications = Object.freeze({
            2: 'New Ticket',
            3: 'Update Ticket',
            5: 'Add Followup',
            6: 'Add Task',
            7: 'Update Followup',
            8: 'Update Task',
            63: 'New user in assignees'
        });
        const notificationConfirmDefaults = Object.freeze({
            title: 'Confirmar desativação',
            confirmLabel: 'Desativar',
            confirmIcon: 'fa-ban',
            confirmTone: 'danger',
            cancelAriaLabel: 'Cancelar ação'
        });

        function toggleMenu() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            if (!sidebar || !mainContent) {
                return;
            }

            const shouldOpen = arguments.length === 0
                ? sidebar.classList.contains('collapsed')
                : Boolean(arguments[0]);

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

        function isMobileViewport() {
            return window.matchMedia('(max-width: 768px)').matches;
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
            if (document.body.dataset.responsiveNavBound !== '1') {
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

            syncResponsiveNavigation();
            window.addEventListener('resize', syncResponsiveNavigation);
        }

        function initTheme() {
            const savedTheme = localStorage.getItem('glpi-theme');
            document.body.classList.toggle('light-mode', savedTheme !== 'dark');
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

        function updateLogoPreviews() {
            const lightInput = document.getElementById('logoLightUrl');
            const lightPreview = document.getElementById('logoLightPreview');
            const darkInput = document.getElementById('logoDarkUrl');
            const darkPreview = document.getElementById('logoDarkPreview');
            if (lightInput && lightPreview) {
                lightPreview.src = lightInput.value.trim() || fallbackLogoLight;
            }
            if (darkInput && darkPreview) {
                darkPreview.src = darkInput.value.trim() || fallbackLogoDark;
            }
        }

        async function readSettingsJson(response) {
            const text = await response.text();
            let data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (error) {
                console.error('Resposta nao JSON:', text);
                throw new Error('Resposta invalida do servidor. Verifique os logs.');
            }
            if (!response.ok || !data || data.ok === false) {
                throw new Error(data?.error || data?.message || 'Falha ao processar solicitacao.');
            }
            return data;
        }

        function normalizeProfileAccessRule(rule) {
            return {
                profile_id: Number(rule?.profile_id) || 0,
                enabled: Number(rule?.enabled || 0) === 1 ? 1 : 0,
                priority: rule?.priority !== null && rule?.priority !== undefined && rule?.priority !== '' ? Number(rule.priority) : null,
                allowed_pages: Array.isArray(rule?.allowed_pages) ? rule.allowed_pages.filter(Boolean) : [],
                lock_my_tasks: Object.prototype.hasOwnProperty.call(rule || {}, 'lock_my_tasks') ? (Number(rule.lock_my_tasks || 0) === 1 ? 1 : 0) : 1,
                show_ticket_urgency: Object.prototype.hasOwnProperty.call(rule || {}, 'show_ticket_urgency') ? (Number(rule.show_ticket_urgency || 0) === 1 ? 1 : 0) : 1
            };
        }

        function currentProfileAccessSelectedId() {
            const profiles = Array.isArray(profileAccessState.profiles) ? profileAccessState.profiles : [];
            const selectedId = Number(profileAccessState.selectedProfileId) || 0;
            const selectedExists = profiles.some((profile) => Number(profile.id) === selectedId);
            profileAccessState.selectedProfileId = selectedExists ? selectedId : (Number(profiles[0]?.id) || 0);
            return profileAccessState.selectedProfileId;
        }

        function applyProfileAccessPayload(data) {
            const nextProfiles = Array.isArray(data?.profiles) ? data.profiles : [];
            const previousSelectedId = Number(profileAccessState.selectedProfileId) || 0;
            const nextSelectedId = nextProfiles.some((profile) => Number(profile.id) === previousSelectedId)
                ? previousSelectedId
                : (Number(nextProfiles[0]?.id) || 0);

            profileAccessState = {
                profiles: nextProfiles,
                rules: Array.isArray(data?.settings?.rules) ? data.settings.rules.map(normalizeProfileAccessRule) : [],
                pageCatalog: data?.page_catalog && typeof data.page_catalog === 'object' ? data.page_catalog : {},
                selectedProfileId: nextSelectedId
            };
        }

        function profileAccessRulesByProfileId() {
            const map = new Map();
            (profileAccessState.rules || []).forEach((rule) => {
                const normalizedRule = normalizeProfileAccessRule(rule);
                if (normalizedRule.profile_id > 0) {
                    map.set(String(normalizedRule.profile_id), normalizedRule);
                }
            });
            return map;
        }

        function profileAccessProfileName(profileId) {
            const profile = (profileAccessState.profiles || []).find((item) => Number(item.id) === Number(profileId));
            return profile?.name || `Perfil #${profileId}`;
        }

        function profileAccessPageMeta(pageKey) {
            const raw = profileAccessState.pageCatalog?.[pageKey];
            if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
                return {
                    label: String(raw.label || pageKey),
                    icon: String(raw.icon || '')
                };
            }

            return {
                label: String(raw || pageKey),
                icon: ''
            };
        }

        function setProfileAccessRule(profileId, rule) {
            const normalizedProfileId = Number(profileId) || 0;
            if (normalizedProfileId <= 0) return;

            const nextRules = Array.isArray(profileAccessState.rules) ? [...profileAccessState.rules] : [];
            const index = nextRules.findIndex((item) => Number(item?.profile_id) === normalizedProfileId);

            if (!rule) {
                if (index >= 0) {
                    nextRules.splice(index, 1);
                }
                profileAccessState.rules = nextRules;
                return;
            }

            const normalizedRule = normalizeProfileAccessRule({ ...rule, profile_id: normalizedProfileId });
            if (normalizedRule.profile_id <= 0) return;

            if (index >= 0) {
                nextRules[index] = normalizedRule;
            } else {
                nextRules.push(normalizedRule);
            }

            profileAccessState.rules = nextRules;
        }

        function readProfileAccessRuleFromRow(row) {
            if (!row) return null;

            const profileId = Number(row.getAttribute('data-profile-access-row') || 0);
            if (profileId <= 0) return null;

            const enabled = row.querySelector('[data-profile-access-enabled]')?.checked ? 1 : 0;
            const priorityValue = (row.querySelector('[data-profile-access-priority]')?.value || '').trim();
            const allowedPages = Array.from(row.querySelectorAll('[data-profile-access-page]:checked'))
                .map((checkbox) => checkbox.value)
                .filter(Boolean);
            const lockMyTasks = row.querySelector('[data-profile-access-lock-my-tasks]')?.checked ? 1 : 0;
            const showTicketUrgency = row.querySelector('[data-profile-access-show-ticket-urgency]')?.checked ? 1 : 0;

            if (enabled === 0 && priorityValue === '' && allowedPages.length === 0 && lockMyTasks === 1 && showTicketUrgency === 1) {
                return null;
            }

            return {
                profile_id: profileId,
                enabled,
                priority: priorityValue === '' ? null : Number(priorityValue),
                allowed_pages: allowedPages,
                lock_my_tasks: lockMyTasks,
                show_ticket_urgency: showTicketUrgency
            };
        }

        function syncCurrentProfileAccessRowToState() {
            const selectedId = currentProfileAccessSelectedId();
            if (selectedId <= 0) return;

            const row = document.querySelector(`[data-profile-access-row="${selectedId}"]`);
            if (!row) return;

            setProfileAccessRule(selectedId, readProfileAccessRuleFromRow(row));
        }

        function renderProfileAccessSummary() {
            const profiles = Array.isArray(profileAccessState.profiles) ? profileAccessState.profiles : [];
            const rules = Array.isArray(profileAccessState.rules) ? profileAccessState.rules : [];
            const activeRules = rules.filter((rule) => Number(rule.enabled || 0) === 1);

            document.getElementById('profileAccessProfilesTotal').textContent = String(profiles.length);
            document.getElementById('profileAccessRulesTotal').textContent = String(rules.length);
            document.getElementById('profileAccessActiveRulesTotal').textContent = String(activeRules.length);
            document.getElementById('profileAccessPagesTotal').textContent = String(Object.keys(profileAccessState.pageCatalog || {}).length);
        }

        function renderProfileAccessPicker() {
            const select = document.getElementById('profileAccessSelect');
            if (!select) return;

            const profiles = Array.isArray(profileAccessState.profiles) ? profileAccessState.profiles : [];
            const selectedId = currentProfileAccessSelectedId();

            if (profiles.length === 0) {
                select.innerHTML = '<option value="">Nenhum perfil GLPI disponível</option>';
                select.disabled = true;
                return;
            }

            select.innerHTML = profiles.map((profile) => `
                <option value="${escapeHtml(profile.id)}" ${Number(profile.id) === selectedId ? 'selected' : ''}>
                    ${escapeHtml(profile.name || `Perfil #${profile.id}`)}
                </option>
            `).join('');
            select.disabled = false;
            select.value = String(selectedId);
        }

        function renderProfileAccessCurrent() {
            const current = document.getElementById('profileAccessCurrent');
            if (!current) return;

            const selectedId = currentProfileAccessSelectedId();
            const profile = (profileAccessState.profiles || []).find((item) => Number(item.id) === selectedId);
            if (!profile) {
                current.innerHTML = `
                    <strong>Nenhum perfil selecionado</strong>
                    <span>Selecione um perfil na lista para editar sua regra.</span>
                `;
                return;
            }

            const rule = profileAccessRulesByProfileId().get(String(selectedId)) || null;
            let summary = 'Sem regra cadastrada. O Dash usará o fallback atual.';
            if (rule) {
                summary = Number(rule.enabled || 0) === 1
                    ? `Regra ativa com prioridade ${Number(rule.priority) || '-'}.`
                    : 'Regra cadastrada, mas desativada.';
            }

            current.innerHTML = `
                <strong>${escapeHtml(profile.name || `Perfil #${profile.id}`)}</strong>
                <span>ID do perfil: ${escapeHtml(profile.id)}. ${escapeHtml(summary)}</span>
            `;
        }

        function attachProfileAccessRowListeners(row) {
            if (!row) return;

            row.querySelectorAll('[data-profile-access-enabled], [data-profile-access-priority], [data-profile-access-page], [data-profile-access-lock-my-tasks], [data-profile-access-show-ticket-urgency]').forEach((field) => {
                const eventName = field.matches('[data-profile-access-priority]') ? 'input' : 'change';
                field.addEventListener(eventName, () => {
                    syncCurrentProfileAccessRowToState();
                    renderProfileAccessSummary();
                    renderProfileAccessCurrent();
                });
            });

            row.querySelectorAll('[data-profile-access-clear]').forEach((button) => {
                button.addEventListener('click', () => resetProfileAccessRow(button.getAttribute('data-profile-access-clear')));
            });
        }

        function renderProfileAccessTable() {
            const body = document.getElementById('profileAccessTableBody');
            if (!body) return;

            const profiles = Array.isArray(profileAccessState.profiles) ? profileAccessState.profiles : [];
            const pageEntries = Object.keys(profileAccessState.pageCatalog || {});
            const selectedId = currentProfileAccessSelectedId();
            const rulesByProfile = profileAccessRulesByProfileId();

            if (profiles.length === 0 || selectedId <= 0) {
                body.innerHTML = '<tr><td colspan="5" class="profile-access-empty">Nenhum perfil GLPI disponível.</td></tr>';
                return;
            }

            const profile = profiles.find((item) => Number(item.id) === selectedId);
            if (!profile) {
                body.innerHTML = '<tr><td colspan="5" class="profile-access-empty">Selecione um perfil GLPI válido.</td></tr>';
                return;
            }

            const rule = rulesByProfile.get(String(profile.id)) || { enabled: 0, priority: null, allowed_pages: [] };
            const allowedPages = Array.isArray(rule.allowed_pages) ? rule.allowed_pages : [];
            const enabled = Number(rule.enabled || 0) === 1;
            const priority = rule.priority !== null && rule.priority !== undefined ? String(rule.priority) : '';
            const lockMyTasks = Number(rule.lock_my_tasks ?? 1) === 1;
            const showTicketUrgency = Number(rule.show_ticket_urgency ?? 1) === 1;
            const isHelpdesk = String(profile.interface || '') === 'helpdesk';
            const lockedPages = isHelpdesk ? ['dashboard', 'tickets'] : [];
            const ruleNote = isHelpdesk
                ? 'Perfil Helpdesk: regra sempre ativa, com "Visão Geral" e "Chamados" liberadas.'
                : (enabled
                    ? 'Regra ativa. Menor prioridade numérica vence.'
                    : (priority !== '' || allowedPages.length > 0 ? 'Regra cadastrada, mas desativada.' : 'Sem regra cadastrada. O Dash usará o fallback atual.'));

            body.innerHTML = `
                <tr data-profile-access-row="${Number(profile.id)}">
                    <td class="profile-access-rule-cell">
                        <label class="profile-access-toggle">
                            <input type="checkbox" data-profile-access-enabled ${(enabled || isHelpdesk) ? 'checked' : ''} ${isHelpdesk ? 'disabled' : ''}>
                            <span>Ativar regra</span>
                        </label>
                        <div class="profile-access-rule-note">${escapeHtml(ruleNote)}</div>
                    </td>
                    <td>
                        <input
                            class="settings-number profile-access-priority"
                            type="number"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            data-profile-access-priority
                            value="${escapeHtml(priority)}"
                            placeholder="1"
                        >
                    </td>
                    <td>
                        <div class="profile-access-pages">
                            ${pageEntries.map((pageKey) => {
                                const meta = profileAccessPageMeta(pageKey);
                                const locked = lockedPages.includes(pageKey);
                                return `
                                    <label class="profile-access-page">
                                        <input type="checkbox" value="${escapeHtml(pageKey)}" data-profile-access-page ${(allowedPages.includes(pageKey) || locked) ? 'checked' : ''} ${locked ? 'disabled' : ''}>
                                        ${meta.icon ? `<i class="fas ${escapeHtml(meta.icon)}" aria-hidden="true"></i>` : ''}
                                        <span>${escapeHtml(meta.label)}</span>
                                    </label>
                                `;
                            }).join('')}
                        </div>
                    </td>
                    <td>
                        <div class="profile-access-pages">
                            <label class="profile-access-page" title="Quando ligado, o usuário sempre consulta apenas os chamados vinculados a ele.">
                                <input type="checkbox" data-profile-access-lock-my-tasks ${lockMyTasks || isHelpdesk ? 'checked' : ''} ${isHelpdesk ? 'disabled' : ''}>
                                <i class="fas fa-user-lock" aria-hidden="true"></i>
                                <span>Bloquear Minhas Tarefas</span>
                            </label>
                            <label class="profile-access-page" title="Controla a exibicao do campo Urgencia na abertura do chamado.">
                                <input type="checkbox" data-profile-access-show-ticket-urgency ${showTicketUrgency ? 'checked' : ''}>
                                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                                <span>Mostrar Urgencia</span>
                            </label>
                        </div>
                    </td>
                    <td>
                        <button class="settings-btn danger" type="button" data-profile-access-clear="${Number(profile.id)}">
                            <i class="fas fa-eraser"></i>
                            <span>Limpar regra</span>
                        </button>
                    </td>
                </tr>
            `;

            attachProfileAccessRowListeners(body.querySelector('[data-profile-access-row]'));
        }

        async function loadProfileAccess(announce = false) {
            const reloadButton = document.getElementById('reloadProfileAccess');
            const saveButton = document.getElementById('saveProfileAccess');
            if (reloadButton) reloadButton.disabled = true;
            if (saveButton) saveButton.disabled = true;

            setSettingsStatus('profileAccessStatus', announce ? 'Carregando perfis e regras...' : '', '');

            try {
                const response = await fetch('/ajax/profile_access.php', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await readSettingsJson(response);
                applyProfileAccessPayload(data);
                renderProfileAccessSummary();
                renderProfileAccessPicker();
                renderProfileAccessCurrent();
                renderProfileAccessTable();
                setSettingsStatus('profileAccessStatus', announce ? 'Perfis e regras carregados.' : '', announce ? 'ok' : '');
            } catch (error) {
                setSettingsStatus('profileAccessStatus', error.message || 'Erro ao carregar acesso por perfil.', 'error');
            } finally {
                if (reloadButton) reloadButton.disabled = false;
                if (saveButton) saveButton.disabled = false;
            }
        }

        function resetProfileAccessRow(profileId) {
            const normalizedProfileId = Number(profileId) || 0;
            const row = document.querySelector(`[data-profile-access-row="${normalizedProfileId}"]`);
            if (!row) return;

            const enabled = row.querySelector('[data-profile-access-enabled]');
            const priority = row.querySelector('[data-profile-access-priority]');
            const pages = row.querySelectorAll('[data-profile-access-page]');

            if (enabled) enabled.checked = false;
            if (priority) priority.value = '';
            pages.forEach((checkbox) => {
                checkbox.checked = false;
            });

            setProfileAccessRule(normalizedProfileId, null);
            renderProfileAccessSummary();
            renderProfileAccessCurrent();
            renderProfileAccessTable();
        }

        function collectProfileAccessRules() {
            syncCurrentProfileAccessRowToState();
            return (Array.isArray(profileAccessState.rules) ? profileAccessState.rules : [])
                .map(normalizeProfileAccessRule)
                .filter((rule) => rule.profile_id > 0);
        }

        function validateProfileAccessRules(rules) {
            const priorities = new Map();

            for (const rule of rules) {
                if (Number(rule.enabled || 0) !== 1) {
                    continue;
                }

                const profileName = profileAccessProfileName(rule.profile_id);
                const priority = Number(rule.priority);
                if (!Number.isInteger(priority) || priority <= 0) {
                    return `Informe uma prioridade inteira positiva para o perfil "${profileName}".`;
                }

                if (!Array.isArray(rule.allowed_pages) || rule.allowed_pages.length === 0) {
                    return `Selecione ao menos uma página para o perfil "${profileName}".`;
                }

                if (priorities.has(priority)) {
                    return `A prioridade ${priority} já está em uso por "${priorities.get(priority)}".`;
                }

                priorities.set(priority, profileName);
            }

            return '';
        }

        async function saveProfileAccess() {
            const reloadButton = document.getElementById('reloadProfileAccess');
            const saveButton = document.getElementById('saveProfileAccess');
            const rules = collectProfileAccessRules();
            const validationError = validateProfileAccessRules(rules);
            if (validationError) {
                setSettingsStatus('profileAccessStatus', validationError, 'error');
                return;
            }

            if (reloadButton) reloadButton.disabled = true;
            if (saveButton) saveButton.disabled = true;
            setSettingsStatus('profileAccessStatus', 'Salvando regras de acesso...', '');

            try {
                const response = await fetch('/ajax/profile_access.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: settingsCsrfToken,
                        rules
                    })
                });
                const data = await readSettingsJson(response);
                applyProfileAccessPayload(data);
                renderProfileAccessSummary();
                renderProfileAccessPicker();
                renderProfileAccessCurrent();
                renderProfileAccessTable();
                setSettingsStatus('profileAccessStatus', 'Regras de acesso salvas com sucesso.', 'ok');
            } catch (error) {
                setSettingsStatus('profileAccessStatus', error.message || 'Erro ao salvar acesso por perfil.', 'error');
            } finally {
                if (reloadButton) reloadButton.disabled = false;
                if (saveButton) saveButton.disabled = false;
            }
        }

        function setSettingsStatus(id, message, type = '') {
            const status = document.getElementById(id);
            if (!status) return;
            status.className = 'settings-status' + (type ? ' ' + type : '');
            status.textContent = message || '';
        }

        function optionRows(map, selected = '') {
            return Object.entries(map || {}).map(([value, label]) => (
                `<option value="${escapeHtml(value)}" ${String(value) === String(selected) ? 'selected' : ''}>${escapeHtml(label)}</option>`
            )).join('');
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function decodeHtmlEntities(value) {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = value ?? '';
            return textarea.value;
        }

        function formatBool(value) {
            return Number(value || 0) === 1 ? 'Ativo' : 'Inativo';
        }

        function formatDate(value) {
            if (!value) return '-';
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return String(value);
            return date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        function formatDurationSeconds(value) {
            const total = Math.max(0, Number(value) || 0);
            if (!total) return '0m';
            const days = Math.floor(total / 86400);
            const hours = Math.floor((total % 86400) / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            if (days > 0) return `${days}d ${hours}h`;
            if (hours > 0) return `${hours}h ${minutes}m`;
            return `${minutes}m`;
        }

        function normalizeSearch(value) {
            return String(value ?? '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        }

        function rowMatchesSearch(row, queryParts) {
            if (!queryParts.length) return true;
            const haystack = normalizeSearch(row.join(' '));
            return queryParts.every((part) => haystack.includes(part));
        }

        function searchParts(value) {
            return normalizeSearch(value).split(/\s+/).filter(Boolean);
        }

        function notificationItemtypeLabel(itemtype) {
            return notificationState.catalog?.itemtypes?.[itemtype] || itemtype || '-';
        }

        function notificationEventLabel(itemtype, event) {
            return notificationState.catalog?.events_by_itemtype?.[itemtype]?.[event] || event || '-';
        }

        function notificationSearchRow(row) {
            return [
                `#${Number(row.id) || 0}`,
                row.name || '',
                notificationItemtypeLabel(row.itemtype),
                notificationEventLabel(row.itemtype, row.event),
                row.entity_name || 'Entidade raiz',
                row.template_names || '',
                row.recipient_summary || '',
                formatBool(row.is_active)
            ];
        }

        function templateSearchRow(row) {
            return [
                `#${Number(row.id) || 0}`,
                row.name || '',
                notificationItemtypeLabel(row.itemtype),
                row.language || 'pt_BR',
                row.subject || ''
            ];
        }

        function collectorSearchRow(row) {
            return [
                `#${Number(row.id) || 0}`,
                row.name || '',
                row.host || '',
                row.login || '',
                Number(row.errors) || 0,
                Number(row.errors) > 0 ? 'Com erro erro' : 'Sem falha ok',
                formatBool(row.is_active)
            ];
        }

        function setCalloutState(calloutId, titleId, textId, title, text, tone = '') {
            const callout = document.getElementById(calloutId);
            const titleElement = document.getElementById(titleId);
            const textElement = document.getElementById(textId);
            if (!callout || !titleElement || !textElement) return;
            callout.className = 'settings-callout' + (tone ? ' ' + tone : '');
            titleElement.textContent = title || '';
            textElement.textContent = text || '';
        }

        function slaUnitLabel(unit, value) {
            const numeric = Number(value) || 0;
            if (unit === 'minute') return numeric === 1 ? 'minuto' : 'minutos';
            if (unit === 'hour') return numeric === 1 ? 'hora' : 'horas';
            if (unit === 'day') return numeric === 1 ? 'dia' : 'dias';
            if (unit === 'month') return numeric === 1 ? 'mês' : 'meses';
            return unit || '';
        }

        function formatSlaDurationLabel(value, unit) {
            const numeric = Number(value) || 0;
            if (!numeric) return '-';
            return `${numeric} ${slaUnitLabel(unit, numeric)}`;
        }

        function setSlaStep(step) {
            activeSlaStep = step;
            let activeButton = null;
            document.querySelectorAll('[data-sla-step-target]').forEach((button) => {
                const isActive = button.dataset.slaStepTarget === step;
                button.classList.toggle('active', isActive);
                if (isActive) {
                    activeButton = button;
                }
            });
            document.querySelectorAll('[data-sla-step-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.slaStepPanel !== step;
            });

            const summaryTitle = document.getElementById('slaStepSummaryTitle');
            const summaryText = document.getElementById('slaStepSummaryText');
            if (activeButton && summaryTitle && summaryText) {
                summaryTitle.textContent = activeButton.dataset.slaStepTitle || activeButton.querySelector('strong')?.textContent || '';
                summaryText.textContent = activeButton.dataset.slaStepDescription || activeButton.querySelector('.settings-step-desc')?.textContent || '';
            }
        }

        function initSlaSteps() {
            document.querySelectorAll('[data-sla-step-target]').forEach((button) => {
                button.addEventListener('click', () => setSlaStep(button.dataset.slaStepTarget));
            });
            setSlaStep(activeSlaStep);
        }

        function hydrateStoredSlaStatuses() {
            setSlaStatus('entity', document.getElementById('slaEntityMode')?.value === 'existing' ? 'existing' : 'pending');
            setSlaStatus('calendar', Number(slaState.settings?.ids?.calendars_id || 0) > 0 ? 'existing' : 'pending');
            setSlaStatus('slm', Number(slaState.settings?.ids?.slms_id || 0) > 0 ? 'existing' : 'pending');
            Object.keys(slaState.settings?.slas || {}).forEach((key) => {
                setSlaStatus(key, Number(slaState.settings?.ids?.slas?.[key] || 0) > 0 ? 'existing' : 'pending');
            });
        }

        function hydrateSlaNotificationFields() {
            const notification = slaState.sla_notification?.notification || {};
            const template = slaState.sla_notification?.template || {};
            const setValue = (id, value) => {
                const element = document.getElementById(id);
                if (element) {
                    element.value = value ?? '';
                }
            };
            setValue('slaNotificationName', notification.name || 'SLA');
            setValue('slaNotificationEntity', notification.entities_id ?? 0);
            setValue('slaNotificationTemplateName', template.name || 'Notificação por E-mail SLA');
            setValue('slaNotificationTemplateLanguage', template.language || 'pt_BR');
            setValue('slaNotificationTemplateSubject', template.subject || 'Notificação de SLA');
            setValue('slaNotificationTemplateId', template.id || 0);
            setValue('slaNotificationTemplateTranslationId', template.translation_id || 0);
            setValue('slaNotificationTemplateHtml', template.content_html || '');
            setValue('slaNotificationTemplateText', template.content_text || '');
            const active = document.getElementById('slaNotificationActive');
            const recursive = document.getElementById('slaNotificationRecursive');
            if (active) active.checked = Number(notification.is_active ?? 1) === 1;
            if (recursive) recursive.checked = Number(notification.is_recursive ?? 1) === 1;
            slaNotificationRecipients = Array.isArray(slaState.sla_notification?.recipient_keys)
                ? [...slaState.sla_notification.recipient_keys]
                : [];
            syncSlaNotificationRecipientInputs();
        }

        function applySlaDataset(data, options = {}) {
            slaEntities = data.entities || slaEntities;
            slaCalendars = data.calendars || slaCalendars;
            slaState = {
                ...slaState,
                settings: data.settings || slaState.settings,
                rules_status: data.rules_status || slaState.rules_status,
                reminders: data.reminders || slaState.reminders,
                sla_notification: data.sla_notification || slaState.sla_notification,
                automation: data.automation || slaState.automation,
                notification_catalog_subset: data.notification_catalog_subset || slaState.notification_catalog_subset,
                ticket_templates: data.ticket_templates || slaState.ticket_templates
            };

            updateSlaLists({ settings: slaState.settings, entities: slaEntities, calendars: slaCalendars });
            hydrateStoredSlaStatuses();
            renderSlaRuleStatus();
            renderSlaReminderStep();
            if (options.hydrateNotification !== false) {
                hydrateSlaNotificationFields();
            }
            renderSlaNotificationStep();
            renderSlaNotificationRecipientCatalog();
            renderSelectedSlaNotificationRecipients();
            renderSlaAutomation();
        }

        async function loadSlaFlow() {
            try {
                const response = await fetch('/ajax/sla-simple.php', { headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                applySlaDataset(data);
            } catch (error) {
                setSettingsStatus('slaStatus', error.message || 'Erro ao carregar o fluxo de SLA.', 'error');
                setSettingsStatus('slaReminderStatus', error.message || 'Erro ao carregar alertas de SLA.', 'error');
                setSettingsStatus('slaNotificationStatus', error.message || 'Erro ao carregar a notificação de SLA.', 'error');
                setSettingsStatus('slaAutomationStatus', error.message || 'Erro ao carregar automações de SLA.', 'error');
            }
        }

        function renderSlaRuleStatus() {
            const table = document.getElementById('slaRulesStatusTable');
            const rules = slaState.rules_status?.items || [];
            if (table) {
                table.innerHTML = rules.length ? rules.map((row) => `
                    <tr>
                        <td>${escapeHtml(row.name || '-')}</td>
                        <td>Prioridade ${escapeHtml(row.priority || '-')}</td>
                        <td>${escapeHtml(row.tto_key || '-')}</td>
                        <td>${escapeHtml(row.ttr_key || '-')}</td>
                        <td>${escapeHtml(row.status_label || 'Pendente')}</td>
                    </tr>
                `).join('') : '<tr><td colspan="5">Nenhuma regra gerenciada encontrada.</td></tr>';
            }

            const baseReady = Boolean(slaState.rules_status?.base_ready);
            const managedCount = Number(slaState.rules_status?.managed_rule_count || 0);
            if (baseReady && managedCount > 0) {
                setCalloutState(
                    'slaBaseCallout',
                    'slaBaseCalloutTitle',
                    'slaBaseCalloutText',
                    'Base SLA pronta para produção',
                    `${managedCount} regra(s) gerenciada(s) já estão associadas ao pacote base do SLA.`,
                    'ok'
                );
            } else if (baseReady) {
                setCalloutState(
                    'slaBaseCallout',
                    'slaBaseCalloutTitle',
                    'slaBaseCalloutText',
                    'SLAs base persistidos, mas regras ainda pendentes',
                    'Use “Criar/Atualizar no GLPI” para garantir que todas as RuleTicket gerenciadas sejam recriadas e sincronizadas.',
                    'attention'
                );
            } else {
                setCalloutState(
                    'slaBaseCallout',
                    'slaBaseCalloutTitle',
                    'slaBaseCalloutText',
                    'Base ainda não persistida',
                    'Entidade, calendário, SLM e os 8 SLAs precisam ser aplicados antes de liberar alerta e notificação SLA.',
                    'attention'
                );
            }
        }

        function renderSlaReminderStep() {
            const items = slaState.reminders?.items || [];
            const byKey = Object.fromEntries(items.map((item) => [item.key, item]));

            document.querySelectorAll('[data-sla-reminder-base]').forEach((cell) => {
                const key = cell.dataset.slaReminderBase;
                const reminder = byKey[key];
                if (!reminder) {
                    cell.textContent = '-';
                    return;
                }
                const settingsRow = slaState.settings?.slas?.[key];
                cell.textContent = settingsRow
                    ? `${key} = ${formatSlaDurationLabel(settingsRow.number_time, settingsRow.definition_time)}`
                    : '-';
            });

            document.querySelectorAll('[data-sla-reminder-status]').forEach((pill) => {
                const key = pill.dataset.slaReminderStatus;
                const reminder = byKey[key];
                if (!reminder) {
                    pill.className = 'sla-status-pill pending';
                    pill.textContent = 'Pendente';
                    return;
                }
                pill.className = `sla-status-pill ${escapeHtml(reminder.status || 'pending')}`;
                pill.textContent = reminder.status_label || 'Pendente';
            });

            document.querySelectorAll('[data-sla-reminder-hint]').forEach((hint) => {
                const key = hint.dataset.slaReminderHint;
                const reminder = byKey[key];
                if (!reminder) {
                    hint.textContent = '';
                    return;
                }
                if (!reminder.is_active) {
                    hint.textContent = 'Sem envio automático para esta prioridade.';
                } else if (!reminder.can_apply) {
                    hint.textContent = 'O aviso precisa ser menor que o tempo total do TTR.';
                } else if (Number(reminder.managed_id || 0) > 0) {
                    hint.textContent = `Gerenciado com aviso ${reminder.offset_label || '-'}.`;
                } else {
                    hint.textContent = `Pronto para aplicar com aviso ${reminder.offset_label || '-'}.`;
                }
            });

            const baseReady = Boolean(slaState.reminders?.base_ready);
            const applyButton = document.getElementById('applySlaReminders');
            if (applyButton) {
                applyButton.disabled = !baseReady;
            }

            if (!baseReady) {
                setCalloutState(
                    'slaReminderCallout',
                    'slaReminderCalloutTitle',
                    'slaReminderCalloutText',
                    'O alerta depende dos TTR já criados',
                    'Primeiro aplique o passo 4. Sem IDs persistidos de TTR o Dash bloqueia a gravação dos lembretes automáticos.',
                    'attention'
                );
                return;
            }

            const managedCount = items.filter((item) => Number(item.managed_id || 0) > 0 && Number(item.is_active || 0) === 1).length;
            setCalloutState(
                'slaReminderCallout',
                'slaReminderCalloutTitle',
                'slaReminderCalloutText',
                managedCount > 0 ? 'Alertas SLA gerenciados encontrados' : 'Alertas SLA prontos para aplicar',
                managedCount > 0
                    ? `${managedCount} prioridade(s) já possuem lembrete automático gerenciado no GLPI.`
                    : 'Defina quanto tempo antes cada TTR deve avisar e aplique os lembretes automáticos.',
                managedCount > 0 ? 'ok' : ''
            );
        }

        function currentSlaNotificationTargetMap() {
            return slaState.sla_notification?.recipient_catalog || {};
        }

        function syncSlaNotificationRecipientInputs() {
            const container = document.getElementById('slaNotificationRecipientsHidden');
            if (!container) return;
            container.innerHTML = slaNotificationRecipients.map((key) => (
                `<input type="hidden" name="sla_notification[recipient_keys][]" value="${escapeHtml(key)}">`
            )).join('');
        }

        function renderSlaNotificationRecipientCatalog() {
            const select = document.getElementById('slaNotificationRecipientCatalog');
            if (!select) return;
            const targetMap = currentSlaNotificationTargetMap();
            const query = searchParts(document.getElementById('slaNotificationRecipientSearch')?.value || '');
            const options = Object.entries(targetMap)
                .filter(([key, label]) => rowMatchesSearch([key, label], query))
                .map(([key, label]) => `<option value="${escapeHtml(key)}">${escapeHtml(label)}</option>`);
            select.innerHTML = options.length
                ? options.join('')
                : '<option value="" disabled>Nenhum destinatário disponível para o evento SLA.</option>';
        }

        function renderSelectedSlaNotificationRecipients() {
            const container = document.getElementById('slaNotificationSelectedRecipients');
            if (!container) return;
            const targetMap = currentSlaNotificationTargetMap();
            if (!slaNotificationRecipients.length) {
                container.innerHTML = '<span class="settings-help">Nenhum destinatário selecionado.</span>';
                syncSlaNotificationRecipientInputs();
                return;
            }

            container.innerHTML = slaNotificationRecipients.map((key) => {
                const label = targetMap[key] || key;
                return `<span class="settings-pill">${escapeHtml(label)}<button type="button" data-remove-sla-recipient="${escapeHtml(key)}" aria-label="Remover destinatário">&times;</button></span>`;
            }).join('');
            syncSlaNotificationRecipientInputs();

            document.querySelectorAll('[data-remove-sla-recipient]').forEach((button) => {
                button.addEventListener('click', () => {
                    slaNotificationRecipients = slaNotificationRecipients.filter((key) => key !== button.dataset.removeSlaRecipient);
                    renderSelectedSlaNotificationRecipients();
                    renderSlaNotificationRecipientCatalog();
                });
            });
        }

        function renderSlaNotificationStep() {
            const state = slaState.sla_notification || {};
            const notification = state.notification || {};
            const template = state.template || {};
            const event = state.event || { available: false, event_label: 'Indisponível' };
            const saveButton = document.getElementById('saveSlaNotification');
            const openButton = document.getElementById('openSlaTemplateEditor');
            const blocked = Boolean(state.blocked) || !Boolean(state.base_ready);

            const itemtypeLabel = document.getElementById('slaNotificationItemtypeLabel');
            const eventLabel = document.getElementById('slaNotificationEventLabel');
            const templateSummary = document.getElementById('slaNotificationTemplateSummary');
            const templateHint = document.getElementById('slaNotificationTemplateHint');
            if (itemtypeLabel) itemtypeLabel.textContent = state.itemtype_label || 'Chamado';
            if (eventLabel) eventLabel.textContent = event.event_label || 'Evento indisponível';
            if (templateSummary) {
                const templateName = template.name || 'Notificação por E-mail SLA';
                templateSummary.textContent = `${templateName} · ${template.status_label || 'Pendente'}`;
            }
            if (templateHint) {
                templateHint.textContent = template.content_html
                    ? 'O template já possui HTML salvo e pode ser ajustado no mesmo editor visual das notificações.'
                    : 'Abra o editor para montar o HTML compartilhado do lembrete de SLA.';
            }

            if (saveButton) saveButton.disabled = blocked;
            if (openButton) openButton.disabled = false;

            if (state.blocked) {
                setCalloutState(
                    'slaNotificationCallout',
                    'slaNotificationCalloutTitle',
                    'slaNotificationCalloutText',
                    'Evento especial indisponível no catálogo',
                    state.blocked_message || 'O GLPI não expôs o evento especial de lembrete automático de SLA.',
                    'attention'
                );
            } else if (!state.base_ready) {
                setCalloutState(
                    'slaNotificationCallout',
                    'slaNotificationCalloutTitle',
                    'slaNotificationCalloutText',
                    'A notificação só pode ser aplicada depois do passo 4',
                    'Você já pode preparar template e destinatários, mas o salvamento fica bloqueado até os SLAs base existirem no GLPI.',
                    'attention'
                );
            } else if (Number(notification.id || 0) > 0 && Number(template.id || 0) > 0) {
                setCalloutState(
                    'slaNotificationCallout',
                    'slaNotificationCalloutTitle',
                    'slaNotificationCalloutText',
                    'Notificação SLA compartilhada pronta',
                    'O itemtype fica fixo em Chamado e o evento especial de lembrete SLA já está associado ao template compartilhado.',
                    'ok'
                );
            } else {
                setCalloutState(
                    'slaNotificationCallout',
                    'slaNotificationCalloutTitle',
                    'slaNotificationCalloutText',
                    'Configure o template e os recebedores do alerta',
                    'O fluxo cria um único template compartilhado e uma única notificação compartilhada para todos os alertas de SLA.',
                    ''
                );
            }
        }

        function renderSlaAutomation() {
            const queued = slaState.automation?.queuednotification || {};
            const slaticket = slaState.automation?.slaticket || {};
            const queueText = document.getElementById('slaAutomationQueueTask');
            const slaText = document.getElementById('slaAutomationSlaTask');
            const recommendation = document.getElementById('slaAutomationRecommendation');
            const queuedSummary = !queued.available
                ? 'Ação automática não encontrada.'
                : `${queued.mode_label || '-'} · ${queued.frequency || 0}s · última execução ${formatDate(queued.lastrun)} · ${Number(queued.pending_count || 0)} pendente(s)`;
            const slaSummary = !slaticket.available
                ? 'Ação automática não encontrada.'
                : `${slaticket.mode_label || '-'} · ${slaticket.frequency || 0}s · última execução ${formatDate(slaticket.lastrun)}`;

            if (queueText) queueText.textContent = queuedSummary;
            if (slaText) slaText.textContent = slaSummary;
            if (recommendation) {
                recommendation.textContent = slaState.automation?.recommendation_ok
                    ? 'As duas ações já estão em Ativa + CLI + 60s.'
                    : 'O ideal é manter `slaticket` e `queuednotification` em Ativa + CLI + 60s.';
            }

            if (slaState.automation?.recommendation_ok && !queued.attention_active && !slaticket.attention_active) {
                setCalloutState(
                    'slaAutomationCallout',
                    'slaAutomationCalloutTitle',
                    'slaAutomationCalloutText',
                    'Ações automáticas alinhadas',
                    'O fluxo de SLA e a fila de e-mails estão na configuração recomendada e sem sinais de atraso recente.',
                    'ok'
                );
            } else {
                setCalloutState(
                    'slaAutomationCallout',
                    'slaAutomationCalloutTitle',
                    'slaAutomationCalloutText',
                    'Há atenção pendente nas automações',
                    'Se `slaticket` ou `queuednotification` estiverem fora de CLI/60s, ou sem execução recente, aplique a recomendação abaixo.',
                    'attention'
                );
            }
        }

        async function applySlaReminders() {
            setSettingsStatus('slaReminderStatus', 'Aplicando alertas...', '');
            const form = new FormData(document.getElementById('settingsForm'));
            form.set('sla_action', 'apply_reminders');

            try {
                const response = await fetch('/ajax/sla-simple.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                applySlaDataset(data);
                setSettingsStatus('slaReminderStatus', 'Alertas automáticos de SLA atualizados no GLPI.', 'ok');
            } catch (error) {
                setSettingsStatus('slaReminderStatus', error.message || 'Erro ao aplicar alertas SLA.', 'error');
            }
        }

        function readSlaNotificationDraft() {
            return {
                name: document.getElementById('slaNotificationName')?.value || 'SLA',
                entities_id: document.getElementById('slaNotificationEntity')?.value || '0',
                is_active: document.getElementById('slaNotificationActive')?.checked ? 1 : 0,
                is_recursive: document.getElementById('slaNotificationRecursive')?.checked ? 1 : 0,
                template_name: document.getElementById('slaNotificationTemplateName')?.value || 'Notificação por E-mail SLA',
                template_id: document.getElementById('slaNotificationTemplateId')?.value || '0',
                template_translation_id: document.getElementById('slaNotificationTemplateTranslationId')?.value || '0',
                template_language: document.getElementById('slaNotificationTemplateLanguage')?.value || 'pt_BR',
                template_subject: document.getElementById('slaNotificationTemplateSubject')?.value || 'Notificação de SLA',
                template_content_html: document.getElementById('slaNotificationTemplateHtml')?.value || '',
                template_content_text: document.getElementById('slaNotificationTemplateText')?.value || '',
                recipient_keys: [...slaNotificationRecipients]
            };
        }

        function openSlaNotificationTemplateEditor() {
            notificationTemplateContext = 'sla';
            setSlaStep('notification');
            const draft = readSlaNotificationDraft();
            document.getElementById('notificationTemplateModalTitle').textContent = 'Editar modelo SLA';
            document.getElementById('templateEditorId').value = draft.template_id || 0;
            document.getElementById('templateEditorTranslationId').value = draft.template_translation_id || 0;
            document.getElementById('templateEditorName').value = `${draft.template_name || 'Notificação por E-mail SLA'} (Chamado)`;
            document.getElementById('templateEditorLanguage').value = draft.template_language || 'pt_BR';
            document.getElementById('templateEditorSubject').value = draft.template_subject || 'Notificação de SLA';
            document.getElementById('templateEditorHtml').value = draft.template_content_html || '';
            document.getElementById('templateEditorText').value = draft.template_content_text || '';
            setSettingsStatus('notificationTemplateStatus', '', '');
            updateNotificationTemplatePreview();
            openNotificationTemplateModal();
            document.getElementById('templateEditorHtml').focus();
        }

        async function saveSlaNotificationFlow() {
            setSettingsStatus('slaNotificationStatus', 'Salvando notificação SLA...', '');
            const form = new FormData(document.getElementById('settingsForm'));
            form.set('sla_action', 'save_sla_notification');

            try {
                const response = await fetch('/ajax/sla-simple.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                applySlaDataset(data);
                setSettingsStatus('slaNotificationStatus', 'Notificação SLA compartilhada salva no GLPI.', 'ok');
            } catch (error) {
                setSettingsStatus('slaNotificationStatus', error.message || 'Erro ao salvar notificação SLA.', 'error');
            }
        }

        async function applySlaAutomationRecommendation() {
            setSettingsStatus('slaAutomationStatus', 'Aplicando recomendação...', '');
            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('sla_action', 'update_sla_automation');

            try {
                const response = await fetch('/ajax/sla-simple.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                applySlaDataset(data, { hydrateNotification: false });
                setSettingsStatus('slaAutomationStatus', 'Ações automáticas de SLA atualizadas para Ativa + CLI + 60s.', 'ok');
            } catch (error) {
                setSettingsStatus('slaAutomationStatus', error.message || 'Erro ao atualizar ações automáticas de SLA.', 'error');
            }
        }

        function setNotificationStep(step) {
            activeNotificationStep = step;
            document.querySelectorAll('[data-notification-step-target]').forEach((button) => {
                button.classList.toggle('active', button.dataset.notificationStepTarget === step);
            });
            document.querySelectorAll('[data-notification-step-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.notificationStepPanel !== step;
            });
        }

        function showNotificationListView() {
            setNotificationStep('notifications');
            document.getElementById('notificationListView').hidden = false;
            document.getElementById('notificationEditorView').hidden = true;
        }

        function showNotificationEditor(row = null) {
            setNotificationStep('notifications');
            document.getElementById('notificationListView').hidden = true;
            document.getElementById('notificationEditorView').hidden = false;

            if (row) {
                populateNotificationFormFromRow(row);
                return;
            }

            resetNotificationForm();
            document.getElementById('notificationName').focus();
        }

        function syncNotificationColumnFilterOptions() {
            const rows = notificationState.notifications || [];
            const setOptions = (id, stateKey, values, labeler) => {
                const element = document.getElementById(id);
                if (!element) return;
                const currentValue = notificationColumnFilters[stateKey] || '';
                const options = Array.from(new Map(values
                    .filter((value) => String(value || '').trim() !== '')
                    .map((value) => [String(value), labeler(value)]))
                    .entries())
                    .sort((a, b) => a[1].localeCompare(b[1], 'pt-BR'));
                element.innerHTML = [
                    '<option value="">Todos</option>',
                    ...options.map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`)
                ].join('');
                element.value = options.some(([value]) => value === currentValue) ? currentValue : '';
                notificationColumnFilters[stateKey] = element.value;
            };

            setOptions('notificationFilterType', 'itemtype', rows.map((row) => row.itemtype), (value) => notificationItemtypeLabel(value));
            setOptions('notificationFilterEvent', 'event', rows.map((row) => row.event), (value) => {
                const row = rows.find((item) => String(item.event) === String(value));
                return notificationEventLabel(row?.itemtype || '', value);
            });
            setOptions('notificationFilterEntity', 'entity', rows.map((row) => row.entity_name || 'Entidade raiz'), (value) => value);
            setOptions('notificationFilterTemplate', 'template', rows.map((row) => row.template_names || '-'), (value) => value);
        }

        function notificationMatchesColumnFilters(row) {
            const nameValue = String(row.name || '');
            const itemtypeValue = String(row.itemtype || '');
            const eventValue = String(row.event || '');
            const entityValue = String(row.entity_name || 'Entidade raiz');
            const templateValue = String(row.template_names || '-');
            const statusValue = String(Number(row.is_active || 0));

            if (notificationColumnFilters.name && !nameValue.toLowerCase().includes(notificationColumnFilters.name.toLowerCase())) return false;
            if (notificationColumnFilters.itemtype && itemtypeValue !== notificationColumnFilters.itemtype) return false;
            if (notificationColumnFilters.event && eventValue !== notificationColumnFilters.event) return false;
            if (notificationColumnFilters.entity && entityValue !== notificationColumnFilters.entity) return false;
            if (notificationColumnFilters.template && templateValue !== notificationColumnFilters.template) return false;
            if (notificationColumnFilters.status && statusValue !== notificationColumnFilters.status) return false;
            return true;
        }

        function notificationDefaultItemtype() {
            const entries = Object.keys(notificationState.catalog?.itemtypes || {});
            return entries[0] || 'Ticket';
        }

        function notificationDefaultEvent(itemtype) {
            const events = Object.keys(notificationState.catalog?.events_by_itemtype?.[itemtype] || {});
            return events[0] || '';
        }

        function currentNotificationTargetMap() {
            const itemtype = document.getElementById('notificationItemtype').value;
            const event = document.getElementById('notificationEvent').value;
            return notificationState.catalog?.targets_by_itemtype?.[itemtype]?.[event] || {};
        }

        function pruneNotificationRecipients() {
            const currentTargets = currentNotificationTargetMap();
            notificationEditorState.recipients = notificationEditorState.recipients.filter((key) => Object.prototype.hasOwnProperty.call(currentTargets, key));
        }

        function syncNotificationFormOptions(preferred = {}) {
            const itemtypeSelect = document.getElementById('notificationItemtype');
            const eventSelect = document.getElementById('notificationEvent');
            const entitySelect = document.getElementById('notificationEntity');
            const templateSelect = document.getElementById('notificationTemplate');

            const selectedItemtype = preferred.itemtype || itemtypeSelect.value || notificationDefaultItemtype();
            itemtypeSelect.innerHTML = optionRows(notificationState.catalog?.itemtypes || {}, selectedItemtype);
            itemtypeSelect.value = selectedItemtype;

            const events = notificationState.catalog?.events_by_itemtype?.[selectedItemtype] || {};
            const selectedEvent = Object.prototype.hasOwnProperty.call(events, preferred.event || eventSelect.value)
                ? (preferred.event || eventSelect.value)
                : notificationDefaultEvent(selectedItemtype);
            eventSelect.innerHTML = optionRows(events, selectedEvent);
            eventSelect.value = selectedEvent;

            const selectedEntity = String(preferred.entities_id ?? entitySelect.value ?? '0');
            entitySelect.innerHTML = [
                '<option value="0">Entidade raiz</option>',
                ...(notificationState.entities || []).map((entity) => (
                    `<option value="${Number(entity.id) || 0}">${escapeHtml(entity.completename || entity.name)}</option>`
                ))
            ].join('');
            entitySelect.value = selectedEntity;

            const filteredTemplates = (notificationState.templates || []).filter((template) => String(template.itemtype || '') === String(selectedItemtype));
            const selectedTemplate = String(preferred.template_id ?? templateSelect.value ?? '0');
            templateSelect.innerHTML = [
                '<option value="0">Sem modelo</option>',
                ...filteredTemplates.map((template) => (
                    `<option value="${Number(template.id) || 0}">${escapeHtml(template.name)} (${escapeHtml(notificationItemtypeLabel(template.itemtype || ''))})</option>`
                ))
            ].join('');
            templateSelect.value = selectedTemplate;

            if (!Array.from(templateSelect.options).some((option) => option.value === templateSelect.value)) {
                templateSelect.value = '0';
            }

            pruneNotificationRecipients();
            renderNotificationRecipientCatalog();
            renderSelectedNotificationRecipients();
        }

        function resetNotificationForm(keepStatus = false) {
            document.getElementById('notificationId').value = '0';
            document.getElementById('notificationName').value = '';
            document.getElementById('notificationActive').checked = true;
            document.getElementById('notificationRecursive').checked = true;
            notificationEditorState.recipients = [];
            syncNotificationFormOptions({ itemtype: notificationDefaultItemtype(), event: notificationDefaultEvent(notificationDefaultItemtype()), entities_id: '0', template_id: '0' });
            document.getElementById('saveNotificationLabel').textContent = 'Salvar notificação';
            if (!keepStatus) {
                setSettingsStatus('notificationStatus', '', '');
            }
        }

        function populateNotificationFormFromRow(row) {
            if (!row) return;
            document.getElementById('notificationId').value = String(Number(row.id) || 0);
            document.getElementById('notificationName').value = row.name || '';
            document.getElementById('notificationActive').checked = Number(row.is_active || 0) === 1;
            document.getElementById('notificationRecursive').checked = Number(row.is_recursive || 0) === 1;
            notificationEditorState.recipients = Array.isArray(row.recipient_keys) ? [...row.recipient_keys] : [];
            syncNotificationFormOptions({
                itemtype: row.itemtype || notificationDefaultItemtype(),
                event: row.event || notificationDefaultEvent(row.itemtype || notificationDefaultItemtype()),
                entities_id: String(Number(row.entities_id) || 0),
                template_id: String(Number(row.template_id) || 0)
            });
            document.getElementById('saveNotificationLabel').textContent = `Salvar edição #${Number(row.id) || 0}`;
            setSettingsStatus(
                'notificationStatus',
                row.has_exclusions
                    ? 'Esta notificação possui destinatários de exclusão preservados no GLPI. Ajuste essas exceções na tela nativa se precisar alterá-las.'
                    : '',
                ''
            );
            document.getElementById('notificationName').focus();
        }

        function renderNotificationRecipientCatalog() {
            const searchValue = document.getElementById('recipientCatalogSearch')?.value || '';
            const searchTerms = searchParts(searchValue);
            const selectedKeys = new Set(notificationEditorState.recipients);
            const options = Object.entries(currentNotificationTargetMap())
                .filter(([key, label]) => !selectedKeys.has(key) && rowMatchesSearch([key, label], searchTerms))
                .map(([key, label]) => `<option value="${escapeHtml(key)}">${escapeHtml(label)}</option>`);

            document.getElementById('notificationRecipientCatalog').innerHTML = options.length
                ? options.join('')
                : '<option value="" disabled>Nenhum destinatário disponível para o gatilho atual.</option>';
        }

        function renderSelectedNotificationRecipients() {
            const targetMap = currentNotificationTargetMap();
            const container = document.getElementById('notificationSelectedRecipients');
            if (!notificationEditorState.recipients.length) {
                container.innerHTML = '<span class="settings-help">Nenhum destinatário selecionado.</span>';
                return;
            }

            container.innerHTML = notificationEditorState.recipients.map((key) => {
                const label = targetMap[key] || key;
                return `<span class="settings-pill">${escapeHtml(label)}<button type="button" data-remove-recipient="${escapeHtml(key)}" aria-label="Remover destinatário">&times;</button></span>`;
            }).join('');

            document.querySelectorAll('[data-remove-recipient]').forEach((button) => {
                button.addEventListener('click', () => {
                    notificationEditorState.recipients = notificationEditorState.recipients.filter((key) => key !== button.dataset.removeRecipient);
                    renderNotificationRecipientCatalog();
                    renderSelectedNotificationRecipients();
                });
            });
        }

        function renderNotificationTables() {
            const notificationsTable = document.getElementById('notificationsTable');
            const templatesTable = document.getElementById('notificationTemplatesTable');
            if (!notificationsTable || !templatesTable) {
                return;
            }

            const notifications = notificationState.notifications || [];
            const filteredNotifications = notifications.filter((row) => notificationMatchesColumnFilters(row));
            notificationsTable.innerHTML = filteredNotifications.length
                ? filteredNotifications.map((row) => `
                    <tr>
                        <td><input type="checkbox" data-notification-row-id="${Number(row.id) || 0}" ${notificationSelection.has(Number(row.id) || 0) ? 'checked' : ''}></td>
                        <td><strong>${escapeHtml(row.name || '-')}</strong></td>
                        <td>${escapeHtml(notificationItemtypeLabel(row.itemtype))}</td>
                        <td>${escapeHtml(notificationEventLabel(row.itemtype, row.event))}</td>
                        <td>${escapeHtml(row.entity_name || 'Entidade raiz')}</td>
                        <td>${renderNotificationTemplateCell(row)}</td>
                        <td>${escapeHtml(formatBool(row.is_active))}</td>
                        <td>
                            <div class="settings-row-actions">
                                <button
                                    class="settings-action-icon"
                                    type="button"
                                    data-notification-info="${Number(row.id) || 0}"
                                    title="Ver detalhes da notificação"
                                    aria-label="Ver detalhes da notificação">
                                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                                </button>
                                <button
                                    class="settings-action-icon"
                                    type="button"
                                    data-notification-edit="${Number(row.id) || 0}"
                                    title="Editar notificação"
                                    aria-label="Editar notificação">
                                    <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                                </button>
                                <button
                                    class="settings-action-icon ${notificationHasIndividualTemplate(row) ? 'disabled' : ''}"
                                    type="button"
                                    data-notification-create-template="${Number(row.id) || 0}"
                                    ${notificationHasIndividualTemplate(row) ? 'disabled' : ''}
                                    title="${notificationHasIndividualTemplate(row) ? 'Modelo individual configurado' : 'Criar modelo individual'}"
                                    aria-label="${notificationHasIndividualTemplate(row) ? 'Modelo individual configurado' : `Criar modelo individual para ${escapeHtml(notificationExpectedIndividualTemplateName(row) || row.name || '-')}`}">
                                    <i class="fas fa-file-circle-plus" aria-hidden="true"></i>
                                </button>
                                <button
                                    class="settings-action-icon ${Number(row.template_id) > 0 ? '' : 'disabled'}"
                                    type="button"
                                    data-notification-template="${Number(row.template_id) || 0}"
                                    ${Number(row.template_id) > 0 ? '' : 'disabled'}
                                    title="${Number(row.template_id) > 0 ? 'Abrir modelo vinculado' : 'Sem modelo vinculado'}"
                                    aria-label="${Number(row.template_id) > 0 ? 'Abrir modelo vinculado' : 'Sem modelo vinculado'}">
                                    <i class="fas fa-file-lines" aria-hidden="true"></i>
                                </button>
                                <button
                                    class="settings-action-icon"
                                    type="button"
                                    data-notification-clone="${Number(row.id) || 0}"
                                    title="Clonar notificacao"
                                    aria-label="Clonar notificacao">
                                    <i class="fas fa-clone" aria-hidden="true"></i>
                                </button>
                                <button
                                    class="settings-action-icon ${Number(row.is_active) ? 'toggle-on' : 'toggle-off'}"
                                    type="button"
                                    data-notification-toggle="${Number(row.id) || 0}"
                                    data-active="${Number(row.is_active) ? 0 : 1}"
                                    title="${Number(row.is_active) ? 'Desativar notificação' : 'Ativar notificação'}"
                                    aria-label="${Number(row.is_active) ? 'Desativar notificação' : 'Ativar notificação'}">
                                    <i class="fas ${Number(row.is_active) ? 'fa-toggle-on' : 'fa-toggle-off'}" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('')
                : `<tr><td colspan="8">${notifications.length ? 'Nenhuma notificação corresponde aos filtros atuais.' : 'Nenhuma notificação encontrada.'}</td></tr>`;

            document.querySelectorAll('[data-notification-info]').forEach((button) => {
                button.addEventListener('click', () => openNotificationInfoModal(button.dataset.notificationInfo));
            });
            document.querySelectorAll('[data-notification-create-template]').forEach((button) => {
                button.addEventListener('click', () => createIndividualNotificationTemplate(button.dataset.notificationCreateTemplate));
            });
            document.querySelectorAll('[data-notification-toggle]').forEach((button) => {
                button.addEventListener('click', () => toggleNotification(button.dataset.notificationToggle, button.dataset.active));
            });
            document.querySelectorAll('[data-notification-clone]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = notificationState.notifications.find((item) => Number(item.id) === Number(button.dataset.notificationClone));
                    const label = row?.name ? `"${row.name}"` : `#${Number(button.dataset.notificationClone) || 0}`;
                    openNotificationConfirmModal(
                        `Clonar a notificação ${label}? A cópia será criada como inativa para revisão.`,
                        async () => cloneNotification(button.dataset.notificationClone),
                        {
                            title: 'Confirmar clonagem',
                            confirmLabel: 'Clonar',
                            confirmIcon: 'fa-clone',
                            confirmTone: 'primary',
                            cancelAriaLabel: 'Cancelar clonagem'
                        }
                    );
                });
            });
            document.querySelectorAll('[data-notification-template]').forEach((button) => {
                button.addEventListener('click', () => {
                    const templateId = Number(button.dataset.notificationTemplate) || 0;
                    if (templateId > 0) {
                        openNotificationTemplateEditor(templateId);
                    }
                });
            });
            document.querySelectorAll('[data-notification-edit]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = notificationState.notifications.find((item) => Number(item.id) === Number(button.dataset.notificationEdit));
                    if (row) {
                        showNotificationEditor(row);
                    }
                });
            });
            document.querySelectorAll('[data-notification-row-id]').forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const id = Number(checkbox.dataset.notificationRowId) || 0;
                    if (checkbox.checked) {
                        notificationSelection.add(id);
                    } else {
                        notificationSelection.delete(id);
                    }
                    renderNotificationTables();
                });
            });

            const visibleIds = filteredNotifications.map((row) => Number(row.id) || 0).filter(Boolean);
            const selectedVisible = visibleIds.filter((id) => notificationSelection.has(id));
            const selectAll = document.getElementById('notificationSelectAll');
            if (selectAll) {
                selectAll.indeterminate = selectedVisible.length > 0 && selectedVisible.length < visibleIds.length;
                selectAll.checked = visibleIds.length > 0 && selectedVisible.length === visibleIds.length;
            }

            const templates = notificationState.templates || [];
            const templateParts = searchParts(settingsFilters.templates);
            const filteredTemplates = templates.filter((row) => rowMatchesSearch(templateSearchRow(row), templateParts));
            templatesTable.innerHTML = filteredTemplates.length
                ? filteredTemplates.map((row) => `
                    <tr>
                        <td><strong>#${Number(row.id) || 0}</strong></td>
                        <td><strong>${escapeHtml(row.name || '-')}</strong></td>
                        <td>${escapeHtml(notificationItemtypeLabel(row.itemtype))}</td>
                        <td>${escapeHtml(row.language || 'pt_BR')}</td>
                        <td>${escapeHtml(row.subject || '-')}</td>
                        <td><button class="settings-action-small" type="button" data-template-edit="${Number(row.id) || 0}">Editar</button></td>
                    </tr>
                `).join('')
                : `<tr><td colspan="6">${templates.length ? 'Nenhum modelo corresponde ao filtro.' : 'Nenhum modelo encontrado.'}</td></tr>`;

            document.querySelectorAll('[data-template-edit]').forEach((button) => {
                button.addEventListener('click', () => openNotificationTemplateEditor(button.dataset.templateEdit));
            });
        }

        function renderCleanupTable() {
            const cleanupTable = document.getElementById('notificationCleanupTable');
            const selectAll = document.getElementById('cleanupSelectAll');
            if (!cleanupTable || !selectAll) {
                return;
            }

            const cleanupRows = (notificationState.notifications || []).filter((row) => String(row.itemtype || '') === 'Ticket' && Number(row.is_active || 0) === 1);
            const cleanupTerms = searchParts(settingsFilters.cleanup);
            const visibleRows = cleanupRows.filter((row) => rowMatchesSearch(notificationSearchRow(row), cleanupTerms));
            cleanupTable.innerHTML = visibleRows.length
                ? visibleRows.map((row) => `
                    <tr>
                        <td><input type="checkbox" data-cleanup-id="${Number(row.id) || 0}" ${cleanupSelection.has(Number(row.id) || 0) ? 'checked' : ''}></td>
                        <td><strong>#${Number(row.id) || 0}</strong></td>
                        <td><strong>${escapeHtml(row.name || '-')}</strong></td>
                        <td>${escapeHtml(notificationEventLabel(row.itemtype, row.event))}</td>
                        <td>${escapeHtml(row.template_names || '-')}</td>
                        <td>${escapeHtml(row.recipient_summary || '-')}</td>
                    </tr>
                `).join('')
                : `<tr><td colspan="6">${cleanupRows.length ? 'Nenhuma notificação ativa de chamado corresponde ao filtro.' : 'Nenhuma notificação ativa de chamado encontrada.'}</td></tr>`;

            const visibleIds = visibleRows.map((row) => Number(row.id) || 0).filter(Boolean);
            const selectedVisible = visibleIds.filter((id) => cleanupSelection.has(id));
            selectAll.indeterminate = selectedVisible.length > 0 && selectedVisible.length < visibleIds.length;
            selectAll.checked = visibleIds.length > 0 && selectedVisible.length === visibleIds.length;

            document.querySelectorAll('[data-cleanup-id]').forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const id = Number(checkbox.dataset.cleanupId) || 0;
                    if (checkbox.checked) {
                        cleanupSelection.add(id);
                    } else {
                        cleanupSelection.delete(id);
                    }
                    renderCleanupTable();
                });
            });
        }

        function renderDeliveryStatus() {
            const modeValue = document.getElementById('notificationQueueModeValue');
            const frequencyValue = document.getElementById('notificationQueueFrequencyValue');
            const pendingValue = document.getElementById('notificationQueuePendingValue');
            const lastRunValue = document.getElementById('notificationQueueLastRunValue');
            const title = document.getElementById('notificationQueueCalloutTitle');
            const text = document.getElementById('notificationQueueCalloutText');
            const callout = document.getElementById('notificationQueueCallout');
            if (!modeValue || !frequencyValue || !pendingValue || !lastRunValue || !title || !text || !callout) {
                return;
            }

            const delivery = notificationState.delivery || {};
            modeValue.textContent = delivery.available ? (delivery.mode_label || '-') : 'N/D';
            frequencyValue.textContent = delivery.available ? `${Number(delivery.frequency || 0)}s` : '-';
            pendingValue.textContent = String(Number(delivery.pending_count || 0));
            lastRunValue.textContent = delivery.available && delivery.lastrun ? formatDate(delivery.lastrun) : 'Nunca';

            callout.classList.remove('attention', 'ok');

            if (!delivery.available) {
                title.textContent = 'Ação automática queuednotification não encontrada';
                text.textContent = 'Revise o GLPI antes de automatizar a fila de notificações.';
                return;
            }

            if (delivery.attention_active) {
                callout.classList.add('attention');
                title.textContent = 'Fila atrasada';
                text.textContent = `Há ${Number(delivery.pending_count || 0)} envio(s) pendente(s) com atraso de ${formatDurationSeconds(delivery.delay_seconds || 0)}.`;
                return;
            }

            if (!delivery.recommendation_ok) {
                title.textContent = 'Ajuste recomendado pendente';
                text.textContent = `Atual: ${delivery.state_label || '-'} / ${delivery.mode_label || '-'} / frequência ${Number(delivery.frequency || 0)}s. Recomenda-se Ativa + CLI + 60s.`;
                return;
            }

            callout.classList.add('ok');
            title.textContent = 'Fila pronta para disparo';
            text.textContent = Number(delivery.pending_count || 0) > 0
                ? `Existem ${Number(delivery.pending_count || 0)} envio(s) aguardando processamento, sem atraso fora da janela normal.`
                : 'Nenhuma pendência relevante na fila de notificações.';
        }

        function notificationMailModeLabel(mode) {
            return ({
                '0': 'PHP',
                '1': 'SMTP',
                '4': 'SMTP+OAUTH'
            })[String(mode || '0')] || 'Desconhecido';
        }

        function stableNotificationMailObject(value) {
            const source = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
            return Object.keys(source)
                .sort((left, right) => left.localeCompare(right))
                .reduce((accumulator, key) => {
                    accumulator[key] = String(source[key] ?? '');
                    return accumulator;
                }, {});
        }

        function normalizeNotificationMailIdentity(value) {
            return String(value || '').trim().toLowerCase();
        }

        function notificationMailRequiresSenderLoginMatch(state = readNotificationMailComparableState()) {
            const mode = String(state.smtp_mode || '0');
            const smtpUsername = normalizeNotificationMailIdentity(state.smtp_username);
            return mode === '1' && smtpUsername.includes('@');
        }

        function notificationMailSenderLoginRuleMessage(state = readNotificationMailComparableState()) {
            if (!notificationMailRequiresSenderLoginMatch(state)) {
                return '';
            }

            const smtpUsername = normalizeNotificationMailIdentity(state.smtp_username);
            const fromEmail = normalizeNotificationMailIdentity(state.from_email);
            if (fromEmail === smtpUsername) {
                return '';
            }

            if (fromEmail === '') {
                return 'Para este provedor SMTP, preencha "E-mail do remetente" com o mesmo endereco do "Login SMTP".';
            }

            return 'Para este provedor SMTP, "E-mail do remetente" deve ser igual ao "Login SMTP".';
        }

        function applyNotificationMailSenderFromLoginIfEmpty() {
            const fromField = document.getElementById('mailFromEmail');
            const loginField = document.getElementById('mailSmtpUsername');
            const modeField = document.getElementById('mailSmtpMode');
            if (!fromField || !loginField || !modeField) {
                return false;
            }

            if (String(modeField.value || '0') !== '1') {
                return false;
            }

            const loginValue = loginField.value.trim();
            if (!loginValue.includes('@') || fromField.value.trim() !== '') {
                return false;
            }

            fromField.value = loginValue;
            return true;
        }

        function renderNotificationMailSenderLoginRule() {
            const fromField = document.getElementById('mailFromEmail');
            const loginField = document.getElementById('mailSmtpUsername');
            const status = document.getElementById('mailSenderLoginRuleStatus');
            if (!fromField || !loginField || !status) {
                return '';
            }

            const state = readNotificationMailComparableState();
            const ruleMessage = notificationMailSenderLoginRuleMessage(state);
            fromField.classList.toggle('mail-rule-error', ruleMessage !== '');
            loginField.classList.toggle('mail-rule-error', ruleMessage !== '');

            if (!notificationMailRequiresSenderLoginMatch(state)) {
                status.className = 'settings-help';
                status.textContent = 'Quando o login SMTP for um e-mail, use o mesmo valor em E-mail do remetente para evitar falhas de autenticacao e envio.';
                return '';
            }

            if (ruleMessage !== '') {
                status.className = 'settings-help attention';
                status.textContent = `${ruleMessage} Se o remetente estiver vazio, o Dash copia o login SMTP ao salvar.`;
                return ruleMessage;
            }

            status.className = 'settings-help ok';
            status.textContent = 'E-mail do remetente e Login SMTP estao alinhados para este provedor.';
            return '';
        }

        function validateNotificationMailSenderLoginRule(options = {}) {
            const { autoFillSender = false } = options;
            if (autoFillSender && applyNotificationMailSenderFromLoginIfEmpty()) {
                updateNotificationMailDirtyState();
            }

            return renderNotificationMailSenderLoginRule();
        }

        function notificationMailComparableStateFromSettings(settings = {}) {
            return {
                admin_email: settings.admin_email || '',
                admin_email_name: settings.admin_email_name || '',
                from_email: settings.from_email || '',
                from_email_name: settings.from_email_name || '',
                replyto_email: settings.replyto_email || '',
                replyto_email_name: settings.replyto_email_name || '',
                noreply_email: settings.noreply_email || '',
                noreply_email_name: settings.noreply_email_name || '',
                mailing_signature: settings.mailing_signature || '',
                smtp_mode: String(settings.smtp_mode || '0'),
                smtp_retry_time: String(settings.smtp_retry_time || '5'),
                smtp_max_retries: String(settings.smtp_max_retries || '5'),
                smtp_check_certificate: Number(settings.smtp_check_certificate || 0) === 1 ? '1' : '0',
                smtp_host: settings.smtp_host || '',
                smtp_port: String(settings.smtp_port || ''),
                smtp_username: settings.smtp_username || '',
                smtp_sender: settings.smtp_sender || '',
                smtp_oauth_provider: settings.smtp_oauth_provider || '',
                smtp_oauth_client_id: settings.smtp_oauth_client_id || '',
                smtp_oauth_options_json: JSON.stringify(stableNotificationMailObject(settings.smtp_oauth_options || {})),
                attach_ticket_documents_to_mail: ['0', '1', '2'].includes(String(settings.attach_ticket_documents_to_mail || '0'))
                    ? String(settings.attach_ticket_documents_to_mail || '0')
                    : '0',
                attach_documents_to_notifications_for_anonymous: Number(settings.attach_documents_to_notifications_for_anonymous || 0) === 1 ? '1' : '0',
                smtp_password_typed: false,
                smtp_clear_password: false,
                smtp_oauth_client_secret_typed: false
            };
        }

        function readNotificationMailComparableState() {
            const mailAdminEmail = document.getElementById('mailAdminEmail');
            if (!mailAdminEmail) {
                return notificationMailPersistedState;
            }

            return {
                admin_email: mailAdminEmail.value || '',
                admin_email_name: document.getElementById('mailAdminName').value || '',
                from_email: document.getElementById('mailFromEmail').value || '',
                from_email_name: document.getElementById('mailFromName').value || '',
                replyto_email: document.getElementById('mailReplyEmail').value || '',
                replyto_email_name: document.getElementById('mailReplyName').value || '',
                noreply_email: document.getElementById('mailNoReplyEmail').value || '',
                noreply_email_name: document.getElementById('mailNoReplyName').value || '',
                mailing_signature: document.getElementById('mailSignature').value || '',
                smtp_mode: document.getElementById('mailSmtpMode').value || '0',
                smtp_retry_time: document.getElementById('mailRetryTime').value || '5',
                smtp_max_retries: document.getElementById('mailMaxRetries').value || '5',
                smtp_check_certificate: document.getElementById('mailSmtpCheckCertificate').value || '0',
                smtp_host: document.getElementById('mailSmtpHost').value || '',
                smtp_port: document.getElementById('mailSmtpPort').value || '',
                smtp_username: document.getElementById('mailSmtpUsername').value || '',
                smtp_sender: document.getElementById('mailSmtpSender').value || '',
                smtp_oauth_provider: document.getElementById('mailSmtpOauthProvider').value || '',
                smtp_oauth_client_id: document.getElementById('mailSmtpOauthClientId').value || '',
                smtp_oauth_options_json: JSON.stringify(stableNotificationMailObject(collectOauthAdditionalOptions())),
                attach_ticket_documents_to_mail: document.getElementById('mailAttachTicketDocuments').value || '0',
                attach_documents_to_notifications_for_anonymous: document.getElementById('mailAttachAnonymousDocuments').value || '0',
                smtp_password_typed: document.getElementById('mailSmtpPassword').value.trim() !== '',
                smtp_clear_password: document.getElementById('mailClearSmtpPassword').checked,
                smtp_oauth_client_secret_typed: document.getElementById('mailSmtpOauthClientSecret').value.trim() !== ''
            };
        }

        function notificationMailPersistedSummary(settings = notificationState.mail_settings || {}) {
            const modeLabel = notificationMailModeLabel(settings.smtp_mode);
            if (String(settings.smtp_mode || '0') === '0') {
                return `Snapshot salvo no GLPI: modo ${modeLabel}, administrador ${settings.admin_email || '-'}, remetente ${settings.from_email || settings.smtp_sender || '-'}.`;
            }

            return `Snapshot salvo no GLPI: modo ${modeLabel}, host ${settings.smtp_host || '-'}, porta ${settings.smtp_port || '-'}, login ${settings.smtp_username || '-'}.`;
        }

        function renderNotificationMailPersistedState() {
            const callout = document.getElementById('mailPersistedStateCallout');
            const title = document.getElementById('mailPersistedStateTitle');
            const summary = document.getElementById('mailPersistedStateSummary');
            if (!callout || !title || !summary) {
                return;
            }

            const senderLoginIssue = notificationMailSenderLoginRuleMessage();
            const hasAttention = notificationMailDirty || senderLoginIssue !== '';
            callout.className = 'settings-callout' + (hasAttention ? ' attention' : ' ok');
            if (senderLoginIssue !== '') {
                title.textContent = notificationMailDirty
                    ? 'A configuracao precisa de ajuste antes de salvar.'
                    : 'A configuracao salva precisa de ajuste antes do teste.';
                summary.textContent = `${senderLoginIssue} ${notificationMailPersistedSummary()}`;
                return;
            }

            title.textContent = notificationMailDirty
                ? 'Há alterações não salvas na tela.'
                : 'O teste usa a configuração já salva no GLPI.';
            summary.textContent = notificationMailDirty
                ? `Salve ou recarregue antes de testar. ${notificationMailPersistedSummary()}`
                : notificationMailPersistedSummary();
        }

        function updateNotificationMailDirtyState() {
            if (!document.getElementById('mailAdminEmail')) {
                return;
            }

            notificationMailDirty = JSON.stringify(readNotificationMailComparableState()) !== JSON.stringify(notificationMailPersistedState);
            renderNotificationMailSenderLoginRule();
            renderNotificationMailPersistedState();
        }

        function bindNotificationMailDirtyListeners() {
            [
                'mailAdminEmail',
                'mailAdminName',
                'mailFromEmail',
                'mailFromName',
                'mailReplyEmail',
                'mailReplyName',
                'mailNoReplyEmail',
                'mailNoReplyName',
                'mailSignature',
                'mailSmtpMode',
                'mailRetryTime',
                'mailMaxRetries',
                'mailAttachTicketDocuments',
                'mailAttachAnonymousDocuments',
                'mailSmtpCheckCertificate',
                'mailSmtpHost',
                'mailSmtpPort',
                'mailSmtpUsername',
                'mailSmtpPassword',
                'mailClearSmtpPassword',
                'mailSmtpSender',
                'mailSmtpOauthProvider',
                'mailSmtpOauthClientId',
                'mailSmtpOauthClientSecret'
            ].forEach((id) => {
                const element = document.getElementById(id);
                if (!element) {
                    return;
                }
                element.addEventListener('input', updateNotificationMailDirtyState);
                element.addEventListener('change', updateNotificationMailDirtyState);
            });

            const oauthAdditionalFields = document.getElementById('mailOauthAdditionalFields');
            if (oauthAdditionalFields) {
                oauthAdditionalFields.addEventListener('input', updateNotificationMailDirtyState);
                oauthAdditionalFields.addEventListener('change', updateNotificationMailDirtyState);
            }
        }

        function populateNotificationMailForm() {
            const mailAdminEmail = document.getElementById('mailAdminEmail');
            const passwordHint = document.getElementById('mailSmtpPasswordHint');
            const oauthProvider = document.getElementById('mailSmtpOauthProvider');
            const oauthClientId = document.getElementById('mailSmtpOauthClientId');
            const oauthClientSecretHint = document.getElementById('mailSmtpOauthClientSecretHint');
            const oauthRefreshHint = document.getElementById('mailOauthRefreshHint');
            const oauthCallbackUrl = document.getElementById('mailSmtpOauthCallbackUrl');
            if (!mailAdminEmail) {
                return;
            }

            const settings = notificationState.mail_settings || {};
            mailAdminEmail.value = settings.admin_email || '';
            document.getElementById('mailAdminName').value = settings.admin_email_name || '';
            document.getElementById('mailFromEmail').value = settings.from_email || '';
            document.getElementById('mailFromName').value = settings.from_email_name || '';
            document.getElementById('mailReplyEmail').value = settings.replyto_email || '';
            document.getElementById('mailReplyName').value = settings.replyto_email_name || '';
            document.getElementById('mailNoReplyEmail').value = settings.noreply_email || '';
            document.getElementById('mailNoReplyName').value = settings.noreply_email_name || '';
            document.getElementById('mailSignature').value = settings.mailing_signature || '';
            document.getElementById('mailSmtpMode').value = String(settings.smtp_mode || '0');
            document.getElementById('mailRetryTime').value = settings.smtp_retry_time || '5';
            document.getElementById('mailMaxRetries').value = settings.smtp_max_retries || '5';
            document.getElementById('mailAttachTicketDocuments').value = ['0', '1', '2'].includes(String(settings.attach_ticket_documents_to_mail || '0'))
                ? String(settings.attach_ticket_documents_to_mail || '0')
                : '0';
            document.getElementById('mailAttachAnonymousDocuments').value = Number(settings.attach_documents_to_notifications_for_anonymous || 0) === 1 ? '1' : '0';
            document.getElementById('mailSmtpCheckCertificate').value = Number(settings.smtp_check_certificate || 0) === 1 ? '1' : '0';
            document.getElementById('mailSmtpHost').value = settings.smtp_host || '';
            document.getElementById('mailSmtpPort').value = settings.smtp_port || '';
            document.getElementById('mailSmtpUsername').value = settings.smtp_username || '';
            document.getElementById('mailSmtpPassword').value = '';
            document.getElementById('mailSmtpSender').value = settings.smtp_sender || '';
            document.getElementById('mailClearSmtpPassword').checked = false;
            if (passwordHint) {
                passwordHint.textContent = settings.smtp_password_configured
                    ? 'Senha SMTP já configurada no GLPI.'
                    : 'Nenhuma senha SMTP configurada no GLPI.';
            }

            const providers = notificationState.catalog?.oauth?.providers || [];
            if (oauthProvider) {
                oauthProvider.innerHTML = [
                    '<option value="">Selecione o provedor OAuth</option>',
                    ...providers.map((provider) => `<option value="${escapeHtml(provider.value)}">${escapeHtml(provider.label)}</option>`)
                ].join('');
                oauthProvider.value = settings.smtp_oauth_provider || '';
            }
            if (oauthClientId) {
                oauthClientId.value = settings.smtp_oauth_client_id || '';
            }
            document.getElementById('mailSmtpOauthClientSecret').value = '';
            if (oauthClientSecretHint) {
                oauthClientSecretHint.textContent = settings.smtp_oauth_client_secret_configured
                    ? 'Client secret OAuth já configurado no GLPI.'
                    : 'Client secret OAuth ainda não configurado.';
            }
            if (oauthRefreshHint) {
                oauthRefreshHint.textContent = settings.smtp_oauth_refresh_token_configured
                    ? 'Token OAuth já autorizado no GLPI.'
                    : 'Se o provedor exigir autorização, finalize a autenticação na tela nativa do GLPI após salvar.';
            }
            if (oauthCallbackUrl) {
                oauthCallbackUrl.value = settings.smtp_oauth_callback_url || '';
            }

            notificationMailPersistedState = notificationMailComparableStateFromSettings(settings);
            renderOauthAdditionalFields();
            syncMailPanels();
            updateNotificationMailDirtyState();
        }

        function renderOauthAdditionalFields() {
            const container = document.getElementById('mailOauthAdditionalFields');
            const providerField = document.getElementById('mailSmtpOauthProvider');
            if (!container || !providerField) {
                return;
            }

            const provider = providerField.value;
            const specs = notificationState.catalog?.oauth?.additional_parameters?.[provider] || [];
            const values = notificationState.mail_settings?.smtp_oauth_options || {};

            if (!specs.length) {
                container.innerHTML = '<div class="settings-help">Nenhum parâmetro adicional foi publicado para este provedor.</div>';
                return;
            }

            container.innerHTML = specs.map((spec) => `
                <div class="settings-inline-stack">
                    <input class="settings-input" type="text" data-oauth-option="${escapeHtml(spec.key)}" placeholder="${escapeHtml(spec.label || spec.key)}" value="${escapeHtml(values[spec.key] ?? spec.default ?? '')}">
                    ${spec.helper ? `<div class="settings-help">${escapeHtml(spec.helper)}</div>` : ''}
                </div>
            `).join('');
        }

        function syncMailPanels() {
            const modeField = document.getElementById('mailSmtpMode');
            const configurationAccordion = document.getElementById('mailConfigurationAccordion');
            const smtpAccordion = document.getElementById('mailSmtpAccordion');
            const credentialsFields = document.getElementById('mailSmtpCredentialsFields');
            const oauthFields = document.getElementById('mailOauthFields');
            if (!modeField || !configurationAccordion || !smtpAccordion || !credentialsFields || !oauthFields) {
                return;
            }

            const mode = modeField.value;
            const isPhp = mode === '0';
            const isOauth = mode === '4';
            configurationAccordion.open = true;
            smtpAccordion.open = !isPhp;
            credentialsFields.hidden = isPhp || isOauth;
            oauthFields.hidden = !isOauth;
        }

        function openNotificationTemplateEditor(id) {
            notificationTemplateContext = 'notifications';
            const template = (notificationState.templates || []).find((row) => Number(row.id) === Number(id));
            if (!template) return;

            document.getElementById('notificationTemplateModalTitle').textContent = 'Editar modelo';
            document.getElementById('templateEditorId').value = Number(template.id) || 0;
            document.getElementById('templateEditorTranslationId').value = Number(template.translation_id) || 0;
            document.getElementById('templateEditorRawName').value = template.name || '';
            document.getElementById('templateEditorItemtype').value = template.itemtype || '';
            document.getElementById('templateEditorName').value = `${template.name || '-'} (${notificationItemtypeLabel(template.itemtype || '') || '-'})`;
            document.getElementById('templateEditorLanguage').value = template.language || 'pt_BR';
            document.getElementById('templateEditorSubject').value = template.subject || '';
            document.getElementById('templateEditorHtml').value = template.content_html || '';
            document.getElementById('templateEditorText').value = template.content_text || '';
            setSettingsStatus('notificationTemplateStatus', '', '');
            updateNotificationTemplatePreview();
            openNotificationTemplateModal();
            document.getElementById('templateEditorHtml').focus();
        }

        function openNotificationTemplateModal() {
            const modal = document.getElementById('notificationTemplateModal');
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeNotificationTemplateModal() {
            const modal = document.getElementById('notificationTemplateModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            resetNotificationTemplatePreview();
        }

        function notificationInfoField(label, value, wide = false) {
            return `
                <div class="settings-info-field${wide ? ' wide' : ''}">
                    <div class="settings-info-label">${escapeHtml(label || '-')}</div>
                    <div class="settings-info-value">${escapeHtml(value || '-')}</div>
                </div>
            `;
        }

        function notificationCurrentMailTemplateName(row) {
            const templateId = Number(row?.template_id) || 0;
            if (templateId > 0) {
                const template = (notificationState.templates || []).find((item) => Number(item.id) === templateId);
                const templateName = String(template?.name || '').trim();
                if (templateName) {
                    return templateName;
                }
            }

            return String(row?.template_names || '').trim() || '-';
        }

        function notificationExpectedIndividualTemplateName(row) {
            return String(row?.name || '').trim();
        }

        function notificationHasIndividualTemplate(row) {
            const expectedTemplate = notificationExpectedIndividualTemplateName(row);
            if (!expectedTemplate || Number(row?.template_id) <= 0) {
                return false;
            }

            const currentTemplate = notificationCurrentMailTemplateName(row);
            return currentTemplate.localeCompare(expectedTemplate, 'pt-BR', { sensitivity: 'accent' }) === 0;
        }

        function notificationIsDefaultInitial(row) {
            const id = Number(row?.id) || 0;
            return Object.prototype.hasOwnProperty.call(defaultInitialNotifications, id);
        }

        function notificationIsUpdateTicket(row) {
            return String(row?.itemtype || '') === 'Ticket' && String(row?.event || '') === 'update';
        }

        function notificationRecommendedUpdateRecipients(row) {
            const targets = notificationState.catalog?.targets_by_itemtype?.[row?.itemtype || '']?.[row?.event || ''] || {};
            return Object.prototype.hasOwnProperty.call(targets, '1_3') ? ['1_3'] : [];
        }

        function notificationRecipientLabel(row, key) {
            const targets = notificationState.catalog?.targets_by_itemtype?.[row?.itemtype || '']?.[row?.event || ''] || {};
            return String(targets[key] || key || '-');
        }

        function notificationHasUpdateTicketNoiseRisk(row) {
            if (!notificationIsUpdateTicket(row) || Number(row?.is_active || 0) !== 1) {
                return false;
            }

            const recipients = Array.isArray(row?.recipient_keys) ? row.recipient_keys : [];
            return recipients.length > 1;
        }

        function renderNotificationTemplateCell(row) {
            const currentTemplate = notificationCurrentMailTemplateName(row);
            const isIndividual = notificationHasIndividualTemplate(row);
            const stateClass = isIndividual ? 'ok' : 'warn';
            const stateLabel = isIndividual ? 'Individual' : 'Pendente';
            const noiseBadge = notificationHasUpdateTicketNoiseRisk(row)
                ? '<span class="settings-template-state warn">Alto ruído</span>'
                : '';

            return `
                <div class="settings-template-cell">
                    <div class="settings-template-cell-main">
                        <span class="settings-template-name">${escapeHtml(currentTemplate)}</span>
                        <span class="settings-template-state ${stateClass}">${stateLabel}</span>
                        ${noiseBadge}
                    </div>
                </div>
            `;
        }

        function notificationIsFollowupEvent(row) {
            const event = String(row?.event || '');
            return String(row?.itemtype || '') === 'Ticket' && ['add_followup', 'update_followup'].includes(event);
        }

        function notificationExpectedFollowupTemplateName() {
            return 'Tickets Acompanhamento';
        }

        function notificationHasExpectedFollowupTemplate(row) {
            const currentTemplate = notificationCurrentMailTemplateName(row);
            return currentTemplate.localeCompare(notificationExpectedFollowupTemplateName(), 'pt-BR', { sensitivity: 'accent' }) === 0;
        }

        function openNotificationInfoDialog() {
            const modal = document.getElementById('notificationInfoModal');
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeNotificationInfoModal() {
            const modal = document.getElementById('notificationInfoModal');
            const fixButton = document.getElementById('fixFollowupTemplateButton');
            const individualButton = document.getElementById('createIndividualTemplateInfoButton');
            activeNotificationInfoId = 0;
            setSettingsStatus('notificationInfoStatus', '', '');
            if (fixButton) {
                fixButton.hidden = true;
                fixButton.disabled = false;
            }
            if (individualButton) {
                individualButton.hidden = true;
                individualButton.disabled = false;
            }
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function renderNotificationInitialConfigDiagnostic(row) {
            const section = document.getElementById('notificationInitialConfigSection');
            const card = document.getElementById('notificationInitialConfigCard');
            const button = document.getElementById('createIndividualTemplateInfoButton');
            if (!section || !card || !button) {
                return;
            }

            if (!notificationIsDefaultInitial(row)) {
                section.hidden = true;
                button.hidden = true;
                return;
            }

            const notificationId = Number(row.id) || 0;
            const expectedTemplate = notificationExpectedIndividualTemplateName(row);
            const currentTemplate = notificationCurrentMailTemplateName(row);
            const isConfigured = notificationHasIndividualTemplate(row);
            const defaultLabel = defaultInitialNotifications[notificationId] || row.name || '-';

            section.hidden = false;
            button.hidden = isConfigured;
            button.disabled = false;
            button.dataset.notificationCreateTemplateInfo = String(notificationId);
            card.className = `settings-diagnostic-card ${isConfigured ? 'ok' : 'warn'}`;
            card.innerHTML = isConfigured
                ? `
                    <div class="settings-diagnostic-title">Modelo individual configurado</div>
                    <div class="settings-diagnostic-text">Esta notificação padrão já usa um modelo com o mesmo nome da notificação.</div>
                    <div class="settings-diagnostic-meta">
                        <div><strong>Notificação padrão:</strong> #${notificationId} ${escapeHtml(defaultLabel)}</div>
                        <div><strong>Modelo individual esperado:</strong> ${escapeHtml(expectedTemplate)}</div>
                        <div><strong>Modelo atual:</strong> ${escapeHtml(currentTemplate)}</div>
                    </div>
                `
                : `
                    <div class="settings-diagnostic-title">Modelo individual pendente</div>
                    <div class="settings-diagnostic-text">Crie e vincule um modelo individual para esta notificação. O modelo será criado com o mesmo nome e assunto da notificação.</div>
                    <div class="settings-diagnostic-meta">
                        <div><strong>Notificação padrão:</strong> #${notificationId} ${escapeHtml(defaultLabel)}</div>
                        <div><strong>Modelo individual esperado:</strong> ${escapeHtml(expectedTemplate)}</div>
                        <div><strong>Modelo atual:</strong> ${escapeHtml(currentTemplate)}</div>
                    </div>
                `;
        }

        function renderNotificationNoiseControlDiagnostic(row) {
            const section = document.getElementById('notificationNoiseControlSection');
            const card = document.getElementById('notificationNoiseControlCard');
            if (!section || !card) {
                return;
            }

            if (!notificationIsUpdateTicket(row)) {
                section.hidden = true;
                return;
            }

            const notificationId = Number(row.id) || 0;
            const isActive = Number(row.is_active || 0) === 1;
            const recipients = Array.isArray(row.recipient_keys) ? row.recipient_keys : [];
            const recipientLabels = recipients.map((key) => notificationRecipientLabel(row, key));
            const recommendedRecipients = notificationRecommendedUpdateRecipients(row);
            const recommendedLabels = recommendedRecipients.map((key) => notificationRecipientLabel(row, key));
            const alreadyReduced = recommendedRecipients.length > 0
                && recipients.length === recommendedRecipients.length
                && recommendedRecipients.every((key) => recipients.includes(key));

            section.hidden = false;
            card.className = `settings-diagnostic-card ${isActive && recipients.length > 1 ? 'warn' : 'ok'}`;
            card.innerHTML = `
                <div class="settings-diagnostic-title">${isActive ? 'Update Ticket pode gerar ruído' : 'Update Ticket desativado'}</div>
                <div class="settings-diagnostic-text">
                    O evento Update Ticket dispara em alterações genéricas do chamado, incluindo atribuição. Para nova atribuição, prefira a notificação dedicada <strong>New user in assignees</strong>.
                </div>
                <div class="settings-diagnostic-meta">
                    <div><strong>Destinatários atuais:</strong> ${escapeHtml(recipientLabels.length ? recipientLabels.join(', ') : '-')}</div>
                    <div><strong>Recomendação:</strong> ${escapeHtml(recommendedLabels.length ? `desativar ou manter somente ${recommendedLabels.join(', ')}` : 'desativar ou revisar manualmente os destinatários')}</div>
                </div>
                ${isActive ? `
                    <div class="settings-actions">
                        <button class="settings-btn danger" type="button" data-notification-noise-disable="${notificationId}">
                            <i class="fas fa-ban"></i>
                            <span>Desativar Update Ticket</span>
                        </button>
                        ${recommendedRecipients.length && !alreadyReduced ? `
                            <button class="settings-btn" type="button" data-notification-noise-reduce="${notificationId}">
                                <i class="fas fa-filter"></i>
                                <span>Manter só requerente</span>
                            </button>
                        ` : ''}
                        <button class="settings-btn" type="button" data-notification-noise-edit="${notificationId}">
                            <i class="fas fa-pen-to-square"></i>
                            <span>Revisar manualmente</span>
                        </button>
                    </div>
                ` : ''}
            `;

            card.querySelector('[data-notification-noise-disable]')?.addEventListener('click', () => {
                disableNoisyUpdateTicket(notificationId);
            });
            card.querySelector('[data-notification-noise-reduce]')?.addEventListener('click', () => {
                reduceNoisyUpdateTicketRecipients(notificationId);
            });
            card.querySelector('[data-notification-noise-edit]')?.addEventListener('click', () => {
                openNotificationNoiseManualReview(notificationId);
            });
        }

        function renderNotificationFollowupDiagnostic(row) {
            const section = document.getElementById('notificationFollowupDiagnosticSection');
            const card = document.getElementById('notificationFollowupDiagnosticCard');
            const fixButton = document.getElementById('fixFollowupTemplateButton');
            if (!section || !card || !fixButton) {
                return;
            }

            if (!notificationIsFollowupEvent(row) || notificationIsDefaultInitial(row)) {
                section.hidden = true;
                fixButton.hidden = true;
                return;
            }

            section.hidden = false;
            const currentTemplate = notificationCurrentMailTemplateName(row);
            const expectedTemplate = notificationExpectedFollowupTemplateName();
            const isConfigured = notificationHasExpectedFollowupTemplate(row);

            fixButton.hidden = isConfigured;
            card.className = `settings-diagnostic-card ${isConfigured ? 'ok' : 'warn'}`;
            card.innerHTML = isConfigured
                ? `
                    <div class="settings-diagnostic-title">Configuração OK</div>
                    <div class="settings-diagnostic-text">Esta notificação de follow-up já está apontando para o template dedicado de acompanhamento da Hafen.</div>
                    <div class="settings-diagnostic-meta">
                        <div><strong>Template esperado:</strong> ${escapeHtml(expectedTemplate)}</div>
                        <div><strong>Template atual:</strong> ${escapeHtml(currentTemplate)}</div>
                    </div>
                `
                : `
                    <div class="settings-diagnostic-title">Template de follow-up divergente</div>
                    <div class="settings-diagnostic-text">O evento de acompanhamento deveria usar o template dedicado <strong>${escapeHtml(expectedTemplate)}</strong>, mas o vínculo atual está diferente. A correção guiada atualiza Add Followup e Update Followup sem mexer em New Ticket e Update Ticket.</div>
                    <div class="settings-diagnostic-meta">
                        <div><strong>Template esperado:</strong> ${escapeHtml(expectedTemplate)}</div>
                        <div><strong>Template atual:</strong> ${escapeHtml(currentTemplate)}</div>
                    </div>
                `;
        }

        function openNotificationInfoModal(id, options = {}) {
            const notificationId = Number(id) || 0;
            const row = (notificationState.notifications || []).find((item) => Number(item.id) === notificationId);
            if (!row) {
                closeNotificationInfoModal();
                setSettingsStatus('notificationListStatus', 'Notificação não encontrada na grade atual.', 'error');
                return;
            }

            activeNotificationInfoId = notificationId;
            document.getElementById('notificationInfoModalTitle').textContent = `Detalhes da notificação #${notificationId}`;
            document.getElementById('notificationInfoGrid').innerHTML = [
                notificationInfoField('ID', `#${notificationId}`),
                notificationInfoField('Status', formatBool(row.is_active)),
                notificationInfoField('Nome', row.name || '-', true),
                notificationInfoField('Tipo', notificationItemtypeLabel(row.itemtype)),
                notificationInfoField('Evento', notificationEventLabel(row.itemtype, row.event)),
                notificationInfoField('Entidade', row.entity_name || 'Entidade raiz'),
                notificationInfoField('Modelo(s) vinculado(s)', row.template_names || '-', true),
                notificationInfoField('Destinatários resumidos', row.recipient_summary || '-', true),
            ].join('');
            renderNotificationInitialConfigDiagnostic(row);
            renderNotificationNoiseControlDiagnostic(row);
            renderNotificationFollowupDiagnostic(row);
            setSettingsStatus('notificationInfoStatus', options.statusMessage || '', options.statusType || '');
            openNotificationInfoDialog();
        }

        function applyNotificationConfirmPresentation(options = {}) {
            const titleElement = document.getElementById('notificationConfirmModalTitle');
            const confirmButton = document.getElementById('confirmNotificationBulkDisable');
            const confirmIcon = document.getElementById('notificationConfirmButtonIcon');
            const confirmLabel = document.getElementById('notificationConfirmButtonLabel');
            const closeButton = document.getElementById('closeNotificationConfirmModal');
            const presentation = {
                ...notificationConfirmDefaults,
                ...(options || {})
            };

            if (titleElement) {
                titleElement.textContent = presentation.title;
            }
            if (confirmButton) {
                confirmButton.classList.remove('primary', 'success', 'danger');
                confirmButton.classList.add(presentation.confirmTone || notificationConfirmDefaults.confirmTone);
            }
            if (confirmIcon) {
                confirmIcon.className = `fas ${presentation.confirmIcon || notificationConfirmDefaults.confirmIcon}`;
            }
            if (confirmLabel) {
                confirmLabel.textContent = presentation.confirmLabel;
            }
            if (closeButton) {
                closeButton.setAttribute('aria-label', presentation.cancelAriaLabel || notificationConfirmDefaults.cancelAriaLabel);
            }
        }

        function openNotificationConfirmModal(message, onConfirm, options = {}) {
            const modal = document.getElementById('notificationConfirmModal');
            const messageElement = document.getElementById('notificationConfirmMessage');
            pendingNotificationConfirmAction = typeof onConfirm === 'function' ? onConfirm : null;
            applyNotificationConfirmPresentation(options);
            if (messageElement) {
                messageElement.textContent = message || 'Confirmar ação?';
            }
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeNotificationConfirmModal() {
            const modal = document.getElementById('notificationConfirmModal');
            const confirmButton = document.getElementById('confirmNotificationBulkDisable');
            pendingNotificationConfirmAction = null;
            if (confirmButton) {
                confirmButton.disabled = false;
            }
            applyNotificationConfirmPresentation();
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function normalizeTemplatePreviewContent(content, isHtml) {
            const raw = String(content ?? '');
            if (!isHtml) {
                return raw;
            }

            const trimmed = raw.trim();
            if (!trimmed) {
                return '';
            }

            const hasRealTags = /<\/?[a-z][^>]*>/i.test(trimmed);
            const hasEscapedTags = /&lt;\/?[a-z][^&]*&gt;/i.test(trimmed);
            return !hasRealTags && hasEscapedTags ? decodeHtmlEntities(trimmed) : trimmed;
        }

        function templatePreviewDocument(content, isHtml) {
            const body = isHtml
                ? content
                : `<pre style="white-space: pre-wrap; font: 14px/1.55 Inter, Arial, sans-serif; margin: 0;">${escapeHtml(content || 'Sem conteúdo para preview.')}</pre>`;
            return `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<base target="_blank">
<style>
html { background: #ebeff2; }
body {
    width: 640px;
    margin: 0 auto;
    padding: 0;
    color: #111827;
    background: #fff;
    font: 14px/1.55 Inter, Arial, sans-serif;
    zoom: .78;
}
@supports not (zoom: 1) {
    body {
        transform: scale(.78);
        transform-origin: top center;
    }
}
img { max-width: 100%; height: auto; }
table { max-width: 100%; border-collapse: collapse; }
.x_outer { min-width: 0 !important; }
</style>
</head>
<body class="light-mode">${body}</body>
</html>`;
        }

        function resetNotificationTemplatePreview() {
            const frame = document.getElementById('templatePreviewFrame');
            if (!frame) {
                return;
            }

            if (notificationTemplatePreviewUrl) {
                URL.revokeObjectURL(notificationTemplatePreviewUrl);
                notificationTemplatePreviewUrl = '';
            }

            frame.removeAttribute('srcdoc');
            frame.src = 'about:blank';
        }

        function renderNotificationTemplatePreview(documentHtml) {
            const frame = document.getElementById('templatePreviewFrame');
            if (!frame) {
                return;
            }

            if (notificationTemplatePreviewUrl) {
                URL.revokeObjectURL(notificationTemplatePreviewUrl);
                notificationTemplatePreviewUrl = '';
            }

            try {
                const blob = new Blob([documentHtml], { type: 'text/html' });
                notificationTemplatePreviewUrl = URL.createObjectURL(blob);
                frame.src = notificationTemplatePreviewUrl;
            } catch (error) {
                frame.srcdoc = documentHtml;
            }
        }

        function updateNotificationTemplatePreview() {
            const html = document.getElementById('templateEditorHtml').value;
            const text = document.getElementById('templateEditorText').value;
            const useHtml = Boolean(html.trim());
            const content = normalizeTemplatePreviewContent(useHtml ? html : text, useHtml);
            renderNotificationTemplatePreview(templatePreviewDocument(content, useHtml));
        }

        function initNotificationSteps() {
            document.querySelectorAll('[data-notification-step-target]').forEach((button) => {
                button.addEventListener('click', () => setNotificationStep(button.dataset.notificationStepTarget));
            });
            setNotificationStep(activeNotificationStep);
        }

        function initSettingsFilters() {
            const templateSearch = document.getElementById('notificationTemplateSearch');
            const collectorSearch = document.getElementById('collectorSearch');
            const cleanupSearch = document.getElementById('notificationCleanupSearch');
            const recipientSearch = document.getElementById('recipientCatalogSearch');

            [
                ['notificationFilterName', 'name'],
                ['notificationFilterType', 'itemtype'],
                ['notificationFilterEvent', 'event'],
                ['notificationFilterEntity', 'entity'],
                ['notificationFilterTemplate', 'template'],
                ['notificationFilterStatus', 'status'],
            ].forEach(([id, key]) => {
                const element = document.getElementById(id);
                if (!element) return;
                const sync = () => {
                    notificationColumnFilters[key] = element.value.trim();
                    renderNotificationTables();
                };
                element.addEventListener('input', sync);
                element.addEventListener('change', sync);
            });
            templateSearch?.addEventListener('input', () => {
                settingsFilters.templates = templateSearch.value;
                renderNotificationTables();
            });
            collectorSearch?.addEventListener('input', () => {
                settingsFilters.collectors = collectorSearch.value;
                renderCollectorsSettings();
            });
            cleanupSearch?.addEventListener('input', () => {
                settingsFilters.cleanup = cleanupSearch.value;
                renderCleanupTable();
            });
            recipientSearch?.addEventListener('input', renderNotificationRecipientCatalog);
        }

        function initNotificationTemplateModal() {
            document.getElementById('closeNotificationTemplateModal').addEventListener('click', closeNotificationTemplateModal);
            document.getElementById('notificationTemplateModal').addEventListener('click', (event) => {
                if (event.target.id === 'notificationTemplateModal') {
                    closeNotificationTemplateModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && document.getElementById('notificationTemplateModal').classList.contains('active')) {
                    closeNotificationTemplateModal();
                }
            });
            ['templateEditorSubject', 'templateEditorHtml', 'templateEditorText'].forEach((id) => {
                document.getElementById(id).addEventListener('input', updateNotificationTemplatePreview);
            });
        }

        function initNotificationInfoModal() {
            const modal = document.getElementById('notificationInfoModal');
            const closeButton = document.getElementById('closeNotificationInfoModal');
            const fixButton = document.getElementById('fixFollowupTemplateButton');
            const individualButton = document.getElementById('createIndividualTemplateInfoButton');

            closeButton.addEventListener('click', closeNotificationInfoModal);
            modal.addEventListener('click', (event) => {
                if (event.target.id === 'notificationInfoModal') {
                    closeNotificationInfoModal();
                }
            });
            fixButton.addEventListener('click', fixNotificationFollowupTemplate);
            individualButton.addEventListener('click', () => {
                createIndividualNotificationTemplate(activeNotificationInfoId, { statusId: 'notificationInfoStatus' });
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && document.getElementById('notificationInfoModal').classList.contains('active')) {
                    closeNotificationInfoModal();
                }
            });
        }

        function setChannelsFaqTab(tab) {
            const modal = document.getElementById('channelsFaqModal');
            modal.querySelectorAll('[data-faq-tab]').forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.faqTab === tab);
            });
            modal.querySelectorAll('[data-faq-section]').forEach((section) => {
                section.hidden = section.dataset.faqSection !== tab;
            });
        }

        function openChannelsFaqModal(tab) {
            const modal = document.getElementById('channelsFaqModal');
            setChannelsFaqTab(tab || 'teams');
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeChannelsFaqModal() {
            const modal = document.getElementById('channelsFaqModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }

        function initChannelsFaqModal() {
            const modal = document.getElementById('channelsFaqModal');
            document.getElementById('ticketAssignedFaqButton').addEventListener('click', () => openChannelsFaqModal('teams'));
            document.getElementById('closeChannelsFaqModal').addEventListener('click', closeChannelsFaqModal);
            modal.querySelectorAll('[data-faq-tab]').forEach((btn) => {
                btn.addEventListener('click', () => setChannelsFaqTab(btn.dataset.faqTab));
            });
            modal.addEventListener('click', (event) => {
                if (event.target.id === 'channelsFaqModal') {
                    closeChannelsFaqModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('active')) {
                    closeChannelsFaqModal();
                }
            });
        }

        // PLAN-20260714-022 (Sub-fase 2/3): modal global de credencial nomeada —
        // compartilhado entre a tela de Regras ("+ Nova credencial" por ação, com
        // actionId) e a tela de Canais de Alerta (gerenciamento do catálogo, sem
        // actionId). Fica em escopo global porque as duas telas são IIFEs separadas.
        let pendingCredentialActionId = null;
        let pendingCredentialActionType = null;
        let pendingCredentialOnSaved = null;
        let pendingCredentialId = 0;

        function channelCredentialCsrf() {
            const el = document.getElementById('rulesCsrf') || document.getElementById('alertingCsrf');
            return el ? el.value : '';
        }

        /**
         * @param {string} actionType
         * @param {?number} actionId - presente só quando aberto da tela de Regras (anexa a credencial à ação).
         * @param {?function} onSaved - chamado quando aberto sem actionId (ex.: Canais de Alerta recarrega a lista).
         * @param {?object} credential - presente ao editar (dashglpi_channel_credential_public de inc/rules_engine.php).
         */
        function openChannelCredentialModal(actionType, actionId, onSaved, credential) {
            pendingCredentialActionType = actionType;
            pendingCredentialActionId = actionId || null;
            pendingCredentialOnSaved = typeof onSaved === 'function' ? onSaved : null;
            pendingCredentialId = credential ? credential.id : 0;

            const actionLabelMap = { teams: 'Microsoft Teams', whatsapp: 'WhatsApp', telegram: 'Telegram' };
            const titlePrefix = credential ? 'Editar credencial — ' : 'Nova credencial — ';
            document.getElementById('channelCredentialModalTitle').textContent = titlePrefix + (actionLabelMap[actionType] || actionType);

            const secret = (credential && credential.secret) || {};
            document.getElementById('credentialName').value = credential ? credential.name : '';
            document.getElementById('credentialWebhookUrl').value = secret.webhook_url || '';
            document.getElementById('credentialApiUrl').value = secret.api_url || '';
            document.getElementById('credentialInstance').value = secret.instance || '';
            document.getElementById('credentialApiKey').value = '';
            document.getElementById('credentialApiKey').placeholder = secret.api_key_set ? '(configurada — deixe em branco para manter)' : 'obrigatório ao criar';
            document.getElementById('credentialBotToken').value = '';
            document.getElementById('credentialBotToken').placeholder = secret.bot_token_set ? '(configurado — deixe em branco para manter)' : 'obrigatório ao criar';
            document.getElementById('credentialIsDefault').checked = !!(credential && credential.is_default);

            const credStatus = document.getElementById('channelCredentialStatus');
            credStatus.className = 'settings-status';
            credStatus.textContent = '';
            document.querySelectorAll('[data-cred-field]').forEach((el) => {
                el.hidden = el.getAttribute('data-cred-field') !== actionType;
            });

            const modal = document.getElementById('channelCredentialModal');
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeChannelCredentialModal() {
            const modal = document.getElementById('channelCredentialModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }

        function saveChannelCredential() {
            const credStatus = document.getElementById('channelCredentialStatus');
            const name = document.getElementById('credentialName').value.trim();
            if (!name) {
                credStatus.className = 'settings-status error';
                credStatus.textContent = 'Informe um nome para a credencial.';
                return;
            }

            const body = new URLSearchParams();
            body.set('csrf_token', channelCredentialCsrf());
            body.set('credential_action', 'save');
            body.set('id', String(pendingCredentialId || 0));
            body.set('action_type', pendingCredentialActionType);
            body.set('name', name);
            body.set('webhook_url', document.getElementById('credentialWebhookUrl').value.trim());
            body.set('api_url', document.getElementById('credentialApiUrl').value.trim());
            body.set('instance', document.getElementById('credentialInstance').value.trim());
            body.set('api_key', document.getElementById('credentialApiKey').value.trim());
            body.set('bot_token', document.getElementById('credentialBotToken').value.trim());
            body.set('is_default', document.getElementById('credentialIsDefault').checked ? '1' : '');

            fetch('/ajax/channel_credentials.php', { method: 'POST', credentials: 'same-origin', body })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.ok) {
                        credStatus.className = 'settings-status error';
                        credStatus.textContent = data.error || 'Erro ao salvar credencial.';
                        return;
                    }

                    const newCredentialId = data.id;
                    const actionId = pendingCredentialActionId;
                    const onSaved = pendingCredentialOnSaved;
                    closeChannelCredentialModal();

                    if (actionId) {
                        const attachBody = new URLSearchParams();
                        attachBody.set('csrf_token', channelCredentialCsrf());
                        attachBody.set('rules_action', 'action_set_credential');
                        attachBody.set('action_id', actionId);
                        attachBody.set('credential_id', newCredentialId);
                        fetch('/ajax/rules_config.php', { method: 'POST', credentials: 'same-origin', body: attachBody })
                            .then((r) => r.json())
                            .then((res) => {
                                if (typeof window.dashglpiRulesRefresh === 'function') {
                                    window.dashglpiRulesRefresh(res);
                                }
                            });
                    } else if (onSaved) {
                        onSaved(data);
                    }
                })
                .catch(() => {
                    credStatus.className = 'settings-status error';
                    credStatus.textContent = 'Erro de rede ao salvar credencial.';
                });
        }

        function initChannelCredentialModal() {
            const modal = document.getElementById('channelCredentialModal');
            document.getElementById('saveChannelCredentialButton').addEventListener('click', saveChannelCredential);
            document.getElementById('closeChannelCredentialModal').addEventListener('click', closeChannelCredentialModal);
            modal.addEventListener('click', (event) => {
                if (event.target.id === 'channelCredentialModal') {
                    closeChannelCredentialModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('active')) {
                    closeChannelCredentialModal();
                }
            });
        }

        function initNotificationConfirmModal() {
            const modal = document.getElementById('notificationConfirmModal');
            const closeButton = document.getElementById('closeNotificationConfirmModal');
            const confirmButton = document.getElementById('confirmNotificationBulkDisable');

            closeButton.addEventListener('click', closeNotificationConfirmModal);
            modal.addEventListener('click', (event) => {
                if (event.target.id === 'notificationConfirmModal') {
                    closeNotificationConfirmModal();
                }
            });
            confirmButton.addEventListener('click', async () => {
                const action = pendingNotificationConfirmAction;
                if (!action) {
                    closeNotificationConfirmModal();
                    return;
                }
                confirmButton.disabled = true;
                try {
                    await action();
                } catch (error) {
                    console.error(error);
                } finally {
                    closeNotificationConfirmModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('active')) {
                    closeNotificationConfirmModal();
                }
            });
        }

        async function loadNotifications(options = {}) {
            const { throwOnError = false } = options;
            try {
                const response = await fetch('/ajax/notifications.php', { headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                notificationState = {
                    notifications: data.notifications || [],
                    templates: data.templates || [],
                    mail_settings: data.mail_settings || {},
                    entities: data.entities || [],
                    summary: data.summary || {},
                    delivery: data.delivery || {},
                    catalog: data.catalog || { itemtypes: {}, events_by_itemtype: {}, targets_by_itemtype: {}, oauth: { providers: [], additional_parameters: {} } }
                };
                cleanupSelection.forEach((id) => {
                    if (!notificationState.notifications.some((row) => Number(row.id) === id && Number(row.is_active) === 1)) {
                        cleanupSelection.delete(id);
                    }
                });
                notificationSelection.forEach((id) => {
                    if (!notificationState.notifications.some((row) => Number(row.id) === id)) {
                        notificationSelection.delete(id);
                    }
                });
                if (!notificationEditorInitialized) {
                    resetNotificationForm(true);
                    notificationEditorInitialized = true;
                } else {
                    syncNotificationFormOptions();
                }
                syncNotificationColumnFilterOptions();
                renderNotificationTables();
                renderCleanupTable();
                renderDeliveryStatus();
                populateNotificationMailForm();
                return data;
            } catch (error) {
                const notificationsTable = document.getElementById('notificationsTable');
                const cleanupTable = document.getElementById('notificationCleanupTable');
                if (notificationsTable) {
                    notificationsTable.innerHTML = `<tr><td colspan="8">${escapeHtml(error.message)}</td></tr>`;
                }
                if (cleanupTable) {
                    cleanupTable.innerHTML = `<tr><td colspan="6">${escapeHtml(error.message)}</td></tr>`;
                }
                if (throwOnError) {
                    throw error;
                }
                return null;
            }
        }

        function appendNotificationMailForm(form) {
            form.set('admin_email', document.getElementById('mailAdminEmail').value);
            form.set('admin_email_name', document.getElementById('mailAdminName').value);
            form.set('from_email', document.getElementById('mailFromEmail').value);
            form.set('from_email_name', document.getElementById('mailFromName').value);
            form.set('replyto_email', document.getElementById('mailReplyEmail').value);
            form.set('replyto_email_name', document.getElementById('mailReplyName').value);
            form.set('noreply_email', document.getElementById('mailNoReplyEmail').value);
            form.set('noreply_email_name', document.getElementById('mailNoReplyName').value);
            form.set('mailing_signature', document.getElementById('mailSignature').value);
            form.set('smtp_mode', document.getElementById('mailSmtpMode').value);
            form.set('smtp_retry_time', document.getElementById('mailRetryTime').value);
            form.set('smtp_max_retries', document.getElementById('mailMaxRetries').value);
            form.set('smtp_check_certificate', document.getElementById('mailSmtpCheckCertificate').value);
            form.set('smtp_host', document.getElementById('mailSmtpHost').value);
            form.set('smtp_port', document.getElementById('mailSmtpPort').value);
            form.set('smtp_username', document.getElementById('mailSmtpUsername').value);
            form.set('smtp_sender', document.getElementById('mailSmtpSender').value);
            form.set('smtp_oauth_provider', document.getElementById('mailSmtpOauthProvider').value);
            form.set('smtp_oauth_client_id', document.getElementById('mailSmtpOauthClientId').value);
            form.set('smtp_oauth_options_json', JSON.stringify(collectOauthAdditionalOptions()));
            if (document.getElementById('mailSmtpPassword').value.trim() !== '') {
                form.set('smtp_passwd', document.getElementById('mailSmtpPassword').value);
            }
            if (document.getElementById('mailClearSmtpPassword').checked) {
                form.set('smtp_clear_password', '1');
            }
            if (document.getElementById('mailSmtpOauthClientSecret').value.trim() !== '') {
                form.set('smtp_oauth_client_secret', document.getElementById('mailSmtpOauthClientSecret').value);
            }
            form.set('attach_ticket_documents_to_mail', document.getElementById('mailAttachTicketDocuments').value || '0');
            form.set('attach_documents_to_notifications_for_anonymous', document.getElementById('mailAttachAnonymousDocuments').value || '0');
        }

        function collectOauthAdditionalOptions() {
            const values = {};
            document.querySelectorAll('[data-oauth-option]').forEach((input) => {
                const key = input.dataset.oauthOption;
                if (key) {
                    values[key] = input.value;
                }
            });
            return Object.keys(values)
                .sort((left, right) => left.localeCompare(right))
                .reduce((accumulator, key) => {
                    accumulator[key] = values[key];
                    return accumulator;
                }, {});
        }

        async function saveNotificationMailSettings() {
            const senderLoginIssue = validateNotificationMailSenderLoginRule({ autoFillSender: true });
            if (senderLoginIssue) {
                setSettingsStatus('notificationMailStatus', `${senderLoginIssue} Ajuste os campos antes de salvar.`, 'error');
                return;
            }

            setSettingsStatus('notificationMailStatus', 'Salvando...', '');
            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('notification_action', 'save_mail_settings');
            appendNotificationMailForm(form);

            try {
                const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                notificationState.mail_settings = data.mail_settings || {};
                notificationState.catalog = data.catalog || notificationState.catalog;
                notificationState.delivery = data.delivery || notificationState.delivery;
                populateNotificationMailForm();
                renderDeliveryStatus();
                setSettingsStatus('notificationMailStatus', 'Configuração de e-mail salva no GLPI.', 'ok');
            } catch (error) {
                setSettingsStatus('notificationMailStatus', error.message || 'Erro ao salvar configuração de e-mail.', 'error');
            }
        }

        async function reloadNotificationMailSettings() {
            setSettingsStatus('notificationMailStatus', 'Recarregando configuração salva no GLPI...', '');
            try {
                await loadNotifications({ throwOnError: true });
                setNotificationStep('mail');
                setSettingsStatus('notificationMailStatus', 'Configuração de e-mail recarregada do GLPI.', 'ok');
            } catch (error) {
                setSettingsStatus('notificationMailStatus', error.message || 'Erro ao recarregar configuração de e-mail.', 'error');
            }
        }

        async function retryOnStaleNotificationId(error, resolveAndApply) {
            const message = String(error?.message || '');
            if (!/nao encontrad/i.test(message)) return false;

            try {
                await loadNotifications({ throwOnError: true });
            } catch {
                return false;
            }

            return Boolean(resolveAndApply());
        }

        async function saveNotificationTemplateTranslation() {
            if (notificationTemplateContext === 'sla') {
                document.getElementById('slaNotificationTemplateId').value = document.getElementById('templateEditorId').value || '0';
                document.getElementById('slaNotificationTemplateTranslationId').value = document.getElementById('templateEditorTranslationId').value || '0';
                document.getElementById('slaNotificationTemplateName').value = document.getElementById('templateEditorName').value.replace(/\s+\(Chamado\)\s*$/, '') || 'Notificação por E-mail SLA';
                document.getElementById('slaNotificationTemplateLanguage').value = document.getElementById('templateEditorLanguage').value || 'pt_BR';
                document.getElementById('slaNotificationTemplateSubject').value = document.getElementById('templateEditorSubject').value || 'Notificação de SLA';
                document.getElementById('slaNotificationTemplateHtml').value = document.getElementById('templateEditorHtml').value || '';
                document.getElementById('slaNotificationTemplateText').value = document.getElementById('templateEditorText').value || '';
                slaState.sla_notification = {
                    ...(slaState.sla_notification || {}),
                    template: {
                        ...(slaState.sla_notification?.template || {}),
                        id: Number(document.getElementById('slaNotificationTemplateId').value) || 0,
                        translation_id: Number(document.getElementById('slaNotificationTemplateTranslationId').value) || 0,
                        name: document.getElementById('slaNotificationTemplateName').value || 'Notificação por E-mail SLA',
                        language: document.getElementById('slaNotificationTemplateLanguage').value || 'pt_BR',
                        subject: document.getElementById('slaNotificationTemplateSubject').value || 'Notificação de SLA',
                        content_html: document.getElementById('slaNotificationTemplateHtml').value || '',
                        content_text: document.getElementById('slaNotificationTemplateText').value || '',
                        status: 'pending',
                        status_label: 'Rascunho'
                    }
                };
                renderSlaNotificationStep();
                setSettingsStatus('notificationTemplateStatus', 'Rascunho do template SLA atualizado no fluxo.', 'ok');
                closeNotificationTemplateModal();
                return;
            }

            const buildTemplateForm = () => {
                const form = new FormData();
                form.set('csrf_token', settingsCsrfToken);
                form.set('notification_action', 'save_template_translation');
                form.set('template_id', document.getElementById('templateEditorId').value);
                form.set('translation_id', document.getElementById('templateEditorTranslationId').value);
                form.set('language', document.getElementById('templateEditorLanguage').value);
                form.set('subject', document.getElementById('templateEditorSubject').value);
                form.set('content_html', document.getElementById('templateEditorHtml').value);
                form.set('content_text', document.getElementById('templateEditorText').value);
                return form;
            };

            const resolveStaleTemplateId = () => {
                const rawName = document.getElementById('templateEditorRawName').value;
                const itemtype = document.getElementById('templateEditorItemtype').value;
                const fresh = (notificationState.templates || []).find((row) => row.name === rawName && String(row.itemtype || '') === String(itemtype));
                if (!fresh) return false;
                document.getElementById('templateEditorId').value = Number(fresh.id) || 0;
                document.getElementById('templateEditorTranslationId').value = Number(fresh.translation_id) || 0;
                return true;
            };

            setSettingsStatus('notificationTemplateStatus', 'Salvando...', '');

            for (let attempt = 0; attempt < 2; attempt++) {
                try {
                    const response = await fetch('/ajax/notifications.php', { method: 'POST', body: buildTemplateForm(), headers: { Accept: 'application/json' } });
                    const data = await readSettingsJson(response);
                    notificationState.templates = data.templates || [];
                    renderNotificationTables();
                    const savedTemplate = notificationState.templates.find((row) => Number(row.id) === Number(document.getElementById('templateEditorId').value));
                    if (savedTemplate) {
                        document.getElementById('templateEditorTranslationId').value = Number(savedTemplate.translation_id) || 0;
                        document.getElementById('templateEditorLanguage').value = savedTemplate.language || 'pt_BR';
                        document.getElementById('templateEditorSubject').value = savedTemplate.subject || '';
                        document.getElementById('templateEditorHtml').value = savedTemplate.content_html || '';
                        document.getElementById('templateEditorText').value = savedTemplate.content_text || '';
                        updateNotificationTemplatePreview();
                    }
                    setSettingsStatus(
                        'notificationTemplateStatus',
                        attempt === 0 ? 'Tradução padrão salva no GLPI.' : 'A lista estava desatualizada — atualizamos e salvamos com sucesso.',
                        'ok'
                    );
                    return;
                } catch (error) {
                    const recovered = attempt === 0 && await retryOnStaleNotificationId(error, resolveStaleTemplateId);
                    if (!recovered) {
                        setSettingsStatus('notificationTemplateStatus', error.message || 'Erro ao salvar tradução do modelo.', 'error');
                        return;
                    }
                    setSettingsStatus('notificationTemplateStatus', 'Modelo estava desatualizado. Atualizando e tentando novamente...', '');
                }
            }
        }

        async function sendNotificationTestEmail() {
            const senderLoginIssue = validateNotificationMailSenderLoginRule();
            if (senderLoginIssue) {
                setSettingsStatus('notificationMailStatus', `${senderLoginIssue} Salve uma configuracao valida no GLPI antes de testar.`, 'error');
                return;
            }

            updateNotificationMailDirtyState();
            if (notificationMailDirty) {
                setSettingsStatus(
                    'notificationMailStatus',
                    'Existem alterações não salvas na configuração de e-mail. Salve ou recarregue do GLPI antes de testar.',
                    'error'
                );
                return;
            }

            setSettingsStatus('notificationMailStatus', 'Enviando teste com a configuração salva no GLPI...', '');
            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('notification_action', 'send_test_email');
            form.set('admin_email', notificationState.mail_settings?.admin_email || document.getElementById('mailAdminEmail').value);

            try {
                const data = await readSettingsJson(await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } }));
                notificationState.mail_settings = data.mail_settings || notificationState.mail_settings;
                notificationState.catalog = data.catalog || notificationState.catalog;
                notificationState.delivery = data.delivery || notificationState.delivery;
                populateNotificationMailForm();
                renderDeliveryStatus();
                setSettingsStatus('notificationMailStatus', 'E-mail de teste solicitado ao GLPI com a configuração salva.', 'ok');
            } catch (error) {
                setSettingsStatus('notificationMailStatus', error.message || 'Erro ao enviar e-mail de teste.', 'error');
            }
        }

        async function saveNotification() {
            const buildNotificationForm = () => {
                const form = new FormData();
                form.set('csrf_token', settingsCsrfToken);
                form.set('notification_action', 'save');
                form.set('id', document.getElementById('notificationId').value);
                form.set('name', document.getElementById('notificationName').value);
                form.set('itemtype', document.getElementById('notificationItemtype').value);
                form.set('event', document.getElementById('notificationEvent').value);
                form.set('entities_id', document.getElementById('notificationEntity').value);
                form.set('template_id', document.getElementById('notificationTemplate').value);
                if (document.getElementById('notificationActive').checked) form.set('is_active', '1');
                if (document.getElementById('notificationRecursive').checked) form.set('is_recursive', '1');
                notificationEditorState.recipients.forEach((recipient) => form.append('recipients[]', recipient));
                return form;
            };

            const resolveStaleNotificationRefs = () => {
                const currentId = Number(document.getElementById('notificationId').value) || 0;
                let recovered = true;

                if (currentId > 0) {
                    const name = document.getElementById('notificationName').value;
                    const fresh = (notificationState.notifications || []).find((row) => row.name === name);
                    recovered = Boolean(fresh);
                    if (fresh) {
                        document.getElementById('notificationId').value = String(Number(fresh.id) || 0);
                    }
                }

                const templateSelect = document.getElementById('notificationTemplate');
                const currentTemplateId = Number(templateSelect.value) || 0;
                if (currentTemplateId > 0 && !(notificationState.templates || []).some((row) => Number(row.id) === currentTemplateId)) {
                    templateSelect.value = '0';
                }

                return recovered;
            };

            setSettingsStatus('notificationStatus', 'Salvando...', '');

            for (let attempt = 0; attempt < 2; attempt++) {
                try {
                    const response = await fetch('/ajax/notifications.php', { method: 'POST', body: buildNotificationForm(), headers: { Accept: 'application/json' } });
                    const data = await readSettingsJson(response);
                    notificationState.notifications = data.notifications || [];
                    notificationState.templates = data.templates || [];
                    notificationState.delivery = data.delivery || notificationState.delivery;
                    notificationState.catalog = data.catalog || notificationState.catalog;
                    notificationSelection.clear();
                    syncNotificationColumnFilterOptions();
                    renderNotificationTables();
                    renderCleanupTable();
                    renderDeliveryStatus();
                    resetNotificationForm(true);
                    showNotificationListView();
                    setSettingsStatus('notificationStatus', '', '');
                    setSettingsStatus(
                        'notificationListStatus',
                        attempt === 0 ? 'Notificação salva no GLPI.' : 'O registro estava desatualizado — atualizamos e salvamos com sucesso.',
                        'ok'
                    );
                    return;
                } catch (error) {
                    const recovered = attempt === 0 && await retryOnStaleNotificationId(error, resolveStaleNotificationRefs);
                    if (!recovered) {
                        setSettingsStatus('notificationStatus', error.message || 'Erro ao salvar notificação.', 'error');
                        return;
                    }
                    setSettingsStatus('notificationStatus', 'Registro estava desatualizado. Atualizando e tentando novamente...', '');
                }
            }
        }

        async function toggleNotification(id, active) {
            const notificationId = Number(id) || 0;
            if (notificationToggleInFlight.has(notificationId)) return;

            const button = document.querySelector(`[data-notification-toggle="${notificationId}"]`);
            const icon = button?.querySelector('i');
            const originalIconClass = icon?.className || '';

            notificationToggleInFlight.add(notificationId);
            if (button) button.disabled = true;
            if (icon) icon.className = 'fas fa-spinner fa-spin';

            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('notification_action', 'toggle');
            form.set('id', id);
            if (Number(active) === 1) form.set('is_active', '1');

            try {
                const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                notificationState.notifications = data.notifications || [];
                notificationState.summary = data.summary || {};
                notificationState.delivery = data.delivery || notificationState.delivery;
                syncNotificationColumnFilterOptions();
                renderNotificationTables();
                renderCleanupTable();
                renderDeliveryStatus();
            } catch (error) {
                setSettingsStatus('notificationListStatus', error.message || 'Erro ao atualizar notificação.', 'error');
                if (button) button.disabled = false;
                if (icon) icon.className = originalIconClass;
            } finally {
                notificationToggleInFlight.delete(notificationId);
            }
        }

        async function cloneNotification(id) {
            setSettingsStatus('notificationListStatus', 'Clonando notificação...', '');
            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('notification_action', 'clone');
            form.set('id', id);

            try {
                const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                notificationState.notifications = data.notifications || [];
                notificationState.templates = data.templates || [];
                notificationState.summary = data.summary || {};
                notificationState.delivery = data.delivery || notificationState.delivery;
                notificationState.catalog = data.catalog || notificationState.catalog;
                syncNotificationColumnFilterOptions();
                renderNotificationTables();
                renderCleanupTable();
                renderDeliveryStatus();

                const clonedId = Number(data.result?.notification_clone?.id || 0);
                const clonedRow = notificationState.notifications.find((row) => Number(row.id) === clonedId);
                const clonedLabel = clonedRow?.name ? `"${clonedRow.name}"` : `#${clonedId || 0}`;
                setSettingsStatus('notificationListStatus', `Notificação clonada no GLPI como cópia inativa: ${clonedLabel}.`, 'ok');
            } catch (error) {
                setSettingsStatus('notificationListStatus', error.message || 'Erro ao clonar notificação.', 'error');
            }
        }

        async function createIndividualNotificationTemplate(id, options = {}) {
            const notificationId = Number(id) || 0;
            const row = (notificationState.notifications || []).find((item) => Number(item.id) === notificationId);
            if (!row) {
                setSettingsStatus(options.statusId || 'notificationListStatus', 'Notificação não encontrada na grade atual.', 'error');
                return;
            }

            const setCreateButtonsDisabled = (disabled) => {
                const currentRow = (notificationState.notifications || []).find((item) => Number(item.id) === notificationId);
                const keepDisabled = !disabled && currentRow ? notificationHasIndividualTemplate(currentRow) : false;
                document.querySelectorAll(`[data-notification-create-template="${notificationId}"]`).forEach((button) => {
                    button.disabled = disabled || keepDisabled;
                });
                const infoButton = document.getElementById('createIndividualTemplateInfoButton');
                if (infoButton && Number(infoButton.dataset.notificationCreateTemplateInfo) === notificationId) {
                    infoButton.disabled = disabled || keepDisabled;
                }
            };

            const statusId = options.statusId || 'notificationListStatus';
            const expectedTemplate = notificationExpectedIndividualTemplateName(row);
            const progressMessage = `Criando modelo individual "${expectedTemplate}"...`;
            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('notification_action', 'create_individual_template');
            form.set('id', String(notificationId));
            form.set('language', 'pt_BR');

            setCreateButtonsDisabled(true);
            setSettingsStatus(statusId, progressMessage, '');
            if (statusId !== 'notificationListStatus') {
                setSettingsStatus('notificationListStatus', progressMessage, '');
            }

            try {
                const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                notificationState.notifications = data.notifications || [];
                notificationState.templates = data.templates || [];
                notificationState.entities = data.entities || notificationState.entities;
                notificationState.summary = data.summary || {};
                notificationState.mail_settings = data.mail_settings || notificationState.mail_settings;
                notificationState.delivery = data.delivery || notificationState.delivery;
                notificationState.catalog = data.catalog || notificationState.catalog;
                syncNotificationFormOptions();
                syncNotificationColumnFilterOptions();
                renderNotificationTables();
                renderCleanupTable();
                renderDeliveryStatus();

                const templateName = data.result?.individual_template?.template_name || expectedTemplate;
                const successMessage = `Modelo individual "${templateName}" criado e vinculado à notificação #${notificationId}.`;
                setSettingsStatus('notificationListStatus', successMessage, 'ok');
                if (activeNotificationInfoId === notificationId) {
                    openNotificationInfoModal(notificationId, { statusMessage: successMessage, statusType: 'ok' });
                } else {
                    setSettingsStatus(statusId, successMessage, 'ok');
                }
            } catch (error) {
                const message = error.message || 'Erro ao criar modelo individual.';
                setSettingsStatus(statusId, message, 'error');
                if (statusId !== 'notificationListStatus') {
                    setSettingsStatus('notificationListStatus', message, 'error');
                }
            } finally {
                setCreateButtonsDisabled(false);
            }
        }

        function refreshNotificationStateFromResponse(data) {
            notificationState.notifications = data.notifications || [];
            notificationState.templates = data.templates || notificationState.templates;
            notificationState.entities = data.entities || notificationState.entities;
            notificationState.summary = data.summary || {};
            notificationState.mail_settings = data.mail_settings || notificationState.mail_settings;
            notificationState.delivery = data.delivery || notificationState.delivery;
            notificationState.catalog = data.catalog || notificationState.catalog;
            syncNotificationFormOptions();
            syncNotificationColumnFilterOptions();
            renderNotificationTables();
            renderCleanupTable();
            renderDeliveryStatus();
        }

        function disableNoisyUpdateTicket(id) {
            const notificationId = Number(id) || 0;
            const row = (notificationState.notifications || []).find((item) => Number(item.id) === notificationId);
            if (!row || !notificationIsUpdateTicket(row)) {
                setSettingsStatus('notificationInfoStatus', 'Notificação Update Ticket não encontrada.', 'error');
                return;
            }

            openNotificationConfirmModal(
                'Desativar Update Ticket? A atribuição continuará usando a notificação dedicada New user in assignees.',
                async () => {
                    const form = new FormData();
                    form.set('csrf_token', settingsCsrfToken);
                    form.set('notification_action', 'toggle');
                    form.set('id', String(notificationId));

                    try {
                        const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                        const data = await readSettingsJson(response);
                        refreshNotificationStateFromResponse(data);
                        const message = 'Update Ticket desativado para reduzir ruído de notificações.';
                        setSettingsStatus('notificationListStatus', message, 'ok');
                        openNotificationInfoModal(notificationId, { statusMessage: message, statusType: 'ok' });
                    } catch (error) {
                        const message = error.message || 'Erro ao desativar Update Ticket.';
                        setSettingsStatus('notificationInfoStatus', message, 'error');
                        setSettingsStatus('notificationListStatus', message, 'error');
                    }
                },
                {
                    title: 'Controle de ruído',
                    confirmLabel: 'Desativar',
                    confirmIcon: 'fa-ban',
                    confirmTone: 'danger',
                    cancelAriaLabel: 'Cancelar desativação'
                }
            );
        }

        function reduceNoisyUpdateTicketRecipients(id) {
            const notificationId = Number(id) || 0;
            const row = (notificationState.notifications || []).find((item) => Number(item.id) === notificationId);
            if (!row || !notificationIsUpdateTicket(row)) {
                setSettingsStatus('notificationInfoStatus', 'Notificação Update Ticket não encontrada.', 'error');
                return;
            }

            const recommendedRecipients = notificationRecommendedUpdateRecipients(row);
            if (!recommendedRecipients.length) {
                setSettingsStatus('notificationInfoStatus', 'Destinatário recomendado não disponível no catálogo do GLPI.', 'error');
                return;
            }

            openNotificationConfirmModal(
                'Manter somente Requerente em Update Ticket? Isso reduz duplicidades, mas o evento continuará disparando para atualizações genéricas.',
                async () => {
                    const form = new FormData();
                    form.set('csrf_token', settingsCsrfToken);
                    form.set('notification_action', 'save');
                    form.set('id', String(notificationId));
                    form.set('name', row.name || 'Update Ticket');
                    form.set('itemtype', row.itemtype || 'Ticket');
                    form.set('event', row.event || 'update');
                    form.set('entities_id', String(Number(row.entities_id) || 0));
                    form.set('template_id', String(Number(row.template_id) || 0));
                    if (Number(row.is_active || 0) === 1) form.set('is_active', '1');
                    if (Number(row.is_recursive || 0) === 1) form.set('is_recursive', '1');
                    recommendedRecipients.forEach((recipient) => form.append('recipients[]', recipient));

                    try {
                        const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                        const data = await readSettingsJson(response);
                        refreshNotificationStateFromResponse(data);
                        const message = 'Update Ticket ajustado para enviar somente ao Requerente.';
                        setSettingsStatus('notificationListStatus', message, 'ok');
                        openNotificationInfoModal(notificationId, { statusMessage: message, statusType: 'ok' });
                    } catch (error) {
                        const message = error.message || 'Erro ao reduzir destinatários do Update Ticket.';
                        setSettingsStatus('notificationInfoStatus', message, 'error');
                        setSettingsStatus('notificationListStatus', message, 'error');
                    }
                },
                {
                    title: 'Controle de ruído',
                    confirmLabel: 'Aplicar',
                    confirmIcon: 'fa-filter',
                    confirmTone: 'primary',
                    cancelAriaLabel: 'Cancelar ajuste'
                }
            );
        }

        function openNotificationNoiseManualReview(id) {
            const notificationId = Number(id) || 0;
            const row = (notificationState.notifications || []).find((item) => Number(item.id) === notificationId);
            if (!row) {
                setSettingsStatus('notificationInfoStatus', 'Notificação não encontrada para revisão.', 'error');
                return;
            }

            closeNotificationInfoModal();
            showNotificationEditor(row);
            setSettingsStatus('notificationStatus', 'Revise os destinatários do Update Ticket antes de salvar.', '');
        }

        async function fixNotificationFollowupTemplate() {
            const fixButton = document.getElementById('fixFollowupTemplateButton');
            if (!activeNotificationInfoId || !fixButton) {
                return;
            }

            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('notification_action', 'fix_followup_template');
            form.set('preset', 'hafen');
            fixButton.disabled = true;
            setSettingsStatus('notificationInfoStatus', 'Aplicando correção de follow-up...', '');
            setSettingsStatus('notificationListStatus', 'Aplicando correção de follow-up...', '');

            try {
                const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                notificationState.notifications = data.notifications || [];
                notificationState.templates = data.templates || [];
                notificationState.entities = data.entities || notificationState.entities;
                notificationState.summary = data.summary || {};
                notificationState.mail_settings = data.mail_settings || notificationState.mail_settings;
                notificationState.delivery = data.delivery || notificationState.delivery;
                notificationState.catalog = data.catalog || notificationState.catalog;
                syncNotificationColumnFilterOptions();
                renderNotificationTables();
                renderCleanupTable();
                renderDeliveryStatus();

                const templateName = data.result?.followup_template_fix?.template_name || notificationExpectedFollowupTemplateName();
                const successMessage = `Correção aplicada: Add Followup e Update Followup agora usam "${templateName}".`;
                openNotificationInfoModal(activeNotificationInfoId, { statusMessage: successMessage, statusType: 'ok' });
                setSettingsStatus('notificationListStatus', successMessage, 'ok');
            } catch (error) {
                const message = error.message || 'Erro ao corrigir configuração de follow-up.';
                setSettingsStatus('notificationInfoStatus', message, 'error');
                setSettingsStatus('notificationListStatus', message, 'error');
            } finally {
                fixButton.disabled = false;
            }
        }

        function selectVisibleNotifications() {
            const visibleRows = (notificationState.notifications || []).filter((row) => notificationMatchesColumnFilters(row));
            visibleRows.forEach((row) => {
                notificationSelection.add(Number(row.id) || 0);
            });
            renderNotificationTables();
        }

        async function bulkDisableSelectedNotifications() {
            if (notificationSelection.size === 0) {
                setSettingsStatus('notificationListStatus', 'Selecione ao menos uma notificação na grade para desativar.', 'error');
                return;
            }

            const selectedIds = Array.from(notificationSelection);
            openNotificationConfirmModal(`Desativar ${selectedIds.length} notificação(ões) selecionada(s)?`, async () => {
                setSettingsStatus('notificationListStatus', 'Desativando notificações selecionadas...', '');
                const form = new FormData();
                form.set('csrf_token', settingsCsrfToken);
                form.set('notification_action', 'bulk_disable');
                selectedIds.forEach((id) => form.append('ids[]', String(id)));

                try {
                    const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                    const data = await readSettingsJson(response);
                    notificationSelection.clear();
                    notificationState.notifications = data.notifications || [];
                    notificationState.summary = data.summary || {};
                    notificationState.delivery = data.delivery || notificationState.delivery;
                    syncNotificationColumnFilterOptions();
                    renderNotificationTables();
                    renderCleanupTable();
                    renderDeliveryStatus();
                    setSettingsStatus('notificationListStatus', 'Notificações selecionadas desativadas no GLPI.', 'ok');
                } catch (error) {
                    setSettingsStatus('notificationListStatus', error.message || 'Erro ao desativar notificações.', 'error');
                }
            });
        }

        async function bulkDisableNotifications() {
            if (cleanupSelection.size === 0) {
                setSettingsStatus('notificationCleanupStatus', 'Selecione ao menos uma notificação para desativar.', 'error');
                return;
            }

            const selectedIds = Array.from(cleanupSelection);
            openNotificationConfirmModal(`Desativar ${selectedIds.length} notificação(ões) selecionada(s)?`, async () => {
                setSettingsStatus('notificationCleanupStatus', 'Desativando notificações selecionadas...', '');
                const form = new FormData();
                form.set('csrf_token', settingsCsrfToken);
                form.set('notification_action', 'bulk_disable');
                selectedIds.forEach((id) => form.append('ids[]', String(id)));

                try {
                    const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                    const data = await readSettingsJson(response);
                    cleanupSelection.clear();
                    notificationState.notifications = data.notifications || [];
                    notificationState.summary = data.summary || {};
                    syncNotificationColumnFilterOptions();
                    renderNotificationTables();
                    renderCleanupTable();
                    setSettingsStatus('notificationCleanupStatus', 'Notificações selecionadas desativadas no GLPI.', 'ok');
                } catch (error) {
                    setSettingsStatus('notificationCleanupStatus', error.message || 'Erro ao desativar notificações.', 'error');
                }
            });
        }

        async function applyQueuedNotificationRecommendation() {
            setSettingsStatus('notificationQueueStatus', 'Aplicando recomendação...', '');
            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('notification_action', 'update_queue');

            try {
                const response = await fetch('/ajax/notifications.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                notificationState.delivery = data.delivery || notificationState.delivery;
                renderDeliveryStatus();
                setSettingsStatus('notificationQueueStatus', 'queuednotification ajustada para Ativa + CLI + 60s.', 'ok');
            } catch (error) {
                setSettingsStatus('notificationQueueStatus', error.message || 'Erro ao atualizar queuednotification.', 'error');
            }
        }

        async function loadCollectors() {
            try {
                const response = await fetch('/ajax/mailcollectors.php', { headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                collectorState = { collectors: data.collectors || [], summary: data.summary || {} };
                renderCollectorsSettings();
            } catch (error) {
                document.getElementById('collectorsTable').innerHTML = `<tr><td colspan="8">${escapeHtml(error.message)}</td></tr>`;
            }
        }

        function renderCollectorsSettings() {
            const collectors = collectorState.collectors || [];
            const collectorParts = searchParts(settingsFilters.collectors);
            const filteredCollectors = collectors.filter((row) => rowMatchesSearch(collectorSearchRow(row), collectorParts));
            document.getElementById('collectorsTable').innerHTML = filteredCollectors.length
                ? filteredCollectors.map((row) => `
                    <tr>
                        <td><strong>#${Number(row.id) || 0}</strong></td>
                        <td><strong>${escapeHtml(row.name || '-')}</strong></td>
                        <td>${escapeHtml(row.host || '-')}</td>
                        <td>${escapeHtml(row.login || '-')}</td>
                        <td>${Number(row.errors) || 0}</td>
                        <td>${escapeHtml(formatDate(row.last_collect_date))}</td>
                        <td>${escapeHtml(formatBool(row.is_active))}</td>
                        <td>
                            <button class="settings-action-small" type="button" data-collector-toggle="${Number(row.id) || 0}" data-active="${Number(row.is_active) ? 0 : 1}">
                                ${Number(row.is_active) ? 'Desativar' : 'Ativar'}
                            </button>
                        </td>
                    </tr>
                `).join('')
                : `<tr><td colspan="8">${collectors.length ? 'Nenhum coletor corresponde ao filtro.' : 'Nenhum coletor encontrado.'}</td></tr>`;

            document.querySelectorAll('[data-collector-toggle]').forEach((button) => {
                button.addEventListener('click', () => toggleCollector(button.dataset.collectorToggle, button.dataset.active));
            });
        }

        async function createCollector() {
            setSettingsStatus('collectorStatus', 'Salvando...', '');
            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('collector_action', 'create');
            form.set('name', document.getElementById('collectorName').value);
            form.set('host', document.getElementById('collectorHost').value);
            form.set('login', document.getElementById('collectorLogin').value);
            form.set('password', document.getElementById('collectorPassword').value);
            form.set('filesize_max', document.getElementById('collectorFilesize').value);
            if (document.getElementById('collectorActive').checked) form.set('is_active', '1');
            if (document.getElementById('collectorUnreadOnly').checked) form.set('collect_only_unread', '1');

            try {
                const response = await fetch('/ajax/mailcollectors.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                collectorState.collectors = data.collectors || [];
                collectorState.summary = data.summary || {};
                renderCollectorsSettings();
                document.getElementById('collectorName').value = '';
                document.getElementById('collectorPassword').value = '';
                setSettingsStatus('collectorStatus', 'Coletor criado no GLPI.', 'ok');
            } catch (error) {
                setSettingsStatus('collectorStatus', error.message || 'Erro ao criar coletor.', 'error');
            }
        }

        async function toggleCollector(id, active) {
            const form = new FormData();
            form.set('csrf_token', settingsCsrfToken);
            form.set('collector_action', 'toggle');
            form.set('id', id);
            if (Number(active) === 1) form.set('is_active', '1');
            try {
                const response = await fetch('/ajax/mailcollectors.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
                const data = await readSettingsJson(response);
                collectorState.collectors = data.collectors || [];
                collectorState.summary = data.summary || {};
                renderCollectorsSettings();
            } catch (error) {
                setSettingsStatus('collectorStatus', error.message || 'Erro ao atualizar coletor.', 'error');
            }
        }

        function updateSlaModePanels() {
            const entityMode = document.getElementById('slaEntityMode').value;
            document.getElementById('slaEntityExistingPanel').classList.toggle('active', entityMode !== 'new');
            document.getElementById('slaEntityNewPanel').classList.toggle('active', entityMode !== 'existing');
            document.getElementById('slaEntityName').placeholder = entityMode === 'edit'
                ? 'Nome da entidade'
                : 'Nome da nova entidade';
            hydrateEntityEditor();

            const calendarMode = document.getElementById('slaCalendarMode').value;
            document.getElementById('slaCalendarExistingPanel').classList.toggle('active', calendarMode !== 'new');
            document.getElementById('slaCalendarNewPanel').classList.toggle('active', calendarMode !== 'existing');
            document.getElementById('slaCalendarName').placeholder = calendarMode === 'edit'
                ? 'Nome do calendário'
                : 'Nome do novo calendário';
            hydrateCalendarEditor();
        }

        function selectedRow(rows, id) {
            const selectedId = String(id || '0');
            return (rows || []).find((row) => String(row.id) === selectedId) || null;
        }

        function hydrateEntityEditor() {
            if (document.getElementById('slaEntityMode').value !== 'edit') return;
            const row = selectedRow(slaEntities, document.getElementById('slaEntityId').value);
            if (!row) return;
            document.getElementById('slaEntityName').value = row.name || '';
            document.getElementById('slaEntityParentId').value = String(row.entities_id || 0);
        }

        function hydrateCalendarEditor() {
            if (document.getElementById('slaCalendarMode').value !== 'edit') return;
            const row = selectedRow(slaCalendars, document.getElementById('slaCalendarId').value);
            if (!row) return;
            document.getElementById('slaCalendarName').value = row.name || '';
            document.getElementById('slaCalendarRecursive').checked = Number(row.is_recursive || 0) === 1;
        }

        function normalizeClockValue(value) {
            const raw = String(value || '').trim();
            if (!raw) return '';

            let digits = raw.replace(/\D/g, '').slice(0, 4);
            if (!digits) return '';
            digits = digits.padStart(4, '0');

            const hour = Math.min(23, Math.max(0, parseInt(digits.slice(0, 2), 10) || 0));
            const minute = Math.min(59, Math.max(0, parseInt(digits.slice(2, 4), 10) || 0));
            return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        }

        function initCalendarTimeControls() {
            document.querySelectorAll('.sla-clock-input').forEach((input) => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/[^\d:]/g, '').slice(0, 5);
                });
                input.addEventListener('blur', () => {
                    input.value = normalizeClockValue(input.value) || input.value;
                });
            });

            document.querySelectorAll('[data-replicate-calendar-day]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = button.closest('.sla-calendar-day');
                    if (!row) return;

                    const sourceBegin = row.querySelector('input[name^="calendar_day_begin"]');
                    const sourceEnd = row.querySelector('input[name^="calendar_day_end"]');
                    const begin = normalizeClockValue(sourceBegin?.value);
                    const end = normalizeClockValue(sourceEnd?.value);
                    if (!begin || !end) return;
                    if (sourceBegin) sourceBegin.value = begin;
                    if (sourceEnd) sourceEnd.value = end;

                    document.querySelectorAll('.sla-calendar-day').forEach((targetRow) => {
                        const targetBegin = targetRow.querySelector('input[name^="calendar_day_begin"]');
                        const targetEnd = targetRow.querySelector('input[name^="calendar_day_end"]');
                        const targetEnabled = targetRow.querySelector('input[name^="calendar_day_enabled"]');
                        if (targetBegin) targetBegin.value = begin;
                        if (targetEnd) targetEnd.value = end;
                        if (targetEnabled) targetEnabled.checked = true;
                    });
                });
            });
        }

        function setSlaStatus(key, status) {
            const labels = {
                created: 'Criado',
                updated: 'Atualizado',
                existing: 'Existente',
                pending: 'Pendente',
                error: 'Erro'
            };
            const pill = document.querySelector(`[data-sla-status="${CSS.escape(key)}"]`);
            if (!pill) return;
            pill.className = 'sla-status-pill ' + (status || 'pending');
            pill.textContent = labels[status] || labels.pending;
        }

        function resetSlaStatuses(status = 'pending') {
            document.querySelectorAll('[data-sla-status]').forEach((pill) => {
                setSlaStatus(pill.dataset.slaStatus, status);
            });
        }

        function applySlaResultStatuses(result) {
            if (!result) return;
            setSlaStatus('entity', result.entity?.status || 'pending');
            setSlaStatus('calendar', result.calendar?.status || 'pending');
            setSlaStatus('slm', result.slm?.status || 'pending');
            (result.slas || []).forEach((sla) => setSlaStatus(sla.key, sla.status || 'pending'));
        }

        function slaKeyParts(key) {
            const match = String(key || '').match(/^(TTO|TTR)-P([1-9][0-9]*)$/);
            return match ? { kind: match[1], number: Number(match[2]) } : null;
        }

        function currentSlaKeys(kind) {
            return Array.from(document.querySelectorAll('input[name^="sla_number["]'))
                .map((input) => {
                    const match = input.name.match(/^sla_number\[(.+)\]$/);
                    return match ? match[1] : '';
                })
                .filter((key) => slaKeyParts(key)?.kind === kind);
        }

        function nextSlaKey(kind) {
            const maxNumber = currentSlaKeys(kind).reduce((max, key) => {
                const parts = slaKeyParts(key);
                return parts ? Math.max(max, parts.number) : max;
            }, 0);
            return `${kind}-P${maxNumber + 1}`;
        }

        function unitOptions(selected = 'hour') {
            return Object.entries({ minute: 'minutos', hour: 'horas', day: 'dias', month: 'meses' })
                .map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`)
                .join('');
        }

        function createSlaRow(kind, key) {
            const row = document.createElement('div');
            row.className = 'sla-list-row';
            row.innerHTML = `
                <div class="sla-row-title">
                    <span>${escapeHtml(key)}</span>
                    <small>${kind === 'TTO' ? 'Tempo para atribuir' : 'Tempo para resolver'}</small>
                </div>
                <div class="sla-time-control">
                    <input class="settings-number" type="number" min="1" max="1000" name="sla_number[${escapeHtml(key)}]" value="1">
                    <select class="settings-select" name="sla_unit[${escapeHtml(key)}]">
                        ${unitOptions('hour')}
                    </select>
                </div>
                <span class="sla-status-pill" data-sla-status="${escapeHtml(key)}">Pendente</span>
            `;
            return row;
        }

        function addDynamicSla(kind) {
            const button = document.querySelector(`[data-add-sla-kind="${kind}"]`);
            if (!button) return;
            const key = nextSlaKey(kind);
            button.parentElement.insertBefore(createSlaRow(kind, key), button);
        }

        function updateSelectOptions(select, rows, selectedId, labelResolver, emptyLabel = null) {
            if (!select) return;
            const current = String(selectedId !== undefined && selectedId !== null ? selectedId : (select.value || '0'));
            select.innerHTML = '';
            if (emptyLabel !== null) {
                const emptyOption = document.createElement('option');
                emptyOption.value = '0';
                emptyOption.textContent = emptyLabel;
                emptyOption.selected = current === '0';
                select.appendChild(emptyOption);
            }
            rows.forEach((row) => {
                const option = document.createElement('option');
                option.value = String(row.id);
                option.textContent = labelResolver(row);
                option.selected = String(row.id) === current;
                select.appendChild(option);
            });
        }

        function updateSlaLists(data) {
            if (!data) return;
            slaEntities = data.entities || [];
            slaCalendars = data.calendars || [];
            updateSelectOptions(
                document.getElementById('slaEntityId'),
                slaEntities,
                data.settings?.entities_id,
                (row) => row.completename || row.name,
                'Entidade raiz'
            );
            updateSelectOptions(
                document.querySelector('select[name="entity_parent_id"]'),
                slaEntities,
                data.settings?.entity_parent_id,
                (row) => row.completename || row.name,
                'Sem entidade pai'
            );
            updateSelectOptions(
                document.getElementById('slaNotificationEntity'),
                slaEntities,
                data.sla_notification?.notification?.entities_id ?? data.settings?.sla_notification?.entities_id ?? 0,
                (row) => row.completename || row.name,
                'Entidade raiz'
            );
            updateSelectOptions(
                document.getElementById('slaCalendarId'),
                slaCalendars,
                data.settings?.calendar_id,
                (row) => row.name,
                'Selecione um calendário'
            );
            hydrateEntityEditor();
            hydrateCalendarEditor();
        }

        async function submitSla(action) {
            const status = document.getElementById('slaStatus');
            const isReapply = action === 'reapply_open_tickets';
            status.className = 'settings-status';
            status.textContent = '';
            if (!isReapply) {
                resetSlaStatuses('pending');
            }
            const entityModeBefore = document.getElementById('slaEntityMode').value;
            const calendarModeBefore = document.getElementById('slaCalendarMode').value;

            const form = new FormData(document.getElementById('settingsForm'));
            form.append('sla_action', action);

            try {
                const response = await fetch('/ajax/sla-simple.php', {
                    method: 'POST',
                    body: form,
                    headers: { 'Accept': 'application/json' }
                });
                const data = await readSettingsJson(response);

                applySlaDataset(data, { hydrateNotification: true });
                if (isReapply) {
                    const tickets = data.result?.tickets || {};
                    status.className = 'settings-status ok';
                    status.textContent = `Reaplicação concluída: ${tickets.applied || 0} chamados atualizados, ${tickets.ignored || 0} ignorados, ${tickets.errors || 0} erros.`;
                    return;
                }

                applySlaResultStatuses(data.result);
                if (action === 'apply') {
                    if (entityModeBefore === 'new') {
                        document.getElementById('slaEntityMode').value = 'existing';
                    }
                    if (calendarModeBefore === 'new') {
                        document.getElementById('slaCalendarMode').value = 'existing';
                    }
                    updateSlaModePanels();
                }
                status.className = 'settings-status ok';
                status.textContent = action === 'apply'
                    ? 'Configuração criada/atualizada no GLPI.'
                    : 'Configuração validada no GLPI.';
            } catch (error) {
                if (!isReapply) {
                    resetSlaStatuses('error');
                }
                status.className = 'settings-status error';
                status.textContent = error.message || 'Erro ao processar SLA.';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            initResponsiveNavigation();
            initSlaSteps();
            initNotificationSteps();
            initSettingsFilters();
            initNotificationTemplateModal();
            initNotificationInfoModal();
            initNotificationConfirmModal();
            initChannelsFaqModal();
            initChannelCredentialModal();
            updateSlaModePanels();
            initCalendarTimeControls();
            document.getElementById('logoLightUrl')?.addEventListener('input', updateLogoPreviews);
            document.getElementById('logoDarkUrl')?.addEventListener('input', updateLogoPreviews);
            document.getElementById('slaEntityMode').addEventListener('change', updateSlaModePanels);
            document.getElementById('slaCalendarMode').addEventListener('change', updateSlaModePanels);
            document.getElementById('slaEntityId').addEventListener('change', hydrateEntityEditor);
            document.getElementById('slaCalendarId').addEventListener('change', hydrateCalendarEditor);
            document.getElementById('validateSla').addEventListener('click', () => submitSla('validate'));
            document.getElementById('applySla').addEventListener('click', () => submitSla('apply'));
            document.getElementById('reapplySla').addEventListener('click', () => submitSla('reapply_open_tickets'));
            document.getElementById('applySlaReminders').addEventListener('click', applySlaReminders);
            document.getElementById('openSlaTemplateEditor').addEventListener('click', openSlaNotificationTemplateEditor);
            document.getElementById('saveSlaNotification').addEventListener('click', saveSlaNotificationFlow);
            document.getElementById('applySlaAutomation').addEventListener('click', applySlaAutomationRecommendation);
            document.getElementById('slaNotificationRecipientSearch').addEventListener('input', renderSlaNotificationRecipientCatalog);
            document.getElementById('addSlaNotificationRecipients').addEventListener('click', () => {
                const select = document.getElementById('slaNotificationRecipientCatalog');
                const selectedValues = Array.from(select.selectedOptions).map((option) => option.value).filter(Boolean);
                slaNotificationRecipients = Array.from(new Set([...slaNotificationRecipients, ...selectedValues]));
                renderSlaNotificationRecipientCatalog();
                renderSelectedSlaNotificationRecipients();
            });
            document.getElementById('clearSlaNotificationRecipients').addEventListener('click', () => {
                slaNotificationRecipients = [];
                renderSlaNotificationRecipientCatalog();
                renderSelectedSlaNotificationRecipients();
            });
            document.querySelectorAll('[data-add-sla-kind]').forEach((button) => {
                button.addEventListener('click', () => addDynamicSla(button.dataset.addSlaKind));
            });
            document.getElementById('notificationItemtype').addEventListener('change', () => {
                syncNotificationFormOptions({ itemtype: document.getElementById('notificationItemtype').value });
            });
            document.getElementById('notificationEvent').addEventListener('change', () => {
                pruneNotificationRecipients();
                renderNotificationRecipientCatalog();
                renderSelectedNotificationRecipients();
            });
            document.getElementById('addNotificationRecipients').addEventListener('click', () => {
                const select = document.getElementById('notificationRecipientCatalog');
                const selectedValues = Array.from(select.selectedOptions).map((option) => option.value).filter(Boolean);
                notificationEditorState.recipients = Array.from(new Set([...notificationEditorState.recipients, ...selectedValues]));
                renderNotificationRecipientCatalog();
                renderSelectedNotificationRecipients();
            });
            document.getElementById('clearNotificationRecipients').addEventListener('click', () => {
                notificationEditorState.recipients = [];
                renderNotificationRecipientCatalog();
                renderSelectedNotificationRecipients();
            });
            document.getElementById('openNotificationCreate').addEventListener('click', () => showNotificationEditor());
            document.getElementById('backToNotificationList').addEventListener('click', showNotificationListView);
            document.getElementById('saveNotification').addEventListener('click', saveNotification);
            document.getElementById('resetNotificationForm').addEventListener('click', () => resetNotificationForm());
            document.getElementById('notificationSelectVisible').addEventListener('click', selectVisibleNotifications);
            document.getElementById('notificationDisableSelected').addEventListener('click', bulkDisableSelectedNotifications);
            document.getElementById('notificationSelectAll').addEventListener('change', () => {
                const visibleCheckboxes = Array.from(document.querySelectorAll('[data-notification-row-id]'));
                visibleCheckboxes.forEach((checkbox) => {
                    const id = Number(checkbox.dataset.notificationRowId) || 0;
                    if (document.getElementById('notificationSelectAll').checked) {
                        notificationSelection.add(id);
                    } else {
                        notificationSelection.delete(id);
                    }
                });
                renderNotificationTables();
            });
            document.getElementById('saveNotificationTemplate').addEventListener('click', saveNotificationTemplateTranslation);
            document.getElementById('saveNotificationMail').addEventListener('click', saveNotificationMailSettings);
            document.getElementById('reloadNotificationMail').addEventListener('click', reloadNotificationMailSettings);
            document.getElementById('sendNotificationTestEmail').addEventListener('click', sendNotificationTestEmail);
            document.getElementById('mailSmtpMode').addEventListener('change', syncMailPanels);
            document.getElementById('mailSmtpUsername').addEventListener('blur', () => {
                applyNotificationMailSenderFromLoginIfEmpty();
                updateNotificationMailDirtyState();
            });
            document.getElementById('mailSmtpOauthProvider').addEventListener('change', () => {
                renderOauthAdditionalFields();
                updateNotificationMailDirtyState();
            });
            bindNotificationMailDirtyListeners();
            document.getElementById('applyQueuedNotificationRecommendation').addEventListener('click', applyQueuedNotificationRecommendation);
            document.getElementById('bulkDisableNotifications').addEventListener('click', bulkDisableNotifications);
            document.getElementById('cleanupSelectAll').addEventListener('change', () => {
                const visibleCheckboxes = Array.from(document.querySelectorAll('[data-cleanup-id]'));
                visibleCheckboxes.forEach((checkbox) => {
                    const id = Number(checkbox.dataset.cleanupId) || 0;
                    if (document.getElementById('cleanupSelectAll').checked) {
                        cleanupSelection.add(id);
                    } else {
                        cleanupSelection.delete(id);
                    }
                });
                renderCleanupTable();
            });
            document.getElementById('toggleCollectorCreate').addEventListener('click', () => {
                document.getElementById('collectorCreatePanel').classList.toggle('active');
            });
            document.getElementById('createCollector').addEventListener('click', createCollector);
            document.getElementById('profileAccessSelect')?.addEventListener('change', (event) => {
                syncCurrentProfileAccessRowToState();
                profileAccessState.selectedProfileId = Number(event.target.value) || 0;
                renderProfileAccessSummary();
                renderProfileAccessCurrent();
                renderProfileAccessTable();
                setSettingsStatus('profileAccessStatus', '', '');
            });
            document.getElementById('reloadProfileAccess')?.addEventListener('click', () => loadProfileAccess(true));
            document.getElementById('saveProfileAccess')?.addEventListener('click', saveProfileAccess);
            if (activeSettingsSection === 'sla') {
                loadSlaFlow();
            }
            if (activeSettingsSection === 'profile_access') {
                loadProfileAccess();
            }
            if (activeSettingsSection === 'notifications') {
                loadNotifications();
            }
            if (activeSettingsSection === 'recipients') {
                loadCollectors();
            }
        });

        document.getElementById('saveSettings')?.addEventListener('click', async () => {
            const status = document.getElementById('settingsStatus');
            status.className = 'settings-status';
            status.textContent = '';

            try {
                const response = await fetch('/ajax/settings.php', {
                    method: 'POST',
                    body: new FormData(document.getElementById('settingsForm')),
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Falha ao salvar configuracoes.');
                }

                if (data.settings && data.settings.logo_light_url) {
                    document.getElementById('logoLightUrl').value = data.settings.logo_light_url;
                    updateLogoPreviews();
                }
                if (data.settings && data.settings.logo_dark_url) {
                    document.getElementById('logoDarkUrl').value = data.settings.logo_dark_url;
                    updateLogoPreviews();
                }
                if (data.settings && data.settings.app_name) {
                    document.getElementById('appName').value = data.settings.app_name;
                }

                status.className = 'settings-status ok';
                status.textContent = 'Configurações salvas.';
            } catch (error) {
                status.className = 'settings-status error';
                status.textContent = error.message || 'Erro ao salvar.';
            }
        });
    </script>
</body>
</html>
