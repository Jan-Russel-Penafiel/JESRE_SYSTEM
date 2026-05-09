USE don_macchiatos;

SET @db_name = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'inventory_confirmed_by') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN inventory_confirmed_by INT UNSIGNED NULL AFTER notes',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'inventory_confirmed_at') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN inventory_confirmed_at DATETIME NULL AFTER inventory_confirmed_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'purchasing_processed_by') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN purchasing_processed_by INT UNSIGNED NULL AFTER inventory_confirmed_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'purchasing_processed_at') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN purchasing_processed_at DATETIME NULL AFTER purchasing_processed_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'purchasing_note') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN purchasing_note TEXT NULL AFTER purchasing_processed_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND INDEX_NAME = 'idx_purchase_inventory_confirmed') = 0,
    'ALTER TABLE purchase_requests ADD INDEX idx_purchase_inventory_confirmed (inventory_confirmed_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND INDEX_NAME = 'idx_purchase_purchasing_processed') = 0,
    'ALTER TABLE purchase_requests ADD INDEX idx_purchase_purchasing_processed (purchasing_processed_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND CONSTRAINT_NAME = 'fk_purchase_inventory_confirmed_by') = 0,
    'ALTER TABLE purchase_requests ADD CONSTRAINT fk_purchase_inventory_confirmed_by FOREIGN KEY (inventory_confirmed_by) REFERENCES users (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND CONSTRAINT_NAME = 'fk_purchase_purchasing_processed_by') = 0,
    'ALTER TABLE purchase_requests ADD CONSTRAINT fk_purchase_purchasing_processed_by FOREIGN KEY (purchasing_processed_by) REFERENCES users (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE purchase_requests
SET inventory_confirmed_by = COALESCE(inventory_confirmed_by, submitted_by),
    inventory_confirmed_at = COALESCE(inventory_confirmed_at, updated_at, created_at)
WHERE status = 'pending'
    AND inventory_confirmed_at IS NULL
    AND notes LIKE '%[Inventory Purchase Order]%';
