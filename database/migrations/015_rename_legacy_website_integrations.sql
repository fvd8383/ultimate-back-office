SET @legacy_website_integrations_table = CONCAT('247sp', '_website_integrations');

SET @rename_legacy_website_integrations_sql = (
    SELECT IF(
        EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = @legacy_website_integrations_table
        )
        AND NOT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'website_integrations'
        ),
        CONCAT('RENAME TABLE `', @legacy_website_integrations_table, '` TO website_integrations'),
        'SELECT 1'
    )
);

PREPARE rename_legacy_website_integrations_statement FROM @rename_legacy_website_integrations_sql;
EXECUTE rename_legacy_website_integrations_statement;
DEALLOCATE PREPARE rename_legacy_website_integrations_statement;
