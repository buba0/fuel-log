-- Run as a database administrator with the Fuel Log database selected.
SET @fuel_log_language_migration = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'language'
    ),
    'SELECT ''users.language is already present''',
    'ALTER TABLE users ADD COLUMN language CHAR(2) NOT NULL DEFAULT ''en'' AFTER currency'
);
PREPARE fuel_log_language_migration FROM @fuel_log_language_migration;
EXECUTE fuel_log_language_migration;
DEALLOCATE PREPARE fuel_log_language_migration;
