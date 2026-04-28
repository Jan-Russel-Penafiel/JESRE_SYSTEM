CREATE DATABASE IF NOT EXISTS don_macchiatos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE don_macchiatos;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('general_manager', 'department_head') NOT NULL,
    department ENUM('purchasing', 'inventory', 'production', 'sales', 'accounting', 'crm', 'marketing') NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS code_sequences (
    sequence_key VARCHAR(64) PRIMARY KEY,
    sequence_date DATE NOT NULL,
    last_value INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(120) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
    per_cup_qty DECIMAL(12,2) NOT NULL DEFAULT 1,
    per_straw_qty DECIMAL(12,2) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approval_note TEXT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_item_name (item_name),
    INDEX idx_inventory_status (status),
    INDEX idx_inventory_stock (stock_qty, reorder_level),
    CONSTRAINT fk_inventory_submitted_by FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS beverage_recipes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    beverage_name VARCHAR(120) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_beverage_recipe_name (beverage_name),
    INDEX idx_beverage_recipe_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS beverage_recipe_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NOT NULL,
    required_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    INDEX idx_recipe_items_recipe (recipe_id),
    INDEX idx_recipe_items_inventory (inventory_item_id),
    CONSTRAINT fk_recipe_items_recipe FOREIGN KEY (recipe_id) REFERENCES beverage_recipes (id) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_items_inventory FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(40) NOT NULL UNIQUE,
    inventory_item_id INT UNSIGNED NOT NULL,
    requested_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    supplier_name VARCHAR(160) NULL,
    quoted_unit_cost DECIMAL(12,2) NULL,
    estimated_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_delivery_date DATE NULL,
    notes TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approval_note TEXT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_purchase_status (status),
    INDEX idx_purchase_inventory (inventory_item_id),
    CONSTRAINT fk_purchase_inventory_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchase_submitted_by FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_purchase_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS production_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    beverage_name VARCHAR(120) NOT NULL,
    quantity_prepared INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    ingredient_item_ids TEXT NULL,
    ingredient_used_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approval_note TEXT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_production_status (status),
    CONSTRAINT fk_production_inventory_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL,
    CONSTRAINT fk_production_submitted_by FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_production_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(40) NOT NULL UNIQUE,
    customer_name VARCHAR(120) NOT NULL,
    beverage_name VARCHAR(120) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'digital') NOT NULL DEFAULT 'cash',
    payment_reference VARCHAR(120) NULL,
    payment_status ENUM('paid', 'pending', 'failed') NOT NULL DEFAULT 'paid',
    receipt_no VARCHAR(60) NULL UNIQUE,
    paid_at DATETIME NULL,
    inventory_item_id INT UNSIGNED NULL,
    ingredient_item_ids TEXT NULL,
    stock_deduct_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    per_cup_qty DECIMAL(12,2) NOT NULL DEFAULT 1,
    per_straw_qty DECIMAL(12,2) NOT NULL DEFAULT 1,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approval_note TEXT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sales_status (status),
    INDEX idx_sales_date (created_at),
    CONSTRAINT fk_sales_inventory_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_submitted_by FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS accounting_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_type ENUM('income', 'expense') NOT NULL,
    source VARCHAR(160) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approval_note TEXT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_accounting_status (status),
    INDEX idx_accounting_type (entry_type),
    CONSTRAINT fk_accounting_submitted_by FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_accounting_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS crm_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(120) NOT NULL,
    contact_no VARCHAR(60) NULL,
    preferences TEXT NULL,
    last_purchase_at DATETIME NULL,
    purchase_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_spent DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approval_note TEXT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_customer_name (customer_name),
    INDEX idx_crm_status (status),
    CONSTRAINT fk_crm_submitted_by FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_crm_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS crm_purchase_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id INT UNSIGNED NOT NULL,
    sales_order_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crm_history_profile (profile_id),
    INDEX idx_crm_history_date (purchased_at),
    CONSTRAINT fk_crm_history_profile FOREIGN KEY (profile_id) REFERENCES crm_profiles (id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_history_sales FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_name VARCHAR(160) NOT NULL,
    trend_notes TEXT NOT NULL,
    promotion_plan TEXT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approval_note TEXT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketing_status (status),
    CONSTRAINT fk_marketing_submitted_by FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_marketing_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS approval_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module ENUM('purchasing', 'inventory', 'production', 'sales', 'accounting', 'crm', 'marketing') NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    action ENUM('approved', 'rejected') NOT NULL,
    note TEXT NULL,
    action_by INT UNSIGNED NULL,
    action_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approval_logs_module (module, record_id),
    INDEX idx_approval_logs_date (action_at),
    CONSTRAINT fk_approval_action_by FOREIGN KEY (action_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_trails (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module ENUM('purchasing', 'inventory', 'production', 'sales', 'accounting', 'crm', 'marketing', 'system') NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(40) NOT NULL,
    source ENUM('user', 'system') NOT NULL DEFAULT 'user',
    note TEXT NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    diff_data JSON NULL,
    performed_by INT UNSIGNED NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_module_record (module, record_id),
    INDEX idx_audit_action (action_type),
    INDEX idx_audit_performed_at (performed_at),
    CONSTRAINT fk_audit_performed_by FOREIGN KEY (performed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @default_hash = '$2y$10$ZW3PzJX7mq29MOVYD9Y.OeygDemi6SSdo.Ue5YgvpsoEsAzWv/roy';

INSERT INTO users (full_name, username, password_hash, role, department)
VALUES
    ('General Manager', 'gm', @default_hash, 'general_manager', NULL),
    ('Purchasing Head', 'purch_head', @default_hash, 'department_head', 'purchasing'),
    ('Inventory Head', 'inv_head', @default_hash, 'department_head', 'inventory'),
    ('Production Head', 'prod_head', @default_hash, 'department_head', 'production'),
    ('Sales Head', 'sales_head', @default_hash, 'department_head', 'sales'),
    ('Accounting Head', 'acct_head', @default_hash, 'department_head', 'accounting'),
    ('CRM Head', 'crm_head', @default_hash, 'department_head', 'crm'),
    ('Marketing Head', 'mkt_head', @default_hash, 'department_head', 'marketing')
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    department = VALUES(department);

SET @gm_id = (SELECT id FROM users WHERE username = 'gm' LIMIT 1);
SET @inv_head_id = (SELECT id FROM users WHERE username = 'inv_head' LIMIT 1);

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Coffee Beans', 'kg', 10.00, 2.00, 'Primary espresso beans', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Coffee Beans');

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Milk', 'liter', 10.00, 2.00, 'Fresh milk stock', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Milk');

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Caramel Syrup', 'bottle', 10.00, 2.00, 'Flavoring stock', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Caramel Syrup');

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Matcha coffee Syrup', 'bottle', 10.00, 2.00, 'Flavoring stock', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Matcha coffee Syrup');

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Spanish latte Syrup', 'bottle', 10.00, 2.00, 'Flavoring stock', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Spanish latte Syrup');

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Hazelnuts Syrup', 'bottle', 10.00, 2.00, 'Flavoring stock', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Hazelnuts Syrup');

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Vanilla Syrup', 'bottle', 30.00, 10.00, 'Flavoring stock', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Vanilla Syrup');

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Cup', 'pcs', 200.00, 50.00, 'Utility cup stock for Sales POS deductions', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Cup');

INSERT INTO inventory_items (item_name, unit, stock_qty, reorder_level, notes, status, submitted_by, approved_by, approved_at)
SELECT 'Straw', 'pcs', 200.00, 50.00, 'Utility straw stock for Sales POS deductions', 'approved', @inv_head_id, @gm_id, NOW()
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_name = 'Straw');

INSERT INTO beverage_recipes (beverage_name, status, notes)
SELECT 'Caramel Macchiato', 'active', 'Standard recipe'
WHERE NOT EXISTS (SELECT 1 FROM beverage_recipes WHERE beverage_name = 'Caramel Macchiato');

INSERT INTO beverage_recipes (beverage_name, status, notes)
SELECT 'Spanish Latte', 'active', 'Standard recipe'
WHERE NOT EXISTS (SELECT 1 FROM beverage_recipes WHERE beverage_name = 'Spanish Latte');

INSERT INTO beverage_recipes (beverage_name, status, notes)
SELECT 'Matcha coffee', 'active', 'Standard recipe'
WHERE NOT EXISTS (SELECT 1 FROM beverage_recipes WHERE beverage_name = 'Matcha coffee');

INSERT INTO beverage_recipes (beverage_name, status, notes)
SELECT 'Hazelnuts coffee', 'active', 'Standard recipe'
WHERE NOT EXISTS (SELECT 1 FROM beverage_recipes WHERE beverage_name = 'Hazelnuts coffee');

INSERT INTO beverage_recipes (beverage_name, status, notes)
SELECT 'Vanilla coffee', 'active', 'Standard recipe'
WHERE NOT EXISTS (SELECT 1 FROM beverage_recipes WHERE beverage_name = 'Vanilla coffee');

SET @caramel_recipe_id = (SELECT id FROM beverage_recipes WHERE beverage_name = 'Caramel Macchiato' LIMIT 1);
SET @spanish_recipe_id = (SELECT id FROM beverage_recipes WHERE beverage_name = 'Spanish Latte' LIMIT 1);
SET @matcha_recipe_id = (SELECT id FROM beverage_recipes WHERE beverage_name = 'Matcha coffee' LIMIT 1);
SET @hazelnuts_recipe_id = (SELECT id FROM beverage_recipes WHERE beverage_name = 'Hazelnuts coffee' LIMIT 1);
SET @vanilla_recipe_id = (SELECT id FROM beverage_recipes WHERE beverage_name = 'Vanilla coffee' LIMIT 1);

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @caramel_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Milk'
    AND @caramel_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @caramel_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @caramel_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Caramel Syrup'
    AND @caramel_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @caramel_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @caramel_recipe_id, i.id, 0.13
FROM inventory_items i
WHERE i.item_name = 'Coffee Beans'
    AND @caramel_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @caramel_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @caramel_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Cup'
    AND @caramel_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @caramel_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @caramel_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Straw'
    AND @caramel_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @caramel_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @spanish_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Milk'
    AND @spanish_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @spanish_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @spanish_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Spanish latte Syrup'
    AND @spanish_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @spanish_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @spanish_recipe_id, i.id, 0.13
FROM inventory_items i
WHERE i.item_name = 'Coffee Beans'
    AND @spanish_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @spanish_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @spanish_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Cup'
    AND @spanish_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @spanish_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @spanish_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Straw'
    AND @spanish_recipe_id IS NOT NULL
    AND NOT EXISTS (
            SELECT 1 FROM beverage_recipe_items bri
            WHERE bri.recipe_id = @spanish_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @matcha_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Milk'
    AND @matcha_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @matcha_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @matcha_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Caramel Syrup'
    AND @matcha_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @matcha_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @matcha_recipe_id, i.id, 0.13
FROM inventory_items i
WHERE i.item_name = 'Coffee Beans'
    AND @matcha_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @matcha_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @matcha_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Cup'
    AND @matcha_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @matcha_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @matcha_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Straw'
    AND @matcha_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @matcha_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @hazelnuts_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Milk'
    AND @hazelnuts_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @hazelnuts_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @hazelnuts_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Caramel Syrup'
    AND @hazelnuts_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @hazelnuts_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @hazelnuts_recipe_id, i.id, 0.13
FROM inventory_items i
WHERE i.item_name = 'Coffee Beans'
    AND @hazelnuts_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @hazelnuts_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @hazelnuts_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Cup'
    AND @hazelnuts_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @hazelnuts_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @hazelnuts_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Straw'
    AND @hazelnuts_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @hazelnuts_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @vanilla_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Milk'
    AND @vanilla_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @vanilla_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @vanilla_recipe_id, i.id, 0.10
FROM inventory_items i
WHERE i.item_name = 'Caramel Syrup'
    AND @vanilla_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @vanilla_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @vanilla_recipe_id, i.id, 0.13
FROM inventory_items i
WHERE i.item_name = 'Coffee Beans'
    AND @vanilla_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @vanilla_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @vanilla_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Cup'
    AND @vanilla_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @vanilla_recipe_id AND bri.inventory_item_id = i.id
    );

INSERT INTO beverage_recipe_items (recipe_id, inventory_item_id, required_qty)
SELECT @vanilla_recipe_id, i.id, 1.00
FROM inventory_items i
WHERE i.item_name = 'Straw'
    AND @vanilla_recipe_id IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM beverage_recipe_items bri
        WHERE bri.recipe_id = @vanilla_recipe_id AND bri.inventory_item_id = i.id
    );
