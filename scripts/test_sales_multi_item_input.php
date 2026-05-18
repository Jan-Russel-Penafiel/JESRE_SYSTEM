<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$post = [
    'customer_name' => 'Juan Dela Cruz',
    'sales_items' => [
        [
            'beverage_name' => 'Caramel Macchiato',
            'quantity' => '2',
            'unit_price' => '95.50',
        ],
        [
            'beverage_name' => 'Strawberry',
            'quantity' => '1',
            'unit_price' => '89',
        ],
    ],
];

[$items, $errors] = validate_sales_order_items_input($post);

if ($errors !== []) {
    throw new RuntimeException('Expected valid multi-item order, got: ' . implode(' ', $errors));
}

if (count($items) !== 2) {
    throw new RuntimeException('Expected two order items, got ' . count($items) . '.');
}

[$summary, $totals] = summarize_sales_order_items($items);

if ($summary !== 'Caramel Macchiato, Strawberry') {
    throw new RuntimeException('Unexpected item summary: ' . $summary);
}

if ($totals['quantity'] !== 3) {
    throw new RuntimeException('Expected total quantity of 3, got ' . $totals['quantity'] . '.');
}

if (abs($totals['total_amount'] - 280.00) > 0.001) {
    throw new RuntimeException('Expected total amount of 280.00, got ' . $totals['total_amount'] . '.');
}

$legacyPost = [
    'beverage_name' => 'Vanilla',
    'quantity' => '4',
    'unit_price' => '75',
];

[$legacyItems, $legacyErrors] = validate_sales_order_items_input($legacyPost);

if ($legacyErrors !== [] || count($legacyItems) !== 1) {
    throw new RuntimeException('Expected legacy single-item order input to remain valid.');
}

echo "Sales multi-item input tests passed.\n";
