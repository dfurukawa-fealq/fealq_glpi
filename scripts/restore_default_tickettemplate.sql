-- Restore minimal stock GLPI ticket template "Default" with id = 1.
-- Validated against the local GLPI 11.x instance in this repository.
--
-- IMPORTANT
-- 1. Run a backup before applying this script on the remote database.
-- 2. This restores the minimal default template and its required linked row.
-- 3. If the deleted remote template had custom predefined/hidden/readonly fields,
--    those customizations are not recreated by this script.

-- Suggested backups
-- CREATE TABLE backup_glpi_tickettemplates_20260620 AS
--   SELECT * FROM glpi_tickettemplates;
-- CREATE TABLE backup_glpi_tickettemplatemandatoryfields_20260620 AS
--   SELECT * FROM glpi_tickettemplatemandatoryfields;
-- CREATE TABLE backup_glpi_entities_20260620 AS
--   SELECT * FROM glpi_entities;

-- Pre-checks
SELECT id, entities_id, name, comment, is_recursive, allowed_statuses
FROM glpi_tickettemplates
WHERE id = 1;

SELECT id, name, entities_id, tickettemplates_strategy, tickettemplates_id
FROM glpi_entities
WHERE id IN (0, 1);

SELECT id, tickettemplates_id, num
FROM glpi_tickettemplatemandatoryfields
WHERE tickettemplates_id = 1;

START TRANSACTION;

INSERT INTO glpi_tickettemplates (
    id,
    name,
    entities_id,
    is_recursive,
    comment,
    allowed_statuses
) VALUES (
    1,
    'Default',
    0,
    1,
    NULL,
    '[1,10,2,3,4,5,6]'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    entities_id = VALUES(entities_id),
    is_recursive = VALUES(is_recursive),
    comment = VALUES(comment),
    allowed_statuses = VALUES(allowed_statuses);

-- Root entity must resolve to the restored default template.
UPDATE glpi_entities
SET
    tickettemplates_strategy = 0,
    tickettemplates_id = 1
WHERE id = 0;

-- Local validated instance contains one mandatory-field link for template 1.
INSERT IGNORE INTO glpi_tickettemplatemandatoryfields (
    tickettemplates_id,
    num
) VALUES (
    1,
    21
);

COMMIT;

-- Post-checks
SELECT id, entities_id, name, comment, is_recursive, allowed_statuses
FROM glpi_tickettemplates
WHERE id = 1;

SELECT id, name, entities_id, tickettemplates_strategy, tickettemplates_id
FROM glpi_entities
WHERE id IN (0, 1);

SELECT id, tickettemplates_id, num
FROM glpi_tickettemplatemandatoryfields
WHERE tickettemplates_id = 1;

-- Optional integrity checks: all should return 0 orphan rows.
SELECT 'glpi_entities' AS table_name, COUNT(*) AS orphan_rows
FROM glpi_entities e
LEFT JOIN glpi_tickettemplates t ON t.id = e.tickettemplates_id
WHERE e.tickettemplates_id > 0
  AND t.id IS NULL

UNION ALL

SELECT 'glpi_tickets', COUNT(*)
FROM glpi_tickets x
LEFT JOIN glpi_tickettemplates t ON t.id = x.tickettemplates_id
WHERE x.tickettemplates_id > 0
  AND t.id IS NULL

UNION ALL

SELECT 'glpi_profiles', COUNT(*)
FROM glpi_profiles p
LEFT JOIN glpi_tickettemplates t ON t.id = p.tickettemplates_id
WHERE p.tickettemplates_id > 0
  AND t.id IS NULL

UNION ALL

SELECT 'glpi_ticketrecurrents', COUNT(*)
FROM glpi_ticketrecurrents r
LEFT JOIN glpi_tickettemplates t ON t.id = r.tickettemplates_id
WHERE r.tickettemplates_id > 0
  AND t.id IS NULL

UNION ALL

SELECT 'glpi_plugin_formcreator_targettickets', COUNT(*)
FROM glpi_plugin_formcreator_targettickets f
LEFT JOIN glpi_tickettemplates t ON t.id = f.tickettemplates_id
WHERE f.tickettemplates_id > 0
  AND t.id IS NULL

UNION ALL

SELECT 'glpi_tickettemplatehiddenfields', COUNT(*)
FROM glpi_tickettemplatehiddenfields h
LEFT JOIN glpi_tickettemplates t ON t.id = h.tickettemplates_id
WHERE h.tickettemplates_id > 0
  AND t.id IS NULL

UNION ALL

SELECT 'glpi_tickettemplatemandatoryfields', COUNT(*)
FROM glpi_tickettemplatemandatoryfields m
LEFT JOIN glpi_tickettemplates t ON t.id = m.tickettemplates_id
WHERE m.tickettemplates_id > 0
  AND t.id IS NULL

UNION ALL

SELECT 'glpi_tickettemplatepredefinedfields', COUNT(*)
FROM glpi_tickettemplatepredefinedfields p2
LEFT JOIN glpi_tickettemplates t ON t.id = p2.tickettemplates_id
WHERE p2.tickettemplates_id > 0
  AND t.id IS NULL

UNION ALL

SELECT 'glpi_tickettemplatereadonlyfields', COUNT(*)
FROM glpi_tickettemplatereadonlyfields r2
LEFT JOIN glpi_tickettemplates t ON t.id = r2.tickettemplates_id
WHERE r2.tickettemplates_id > 0
  AND t.id IS NULL;
