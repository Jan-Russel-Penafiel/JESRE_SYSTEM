<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    $token = $_SESSION['_csrf_token'] ?? null;
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
    }

    return $token;
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function is_valid_csrf_token(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = $_SESSION['_csrf_token'] ?? null;
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consume_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);

    return $flashes;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function departments(): array
{
    global $DEPARTMENTS;

    return $DEPARTMENTS;
}

function department_configs(): array
{
    global $DEPARTMENT_CONFIG;

    return $DEPARTMENT_CONFIG;
}

function department_config(string $departmentKey): ?array
{
    $configs = department_configs();

    return $configs[$departmentKey] ?? null;
}

function department_table(string $departmentKey): ?string
{
    $config = department_config($departmentKey);

    return $config['table'] ?? null;
}

function department_label(string $departmentKey): string
{
    $list = departments();

    return $list[$departmentKey] ?? ucfirst($departmentKey);
}

function department_short_label(string $departmentKey): string
{
    return trim(str_replace(' Department', '', department_label($departmentKey)));
}

function can_user_access_department(array $user, string $departmentKey): bool
{
    if (($user['role'] ?? null) === ROLE_GENERAL_MANAGER) {
        return in_array($departmentKey, ['purchasing', 'inventory', 'production', 'sales', 'accounting', 'crm', 'marketing'], true);
    }

    return ($user['role'] ?? null) === ROLE_DEPARTMENT_HEAD && ($user['department'] ?? '') === $departmentKey;
}

function fetch_sales_trend_snapshot(PDO $pdo, int $days = 7): array
{
    $days = max(1, $days);
    $fromDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $stmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END), 0) AS today_revenue,
        COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN quantity ELSE 0 END), 0) AS today_qty,
        COALESCE(SUM(CASE WHEN DATE(created_at) >= ? THEN total_amount ELSE 0 END), 0) AS range_revenue,
        COALESCE(SUM(CASE WHEN DATE(created_at) >= ? THEN quantity ELSE 0 END), 0) AS range_qty,
        COALESCE(SUM(CASE WHEN DATE(created_at) >= ? THEN 1 ELSE 0 END), 0) AS range_orders
    FROM sales_orders
    WHERE status = 'approved'");
    $stmt->execute([$fromDate, $fromDate, $fromDate]);
    $totals = $stmt->fetch() ?: [];

    $topStmt = $pdo->prepare("SELECT beverage_name, SUM(quantity) AS total_qty, SUM(total_amount) AS total_revenue
        FROM sales_orders
        WHERE status = 'approved' AND DATE(created_at) >= ?
        GROUP BY beverage_name
        ORDER BY total_qty DESC, total_revenue DESC
        LIMIT 1");
    $topStmt->execute([$fromDate]);
    $top = $topStmt->fetch() ?: null;

    $avgRevenuePerDay = ((float) ($totals['range_revenue'] ?? 0)) / $days;
    $todayRevenue = (float) ($totals['today_revenue'] ?? 0);

    $direction = 'stable';
    if ($avgRevenuePerDay > 0) {
        if ($todayRevenue >= ($avgRevenuePerDay * 1.2)) {
            $direction = 'up';
        } elseif ($todayRevenue <= ($avgRevenuePerDay * 0.8)) {
            $direction = 'down';
        }
    }

    return [
        'days' => $days,
        'today_revenue' => $todayRevenue,
        'today_qty' => (float) ($totals['today_qty'] ?? 0),
        'range_revenue' => (float) ($totals['range_revenue'] ?? 0),
        'range_qty' => (float) ($totals['range_qty'] ?? 0),
        'range_orders' => (int) ($totals['range_orders'] ?? 0),
        'avg_revenue_per_day' => $avgRevenuePerDay,
        'direction' => $direction,
        'top_beverage_name' => (string) ($top['beverage_name'] ?? ''),
        'top_beverage_qty' => (float) ($top['total_qty'] ?? 0),
        'top_beverage_revenue' => (float) ($top['total_revenue'] ?? 0),
    ];
}

function fetch_sales_performance_snapshot(PDO $pdo, int $days = 30): array
{
    $days = max(1, $days);
    $fromDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $topStmt = $pdo->prepare("SELECT beverage_name, SUM(quantity) AS total_qty, SUM(total_amount) AS total_revenue
        FROM sales_orders
        WHERE status = 'approved' AND DATE(created_at) >= ?
        GROUP BY beverage_name
        ORDER BY total_qty DESC, total_revenue DESC
        LIMIT 1");
    $topStmt->execute([$fromDate]);
    $top = $topStmt->fetch() ?: null;

    $lowStmt = $pdo->prepare("SELECT beverage_name, SUM(quantity) AS total_qty, SUM(total_amount) AS total_revenue
        FROM sales_orders
        WHERE status = 'approved' AND DATE(created_at) >= ?
        GROUP BY beverage_name
        ORDER BY total_qty ASC, total_revenue ASC
        LIMIT 1");
    $lowStmt->execute([$fromDate]);
    $low = $lowStmt->fetch() ?: null;

    return [
        'days' => $days,
        'high_sales_beverage_name' => (string) ($top['beverage_name'] ?? ''),
        'high_sales_qty' => (float) ($top['total_qty'] ?? 0),
        'high_sales_revenue' => (float) ($top['total_revenue'] ?? 0),
        'low_sales_beverage_name' => (string) ($low['beverage_name'] ?? ''),
        'low_sales_qty' => (float) ($low['total_qty'] ?? 0),
        'low_sales_revenue' => (float) ($low['total_revenue'] ?? 0),
    ];
}

function fetch_inventory_health_snapshot(PDO $pdo): array
{
    $totalsStmt = $pdo->query("SELECT
        COUNT(*) AS item_count,
        COALESCE(SUM(stock_qty), 0) AS stock_total,
        COALESCE(SUM(CASE WHEN stock_qty <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock_count
    FROM inventory_items
    WHERE status = 'approved'");
    $totals = $totalsStmt->fetch() ?: [];

    $lowStmt = $pdo->query("SELECT item_name, stock_qty, reorder_level, unit
        FROM inventory_items
        WHERE status = 'approved' AND stock_qty <= reorder_level
        ORDER BY stock_qty ASC
        LIMIT 5");
    $lowItems = $lowStmt->fetchAll();

    return [
        'item_count' => (int) ($totals['item_count'] ?? 0),
        'stock_total' => (float) ($totals['stock_total'] ?? 0),
        'low_stock_count' => (int) ($totals['low_stock_count'] ?? 0),
        'low_items' => $lowItems,
    ];
}

function status_badge_class(string $status): string
{
    if ($status === 'approved') {
        return 'bg-emerald-100 text-emerald-700 border border-emerald-300';
    }

    if ($status === 'rejected') {
        return 'bg-rose-100 text-rose-700 border border-rose-300';
    }

    return 'bg-amber-100 text-amber-700 border border-amber-300';
}

function format_money($value): string
{
    return 'PHP ' . number_format((float) $value, 2);
}

function format_table_value(string $column, $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (in_array($column, ['stock_qty', 'reorder_level', 'ingredient_used_qty', 'unit_price', 'stock_deduct_qty', 'per_cup_qty', 'per_straw_qty', 'amount', 'total_amount', 'total_spent', 'requested_qty', 'quoted_unit_cost', 'estimated_total'], true)) {
        return number_format((float) $value, 2);
    }

    if (in_array($column, ['created_at', 'updated_at', 'approved_at', 'last_purchase_at', 'paid_at'], true)) {
        $timestamp = strtotime((string) $value);

        return $timestamp ? date('M d, Y h:i A', $timestamp) : (string) $value;
    }

    if (in_array($column, ['start_date', 'end_date', 'expected_delivery_date'], true)) {
        $timestamp = strtotime((string) $value);

        return $timestamp ? date('M d, Y', $timestamp) : (string) $value;
    }

    return (string) $value;
}

function normalize_inventory_item_ids($raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }

    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $raw = $decoded;
        } else {
            $raw = preg_split('/\s*,\s*/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
    }

    if (!is_array($raw)) {
        return [];
    }

    $unique = [];
    foreach ($raw as $value) {
        if (is_int($value) && $value > 0) {
            $unique[$value] = $value;
            continue;
        }

        if (is_string($value) && ctype_digit($value)) {
            $id = (int) $value;
            if ($id > 0) {
                $unique[$id] = $id;
            }
        }
    }

    return array_values($unique);
}

function inventory_item_ids_to_json(array $ids): ?string
{
    $normalized = normalize_inventory_item_ids($ids);
    if ($normalized === []) {
        return null;
    }

    return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function inventory_item_ids_from_record(array $record): array
{
    $ids = normalize_inventory_item_ids($record['ingredient_item_ids'] ?? null);
    if ($ids !== []) {
        return $ids;
    }

    $legacyId = (int) ($record['inventory_item_id'] ?? 0);
    if ($legacyId > 0) {
        return [$legacyId];
    }

    return [];
}

function format_inventory_item_selection($raw, array $inventoryMap): string
{
    $ids = normalize_inventory_item_ids($raw);
    if ($ids === []) {
        return '-';
    }

    $labels = [];
    foreach ($ids as $id) {
        $labels[] = $inventoryMap[$id] ?? ('Unknown item #' . $id);
    }

    return implode(', ', $labels);
}

function next_order_code(PDO $pdo): string
{
    $prefix = 'DM' . date('Ymd');
    $sequence = next_sequence_value($pdo, 'sales_order_code');

    return $prefix . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
}

function next_purchase_request_code(PDO $pdo): string
{
    $prefix = 'PR' . date('Ymd');
    $sequence = next_sequence_value($pdo, 'purchase_request_code');

    return $prefix . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
}

function next_receipt_code(PDO $pdo): string
{
    $prefix = 'RCPT-' . date('Ymd');
    $sequence = next_sequence_value($pdo, 'sales_receipt_code');

    return $prefix . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
}

function next_sequence_value(PDO $pdo, string $sequenceKey): int
{
    $stmt = $pdo->prepare("INSERT INTO code_sequences (sequence_key, sequence_date, last_value, updated_at)
        VALUES (?, CURDATE(), LAST_INSERT_ID(1), NOW())
        ON DUPLICATE KEY UPDATE
            last_value = IF(sequence_date = CURDATE(), LAST_INSERT_ID(last_value + 1), LAST_INSERT_ID(1)),
            sequence_date = CURDATE(),
            updated_at = NOW()");
    $stmt->execute([$sequenceKey]);

    $sequence = (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();

    return $sequence > 0 ? $sequence : 1;
}

function validate_department_input(array $departmentConfig, array $source): array
{
    $data = [];
    $errors = [];

    foreach ($departmentConfig['fields'] as $field) {
        $name = $field['name'];
        $type = $field['type'];
        $required = (bool) ($field['required'] ?? false);
        $raw = $source[$name] ?? '';

        if (is_string($raw)) {
            $raw = trim($raw);
        }

        if ($type === 'number') {
            if ($raw === '' && !$required) {
                $data[$name] = null;
                continue;
            }

            if (!is_numeric((string) $raw)) {
                $errors[] = $field['label'] . ' must be a valid number.';
                continue;
            }

            $data[$name] = (float) $raw;
            continue;
        }

        if ($type === 'inventory_select') {
            if ($raw === '' && !$required) {
                $data[$name] = null;
                continue;
            }

            if (!ctype_digit((string) $raw)) {
                $errors[] = $field['label'] . ' is invalid.';
                continue;
            }

            $data[$name] = (int) $raw;
            continue;
        }

        if ($type === 'inventory_multi_select') {
            $ids = normalize_inventory_item_ids($raw);

            if ($required && $ids === []) {
                $errors[] = $field['label'] . ' is required.';
                continue;
            }

            $data[$name] = $ids;
            continue;
        }

        if ($type === 'crm_select') {
            $customerName = trim((string) $raw);

            if ($customerName === '' && !$required) {
                $data[$name] = null;
                continue;
            }

            if ($customerName === '' && $required) {
                $errors[] = $field['label'] . ' is required.';
                continue;
            }

            if (strlen($customerName) > 120) {
                $errors[] = $field['label'] . ' must be 120 characters or less.';
                continue;
            }

            $data[$name] = $customerName;
            continue;
        }

        if ($type === 'select') {
            $options = $field['options'] ?? [];
            if (!is_array($options) || $options === []) {
                $errors[] = $field['label'] . ' has no valid options configured.';
                continue;
            }

            if ($raw === '') {
                if ($required) {
                    $errors[] = $field['label'] . ' is required.';
                } else {
                    $data[$name] = null;
                }
                continue;
            }

            if (!array_key_exists((string) $raw, $options)) {
                $errors[] = $field['label'] . ' has an invalid option.';
                continue;
            }

            $data[$name] = (string) $raw;
            continue;
        }

        if ($required && $raw === '') {
            $errors[] = $field['label'] . ' is required.';
            continue;
        }

        $data[$name] = $raw === '' ? null : (string) $raw;
    }

    return [$data, $errors];
}

function query_with(array $base, array $changes = [], array $remove = []): string
{
    $query = $base;

    foreach ($changes as $key => $value) {
        $query[$key] = $value;
    }

    foreach ($remove as $key) {
        unset($query[$key]);
    }

    return http_build_query($query);
}

function normalize_audit_snapshot(?array $snapshot): ?array
{
    if ($snapshot === null) {
        return null;
    }

    $normalized = [];

    foreach ($snapshot as $key => $value) {
        if (is_resource($value)) {
            continue;
        }

        if (is_object($value)) {
            $normalized[$key] = (string) $value;
            continue;
        }

        $normalized[$key] = $value;
    }

    ksort($normalized);

    return $normalized;
}

function build_audit_diff(?array $oldData, ?array $newData): array
{
    $old = $oldData ?? [];
    $new = $newData ?? [];
    $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
    sort($keys);

    $diff = [];

    foreach ($keys as $key) {
        $oldValue = $old[$key] ?? null;
        $newValue = $new[$key] ?? null;

        if ($oldValue === $newValue) {
            continue;
        }

        $diff[$key] = [
            'old' => $oldValue,
            'new' => $newValue,
        ];
    }

    return $diff;
}

function write_audit_log(
    PDO $pdo,
    string $module,
    string $tableName,
    int $recordId,
    string $actionType,
    ?array $oldData,
    ?array $newData,
    ?int $performedBy,
    ?string $note = null,
    string $source = 'user'
): void {
    $normalizedOld = normalize_audit_snapshot($oldData);
    $normalizedNew = normalize_audit_snapshot($newData);
    $diff = build_audit_diff($normalizedOld, $normalizedNew);

    $stmt = $pdo->prepare('INSERT INTO audit_trails
        (module, table_name, record_id, action_type, source, note, old_data, new_data, diff_data, performed_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $stmt->execute([
        $module,
        $tableName,
        $recordId,
        $actionType,
        $source,
        $note,
        $normalizedOld === null ? null : json_encode($normalizedOld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $normalizedNew === null ? null : json_encode($normalizedNew, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        empty($diff) ? null : json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $performedBy,
    ]);
}
