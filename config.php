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
        'description' => 'Make purchase orders confirmed by Inventory, then route them to the General Manager for final purchase approval.',
        'create_button_label' => 'New Purchase Order',
        'submit_label' => 'Save Purchase Order',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Receive purchase orders confirmed by Inventory.',
            'Make supplier purchase orders for low-stock ingredients and supplies.',
            'General Manager final approval restocks linked inventory items and records the expense.',
        ],
        'primary_label' => 'request_code',
        'fields' => [
            ['name' => 'request_code', 'label' => 'PO Code (optional)', 'type' => 'text', 'required' => false],
            ['name' => 'inventory_item_id', 'label' => 'Item to Purchase', 'type' => 'inventory_select', 'required' => true],
            ['name' => 'requested_qty', 'label' => 'Order Quantity', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'supplier_name', 'label' => 'Supplier Name', 'type' => 'text', 'required' => false],
            ['name' => 'quoted_unit_cost', 'label' => 'Total Cost', 'type' => 'number', 'step' => '0.01', 'required' => false],
            ['name' => 'expected_delivery_date', 'label' => 'Expected Delivery Date', 'type' => 'date', 'required' => false],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'PO Code' => 'request_code',
            'Item' => 'inventory_item_id',
            'Order Qty' => 'requested_qty',
            'Supplier' => 'supplier_name',
            'Total Cost' => 'estimated_total',
            'Workflow Stage' => 'purchase_workflow_stage',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'inventory' => [
        'table' => 'inventory_items',
        'title' => 'Inventory Department',
        'description' => 'Monitor stock levels, receive Production purchase requests, and confirm generated purchase orders for Purchasing.',
        'create_button_label' => 'Prepare Purchase Order',
        'submit_label' => 'Save Inventory Entry',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Record and monitor inventory levels after Production prepares orders.',
            'Determine low and high stock levels from live inventory quantities.',
            'Confirm generated purchase orders for Purchasing when stock is low.',
        ],
        'primary_label' => 'item_name',
        'fields' => [
            ['name' => 'item_name', 'label' => 'Item Name', 'type' => 'text', 'required' => true],
            ['name' => 'unit', 'label' => 'Unit', 'type' => 'text', 'required' => true],
            ['name' => 'stock_qty', 'label' => 'Stock Quantity', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'reorder_level', 'label' => 'Reorder Level', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'per_cup_qty', 'label' => 'Per Cup Value', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'per_straw_qty', 'label' => 'Per Straw Value', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Item' => 'item_name',
            'Stock' => 'stock_qty',
            'Unit' => 'unit',
            'Reorder Level' => 'reorder_level',
            'Per Cup' => 'per_cup_qty',
            'Per Straw' => 'per_straw_qty',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'production' => [
        'table' => 'production_logs',
        'title' => 'Production Department',
        'description' => 'Receive Sales Order copies, prepare orders, record inventory movement, and send low-stock purchase requests directly to Inventory.',
        'create_button_label' => 'Production Log',
        'submit_label' => 'Save Production Log',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Receive approved Sales Order copies from Sales.',
            'Prepare orders and monitor inventory movement after preparation.',
            'Send purchase requests to Inventory when stock reaches the low-stock threshold.',
        ],
        'primary_label' => 'beverage_name',
        'fields' => [
            ['name' => 'beverage_name', 'label' => 'Beverage Name', 'type' => 'recipe_select', 'required' => true],
            ['name' => 'quantity_prepared', 'label' => 'Quantity Prepared (cups)', 'type' => 'number', 'step' => '1', 'required' => true],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Beverage' => 'beverage_name',
            'Prepared Qty' => 'quantity_prepared',
            'Date' => 'created_at',
        ],
    ],
    'sales' => [
        'table' => 'sales_orders',
        'title' => 'Sales Department',
        'description' => 'Record customer sales and daily production logs, then send Sales Order copies to Production and financial activity to Accounting.',
        'create_button_label' => 'New Sale',
        'submit_label' => 'Save Sale',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Record buyer name, beverage, and quantity without blocking on same-day production stock.',
            'Record buyer name, beverage, and quantity for the Sales Order.',
            'Approved sales send order copies to Production and accounting entries to Accounting.',
        ],
        'primary_label' => 'order_code',
        'fields' => [
            ['name' => 'order_code', 'label' => 'Order Code (optional)', 'type' => 'text', 'required' => false],
            ['name' => 'customer_name', 'label' => 'Buyer Name', 'type' => 'text', 'required' => true],
            ['name' => 'beverage_name', 'label' => 'Coffee Flavor', 'type' => 'recipe_select', 'required' => true],
            ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'step' => '1', 'required' => true],
            ['name' => 'unit_price', 'label' => 'Unit Price', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Order Code' => 'order_code',
            'Buyer' => 'customer_name',
            'Flavor' => 'beverage_name',
            'Qty' => 'quantity',
            'Total' => 'total_amount',
            'Receipt' => 'receipt_no',
            'Date' => 'created_at',
        ],
    ],
    'accounting' => [
        'table' => 'accounting_entries',
        'title' => 'Accounting Department',
        'description' => 'Record expenses from the Sales parallel flow, including utilities, electricity, water, and other bills.',
        'create_button_label' => 'Record Expense',
        'submit_label' => 'Save Expense',
        'edit_label' => 'Save Changes',
        'workflow_points' => [
            'Record utility and bill expenses such as electricity and water.',
            'Processed sales orders automatically create income entries.',
            'Keep sales income and operating expenses available for reports.',
        ],
        'primary_label' => 'source',
        'fields' => [
            ['name' => 'entry_type', 'label' => 'Entry Type', 'type' => 'select', 'required' => true, 'options' => ['expense' => 'Expense']],
            ['name' => 'source', 'label' => 'Expense Source', 'type' => 'text', 'required' => true],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'description', 'label' => 'Description / Bill Details', 'type' => 'textarea', 'required' => false],
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
