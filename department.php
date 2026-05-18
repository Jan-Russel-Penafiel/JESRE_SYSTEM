<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_login();

$department = $_GET['dept'] ?? '';
$config = department_config($department);

if (!$config) {
    set_flash('error', 'Invalid department selected.');
    redirect('dashboard.php');
}

require_department_access($department);

$user = current_user();
$pdo = db();
$table = $config['table'];

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
$dateFrom = (string) ($_GET['from'] ?? '');
$dateTo = (string) ($_GET['to'] ?? '');
$rangeFilter = (string) ($_GET['range'] ?? 'all');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
$allowedPerPage = [10, 25, 50, 100];
$statusOptions = ['all', 'pending', 'approved', 'rejected'];
$rangeOptions = ['all', 'daily', 'weekly', 'monthly'];

if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}

if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = 'all';
}

if (!in_array($rangeFilter, $rangeOptions, true)) {
    $rangeFilter = 'all';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

if ($rangeFilter === 'daily') {
    $dateFrom = date('Y-m-d');
    $dateTo = $dateFrom;
} elseif ($rangeFilter === 'weekly') {
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo = date('Y-m-d', strtotime('sunday this week'));
} elseif ($rangeFilter === 'monthly') {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-t');
}

$isInventoryDepartment = $department === 'inventory';
$isProductionDepartment = $department === 'production';
$isPurchasingDepartment = $department === 'purchasing';
$isAccountingDepartment = $department === 'accounting';
$isCrmDepartment = $department === 'crm';
$showCreateModal = !$isInventoryDepartment && !$isProductionDepartment && !$isPurchasingDepartment && !$isCrmDepartment;
$showApplyResetButtons = !$isCrmDepartment;
$autoSubmitFilters = $isCrmDepartment;

$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = 't.status = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $searchColumns = array_values(array_unique(array_map(static function (array $field): string {
        return $field['name'];
    }, $config['fields'])));
    $searchColumns[] = 'status';

    $searchLike = '%' . $search . '%';
    $searchConditions = [];

    foreach ($searchColumns as $column) {
        $searchConditions[] = "CAST(t.{$column} AS CHAR) LIKE ?";
        $params[] = $searchLike;
    }

    $searchConditions[] = 'su.full_name LIKE ?';
    $params[] = $searchLike;
    $searchConditions[] = 'au.full_name LIKE ?';
    $params[] = $searchLike;

    $where[] = '(' . implode(' OR ', $searchConditions) . ')';
}

if ($dateFrom !== '') {
    $where[] = 'DATE(t.created_at) >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(t.created_at) <= ?';
    $params[] = $dateTo;
}

if ($isPurchasingDepartment) {
    $where[] = 't.inventory_confirmed_at IS NOT NULL';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*)
    FROM {$table} t
    LEFT JOIN users su ON su.id = t.submitted_by
    LEFT JOIN users au ON au.id = t.approved_by
    {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT t.*, su.full_name AS submitted_name, au.full_name AS approved_name
    FROM {$table} t
    LEFT JOIN users su ON su.id = t.submitted_by
    LEFT JOIN users au ON au.id = t.approved_by
    {$whereSql}
    ORDER BY t.id DESC
    LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$salesOrderItemsByOrder = [];
if ($department === 'sales' && $rows) {
    $salesOrderItemsByOrder = fetch_sales_order_items_grouped($pdo, array_column($rows, 'id'));
}

$allInventoryItems = [];
$approvedInventoryItems = [];
$approvedIngredientItems = [];
$inventoryMap = [];
$ingredientSelectionMap = [];
$approvedCrmProfiles = [];
$createButtonLabel = (string) ($config['create_button_label'] ?? 'Create Record');
$submitLabel = (string) ($config['submit_label'] ?? 'Save Record');
$editLabel = (string) ($config['edit_label'] ?? 'Save Changes');

if (in_array($department, ['purchasing', 'inventory', 'production', 'sales'], true)) {
    $allInventoryItems = $pdo->query('SELECT id, item_name, stock_qty, unit, per_cup_qty, per_straw_qty, status FROM inventory_items ORDER BY item_name ASC')->fetchAll();
    foreach ($allInventoryItems as $item) {
        $itemId = (int) ($item['id'] ?? 0);
        $itemName = (string) ($item['item_name'] ?? '-');
        $stockQty = number_format((float) ($item['stock_qty'] ?? 0), 2);
        $unit = (string) ($item['unit'] ?? '');
        $perCupQty = number_format((float) ($item['per_cup_qty'] ?? 0), 2);
        $perStrawQty = number_format((float) ($item['per_straw_qty'] ?? 0), 2);

        $baseLabel = $itemName . ' (' . $stockQty . ' ' . $unit . ')';
        $inventoryMap[$itemId] = $baseLabel;
        $ingredientSelectionMap[$itemId] = $baseLabel . ' | Cup: ' . $perCupQty . ' | Straw: ' . $perStrawQty;

        if (($item['status'] ?? '') === 'approved') {
            $approvedInventoryItems[] = $item;
        }
    }

    $approvedIngredientItems = array_values(array_filter($approvedInventoryItems, static function (array $item): bool {
        $itemName = strtolower(trim((string) ($item['item_name'] ?? '')));

        return !in_array($itemName, ['cup', 'straw'], true);
    }));

    $deduplicatedIngredientItems = [];
    foreach ($approvedIngredientItems as $item) {
        $itemNameKey = strtolower(trim((string) ($item['item_name'] ?? '')));
        if ($itemNameKey === '') {
            continue;
        }

        if (!isset($deduplicatedIngredientItems[$itemNameKey])) {
            $deduplicatedIngredientItems[$itemNameKey] = $item;
            continue;
        }

        $existingItem = $deduplicatedIngredientItems[$itemNameKey];
        $existingStockQty = (float) ($existingItem['stock_qty'] ?? 0);
        $currentStockQty = (float) ($item['stock_qty'] ?? 0);
        $existingItemId = (int) ($existingItem['id'] ?? 0);
        $currentItemId = (int) ($item['id'] ?? 0);

        if ($currentStockQty > $existingStockQty || $currentStockQty === $existingStockQty && $currentItemId > $existingItemId) {
            $deduplicatedIngredientItems[$itemNameKey] = $item;
        }
    }

    $approvedIngredientItems = array_values($deduplicatedIngredientItems);

    $priorityFlavorOrder = [
        'caramel syrup' => 0,
        'matcha coffee syrup' => 1,
        'spanish latte syrup' => 2,
        'hazelnuts syrup' => 3,
        'vanilla syrup' => 4,
    ];

    usort($approvedIngredientItems, static function (array $left, array $right) use ($priorityFlavorOrder): int {
        $leftName = strtolower(trim((string) ($left['item_name'] ?? '')));
        $rightName = strtolower(trim((string) ($right['item_name'] ?? '')));

        $leftPriority = $priorityFlavorOrder[$leftName] ?? 99;
        $rightPriority = $priorityFlavorOrder[$rightName] ?? 99;
        if ($leftPriority !== $rightPriority) {
            return $leftPriority <=> $rightPriority;
        }

        return strnatcasecmp((string) ($left['item_name'] ?? ''), (string) ($right['item_name'] ?? ''));
    });
}

$recipeOptions = [];
if (in_array($department, ['production', 'sales'], true)) {
    $recipeOptions = fetch_active_beverage_recipes($pdo);
}

if ($department === 'sales') {
    $approvedCrmProfiles = $pdo->query('SELECT id, customer_name FROM crm_profiles WHERE status = \'approved\' ORDER BY customer_name ASC')->fetchAll() ?? [];
}

$formatIngredientSelection = static function ($value, ?array $record = null) use ($ingredientSelectionMap): string {
    $ids = normalize_inventory_item_ids($value);
    if ($ids === [] && $record !== null) {
        $ids = inventory_item_ids_from_record($record);
    }

    return format_inventory_item_selection($ids, $ingredientSelectionMap);
};

$formatInventoryItemLabel = static function (array $item, bool $showCupStrawUsage = true): string {
    $itemName = (string) ($item['item_name'] ?? '-');
    $stockQty = number_format((float) ($item['stock_qty'] ?? 0), 2);
    $unit = (string) ($item['unit'] ?? '');

    $label = $itemName . ' (' . $stockQty . ' ' . $unit . ')';
    if (!$showCupStrawUsage) {
        return $label;
    }

    $perCupQty = number_format((float) ($item['per_cup_qty'] ?? 0), 2);
    $perStrawQty = number_format((float) ($item['per_straw_qty'] ?? 0), 2);

    return $label . ' | Cup: ' . $perCupQty . ' | Straw: ' . $perStrawQty;
};

$jsPdfVersion = '';
if ($department === 'sales') {
    $jsPdfFile = __DIR__ . '/assets/vendor/jspdf/jspdf.umd.min.js';
    $jsPdfVersion = is_file($jsPdfFile) ? (string) filemtime($jsPdfFile) : '1';
}

$todayProductionStock = [];
if ($department === 'sales') {
    $soldSubquery = sales_order_items_table_exists($pdo)
        ? "SELECT LOWER(soi.beverage_name) AS bname, SUM(soi.quantity) AS sold
            FROM sales_order_items soi
            JOIN sales_orders so ON so.id = soi.sales_order_id
            WHERE so.status = 'approved' AND DATE(so.created_at) = CURDATE()
            GROUP BY LOWER(soi.beverage_name)"
        : "SELECT LOWER(beverage_name) AS bname, SUM(quantity) AS sold
            FROM sales_orders
            WHERE status = 'approved' AND DATE(created_at) = CURDATE()
            GROUP BY LOWER(beverage_name)";

    $stockStmt = $pdo->query(
        "SELECT
            pl.beverage_name,
            COALESCE(SUM(pl.quantity_prepared), 0) AS produced,
            COALESCE(s.sold, 0) AS sold,
            COALESCE(SUM(pl.quantity_prepared), 0) - COALESCE(s.sold, 0) AS remaining
        FROM production_logs pl
        LEFT JOIN ({$soldSubquery}) s ON s.bname = LOWER(pl.beverage_name)
        WHERE pl.status = 'approved' AND DATE(pl.created_at) = CURDATE()
        GROUP BY pl.beverage_name, s.sold
        ORDER BY pl.beverage_name ASC"
    );
    $todayProductionStock = $stockStmt ? $stockStmt->fetchAll() : [];
}

$dailySalesRows = [];
$dailySalesTotal = 0.0;
if ($isAccountingDepartment) {
    $dailySalesStmt = $pdo->query("SELECT customer_name, beverage_name, quantity, unit_price, total_amount
        FROM sales_orders
        WHERE status = 'approved' AND DATE(created_at) = CURDATE()
        ORDER BY COALESCE(paid_at, created_at) DESC, id DESC");
    $dailySalesRows = $dailySalesStmt ? $dailySalesStmt->fetchAll() : [];
    foreach ($dailySalesRows as $dailySalesRow) {
        $dailySalesTotal += (float) ($dailySalesRow['total_amount'] ?? 0);
    }
}

$productionSalesOrderRows = [];
if ($isProductionDepartment) {
    $salesCopyWhere = ["status = 'approved'"];
    $salesCopyParams = [];

    if ($dateFrom !== '') {
        $salesCopyWhere[] = 'DATE(created_at) >= ?';
        $salesCopyParams[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $salesCopyWhere[] = 'DATE(created_at) <= ?';
        $salesCopyParams[] = $dateTo;
    }

    $salesCopyWhereSql = implode(' AND ', $salesCopyWhere);
    $salesCopyStmt = $pdo->prepare("SELECT order_code, customer_name, beverage_name, quantity, total_amount, paid_at, created_at
        FROM sales_orders
        WHERE {$salesCopyWhereSql}
        ORDER BY COALESCE(paid_at, created_at) DESC, id DESC
        LIMIT 20");
    $salesCopyStmt->execute($salesCopyParams);
    $productionSalesOrderRows = $salesCopyStmt->fetchAll() ?: [];
}

$workflowInventoryRows = [];
if (in_array($department, ['production', 'inventory'], true)) {
    $workflowInventoryRows = $pdo->query("SELECT id, item_name, stock_qty, unit, reorder_level,
            CASE
                WHEN stock_qty <= 0 THEN 'out'
                WHEN stock_qty <= reorder_level THEN 'low'
                WHEN stock_qty >= (reorder_level * 3) AND reorder_level > 0 THEN 'high'
                ELSE 'normal'
            END AS stock_level
        FROM inventory_items
        WHERE status = 'approved'
        ORDER BY
            CASE
                WHEN stock_qty <= 0 THEN 0
                WHEN stock_qty <= reorder_level THEN 1
                WHEN stock_qty >= (reorder_level * 3) AND reorder_level > 0 THEN 3
                ELSE 2
            END,
            stock_qty ASC,
            item_name ASC
        LIMIT 20")->fetchAll() ?: [];
}

$inventoryPurchaseOrderRows = [];
if ($isInventoryDepartment) {
    $inventoryPurchaseOrderRows = $pdo->query("SELECT pr.*, i.item_name, i.unit, i.stock_qty, i.reorder_level
        FROM purchase_requests pr
        LEFT JOIN inventory_items i ON i.id = pr.inventory_item_id
        WHERE pr.status = 'pending' AND pr.inventory_confirmed_at IS NULL
        ORDER BY pr.updated_at DESC, pr.id DESC
        LIMIT 20")->fetchAll() ?: [];
}

$pageTitle = $config['title'];
$activePage = 'department_' . $department;

$renderSalesOrderItemsBuilder = static function (array $recipeOptions, array $items, string $builderId): void {
    if ($items === []) {
        $items = [
            [
                'beverage_name' => '',
                'quantity' => 1,
                'unit_price' => '',
            ],
        ];
    }

    $renderRow = static function (array $item, $index) use ($recipeOptions): void {
        $beverageValue = (string) ($item['beverage_name'] ?? '');
        $quantityValue = (string) ($item['quantity'] ?? 1);
        $unitPriceValue = (string) ($item['unit_price'] ?? '');
        ?>
        <div class="grid gap-2 rounded-lg border border-slate-200 bg-white p-2 sm:grid-cols-[1fr_90px_120px_auto]" data-sales-item-row>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Flavor</label>
                <select name="sales_items[<?= e((string) $index) ?>][beverage_name]" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required data-sales-item-name>
                    <option value="">Select flavor</option>
                    <?php foreach ($recipeOptions as $recipeOption): ?>
                        <?php
                        $recipeName = (string) ($recipeOption['beverage_name'] ?? '');
                        $ingredientsTooltip = (string) ($recipeOption['ingredients_label'] ?? 'Ingredients: Not set');
                        ?>
                        <option value="<?= e($recipeName) ?>" title="<?= e($ingredientsTooltip) ?>" data-ingredients="<?= e($ingredientsTooltip) ?>" <?= $beverageValue === $recipeName ? 'selected' : '' ?>><?= e($recipeName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Qty</label>
                <input type="number" name="sales_items[<?= e((string) $index) ?>][quantity]" value="<?= e($quantityValue) ?>" min="1" step="1" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required data-sales-item-quantity>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Unit Price</label>
                <input type="number" name="sales_items[<?= e((string) $index) ?>][unit_price]" value="<?= e($unitPriceValue) ?>" min="0.01" step="0.01" class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required data-sales-item-price>
            </div>
            <div class="flex items-end">
                <button type="button" class="w-full rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100" data-sales-remove-item>Remove</button>
            </div>
        </div>
        <?php
    };
    ?>
    <div class="rounded-xl border border-slate-300 bg-slate-50 p-3" data-sales-order-builder id="<?= e($builderId) ?>">
        <div class="space-y-2" data-sales-items-list>
            <?php foreach ($items as $index => $item): ?>
                <?php $renderRow($item, $index); ?>
            <?php endforeach; ?>
        </div>

        <template data-sales-item-template>
            <?php $renderRow(['beverage_name' => '', 'quantity' => 1, 'unit_price' => ''], '__INDEX__'); ?>
        </template>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <button type="button" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-2 text-sm font-bold text-brand-700 hover:bg-brand-100" data-sales-add-item>Add Flavor</button>
            <p class="text-sm font-extrabold text-slate-900">Total: <span data-sales-order-total>PHP 0.00</span></p>
        </div>

        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full min-w-[560px] text-left text-xs">
                <thead class="text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Flavor</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Unit Price</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody data-sales-items-preview>
                    <tr><td colspan="4" class="px-3 py-3 text-slate-500">No items selected.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
};

require_once __DIR__ . '/includes/layout_top.php';
?>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <form method="get" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <input type="hidden" name="dept" value="<?= e($department) ?>">
        <input type="hidden" name="range" value="<?= e($rangeFilter) ?>">

        <div class="md:col-span-2 xl:col-span-2">
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search records" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>
            <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <?php foreach ($statusOptions as $option): ?>
                    <option value="<?= e($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">From</label>
            <input type="date" name="from" value="<?= e($dateFrom) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">To</label>
            <input type="date" name="to" value="<?= e($dateTo) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Rows</label>
            <select name="per_page" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <?php foreach ($allowedPerPage as $size): ?>
                    <option value="<?= e((string) $size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e((string) $size) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-2 xl:col-span-6 flex flex-wrap items-center gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $rangeKey => $rangeLabel): ?>
                    <a href="department.php?<?= e(query_with($_GET, ['dept' => $department, 'range' => $rangeKey, 'page' => 1], ['from', 'to'])) ?>" class="rounded-xl px-3 py-1.5 text-xs font-bold <?= $rangeFilter === $rangeKey ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' ?>"><?= e($rangeLabel) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                <?php if ($showApplyResetButtons): ?>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Apply Filters</button>
                    <a href="department.php?dept=<?= e($department) ?>" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    <?php if ($department === 'sales'): ?>
                        <button type="button" onclick="openModal('modal-production-stock')" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Today's Production Stock</button>
                        <button type="button" onclick="openModal('modal-sales-production-log')" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-100">Log Daily Production</button>
                    <?php endif; ?>
                    <?php if ($department === 'production'): ?>
                        <button type="button" onclick="openModal('modal-production-purchase-request')" class="rounded-xl border border-orange-300 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-100">Prepare Purchase Request</button>
                    <?php endif; ?>
                    <?php if ($department === 'inventory'): ?>
                        <button type="button" onclick="openModal('modal-inventory-purchase-order')" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-100">New Purchase Order</button>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($showCreateModal): ?>
                    <button type="button" onclick="openModal('modal-create')" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800"><?= e($createButtonLabel) ?></button>
                <?php endif; ?>
                <p class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">Total results: <?= e((string) $totalRows) ?></p>
            </div>
        </div>
    </form>

    <div class="table-scroll mt-4">
        <table class="stack-table w-full min-w-[980px] text-sm">
            <thead>
            <tr class="text-left text-slate-500">
                <th class="pb-2 pr-4" data-priority="high">#</th>
                <?php foreach ($config['list_columns'] as $label => $column): ?>
                    <th class="pb-2 pr-4" data-priority="medium"><?= e($label) ?></th>
                <?php endforeach; ?>
                <th class="pb-2 pr-4" data-priority="low">Submitted By</th>
                <th class="pb-2 pr-4" data-priority="low">Approved By</th>
                <th class="pb-2" data-priority="high">Actions</th>
            </tr>
            </thead>
            <tbody class="text-slate-700">
            <?php if ($rows): ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $rowId = (int) $row['id'];
                    $canManage = $department !== 'inventory'
                        && $department !== 'production'
                        && !($department === 'purchasing' && !empty($row['inventory_confirmed_at']))
                        && !($department === 'sales' && ($row['status'] ?? '') === 'approved')
                        && ((($user['role'] ?? '') === ROLE_GENERAL_MANAGER)
                            || (int) ($row['submitted_by'] ?? 0) === (int) ($user['id'] ?? 0));
                    $canPurchasingDecide = $department === 'purchasing'
                        && can_purchasing_process_purchase_order($row)
                        && can_user_access_department($user ?? [], 'purchasing');
                    $purchasingActionLabel = $canPurchasingDecide ? purchasing_purchase_order_action_label($row) : null;
                    $isApproved = ($row['status'] ?? '') === 'approved';
                    $receiptPayload = null;
                    if ($department === 'sales') {
                        $receiptItems = sales_order_receipt_items($row, $salesOrderItemsByOrder[$rowId] ?? []);
                        $receiptPayload = [
                            'order_code' => (string) ($row['order_code'] ?? '-'),
                            'receipt_no' => (string) ($row['receipt_no'] ?? '-'),
                            'customer_name' => (string) ($row['customer_name'] ?? '-'),
                            'beverage_name' => (string) ($row['beverage_name'] ?? '-'),
                            'quantity' => (float) ($row['quantity'] ?? 0),
                            'unit_price' => (float) ($row['unit_price'] ?? 0),
                            'items' => $receiptItems,
                            'total_amount' => (float) ($row['total_amount'] ?? 0),
                            'payment_method' => (string) ($row['payment_method'] ?? '-'),
                            'payment_reference' => (string) ($row['payment_reference'] ?? ''),
                            'paid_at' => (string) format_table_value('paid_at', $row['paid_at'] ?? null),
                            'cashier_name' => 'Guardados',
                            'contact_information' => 'Don Macchiatos | Jaycee Ave, Koronadal , 9506 South Cotabato | 09922742924',
                        ];
                    }
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-4 font-bold">#<?= e((string) $rowId) ?></td>
                        <?php foreach ($config['list_columns'] as $column): ?>
                            <td class="py-2 pr-4">
                                <?php if ($column === 'status'): ?>
                                    <span class="rounded-full px-2 py-1 text-xs font-bold <?= e(status_badge_class((string) $row[$column])) ?>"><?= e(strtoupper((string) $row[$column])) ?></span>
                                <?php elseif ($column === 'purchase_workflow_stage'): ?>
                                    <span class="rounded-full border border-slate-300 bg-slate-50 px-2 py-1 text-xs font-bold text-slate-700"><?= e(purchase_workflow_stage_label($row)) ?></span>
                                <?php elseif ($column === 'inventory_item_id'): ?>
                                    <?= e($inventoryMap[(int) ($row[$column] ?? 0)] ?? '-') ?>
                                <?php elseif ($column === 'ingredient_item_ids'): ?>
                                    <?= e($formatIngredientSelection($row[$column] ?? null, $row)) ?>
                                <?php else: ?>
                                    <?= e(format_table_value($column, $row[$column] ?? null)) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="py-2 pr-4"><?= e($row['submitted_name'] ?? '-') ?></td>
                        <td class="py-2 pr-4"><?= e($row['approved_name'] ?? '-') ?></td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="openModal('view-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50">View</button>
                                <?php if ($department === 'sales' && $receiptPayload !== null): ?>
                                    <button type="button" data-receipt="<?= e((string) json_encode($receiptPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>" onclick="printSalesReceiptFromButton(this)" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100">Print Receipt</button>
                                <?php endif; ?>
                                <?php if ($canManage): ?>
                                    <button type="button" onclick="openModal('edit-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-lg border border-brand-300 bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700 hover:bg-brand-100">Edit</button>
                                    <button type="button" onclick="openModal('delete-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100">Delete</button>
                                <?php endif; ?>
                                <?php if ($purchasingActionLabel !== null): ?>
                                    <button type="button" onclick="openModal('approve-purchase-order-<?= e((string) $rowId) ?>')" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100"><?= e($purchasingActionLabel) ?></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="100%" class="py-4 text-center text-slate-500">No records yet for this department.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($isAccountingDepartment): ?>
        <div class="mt-6 grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h4 class="text-sm font-extrabold text-blue-700">Daily Sales Transactions</h4>
                <p class="mt-1 text-xs text-slate-500">Today's approved sales orders.</p>
                <div class="table-scroll mt-3">
                    <table class="stack-table w-full min-w-[420px] text-sm">
                        <thead>
                        <tr class="text-left text-slate-500">
                            <th class="pb-2 pr-3" data-priority="high">Customer</th>
                            <th class="pb-2 pr-3" data-priority="high">Flavor</th>
                            <th class="pb-2 pr-3 text-right" data-priority="medium">Qty</th>
                            <th class="pb-2 pr-3 text-right" data-priority="high">Amount</th>
                            <th class="pb-2 text-right" data-priority="high">Total</th>
                        </tr>
                        </thead>
                        <tbody class="text-slate-700">
                        <?php if ($dailySalesRows): ?>
                            <?php foreach ($dailySalesRows as $dsRow): ?>
                                <tr class="border-t border-slate-100">
                                    <td class="py-2 pr-3"><?= e((string) ($dsRow['customer_name'] ?? '-')) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) ($dsRow['beverage_name'] ?? '-')) ?></td>
                                    <td class="py-2 pr-3 text-right"><?= e((string) ((int) ($dsRow['quantity'] ?? 0))) ?></td>
                                    <td class="py-2 pr-3 text-right"><?= e(format_money((float) ($dsRow['unit_price'] ?? 0))) ?></td>
                                    <td class="py-2 text-right font-semibold"><?= e(format_money((float) ($dsRow['total_amount'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="py-3 text-slate-500">No sales transactions today.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h4 class="text-sm font-extrabold text-slate-900">Total Sales Daily</h4>
                <p class="mt-1 text-xs text-slate-500">Summary of today's approved sales transactions.</p>
                <div class="table-scroll mt-3">
                    <table class="stack-table w-full min-w-[320px] text-sm">
                        <tbody class="text-slate-700">
                            <tr class="border-t border-slate-200">
                                <td class="py-2 pr-4 font-black">Total Amount</td>
                                <td class="py-2 text-right font-black text-emerald-700"><?= e(format_money($dailySalesTotal)) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-slate-500">Page <?= e((string) $page) ?> of <?= e((string) $totalPages) ?></p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="department.php?<?= e(query_with($_GET, ['page' => $page - 1])) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="department.php?<?= e(query_with($_GET, ['page' => $page + 1])) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</section>

<?php if ($isProductionDepartment): ?>
<section class="mt-6 grid gap-4 xl:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Sales Order Copies</h4>
        <p class="mt-1 text-xs text-slate-500">Approved Sales Orders sent to Production for order preparation.</p>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[560px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-3" data-priority="high">Order</th>
                    <th class="pb-2 pr-3" data-priority="high">Customer</th>
                    <th class="pb-2 pr-3" data-priority="high">Beverage</th>
                    <th class="pb-2 pr-3 text-right" data-priority="medium">Qty</th>
                    <th class="pb-2 text-right" data-priority="low">Paid</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($productionSalesOrderRows): ?>
                    <?php foreach ($productionSalesOrderRows as $salesCopy): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-3 font-semibold"><?= e((string) ($salesCopy['order_code'] ?? '-')) ?></td>
                            <td class="py-2 pr-3"><?= e((string) ($salesCopy['customer_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-3"><?= e((string) ($salesCopy['beverage_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-3 text-right"><?= e((string) ((int) ($salesCopy['quantity'] ?? 0))) ?></td>
                            <td class="py-2 text-right text-xs text-slate-500"><?= e(format_table_value('paid_at', $salesCopy['paid_at'] ?? ($salesCopy['created_at'] ?? null))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="py-3 text-slate-500">No approved Sales Order copies in this view.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Inventory Level Check</h4>
        <p class="mt-1 text-xs text-slate-500">Low and high stock levels used before preparing purchase requests.</p>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[520px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-3" data-priority="high">Item</th>
                    <th class="pb-2 pr-3 text-right" data-priority="high">Stock</th>
                    <th class="pb-2 pr-3 text-right" data-priority="medium">Reorder</th>
                    <th class="pb-2 text-right" data-priority="high">Level</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($workflowInventoryRows): ?>
                    <?php foreach ($workflowInventoryRows as $inventoryLevel): ?>
                        <?php
                        $level = (string) ($inventoryLevel['stock_level'] ?? 'normal');
                        $levelLabel = ['out' => 'Out', 'low' => 'Low', 'high' => 'High', 'normal' => 'Normal'][$level] ?? 'Normal';
                        $levelClass = $level === 'out' ? 'text-rose-700' : ($level === 'low' ? 'text-orange-700' : ($level === 'high' ? 'text-brand-700' : 'text-emerald-700'));
                        ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-3 font-semibold"><?= e((string) ($inventoryLevel['item_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-3 text-right"><?= e(number_format((float) ($inventoryLevel['stock_qty'] ?? 0), 2)) ?> <?= e((string) ($inventoryLevel['unit'] ?? '')) ?></td>
                            <td class="py-2 pr-3 text-right"><?= e(number_format((float) ($inventoryLevel['reorder_level'] ?? 0), 2)) ?></td>
                            <td class="py-2 text-right font-bold <?= e($levelClass) ?>"><?= e($levelLabel) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-3 text-slate-500">No approved inventory items available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php endif; ?>

<?php if ($isInventoryDepartment): ?>
<?php foreach ($inventoryPurchaseOrderRows as $purchaseOrderRow): ?>
    <?php $purchaseOrderRowId = (int) ($purchaseOrderRow['id'] ?? 0); ?>
    <div id="prepare-inventory-po-<?= e((string) $purchaseOrderRowId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'prepare-inventory-po-<?= e((string) $purchaseOrderRowId) ?>')">
        <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <h4 class="text-lg font-extrabold text-slate-900">Confirm Request #<?= e((string) $purchaseOrderRowId) ?></h4>
                <button type="button" onclick="closeModal('prepare-inventory-po-<?= e((string) $purchaseOrderRowId) ?>')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
            </div>

            <form method="post" action="handlers.php" class="mt-4 grid gap-3 md:grid-cols-2" data-disable-on-submit>
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="inventory_prepare_purchase_order">
                <input type="hidden" name="dept" value="purchasing">
                <input type="hidden" name="redirect_dept" value="inventory">
                <input type="hidden" name="id" value="<?= e((string) $purchaseOrderRowId) ?>">

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Item to Purchase *</label>
                    <select name="inventory_item_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                        <option value="">Select inventory item</option>
                        <?php foreach ($approvedInventoryItems as $item): ?>
                            <option value="<?= e((string) $item['id']) ?>" data-item-unit="<?= e((string) ($item['unit'] ?? '')) ?>" data-item-stock="<?= e((string) number_format((float) ($item['stock_qty'] ?? 0), 2)) ?>" <?= (int) ($purchaseOrderRow['inventory_item_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= e($formatInventoryItemLabel($item, false)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Order Quantity *</label>
                    <input type="number" name="requested_qty" step="0.01" min="0.01" value="<?= e((string) ($purchaseOrderRow['requested_qty'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                    <p class="mt-1 text-xs font-semibold text-slate-500" data-purchase-unit-display>Unit Type: Select ingredient first</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Supplier Name</label>
                    <input type="text" name="supplier_name" value="<?= e((string) ($purchaseOrderRow['supplier_name'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Total Cost</label>
                    <input type="number" name="quoted_unit_cost" step="0.01" min="0" value="<?= e((string) ($purchaseOrderRow['quoted_unit_cost'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700">Expected Delivery Date</label>
                    <input type="date" name="expected_delivery_date" value="<?= e((string) ($purchaseOrderRow['expected_delivery_date'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700">Notes</label>
                    <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"><?= e((string) ($purchaseOrderRow['notes'] ?? '')) ?></textarea>
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Confirm</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h4 class="text-sm font-extrabold text-slate-900">Low and High Stock Levels</h4>
    <p class="mt-1 text-xs text-slate-500">Inventory prepares purchase orders for Purchasing approval from this stock review.</p>
    <div class="table-scroll mt-3">
        <table class="stack-table w-full min-w-[620px] text-sm">
            <thead>
            <tr class="text-left text-slate-500">
                <th class="pb-2 pr-3" data-priority="high">Item</th>
                <th class="pb-2 pr-3 text-right" data-priority="high">Stock</th>
                <th class="pb-2 pr-3 text-right" data-priority="medium">Reorder</th>
                <th class="pb-2 text-right" data-priority="high">Level</th>
            </tr>
            </thead>
            <tbody class="text-slate-700">
            <?php if ($workflowInventoryRows): ?>
                <?php foreach ($workflowInventoryRows as $inventoryLevel): ?>
                    <?php
                    $level = (string) ($inventoryLevel['stock_level'] ?? 'normal');
                    $levelLabel = ['out' => 'Out', 'low' => 'Low', 'high' => 'High', 'normal' => 'Normal'][$level] ?? 'Normal';
                    $levelClass = $level === 'out' ? 'text-rose-700' : ($level === 'low' ? 'text-orange-700' : ($level === 'high' ? 'text-brand-700' : 'text-emerald-700'));
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-3 font-semibold"><?= e((string) ($inventoryLevel['item_name'] ?? '-')) ?></td>
                        <td class="py-2 pr-3 text-right"><?= e(number_format((float) ($inventoryLevel['stock_qty'] ?? 0), 2)) ?> <?= e((string) ($inventoryLevel['unit'] ?? '')) ?></td>
                        <td class="py-2 pr-3 text-right"><?= e(number_format((float) ($inventoryLevel['reorder_level'] ?? 0), 2)) ?></td>
                        <td class="py-2 text-right font-bold <?= e($levelClass) ?>"><?= e($levelLabel) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="py-3 text-slate-500">No approved inventory items available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <h4 class="text-sm font-extrabold text-slate-900">Purchase Requests Received</h4>
        <p class="mt-1 text-xs text-slate-500">Inventory confirms requested items and supplier details before sending the purchase order to Purchasing.</p>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[760px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-3" data-priority="high">Code</th>
                    <th class="pb-2 pr-3" data-priority="high">Item</th>
                    <th class="pb-2 pr-3 text-right" data-priority="medium">Qty</th>
                    <th class="pb-2 pr-3" data-priority="medium">Supplier</th>
                    <th class="pb-2 pr-3 text-right" data-priority="medium">Cost</th>
                    <th class="pb-2 text-right" data-priority="high">Action</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($inventoryPurchaseOrderRows): ?>
                    <?php foreach ($inventoryPurchaseOrderRows as $purchaseOrderRow): ?>
                        <?php $purchaseOrderRowId = (int) ($purchaseOrderRow['id'] ?? 0); ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-3 font-semibold"><?= e((string) ($purchaseOrderRow['request_code'] ?? '-')) ?></td>
                            <td class="py-2 pr-3"><?= e((string) ($purchaseOrderRow['item_name'] ?? ('Item #' . (int) ($purchaseOrderRow['inventory_item_id'] ?? 0)))) ?></td>
                            <td class="py-2 pr-3 text-right"><?= e(number_format((float) ($purchaseOrderRow['requested_qty'] ?? 0), 2)) ?> <?= e((string) ($purchaseOrderRow['unit'] ?? '')) ?></td>
                            <td class="py-2 pr-3"><?= e((string) ($purchaseOrderRow['supplier_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-3 text-right"><?= e(format_money((float) ($purchaseOrderRow['estimated_total'] ?? 0))) ?></td>
                            <td class="py-2 text-right">
                                <?php $inventoryActionLabel = inventory_purchase_order_action_label($purchaseOrderRow); ?>
                                <?php if ($inventoryActionLabel !== null): ?>
                                    <div class="inline-flex items-center gap-2">
                                        <form method="post" action="handlers.php" class="m-0" data-disable-on-submit>
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="inventory_prepare_purchase_order">
                                            <input type="hidden" name="dept" value="purchasing">
                                            <input type="hidden" name="redirect_dept" value="inventory">
                                            <input type="hidden" name="id" value="<?= e((string) $purchaseOrderRowId) ?>">
                                            <input type="hidden" name="inventory_item_id" value="<?= e((string) ($purchaseOrderRow['inventory_item_id'] ?? '')) ?>">
                                            <input type="hidden" name="requested_qty" value="<?= e((string) ($purchaseOrderRow['requested_qty'] ?? '')) ?>">
                                            <input type="hidden" name="supplier_name" value="<?= e((string) ($purchaseOrderRow['supplier_name'] ?? '')) ?>">
                                            <input type="hidden" name="quoted_unit_cost" value="<?= e((string) ($purchaseOrderRow['quoted_unit_cost'] ?? '')) ?>">
                                            <input type="hidden" name="expected_delivery_date" value="<?= e((string) ($purchaseOrderRow['expected_delivery_date'] ?? '')) ?>">
                                            <input type="hidden" name="notes" value="<?= e((string) ($purchaseOrderRow['notes'] ?? '')) ?>">
                                            <button type="submit" class="rounded-lg border border-brand-300 bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700 hover:bg-brand-100"><?= e($inventoryActionLabel) ?></button>
                                        </form>
                                        <button type="button" onclick="openModal('prepare-inventory-po-<?= e((string) $purchaseOrderRowId) ?>')" class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50">Review</button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="py-3 text-slate-500">No purchase requests awaiting Inventory confirmation.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($department === 'sales'): ?>
<div id="modal-production-stock" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'modal-production-stock')">
    <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h4 class="text-lg font-extrabold text-slate-900">Today's Production Stock</h4>
                <p class="mt-0.5 text-xs text-slate-500">Produced today minus cups already sold. Resets at midnight.</p>
            </div>
            <button type="button" onclick="closeModal('modal-production-stock')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
        </div>

        <div class="mt-4">
            <?php if ($todayProductionStock): ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <?php foreach ($todayProductionStock as $stockRow): ?>
                        <?php
                        $remaining = (int) ($stockRow['remaining'] ?? 0);
                        $produced = (int) ($stockRow['produced'] ?? 0);
                        $sold = (int) ($stockRow['sold'] ?? 0);
                        $remainingClass = $remaining <= 0 ? 'text-rose-700' : ($remaining <= 10 ? 'text-amber-700' : 'text-emerald-700');
                        ?>
                        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-sm font-extrabold text-slate-900"><?= e((string) ($stockRow['beverage_name'] ?? '-')) ?></p>
                            <div class="mt-2 grid grid-cols-3 gap-1 text-center text-xs">
                                <div class="rounded-lg bg-slate-50 p-2">
                                    <p class="font-bold text-slate-500 uppercase tracking-wide">Produced</p>
                                    <p class="mt-1 text-lg font-extrabold text-slate-900"><?= e((string) $produced) ?></p>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-2">
                                    <p class="font-bold text-slate-500 uppercase tracking-wide">Sold</p>
                                    <p class="mt-1 text-lg font-extrabold text-slate-900"><?= e((string) $sold) ?></p>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-2">
                                    <p class="font-bold text-slate-500 uppercase tracking-wide">Remaining</p>
                                    <p class="mt-1 text-lg font-extrabold <?= e($remainingClass) ?>"><?= e((string) max(0, $remaining)) ?></p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">No production logged for today yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($department === 'sales'): ?>
<div id="modal-sales-production-log" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'modal-sales-production-log')">
    <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <h4 class="text-lg font-extrabold text-slate-900">Log Daily Production</h4>
            <button type="button" onclick="closeModal('modal-sales-production-log')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
        </div>

        <form method="post" action="handlers.php" class="mt-4 grid gap-3 md:grid-cols-2">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create_record">
            <input type="hidden" name="dept" value="production">
            <input type="hidden" name="redirect_dept" value="sales">

            <div>
                <label class="block text-sm font-semibold text-slate-700">Beverage Name *</label>
                <?php if ($recipeOptions === []): ?>
                    <div class="mt-1 rounded-xl border border-slate-300 bg-slate-50 p-3">
                        <p class="text-xs font-semibold text-rose-600">No active beverage recipes available.</p>
                    </div>
                <?php else: ?>
                    <select name="beverage_name" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required data-recipe-tooltip>
                        <option value="">Select beverage recipe</option>
                        <?php foreach ($recipeOptions as $recipeOption): ?>
                            <?php
                            $recipeName = (string) ($recipeOption['beverage_name'] ?? '');
                            $ingredientsTooltip = (string) ($recipeOption['ingredients_label'] ?? 'Ingredients: Not set');
                            ?>
                            <option value="<?= e($recipeName) ?>" title="<?= e($ingredientsTooltip) ?>" data-ingredients="<?= e($ingredientsTooltip) ?>"><?= e($recipeName) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">Quantity Prepared (cups) *</label>
                <input type="number" name="quantity_prepared" step="1" min="1" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"></textarea>
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Save Production Log</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($department === 'production' || $department === 'inventory'): ?>
<?php
$purchaseWorkflowModalId = $department === 'production' ? 'modal-production-purchase-request' : 'modal-inventory-purchase-order';
$purchaseWorkflowTitle = $department === 'production' ? 'Prepare Purchase Request' : 'New Purchase Order';
$purchaseWorkflowSubmit = $department === 'production' ? 'Save Purchase Request' : 'Confirm';
$purchaseWorkflowNote = $department === 'production'
    ? 'Production sends this request when stock is low after order preparation.'
    : 'Inventory confirms this purchase order and forwards it to Purchasing.';
?>
<div id="<?= e($purchaseWorkflowModalId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, '<?= e($purchaseWorkflowModalId) ?>')">
    <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h4 class="text-lg font-extrabold text-slate-900"><?= e($purchaseWorkflowTitle) ?></h4>
                <p class="mt-0.5 text-xs text-slate-500"><?= e($purchaseWorkflowNote) ?></p>
            </div>
            <button type="button" onclick="closeModal('<?= e($purchaseWorkflowModalId) ?>')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
        </div>

        <form method="post" action="handlers.php" class="mt-4 grid gap-3 md:grid-cols-2"<?= $department === 'inventory' ? ' data-disable-on-submit' : '' ?>>
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create_record">
            <input type="hidden" name="dept" value="purchasing">
            <input type="hidden" name="redirect_dept" value="<?= e($department) ?>">

            <div>
                <label class="block text-sm font-semibold text-slate-700">Item to Purchase *</label>
                <select name="inventory_item_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                    <option value="">Select inventory item</option>
                    <?php foreach ($approvedInventoryItems as $item): ?>
                        <option value="<?= e((string) $item['id']) ?>" data-item-unit="<?= e((string) ($item['unit'] ?? '')) ?>" data-item-stock="<?= e((string) number_format((float) ($item['stock_qty'] ?? 0), 2)) ?>"><?= e($formatInventoryItemLabel($item, false)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">Order Quantity *</label>
                <input type="number" name="requested_qty" step="0.01" min="0.01" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                <p class="mt-1 text-xs font-semibold text-slate-500" data-purchase-unit-display>Unit Type: Select ingredient first</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">Supplier Name</label>
                <input type="text" name="supplier_name" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">Total Cost</label>
                <input type="number" name="quoted_unit_cost" step="0.01" min="0" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700">Expected Delivery Date</label>
                <input type="date" name="expected_delivery_date" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"></textarea>
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800"><?= e($purchaseWorkflowSubmit) ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($showCreateModal): ?>
<div id="modal-create" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'modal-create')">
    <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <h4 class="text-lg font-extrabold text-slate-900"><?= e($createButtonLabel) ?></h4>
            <button type="button" onclick="closeModal('modal-create')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
        </div>

        <form method="post" action="handlers.php" class="mt-4 grid gap-3 md:grid-cols-2">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create_record">
            <input type="hidden" name="dept" value="<?= e($department) ?>">

            <?php foreach ($config['fields'] as $field): ?>
                <?php
                $fieldName = $field['name'];
                $fieldType = $field['type'];
                $required = (bool) ($field['required'] ?? false);
                if ($department === 'sales' && $fieldName === 'beverage_name') {
                    ?>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700">Order Items *</label>
                        <?php $renderSalesOrderItemsBuilder($recipeOptions, [], 'sales-create-items'); ?>
                    </div>
                    <?php
                    continue;
                }
                if ($department === 'sales' && in_array($fieldName, ['quantity', 'unit_price'], true)) {
                    continue;
                }
                $fieldClass = in_array($fieldType, ['textarea', 'inventory_multi_select'], true) ? 'md:col-span-2' : '';
                ?>
                <div class="<?= e($fieldClass) ?>">
                    <label class="block text-sm font-semibold text-slate-700"><?= e($field['label']) ?><?= $required ? ' *' : '' ?></label>

                    <?php if ($fieldType === 'textarea'): ?>
                        <textarea name="<?= e($fieldName) ?>" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>></textarea>
                    <?php elseif ($fieldType === 'select'): ?>
                        <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                            <option value="">Select option</option>
                            <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                                <option value="<?= e((string) $optionValue) ?>"><?= e((string) $optionLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($fieldType === 'recipe_select'): ?>
                        <?php if ($recipeOptions === []): ?>
                            <div class="mt-1 rounded-xl border border-slate-300 bg-slate-50 p-3">
                                <p class="text-xs font-semibold text-rose-600">No active beverage recipes available. Add recipes to enable production and sales entry.</p>
                            </div>
                        <?php else: ?>
                            <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?> data-recipe-tooltip>
                                <option value="">Select beverage recipe</option>
                                <?php foreach ($recipeOptions as $recipeOption): ?>
                                    <?php
                                    $recipeName = (string) ($recipeOption['beverage_name'] ?? '');
                                    $ingredientsTooltip = (string) ($recipeOption['ingredients_label'] ?? 'Ingredients: Not set');
                                    ?>
                                    <option value="<?= e($recipeName) ?>" title="<?= e($ingredientsTooltip) ?>" data-ingredients="<?= e($ingredientsTooltip) ?>"><?= e($recipeName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    <?php elseif ($fieldType === 'inventory_select'): ?>
                        <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                            <option value="">Select inventory item</option>
                            <?php foreach ($approvedIngredientItems as $item): ?>
                                <option value="<?= e((string) $item['id']) ?>" data-item-unit="<?= e((string) ($item['unit'] ?? '')) ?>" data-item-stock="<?= e((string) number_format((float) ($item['stock_qty'] ?? 0), 2)) ?>"><?= e($formatInventoryItemLabel($item, false)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($fieldType === 'crm_select'): ?>
                        <?php if ($approvedCrmProfiles === []): ?>
                            <div class="mt-1 rounded-xl border border-slate-300 bg-slate-50 p-3">
                                <p class="text-xs font-semibold text-rose-600">No approved CRM profiles available. Create a CRM profile first before creating sales orders.</p>
                            </div>
                        <?php else: ?>
                            <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                                <option value="">Select CRM customer</option>
                                <?php foreach ($approvedCrmProfiles as $profile): ?>
                                    <option value="<?= e((string) $profile['customer_name']) ?>"><?= e((string) $profile['customer_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    <?php elseif ($fieldType === 'inventory_multi_select'): ?>
                        <?php
                        $selectionGroupId = 'create-' . $fieldName;
                        $syrupNames = ['caramel syrup', 'matcha coffee syrup', 'spanish latte syrup', 'hazelnuts syrup', 'vanilla syrup'];
                        $syrupItems = [];
                        $nonSyrupItems = [];
                        foreach ($approvedIngredientItems as $item) {
                            if (in_array(strtolower(trim((string) ($item['item_name'] ?? ''))), $syrupNames, true)) {
                                $syrupItems[] = $item;
                            } else {
                                $nonSyrupItems[] = $item;
                            }
                        }
                        $syrupSelectId = $selectionGroupId . '-syrup-select';
                        ?>
                        <div class="mt-1 rounded-xl border border-slate-300 bg-slate-50 p-3">
                            <label class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-600">
                                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900" data-select-all="<?= e($selectionGroupId) ?>">
                                <span>Select all ingredients</span>
                            </label>

                            <?php if ($syrupItems !== []): ?>
                                <div class="mt-3">
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Syrup Flavor</label>
                                    <select id="<?= e($syrupSelectId) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" onchange="syncSyrupSelection('<?= e($selectionGroupId) ?>', this.value)">
                                        <option value="">— No syrup —</option>
                                        <?php foreach ($syrupItems as $item): ?>
                                            <option value="<?= e((string) $item['id']) ?>"><?= e((string) ($item['item_name'] ?? '')) ?> (<?= e(number_format((float) ($item['stock_qty'] ?? 0), 2)) ?> <?= e((string) ($item['unit'] ?? '')) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php foreach ($syrupItems as $item): ?>
                                        <?php $inputId = $selectionGroupId . '-' . (int) $item['id']; ?>
                                        <input id="<?= e($inputId) ?>" type="checkbox" name="<?= e($fieldName) ?>[]" value="<?= e((string) $item['id']) ?>" data-select-item="<?= e($selectionGroupId) ?>" data-syrup-checkbox class="hidden">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <?php foreach ($nonSyrupItems as $item): ?>
                                    <?php $inputId = $selectionGroupId . '-' . (int) $item['id']; ?>
                                    <label for="<?= e($inputId) ?>" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                        <input id="<?= e($inputId) ?>" type="checkbox" name="<?= e($fieldName) ?>[]" value="<?= e((string) $item['id']) ?>" data-select-item="<?= e($selectionGroupId) ?>" class="h-4 w-4 rounded border-slate-300 text-slate-900">
                                        <span><?= e($formatInventoryItemLabel($item)) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($approvedIngredientItems === []): ?>
                                <p class="mt-2 text-xs font-semibold text-rose-600">No approved inventory ingredients available.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php
                        $inputType = $fieldType === 'number' ? 'number' : ($fieldType === 'date' ? 'date' : 'text');
                        $stepAttribute = $inputType === 'number' ? ' step="' . e((string) ($field['step'] ?? 'any')) . '"' : '';
                        ?>
                        <input type="<?= e($inputType) ?>" name="<?= e($fieldName) ?>"<?= $stepAttribute ?> class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                        <?php if ($department === 'purchasing' && $fieldName === 'requested_qty'): ?>
                            <p class="mt-1 text-xs font-semibold text-slate-500" data-purchase-unit-display>Unit Type: Select ingredient first</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800"><?= e($submitLabel) ?></button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php foreach ($rows as $row): ?>
    <?php
    $rowId = (int) $row['id'];
    $canManage = $department !== 'inventory'
        && $department !== 'production'
        && !($department === 'purchasing' && !empty($row['inventory_confirmed_at']))
        && !($department === 'sales' && ($row['status'] ?? '') === 'approved')
        && ((($user['role'] ?? '') === ROLE_GENERAL_MANAGER)
            || (int) ($row['submitted_by'] ?? 0) === (int) ($user['id'] ?? 0));
    $canPurchasingDecide = $department === 'purchasing'
        && can_purchasing_process_purchase_order($row)
        && can_user_access_department($user ?? [], 'purchasing');
    $purchasingActionLabel = $canPurchasingDecide ? purchasing_purchase_order_action_label($row) : null;
    $isApproved = ($row['status'] ?? '') === 'approved';
    ?>

    <div id="view-<?= e($department) ?>-<?= e((string) $rowId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'view-<?= e($department) ?>-<?= e((string) $rowId) ?>')">
        <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <h4 class="text-lg font-extrabold text-slate-900">View Record #<?= e((string) $rowId) ?></h4>
                <button type="button" onclick="closeModal('view-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
            </div>

            <div class="mt-4 grid gap-2 text-sm">
                <?php foreach ($config['fields'] as $field): ?>
                    <?php
                    $name = $field['name'];
                    $value = $row[$name] ?? null;
                    if ($name === 'inventory_item_id') {
                        $value = $inventoryMap[(int) ($row[$name] ?? 0)] ?? '-';
                    } elseif ($name === 'ingredient_item_ids') {
                        $value = $formatIngredientSelection($row[$name] ?? null, $row);
                    }
                    ?>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500"><?= e($field['label']) ?></p>
                        <p class="font-semibold text-slate-800"><?= e(format_table_value($name, $value)) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php if ($department === 'sales'): ?>
                    <?php $salesViewItems = sales_order_receipt_items($row, $salesOrderItemsByOrder[$rowId] ?? []); ?>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Order Items</p>
                        <div class="mt-2 space-y-1">
                            <?php foreach ($salesViewItems as $salesViewItem): ?>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-semibold text-slate-800"><?= e((string) ($salesViewItem['beverage_name'] ?? '-')) ?></span>
                                    <span class="whitespace-nowrap text-slate-600"><?= e((string) ((int) ($salesViewItem['quantity'] ?? 0))) ?> x <?= e(format_money((float) ($salesViewItem['unit_price'] ?? 0))) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Total Amount</p>
                        <p class="font-semibold text-slate-800"><?= e(format_money((float) ($row['total_amount'] ?? 0))) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($department === 'crm'): ?>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Purchase Count</p>
                        <p class="font-semibold text-slate-800"><?= e((string) ((int) ($row['purchase_count'] ?? 0))) ?></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Total Spent</p>
                        <p class="font-semibold text-slate-800"><?= e(format_money((float) ($row['total_spent'] ?? 0))) ?></p>
                    </div>
                <?php endif; ?>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Status</p>
                    <p class="font-semibold text-slate-800"><?= e(strtoupper((string) ($row['status'] ?? 'pending'))) ?></p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Approval Note</p>
                    <p class="font-semibold text-slate-800"><?= e((string) ($row['approval_note'] ?? '-')) ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManage): ?>
        <div id="edit-<?= e($department) ?>-<?= e((string) $rowId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'edit-<?= e($department) ?>-<?= e((string) $rowId) ?>')">
            <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <h4 class="text-lg font-extrabold text-slate-900">Edit Record #<?= e((string) $rowId) ?></h4>
                    <button type="button" onclick="closeModal('edit-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
                </div>

                <form method="post" action="handlers.php" class="mt-4 grid gap-3 md:grid-cols-2">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="edit_record">
                    <input type="hidden" name="dept" value="<?= e($department) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $rowId) ?>">

                    <?php foreach ($config['fields'] as $field): ?>
                        <?php
                        $fieldName = $field['name'];
                        $fieldType = $field['type'];
                        $required = (bool) ($field['required'] ?? false);
                        $value = $row[$fieldName] ?? '';
                        if ($department === 'sales' && $fieldName === 'beverage_name') {
                            $editSalesItems = sales_order_receipt_items($row, $salesOrderItemsByOrder[$rowId] ?? []);
                            ?>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-slate-700">Order Items *</label>
                                <?php $renderSalesOrderItemsBuilder($recipeOptions, $editSalesItems, 'sales-edit-items-' . $rowId); ?>
                            </div>
                            <?php
                            continue;
                        }
                        if ($department === 'sales' && in_array($fieldName, ['quantity', 'unit_price'], true)) {
                            continue;
                        }
                        $fieldClass = in_array($fieldType, ['textarea', 'inventory_multi_select'], true) ? 'md:col-span-2' : '';
                        $selectedInventoryValues = [];
                        if ($fieldType === 'inventory_multi_select') {
                            $selectedInventoryValues = $fieldName === 'ingredient_item_ids'
                                ? inventory_item_ids_from_record($row)
                                : normalize_inventory_item_ids($value);
                        }
                        ?>
                        <div class="<?= e($fieldClass) ?>">
                            <label class="block text-sm font-semibold text-slate-700"><?= e($field['label']) ?><?= $required ? ' *' : '' ?></label>

                            <?php if ($fieldType === 'textarea'): ?>
                                <textarea name="<?= e($fieldName) ?>" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>><?= e((string) $value) ?></textarea>
                            <?php elseif ($fieldType === 'select'): ?>
                                <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                                    <option value="">Select option</option>
                                    <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                                        <option value="<?= e((string) $optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= e((string) $optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($fieldType === 'recipe_select'): ?>
                                <?php if ($recipeOptions === []): ?>
                                    <div class="mt-1 rounded-xl border border-slate-300 bg-slate-50 p-3">
                                        <p class="text-xs font-semibold text-rose-600">No active beverage recipes available. Add recipes to enable production and sales entry.</p>
                                    </div>
                                <?php else: ?>
                                    <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?> data-recipe-tooltip>
                                        <option value="">Select beverage recipe</option>
                                        <?php foreach ($recipeOptions as $recipeOption): ?>
                                            <?php
                                            $recipeName = (string) ($recipeOption['beverage_name'] ?? '');
                                            $ingredientsTooltip = (string) ($recipeOption['ingredients_label'] ?? 'Ingredients: Not set');
                                            ?>
                                            <option value="<?= e($recipeName) ?>" title="<?= e($ingredientsTooltip) ?>" data-ingredients="<?= e($ingredientsTooltip) ?>" <?= (string) $value === $recipeName ? 'selected' : '' ?>><?= e($recipeName) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            <?php elseif ($fieldType === 'inventory_select'): ?>
                                <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                                    <option value="">Select inventory item</option>
                                    <?php foreach ($approvedIngredientItems as $item): ?>
                                        <option value="<?= e((string) $item['id']) ?>" data-item-unit="<?= e((string) ($item['unit'] ?? '')) ?>" data-item-stock="<?= e((string) number_format((float) ($item['stock_qty'] ?? 0), 2)) ?>" <?= (int) $value === (int) $item['id'] ? 'selected' : '' ?>><?= e($formatInventoryItemLabel($item, false)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($fieldType === 'crm_select'): ?>
                                <?php if ($approvedCrmProfiles === []): ?>
                                    <div class="mt-1 rounded-xl border border-slate-300 bg-slate-50 p-3">
                                        <p class="text-xs font-semibold text-rose-600">No approved CRM profiles available.</p>
                                    </div>
                                <?php else: ?>
                                    <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                                        <option value="">Select CRM customer</option>
                                        <?php foreach ($approvedCrmProfiles as $profile): ?>
                                            <option value="<?= e((string) $profile['customer_name']) ?>" <?= (string) $value === (string) $profile['customer_name'] ? 'selected' : '' ?>><?= e((string) $profile['customer_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            <?php elseif ($fieldType === 'inventory_multi_select'): ?>
                                <?php
                                $selectionGroupId = 'edit-' . $rowId . '-' . $fieldName;
                                $syrupNames = ['caramel syrup', 'matcha coffee syrup', 'spanish latte syrup', 'hazelnuts syrup', 'vanilla syrup'];
                                $syrupItems = [];
                                $nonSyrupItems = [];
                                foreach ($approvedIngredientItems as $item) {
                                    if (in_array(strtolower(trim((string) ($item['item_name'] ?? ''))), $syrupNames, true)) {
                                        $syrupItems[] = $item;
                                    } else {
                                        $nonSyrupItems[] = $item;
                                    }
                                }
                                $syrupSelectId = $selectionGroupId . '-syrup-select';
                                $selectedSyrupId = '';
                                foreach ($syrupItems as $item) {
                                    if (in_array((int) $item['id'], $selectedInventoryValues, true)) {
                                        $selectedSyrupId = (string) $item['id'];
                                        break;
                                    }
                                }
                                ?>
                                <div class="mt-1 rounded-xl border border-slate-300 bg-slate-50 p-3">
                                    <label class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-600">
                                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900" data-select-all="<?= e($selectionGroupId) ?>">
                                        <span>Select all ingredients</span>
                                    </label>

                                    <?php if ($syrupItems !== []): ?>
                                        <div class="mt-3">
                                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Syrup Flavor</label>
                                            <select id="<?= e($syrupSelectId) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" onchange="syncSyrupSelection('<?= e($selectionGroupId) ?>', this.value)">
                                                <option value="">— No syrup —</option>
                                                <?php foreach ($syrupItems as $item): ?>
                                                    <option value="<?= e((string) $item['id']) ?>" <?= $selectedSyrupId === (string) $item['id'] ? 'selected' : '' ?>><?= e((string) ($item['item_name'] ?? '')) ?> (<?= e(number_format((float) ($item['stock_qty'] ?? 0), 2)) ?> <?= e((string) ($item['unit'] ?? '')) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php foreach ($syrupItems as $item): ?>
                                                <?php
                                                $inputId = $selectionGroupId . '-' . (int) $item['id'];
                                                $isChecked = in_array((int) $item['id'], $selectedInventoryValues, true);
                                                ?>
                                                <input id="<?= e($inputId) ?>" type="checkbox" name="<?= e($fieldName) ?>[]" value="<?= e((string) $item['id']) ?>" data-select-item="<?= e($selectionGroupId) ?>" data-syrup-checkbox class="hidden" <?= $isChecked ? 'checked' : '' ?>>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <?php foreach ($nonSyrupItems as $item): ?>
                                            <?php
                                            $inputId = $selectionGroupId . '-' . (int) $item['id'];
                                            $isChecked = in_array((int) $item['id'], $selectedInventoryValues, true);
                                            ?>
                                            <label for="<?= e($inputId) ?>" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                                <input id="<?= e($inputId) ?>" type="checkbox" name="<?= e($fieldName) ?>[]" value="<?= e((string) $item['id']) ?>" data-select-item="<?= e($selectionGroupId) ?>" class="h-4 w-4 rounded border-slate-300 text-slate-900" <?= $isChecked ? 'checked' : '' ?>>
                                                <span><?= e($formatInventoryItemLabel($item)) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if ($approvedIngredientItems === []): ?>
                                        <p class="mt-2 text-xs font-semibold text-rose-600">No approved inventory ingredients available.</p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php
                                $inputType = $fieldType === 'number' ? 'number' : ($fieldType === 'date' ? 'date' : 'text');
                                $stepAttribute = $inputType === 'number' ? ' step="' . e((string) ($field['step'] ?? 'any')) . '"' : '';
                                ?>
                                <input type="<?= e($inputType) ?>" name="<?= e($fieldName) ?>" value="<?= e((string) $value) ?>"<?= $stepAttribute ?> class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                                <?php if ($department === 'purchasing' && $fieldName === 'requested_qty'): ?>
                                    <p class="mt-1 text-xs font-semibold text-slate-500" data-purchase-unit-display>Unit Type: Select ingredient first</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800"><?= e($editLabel) ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div id="delete-<?= e($department) ?>-<?= e((string) $rowId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'delete-<?= e($department) ?>-<?= e((string) $rowId) ?>')">
            <div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
                <h4 class="text-lg font-extrabold text-slate-900">Delete Record #<?= e((string) $rowId) ?></h4>
                <p class="mt-2 text-sm text-slate-600">This action cannot be undone.</p>

                <form method="post" action="handlers.php" class="mt-4 flex justify-end gap-2">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete_record">
                    <input type="hidden" name="dept" value="<?= e($department) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $rowId) ?>">
                    <button type="button" onclick="closeModal('delete-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-500">Delete</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($purchasingActionLabel !== null): ?>
        <div id="approve-purchase-order-<?= e((string) $rowId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'approve-purchase-order-<?= e((string) $rowId) ?>')">
            <div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
                <h4 class="text-lg font-extrabold text-slate-900">Make Order #<?= e((string) $rowId) ?></h4>
                <p class="mt-2 text-sm text-slate-600">Making this order sends it to the General Manager for final purchase approval.</p>

                <form method="post" action="handlers.php" class="mt-4 space-y-3" data-disable-on-submit>
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="purchasing_decide_purchase_order">
                    <input type="hidden" name="dept" value="purchasing">
                    <input type="hidden" name="id" value="<?= e((string) $rowId) ?>">
                    <input type="hidden" name="decision" value="processed">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700">Order Note (optional)</label>
                        <textarea name="approval_note" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeModal('approve-purchase-order-<?= e((string) $rowId) ?>')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500"><?= e($purchasingActionLabel) ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($department === 'sales'): ?>
    <script src="assets/vendor/jspdf/jspdf.umd.min.js?v=<?= e($jsPdfVersion) ?>"></script>
    <script>
        function printSalesReceiptFromButton(button) {
            const rawPayload = button ? (button.getAttribute('data-receipt') || '{}') : '{}';
            let payload = {};

            try {
                payload = JSON.parse(rawPayload);
            } catch (error) {
                alert('Unable to parse receipt data. Please refresh and try again.');
                return;
            }

            const jsPdfNamespace = window.jspdf;
            if (!jsPdfNamespace || !jsPdfNamespace.jsPDF) {
                alert('jsPDF failed to load. Please refresh and try again.');
                return;
            }

            const doc = new jsPdfNamespace.jsPDF({ unit: 'pt', format: [226.77, 600] });
            let y = 24;
            const left = 14;
            const maxWidth = 196;
            const lineHeight = 11;

            const writeWrappedLine = function (label, value, bold) {
                const text = label + ': ' + (value || '-');
                const lines = doc.splitTextToSize(text, maxWidth);
                doc.setFont('helvetica', bold ? 'bold' : 'normal');
                doc.setFontSize(9);
                lines.forEach(function (line) {
                    doc.text(line, left, y);
                    y += lineHeight;
                });
            };

            const writeTextLine = function (value, bold) {
                const lines = doc.splitTextToSize(value || '-', maxWidth);
                doc.setFont('helvetica', bold ? 'bold' : 'normal');
                doc.setFontSize(9);
                lines.forEach(function (line) {
                    doc.text(line, left, y);
                    y += lineHeight;
                });
            };

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            doc.text('Don Macchiatos', left, y);
            y += 14;

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.text('Sales Receipt', left, y);
            y += 12;
            doc.text('----------------------------------------------', left, y);
            y += 12;

            writeWrappedLine('Receipt No', payload.receipt_no || '-', true);
            writeWrappedLine('Order Code', payload.order_code || '-', false);
            writeWrappedLine('Customer', payload.customer_name || '-', false);
            const receiptItems = Array.isArray(payload.items) && payload.items.length > 0
                ? payload.items
                : [{
                    beverage_name: payload.beverage_name || '-',
                    quantity: payload.quantity || 0,
                    unit_price: payload.unit_price || 0,
                    total_amount: payload.total_amount || 0
                }];

            writeTextLine('Items', true);
            receiptItems.forEach(function (item) {
                const itemName = item.beverage_name || '-';
                const itemQuantity = Number(item.quantity || 0);
                const itemUnitPrice = Number(item.unit_price || 0);
                const itemTotal = Number(item.total_amount || (itemQuantity * itemUnitPrice));
                writeTextLine(itemName + ' | ' + itemQuantity.toFixed(0) + ' x PHP ' + itemUnitPrice.toFixed(2) + ' = PHP ' + itemTotal.toFixed(2), false);
            });
            writeWrappedLine('Payment Method', payload.payment_method || '-', false);
            if (payload.payment_reference) {
                writeWrappedLine('Payment Ref', payload.payment_reference, false);
            }
            writeWrappedLine('Paid At', payload.paid_at || '-', false);
            writeWrappedLine('Cashier Name', payload.cashier_name || '-', false);
            writeWrappedLine('Contact Information', payload.contact_information || '-', false);

            y += 2;
            doc.text('----------------------------------------------', left, y);
            y += 12;

            writeWrappedLine('Total Amount', 'PHP ' + Number(payload.total_amount || 0).toFixed(2), true);

            y += 6;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.text('Thank you for your order!', left, y);

            const filenameBase = (payload.receipt_no || payload.order_code || 'receipt').toString().replace(/\s+/g, '-');
            doc.save(filenameBase + '.pdf');
        }

        (function () {
            function formatMoney(value) {
                return 'PHP ' + Number(value || 0).toFixed(2);
            }

            function updateSalesOrderBuilder(builder) {
                const rows = Array.from(builder.querySelectorAll('[data-sales-item-row]'));
                const previewBody = builder.querySelector('[data-sales-items-preview]');
                const totalTarget = builder.querySelector('[data-sales-order-total]');
                let totalAmount = 0;
                const previewRows = [];

                rows.forEach(function (row) {
                    const flavorSelect = row.querySelector('[data-sales-item-name]');
                    const quantityInput = row.querySelector('[data-sales-item-quantity]');
                    const priceInput = row.querySelector('[data-sales-item-price]');
                    const selectedOption = flavorSelect ? flavorSelect.options[flavorSelect.selectedIndex] : null;
                    const flavorName = selectedOption && selectedOption.value ? selectedOption.textContent.trim() : '';
                    const quantity = Number(quantityInput ? quantityInput.value : 0);
                    const unitPrice = Number(priceInput ? priceInput.value : 0);
                    const lineAmount = quantity > 0 && unitPrice > 0 ? quantity * unitPrice : 0;

                    if (flavorName !== '' && lineAmount > 0) {
                        totalAmount += lineAmount;
                        previewRows.push({
                            flavorName: flavorName,
                            quantity: quantity,
                            unitPrice: unitPrice,
                            lineAmount: lineAmount
                        });
                    }
                });

                rows.forEach(function (row) {
                    const removeButton = row.querySelector('[data-sales-remove-item]');
                    if (!removeButton) {
                        return;
                    }

                    removeButton.disabled = rows.length <= 1;
                    removeButton.classList.toggle('cursor-not-allowed', rows.length <= 1);
                    removeButton.classList.toggle('opacity-50', rows.length <= 1);
                });

                if (previewBody) {
                    if (previewRows.length === 0) {
                        previewBody.innerHTML = '<tr><td colspan="4" class="px-3 py-3 text-slate-500">No items selected.</td></tr>';
                    } else {
                        previewBody.innerHTML = previewRows.map(function (item) {
                            return '<tr class="border-t border-slate-100">'
                                + '<td class="px-3 py-2 font-semibold text-slate-800">' + item.flavorName.replace(/[&<>"']/g, function (char) {
                                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
                                }) + '</td>'
                                + '<td class="px-3 py-2 text-right">' + item.quantity.toFixed(0) + '</td>'
                                + '<td class="px-3 py-2 text-right">' + formatMoney(item.unitPrice) + '</td>'
                                + '<td class="px-3 py-2 text-right font-bold">' + formatMoney(item.lineAmount) + '</td>'
                                + '</tr>';
                        }).join('');
                    }
                }

                if (totalTarget) {
                    totalTarget.textContent = formatMoney(totalAmount);
                }
            }

            function initializeSalesOrderBuilder(builder) {
                if (!builder || builder.dataset.salesOrderBuilderBound === '1') {
                    return;
                }

                builder.dataset.salesOrderBuilderBound = '1';
                const list = builder.querySelector('[data-sales-items-list]');
                const template = builder.querySelector('[data-sales-item-template]');
                const addButton = builder.querySelector('[data-sales-add-item]');
                builder.dataset.nextIndex = String(builder.querySelectorAll('[data-sales-item-row]').length);

                if (addButton && list && template) {
                    addButton.addEventListener('click', function () {
                        const nextIndex = Number(builder.dataset.nextIndex || '0');
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(nextIndex)).trim();
                        const row = wrapper.firstElementChild;
                        if (row) {
                            list.appendChild(row);
                            builder.dataset.nextIndex = String(nextIndex + 1);
                            updateSalesOrderBuilder(builder);
                        }
                    });
                }

                builder.addEventListener('click', function (event) {
                    const target = event.target instanceof Element ? event.target : null;
                    if (!target) {
                        return;
                    }

                    const removeButton = target.closest('[data-sales-remove-item]');
                    if (!removeButton) {
                        return;
                    }

                    const rows = builder.querySelectorAll('[data-sales-item-row]');
                    if (rows.length <= 1) {
                        return;
                    }

                    const row = removeButton.closest('[data-sales-item-row]');
                    if (row) {
                        row.remove();
                        updateSalesOrderBuilder(builder);
                    }
                });

                builder.addEventListener('input', function () {
                    updateSalesOrderBuilder(builder);
                });
                builder.addEventListener('change', function () {
                    updateSalesOrderBuilder(builder);
                });

                updateSalesOrderBuilder(builder);
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-sales-order-builder]').forEach(initializeSalesOrderBuilder);
            });
            window.addEventListener('load', function () {
                document.querySelectorAll('[data-sales-order-builder]').forEach(initializeSalesOrderBuilder);
            });
        })();
    </script>
<?php endif; ?>

<?php if (in_array($department, ['purchasing', 'production', 'inventory'], true)): ?>
    <script>
        (function () {
            function syncPurchasingUnitType(form) {
                if (!form) {
                    return;
                }

                const inventorySelect = form.querySelector('select[name="inventory_item_id"]');
                const unitDisplay = form.querySelector('[data-purchase-unit-display]');
                if (!inventorySelect || !unitDisplay) {
                    return;
                }

                const selectedOption = inventorySelect.options[inventorySelect.selectedIndex];
                const unitType = selectedOption ? (selectedOption.getAttribute('data-item-unit') || '') : '';
                unitDisplay.textContent = unitType !== ''
                    ? ('Unit Type: ' + unitType)
                    : 'Unit Type: Select ingredient first';
            }

            function initializePurchasingUnitType() {
                document.querySelectorAll('form[action="handlers.php"]').forEach(function (form) {
                    const inventorySelect = form.querySelector('select[name="inventory_item_id"]');
                    const unitDisplay = form.querySelector('[data-purchase-unit-display]');
                    if (!inventorySelect || !unitDisplay) {
                        return;
                    }

                    if (inventorySelect.dataset.unitTypeBound !== '1') {
                        inventorySelect.dataset.unitTypeBound = '1';
                        inventorySelect.addEventListener('change', function () {
                            syncPurchasingUnitType(form);
                        });
                    }

                    syncPurchasingUnitType(form);
                });
            }

            function initializeSingleSubmitForms() {
                document.querySelectorAll('form[data-disable-on-submit]').forEach(function (form) {
                    if (form.dataset.singleSubmitBound === '1') {
                        return;
                    }

                    form.dataset.singleSubmitBound = '1';
                    form.addEventListener('submit', function () {
                        const submitButton = form.querySelector('button[type="submit"]');
                        if (!submitButton || submitButton.disabled) {
                            return;
                        }

                        submitButton.disabled = true;
                        submitButton.classList.add('cursor-not-allowed', 'opacity-60');
                        submitButton.textContent = 'Submitted';
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                initializePurchasingUnitType();
                initializeSingleSubmitForms();
            });
            window.addEventListener('load', function () {
                initializePurchasingUnitType();
                initializeSingleSubmitForms();
            });
        })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
