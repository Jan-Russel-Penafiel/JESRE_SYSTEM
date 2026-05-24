<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

$action = $_POST['action'] ?? '';
$department = $_POST['dept'] ?? '';
$redirectDepartment = (string) ($_POST['redirect_dept'] ?? $department);
$user = current_user();
$pdo = db();

if (!department_config($redirectDepartment)) {
    $redirectDepartment = $department;
}

$redirectToDepartment = static function (string $dept): void {
    redirect('department.php?dept=' . urlencode($dept));
};

$requireConfig = static function (string $dept): array {
    $config = department_config($dept);
    if (!$config) {
        throw new RuntimeException('Invalid department.');
    }

    return $config;
};

$validationConfigForDepartment = static function (array $config, string $dept): array {
    if ($dept !== 'sales') {
        return $config;
    }

    $config['fields'] = array_values(array_filter($config['fields'], static function (array $field): bool {
        return !in_array((string) ($field['name'] ?? ''), ['beverage_name', 'quantity', 'unit_price'], true);
    }));

    return $config;
};

$normalizeCustomerTin = static function ($value): ?string {
    $tin = trim((string) ($value ?? ''));
    if ($tin === '') {
        return null;
    }

    if (strlen($tin) > 30) {
        throw new RuntimeException('Customer TIN must be 30 characters or less.');
    }

    if (!preg_match('/^[0-9\-\s]+$/', $tin)) {
        throw new RuntimeException('Customer TIN may contain numbers, spaces, and hyphens only.');
    }

    return preg_replace('/\s+/', ' ', $tin) ?: null;
};

$canCreateDepartmentRecord = static function (array $currentUser, string $dept): bool {
    if (can_user_access_department($currentUser, $dept)) {
        return true;
    }

    $userDepartment = (string) ($currentUser['department'] ?? '');

    if ($dept === 'production' && $userDepartment === 'sales') {
        return true;
    }

    if ($dept === 'purchasing' && in_array($userDepartment, ['production', 'inventory'], true)) {
        return true;
    }

    return false;
};

$fetchRecord = static function (PDO $db, string $table, int $id, bool $forUpdate = false): ?array {
    $lock = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?{$lock}");
    $stmt->execute([$id]);

    $record = $stmt->fetch();

    return $record ?: null;
};

$assertOwnsRecord = static function (array $currentUser, array $record): void {
    if (($currentUser['role'] ?? '') === ROLE_GENERAL_MANAGER) {
        return;
    }

    $submittedBy = (int) ($record['submitted_by'] ?? 0);
    if ((int) $currentUser['id'] !== $submittedBy) {
        throw new RuntimeException('You can only modify your own submissions.');
    }
};

$upsertLowStockPurchaseRequest = static function (
    PDO $db,
    int $inventoryItemId,
    int $actorId,
    string $reason,
    ?float $requestedQtyOverride = null,
    bool $forceCreate = false
) use ($fetchRecord): ?int {
    if ($inventoryItemId <= 0) {
        return null;
    }

    $inventoryStmt = $db->prepare('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE');
    $inventoryStmt->execute([$inventoryItemId]);
    $inventory = $inventoryStmt->fetch();
    if (!$inventory) {
        return null;
    }

    $stockQty = (float) ($inventory['stock_qty'] ?? 0);
    $reorderLevel = (float) ($inventory['reorder_level'] ?? 0);
    if (!$forceCreate && $stockQty > $reorderLevel) {
        return null;
    }

    if ($requestedQtyOverride !== null && $requestedQtyOverride > 0) {
        $targetRequestQty = round(max($requestedQtyOverride, 1), 2);
    } else {
        $targetRequestQty = max(($reorderLevel * 2) - $stockQty, $reorderLevel > 0 ? $reorderLevel : 1);
        $targetRequestQty = round($targetRequestQty, 2);
    }

    $existingStmt = $db->prepare("SELECT * FROM purchase_requests
        WHERE inventory_item_id = ? AND status = 'pending' AND inventory_confirmed_at IS NULL
        ORDER BY id DESC
        LIMIT 1 FOR UPDATE");
    $existingStmt->execute([$inventoryItemId]);
    $existing = $existingStmt->fetch() ?: null;

    if ($existing) {
        $existingQty = (float) ($existing['requested_qty'] ?? 0);
        if ($targetRequestQty > $existingQty) {
            $oldRequest = $existing;
            $existingNotes = trim((string) ($existing['notes'] ?? ''));
            $newNotes = ($existingNotes !== '' ? ($existingNotes . "\n") : '') . '[SYSTEM] ' . $reason;

            $updateStmt = $db->prepare("UPDATE purchase_requests
                SET requested_qty = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?");
            $updateStmt->execute([$targetRequestQty, $newNotes, (int) $existing['id']]);

            $updatedRequest = $fetchRecord($db, 'purchase_requests', (int) $existing['id'], false);
            write_audit_log(
                $db,
                'purchasing',
                'purchase_requests',
                (int) $existing['id'],
                'system_update',
                $oldRequest,
                $updatedRequest,
                $actorId,
                'Auto-updated purchase request for Inventory review after a low-stock alert (' . ($inventory['item_name'] ?? 'Unknown Item') . ').',
                'system'
            );
        }

        return (int) $existing['id'];
    }

    $requestCode = next_purchase_request_code($db);
    $notes = '[SYSTEM] ' . $reason;

    $insertStmt = $db->prepare("INSERT INTO purchase_requests
        (request_code, inventory_item_id, requested_qty, supplier_name, quoted_unit_cost, estimated_total, expected_delivery_date, notes, status, submitted_by)
        VALUES (?, ?, ?, NULL, NULL, 0, ?, ?, 'pending', ?)");
    $insertStmt->execute([
        $requestCode,
        $inventoryItemId,
        $targetRequestQty,
        date('Y-m-d', strtotime('+3 days')),
        $notes,
        $actorId,
    ]);

    $purchaseRequestId = (int) $db->lastInsertId();
    $purchaseRequest = $fetchRecord($db, 'purchase_requests', $purchaseRequestId, false);

    write_audit_log(
        $db,
        'purchasing',
        'purchase_requests',
        $purchaseRequestId,
        'system_create',
        null,
        $purchaseRequest,
        $actorId,
        'Auto-created purchase request for Inventory review after a low-stock alert (' . ($inventory['item_name'] ?? 'Unknown Item') . ').',
        'system'
    );

    return $purchaseRequestId;
};

$ensureInventoryRequestAvailability = static function (
    PDO $db,
    int $inventoryItemId,
    float $requiredQty,
    int $actorId,
    string $missingSelectionMessage,
    string $missingRecordMessage,
    string $unapprovedMessage,
    string $invalidQuantityMessage,
    string $insufficientStockMessage,
    string $reorderAlertReason
) use ($upsertLowStockPurchaseRequest): void {
    if ($inventoryItemId <= 0) {
        throw new RuntimeException($missingSelectionMessage);
    }

    $inventoryStmt = $db->prepare('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE');
    $inventoryStmt->execute([$inventoryItemId]);
    $inventory = $inventoryStmt->fetch();
    if (!$inventory) {
        throw new RuntimeException($missingRecordMessage);
    }

    if (($inventory['status'] ?? '') !== 'approved') {
        throw new RuntimeException($unapprovedMessage);
    }

    if ($requiredQty <= 0) {
        throw new RuntimeException($invalidQuantityMessage);
    }

    $availableQty = (float) ($inventory['stock_qty'] ?? 0);
    if ($availableQty < $requiredQty) {
        throw new RuntimeException($insufficientStockMessage);
    }

    if ($availableQty <= (float) ($inventory['reorder_level'] ?? 0)) {
        $upsertLowStockPurchaseRequest(
            $db,
            $inventoryItemId,
            $actorId,
            $reorderAlertReason,
            null,
            false
        );
    }
};

$resolveInventoryUtilityItemId = static function (PDO $db, string $keyword): int {
    $normalizedKeyword = strtolower(trim($keyword));
    if ($normalizedKeyword === '') {
        return 0;
    }

    $exactStmt = $db->prepare('SELECT id FROM inventory_items WHERE status = ? AND LOWER(item_name) = ? ORDER BY id ASC LIMIT 1');
    $exactStmt->execute(['approved', $normalizedKeyword]);
    $exactId = (int) ($exactStmt->fetchColumn() ?: 0);
    if ($exactId > 0) {
        return $exactId;
    }

    $likeStmt = $db->prepare('SELECT id FROM inventory_items WHERE status = ? AND LOWER(item_name) LIKE ? ORDER BY id ASC LIMIT 1');
    $likeStmt->execute(['approved', '%' . $normalizedKeyword . '%']);

    return (int) ($likeStmt->fetchColumn() ?: 0);
};

$excludeUtilityIngredientIds = static function (PDO $db, array $inventoryItemIds) use ($resolveInventoryUtilityItemId): array {
    $ids = normalize_inventory_item_ids($inventoryItemIds);
    if ($ids === []) {
        return [];
    }

    $excludedIds = [];
    foreach (['cup', 'straw'] as $utilityKeyword) {
        $utilityId = $resolveInventoryUtilityItemId($db, $utilityKeyword);
        if ($utilityId > 0) {
            $excludedIds[] = $utilityId;
        }
    }

    if ($excludedIds === []) {
        return $ids;
    }

    return array_values(array_filter($ids, static function (int $id) use ($excludedIds): bool {
        return !in_array($id, $excludedIds, true);
    }));
};

$buildRecipeIngredientIds = static function (array $recipeItems): array {
    $ids = [];
    foreach ($recipeItems as $recipeItem) {
        $inventoryItemId = (int) ($recipeItem['inventory_item_id'] ?? 0);
        if ($inventoryItemId > 0) {
            $ids[$inventoryItemId] = $inventoryItemId;
        }
    }

    return array_values($ids);
};

$ensureRecipeIngredientAvailability = static function (
    PDO $db,
    array $recipeItems,
    float $multiplier,
    int $actorId,
    string $contextLabel
) use ($ensureInventoryRequestAvailability): void {
    if ($recipeItems === []) {
        throw new RuntimeException('No active recipe configured for ' . $contextLabel . '.');
    }

    foreach ($recipeItems as $recipeItem) {
        $inventoryItemId = (int) ($recipeItem['inventory_item_id'] ?? 0);
        $requiredQty = (float) ($recipeItem['required_qty'] ?? 0) * $multiplier;
        $itemName = (string) ($recipeItem['item_name'] ?? 'ingredient');

        $ensureInventoryRequestAvailability(
            $db,
            $inventoryItemId,
            $requiredQty,
            $actorId,
            'Recipe ingredient selection is required.',
            'Recipe ingredient ' . $itemName . ' does not exist.',
            'Recipe ingredient ' . $itemName . ' is not approved yet.',
            'Recipe ingredient quantity must be greater than zero.',
            $itemName . ' is unavailable. Inventory Department has been alerted for purchase order preparation.',
            'Inventory Department received a low-stock update from ' . $contextLabel . ' recipe consumption (' . $itemName . ').'
        );
    }
};

$ensureSalesFlavorAvailability = static function (
    PDO $db,
    array $inventoryItemIds,
    float $quantity,
    int $actorId
) use ($ensureInventoryRequestAvailability, $resolveInventoryUtilityItemId): void {
    $selectedIngredientIds = normalize_inventory_item_ids($inventoryItemIds);
    if ($selectedIngredientIds === []) {
        throw new RuntimeException('Please select ingredient items for this order.');
    }

    if ($quantity <= 0) {
        throw new RuntimeException('Quantity must be greater than zero.');
    }

    $ingredientRequiredQty = $quantity;
    foreach ($selectedIngredientIds as $inventoryItemId) {
        $ensureInventoryRequestAvailability(
            $db,
            $inventoryItemId,
            $ingredientRequiredQty,
            $actorId,
            'Please select ingredient items for this order.',
            'Selected flavor is unavailable because the linked ingredient record does not exist.',
            'Selected flavor is unavailable because the linked inventory ingredient is not approved yet.',
            'Quantity must be greater than zero.',
            'Flavor unavailable for this order. Inventory Department has been alerted for purchase order preparation.',
            'Inventory Department received a low-stock update from Sales while the flavor was still available.'
        );
    }

    $cupRequiredQty = $quantity;
    if ($cupRequiredQty > 0) {
        $cupInventoryItemId = $resolveInventoryUtilityItemId($db, 'cup');
        if ($cupInventoryItemId <= 0) {
            throw new RuntimeException('Flavor unavailable for this order. Cup inventory item is not configured or approved.');
        }

        $ensureInventoryRequestAvailability(
            $db,
            $cupInventoryItemId,
            $cupRequiredQty,
            $actorId,
            'Flavor unavailable for this order. Cup inventory item is required.',
            'Flavor unavailable for this order. Cup inventory item does not exist.',
            'Flavor unavailable for this order. Cup inventory item is not approved yet.',
            'Quantity must be greater than zero.',
            'Flavor unavailable for this order. Cup stock is insufficient. Inventory Department has been alerted for purchase order preparation.',
            'Inventory Department received a low-stock update from Sales cup consumption while stock was still available.'
        );
    }

    $strawRequiredQty = $quantity;
    if ($strawRequiredQty > 0) {
        $strawInventoryItemId = $resolveInventoryUtilityItemId($db, 'straw');
        if ($strawInventoryItemId <= 0) {
            throw new RuntimeException('Flavor unavailable for this order. Straw inventory item is not configured or approved.');
        }

        $ensureInventoryRequestAvailability(
            $db,
            $strawInventoryItemId,
            $strawRequiredQty,
            $actorId,
            'Flavor unavailable for this order. Straw inventory item is required.',
            'Flavor unavailable for this order. Straw inventory item does not exist.',
            'Flavor unavailable for this order. Straw inventory item is not approved yet.',
            'Quantity must be greater than zero.',
            'Flavor unavailable for this order. Straw stock is insufficient. Inventory Department has been alerted for purchase order preparation.',
            'Inventory Department received a low-stock update from Sales straw consumption while stock was still available.'
        );
    }
};

$ensureProductionIngredientAvailability = static function (
    PDO $db,
    array $inventoryItemIds,
    float $quantityPrepared,
    int $actorId
) use ($ensureInventoryRequestAvailability): void {
    $selectedIngredientIds = normalize_inventory_item_ids($inventoryItemIds);
    if ($selectedIngredientIds === []) {
        throw new RuntimeException('Please select ingredient items for this production request.');
    }

    foreach ($selectedIngredientIds as $inventoryItemId) {
        $ensureInventoryRequestAvailability(
            $db,
            $inventoryItemId,
            $quantityPrepared,
            $actorId,
            'Please select ingredient items for this production request.',
            'Selected production ingredient request cannot be completed because the linked inventory record does not exist.',
            'Selected production ingredient request cannot be completed because the inventory ingredient is not approved yet.',
            'Quantity prepared must be greater than zero.',
            'Ingredient request cannot be fulfilled. Inventory Department has been alerted for purchase order preparation.',
            'Inventory Department received a production ingredient request while the stock was already at/below reorder level.'
        );
    }
};

$prepareSalesOrderItems = static function (PDO $db, array $items) use ($buildRecipeIngredientIds): array {
    $preparedItems = [];
    $selectedIngredientIds = [];

    foreach ($items as $item) {
        $beverageName = (string) ($item['beverage_name'] ?? '');
        $recipeItems = fetch_recipe_items_by_beverage($db, $beverageName);
        $ingredientIds = $buildRecipeIngredientIds($recipeItems);
        if ($ingredientIds === []) {
            throw new RuntimeException('No active recipe configured for ' . $beverageName . '.');
        }

        foreach ($ingredientIds as $ingredientId) {
            $selectedIngredientIds[$ingredientId] = $ingredientId;
        }

        $preparedItems[] = [
            'beverage_name' => $beverageName,
            'quantity' => (int) ($item['quantity'] ?? 0),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'total_amount' => (float) ($item['total_amount'] ?? 0),
            'inventory_item_id' => $ingredientIds[0],
            'ingredient_item_ids' => inventory_item_ids_to_json($ingredientIds),
        ];
    }

    return [$preparedItems, array_values($selectedIngredientIds)];
};

$insertSalesOrderItems = static function (PDO $db, int $salesOrderId, array $items): void {
    if ($salesOrderId <= 0) {
        throw new RuntimeException('Sales order item rows require a valid sales order.');
    }

    if (!sales_order_items_table_exists($db)) {
        throw new RuntimeException('Sales order item table is missing. Run scripts/2026_05_18_sales_order_items.sql before processing multi-item sales.');
    }

    $stmt = $db->prepare('INSERT INTO sales_order_items
        (sales_order_id, beverage_name, quantity, unit_price, total_amount, inventory_item_id, ingredient_item_ids)
        VALUES (?, ?, ?, ?, ?, ?, ?)');

    foreach ($items as $item) {
        $stmt->execute([
            $salesOrderId,
            (string) ($item['beverage_name'] ?? ''),
            (int) ($item['quantity'] ?? 0),
            (float) ($item['unit_price'] ?? 0),
            (float) ($item['total_amount'] ?? 0),
            (int) ($item['inventory_item_id'] ?? 0),
            $item['ingredient_item_ids'] ?? null,
        ]);
    }
};

$deductInventory = static function (PDO $db, int $inventoryItemId, float $deductQuantity, int $actorId, string $reason) use ($upsertLowStockPurchaseRequest): void {
    if ($inventoryItemId <= 0 || $deductQuantity <= 0) {
        return;
    }

    $inventoryStmt = $db->prepare("SELECT id, item_name, unit, stock_qty, reorder_level, status FROM inventory_items WHERE id = ? FOR UPDATE");
    $inventoryStmt->execute([$inventoryItemId]);
    $inventory = $inventoryStmt->fetch();

    if (!$inventory) {
        throw new RuntimeException('Linked inventory item does not exist.');
    }

    if (($inventory['status'] ?? '') !== 'approved') {
        throw new RuntimeException('Linked inventory item must be approved before deduction.');
    }

    $currentStock = (float) ($inventory['stock_qty'] ?? 0);
    if ($currentStock < $deductQuantity) {
        throw new RuntimeException('Insufficient stock for ' . $inventory['item_name'] . '. Required: ' . number_format($deductQuantity, 2) . ' ' . $inventory['unit'] . '.');
    }

    $oldInventory = $inventory;

    $updateStmt = $db->prepare('UPDATE inventory_items SET stock_qty = stock_qty - ?, updated_at = NOW() WHERE id = ?');
    $updateStmt->execute([$deductQuantity, $inventoryItemId]);

    $updatedInventoryStmt = $db->prepare('SELECT * FROM inventory_items WHERE id = ?');
    $updatedInventoryStmt->execute([$inventoryItemId]);
    $newInventory = $updatedInventoryStmt->fetch() ?: null;

    write_audit_log(
        $db,
        'inventory',
        'inventory_items',
        $inventoryItemId,
        'system_update',
        $oldInventory,
        $newInventory,
        $actorId,
        $reason,
        'system'
    );

    $upsertLowStockPurchaseRequest(
        $db,
        $inventoryItemId,
        $actorId,
        'Inventory Department received a low-stock update after inventory deduction. ' . $reason,
        null,
        false
    );
};

$syncAutomatedMarketingCampaign = static function (PDO $db, array $salesRecord, int $approverId) use ($fetchRecord): void {
    $salesRecordId = (int) ($salesRecord['id'] ?? 0);
    if ($salesRecordId <= 0) {
        return;
    }

    $campaignName = 'AUTO-DIGITAL-' . date('Ymd');
    $salesTrend = fetch_sales_trend_snapshot($db, 7);
    $salesPerformance = fetch_sales_performance_snapshot($db, 30);
    $inventoryHealth = fetch_inventory_health_snapshot($db);

    $topBeverage = trim((string) ($salesPerformance['high_sales_beverage_name'] ?? ''));
    if ($topBeverage === '') {
        $topBeverage = trim((string) ($salesTrend['top_beverage_name'] ?? ''));
    }
    if ($topBeverage === '') {
        $topBeverage = trim((string) ($salesRecord['beverage_name'] ?? ''));
    }
    if ($topBeverage === '') {
        $topBeverage = 'Featured Beverage';
    }

    $lowSellingBeverage = trim((string) ($salesPerformance['low_sales_beverage_name'] ?? ''));
    if ($lowSellingBeverage === '') {
        $lowSellingBeverage = $topBeverage;
    }

    $direction = (string) ($salesTrend['direction'] ?? 'stable');
    $directionLabel = 'stable';
    if ($direction === 'up') {
        $directionLabel = 'upward';
    } elseif ($direction === 'down') {
        $directionLabel = 'downward';
    }

    $trendNotes = sprintf(
        'Auto-analysis from approved sales flow: 7-day revenue %s, today revenue %s vs average %s/day, high-sales beverage %s (%s qty), low-sales beverage %s (%s qty).',
        $directionLabel,
        number_format((float) ($salesTrend['today_revenue'] ?? 0), 2),
        number_format((float) ($salesTrend['avg_revenue_per_day'] ?? 0), 2),
        $topBeverage,
        number_format((float) ($salesPerformance['high_sales_qty'] ?? 0), 0),
        $lowSellingBeverage,
        number_format((float) ($salesPerformance['low_sales_qty'] ?? 0), 0)
    );

    $lowStockCount = (int) ($inventoryHealth['low_stock_count'] ?? 0);
    $lowStockItems = array_map(static function (array $item): string {
        return (string) ($item['item_name'] ?? 'Unknown Item');
    }, $inventoryHealth['low_items'] ?? []);
    $lowStockSummary = $lowStockItems ? implode(', ', $lowStockItems) : 'none';

    $promotionPlan = 'Auto digital promo priority: promote low-sales coffee ' . $lowSellingBeverage . ' via social feed, SMS, and checkout banner. '
        . 'Bundle with high-sales coffee ' . $topBeverage . ' to improve conversion and repeat buying.';

    if ($lowStockCount > 0) {
        $promotionPlan .= ' Inventory guard active: reduce exposure for low-stock items (' . $lowStockSummary . ') and prioritize available alternatives.';
    } else {
        $promotionPlan .= ' Inventory healthy: keep full campaign intensity and add retargeting for repeat buyers.';
    }

    $existingStmt = $db->prepare('SELECT * FROM marketing_campaigns WHERE campaign_name = ? LIMIT 1 FOR UPDATE');
    $existingStmt->execute([$campaignName]);
    $existing = $existingStmt->fetch() ?: null;

    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+3 days'));

    if ($existing) {
        $updateStmt = $db->prepare("UPDATE marketing_campaigns
            SET trend_notes = ?,
                promotion_plan = ?,
                start_date = ?,
                end_date = ?,
                status = 'approved',
                approved_by = ?,
                approval_note = ?,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = ?");
        $updateStmt->execute([
            $trendNotes,
            $promotionPlan,
            $startDate,
            $endDate,
            $approverId,
            'Auto-updated by approved sales order #' . $salesRecordId,
            (int) $existing['id'],
        ]);

        $updatedCampaign = $fetchRecord($db, 'marketing_campaigns', (int) $existing['id'], false);
        write_audit_log(
            $db,
            'marketing',
            'marketing_campaigns',
            (int) $existing['id'],
            'system_update',
            $existing,
            $updatedCampaign,
            $approverId,
            'Auto-updated digital promotion campaign from approved sales order #' . $salesRecordId . '.',
            'system'
        );

        return;
    }

    $insertStmt = $db->prepare("INSERT INTO marketing_campaigns
        (campaign_name, trend_notes, promotion_plan, start_date, end_date, status, submitted_by, approved_by, approval_note, approved_at)
        VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, ?, NOW())");
    $insertStmt->execute([
        $campaignName,
        $trendNotes,
        $promotionPlan,
        $startDate,
        $endDate,
        $salesRecord['submitted_by'] ?? null,
        $approverId,
        'Auto-generated by approved sales order #' . $salesRecordId,
    ]);

    $campaignId = (int) $db->lastInsertId();
    $newCampaign = $fetchRecord($db, 'marketing_campaigns', $campaignId, false);

    write_audit_log(
        $db,
        'marketing',
        'marketing_campaigns',
        $campaignId,
        'system_create',
        null,
        $newCampaign,
        $approverId,
        'Auto-created digital promotion campaign from approved sales order #' . $salesRecordId . '.',
        'system'
    );

    $approvalLogStmt = $db->prepare('INSERT INTO approval_logs (module, record_id, action, note, action_by) VALUES (?, ?, ?, ?, ?)');
    $approvalLogStmt->execute(['marketing', $campaignId, 'approved', 'Auto-approved marketing campaign from sales automation flow.', $approverId]);
};

$applyApprovalAutomation = static function (PDO $db, string $dept, array $record, int $approverId) use ($deductInventory, $fetchRecord, $resolveInventoryUtilityItemId, $syncAutomatedMarketingCampaign): void {
    if ($dept === 'purchasing') {
        $inventoryItemId = (int) ($record['inventory_item_id'] ?? 0);
        $requestedQty = (float) ($record['requested_qty'] ?? 0);
        $receivedQty = (float) ($record['received_qty'] ?? 0);

        if ($inventoryItemId <= 0 || $requestedQty <= 0) {
            throw new RuntimeException('Purchase request must include a valid inventory item and requested quantity.');
        }

        if ($receivedQty <= 0 || empty($record['received_verified_at'])) {
            throw new RuntimeException('Inventory must verify the received quantity before purchase restocking.');
        }

        $inventoryStmt = $db->prepare('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE');
        $inventoryStmt->execute([$inventoryItemId]);
        $inventory = $inventoryStmt->fetch();
        if (!$inventory) {
            throw new RuntimeException('Linked inventory item for purchase request does not exist.');
        }

        if (($inventory['status'] ?? '') !== 'approved') {
            throw new RuntimeException('Linked inventory item must be approved before purchase restocking.');
        }

        $oldInventory = $inventory;
        $updateInventory = $db->prepare('UPDATE inventory_items SET stock_qty = stock_qty + ?, updated_at = NOW() WHERE id = ?');
        $updateInventory->execute([$receivedQty, $inventoryItemId]);

        $updatedInventory = $fetchRecord($db, 'inventory_items', $inventoryItemId, false);
        write_audit_log(
            $db,
            'inventory',
            'inventory_items',
            $inventoryItemId,
            'system_update',
            $oldInventory,
            $updatedInventory,
            $approverId,
            'Auto-restocked inventory from approved purchase request #' . (int) ($record['id'] ?? 0) . ' using verified received quantity.',
            'system'
        );

        $requestCode = trim((string) ($record['request_code'] ?? ''));
        if ($requestCode === '') {
            $requestCode = 'PR#' . (int) ($record['id'] ?? 0);
        }

        $unitCost = (float) ($record['quoted_unit_cost'] ?? 0);
        $expenseAmount = $unitCost > 0 ? round($unitCost * $receivedQty, 2) : 0.0;
        if ($expenseAmount <= 0) {
            $expenseAmount = (float) ($record['estimated_total'] ?? 0);
        }

        $insertAccounting = $db->prepare("INSERT INTO accounting_entries
            (entry_type, source, amount, description, status, submitted_by, approved_by, approval_note, approved_at)
            VALUES ('expense', ?, ?, ?, 'approved', ?, ?, 'Auto-generated from approved purchase request.', NOW())");
        $insertAccounting->execute([
            'Purchase ' . $requestCode,
            $expenseAmount,
            'Auto-generated from purchasing approval flow. Ordered ' . number_format($requestedQty, 2) . ', received ' . number_format($receivedQty, 2) . '.',
            $record['submitted_by'] ?? null,
            $approverId,
        ]);

        $accountingId = (int) $db->lastInsertId();
        $accountingEntry = $fetchRecord($db, 'accounting_entries', $accountingId, false);
        write_audit_log(
            $db,
            'accounting',
            'accounting_entries',
            $accountingId,
            'system_create',
            null,
            $accountingEntry,
            $approverId,
            'Auto-created expense entry from approved purchase request ' . $requestCode . '.',
            'system'
        );

        return;
    }

    if ($dept === 'production') {
        $quantityPrepared = (float) ($record['quantity_prepared'] ?? 0);

        if ($quantityPrepared <= 0) {
            throw new RuntimeException('Production approval requires a valid quantity prepared.');
        }

        $recipeItems = fetch_recipe_items_by_beverage($db, (string) ($record['beverage_name'] ?? ''));
        if ($recipeItems !== []) {
            foreach ($recipeItems as $recipeItem) {
                $inventoryItemId = (int) ($recipeItem['inventory_item_id'] ?? 0);
                $requiredQty = (float) ($recipeItem['required_qty'] ?? 0) * $quantityPrepared;
                if ($inventoryItemId <= 0 || $requiredQty <= 0) {
                    continue;
                }

                $deductInventory(
                    $db,
                    $inventoryItemId,
                    $requiredQty,
                    $approverId,
                    'Auto-deducted recipe usage from approved production log #' . (int) $record['id']
                );
            }

            return;
        }

        $selectedIngredientIds = inventory_item_ids_from_record($record);
        if ($selectedIngredientIds === []) {
            throw new RuntimeException('Production approval requires ingredient selections and quantity prepared.');
        }

        foreach ($selectedIngredientIds as $inventoryItemId) {
            $deductInventory(
                $db,
                $inventoryItemId,
                $quantityPrepared,
                $approverId,
                'Auto-deducted ingredient usage from approved production log #' . (int) $record['id']
            );
        }

        return;
    }

    if ($dept !== 'sales') {
        return;
    }

    $quantity = (float) ($record['quantity'] ?? 0);
    if ($quantity <= 0) {
        throw new RuntimeException('Sales approval requires a valid quantity.');
    }

    // Ingredient deduction is handled by Production when the daily batch is logged.
    // Sales only records the transaction and generates accounting/CRM entries.

    $orderCode = (string) ($record['order_code'] ?? ('ORDER-' . (int) $record['id']));
    $totalAmount = (float) ($record['total_amount'] ?? 0);

    $insertAccounting = $db->prepare("INSERT INTO accounting_entries
        (entry_type, source, amount, description, status, submitted_by, approved_by, approval_note, approved_at)
        VALUES ('income', ?, ?, ?, 'approved', ?, ?, 'Auto-generated from approved sales order.', NOW())");
    $insertAccounting->execute([
        'Sales ' . $orderCode,
        $totalAmount,
        'Auto-generated from processed sales order flow.',
        $record['submitted_by'] ?? null,
        $approverId,
    ]);

    $accountingId = (int) $db->lastInsertId();
    $accountingEntry = $fetchRecord($db, 'accounting_entries', $accountingId, false);
    write_audit_log(
        $db,
        'accounting',
        'accounting_entries',
        $accountingId,
        'system_create',
        null,
        $accountingEntry,
        $approverId,
        'Auto-created accounting entry from approved sales order ' . $orderCode . '.',
        'system'
    );

    // Record COGS expense: sum ingredient costs consumed for each item in this sale.
    $cogsTotal = 0.0;
    $orderItems = sales_order_receipt_items($record, fetch_sales_order_items($db, (int) ($record['id'] ?? 0)));
    foreach ($orderItems as $orderItem) {
        $itemQuantity = (float) ($orderItem['quantity'] ?? 0);
        if ($itemQuantity <= 0) {
            continue;
        }

        $cogsRecipeItems = fetch_recipe_items_by_beverage($db, (string) ($orderItem['beverage_name'] ?? ''));
        foreach ($cogsRecipeItems as $cogsItem) {
            $cogsItemId = (int) ($cogsItem['inventory_item_id'] ?? 0);
            if ($cogsItemId <= 0) {
                continue;
            }
            $latestPurchase = $db->prepare(
                "SELECT quoted_unit_cost FROM purchase_requests
                 WHERE inventory_item_id = ? AND status = 'approved' AND quoted_unit_cost IS NOT NULL AND requested_qty > 0
                 ORDER BY approved_at DESC LIMIT 1"
            );
            $latestPurchase->execute([$cogsItemId]);
            $purchaseRow = $latestPurchase->fetch();
            if (!$purchaseRow) {
                continue;
            }
            $unitCost = (float) $purchaseRow['quoted_unit_cost'];
            $qtyUsed = (float) ($cogsItem['required_qty'] ?? 0) * $itemQuantity;
            $cogsTotal += $unitCost * $qtyUsed;
        }
    }
    $cogsTotal = round($cogsTotal, 2);

    if ($cogsTotal > 0) {
        $insertCogs = $db->prepare("INSERT INTO accounting_entries
            (entry_type, source, amount, description, status, submitted_by, approved_by, approval_note, approved_at)
            VALUES ('expense', ?, ?, ?, 'approved', ?, ?, 'Auto-generated COGS from approved sales order.', NOW())");
        $insertCogs->execute([
            'COGS ' . $orderCode,
            $cogsTotal,
            'Cost of goods sold for ' . (string) ($record['beverage_name'] ?? '') . ' (auto-computed from ingredient costs).',
            $record['submitted_by'] ?? null,
            $approverId,
        ]);

        $cogsEntryId = (int) $db->lastInsertId();
        $cogsEntry = $fetchRecord($db, 'accounting_entries', $cogsEntryId, false);
        write_audit_log(
            $db,
            'accounting',
            'accounting_entries',
            $cogsEntryId,
            'system_create',
            null,
            $cogsEntry,
            $approverId,
            'Auto-created COGS expense entry from approved sales order ' . $orderCode . '.',
            'system'
        );
    }

    $customerName = trim((string) ($record['customer_name'] ?? ''));
    if ($customerName === '') {
        return;
    }
    $customerTin = trim((string) ($record['customer_tin'] ?? ''));
    $customerTin = $customerTin !== '' ? $customerTin : null;

    $crmSelect = $db->prepare('SELECT * FROM crm_profiles WHERE customer_name = ? LIMIT 1 FOR UPDATE');
    $crmSelect->execute([$customerName]);
    $profile = $crmSelect->fetch();

    if ($profile) {
        $profileId = (int) $profile['id'];
        $oldProfile = $profile;

        $updateProfile = $db->prepare("UPDATE crm_profiles
            SET purchase_count = purchase_count + 1,
                total_spent = total_spent + ?,
                last_purchase_at = NOW(),
                customer_tin = COALESCE(?, customer_tin),
                status = 'approved',
                approved_by = ?,
                approval_note = 'Auto-updated from approved sales order.',
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = ?");
        $updateProfile->execute([$totalAmount, $customerTin, $approverId, $profileId]);

        $updatedProfile = $fetchRecord($db, 'crm_profiles', $profileId, false);
        write_audit_log(
            $db,
            'crm',
            'crm_profiles',
            $profileId,
            'system_update',
            $oldProfile,
            $updatedProfile,
            $approverId,
            'Auto-updated CRM profile from approved sales order ' . $orderCode . '.',
            'system'
        );
    } else {
        $insertProfile = $db->prepare("INSERT INTO crm_profiles
            (customer_name, customer_tin, contact_no, preferences, last_purchase_at, purchase_count, total_spent, status, submitted_by, approved_by, approval_note, approved_at)
            VALUES (?, ?, NULL, NULL, NOW(), 1, ?, 'approved', ?, ?, 'Auto-created from approved sales order.', NOW())");
        $insertProfile->execute([$customerName, $customerTin, $totalAmount, $record['submitted_by'] ?? null, $approverId]);
        $profileId = (int) $db->lastInsertId();

        $newProfile = $fetchRecord($db, 'crm_profiles', $profileId, false);
        write_audit_log(
            $db,
            'crm',
            'crm_profiles',
            $profileId,
            'system_create',
            null,
            $newProfile,
            $approverId,
            'Auto-created CRM profile from approved sales order ' . $orderCode . '.',
            'system'
        );
    }

    $insertHistory = $db->prepare('INSERT INTO crm_purchase_history (profile_id, sales_order_id, amount, purchased_at) VALUES (?, ?, ?, NOW())');
    $insertHistory->execute([$profileId, (int) $record['id'], $totalAmount]);

    $historyId = (int) $db->lastInsertId();
    $historyRecord = $fetchRecord($db, 'crm_purchase_history', $historyId, false);
    write_audit_log(
        $db,
        'crm',
        'crm_purchase_history',
        $historyId,
        'system_create',
        null,
        $historyRecord,
        $approverId,
        'Auto-created CRM purchase history from approved sales order ' . $orderCode . '.',
        'system'
    );

    $syncAutomatedMarketingCampaign($db, $record, $approverId);
};

try {
    if (!is_valid_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        throw new RuntimeException('Invalid security token. Please refresh and try again.');
    }

    if ($action === 'create_record') {
        $config = $requireConfig($department);

        if (!$canCreateDepartmentRecord($user ?? [], $department)) {
            throw new RuntimeException('Unauthorized department access.');
        }

        if ($department === 'inventory') {
            throw new RuntimeException('Receiving inventory records manually is disabled for this workflow.');
        }

        [$data, $errors] = validate_department_input($validationConfigForDepartment($config, $department), $_POST);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }

        if ($department === 'marketing') {
            $startDate = (string) ($data['start_date'] ?? '');
            $endDate = (string) ($data['end_date'] ?? '');
            if ($startDate !== '' && $endDate !== '' && strtotime($endDate) < strtotime($startDate)) {
                throw new RuntimeException('End date must be later than or equal to start date.');
            }
        }

        $selectedProductionIngredientIds = [];
        $selectedSalesIngredientIds = [];
        $productionRecipeItems = [];
        $salesRecipeItems = [];
        $salesOrderItems = [];

        if ($department === 'production') {
            $data['quantity_prepared'] = (int) ($data['quantity_prepared'] ?? 0);
            $data['ingredient_used_qty'] = (float) ($data['quantity_prepared'] ?? 0);
            $productionRecipeItems = fetch_recipe_items_by_beverage($pdo, (string) ($data['beverage_name'] ?? ''));
            $selectedProductionIngredientIds = $buildRecipeIngredientIds($productionRecipeItems);
            if ($selectedProductionIngredientIds === []) {
                throw new RuntimeException('No active recipe configured for this beverage.');
            }

            $data['ingredient_item_ids'] = inventory_item_ids_to_json($selectedProductionIngredientIds);
            $data['inventory_item_id'] = $selectedProductionIngredientIds[0];
        }

        if ($department === 'purchasing') {
            $data['requested_qty'] = (float) ($data['requested_qty'] ?? 0);
            $quotedUnitCost = $data['quoted_unit_cost'] ?? null;
            $data['quoted_unit_cost'] = $quotedUnitCost === null ? null : (float) $quotedUnitCost;
            $data['request_code'] = ($data['request_code'] ?? null) ?: next_purchase_request_code($pdo);
            $data['estimated_total'] = $data['quoted_unit_cost'] === null ? 0 : round((float) $data['quoted_unit_cost'] * $data['requested_qty'], 2);
            $sourceDepartment = resolve_purchase_request_source_department($user ?? [], $redirectDepartment);
            if (in_array($sourceDepartment, ['production', 'inventory'], true)) {
                $sourceNote = $sourceDepartment === 'production'
                    ? '[Production Purchase Request]'
                    : '[Inventory Purchase Order]';
                $existingNote = trim((string) ($data['notes'] ?? ''));
                $data['notes'] = trim($sourceNote . ($existingNote !== '' ? ' ' . $existingNote : ''));

                if ($sourceDepartment === 'inventory') {
                    $data['inventory_confirmed_by'] = (int) ($user['id'] ?? 0);
                    $data['inventory_confirmed_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        if ($department === 'sales') {
            [$salesInputItems, $salesItemErrors] = validate_sales_order_items_input($_POST);
            if ($salesItemErrors) {
                throw new RuntimeException(implode(' ', $salesItemErrors));
            }

            [$salesOrderItems, $selectedSalesIngredientIds] = $prepareSalesOrderItems($pdo, $salesInputItems);
            [$salesItemSummary, $salesTotals] = summarize_sales_order_items($salesOrderItems);

            $data['beverage_name'] = $salesItemSummary;
            $data['quantity'] = (int) ($salesTotals['quantity'] ?? 0);
            $data['unit_price'] = (float) ($salesTotals['unit_price'] ?? 0);
            $data['per_cup_qty'] = 1.0;
            $data['per_straw_qty'] = 1.0;
            $data['stock_deduct_qty'] = 1.0;

            $data['ingredient_item_ids'] = inventory_item_ids_to_json($selectedSalesIngredientIds);
            $data['inventory_item_id'] = $selectedSalesIngredientIds[0];
            $data['order_code'] = ($data['order_code'] ?? null) ?: next_order_code($pdo);
            $data['payment_method'] = (string) ($data['payment_method'] ?? 'cash');
            $data['payment_reference'] = $data['payment_reference'] ?? null;
            $data['payment_status'] = 'paid';
            $data['receipt_no'] = next_receipt_code($pdo);
            $data['paid_at'] = date('Y-m-d H:i:s');
            $data['total_amount'] = (float) ($salesTotals['total_amount'] ?? 0);
        }

        if (in_array($department, ['sales', 'crm'], true)) {
            $data['customer_tin'] = $normalizeCustomerTin($data['customer_tin'] ?? null);
        }

        $table = $config['table'];
        $data['status'] = 'pending';
        $data['submitted_by'] = (int) ($user['id'] ?? 0);
        $realTimeSales = $department === 'sales' && REALTIME_SALES_MODE;
        $realTimeProduction = $department === 'production';
        if ($realTimeSales || $realTimeProduction) {
            $data['status'] = 'approved';
            $data['approved_by'] = (int) ($user['id'] ?? 0);
            $data['approval_note'] = $realTimeProduction
                ? 'Auto-approved on save. Production records are locked after submission.'
                : 'Auto-approved in real-time POS mode.';
            $data['approved_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnSql = implode(', ', $columns);

        if ($department === 'production') {
            $ensureRecipeIngredientAvailability(
                $pdo,
                $productionRecipeItems,
                (float) ($data['quantity_prepared'] ?? 0),
                (int) ($user['id'] ?? 0),
                'production log'
            );
        }

        $pdo->beginTransaction();

        if ($department === 'purchasing') {
            $inventoryLinkStmt = $pdo->prepare('SELECT id FROM inventory_items WHERE id = ? LIMIT 1 FOR UPDATE');
            $inventoryLinkStmt->execute([(int) ($data['inventory_item_id'] ?? 0)]);
            if (!$inventoryLinkStmt->fetch()) {
                throw new RuntimeException('Selected ingredient item for purchasing does not exist.');
            }
        }

        $stmt = $pdo->prepare("INSERT INTO {$table} ({$columnSql}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));

        $recordId = (int) $pdo->lastInsertId();
        if ($department === 'sales') {
            $insertSalesOrderItems($pdo, $recordId, $salesOrderItems);
        }

        $createdRecord = $fetchRecord($pdo, $table, $recordId, false);

        $createAuditNote = $realTimeSales
            ? 'Record created and processed in real-time POS mode.'
            : 'Record created and queued for manager review.';
        if ($department === 'purchasing') {
            $sourceDepartment = resolve_purchase_request_source_department($user ?? [], $redirectDepartment);
            if ($sourceDepartment === 'production') {
                $createAuditNote = 'Production Department sent a purchase request to Inventory review.';
            } elseif ($sourceDepartment === 'inventory') {
                $createAuditNote = 'Inventory Department confirmed a purchase order and sent it to Purchasing.';
            }
        }

        write_audit_log(
            $pdo,
            $department,
            $table,
            $recordId,
            'create',
            null,
            $createdRecord,
            (int) ($user['id'] ?? 0),
            $createAuditNote,
            'user'
        );

        if ($realTimeSales || $realTimeProduction) {
            $applyApprovalAutomation($pdo, $department, $createdRecord ?? [], (int) ($user['id'] ?? 0));

            $autoNote = $realTimeProduction
                ? 'Auto-approved on save. Production record locked.'
                : 'Auto-approved in real-time POS mode.';
            $log = $pdo->prepare('INSERT INTO approval_logs (module, record_id, action, note, action_by) VALUES (?, ?, ?, ?, ?)');
            $log->execute([
                $department,
                $recordId,
                'approved',
                $autoNote,
                (int) ($user['id'] ?? 0),
            ]);
        }

        $pdo->commit();

        $successMessage = ($realTimeSales || $realTimeProduction)
            ? (department_label($department) . ' record saved and locked.')
            : (department_label($department) . ' record created and queued for manager review.');
        if ($department === 'purchasing') {
            $sourceDepartment = resolve_purchase_request_source_department($user ?? [], $redirectDepartment);
            if ($sourceDepartment === 'production') {
                $successMessage = 'Purchase request sent to Inventory Department.';
            } elseif ($sourceDepartment === 'inventory') {
                $successMessage = 'Purchase order confirmed and sent to Purchasing Department.';
            }
        }

        set_flash('success', $successMessage);
        $redirectToDepartment($redirectDepartment);
    }

    if ($action === 'edit_record') {
        $config = $requireConfig($department);

        if (!can_user_access_department($user ?? [], $department)) {
            throw new RuntimeException('Unauthorized department access.');
        }

        if ($department === 'inventory') {
            throw new RuntimeException('Editing inventory records directly is disabled for this workflow.');
        }

        if ($department === 'production') {
            throw new RuntimeException('Production records are locked after saving and cannot be edited.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid record ID.');
        }

        $table = $config['table'];

        $record = $fetchRecord($pdo, $table, $id, false);
        if (!$record) {
            throw new RuntimeException('Record not found.');
        }

        if ($department === 'sales' && ($record['status'] ?? '') === 'approved') {
            throw new RuntimeException('Sales records cannot be edited after they have been processed.');
        }

        $assertOwnsRecord($user ?? [], $record);

        [$data, $errors] = validate_department_input($validationConfigForDepartment($config, $department), $_POST);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }

        if ($department === 'marketing') {
            $startDate = (string) ($data['start_date'] ?? '');
            $endDate = (string) ($data['end_date'] ?? '');
            if ($startDate !== '' && $endDate !== '' && strtotime($endDate) < strtotime($startDate)) {
                throw new RuntimeException('End date must be later than or equal to start date.');
            }
        }

        $selectedProductionIngredientIds = [];
        $selectedSalesIngredientIds = [];
        $productionRecipeItems = [];
        $salesRecipeItems = [];
        $salesOrderItems = [];

        if ($department === 'production') {
            $data['quantity_prepared'] = (int) ($data['quantity_prepared'] ?? 0);
            $data['ingredient_used_qty'] = (float) ($data['quantity_prepared'] ?? 0);
            $productionRecipeItems = fetch_recipe_items_by_beverage($pdo, (string) ($data['beverage_name'] ?? ''));
            $selectedProductionIngredientIds = $buildRecipeIngredientIds($productionRecipeItems);
            if ($selectedProductionIngredientIds === []) {
                throw new RuntimeException('No active recipe configured for this beverage.');
            }

            $data['ingredient_item_ids'] = inventory_item_ids_to_json($selectedProductionIngredientIds);
            $data['inventory_item_id'] = $selectedProductionIngredientIds[0];
        }

        if ($department === 'purchasing') {
            $data['requested_qty'] = (float) ($data['requested_qty'] ?? 0);
            $quotedUnitCost = $data['quoted_unit_cost'] ?? null;
            $data['quoted_unit_cost'] = $quotedUnitCost === null ? null : (float) $quotedUnitCost;
            $data['request_code'] = ($data['request_code'] ?? null) ?: (string) ($record['request_code'] ?? next_purchase_request_code($pdo));
            $data['estimated_total'] = $data['quoted_unit_cost'] === null ? 0 : round((float) $data['quoted_unit_cost'] * $data['requested_qty'], 2);
        }

        if ($department === 'sales') {
            [$salesInputItems, $salesItemErrors] = validate_sales_order_items_input($_POST);
            if ($salesItemErrors) {
                throw new RuntimeException(implode(' ', $salesItemErrors));
            }

            [$salesOrderItems, $selectedSalesIngredientIds] = $prepareSalesOrderItems($pdo, $salesInputItems);
            [$salesItemSummary, $salesTotals] = summarize_sales_order_items($salesOrderItems);

            $data['beverage_name'] = $salesItemSummary;
            $data['quantity'] = (int) ($salesTotals['quantity'] ?? 0);
            $data['unit_price'] = (float) ($salesTotals['unit_price'] ?? 0);
            $data['stock_deduct_qty'] = 1.0;
            $data['per_cup_qty'] = 1.0;
            $data['per_straw_qty'] = 1.0;

            $data['ingredient_item_ids'] = inventory_item_ids_to_json($selectedSalesIngredientIds);
            $data['inventory_item_id'] = $selectedSalesIngredientIds[0];
            $data['order_code'] = ($data['order_code'] ?? null) ?: (string) ($record['order_code'] ?? next_order_code($pdo));
            $data['payment_method'] = (string) ($data['payment_method'] ?? ($record['payment_method'] ?? 'cash'));
            $data['payment_reference'] = $data['payment_reference'] ?? null;
            $data['payment_status'] = 'paid';
            $data['receipt_no'] = (string) ($record['receipt_no'] ?? '');
            if ($data['receipt_no'] === '') {
                $data['receipt_no'] = next_receipt_code($pdo);
            }
            $data['paid_at'] = (string) ($record['paid_at'] ?? '');
            if ($data['paid_at'] === '') {
                $data['paid_at'] = date('Y-m-d H:i:s');
            }
            $data['total_amount'] = (float) ($salesTotals['total_amount'] ?? 0);
        }

        if (in_array($department, ['sales', 'crm'], true)) {
            $data['customer_tin'] = $normalizeCustomerTin($data['customer_tin'] ?? null);
        }

        if ($department === 'production') {
            $ensureRecipeIngredientAvailability(
                $pdo,
                $productionRecipeItems,
                (float) ($data['quantity_prepared'] ?? 0),
                (int) ($user['id'] ?? 0),
                'production log'
            );
        }

        $pdo->beginTransaction();

        $record = $fetchRecord($pdo, $table, $id, true);
        if (!$record) {
            throw new RuntimeException('Record not found.');
        }

        $assertOwnsRecord($user ?? [], $record);

        if ($department === 'purchasing') {
            $inventoryLinkStmt = $pdo->prepare('SELECT id FROM inventory_items WHERE id = ? LIMIT 1 FOR UPDATE');
            $inventoryLinkStmt->execute([(int) ($data['inventory_item_id'] ?? 0)]);
            if (!$inventoryLinkStmt->fetch()) {
                throw new RuntimeException('Selected ingredient item for purchasing does not exist.');
            }
        }

        $realTimeSales = $department === 'sales' && REALTIME_SALES_MODE;
        $nextStatus = $realTimeSales ? 'approved' : 'pending';
        $nextApprovedBy = $realTimeSales ? (int) ($user['id'] ?? 0) : null;
        $nextApprovalNote = $realTimeSales ? 'Auto-approved in real-time POS mode.' : null;
        $nextApprovedAt = $realTimeSales ? date('Y-m-d H:i:s') : null;

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = ?';
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $assignments) . ", status = ?, approved_by = ?, approval_note = ?, approved_at = ?, submitted_by = ?, updated_at = NOW() WHERE id = ?";
        $params = array_values($data);
        $params[] = $nextStatus;
        $params[] = $nextApprovedBy;
        $params[] = $nextApprovalNote;
        $params[] = $nextApprovedAt;
        $params[] = (int) ($user['id'] ?? 0);
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($department === 'sales') {
            if (!sales_order_items_table_exists($pdo)) {
                throw new RuntimeException('Sales order item table is missing. Run scripts/2026_05_18_sales_order_items.sql before processing multi-item sales.');
            }

            $deleteItems = $pdo->prepare('DELETE FROM sales_order_items WHERE sales_order_id = ?');
            $deleteItems->execute([$id]);
            $insertSalesOrderItems($pdo, $id, $salesOrderItems);
        }

        $updatedRecord = $fetchRecord($pdo, $table, $id, false);
        write_audit_log(
            $pdo,
            $department,
            $table,
            $id,
            'edit',
            $record,
            $updatedRecord,
            (int) ($user['id'] ?? 0),
            $realTimeSales
                ? 'Record edited and re-processed in real-time POS mode.'
                : 'Record edited and re-queued for manager review.',
            'user'
        );

        if ($realTimeSales) {
            $applyApprovalAutomation($pdo, $department, $updatedRecord ?? [], (int) ($user['id'] ?? 0));

            $log = $pdo->prepare('INSERT INTO approval_logs (module, record_id, action, note, action_by) VALUES (?, ?, ?, ?, ?)');
            $log->execute([
                $department,
                $id,
                'approved',
                'Auto-approved in real-time POS mode.',
                (int) ($user['id'] ?? 0),
            ]);
        }

        $pdo->commit();

        set_flash(
            'success',
            $realTimeSales
                ? 'Record updated and processed in real-time.'
                : 'Record updated and re-queued for manager review.'
        );
        $redirectToDepartment($redirectDepartment);
    }

    if ($action === 'delete_record') {
        $config = $requireConfig($department);

        if (!can_user_access_department($user ?? [], $department)) {
            throw new RuntimeException('Unauthorized department access.');
        }

        if ($department === 'inventory') {
            throw new RuntimeException('Deleting inventory records directly is disabled for this workflow.');
        }

        if ($department === 'production') {
            throw new RuntimeException('Production records are locked after saving and cannot be deleted.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid record ID.');
        }

        $table = $config['table'];
        $pdo->beginTransaction();

        $record = $fetchRecord($pdo, $table, $id, true);
        if (!$record) {
            throw new RuntimeException('Record not found.');
        }

        $assertOwnsRecord($user ?? [], $record);

        write_audit_log(
            $pdo,
            $department,
            $table,
            $id,
            'delete',
            $record,
            null,
            (int) ($user['id'] ?? 0),
            'Record deleted before approval.',
            'user'
        );

        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        set_flash('success', 'Record deleted successfully.');
        $redirectToDepartment($department);
    }

    if ($action === 'inventory_prepare_purchase_order') {
        if (!can_user_access_department($user ?? [], 'inventory')) {
            throw new RuntimeException('Unauthorized department access.');
        }

        $config = $requireConfig('purchasing');
        [$data, $errors] = validate_department_input($config, $_POST);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid purchase order ID.');
        }

        $data['requested_qty'] = (float) ($data['requested_qty'] ?? 0);
        $quotedUnitCost = $data['quoted_unit_cost'] ?? null;
        $data['quoted_unit_cost'] = $quotedUnitCost === null ? null : (float) $quotedUnitCost;
        $data['estimated_total'] = $data['quoted_unit_cost'] === null ? 0 : round((float) $data['quoted_unit_cost'] * $data['requested_qty'], 2);
        $existingNote = trim((string) ($data['notes'] ?? ''));
        if (strpos($existingNote, '[Inventory Purchase Order]') === false) {
            $data['notes'] = trim('[Inventory Purchase Order]' . ($existingNote !== '' ? ' ' . $existingNote : ''));
        }

        $pdo->beginTransaction();

        $table = $config['table'];
        $record = $fetchRecord($pdo, $table, $id, true);
        if (!$record) {
            throw new RuntimeException('Purchase order not found.');
        }

        if (!can_inventory_confirm_purchase_order($record)) {
            throw new RuntimeException('Only pending purchase requests awaiting Inventory review can be confirmed as purchase orders.');
        }

        $inventoryLinkStmt = $pdo->prepare('SELECT id FROM inventory_items WHERE id = ? LIMIT 1 FOR UPDATE');
        $inventoryLinkStmt->execute([(int) ($data['inventory_item_id'] ?? 0)]);
        if (!$inventoryLinkStmt->fetch()) {
            throw new RuntimeException('Selected inventory item for purchase order does not exist.');
        }

        $update = $pdo->prepare("UPDATE {$table}
            SET inventory_item_id = ?,
                requested_qty = ?,
                supplier_name = ?,
                quoted_unit_cost = ?,
                estimated_total = ?,
                expected_delivery_date = ?,
                notes = ?,
                inventory_confirmed_by = ?,
                inventory_confirmed_at = NOW(),
                purchasing_processed_by = NULL,
                purchasing_processed_at = NULL,
                purchasing_note = NULL,
                received_qty = NULL,
                received_verified_by = NULL,
                received_verified_at = NULL,
                receiving_note = NULL,
                approved_by = NULL,
                approval_note = NULL,
                approved_at = NULL,
                submitted_by = ?,
                updated_at = NOW()
            WHERE id = ?");
        $update->execute([
            (int) ($data['inventory_item_id'] ?? 0),
            (float) ($data['requested_qty'] ?? 0),
            $data['supplier_name'] ?? null,
            $data['quoted_unit_cost'],
            (float) ($data['estimated_total'] ?? 0),
            $data['expected_delivery_date'] ?? null,
            $data['notes'] ?? null,
            (int) ($user['id'] ?? 0),
            (int) ($user['id'] ?? 0),
            $id,
        ]);

        $updatedRecord = $fetchRecord($pdo, $table, $id, false);
        write_audit_log(
            $pdo,
            'inventory',
            $table,
            $id,
            'confirm_purchase_order',
            $record,
            $updatedRecord,
            (int) ($user['id'] ?? 0),
            'Inventory Department confirmed purchase order #' . $id . ' and forwarded it to Purchasing.',
            'user'
        );

        $pdo->commit();

        set_flash('success', 'Purchase order #' . $id . ' confirmed and sent to Purchasing.');
        $redirectToDepartment('inventory');
    }

    if ($action === 'purchasing_decide_purchase_order') {
        if (!can_user_access_department($user ?? [], 'purchasing')) {
            throw new RuntimeException('Unauthorized department access.');
        }

        $config = $requireConfig('purchasing');
        $table = $config['table'];
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid purchase order ID.');
        }

        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['processed', 'rejected'], true)) {
            throw new RuntimeException('Invalid purchase order decision.');
        }

        $approvalNote = trim((string) ($_POST['approval_note'] ?? ''));

        $pdo->beginTransaction();

        $record = $fetchRecord($pdo, $table, $id, true);
        if (!$record) {
            throw new RuntimeException('Purchase order not found.');
        }

        if (!can_purchasing_process_purchase_order($record)) {
            throw new RuntimeException('Only Inventory-confirmed purchase orders can be made by Purchasing.');
        }

        if ($decision === 'processed') {
            $update = $pdo->prepare("UPDATE {$table}
                SET purchasing_processed_by = ?,
                    purchasing_processed_at = NOW(),
                    purchasing_note = ?,
                    updated_at = NOW()
                WHERE id = ?");
            $update->execute([(int) ($user['id'] ?? 0), $approvalNote !== '' ? $approvalNote : null, $id]);
        } else {
            $update = $pdo->prepare("UPDATE {$table}
                SET status = 'rejected',
                    approved_by = ?,
                    approval_note = ?,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?");
            $update->execute([(int) ($user['id'] ?? 0), $approvalNote !== '' ? $approvalNote : null, $id]);
        }

        $updatedRecord = $fetchRecord($pdo, $table, $id, false);
        $auditAction = $decision === 'processed' ? 'process_purchase_order' : 'rejected';
        write_audit_log(
            $pdo,
            'purchasing',
            $table,
            $id,
            $auditAction,
            $record,
            $updatedRecord,
            (int) ($user['id'] ?? 0),
            $approvalNote !== ''
                ? $approvalNote
                : ($decision === 'processed'
                    ? 'Purchase order made by Purchasing Department and sent to Inventory for received quantity verification.'
                    : 'Purchase order rejected by Purchasing Department.'),
            'user'
        );

        if ($decision === 'rejected') {
            $log = $pdo->prepare('INSERT INTO approval_logs (module, record_id, action, note, action_by) VALUES (?, ?, ?, ?, ?)');
            $log->execute(['purchasing', $id, 'rejected', $approvalNote !== '' ? $approvalNote : null, (int) ($user['id'] ?? 0)]);
        }

        $pdo->commit();

        set_flash(
            'success',
            $decision === 'processed'
                ? 'Purchase order #' . $id . ' made and sent to Inventory for received quantity verification.'
                : 'Purchase order #' . $id . ' has been rejected.'
        );
        $redirectToDepartment('purchasing');
    }

    if ($action === 'inventory_verify_purchase_receipt') {
        if (!can_user_access_department($user ?? [], 'inventory')) {
            throw new RuntimeException('Unauthorized department access.');
        }

        $config = $requireConfig('purchasing');
        $table = $config['table'];
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid purchase order ID.');
        }

        $receivedQtyRaw = trim((string) ($_POST['received_qty'] ?? ''));
        if ($receivedQtyRaw === '' || !is_numeric($receivedQtyRaw)) {
            throw new RuntimeException('Received quantity must be a valid number.');
        }

        $receivedQty = round((float) $receivedQtyRaw, 2);
        if ($receivedQty <= 0) {
            throw new RuntimeException('Received quantity must be greater than zero.');
        }

        $receivingNote = trim((string) ($_POST['receiving_note'] ?? ''));
        if (strlen($receivingNote) > 2000) {
            throw new RuntimeException('Receiving note must be 2000 characters or less.');
        }

        $pdo->beginTransaction();

        $record = $fetchRecord($pdo, $table, $id, true);
        if (!$record) {
            throw new RuntimeException('Purchase order not found.');
        }

        if (!can_inventory_verify_received_purchase_order($record)) {
            throw new RuntimeException('Only purchase orders made by Purchasing can be verified as received by Inventory.');
        }

        $orderedQty = (float) ($record['requested_qty'] ?? 0);
        if (abs($receivedQty - $orderedQty) > 0.0001 && $receivingNote === '') {
            throw new RuntimeException('Receiving note is required when received quantity differs from ordered quantity.');
        }

        $update = $pdo->prepare("UPDATE {$table}
            SET received_qty = ?,
                received_verified_by = ?,
                received_verified_at = NOW(),
                receiving_note = ?,
                approved_by = NULL,
                approval_note = NULL,
                approved_at = NULL,
                updated_at = NOW()
            WHERE id = ?");
        $update->execute([
            $receivedQty,
            (int) ($user['id'] ?? 0),
            $receivingNote !== '' ? $receivingNote : null,
            $id,
        ]);

        $updatedRecord = $fetchRecord($pdo, $table, $id, false);
        write_audit_log(
            $pdo,
            'inventory',
            $table,
            $id,
            'verify_purchase_receipt',
            $record,
            $updatedRecord,
            (int) ($user['id'] ?? 0),
            'Inventory Department verified received quantity for purchase order #' . $id . '.',
            'user'
        );

        $pdo->commit();

        set_flash('success', 'Received quantity for purchase order #' . $id . ' verified and sent to General Manager for final approval.');
        $redirectToDepartment('inventory');
    }

    if ($action === 'approve_record' || $action === 'reject_record') {
        require_general_manager();

        $config = $requireConfig($department);
        $table = $config['table'];

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid record ID.');
        }

        $decision = $action === 'approve_record' ? 'approved' : 'rejected';
        $approvalNote = trim((string) ($_POST['approval_note'] ?? ''));

        $pdo->beginTransaction();

        $record = $fetchRecord($pdo, $table, $id, true);
        if (!$record) {
            throw new RuntimeException('Record not found.');
        }

        if (($record['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Only pending records can be processed.');
        }

        if ($department === 'purchasing' && !can_general_manager_finalize_purchase_order($record)) {
            throw new RuntimeException('Purchase order must be confirmed by Inventory, made by Purchasing, and verified as received by Inventory before General Manager final approval.');
        }

        if ($decision === 'approved') {
            $applyApprovalAutomation($pdo, $department, $record, (int) ($user['id'] ?? 0));
        }

        $update = $pdo->prepare("UPDATE {$table} SET status = ?, approved_by = ?, approval_note = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?");
        $update->execute([$decision, (int) ($user['id'] ?? 0), $approvalNote !== '' ? $approvalNote : null, $id]);

        $updatedRecord = $fetchRecord($pdo, $table, $id, false);

        if ($decision === 'approved' && $department === 'inventory') {
            $upsertLowStockPurchaseRequest(
                $pdo,
                (int) ($updatedRecord['id'] ?? 0),
                (int) ($user['id'] ?? 0),
                'Inventory Department reviewed stock levels and sent a low-stock update from inventory record #' . $id . '.',
                null,
                false
            );
        }

        write_audit_log(
            $pdo,
            $department,
            $table,
            $id,
            $decision,
            $record,
            $updatedRecord,
            (int) ($user['id'] ?? 0),
            $approvalNote !== '' ? $approvalNote : ('Record ' . $decision . ' by General Manager.'),
            'user'
        );

        $log = $pdo->prepare('INSERT INTO approval_logs (module, record_id, action, note, action_by) VALUES (?, ?, ?, ?, ?)');
        $log->execute([$department, $id, $decision, $approvalNote !== '' ? $approvalNote : null, (int) ($user['id'] ?? 0)]);

        $pdo->commit();

        set_flash('success', department_label($department) . ' record #' . $id . ' has been ' . $decision . '.');
        redirect('approvals.php');
    }

    throw new RuntimeException('Unsupported action.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $errorMessage = $exception->getMessage();
    $salesUnavailablePrefix = 'Flavor unavailable for this order.';
    $productionUnavailablePrefix = 'Ingredient request cannot be fulfilled.';

    if (
        $department === 'production'
        && in_array($action, ['create_record', 'edit_record'], true)
    ) {
        $isProductionEscalation = strncmp($errorMessage, $productionUnavailablePrefix, strlen($productionUnavailablePrefix)) === 0;

        if ($isProductionEscalation) {
            $inventoryItemIds = $excludeUtilityIngredientIds($pdo, $_POST['ingredient_item_ids'] ?? []);
            if ($inventoryItemIds === []) {
                $legacyInventoryItemId = (int) ($_POST['inventory_item_id'] ?? 0);
                if ($legacyInventoryItemId > 0) {
                    $inventoryItemIds = [$legacyInventoryItemId];
                }
            }

            $requiredQty = (float) ($_POST['quantity_prepared'] ?? 0);
            $requiredQtyByItemId = [];
            if ($inventoryItemIds === [] && $requiredQty > 0) {
                $recipeItems = fetch_recipe_items_by_beverage($pdo, (string) ($_POST['beverage_name'] ?? ''));
                foreach ($recipeItems as $recipeItem) {
                    $recipeInventoryItemId = (int) ($recipeItem['inventory_item_id'] ?? 0);
                    $recipeRequiredQty = (float) ($recipeItem['required_qty'] ?? 0) * $requiredQty;
                    if ($recipeInventoryItemId <= 0 || $recipeRequiredQty <= 0) {
                        continue;
                    }

                    $inventoryItemIds[$recipeInventoryItemId] = $recipeInventoryItemId;
                    $requiredQtyByItemId[$recipeInventoryItemId] = $recipeRequiredQty;
                }

                $inventoryItemIds = array_values($inventoryItemIds);
            }

            $purchaseReason = 'Inventory Department received a shortage alert from Production. Required %s but only %s available.';
            $fallbackMessage = 'Ingredient request cannot be fulfilled. Inventory Department was alerted, but the purchase request could not be created automatically. Please notify Inventory Department manually.';
            $missingInventoryMessage = 'Ingredient request cannot be fulfilled. Unable to locate linked inventory ingredient items for auto-escalation.';

            if ($inventoryItemIds !== [] && $requiredQty > 0) {
                try {
                    $pdo->beginTransaction();

                    $createdEscalation = false;

                    foreach ($inventoryItemIds as $inventoryItemId) {
                        $inventoryStmt = $pdo->prepare('SELECT stock_qty FROM inventory_items WHERE id = ? LIMIT 1 FOR UPDATE');
                        $inventoryStmt->execute([$inventoryItemId]);
                        $inventoryRow = $inventoryStmt->fetch() ?: null;
                        if ($inventoryRow === null) {
                            continue;
                        }

                        $availableQty = (float) ($inventoryRow['stock_qty'] ?? 0);
                        $itemRequiredQty = (float) ($requiredQtyByItemId[$inventoryItemId] ?? $requiredQty);
                        if ($availableQty >= $itemRequiredQty) {
                            continue;
                        }

                        $shortageQty = max($itemRequiredQty - $availableQty, 1);
                        $purchaseRequestId = $upsertLowStockPurchaseRequest(
                            $pdo,
                            $inventoryItemId,
                            (int) ($user['id'] ?? 0),
                            sprintf($purchaseReason, number_format($itemRequiredQty, 2), number_format($availableQty, 2)),
                            $shortageQty,
                            true
                        );

                        if ($purchaseRequestId !== null) {
                            $createdEscalation = true;
                        }
                    }

                    $pdo->commit();

                    if (!$createdEscalation) {
                        $errorMessage = $missingInventoryMessage;
                    }
                } catch (Throwable $escalationException) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $errorMessage = $fallbackMessage;
                }
            } else {
                $errorMessage = $missingInventoryMessage;
            }
        }
    }

    set_flash('error', $errorMessage);

    if (in_array($action, ['approve_record', 'reject_record'], true)) {
        redirect('approvals.php');
    }

    if ($department !== '') {
        $redirectToDepartment($redirectDepartment);
    }

    redirect('dashboard.php');
}
