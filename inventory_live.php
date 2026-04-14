<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_login();
require_department_access('inventory');

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

    $rows = $pdo->query("SELECT id, item_name, stock_qty, reorder_level, unit, updated_at
        FROM inventory_items
        WHERE status = 'approved'
        ORDER BY (stock_qty <= reorder_level) DESC, stock_qty ASC, item_name ASC
        LIMIT 20")
        ->fetchAll();

    $lowStockCount = 0;
    $items = [];

    foreach ($rows as $row) {
        $stockQty = (float) ($row['stock_qty'] ?? 0);
        $reorderLevel = (float) ($row['reorder_level'] ?? 0);
        $isLowStock = $stockQty <= $reorderLevel;
        if ($isLowStock) {
            $lowStockCount++;
        }

        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'item_name' => (string) ($row['item_name'] ?? ''),
            'stock_qty' => $stockQty,
            'reorder_level' => $reorderLevel,
            'unit' => (string) ($row['unit'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'health' => $isLowStock ? 'low_stock' : 'healthy',
        ];
    }

    $payload = [
        'ok' => true,
        'generated_at' => date('c'),
        'generated_label' => date('M d, Y h:i:s A'),
        'summary' => [
            'total_items' => count($items),
            'low_stock_count' => $lowStockCount,
        ],
        'items' => $items,
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Unable to load live inventory data.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
