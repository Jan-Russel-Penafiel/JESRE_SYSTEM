USE don_macchiatos;

SET @db_name = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'received_qty') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN received_qty DECIMAL(12,2) NULL AFTER purchasing_note',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'received_verified_by') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN received_verified_by INT UNSIGNED NULL AFTER received_qty',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'received_verified_at') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN received_verified_at DATETIME NULL AFTER received_verified_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND COLUMN_NAME = 'receiving_note') = 0,
    'ALTER TABLE purchase_requests ADD COLUMN receiving_note TEXT NULL AFTER received_verified_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND INDEX_NAME = 'idx_purchase_received_verified') = 0,
    'ALTER TABLE purchase_requests ADD INDEX idx_purchase_received_verified (received_verified_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @db_name AND TABLE_NAME = 'purchase_requests' AND CONSTRAINT_NAME = 'fk_purchase_received_verified_by') = 0,
    'ALTER TABLE purchase_requests ADD CONSTRAINT fk_purchase_received_verified_by FOREIGN KEY (received_verified_by) REFERENCES users (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sales_orders' AND COLUMN_NAME = 'customer_tin') = 0,
    'ALTER TABLE sales_orders ADD COLUMN customer_tin VARCHAR(30) NULL AFTER customer_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'sales_orders' AND INDEX_NAME = 'idx_sales_customer_tin') = 0,
    'ALTER TABLE sales_orders ADD INDEX idx_sales_customer_tin (customer_tin)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'crm_profiles' AND COLUMN_NAME = 'customer_tin') = 0,
    'ALTER TABLE crm_profiles ADD COLUMN customer_tin VARCHAR(30) NULL AFTER customer_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'crm_profiles' AND INDEX_NAME = 'idx_crm_customer_tin') = 0,
    'ALTER TABLE crm_profiles ADD INDEX idx_crm_customer_tin (customer_tin)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE purchase_requests
SET received_qty = COALESCE(received_qty, requested_qty),
    received_verified_by = COALESCE(received_verified_by, approved_by),
    received_verified_at = COALESCE(received_verified_at, approved_at),
    receiving_note = COALESCE(receiving_note, 'Backfilled from approved purchase history.')
WHERE status = 'approved'
    AND received_verified_at IS NULL;
