<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'Don Macchiatos');
define('APP_URL', rtrim((string) (getenv('APP_URL') ?: 'http://localhost/re'), '/'));
define('ROLE_GENERAL_MANAGER', 'general_manager');
define('ROLE_DEPARTMENT_HEAD', 'department_head');

$realTimeSalesEnv = getenv('REALTIME_SALES_MODE');
define(
    'REALTIME_SALES_MODE',
    $realTimeSalesEnv !== false
        ? in_array(strtolower((string) $realTimeSalesEnv), ['1', 'true', 'yes', 'on'], true)
        : true
);

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'don_macchiatos');
define('DB_USER', 'root');
define('DB_PASS', '');

$DEPARTMENTS = [
    'purchasing' => 'Purchasing Department',
    'inventory' => 'Inventory Department',
    'production' => 'Production Department',
    'sales' => 'Sales Department',
    'accounting' => 'Accounting Department',
    'crm' => 'CRM Department',
    'marketing' => 'Marketing Department',
];

$DEPARTMENT_CONFIG = [
    'purchasing' => [
        'table' => 'purchase_requests',
        'title' => 'Purchasing Department',
        'description' => 'Buy ingredients before inventory runs out and keep supplier restocking aligned with low-stock alerts from Inventory.',
        'create_button_label' => 'New Purchase Request',
        'submit_label' => 'Save Purchase Request',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Buy ingredients before stocks run out.',
            'Use Inventory low-stock updates to prepare supplier purchases.',
            'Approved purchase requests automatically restock linked inventory items.',
        ],
        'primary_label' => 'request_code',
        'fields' => [
            ['name' => 'request_code', 'label' => 'Request Code (optional)', 'type' => 'text', 'required' => false],
            ['name' => 'inventory_item_id', 'label' => 'Ingredient to Purchase', 'type' => 'inventory_select', 'required' => true],
            ['name' => 'requested_qty', 'label' => 'Requested Quantity', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'supplier_name', 'label' => 'Supplier Name', 'type' => 'text', 'required' => false],
            ['name' => 'quoted_unit_cost', 'label' => 'Quoted Unit Cost', 'type' => 'number', 'step' => '0.01', 'required' => false],
            ['name' => 'expected_delivery_date', 'label' => 'Expected Delivery Date', 'type' => 'date', 'required' => false],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Request Code' => 'request_code',
            'Ingredient Item' => 'inventory_item_id',
            'Requested Qty' => 'requested_qty',
            'Supplier' => 'supplier_name',
            'Estimated Total' => 'estimated_total',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'inventory' => [
        'table' => 'inventory_items',
        'title' => 'Inventory Department',
        'description' => 'Receive ingredients, store them in the inventory database, auto-deduct stock on sales, and keep Purchasing updated on low stock.',
        'create_button_label' => 'Receive Ingredients',
        'submit_label' => 'Save Inventory Entry',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Receive ingredients and store them in the inventory database.',
            'Stock is auto-deducted when Sales or Production consume ingredients.',
            'Low-stock updates trigger Purchasing requests before materials run out.',
        ],
        'primary_label' => 'item_name',
        'fields' => [
            ['name' => 'item_name', 'label' => 'Item Name', 'type' => 'text', 'required' => true],
            ['name' => 'unit', 'label' => 'Unit', 'type' => 'text', 'required' => true],
            ['name' => 'stock_qty', 'label' => 'Stock Quantity', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'reorder_level', 'label' => 'Reorder Level', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Item' => 'item_name',
            'Stock' => 'stock_qty',
            'Unit' => 'unit',
            'Reorder Level' => 'reorder_level',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'production' => [
        'table' => 'production_logs',
        'title' => 'Production Department',
        'description' => 'Request ingredients from Inventory, prepare beverages, and log ingredient usage so stock levels stay accurate.',
        'create_button_label' => 'Log Production Request',
        'submit_label' => 'Save Production Log',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Request ingredients from Inventory before preparing beverages.',
            'Record the quantity prepared and the ingredient usage per batch.',
            'If ingredients are short, Inventory and Purchasing are alerted automatically.',
        ],
        'primary_label' => 'beverage_name',
        'fields' => [
            ['name' => 'beverage_name', 'label' => 'Beverage Name', 'type' => 'text', 'required' => true],
            ['name' => 'quantity_prepared', 'label' => 'Quantity Prepared', 'type' => 'number', 'step' => '1', 'required' => true],
            ['name' => 'inventory_item_id', 'label' => 'Ingredient Item', 'type' => 'inventory_select', 'required' => true],
            ['name' => 'ingredient_used_qty', 'label' => 'Ingredient Used Qty', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Beverage' => 'beverage_name',
            'Prepared Qty' => 'quantity_prepared',
            'Ingredient Used' => 'ingredient_used_qty',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'sales' => [
        'table' => 'sales_orders',
        'title' => 'Sales Department',
        'description' => 'Assist the customer, confirm the order in POS, check flavor availability, process payment, issue the receipt, and update sales logs in real time.',
        'create_button_label' => 'New POS Order',
        'submit_label' => 'Save POS Order',
        'edit_label' => 'Save POS Changes',
        'workflow_points' => [
            'Input the customer order directly into POS.',
            'Flavor availability is validated before the order is saved.',
            'Successful orders generate a digital sales order, payment log, receipt, and real-time sales update.',
        ],
        'primary_label' => 'order_code',
        'fields' => [
            ['name' => 'order_code', 'label' => 'Order Code (optional)', 'type' => 'text', 'required' => false],
            ['name' => 'customer_name', 'label' => 'Customer Name', 'type' => 'text', 'required' => true],
            ['name' => 'beverage_name', 'label' => 'Beverage Name', 'type' => 'text', 'required' => true],
            ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'step' => '1', 'required' => true],
            ['name' => 'unit_price', 'label' => 'Unit Price', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'payment_method', 'label' => 'Payment Method', 'type' => 'select', 'required' => true, 'options' => ['cash' => 'Cash', 'card' => 'Card', 'digital' => 'Digital']],
            ['name' => 'payment_reference', 'label' => 'Payment Reference (optional)', 'type' => 'text', 'required' => false],
            ['name' => 'inventory_item_id', 'label' => 'Inventory Item to Deduct', 'type' => 'inventory_select', 'required' => true],
            ['name' => 'stock_deduct_qty', 'label' => 'Stock Deduct Per Order', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Order Code' => 'order_code',
            'Customer' => 'customer_name',
            'Beverage' => 'beverage_name',
            'Qty' => 'quantity',
            'Payment' => 'payment_method',
            'Receipt' => 'receipt_no',
            'Total' => 'total_amount',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'accounting' => [
        'table' => 'accounting_entries',
        'title' => 'Accounting Department',
        'description' => 'Record financial transactions, store digital logs, and use sales classifications to monitor high-sales and low-sales coffee performance.',
        'create_button_label' => 'Record Transaction',
        'submit_label' => 'Save Financial Log',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Store digital financial logs for every recorded transaction.',
            'Processed sales orders automatically create income entries.',
            'Track high-sales and low-sales coffee for business analysis.',
        ],
        'primary_label' => 'source',
        'fields' => [
            ['name' => 'entry_type', 'label' => 'Entry Type', 'type' => 'select', 'required' => true, 'options' => ['income' => 'Income', 'expense' => 'Expense']],
            ['name' => 'source', 'label' => 'Source', 'type' => 'text', 'required' => true],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Type' => 'entry_type',
            'Source' => 'source',
            'Amount' => 'amount',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'crm' => [
        'table' => 'crm_profiles',
        'title' => 'CRM Department',
        'description' => 'Track customer preferences and purchase history, then connect those records with high-sales and low-sales coffee behavior.',
        'create_button_label' => 'Add CRM Record',
        'submit_label' => 'Save CRM Record',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Track customer preferences for future service and promotions.',
            'Store customer purchase history from processed sales orders.',
            'Use high-sales and low-sales coffee data to identify buying behavior.',
        ],
        'primary_label' => 'customer_name',
        'fields' => [
            ['name' => 'customer_name', 'label' => 'Customer Name', 'type' => 'text', 'required' => true],
            ['name' => 'contact_no', 'label' => 'Contact Number', 'type' => 'text', 'required' => false],
            ['name' => 'preferences', 'label' => 'Preferences', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Customer' => 'customer_name',
            'Contact' => 'contact_no',
            'Purchases' => 'purchase_count',
            'Total Spent' => 'total_spent',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'marketing' => [
        'table' => 'marketing_campaigns',
        'title' => 'Marketing Department',
        'description' => 'Analyze sales trends, create digital content, and focus promotions on low-sales coffee while monitoring market demand.',
        'create_button_label' => 'Create Campaign',
        'submit_label' => 'Save Campaign',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Analyze trends from sales, CRM, and inventory data.',
            'Create digital content and campaign plans from current demand.',
            'Promote low-sales coffee while protecting low-stock items from overexposure.',
        ],
        'primary_label' => 'campaign_name',
        'fields' => [
            ['name' => 'campaign_name', 'label' => 'Campaign Name', 'type' => 'text', 'required' => true],
            ['name' => 'trend_notes', 'label' => 'Daily Trend Analysis', 'type' => 'textarea', 'required' => true],
            ['name' => 'promotion_plan', 'label' => 'Promotion Plan', 'type' => 'textarea', 'required' => true],
            ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'required' => true],
            ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date', 'required' => true],
        ],
        'list_columns' => [
            'Campaign' => 'campaign_name',
            'Start Date' => 'start_date',
            'End Date' => 'end_date',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
];
