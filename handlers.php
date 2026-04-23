<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

$action = $_POST['action'] ?? '';
$department = $_POST['dept'] ?? '';
$user = current_user();
$pdo = db();

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
        WHERE inventory_item_id = ? AND status = 'pending'
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
                    estimated_total = CASE
                        WHEN quoted_unit_cost IS NULL THEN estimated_total
                        ELSE ROUND(quoted_unit_cost * ?, 2)
                    END,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?");
            $updateStmt->execute([$targetRequestQty, $targetRequestQty, $newNotes, (int) $existing['id']]);

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
                'Auto-updated purchase request after Inventory forwarded a low-stock alert (' . ($inventory['item_name'] ?? 'Unknown Item') . ').',
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
        'Auto-created purchase request after Inventory forwarded a low-stock alert (' . ($inventory['item_name'] ?? 'Unknown Item') . ').',
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

$ensureSalesFlavorAvailability = static function (
    PDO $db,
    array $inventoryItemIds,
    float $quantity,
    float $stockDeductQty,
    float $perCupQty,
    float $perStrawQty,
    int $actorId
) use ($ensureInventoryRequestAvailability, $resolveInventoryUtilityItemId): void {
    $selectedIngredientIds = normalize_inventory_item_ids($inventoryItemIds);
    if ($selectedIngredientIds === []) {
        throw new RuntimeException('Please select ingredient items for this order.');
    }

    if ($quantity <= 0) {
        throw new RuntimeException('Quantity must be greater than zero.');
    }

    if ($stockDeductQty <= 0) {
        throw new RuntimeException('Stock deduct quantity must be greater than zero.');
    }

    if ($perCupQty < 0 || $perStrawQty < 0) {
        throw new RuntimeException('Per cup and per straw values cannot be negative.');
    }

    $ingredientRequiredQty = $quantity * $stockDeductQty;
    foreach ($selectedIngredientIds as $inventoryItemId) {
        $ensureInventoryRequestAvailability(
            $db,
            $inventoryItemId,
            $ingredientRequiredQty,
            $actorId,
            'Please select ingredient items for this order.',
            'Selected flavor is unavailable because the linked ingredient record does not exist.',
            'Selected flavor is unavailable because the linked inventory ingredient is not approved yet.',
            'Stock deduct quantity must be greater than zero.',
            'Flavor unavailable for this order. Inventory Department has been alerted and Purchasing Department has been notified.',
            'Inventory Department received a low-stock update from Sales while the flavor was still available.'
        );
    }

    $cupRequiredQty = $quantity * $perCupQty;
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
            'Per cup value must be greater than or equal to zero.',
            'Flavor unavailable for this order. Cup stock is insufficient. Inventory Department has been alerted and Purchasing Department has been notified.',
            'Inventory Department received a low-stock update from Sales cup consumption while stock was still available.'
        );
    }

    $strawRequiredQty = $quantity * $perStrawQty;
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
            'Per straw value must be greater than or equal to zero.',
            'Flavor unavailable for this order. Straw stock is insufficient. Inventory Department has been alerted and Purchasing Department has been notified.',
            'Inventory Department received a low-stock update from Sales straw consumption while stock was still available.'
        );
    }
};

$ensureProductionIngredientAvailability = static function (
    PDO $db,
    array $inventoryItemIds,
    float $ingredientUsedQty,
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
            $ingredientUsedQty,
            $actorId,
            'Please select ingredient items for this production request.',
            'Selected production ingredient request cannot be completed because the linked inventory record does not exist.',
            'Selected production ingredient request cannot be completed because the inventory ingredient is not approved yet.',
            'Ingredient used quantity must be greater than zero.',
            'Ingredient request cannot be fulfilled. Inventory Department has been alerted and Purchasing Department has been notified.',
            'Inventory Department received a production ingredient request while the stock was already at/below reorder level.'
        );
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

        if ($inventoryItemId <= 0 || $requestedQty <= 0) {
            throw new RuntimeException('Purchase request must include a valid inventory item and requested quantity.');
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
        $updateInventory->execute([$requestedQty, $inventoryItemId]);

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
            'Auto-restocked inventory from approved purchase request #' . (int) ($record['id'] ?? 0) . '.',
            'system'
        );

        return;
    }

    if ($dept === 'production') {
        $selectedIngredientIds = inventory_item_ids_from_record($record);
        $ingredientUsedQty = (float) ($record['ingredient_used_qty'] ?? 0);

        if ($selectedIngredientIds === [] || $ingredientUsedQty <= 0) {
            throw new RuntimeException('Production approval requires ingredient selections and ingredient used quantity.');
        }

        foreach ($selectedIngredientIds as $inventoryItemId) {
            $deductInventory(
                $db,
                $inventoryItemId,
                $ingredientUsedQty,
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
    $deductPerOrder = (float) ($record['stock_deduct_qty'] ?? 0);
    $selectedIngredientIds = inventory_item_ids_from_record($record);
    $totalIngredientDeduction = $quantity * $deductPerOrder;

    if ($selectedIngredientIds === [] || $totalIngredientDeduction <= 0) {
        throw new RuntimeException('Sales approval requires ingredient selections and stock deduct quantity.');
    }

    foreach ($selectedIngredientIds as $inventoryItemId) {
        $deductInventory(
            $db,
            $inventoryItemId,
            $totalIngredientDeduction,
            $approverId,
            'Auto-deducted stock from approved sales order #' . (int) $record['id']
        );
    }

    $cupRequiredQty = $quantity * (float) ($record['per_cup_qty'] ?? 0);
    if ($cupRequiredQty > 0) {
        $cupInventoryItemId = $resolveInventoryUtilityItemId($db, 'cup');
        if ($cupInventoryItemId <= 0) {
            throw new RuntimeException('Cup inventory item is not configured or approved.');
        }

        $deductInventory(
            $db,
            $cupInventoryItemId,
            $cupRequiredQty,
            $approverId,
            'Auto-deducted cup usage from approved sales order #' . (int) $record['id']
        );
    }

    $strawRequiredQty = $quantity * (float) ($record['per_straw_qty'] ?? 0);
    if ($strawRequiredQty > 0) {
        $strawInventoryItemId = $resolveInventoryUtilityItemId($db, 'straw');
        if ($strawInventoryItemId <= 0) {
            throw new RuntimeException('Straw inventory item is not configured or approved.');
        }

        $deductInventory(
            $db,
            $strawInventoryItemId,
            $strawRequiredQty,
            $approverId,
            'Auto-deducted straw usage from approved sales order #' . (int) $record['id']
        );
    }

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

    $customerName = trim((string) ($record['customer_name'] ?? ''));
    if ($customerName === '') {
        return;
    }

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
                status = 'approved',
                approved_by = ?,
                approval_note = 'Auto-updated from approved sales order.',
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = ?");
        $updateProfile->execute([$totalAmount, $approverId, $profileId]);

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
            (customer_name, contact_no, preferences, last_purchase_at, purchase_count, total_spent, status, submitted_by, approved_by, approval_note, approved_at)
            VALUES (?, NULL, NULL, NOW(), 1, ?, 'approved', ?, ?, 'Auto-created from approved sales order.', NOW())");
        $insertProfile->execute([$customerName, $totalAmount, $record['submitted_by'] ?? null, $approverId]);
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

        if (!can_user_access_department($user ?? [], $department)) {
            throw new RuntimeException('Unauthorized department access.');
        }

        [$data, $errors] = validate_department_input($config, $_POST);
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

        if ($department === 'production') {
            $data['quantity_prepared'] = (int) ($data['quantity_prepared'] ?? 0);
            $data['ingredient_used_qty'] = (float) ($data['ingredient_used_qty'] ?? 0);
            $selectedProductionIngredientIds = normalize_inventory_item_ids($data['ingredient_item_ids'] ?? []);
            if ($selectedProductionIngredientIds === []) {
                throw new RuntimeException('Please select ingredient items for this production request.');
            }

            $data['ingredient_item_ids'] = inventory_item_ids_to_json($selectedProductionIngredientIds);
            $data['inventory_item_id'] = $selectedProductionIngredientIds[0];
        }

        if ($department === 'purchasing') {
            $data['requested_qty'] = (float) ($data['requested_qty'] ?? 0);
            $quotedUnitCost = $data['quoted_unit_cost'] ?? null;
            $data['quoted_unit_cost'] = $quotedUnitCost === null ? null : (float) $quotedUnitCost;
            $data['request_code'] = ($data['request_code'] ?? null) ?: next_purchase_request_code($pdo);
            $data['estimated_total'] = $data['quoted_unit_cost'] === null
                ? 0
                : ($data['requested_qty'] * (float) $data['quoted_unit_cost']);
        }

        if ($department === 'sales') {
            $data['quantity'] = (int) ($data['quantity'] ?? 0);
            $data['unit_price'] = (float) ($data['unit_price'] ?? 0);
            $data['per_cup_qty'] = (float) ($data['per_cup_qty'] ?? 0);
            $data['per_straw_qty'] = (float) ($data['per_straw_qty'] ?? 0);
            $data['stock_deduct_qty'] = (float) ($data['stock_deduct_qty'] ?? 0);
            if ($data['stock_deduct_qty'] <= 0) {
                $data['stock_deduct_qty'] = $data['per_cup_qty'] + $data['per_straw_qty'];
            }
            if ($data['per_cup_qty'] < 0 || $data['per_straw_qty'] < 0) {
                throw new RuntimeException('Per cup and per straw values cannot be negative.');
            }
            if ($data['stock_deduct_qty'] <= 0) {
                throw new RuntimeException('Stock deduct quantity must be greater than zero.');
            }

            $selectedSalesIngredientIds = normalize_inventory_item_ids($data['ingredient_item_ids'] ?? []);
            if ($selectedSalesIngredientIds === []) {
                throw new RuntimeException('Please select ingredient items for this order.');
            }

            $data['ingredient_item_ids'] = inventory_item_ids_to_json($selectedSalesIngredientIds);
            $data['inventory_item_id'] = $selectedSalesIngredientIds[0];
            $data['order_code'] = ($data['order_code'] ?? null) ?: next_order_code($pdo);
            $data['payment_method'] = (string) ($data['payment_method'] ?? 'cash');
            $data['payment_reference'] = $data['payment_reference'] ?? null;
            $data['payment_status'] = 'paid';
            $data['receipt_no'] = next_receipt_code($pdo);
            $data['paid_at'] = date('Y-m-d H:i:s');
            $data['total_amount'] = $data['quantity'] * $data['unit_price'];
        }

        $table = $config['table'];
        $data['status'] = 'pending';
        $data['submitted_by'] = (int) ($user['id'] ?? 0);
        $realTimeSales = $department === 'sales' && REALTIME_SALES_MODE;
        if ($realTimeSales) {
            $data['status'] = 'approved';
            $data['approved_by'] = (int) ($user['id'] ?? 0);
            $data['approval_note'] = 'Auto-approved in real-time POS mode.';
            $data['approved_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnSql = implode(', ', $columns);

        if ($department === 'sales') {
            $ensureSalesFlavorAvailability(
                $pdo,
                $selectedSalesIngredientIds,
                (float) ($data['quantity'] ?? 0),
                (float) ($data['stock_deduct_qty'] ?? 0),
                (float) ($data['per_cup_qty'] ?? 0),
                (float) ($data['per_straw_qty'] ?? 0),
                (int) ($user['id'] ?? 0)
            );
        }

        if ($department === 'production') {
            $ensureProductionIngredientAvailability(
                $pdo,
                $selectedProductionIngredientIds,
                (float) ($data['ingredient_used_qty'] ?? 0),
                (int) ($user['id'] ?? 0)
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
        $createdRecord = $fetchRecord($pdo, $table, $recordId, false);

        write_audit_log(
            $pdo,
            $department,
            $table,
            $recordId,
            'create',
            null,
            $createdRecord,
            (int) ($user['id'] ?? 0),
            $realTimeSales
                ? 'Record created and processed in real-time POS mode.'
                : 'Record created and queued for manager review.',
            'user'
        );

        if ($realTimeSales) {
            $applyApprovalAutomation($pdo, $department, $createdRecord ?? [], (int) ($user['id'] ?? 0));

            $log = $pdo->prepare('INSERT INTO approval_logs (module, record_id, action, note, action_by) VALUES (?, ?, ?, ?, ?)');
            $log->execute([
                $department,
                $recordId,
                'approved',
                'Auto-approved in real-time POS mode.',
                (int) ($user['id'] ?? 0),
            ]);
        }

        $pdo->commit();

        set_flash(
            'success',
            $realTimeSales
                ? (department_label($department) . ' record created and processed in real-time.')
                : (department_label($department) . ' record created and queued for manager review.')
        );
        $redirectToDepartment($department);
    }

    if ($action === 'edit_record') {
        $config = $requireConfig($department);

        if (!can_user_access_department($user ?? [], $department)) {
            throw new RuntimeException('Unauthorized department access.');
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

        $assertOwnsRecord($user ?? [], $record);

        [$data, $errors] = validate_department_input($config, $_POST);
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

        if ($department === 'production') {
            $data['quantity_prepared'] = (int) ($data['quantity_prepared'] ?? 0);
            $data['ingredient_used_qty'] = (float) ($data['ingredient_used_qty'] ?? 0);
            $selectedProductionIngredientIds = normalize_inventory_item_ids($data['ingredient_item_ids'] ?? []);
            if ($selectedProductionIngredientIds === []) {
                throw new RuntimeException('Please select ingredient items for this production request.');
            }

            $data['ingredient_item_ids'] = inventory_item_ids_to_json($selectedProductionIngredientIds);
            $data['inventory_item_id'] = $selectedProductionIngredientIds[0];
        }

        if ($department === 'purchasing') {
            $data['requested_qty'] = (float) ($data['requested_qty'] ?? 0);
            $quotedUnitCost = $data['quoted_unit_cost'] ?? null;
            $data['quoted_unit_cost'] = $quotedUnitCost === null ? null : (float) $quotedUnitCost;
            $data['request_code'] = ($data['request_code'] ?? null) ?: (string) ($record['request_code'] ?? next_purchase_request_code($pdo));
            $data['estimated_total'] = $data['quoted_unit_cost'] === null
                ? 0
                : ($data['requested_qty'] * (float) $data['quoted_unit_cost']);
        }

        if ($department === 'sales') {
            $data['quantity'] = (int) ($data['quantity'] ?? 0);
            $data['unit_price'] = (float) ($data['unit_price'] ?? 0);
            $data['stock_deduct_qty'] = (float) ($data['stock_deduct_qty'] ?? 0);
            $data['per_cup_qty'] = (float) ($data['per_cup_qty'] ?? 0);
            $data['per_straw_qty'] = (float) ($data['per_straw_qty'] ?? 0);
            if ($data['per_cup_qty'] < 0 || $data['per_straw_qty'] < 0) {
                throw new RuntimeException('Per cup and per straw values cannot be negative.');
            }

            $selectedSalesIngredientIds = normalize_inventory_item_ids($data['ingredient_item_ids'] ?? []);
            if ($selectedSalesIngredientIds === []) {
                throw new RuntimeException('Please select ingredient items for this order.');
            }

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
            $data['total_amount'] = $data['quantity'] * $data['unit_price'];
        }

        if ($department === 'sales') {
            $ensureSalesFlavorAvailability(
                $pdo,
                $selectedSalesIngredientIds,
                (float) ($data['quantity'] ?? 0),
                (float) ($data['stock_deduct_qty'] ?? 0),
                (float) ($data['per_cup_qty'] ?? 0),
                (float) ($data['per_straw_qty'] ?? 0),
                (int) ($user['id'] ?? 0)
            );
        }

        if ($department === 'production') {
            $ensureProductionIngredientAvailability(
                $pdo,
                $selectedProductionIngredientIds,
                (float) ($data['ingredient_used_qty'] ?? 0),
                (int) ($user['id'] ?? 0)
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
        $redirectToDepartment($department);
    }

    if ($action === 'delete_record') {
        $config = $requireConfig($department);

        if (!can_user_access_department($user ?? [], $department)) {
            throw new RuntimeException('Unauthorized department access.');
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
        in_array($department, ['sales', 'production'], true)
        && in_array($action, ['create_record', 'edit_record'], true)
    ) {
        $isSalesEscalation = strncmp($errorMessage, $salesUnavailablePrefix, strlen($salesUnavailablePrefix)) === 0;
        $isProductionEscalation = strncmp($errorMessage, $productionUnavailablePrefix, strlen($productionUnavailablePrefix)) === 0;

        $salesErrorLower = strtolower($errorMessage);
        $shouldAttemptSalesEscalation = !$isSalesEscalation
            || strpos($salesErrorLower, 'stock is insufficient') !== false
            || strpos($salesErrorLower, 'inventory department has been alerted and purchasing department has been notified') !== false;

        if (($isSalesEscalation || $isProductionEscalation) && $shouldAttemptSalesEscalation) {
            $inventoryItemIds = normalize_inventory_item_ids($_POST['ingredient_item_ids'] ?? []);
            if ($inventoryItemIds === []) {
                $legacyInventoryItemId = (int) ($_POST['inventory_item_id'] ?? 0);
                if ($legacyInventoryItemId > 0) {
                    $inventoryItemIds = [$legacyInventoryItemId];
                }
            }

            $requiredQty = 0.0;
            $purchaseReason = '';
            $fallbackMessage = '';
            $missingInventoryMessage = '';

            if ($department === 'sales') {
                $quantity = (float) ($_POST['quantity'] ?? 0);
                $stockDeductQty = (float) ($_POST['stock_deduct_qty'] ?? 0);
                $perCupQty = (float) ($_POST['per_cup_qty'] ?? 0);
                $perStrawQty = (float) ($_POST['per_straw_qty'] ?? 0);
                if ($stockDeductQty <= 0) {
                    $stockDeductQty = $perCupQty + $perStrawQty;
                }
                $errorLower = $salesErrorLower;

                if (strpos($errorLower, 'cup stock is insufficient') !== false) {
                    $cupInventoryItemId = $resolveInventoryUtilityItemId($pdo, 'cup');
                    $inventoryItemIds = $cupInventoryItemId > 0 ? [$cupInventoryItemId] : [];
                    $requiredQty = $quantity * $perCupQty;
                    $purchaseReason = 'Inventory Department received a shortage alert from Sales POS cup consumption. Required %s but only %s available.';
                    $fallbackMessage = 'Flavor unavailable for this order. Cup stock alert reached Inventory, but the purchase request could not be created automatically. Please notify Purchasing Department manually.';
                    $missingInventoryMessage = 'Flavor unavailable for this order. Unable to locate linked cup inventory item for auto-escalation.';
                } elseif (strpos($errorLower, 'straw stock is insufficient') !== false) {
                    $strawInventoryItemId = $resolveInventoryUtilityItemId($pdo, 'straw');
                    $inventoryItemIds = $strawInventoryItemId > 0 ? [$strawInventoryItemId] : [];
                    $requiredQty = $quantity * $perStrawQty;
                    $purchaseReason = 'Inventory Department received a shortage alert from Sales POS straw consumption. Required %s but only %s available.';
                    $fallbackMessage = 'Flavor unavailable for this order. Straw stock alert reached Inventory, but the purchase request could not be created automatically. Please notify Purchasing Department manually.';
                    $missingInventoryMessage = 'Flavor unavailable for this order. Unable to locate linked straw inventory item for auto-escalation.';
                } else {
                    $requiredQty = $quantity * $stockDeductQty;
                    $purchaseReason = 'Inventory Department received a shortage alert from Sales POS. Required %s but only %s available.';
                    $fallbackMessage = 'Flavor unavailable for this order. Inventory Department was alerted, but the purchase request could not be created automatically. Please notify Purchasing Department manually.';
                    $missingInventoryMessage = 'Flavor unavailable for this order. Unable to locate linked inventory ingredient items for auto-escalation.';
                }
            } else {
                $requiredQty = (float) ($_POST['ingredient_used_qty'] ?? 0);
                $purchaseReason = 'Inventory Department received a shortage alert from Production. Required %s but only %s available.';
                $fallbackMessage = 'Ingredient request cannot be fulfilled. Inventory Department was alerted, but the purchase request could not be created automatically. Please notify Purchasing Department manually.';
                $missingInventoryMessage = 'Ingredient request cannot be fulfilled. Unable to locate linked inventory ingredient items for auto-escalation.';
            }

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
                        if ($availableQty >= $requiredQty) {
                            continue;
                        }

                        $shortageQty = max($requiredQty - $availableQty, 1);
                        $purchaseRequestId = $upsertLowStockPurchaseRequest(
                            $pdo,
                            $inventoryItemId,
                            (int) ($user['id'] ?? 0),
                            sprintf($purchaseReason, number_format($requiredQty, 2), number_format($availableQty, 2)),
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
        $redirectToDepartment($department);
    }

    redirect('dashboard.php');
}
