<?php

const DASHGLPI_SQL_CONSOLE_LOG_TABLE = 'glpi_plugin_dashglpi_sql_console_logs';
const DASHGLPI_SQL_CONSOLE_DEFAULT_LIMIT = 100;
const DASHGLPI_SQL_CONSOLE_MAX_LIMIT = 500;
const DASHGLPI_SQL_CONSOLE_HISTORY_LIMIT = 15;
const DASHGLPI_SQL_CONSOLE_QUERY_MAX_LENGTH = 20000;
const DASHGLPI_SQL_CONSOLE_CELL_MAX_LENGTH = 4000;
const DASHGLPI_SQL_CONSOLE_LOG_SQL_MAX_LENGTH = 64000;
const DASHGLPI_SQL_CONSOLE_TIMEOUT_MS = 5000;
const DASHGLPI_SQL_CONSOLE_WRITE_FLAG_ENV = 'DASHGLPI_SQL_WRITE_ENABLED';
const DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY = 'dashglpi_sql_console_write_previews';
const DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_TTL_SECONDS = 900;

function dashglpi_sql_console_limit_options(): array
{
    return [25, 50, 100, 200, 500];
}

function dashglpi_sql_console_read_statement_types(): array
{
    return ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'WITH'];
}

function dashglpi_sql_console_write_statement_types(): array
{
    return ['UPDATE', 'INSERT', 'DELETE'];
}

function dashglpi_sql_console_write_enabled(): bool
{
    $raw = strtolower(trim((string) dashglpi_env(DASHGLPI_SQL_CONSOLE_WRITE_FLAG_ENV, 'false')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function dashglpi_sql_console_glpi_cache_command(): string
{
    return 'rm -rf /var/glpi/files/_cache/*';
}

function dashglpi_sql_console_glpi_cache_bridge_available(): bool
{
    $token = trim((string) dashglpi_env('DASHGLPI_BRIDGE_TOKEN', ''));
    if ($token === '') {
        return false;
    }

    $baseUrl = trim((string) dashglpi_env('DASHGLPI_BRIDGE_BASE_URL', ''));
    if ($baseUrl !== '') {
        return true;
    }

    $bridgeUrl = trim((string) dashglpi_env('DASHGLPI_BRIDGE_URL', ''));
    return $bridgeUrl !== '';
}

function dashglpi_sql_console_confirmation_phrase(string $statementType): string
{
    $statementType = strtoupper(trim($statementType));
    if ($statementType === '') {
        $statementType = 'ALTERACAO';
    }

    return 'CONFIRMAR ' . $statementType;
}

function dashglpi_sql_console_rules(): array
{
    $rules = [
        'Somente uma consulta por execucao. Comentarios e multiplos comandos continuam bloqueados.',
        'Consultas de leitura aceitas: SELECT, SHOW, DESCRIBE, DESC, EXPLAIN e WITH somente leitura.',
        'Toda execucao fica auditada com usuario, SQL, duracao, status e resultado.',
    ];

    if (dashglpi_sql_console_glpi_cache_bridge_available()) {
        $rules[] = 'A acao "Limpar cache GLPI" remove o conteudo de /var/glpi/files/_cache com confirmacao manual.';
    }

    if (!dashglpi_sql_console_write_enabled()) {
        $rules[] = 'UPDATE, INSERT e DELETE ficam bloqueados ate habilitar a flag ' . DASHGLPI_SQL_CONSOLE_WRITE_FLAG_ENV . '.';
        return $rules;
    }

    $rules[] = 'Modo de escrita habilitado: apenas UPDATE, INSERT e DELETE com protecao reforcada.';
    $rules[] = 'UPDATE e DELETE exigem WHERE obrigatorio, previa obrigatoria e confirmacao digitada.';

    return $rules;
}

function dashglpi_sql_console_dataset(): array
{
    dashglpi_sql_console_ensure_log_table();

    return [
        'ok' => true,
        'current_database' => (string) dashglpi_env('GLPI_DB_NAME', ''),
        'default_limit' => DASHGLPI_SQL_CONSOLE_DEFAULT_LIMIT,
        'limit_options' => dashglpi_sql_console_limit_options(),
        'write_mode_enabled' => dashglpi_sql_console_write_enabled(),
        'write_flag_env' => DASHGLPI_SQL_CONSOLE_WRITE_FLAG_ENV,
        'write_preview_ttl_seconds' => DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_TTL_SECONDS,
        'write_statement_types' => dashglpi_sql_console_write_statement_types(),
        'presets' => dashglpi_sql_console_presets(),
        'history' => dashglpi_sql_console_history(),
        'rules' => dashglpi_sql_console_rules(),
        'maintenance' => [
            'clear_glpi_cache_available' => dashglpi_sql_console_glpi_cache_bridge_available(),
            'clear_glpi_cache_command' => dashglpi_sql_console_glpi_cache_command(),
            'optimize_heavy_tables_command' => dashglpi_sql_console_optimize_heavy_tables_command(),
        ],
        'diagnostics' => dashglpi_sql_console_diagnostics(),
    ];
}

function dashglpi_sql_console_optimize_heavy_tables_command(): string
{
    return 'OPTIMIZE TABLE glpi_logs, glpi_tickets, glpi_queuednotifications; '
        . 'DELETE FROM glpi_logs WHERE date_mod < (NOW() - INTERVAL 90 DAY);';
}

function dashglpi_sql_console_optimize_heavy_tables(): array
{
    $pdo = dashglpi_db();
    $startedAt = microtime(true);
    $tables = ['glpi_logs', 'glpi_tickets', 'glpi_queuednotifications'];
    $removedLogs = 0;

    try {
        $optimizeStmt = $pdo->query('OPTIMIZE TABLE ' . implode(', ', $tables));
        if ($optimizeStmt instanceof PDOStatement) {
            $optimizeStmt->closeCursor();
        }

        $stmt = $pdo->prepare('DELETE FROM glpi_logs WHERE date_mod < (NOW() - INTERVAL 90 DAY)');
        $stmt->execute();
        $removedLogs = $stmt->rowCount();
    } catch (Throwable $e) {
        dashglpi_sql_console_log([
            'sql' => dashglpi_sql_console_optimize_heavy_tables_command(),
            'status' => 'error',
            'rows_returned' => 0,
            'affected_rows' => 0,
            'rows_limited' => 0,
            'duration_ms' => dashglpi_sql_console_duration_ms($startedAt),
            'error_message' => $e->getMessage(),
            'statement_type' => 'MAINTENANCE',
            'execution_mode' => 'write',
            'request_phase' => 'execute',
            'target_tables' => $tables,
        ]);

        throw new RuntimeException('Falha ao otimizar tabelas pesadas: ' . $e->getMessage());
    }

    $durationMs = dashglpi_sql_console_duration_ms($startedAt);

    dashglpi_sql_console_log([
        'sql' => dashglpi_sql_console_optimize_heavy_tables_command(),
        'status' => 'success',
        'rows_returned' => 0,
        'affected_rows' => $removedLogs,
        'rows_limited' => 0,
        'duration_ms' => $durationMs,
        'error_message' => '',
        'statement_type' => 'MAINTENANCE',
        'execution_mode' => 'write',
        'request_phase' => 'execute',
        'target_tables' => $tables,
    ]);

    return [
        'ok' => true,
        'message' => 'Tabelas pesadas otimizadas com sucesso.',
        'tables' => $tables,
        'removed_logs' => $removedLogs,
        'duration_ms' => $durationMs,
        'executed_at' => date('Y-m-d H:i:s'),
        'history' => dashglpi_sql_console_history(),
    ];
}

function dashglpi_sql_console_diagnostics(): array
{
    return [
        'timezone_named_support' => dashglpi_sql_console_timezone_named_support(),
    ];
}

function dashglpi_sql_console_timezone_named_support(): bool
{
    try {
        $row = dashglpi_fetch_one("SELECT CONVERT_TZ(NOW(), 'UTC', 'America/Sao_Paulo') AS tz_test");
        return $row !== null && $row['tz_test'] !== null;
    } catch (Throwable $e) {
        return false;
    }
}

function dashglpi_sql_console_clear_glpi_cache(): array
{
    if (!dashglpi_sql_console_glpi_cache_bridge_available()) {
        throw new RuntimeException('Bridge do GLPI nao configurado para limpar o cache.');
    }

    require_once __DIR__ . '/sla_simple.php';

    $result = dashglpi_sla_bridge_request('clear_glpi_cache', []);

    return [
        'ok' => true,
        'message' => (string) ($result['message'] ?? 'Cache do GLPI limpo com sucesso.'),
        'cache_path' => (string) ($result['cache_path'] ?? '/var/glpi/files/_cache'),
        'removed_entries' => max(0, (int) ($result['removed_entries'] ?? 0)),
        'cleared_at' => (string) ($result['cleared_at'] ?? date('Y-m-d H:i:s')),
        'command' => (string) ($result['command'] ?? dashglpi_sql_console_glpi_cache_command()),
    ];
}

function dashglpi_sql_console_presets(): array
{
    return [
        [
            'id' => 'tickets-status',
            'label' => 'Chamados por status',
            'description' => 'Resumo rapido da fila atual do GLPI.',
            'sql' => "SELECT status, COUNT(*) AS total\nFROM glpi_tickets\nWHERE is_deleted = 0\nGROUP BY status\nORDER BY total DESC;",
        ],
        [
            'id' => 'recent-tickets',
            'label' => 'Ultimos chamados',
            'description' => 'Ultimos 20 chamados criados.',
            'sql' => "SELECT id, name, status, date\nFROM glpi_tickets\nWHERE is_deleted = 0\nORDER BY date DESC\nLIMIT 20;",
        ],
        [
            'id' => 'top-categories',
            'label' => 'Top categorias',
            'description' => 'Categorias com maior volume de tickets.',
            'sql' => "SELECT c.completename AS categoria, COUNT(*) AS total\nFROM glpi_tickets t\nLEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id\nWHERE t.is_deleted = 0\nGROUP BY c.completename\nORDER BY total DESC\nLIMIT 15;",
        ],
        [
            'id' => 'active-users',
            'label' => 'Usuarios ativos',
            'description' => 'Lista de usuarios ativos cadastrados.',
            'sql' => "SELECT id, name, firstname, realname, email\nFROM glpi_users\nWHERE is_active = 1 AND is_deleted = 0\nORDER BY name ASC\nLIMIT 50;",
        ],
        [
            'id' => 'queue-notifications',
            'label' => 'Verificar Fila de E-mails',
            'description' => 'Notificacoes pendentes de envio na fila.',
            'sql' => "SELECT COUNT(*) AS pendentes\nFROM glpi_queuednotifications\nWHERE mode = 'mailing'\n  AND is_deleted = 0\n  AND sent_time IS NULL;",
        ],
    ];
}

function dashglpi_sql_console_history(int $limit = DASHGLPI_SQL_CONSOLE_HISTORY_LIMIT): array
{
    dashglpi_sql_console_ensure_log_table();

    $limit = max(1, min(50, $limit));

    return dashglpi_fetch_all(
        "SELECT id,
                users_id,
                user_name,
                statement_type,
                execution_mode,
                request_phase,
                statement_hash,
                statement_preview,
                sql_text,
                rows_returned,
                affected_rows,
                rows_limited,
                duration_ms,
                status,
                error_message,
                target_tables,
                executed_at
         FROM " . DASHGLPI_SQL_CONSOLE_LOG_TABLE . "
         WHERE request_phase = 'execute'
         ORDER BY executed_at DESC, id DESC
         LIMIT $limit"
    );
}

function dashglpi_sql_console_prepare_write_preview(string $sql): array
{
    dashglpi_sql_console_ensure_log_table();

    $sql = dashglpi_sql_console_normalize_input($sql);
    $inspection = dashglpi_sql_console_inspect_query($sql);
    if (($inspection['execution_mode'] ?? '') !== 'write') {
        throw new InvalidArgumentException('A previa de escrita e aceita apenas para UPDATE, INSERT ou DELETE.');
    }

    dashglpi_sql_console_cleanup_write_previews();
    dashglpi_start_session();

    $token = bin2hex(random_bytes(16));
    $_SESSION[DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY][$token] = [
        'sql_hash' => hash('sha256', $sql),
        'statement_type' => (string) ($inspection['statement_type'] ?? ''),
        'created_at' => time(),
        'expires_at' => time() + DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_TTL_SECONDS,
    ];

    dashglpi_sql_console_log([
        'sql' => $sql,
        'status' => 'preview',
        'rows_returned' => 0,
        'affected_rows' => 0,
        'rows_limited' => 0,
        'duration_ms' => 0,
        'error_message' => '',
        'statement_type' => (string) ($inspection['statement_type'] ?? ''),
        'execution_mode' => 'write',
        'request_phase' => 'preview',
        'target_tables' => $inspection['target_tables'] ?? [],
    ]);

    return [
        'ok' => true,
        'preview' => [
            'token' => $token,
            'statement_type' => (string) ($inspection['statement_type'] ?? ''),
            'statement_preview' => (string) ($inspection['statement_preview'] ?? ''),
            'target_tables' => array_values($inspection['target_tables'] ?? []),
            'confirmation_phrase' => dashglpi_sql_console_confirmation_phrase((string) ($inspection['statement_type'] ?? 'ALTERACAO')),
            'expires_in_seconds' => DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_TTL_SECONDS,
            'warnings' => dashglpi_sql_console_write_warnings($inspection),
        ],
    ];
}

function dashglpi_sql_console_execute(string $sql, int $limit, array $options = []): array
{
    dashglpi_sql_console_ensure_log_table();

    $sql = dashglpi_sql_console_normalize_input($sql);
    $startedAt = microtime(true);
    $inspection = null;

    try {
        $inspection = dashglpi_sql_console_inspect_query($sql);
        $executionMode = (string) ($inspection['execution_mode'] ?? 'read');

        if ($executionMode === 'write') {
            $previewToken = dashglpi_sql_console_assert_write_confirmation($sql, $inspection, $options);
            $result = dashglpi_sql_console_execute_write($sql, $inspection, $startedAt);
            dashglpi_sql_console_consume_write_preview($previewToken);
            return $result;
        }

        $limit = dashglpi_sql_console_normalize_limit($limit);
        return dashglpi_sql_console_execute_read($sql, $limit, $inspection, $startedAt);
    } catch (Throwable $e) {
        dashglpi_sql_console_log([
            'sql' => $sql,
            'status' => $e instanceof InvalidArgumentException ? 'blocked' : 'error',
            'rows_returned' => 0,
            'affected_rows' => 0,
            'rows_limited' => 0,
            'duration_ms' => dashglpi_sql_console_duration_ms($startedAt),
            'error_message' => $e->getMessage(),
            'statement_type' => (string) ($inspection['statement_type'] ?? ''),
            'execution_mode' => (string) ($inspection['execution_mode'] ?? 'read'),
            'request_phase' => 'execute',
            'target_tables' => $inspection['target_tables'] ?? [],
        ]);

        throw $e;
    }
}

function dashglpi_sql_console_execute_read(string $sql, int $limit, array $inspection, float $startedAt): array
{
    $pdo = dashglpi_db();
    dashglpi_sql_console_apply_timeout($pdo);

    $statement = $pdo->query($sql);
    if (!$statement instanceof PDOStatement) {
        throw new RuntimeException('Nao foi possivel executar a consulta SQL.');
    }

    $columns = dashglpi_sql_console_columns($statement);
    $rows = [];
    $truncated = false;

    while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
        if (count($rows) >= $limit) {
            $truncated = true;
            break;
        }

        $rows[] = array_map('dashglpi_sql_console_normalize_cell', $row);
    }

    $durationMs = dashglpi_sql_console_duration_ms($startedAt);
    $rowsReturned = count($rows);

    dashglpi_sql_console_log([
        'sql' => $sql,
        'status' => 'success',
        'rows_returned' => $rowsReturned,
        'affected_rows' => 0,
        'rows_limited' => $truncated ? 1 : 0,
        'duration_ms' => $durationMs,
        'error_message' => '',
        'statement_type' => (string) ($inspection['statement_type'] ?? ''),
        'execution_mode' => 'read',
        'request_phase' => 'execute',
        'target_tables' => $inspection['target_tables'] ?? [],
    ]);

    return [
        'ok' => true,
        'query' => [
            'statement_type' => (string) ($inspection['statement_type'] ?? ''),
            'preview' => (string) ($inspection['statement_preview'] ?? dashglpi_sql_console_statement_preview($sql)),
        ],
        'meta' => [
            'limit' => $limit,
            'rows_returned' => $rowsReturned,
            'affected_rows' => 0,
            'rows_limited' => $truncated,
            'duration_ms' => $durationMs,
            'executed_at' => date('Y-m-d H:i:s'),
            'execution_mode' => 'read',
        ],
        'columns' => $columns,
        'rows' => $rows,
        'history' => dashglpi_sql_console_history(),
    ];
}

function dashglpi_sql_console_execute_write(string $sql, array $inspection, float $startedAt): array
{
    $pdo = dashglpi_db();
    dashglpi_sql_console_apply_timeout($pdo);

    $affectedRows = 0;

    try {
        $pdo->beginTransaction();
        $affectedRows = $pdo->exec($sql);

        if ($affectedRows === false) {
            throw new RuntimeException('Nao foi possivel aplicar a alteracao SQL.');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $durationMs = dashglpi_sql_console_duration_ms($startedAt);
    $targetTables = array_values($inspection['target_tables'] ?? []);

    dashglpi_sql_console_log([
        'sql' => $sql,
        'status' => 'success',
        'rows_returned' => 0,
        'affected_rows' => $affectedRows,
        'rows_limited' => 0,
        'duration_ms' => $durationMs,
        'error_message' => '',
        'statement_type' => (string) ($inspection['statement_type'] ?? ''),
        'execution_mode' => 'write',
        'request_phase' => 'execute',
        'target_tables' => $targetTables,
    ]);

    return [
        'ok' => true,
        'query' => [
            'statement_type' => (string) ($inspection['statement_type'] ?? ''),
            'preview' => (string) ($inspection['statement_preview'] ?? dashglpi_sql_console_statement_preview($sql)),
        ],
        'meta' => [
            'limit' => 0,
            'rows_returned' => 0,
            'affected_rows' => $affectedRows,
            'rows_limited' => false,
            'duration_ms' => $durationMs,
            'executed_at' => date('Y-m-d H:i:s'),
            'execution_mode' => 'write',
        ],
        'columns' => [],
        'rows' => [],
        'mutation' => [
            'statement_type' => (string) ($inspection['statement_type'] ?? ''),
            'target_tables' => $targetTables,
            'affected_rows' => $affectedRows,
        ],
        'history' => dashglpi_sql_console_history(),
    ];
}

function dashglpi_sql_console_ensure_log_table(): void
{
    static $created = false;

    if ($created) {
        return;
    }

    dashglpi_db()->exec(
        "CREATE TABLE IF NOT EXISTS " . DASHGLPI_SQL_CONSOLE_LOG_TABLE . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            users_id INT UNSIGNED NOT NULL DEFAULT 0,
            user_name VARCHAR(255) NOT NULL DEFAULT '',
            statement_type VARCHAR(32) NOT NULL DEFAULT '',
            execution_mode VARCHAR(16) NOT NULL DEFAULT 'read',
            request_phase VARCHAR(16) NOT NULL DEFAULT 'execute',
            statement_hash CHAR(64) NOT NULL,
            statement_preview VARCHAR(255) NOT NULL,
            sql_text MEDIUMTEXT NOT NULL,
            target_tables VARCHAR(255) NOT NULL DEFAULT '',
            rows_returned INT UNSIGNED NOT NULL DEFAULT 0,
            affected_rows INT UNSIGNED NOT NULL DEFAULT 0,
            rows_limited TINYINT(1) NOT NULL DEFAULT 0,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'success',
            error_message VARCHAR(1000) NOT NULL DEFAULT '',
            executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_dashglpi_sql_console_executed (executed_at),
            KEY idx_dashglpi_sql_console_user (users_id, executed_at),
            KEY idx_dashglpi_sql_console_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    dashglpi_sql_console_ensure_log_columns();
    $created = true;
}

function dashglpi_sql_console_ensure_log_columns(): void
{
    static $columnsEnsured = false;

    if ($columnsEnsured) {
        return;
    }

    $table = DASHGLPI_SQL_CONSOLE_LOG_TABLE;
    $columns = [
        'statement_type' => "ALTER TABLE $table ADD COLUMN statement_type VARCHAR(32) NOT NULL DEFAULT '' AFTER user_name",
        'execution_mode' => "ALTER TABLE $table ADD COLUMN execution_mode VARCHAR(16) NOT NULL DEFAULT 'read' AFTER statement_type",
        'request_phase' => "ALTER TABLE $table ADD COLUMN request_phase VARCHAR(16) NOT NULL DEFAULT 'execute' AFTER execution_mode",
        'target_tables' => "ALTER TABLE $table ADD COLUMN target_tables VARCHAR(255) NOT NULL DEFAULT '' AFTER sql_text",
        'affected_rows' => "ALTER TABLE $table ADD COLUMN affected_rows INT UNSIGNED NOT NULL DEFAULT 0 AFTER rows_returned",
    ];

    foreach ($columns as $columnName => $alterSql) {
        if (!dashglpi_sql_console_log_table_has_column($columnName)) {
            dashglpi_db()->exec($alterSql);
        }
    }

    $columnsEnsured = true;
}

function dashglpi_sql_console_log_table_has_column(string $columnName): bool
{
    $pdo = dashglpi_db();
    $stmt = $pdo->query(
        "SHOW COLUMNS FROM " . DASHGLPI_SQL_CONSOLE_LOG_TABLE . " LIKE " . $pdo->quote($columnName)
    );

    return (bool) $stmt->fetch();
}

function dashglpi_sql_console_normalize_input(string $sql): string
{
    $sql = str_replace("\r\n", "\n", trim($sql));
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;

    if ($sql === '') {
        throw new InvalidArgumentException('Informe uma consulta SQL para executar.');
    }

    if (strlen($sql) > DASHGLPI_SQL_CONSOLE_QUERY_MAX_LENGTH) {
        throw new InvalidArgumentException('A consulta excede o limite de caracteres permitido.');
    }

    return $sql;
}

function dashglpi_sql_console_normalize_limit(int $limit): int
{
    $allowed = dashglpi_sql_console_limit_options();
    if (in_array($limit, $allowed, true)) {
        return $limit;
    }

    return DASHGLPI_SQL_CONSOLE_DEFAULT_LIMIT;
}

function dashglpi_sql_console_inspect_query(string $sql): array
{
    $analysis = dashglpi_sql_console_analyze_sql($sql);
    $firstToken = (string) ($analysis['first_token'] ?? '');
    $flatSql = (string) ($analysis['flat_sql'] ?? '');

    if (!empty($analysis['has_multiple_statements'])) {
        throw new InvalidArgumentException('Somente uma consulta por execucao e permitida.');
    }

    if ($firstToken === '') {
        throw new InvalidArgumentException('Nao foi possivel identificar a instrucao SQL.');
    }

    if (in_array($firstToken, dashglpi_sql_console_read_statement_types(), true)) {
        dashglpi_sql_console_validate_read_query($flatSql, $firstToken);

        return [
            'statement_type' => $firstToken,
            'execution_mode' => 'read',
            'statement_preview' => dashglpi_sql_console_statement_preview($sql),
            'target_tables' => [],
        ];
    }

    if (in_array($firstToken, dashglpi_sql_console_write_statement_types(), true)) {
        if (!dashglpi_sql_console_write_enabled()) {
            throw new InvalidArgumentException('Modo de escrita desabilitado. Habilite a flag ' . DASHGLPI_SQL_CONSOLE_WRITE_FLAG_ENV . ' para liberar UPDATE, INSERT e DELETE.');
        }

        $targetTables = dashglpi_sql_console_extract_target_tables($sql, $firstToken);
        dashglpi_sql_console_validate_write_query($flatSql, $firstToken, $targetTables);

        return [
            'statement_type' => $firstToken,
            'execution_mode' => 'write',
            'statement_preview' => dashglpi_sql_console_statement_preview($sql),
            'target_tables' => $targetTables,
        ];
    }

    throw new InvalidArgumentException('Somente consultas de leitura ou escrita controlada sao permitidas neste console.');
}

function dashglpi_sql_console_validate_read_query(string $flatSql, string $firstToken): void
{
    $effectiveSql = $flatSql;
    $effectiveToken = $firstToken;

    if ($firstToken === 'EXPLAIN') {
        if (!preg_match('/\b(SELECT|SHOW|DESCRIBE|DESC|WITH)\b/', $flatSql, $matches, PREG_OFFSET_CAPTURE)) {
            throw new InvalidArgumentException('Use EXPLAIN apenas sobre consultas de leitura suportadas.');
        }

        $effectiveToken = $matches[1][0];
        $effectiveSql = substr($flatSql, (int) $matches[1][1]);
    }

    if (in_array($effectiveToken, ['SHOW', 'DESCRIBE', 'DESC'], true)) {
        dashglpi_sql_console_assert_safe_patterns($effectiveSql, [
            '/\bINTO\s+(OUTFILE|DUMPFILE)\b/',
            '/\bLOAD_FILE\s*\(/',
            '/\bSLEEP\s*\(/',
            '/\bBENCHMARK\s*\(/',
        ]);
        return;
    }

    if ($effectiveToken === 'WITH' && !preg_match('/\bSELECT\b/', $effectiveSql)) {
        throw new InvalidArgumentException('Consultas WITH devem resultar em SELECT somente leitura.');
    }

    dashglpi_sql_console_assert_safe_patterns($effectiveSql, [
        '/\bINSERT\b/',
        '/\bUPDATE\b/',
        '/\bDELETE\b/',
        '/\bDROP\b/',
        '/\bALTER\b/',
        '/\bTRUNCATE\b/',
        '/\bREPLACE\b/',
        '/\bMERGE\b/',
        '/\bCALL\b/',
        '/\bDO\b/',
        '/\bHANDLER\b/',
        '/\bLOAD\s+DATA\b/',
        '/\bLOCK\s+TABLES\b/',
        '/\bUNLOCK\s+TABLES\b/',
        '/\bGRANT\b/',
        '/\bREVOKE\b/',
        '/\bUSE\b/',
        '/\bPREPARE\b/',
        '/\bEXECUTE\b/',
        '/\bDEALLOCATE\b/',
        '/\bKILL\b/',
        '/\bFLUSH\b/',
        '/\bRESET\b/',
        '/\bINSTALL\b/',
        '/\bUNINSTALL\b/',
        '/\bSTART\s+TRANSACTION\b/',
        '/\bCOMMIT\b/',
        '/\bROLLBACK\b/',
        '/\bSAVEPOINT\b/',
        '/\bRELEASE\s+SAVEPOINT\b/',
        '/\bSHUTDOWN\b/',
        '/\bINTO\s+(OUTFILE|DUMPFILE)\b/',
        '/\bINTO\s+@/',
        '/\bLOAD_FILE\s*\(/',
        '/\bSLEEP\s*\(/',
        '/\bBENCHMARK\s*\(/',
        '/\bFOR\s+UPDATE\b/',
        '/\bLOCK\s+IN\s+SHARE\s+MODE\b/',
    ]);
}

function dashglpi_sql_console_validate_write_query(string $flatSql, string $statementType, array $targetTables): void
{
    dashglpi_sql_console_assert_safe_patterns($flatSql, [
        '/\bDROP\b/',
        '/\bALTER\b/',
        '/\bTRUNCATE\b/',
        '/\bREPLACE\b/',
        '/\bMERGE\b/',
        '/\bCALL\b/',
        '/\bDO\b/',
        '/\bHANDLER\b/',
        '/\bLOAD\s+DATA\b/',
        '/\bLOCK\s+TABLES\b/',
        '/\bUNLOCK\s+TABLES\b/',
        '/\bGRANT\b/',
        '/\bREVOKE\b/',
        '/\bUSE\b/',
        '/\bPREPARE\b/',
        '/\bEXECUTE\b/',
        '/\bDEALLOCATE\b/',
        '/\bKILL\b/',
        '/\bFLUSH\b/',
        '/\bRESET\b/',
        '/\bINSTALL\b/',
        '/\bUNINSTALL\b/',
        '/\bSTART\s+TRANSACTION\b/',
        '/\bCOMMIT\b/',
        '/\bROLLBACK\b/',
        '/\bSAVEPOINT\b/',
        '/\bRELEASE\s+SAVEPOINT\b/',
        '/\bSHUTDOWN\b/',
        '/\bINTO\s+(OUTFILE|DUMPFILE)\b/',
        '/\bINTO\s+@/',
        '/\bLOAD_FILE\s*\(/',
        '/\bSLEEP\s*\(/',
        '/\bBENCHMARK\s*\(/',
    ]);

    if (in_array($statementType, ['UPDATE', 'DELETE'], true) && !preg_match('/\bWHERE\b/', $flatSql)) {
        throw new InvalidArgumentException($statementType . ' exige WHERE obrigatorio neste console.');
    }

    dashglpi_sql_console_assert_writable_tables($targetTables);
}

function dashglpi_sql_console_extract_target_tables(string $sql, string $statementType): array
{
    $compactSql = trim(preg_replace('/\s+/', ' ', $sql) ?? '');
    $patterns = [
        'UPDATE' => '/^UPDATE\s+`?([a-zA-Z0-9_.]+)`?/i',
        'INSERT' => '/^INSERT(?:\s+INTO)?\s+`?([a-zA-Z0-9_.]+)`?/i',
        'DELETE' => '/^DELETE(?:\s+[a-zA-Z0-9_,`\s]+)?\s+FROM\s+`?([a-zA-Z0-9_.]+)`?/i',
    ];

    $pattern = $patterns[$statementType] ?? null;
    if (!$pattern) {
        return [];
    }

    if (!preg_match($pattern, $compactSql, $matches)) {
        return [];
    }

    $table = trim((string) ($matches[1] ?? ''));
    if ($table === '') {
        return [];
    }

    return [$table];
}

function dashglpi_sql_console_assert_writable_tables(array $targetTables): void
{
    if (!$targetTables) {
        return;
    }

    $protectedTables = [
        strtolower(DASHGLPI_SQL_CONSOLE_LOG_TABLE),
        'glpi_plugin_dashglpi_settings',
    ];

    foreach ($targetTables as $table) {
        if (in_array(strtolower((string) $table), $protectedTables, true)) {
            throw new InvalidArgumentException('Este console nao permite escrita em tabelas internas de controle do DashGLPI.');
        }
    }
}

function dashglpi_sql_console_write_warnings(array $inspection): array
{
    $statementType = (string) ($inspection['statement_type'] ?? 'ALTERACAO');
    $tables = array_values($inspection['target_tables'] ?? []);
    $tableLabel = $tables ? implode(', ', $tables) : 'tabela alvo nao identificada';

    return [
        'Operacao detectada: ' . $statementType . '.',
        'Alvo principal: ' . $tableLabel . '.',
        'Confirme a frase exibida e revise a query antes de executar.',
    ];
}

function dashglpi_sql_console_cleanup_write_previews(): void
{
    dashglpi_start_session();
    $items = is_array($_SESSION[DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY] ?? null)
        ? $_SESSION[DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY]
        : [];
    $now = time();

    foreach ($items as $token => $preview) {
        $expiresAt = (int) ($preview['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt >= $now) {
            continue;
        }

        unset($items[$token]);
    }

    $_SESSION[DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY] = $items;
}

function dashglpi_sql_console_assert_write_confirmation(string $sql, array $inspection, array $options): string
{
    if (empty($options['confirm_write'])) {
        throw new InvalidArgumentException('Gerar previa e confirmacao explicita sao obrigatorios antes de executar escrita.');
    }

    $previewToken = trim((string) ($options['preview_token'] ?? ''));
    if ($previewToken === '') {
        throw new InvalidArgumentException('Token da previa de escrita nao informado.');
    }

    dashglpi_sql_console_cleanup_write_previews();
    dashglpi_start_session();

    $previews = is_array($_SESSION[DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY] ?? null)
        ? $_SESSION[DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY]
        : [];
    $preview = $previews[$previewToken] ?? null;

    if (!is_array($preview)) {
        throw new InvalidArgumentException('A previa expirou ou nao foi encontrada. Gere uma nova previa antes de executar.');
    }

    $sqlHash = hash('sha256', $sql);
    if (!hash_equals((string) ($preview['sql_hash'] ?? ''), $sqlHash)) {
        throw new InvalidArgumentException('A query foi alterada apos a previa. Gere uma nova previa antes de executar.');
    }

    $expectedType = (string) ($inspection['statement_type'] ?? '');
    if ((string) ($preview['statement_type'] ?? '') !== $expectedType) {
        throw new InvalidArgumentException('A previa atual nao corresponde ao tipo de instrucao informado.');
    }

    $expectedPhrase = dashglpi_sql_console_confirmation_phrase($expectedType);
    $confirmationText = strtoupper(trim((string) ($options['confirmation_text'] ?? '')));
    if ($confirmationText !== $expectedPhrase) {
        throw new InvalidArgumentException('Frase de confirmacao invalida. Digite exatamente "' . $expectedPhrase . '".');
    }

    return $previewToken;
}

function dashglpi_sql_console_consume_write_preview(string $previewToken): void
{
    dashglpi_start_session();

    if (isset($_SESSION[DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY][$previewToken])) {
        unset($_SESSION[DASHGLPI_SQL_CONSOLE_WRITE_PREVIEW_SESSION_KEY][$previewToken]);
    }
}

function dashglpi_sql_console_analyze_sql(string $sql): array
{
    $length = strlen($sql);
    $clean = '';
    $state = 'normal';
    $hasMultipleStatements = false;
    $afterTerminator = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';
        $third = $i + 2 < $length ? $sql[$i + 2] : '';

        if ($state === 'normal') {
            if ($afterTerminator) {
                if (ctype_space($char)) {
                    continue;
                }
                if ($char === '#' || ($char === '-' && $next === '-' && ($third === '' || ctype_space($third)))) {
                    $state = 'line_comment';
                    if ($char === '-') {
                        $i++;
                    }
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $state = 'block_comment';
                    $i++;
                    continue;
                }

                $hasMultipleStatements = true;
                break;
            }

            if ($char === ';') {
                $afterTerminator = true;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $state = $char;
                $clean .= ' ';
                continue;
            }

            if ($char === '#' || ($char === '-' && $next === '-' && ($third === '' || ctype_space($third)))) {
                $state = 'line_comment';
                if ($char === '-') {
                    $i++;
                }
                $clean .= ' ';
                continue;
            }

            if ($char === '/' && $next === '*') {
                $state = 'block_comment';
                $clean .= ' ';
                $i++;
                continue;
            }

            $clean .= $char;
            continue;
        }

        if ($state === 'line_comment') {
            if ($char === "\n") {
                $state = 'normal';
                $clean .= "\n";
            }
            continue;
        }

        if ($state === 'block_comment') {
            if ($char === '*' && $next === '/') {
                $state = 'normal';
                $i++;
            }
            continue;
        }

        if ($state === "'" || $state === '"') {
            if ($char === '\\') {
                $i++;
                continue;
            }

            if ($char === $state) {
                if ($next === $state) {
                    $i++;
                    continue;
                }

                $state = 'normal';
            }

            continue;
        }

        if ($state === '`') {
            if ($char === '`') {
                $state = 'normal';
            }
            continue;
        }
    }

    if ($state === 'line_comment') {
        $state = 'normal';
    }

    if ($state !== 'normal') {
        throw new InvalidArgumentException('A consulta possui aspas ou comentarios nao finalizados.');
    }

    $flatSql = strtoupper(trim(preg_replace('/\s+/', ' ', $clean) ?? ''));
    $firstToken = '';

    if (preg_match('/^[A-Z_][A-Z0-9_]*/', $flatSql, $matches)) {
        $firstToken = $matches[0];
    }

    return [
        'flat_sql' => $flatSql,
        'first_token' => $firstToken,
        'has_multiple_statements' => $hasMultipleStatements,
    ];
}

function dashglpi_sql_console_assert_safe_patterns(string $sql, array $patterns): void
{
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $sql) === 1) {
            throw new InvalidArgumentException('A consulta usa uma instrucao bloqueada por seguranca.');
        }
    }
}

function dashglpi_sql_console_apply_timeout(PDO $pdo): void
{
    try {
        $pdo->exec('SET SESSION MAX_EXECUTION_TIME = ' . (int) DASHGLPI_SQL_CONSOLE_TIMEOUT_MS);
        return;
    } catch (Throwable) {
    }

    try {
        $seconds = DASHGLPI_SQL_CONSOLE_TIMEOUT_MS / 1000;
        $pdo->exec('SET SESSION max_statement_time = ' . number_format($seconds, 3, '.', ''));
    } catch (Throwable) {
    }
}

function dashglpi_sql_console_columns(PDOStatement $statement): array
{
    $columns = [];
    $count = $statement->columnCount();

    for ($index = 0; $index < $count; $index++) {
        $meta = $statement->getColumnMeta($index);
        $name = trim((string) ($meta['name'] ?? ''));
        $columns[] = $name !== '' ? $name : 'col_' . ($index + 1);
    }

    return $columns;
}

function dashglpi_sql_console_normalize_cell($value)
{
    if ($value === null) {
        return null;
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_scalar($value)) {
        $string = (string) $value;
        if (strlen($string) > DASHGLPI_SQL_CONSOLE_CELL_MAX_LENGTH) {
            return substr($string, 0, DASHGLPI_SQL_CONSOLE_CELL_MAX_LENGTH) . '...';
        }

        return $string;
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[valor nao serializavel]';
}

function dashglpi_sql_console_statement_preview(string $sql): string
{
    $preview = trim(preg_replace('/\s+/', ' ', $sql) ?? '');
    if (strlen($preview) <= 180) {
        return $preview;
    }

    return substr($preview, 0, 177) . '...';
}

function dashglpi_sql_console_duration_ms(float $startedAt): int
{
    return max(0, (int) round((microtime(true) - $startedAt) * 1000));
}

function dashglpi_sql_console_log(array $payload): void
{
    try {
        $user = dashglpi_current_user() ?? ['id' => 0, 'name' => ''];
        $sql = (string) ($payload['sql'] ?? '');
        $statementPreview = dashglpi_sql_console_statement_preview($sql);
        $statementHash = hash('sha256', $sql);
        $targetTables = $payload['target_tables'] ?? [];

        if (!is_array($targetTables)) {
            $targetTables = [(string) $targetTables];
        }

        $targetTables = array_values(array_filter(array_map(
            static fn($item): string => trim((string) $item),
            $targetTables
        ), static fn(string $item): bool => $item !== ''));

        $stmt = dashglpi_db()->prepare(
            "INSERT INTO " . DASHGLPI_SQL_CONSOLE_LOG_TABLE . " (
                users_id,
                user_name,
                statement_type,
                execution_mode,
                request_phase,
                statement_hash,
                statement_preview,
                sql_text,
                target_tables,
                rows_returned,
                affected_rows,
                rows_limited,
                duration_ms,
                status,
                error_message
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            max(0, (int) ($user['id'] ?? 0)),
            substr((string) ($user['name'] ?? ''), 0, 255),
            substr((string) ($payload['statement_type'] ?? ''), 0, 32),
            substr((string) ($payload['execution_mode'] ?? 'read'), 0, 16),
            substr((string) ($payload['request_phase'] ?? 'execute'), 0, 16),
            $statementHash,
            substr($statementPreview, 0, 255),
            substr($sql, 0, DASHGLPI_SQL_CONSOLE_LOG_SQL_MAX_LENGTH),
            substr(implode(', ', $targetTables), 0, 255),
            max(0, (int) ($payload['rows_returned'] ?? 0)),
            max(0, (int) ($payload['affected_rows'] ?? 0)),
            !empty($payload['rows_limited']) ? 1 : 0,
            max(0, (int) ($payload['duration_ms'] ?? 0)),
            substr((string) ($payload['status'] ?? 'success'), 0, 32),
            substr((string) ($payload['error_message'] ?? ''), 0, 1000),
        ]);
    } catch (Throwable $e) {
        error_log('[DashGLPI] SQL console audit log error: ' . $e->getMessage());
    }
}
