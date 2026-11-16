SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='theme'),
 'SELECT 1', 'ALTER TABLE users ADD COLUMN theme VARCHAR(8) NOT NULL DEFAULT ''system''');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='api_token_hash'),
 'SELECT 1', 'ALTER TABLE users ADD COLUMN api_token_hash CHAR(64) NULL');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='grafana_token_hash'),
 'SELECT 1', 'ALTER TABLE users ADD COLUMN grafana_token_hash CHAR(64) NULL');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cars' AND COLUMN_NAME='vehicle_type'),
 'SELECT 1', 'ALTER TABLE cars ADD COLUMN vehicle_type VARCHAR(16) NOT NULL DEFAULT ''car''');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cars' AND COLUMN_NAME='fuel_type'),
 'SELECT 1', 'ALTER TABLE cars ADD COLUMN fuel_type VARCHAR(16) NOT NULL DEFAULT ''other''');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cars' AND COLUMN_NAME='make'),
 'SELECT 1', 'ALTER TABLE cars ADD COLUMN make VARCHAR(60) NOT NULL DEFAULT ''''');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cars' AND COLUMN_NAME='model'),
 'SELECT 1', 'ALTER TABLE cars ADD COLUMN model VARCHAR(60) NOT NULL DEFAULT ''''');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cars' AND COLUMN_NAME='model_year'),
 'SELECT 1', 'ALTER TABLE cars ADD COLUMN model_year SMALLINT UNSIGNED NULL');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cars' AND COLUMN_NAME='engine'),
 'SELECT 1', 'ALTER TABLE cars ADD COLUMN engine VARCHAR(100) NOT NULL DEFAULT ''''');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cars' AND COLUMN_NAME='registration'),
 'SELECT 1', 'ALTER TABLE cars ADD COLUMN registration VARCHAR(30) NOT NULL DEFAULT ''''');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;

SET @fuel_log_migration = IF(
 EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fillups' AND COLUMN_NAME='anomaly_dismissed'),
 'SELECT 1', 'ALTER TABLE fillups ADD COLUMN anomaly_dismissed TINYINT NOT NULL DEFAULT 0');
PREPARE fuel_log_migration FROM @fuel_log_migration;
EXECUTE fuel_log_migration;
DEALLOCATE PREPARE fuel_log_migration;
