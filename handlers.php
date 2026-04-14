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

$deductInventory = static function (PDO $db, int $inventoryItemId, float $deductQuantity, int $actorId, string $reason): void {
    if ($inventoryItemId <= 0 || $deductQuantity <= 0) {
        return;
    }

    $inventoryStmt = $db->prepare("SELECT id, item_name, unit, stock_qty, status FROM inventory_items WHERE id = ? FOR UPDATE");
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
};

$applyApprovalAutomation = static function (PDO $db, string $dept, array $record, int $approverId) use ($deductInventory, $fetchRecord): void {
    if ($dept === 'production') {
        $deductInventory(
            $db,
            (int) ($record['inventory_item_id'] ?? 0),
            (float) ($record['ingredient_used_qty'] ?? 0),
            $approverId,
            'Auto-deducted ingredient usage from approved production log #' . (int) $record['id']
        );
        return;
    }

    if ($dept !== 'sales') {
        return;
    }

    $quantity = (float) ($record['quantity'] ?? 0);
    $deductPerOrder = (float) ($record['stock_deduct_qty'] ?? 0);
    $totalDeduction = $quantity * $deductPerOrder;

    $deductInventory(
        $db,
        (int) ($record['inventory_item_id'] ?? 0),
        $totalDeduction,
        $approverId,
        'Auto-deducted stock from approved sales order #' . (int) $record['id']
    );

    $orderCode = (string) ($record['order_code'] ?? ('ORDER-' . (int) $record['id']));
    $totalAmount = (float) ($record['total_amount'] ?? 0);

    $insertAccounting = $db->prepare("INSERT INTO accounting_entries
        (entry_type, source, amount, description, status, submitted_by, approved_by, approval_note, approved_at)
        VALUES ('income', ?, ?, ?, 'approved', ?, ?, 'Auto-generated from approved sales order.', NOW())");
    $insertAccounting->execute([
        'Sales ' . $orderCode,
        $totalAmount,
        'Auto-generated from sales approval flow.',
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
};

try {
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

        if ($department === 'production') {
            $data['quantity_prepared'] = (int) ($data['quantity_prepared'] ?? 0);
        }

        if ($department === 'sales') {
            $data['quantity'] = (int) ($data['quantity'] ?? 0);
            $data['unit_price'] = (float) ($data['unit_price'] ?? 0);
            $data['stock_deduct_qty'] = (float) ($data['stock_deduct_qty'] ?? 0);
            $data['order_code'] = ($data['order_code'] ?? null) ?: next_order_code($pdo);
            $data['total_amount'] = $data['quantity'] * $data['unit_price'];
        }

        $table = $config['table'];
        $data['status'] = 'pending';
        $data['submitted_by'] = (int) ($user['id'] ?? 0);

        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnSql = implode(', ', $columns);

        $pdo->beginTransaction();

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
            'Record created and submitted for approval.',
            'user'
        );

        $pdo->commit();

        set_flash('success', department_label($department) . ' record created and sent for GM approval.');
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
        $pdo->beginTransaction();

        $record = $fetchRecord($pdo, $table, $id, true);
        if (!$record) {
            throw new RuntimeException('Record not found.');
        }

        $assertOwnsRecord($user ?? [], $record);

        if (($record['status'] ?? '') === 'approved') {
            throw new RuntimeException('Approved records are locked from editing.');
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

        if ($department === 'production') {
            $data['quantity_prepared'] = (int) ($data['quantity_prepared'] ?? 0);
        }

        if ($department === 'sales') {
            $data['quantity'] = (int) ($data['quantity'] ?? 0);
            $data['unit_price'] = (float) ($data['unit_price'] ?? 0);
            $data['stock_deduct_qty'] = (float) ($data['stock_deduct_qty'] ?? 0);
            $data['order_code'] = ($data['order_code'] ?? null) ?: (string) ($record['order_code'] ?? next_order_code($pdo));
            $data['total_amount'] = $data['quantity'] * $data['unit_price'];
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = ?';
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $assignments) . ", status = 'pending', approved_by = NULL, approval_note = NULL, approved_at = NULL, submitted_by = ?, updated_at = NOW() WHERE id = ?";
        $params = array_values($data);
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
            'Record edited and re-submitted for approval.',
            'user'
        );

        $pdo->commit();

        set_flash('success', 'Record updated and re-submitted for approval.');
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

        if (($record['status'] ?? '') === 'approved') {
            throw new RuntimeException('Approved records cannot be deleted.');
        }

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

    set_flash('error', $exception->getMessage());

    if (in_array($action, ['approve_record', 'reject_record'], true)) {
        redirect('approvals.php');
    }

    if ($department !== '') {
        $redirectToDepartment($department);
    }

    redirect('dashboard.php');
}
