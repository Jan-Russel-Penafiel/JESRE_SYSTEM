<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'DM Hub');
define('ROLE_GENERAL_MANAGER', 'general_manager');
define('ROLE_DEPARTMENT_HEAD', 'department_head');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'don_macchiatos');
define('DB_USER', 'root');
define('DB_PASS', '');

$DEPARTMENTS = [
    'inventory' => 'Inventory Department',
    'production' => 'Production Department',
    'sales' => 'Sales Department',
    'accounting' => 'Accounting Department',
    'crm' => 'CRM Department',
    'marketing' => 'Marketing Department',
];

$DEPARTMENT_CONFIG = [
    'inventory' => [
        'table' => 'inventory_items',
        'title' => 'Inventory Department',
        'description' => '',
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
        'description' => '',
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
        'description' => '',
        'primary_label' => 'order_code',
        'fields' => [
            ['name' => 'order_code', 'label' => 'Order Code (optional)', 'type' => 'text', 'required' => false],
            ['name' => 'customer_name', 'label' => 'Customer Name', 'type' => 'text', 'required' => true],
            ['name' => 'beverage_name', 'label' => 'Beverage Name', 'type' => 'text', 'required' => true],
            ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'step' => '1', 'required' => true],
            ['name' => 'unit_price', 'label' => 'Unit Price', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'inventory_item_id', 'label' => 'Inventory Item to Deduct', 'type' => 'inventory_select', 'required' => true],
            ['name' => 'stock_deduct_qty', 'label' => 'Stock Deduct Per Order', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false],
        ],
        'list_columns' => [
            'Order Code' => 'order_code',
            'Customer' => 'customer_name',
            'Beverage' => 'beverage_name',
            'Qty' => 'quantity',
            'Total' => 'total_amount',
            'Status' => 'status',
            'Updated' => 'updated_at',
        ],
    ],
    'accounting' => [
        'table' => 'accounting_entries',
        'title' => 'Accounting Department',
        'description' => '',
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
        'description' => '',
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
        'description' => '',
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
