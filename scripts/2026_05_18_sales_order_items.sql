CREATE TABLE IF NOT EXISTS sales_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT UNSIGNED NOT NULL,
    beverage_name VARCHAR(120) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    inventory_item_id INT UNSIGNED NULL,
    ingredient_item_ids TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sales_order_items_order (sales_order_id),
    INDEX idx_sales_order_items_beverage (beverage_name),
    CONSTRAINT fk_sales_order_items_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_order_items_inventory FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO sales_order_items (
    sales_order_id,
    beverage_name,
    quantity,
    unit_price,
    total_amount,
    inventory_item_id,
    ingredient_item_ids,
    created_at,
    updated_at
)
SELECT
    so.id,
    so.beverage_name,
    so.quantity,
    so.unit_price,
    so.total_amount,
    so.inventory_item_id,
    so.ingredient_item_ids,
    so.created_at,
    so.updated_at
FROM sales_orders so
WHERE NOT EXISTS (
    SELECT 1
    FROM sales_order_items soi
    WHERE soi.sales_order_id = so.id
);
