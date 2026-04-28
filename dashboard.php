<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_login();

$user = current_user();
$pdo = db();

$range = (string) ($_GET['range'] ?? 'daily');
if (!in_array($range, ['daily', 'weekly', 'monthly'], true)) {
    $range = 'daily';
}

$gmDepts = ['purchasing', 'inventory', 'production', 'sales', 'accounting', 'crm', 'marketing'];
$gmDept  = (string) ($_GET['dept'] ?? 'purchasing');
if (!in_array($gmDept, $gmDepts, true)) {
    $gmDept = 'purchasing';
}

$rangeSqlMap = [
    'daily' => 'DATE(created_at) = CURDATE()',
    'weekly' => 'YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)',
    'monthly' => 'YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())',
];
$rangeLabelMap = [
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
];
$rangeWhere = $rangeSqlMap[$range];

$canAccessPurchasing = can_user_access_department($user ?? [], 'purchasing');
$canAccessInventory = can_user_access_department($user ?? [], 'inventory');
$canAccessProduction = can_user_access_department($user ?? [], 'production');
$canAccessSales = can_user_access_department($user ?? [], 'sales');
$canAccessAccounting = can_user_access_department($user ?? [], 'accounting');
$canAccessCrm = can_user_access_department($user ?? [], 'crm');
$canAccessMarketing = can_user_access_department($user ?? [], 'marketing');

$rangeWhereByAlias = static function (string $alias) use ($rangeWhere): string {
    return str_replace('created_at', $alias . '.created_at', $rangeWhere);
};

$renderRangeFilters = static function (string $currentRange, array $rangeLabelMap): void {
    echo '<div class="mt-3 flex flex-wrap gap-2">';
    foreach ($rangeLabelMap as $rangeKey => $rangeLabel) {
        $isActive = $rangeKey === $currentRange;
        $buttonClass = $isActive
            ? 'inline-flex items-center rounded-full border border-brand-700 bg-brand-700 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white'
            : 'inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-slate-600 hover:border-slate-300 hover:bg-white';
        echo '<a href="dashboard.php?range=' . e($rangeKey) . '" class="' . e($buttonClass) . '">' . e($rangeLabel) . '</a>';
    }
    echo '</div>';
};

$inventoryStatusSnapshot = [
    'total_items' => 0,
    'approved_items' => 0,
    'total_stock_qty' => 0.0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'healthy_stock' => 0,
];
$purchasingStats = [
    'total_requests' => 0,
    'approved_requests' => 0,
    'pending_requests' => 0,
    'rejected_requests' => 0,
    'total_requested_qty' => 0.0,
    'approved_estimated_total' => 0.0,
];
$purchasingIngredientRows = [];
$inventoryMonitoringRows = [];
$productionStats = [
    'total_logs' => 0,
    'approved_logs' => 0,
    'pending_logs' => 0,
    'rejected_logs' => 0,
    'approved_qty_prepared' => 0.0,
    'avg_approved_qty_prepared' => 0.0,
];
$productionTopBeverageRows = [];
$productionRecentRows = [];
$salesStats = [
    'total_orders' => 0,
    'total_qty' => 0.0,
    'total_revenue' => 0.0,
];
$highSalesCoffeeRows = [];
$lowSalesCoffeeRows = [];
$salesDashboardSnapshot = [
    'average_order_value' => 0.0,
    'unique_customers' => 0,
];
$salesPaymentRows = [];
$salesRecentRows = [];
$crmTrackedFlavorRows = [];
$crmHighTrackedFlavorRows = [];
$crmLowTrackedFlavorRows = [];
$crmCoverageSnapshot = [
    'total_sales_orders' => 0,
    'covered_sales_orders' => 0,
    'order_coverage_percent' => 0.0,
    'total_sales_customers' => 0,
    'covered_sales_customers' => 0,
    'customer_coverage_percent' => 0.0,
    'approved_crm_profiles' => 0,
];
$crmSummarySnapshot = [
    'approved_profiles' => 0,
    'active_profiles' => 0,
    'total_purchase_count' => 0,
    'total_spent' => 0.0,
    'avg_spent' => 0.0,
];
$salesTrendLeader = [
    'beverage_name' => '-',
    'beverage_qty' => 0.0,
    'beverage_revenue' => 0.0,
    'customer_name' => '-',
    'customer_qty' => 0.0,
    'customer_revenue' => 0.0,
];
$automatedPromotionFeed = [];

if ($canAccessPurchasing || $canAccessInventory) {
    $inventoryStatusRow = $pdo->query("SELECT
        COUNT(*) AS total_items,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_items,
        COALESCE(SUM(stock_qty), 0) AS total_stock_qty,
        COALESCE(SUM(CASE WHEN stock_qty <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
        COALESCE(SUM(CASE WHEN stock_qty > 0 AND stock_qty <= 10 THEN 1 ELSE 0 END), 0) AS low_stock,
        COALESCE(SUM(CASE WHEN stock_qty > 10 AND stock_qty <= 50 THEN 1 ELSE 0 END), 0) AS watch_stock,
        COALESCE(SUM(CASE WHEN stock_qty > 50 THEN 1 ELSE 0 END), 0) AS healthy_stock
    FROM inventory_items")->fetch() ?: [];

    $inventoryStatusSnapshot = [
        'total_items' => (int) ($inventoryStatusRow['total_items'] ?? 0),
        'approved_items' => (int) ($inventoryStatusRow['approved_items'] ?? 0),
        'total_stock_qty' => (float) ($inventoryStatusRow['total_stock_qty'] ?? 0),
        'low_stock' => (int) ($inventoryStatusRow['low_stock'] ?? 0),
        'watch_stock' => (int) ($inventoryStatusRow['watch_stock'] ?? 0),
        'out_of_stock' => (int) ($inventoryStatusRow['out_of_stock'] ?? 0),
        'healthy_stock' => (int) ($inventoryStatusRow['healthy_stock'] ?? 0),
    ];
}

if ($canAccessPurchasing) {
    $purchasingRangeWhere = $rangeWhereByAlias('pr');

    $purchasingStatsRow = $pdo->query("SELECT
        COUNT(*) AS total_requests,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_requests,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_requests,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_requests,
        COALESCE(SUM(requested_qty), 0) AS total_requested_qty,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN estimated_total ELSE 0 END), 0) AS approved_estimated_total
    FROM purchase_requests
    WHERE {$rangeWhere}")->fetch() ?: [];

    $purchasingStats = [
        'total_requests' => (int) ($purchasingStatsRow['total_requests'] ?? 0),
        'approved_requests' => (int) ($purchasingStatsRow['approved_requests'] ?? 0),
        'pending_requests' => (int) ($purchasingStatsRow['pending_requests'] ?? 0),
        'rejected_requests' => (int) ($purchasingStatsRow['rejected_requests'] ?? 0),
        'total_requested_qty' => (float) ($purchasingStatsRow['total_requested_qty'] ?? 0),
        'approved_estimated_total' => (float) ($purchasingStatsRow['approved_estimated_total'] ?? 0),
    ];

    $purchasingIngredientRows = $pdo->query("SELECT
        COALESCE(i.item_name, CONCAT('Item #', pr.inventory_item_id)) AS ingredient_name,
        COALESCE(SUM(pr.requested_qty), 0) AS total_requested_qty,
        COALESCE(SUM(CASE WHEN pr.status = 'approved' THEN pr.requested_qty ELSE 0 END), 0) AS approved_requested_qty,
        COALESCE(SUM(CASE WHEN pr.status = 'approved' THEN pr.estimated_total ELSE 0 END), 0) AS approved_estimated_total
    FROM purchase_requests pr
    LEFT JOIN inventory_items i ON i.id = pr.inventory_item_id
    WHERE {$purchasingRangeWhere}
    GROUP BY pr.inventory_item_id, ingredient_name
    ORDER BY total_requested_qty DESC, approved_estimated_total DESC
    LIMIT 8")->fetchAll() ?: [];
}

if ($canAccessInventory) {
    $inventoryMonitoringRows = $pdo->query("SELECT
        item_name,
        stock_qty,
        unit,
        reorder_level,
        (stock_qty - reorder_level) AS stock_gap,
        CASE
            WHEN stock_qty <= 0 THEN 'out'
            WHEN stock_qty <= 10 THEN 'low'
            WHEN stock_qty <= 50 THEN 'watch'
            ELSE 'healthy'
        END AS monitoring_status
    FROM inventory_items
    ORDER BY
        CASE
            WHEN stock_qty <= 0 THEN 0
            WHEN stock_qty <= 10 THEN 1
            WHEN stock_qty <= 50 THEN 2
            ELSE 3
        END,
        stock_qty ASC,
        item_name ASC
    LIMIT 12")->fetchAll() ?: [];
}

if ($canAccessProduction) {
    $productionRangeWhere = $rangeWhereByAlias('pl');

    $productionStatsRow = $pdo->query("SELECT
        COUNT(*) AS total_logs,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_logs,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_logs,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_logs,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN quantity_prepared ELSE 0 END), 0) AS approved_qty_prepared,
        COALESCE(AVG(CASE WHEN status = 'approved' THEN quantity_prepared ELSE NULL END), 0) AS avg_approved_qty_prepared
    FROM production_logs pl
    WHERE {$productionRangeWhere}")->fetch() ?: [];

    $productionStats = [
        'total_logs' => (int) ($productionStatsRow['total_logs'] ?? 0),
        'approved_logs' => (int) ($productionStatsRow['approved_logs'] ?? 0),
        'pending_logs' => (int) ($productionStatsRow['pending_logs'] ?? 0),
        'rejected_logs' => (int) ($productionStatsRow['rejected_logs'] ?? 0),
        'approved_qty_prepared' => (float) ($productionStatsRow['approved_qty_prepared'] ?? 0),
        'avg_approved_qty_prepared' => (float) ($productionStatsRow['avg_approved_qty_prepared'] ?? 0),
    ];

    $productionTopBeverageRows = $pdo->query("SELECT
        pl.beverage_name,
        COALESCE(SUM(pl.quantity_prepared), 0) AS total_qty_prepared,
        COUNT(*) AS total_logs
    FROM production_logs pl
    WHERE pl.status = 'approved' AND {$productionRangeWhere} AND TRIM(COALESCE(pl.beverage_name, '')) <> ''
    GROUP BY pl.beverage_name
    ORDER BY total_qty_prepared DESC, total_logs DESC
    LIMIT 8")->fetchAll() ?: [];

    $productionRecentRows = $pdo->query("SELECT
        pl.beverage_name,
        pl.quantity_prepared,
        pl.status,
        pl.updated_at
    FROM production_logs pl
    WHERE {$productionRangeWhere}
    ORDER BY pl.updated_at DESC, pl.id DESC
    LIMIT 8")->fetchAll() ?: [];
}

if ($canAccessAccounting || $canAccessCrm || $canAccessSales) {
    $salesRangeWhere = $rangeWhereByAlias('so');

    $salesStatsRow = $pdo->query("SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(quantity), 0) AS total_qty,
        COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM sales_orders so
    WHERE so.status = 'approved' AND {$salesRangeWhere}")->fetch() ?: [];

    $salesStats = [
        'total_orders' => (int) ($salesStatsRow['total_orders'] ?? 0),
        'total_qty' => (float) ($salesStatsRow['total_qty'] ?? 0),
        'total_revenue' => (float) ($salesStatsRow['total_revenue'] ?? 0),
    ];

    if ($salesStats['total_orders'] > 0) {
        $salesDashboardSnapshot['average_order_value'] = $salesStats['total_revenue'] / $salesStats['total_orders'];
    }

    if ($canAccessSales) {
        $uniqueCustomersRow = $pdo->query("SELECT
            COUNT(DISTINCT LOWER(TRIM(so.customer_name))) AS unique_customers
        FROM sales_orders so
        WHERE so.status = 'approved' AND {$salesRangeWhere} AND TRIM(COALESCE(so.customer_name, '')) <> ''")->fetch() ?: [];

        $salesDashboardSnapshot['unique_customers'] = (int) ($uniqueCustomersRow['unique_customers'] ?? 0);

        $salesPaymentRows = $pdo->query("SELECT
            so.payment_method,
            COUNT(*) AS total_orders,
            COALESCE(SUM(so.total_amount), 0) AS total_revenue
        FROM sales_orders so
        WHERE so.status = 'approved' AND {$salesRangeWhere}
        GROUP BY so.payment_method
        ORDER BY total_orders DESC, total_revenue DESC")->fetchAll() ?: [];

        $salesRecentRows = $pdo->query("SELECT
            so.order_code,
            so.customer_name,
            so.beverage_name,
            so.quantity,
            so.total_amount,
            so.payment_method,
            so.paid_at,
            so.created_at
        FROM sales_orders so
        WHERE so.status = 'approved' AND {$salesRangeWhere}
        ORDER BY COALESCE(so.paid_at, so.created_at) DESC
        LIMIT 8")->fetchAll() ?: [];
    }

    $highSalesCoffeeRows = $pdo->query("SELECT
        so.beverage_name,
        COALESCE(SUM(so.quantity), 0) AS total_qty,
        COALESCE(SUM(so.total_amount), 0) AS total_revenue,
        COUNT(*) AS total_orders
    FROM sales_orders so
    WHERE so.status = 'approved' AND {$salesRangeWhere} AND TRIM(COALESCE(so.beverage_name, '')) <> ''
    GROUP BY so.beverage_name
    ORDER BY total_qty DESC, total_revenue DESC
    LIMIT 5")->fetchAll() ?: [];

    $lowSalesCoffeeRows = $pdo->query("SELECT
        so.beverage_name,
        COALESCE(SUM(so.quantity), 0) AS total_qty,
        COALESCE(SUM(so.total_amount), 0) AS total_revenue,
        COUNT(*) AS total_orders
    FROM sales_orders so
    WHERE so.status = 'approved' AND {$salesRangeWhere} AND TRIM(COALESCE(so.beverage_name, '')) <> ''
    GROUP BY so.beverage_name
    ORDER BY total_qty ASC, total_revenue ASC
    LIMIT 5")->fetchAll() ?: [];

    if ($canAccessAccounting || $canAccessCrm) {
        $trackedFlavorNames = ['Caramel Syrup', 'Matcha coffee Syrup', 'Spanish latte Syrup', 'Hazelnuts Syrup', 'Vanilla Syrup'];
        $crmTrackedFlavorIndex = [];
        foreach ($trackedFlavorNames as $trackedFlavorName) {
            $crmTrackedFlavorIndex[$trackedFlavorName] = [
                'flavor_name' => $trackedFlavorName,
                'total_qty' => 0.0,
                'total_revenue' => 0.0,
                'total_orders' => 0,
            ];
        }

        $trackedFlavorPlaceholders = implode(', ', array_fill(0, count($trackedFlavorNames), '?'));
        $trackedFlavorStmt = $pdo->prepare("SELECT id, item_name FROM inventory_items WHERE item_name IN ({$trackedFlavorPlaceholders})");
        $trackedFlavorStmt->execute($trackedFlavorNames);
        $trackedFlavorInventoryRows = $trackedFlavorStmt->fetchAll() ?: [];

        $trackedFlavorIdMap = [];
        foreach ($trackedFlavorInventoryRows as $trackedFlavorInventoryRow) {
            $trackedFlavorId = (int) ($trackedFlavorInventoryRow['id'] ?? 0);
            $trackedFlavorName = (string) ($trackedFlavorInventoryRow['item_name'] ?? '');
            if ($trackedFlavorId > 0 && isset($crmTrackedFlavorIndex[$trackedFlavorName])) {
                $trackedFlavorIdMap[$trackedFlavorId] = $trackedFlavorName;
            }
        }

        $trackedFlavorSalesRows = $pdo->query("SELECT
            so.inventory_item_id,
            so.ingredient_item_ids,
            so.quantity,
            so.total_amount
        FROM sales_orders so
        WHERE so.status = 'approved' AND {$salesRangeWhere}")->fetchAll() ?: [];

        foreach ($trackedFlavorSalesRows as $trackedFlavorSalesRow) {
            $trackedIngredientIds = inventory_item_ids_from_record($trackedFlavorSalesRow);
            $selectedTrackedFlavorNames = [];

            foreach ($trackedIngredientIds as $trackedIngredientId) {
                if (!isset($trackedFlavorIdMap[$trackedIngredientId])) {
                    continue;
                }

                $selectedTrackedFlavorName = $trackedFlavorIdMap[$trackedIngredientId];
                $selectedTrackedFlavorNames[$selectedTrackedFlavorName] = $selectedTrackedFlavorName;
            }

            if ($selectedTrackedFlavorNames === []) {
                continue;
            }

            $allocationDivisor = count($selectedTrackedFlavorNames);
            $allocatedQty = ((float) ($trackedFlavorSalesRow['quantity'] ?? 0)) / $allocationDivisor;
            $allocatedRevenue = ((float) ($trackedFlavorSalesRow['total_amount'] ?? 0)) / $allocationDivisor;

            foreach ($selectedTrackedFlavorNames as $selectedTrackedFlavorName) {
                $crmTrackedFlavorIndex[$selectedTrackedFlavorName]['total_qty'] += $allocatedQty;
                $crmTrackedFlavorIndex[$selectedTrackedFlavorName]['total_revenue'] += $allocatedRevenue;
                $crmTrackedFlavorIndex[$selectedTrackedFlavorName]['total_orders'] += 1;
            }
        }

        $crmTrackedFlavorRows = array_values($crmTrackedFlavorIndex);

        $crmHighTrackedFlavorRows = $crmTrackedFlavorRows;
        usort($crmHighTrackedFlavorRows, static function (array $left, array $right): int {
            $qtyComparison = ((float) ($right['total_qty'] ?? 0)) <=> ((float) ($left['total_qty'] ?? 0));
            if ($qtyComparison !== 0) {
                return $qtyComparison;
            }

            return ((float) ($right['total_revenue'] ?? 0)) <=> ((float) ($left['total_revenue'] ?? 0));
        });

        $crmLowTrackedFlavorRows = $crmTrackedFlavorRows;
        usort($crmLowTrackedFlavorRows, static function (array $left, array $right): int {
            $qtyComparison = ((float) ($left['total_qty'] ?? 0)) <=> ((float) ($right['total_qty'] ?? 0));
            if ($qtyComparison !== 0) {
                return $qtyComparison;
            }

            return ((float) ($left['total_revenue'] ?? 0)) <=> ((float) ($right['total_revenue'] ?? 0));
        });
    }
}

if ($canAccessCrm || $canAccessMarketing) {
    $salesCoverageWhere = $rangeWhereByAlias('so');

    $crmCoverageRow = $pdo->query("SELECT
        COALESCE((SELECT COUNT(*)
            FROM sales_orders so
            WHERE so.status = 'approved' AND {$salesCoverageWhere}
        ), 0) AS total_sales_orders,
        COALESCE((SELECT COUNT(*)
            FROM sales_orders so
            WHERE so.status = 'approved'
                AND {$salesCoverageWhere}
                AND EXISTS (
                    SELECT 1
                    FROM crm_profiles cp
                    WHERE cp.status = 'approved'
                        AND LOWER(TRIM(cp.customer_name)) = LOWER(TRIM(so.customer_name))
                )
        ), 0) AS covered_sales_orders,
        COALESCE((SELECT COUNT(DISTINCT LOWER(TRIM(so.customer_name)))
            FROM sales_orders so
            WHERE so.status = 'approved'
                AND {$salesCoverageWhere}
                AND TRIM(COALESCE(so.customer_name, '')) <> ''
        ), 0) AS total_sales_customers,
        COALESCE((SELECT COUNT(DISTINCT LOWER(TRIM(so.customer_name)))
            FROM sales_orders so
            WHERE so.status = 'approved'
                AND {$salesCoverageWhere}
                AND TRIM(COALESCE(so.customer_name, '')) <> ''
                AND EXISTS (
                    SELECT 1
                    FROM crm_profiles cp
                    WHERE cp.status = 'approved'
                        AND LOWER(TRIM(cp.customer_name)) = LOWER(TRIM(so.customer_name))
                )
        ), 0) AS covered_sales_customers,
        COALESCE((SELECT COUNT(*) FROM crm_profiles cp WHERE cp.status = 'approved'), 0) AS approved_crm_profiles")->fetch() ?: [];

    $crmCoverageSnapshot = [
        'total_sales_orders' => (int) ($crmCoverageRow['total_sales_orders'] ?? 0),
        'covered_sales_orders' => (int) ($crmCoverageRow['covered_sales_orders'] ?? 0),
        'order_coverage_percent' => 0.0,
        'total_sales_customers' => (int) ($crmCoverageRow['total_sales_customers'] ?? 0),
        'covered_sales_customers' => (int) ($crmCoverageRow['covered_sales_customers'] ?? 0),
        'customer_coverage_percent' => 0.0,
        'approved_crm_profiles' => (int) ($crmCoverageRow['approved_crm_profiles'] ?? 0),
    ];

    if ($crmCoverageSnapshot['total_sales_orders'] > 0) {
        $crmCoverageSnapshot['order_coverage_percent'] = ($crmCoverageSnapshot['covered_sales_orders'] / $crmCoverageSnapshot['total_sales_orders']) * 100;
    }

    if ($crmCoverageSnapshot['total_sales_customers'] > 0) {
        $crmCoverageSnapshot['customer_coverage_percent'] = ($crmCoverageSnapshot['covered_sales_customers'] / $crmCoverageSnapshot['total_sales_customers']) * 100;
    }
}

if ($canAccessCrm) {
    $crmSummaryRow = $pdo->query("SELECT
        COUNT(*) AS approved_profiles,
        COALESCE(SUM(CASE WHEN purchase_count > 0 THEN 1 ELSE 0 END), 0) AS active_profiles,
        COALESCE(SUM(purchase_count), 0) AS total_purchase_count,
        COALESCE(SUM(total_spent), 0) AS total_spent
    FROM crm_profiles
    WHERE status = 'approved'")->fetch() ?: [];

    $crmSummarySnapshot = [
        'approved_profiles' => (int) ($crmSummaryRow['approved_profiles'] ?? 0),
        'active_profiles' => (int) ($crmSummaryRow['active_profiles'] ?? 0),
        'total_purchase_count' => (int) ($crmSummaryRow['total_purchase_count'] ?? 0),
        'total_spent' => (float) ($crmSummaryRow['total_spent'] ?? 0),
        'avg_spent' => 0.0,
    ];

    if ($crmSummarySnapshot['approved_profiles'] > 0) {
        $crmSummarySnapshot['avg_spent'] = $crmSummarySnapshot['total_spent'] / $crmSummarySnapshot['approved_profiles'];
    }

    if ($highSalesCoffeeRows !== []) {
        $salesTrendLeader['beverage_name'] = (string) ($highSalesCoffeeRows[0]['beverage_name'] ?? '-');
        $salesTrendLeader['beverage_qty'] = (float) ($highSalesCoffeeRows[0]['total_qty'] ?? 0);
        $salesTrendLeader['beverage_revenue'] = (float) ($highSalesCoffeeRows[0]['total_revenue'] ?? 0);
    }

    $salesRangeWhere = $rangeWhereByAlias('so');
    $customerLeaderRow = $pdo->query("SELECT
        so.customer_name,
        COALESCE(SUM(so.quantity), 0) AS total_qty,
        COALESCE(SUM(so.total_amount), 0) AS total_revenue
    FROM sales_orders so
    WHERE so.status = 'approved' AND {$salesRangeWhere} AND TRIM(COALESCE(so.customer_name, '')) <> ''
    GROUP BY so.customer_name
    ORDER BY total_revenue DESC, total_qty DESC
    LIMIT 1")->fetch() ?: [];

    if ($customerLeaderRow !== []) {
        $salesTrendLeader['customer_name'] = (string) ($customerLeaderRow['customer_name'] ?? '-');
        $salesTrendLeader['customer_qty'] = (float) ($customerLeaderRow['total_qty'] ?? 0);
        $salesTrendLeader['customer_revenue'] = (float) ($customerLeaderRow['total_revenue'] ?? 0);
    }

    if ($crmCoverageSnapshot['order_coverage_percent'] < 70) {
        $automatedPromotionFeed[] = 'Increase customer profile capture at checkout to improve CRM sales-order coverage.';
    }

    if ($crmCoverageSnapshot['customer_coverage_percent'] < 70) {
        $automatedPromotionFeed[] = 'Run digital signup incentives to expand CRM coverage for unique buyers.';
    }

    foreach (array_slice($lowSalesCoffeeRows, 0, 3) as $lowSalesCoffeeRow) {
        $flavorName = trim((string) ($lowSalesCoffeeRow['beverage_name'] ?? ''));
        if ($flavorName !== '') {
            $automatedPromotionFeed[] = 'Launch a targeted promo for ' . $flavorName . ' to improve low-sales coffee movement.';
        }
    }

    if ($highSalesCoffeeRows !== []) {
        $leaderFlavorName = trim((string) ($highSalesCoffeeRows[0]['beverage_name'] ?? ''));
        if ($leaderFlavorName !== '') {
            $automatedPromotionFeed[] = 'Bundle high-demand ' . $leaderFlavorName . ' with a low-sales flavor for cross-promotion.';
        }
    }

    if ($automatedPromotionFeed === []) {
        $automatedPromotionFeed[] = 'CRM coverage and flavor sales are stable. Keep scheduled promotion rotations active.';
    }
}

$pageTitle = 'Department Dashboards';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/layout_top.php';
?>

<?php $isGeneralManager = ($user['role'] ?? '') === ROLE_GENERAL_MANAGER; ?>

<?php if ($isGeneralManager): ?>
<?php
$gmDeptMeta = [
    'purchasing' => ['label' => 'Purchasing', 'activeClass' => 'border-slate-700  bg-slate-700  text-white',           'inactiveClass' => 'border-slate-300  bg-slate-50 text-slate-700  hover:border-slate-500 hover:bg-slate-100 hover:text-slate-900 hover:-translate-y-0.5 hover:shadow-sm'],
    'inventory'  => ['label' => 'Inventory',  'activeClass' => 'border-brand-700  bg-brand-700  text-white',           'inactiveClass' => 'border-brand-300  bg-brand-50 text-brand-700  hover:border-brand-500 hover:bg-brand-100 hover:text-brand-900 hover:-translate-y-0.5 hover:shadow-sm'],
    'production' => ['label' => 'Production', 'activeClass' => 'border-violet-700 bg-violet-700 text-white',           'inactiveClass' => 'border-violet-300 bg-violet-50 text-violet-700 hover:border-violet-500 hover:bg-violet-100 hover:text-violet-900 hover:-translate-y-0.5 hover:shadow-sm'],
    'sales'      => ['label' => 'Sales',      'activeClass' => 'border-emerald-700 bg-emerald-700 text-white',         'inactiveClass' => 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:border-emerald-500 hover:bg-emerald-100 hover:text-emerald-900 hover:-translate-y-0.5 hover:shadow-sm'],
    'accounting' => ['label' => 'Accounting', 'activeClass' => 'border-amber-700  bg-amber-700  text-white',           'inactiveClass' => 'border-amber-300  bg-amber-50 text-amber-700  hover:border-amber-500 hover:bg-amber-100 hover:text-amber-900 hover:-translate-y-0.5 hover:shadow-sm'],
    'crm'        => ['label' => 'CRM',        'activeClass' => 'border-sky-700    bg-sky-700    text-white',           'inactiveClass' => 'border-sky-300    bg-sky-50 text-sky-700    hover:border-sky-500 hover:bg-sky-100 hover:text-sky-900 hover:-translate-y-0.5 hover:shadow-sm'],
    'marketing'  => ['label' => 'Marketing',  'activeClass' => 'border-rose-700   bg-rose-700   text-white',           'inactiveClass' => 'border-rose-300   bg-rose-50 text-rose-700   hover:border-rose-500 hover:bg-rose-100 hover:text-rose-900 hover:-translate-y-0.5 hover:shadow-sm'],
];
?>
<section class="mt-8 rounded-2xl border border-brand-200 bg-brand-50 p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-extrabold text-brand-900">General Manager — Department Filter</h3>
            <p class="text-xs text-brand-700">Select a department and time range to view its dashboard.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($rangeLabelMap as $rangeKey => $rangeLabel): ?>
                <?php $isRangeActive = $rangeKey === $range; ?>
                <a href="dashboard.php?dept=<?= e($gmDept) ?>&range=<?= e($rangeKey) ?>"
                   class="<?= $isRangeActive
                       ? 'inline-flex items-center rounded-full border border-brand-700 bg-brand-700 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white transition'
                       : 'inline-flex items-center rounded-full border border-brand-300 bg-brand-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-brand-700 transition hover:border-brand-500 hover:bg-brand-100 hover:text-brand-900 hover:-translate-y-0.5 hover:shadow-sm' ?>">
                    <?= e($rangeLabel) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        <?php foreach ($gmDeptMeta as $dKey => $dMeta): ?>
            <?php $isDeptActive = $dKey === $gmDept; ?>
                <a href="dashboard.php?dept=<?= e($dKey) ?>&range=<?= e($range) ?>"
                    class="inline-flex items-center rounded-full border px-4 py-1.5 text-xs font-bold uppercase tracking-wide transition <?= e($isDeptActive ? $dMeta['activeClass'] : $dMeta['inactiveClass']) ?>">
                <?= e($dMeta['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($canAccessPurchasing && (!$isGeneralManager || $gmDept === 'purchasing')): ?>
<section id="dept-purchasing" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
        <h3 class="text-lg font-extrabold text-slate-900">Purchasing Department Dashboard</h3>
        <p class="text-sm text-slate-600">Statistical analytics of purchasing ingredients and inventory status.</p>
        <?php if (!$isGeneralManager): ?>
            <?php $renderRangeFilters($range, $rangeLabelMap); ?>
        <?php endif; ?>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total Requests</p>
            <p class="mt-1 text-xl font-black text-slate-900"><?= e((string) $purchasingStats['total_requests']) ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Approved Requests</p>
            <p class="mt-1 text-xl font-black text-emerald-700"><?= e((string) $purchasingStats['approved_requests']) ?></p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
            <p class="text-xs uppercase tracking-wide text-amber-700">Pending Requests</p>
            <p class="mt-1 text-xl font-black text-amber-700"><?= e((string) $purchasingStats['pending_requests']) ?></p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
            <p class="text-xs uppercase tracking-wide text-rose-700">Rejected Requests</p>
            <p class="mt-1 text-xl font-black text-rose-700"><?= e((string) $purchasingStats['rejected_requests']) ?></p>
        </div>
        <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
            <p class="text-xs uppercase tracking-wide text-brand-700">Total Requested Qty</p>
            <p class="mt-1 text-xl font-black text-brand-700"><?= e(number_format($purchasingStats['total_requested_qty'], 2)) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-100 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-600">Approved Total Cost</p>
            <p class="mt-1 text-xl font-black text-slate-900"><?= e(format_money($purchasingStats['approved_estimated_total'])) ?></p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Purchase Request Status</h4>
            <div class="flex items-center justify-center mt-3" style="height:220px">
                <canvas id="chartPurchasingStatus"></canvas>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Top Purchasing Ingredients</h4>
            <div class="mt-3" style="height:220px">
                <canvas id="chartPurchasingIngredients"></canvas>
            </div>
        </article>
    </div>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Top Purchasing Ingredients</h4>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[520px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-4" data-priority="high">Ingredient</th>
                    <th class="pb-2 pr-4" data-priority="high">Requested Qty</th>
                    <th class="pb-2 pr-4" data-priority="medium">Approved Qty</th>
                    <th class="pb-2" data-priority="medium">Approved Spend</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($purchasingIngredientRows): ?>
                    <?php foreach ($purchasingIngredientRows as $ingredientRow): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e((string) ($ingredientRow['ingredient_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) ($ingredientRow['total_requested_qty'] ?? 0), 2)) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) ($ingredientRow['approved_requested_qty'] ?? 0), 2)) ?></td>
                            <td class="py-2"><?= e(format_money((float) ($ingredientRow['approved_estimated_total'] ?? 0))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-3 text-slate-500">No purchasing ingredient analytics available for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Inventory Status</h4>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Items</p>
                <p class="mt-1 text-xl font-black text-slate-900"><?= e((string) $inventoryStatusSnapshot['total_items']) ?></p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Safe (&gt;50)</p>
                <p class="mt-1 text-xl font-black text-emerald-700"><?= e((string) $inventoryStatusSnapshot['healthy_stock']) ?></p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                <p class="text-xs uppercase tracking-wide text-amber-700">Watch (11–50)</p>
                <p class="mt-1 text-xl font-black text-amber-700"><?= e((string) $inventoryStatusSnapshot['watch_stock']) ?></p>
            </div>
            <div class="rounded-xl border border-orange-200 bg-orange-50 p-3">
                <p class="text-xs uppercase tracking-wide text-orange-700">Low Stock (≤10)</p>
                <p class="mt-1 text-xl font-black text-orange-700"><?= e((string) $inventoryStatusSnapshot['low_stock']) ?></p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                <p class="text-xs uppercase tracking-wide text-rose-700">Out of Stock</p>
                <p class="mt-1 text-xl font-black text-rose-700"><?= e((string) $inventoryStatusSnapshot['out_of_stock']) ?></p>
            </div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
                <p class="text-xs uppercase tracking-wide text-brand-700">Total Stock Qty</p>
                <p class="mt-1 text-xl font-black text-brand-700"><?= e(number_format($inventoryStatusSnapshot['total_stock_qty'], 2)) ?></p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($canAccessInventory && (!$isGeneralManager || $gmDept === 'inventory')): ?>
<section id="dept-inventory" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
        <h3 class="text-lg font-extrabold text-slate-900">Inventory Department Dashboard</h3>
        <p class="text-sm text-slate-600">Inventory status and inventory monitoring.</p>
        <?php if (!$isGeneralManager): ?>
            <?php $renderRangeFilters($range, $rangeLabelMap); ?>
        <?php endif; ?>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total Items</p>
            <p class="mt-1 text-xl font-black text-slate-900"><?= e((string) $inventoryStatusSnapshot['total_items']) ?></p>
        </div>
        <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
            <p class="text-xs uppercase tracking-wide text-brand-700">Approved Items</p>
            <p class="mt-1 text-xl font-black text-brand-700"><?= e((string) $inventoryStatusSnapshot['approved_items']) ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Safe (&gt;50)</p>
            <p class="mt-1 text-xl font-black text-emerald-700"><?= e((string) $inventoryStatusSnapshot['healthy_stock']) ?></p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
            <p class="text-xs uppercase tracking-wide text-amber-700">Watch (11–50)</p>
            <p class="mt-1 text-xl font-black text-amber-700"><?= e((string) $inventoryStatusSnapshot['watch_stock']) ?></p>
        </div>
        <div class="rounded-xl border border-orange-200 bg-orange-50 p-3">
            <p class="text-xs uppercase tracking-wide text-orange-700">Low Stock (≤10)</p>
            <p class="mt-1 text-xl font-black text-orange-700"><?= e((string) $inventoryStatusSnapshot['low_stock']) ?></p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
            <p class="text-xs uppercase tracking-wide text-rose-700">Out of Stock</p>
            <p class="mt-1 text-xl font-black text-rose-700"><?= e((string) $inventoryStatusSnapshot['out_of_stock']) ?></p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Stock Health Distribution</h4>
            <div class="flex items-center justify-center mt-3" style="height:220px">
                <canvas id="chartInventoryHealth"></canvas>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Stock Qty vs Reorder Level (Top Items)</h4>
            <div class="mt-3" style="height:220px">
                <canvas id="chartInventoryMonitoring"></canvas>
            </div>
        </article>
    </div>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Inventory Monitoring</h4>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[680px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-4" data-priority="high">Item</th>
                    <th class="pb-2 pr-4" data-priority="high">Stock Qty</th>
                    <th class="pb-2 pr-4" data-priority="medium">Reorder Level</th>
                    <th class="pb-2 pr-4" data-priority="medium">Stock Gap</th>
                    <th class="pb-2" data-priority="high">Monitoring Status</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($inventoryMonitoringRows): ?>
                    <?php foreach ($inventoryMonitoringRows as $monitoringRow): ?>
                        <?php
                        $monitoringStatus = (string) ($monitoringRow['monitoring_status'] ?? 'watch');
                        $monitoringClass = 'bg-amber-100 text-amber-700';
                        $monitoringLabel = 'Watch (11–50)';
                        if ($monitoringStatus === 'out') {
                            $monitoringClass = 'bg-rose-100 text-rose-700';
                            $monitoringLabel = 'Out of Stock';
                        } elseif ($monitoringStatus === 'low') {
                            $monitoringClass = 'bg-orange-100 text-orange-700';
                            $monitoringLabel = 'Low Stock (≤10)';
                        } elseif ($monitoringStatus === 'healthy') {
                            $monitoringClass = 'bg-emerald-100 text-emerald-700';
                            $monitoringLabel = 'Safe (>50)';
                        }
                        ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e((string) ($monitoringRow['item_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) ($monitoringRow['stock_qty'] ?? 0), 2)) ?> <?= e((string) ($monitoringRow['unit'] ?? '')) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) ($monitoringRow['reorder_level'] ?? 0), 2)) ?></td>
                            <td class="py-2 pr-4 font-semibold <?= (float) ($monitoringRow['stock_gap'] ?? 0) < 0 ? 'text-rose-700' : 'text-slate-700' ?>"><?= e(number_format((float) ($monitoringRow['stock_gap'] ?? 0), 2)) ?></td>
                            <td class="py-2"><span class="rounded-full px-2 py-1 text-xs font-bold <?= e($monitoringClass) ?>"><?= e($monitoringLabel) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="py-3 text-slate-500">No inventory monitoring records available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php endif; ?>

<?php if ($canAccessProduction && (!$isGeneralManager || $gmDept === 'production')): ?>
<section id="dept-production" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
        <h3 class="text-lg font-extrabold text-slate-900">Production Department Dashboard</h3>
        <p class="text-sm text-slate-600">Production logs, prepared quantity, and beverage output monitoring.</p>
        <?php if (!$isGeneralManager): ?>
            <?php $renderRangeFilters($range, $rangeLabelMap); ?>
        <?php endif; ?>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total Logs</p>
            <p class="mt-1 text-xl font-black text-slate-900"><?= e((string) $productionStats['total_logs']) ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Approved Logs</p>
            <p class="mt-1 text-xl font-black text-emerald-700"><?= e((string) $productionStats['approved_logs']) ?></p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
            <p class="text-xs uppercase tracking-wide text-amber-700">Pending Logs</p>
            <p class="mt-1 text-xl font-black text-amber-700"><?= e((string) $productionStats['pending_logs']) ?></p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
            <p class="text-xs uppercase tracking-wide text-rose-700">Rejected Logs</p>
            <p class="mt-1 text-xl font-black text-rose-700"><?= e((string) $productionStats['rejected_logs']) ?></p>
        </div>
        <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
            <p class="text-xs uppercase tracking-wide text-brand-700">Approved Qty Prepared</p>
            <p class="mt-1 text-xl font-black text-brand-700"><?= e(number_format((float) $productionStats['approved_qty_prepared'], 0)) ?></p>
        </div>
        <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
            <p class="text-xs uppercase tracking-wide text-violet-700">Avg Approved Qty</p>
            <p class="mt-1 text-xl font-black text-violet-700"><?= e(number_format((float) $productionStats['avg_approved_qty_prepared'], 2)) ?></p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Production Log Status</h4>
            <div class="flex items-center justify-center mt-3" style="height:220px">
                <canvas id="chartProductionStatus"></canvas>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Top Beverages by Qty Prepared</h4>
            <div class="mt-3" style="height:220px">
                <canvas id="chartProductionBeverages"></canvas>
            </div>
        </article>
    </div>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Top Beverages by Qty Prepared</h4>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[520px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-4" data-priority="high">Beverage</th>
                    <th class="pb-2 pr-4" data-priority="high">Qty Prepared</th>
                    <th class="pb-2" data-priority="medium">Logs</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($productionTopBeverageRows): ?>
                    <?php foreach ($productionTopBeverageRows as $productionBeverageRow): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e((string) ($productionBeverageRow['beverage_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) ($productionBeverageRow['total_qty_prepared'] ?? 0), 0)) ?></td>
                            <td class="py-2"><?= e((string) ((int) ($productionBeverageRow['total_logs'] ?? 0))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="py-3 text-slate-500">No approved production beverage data for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Recent Production Logs</h4>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[460px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-4" data-priority="high">Beverage</th>
                    <th class="pb-2 pr-4" data-priority="high">Qty</th>
                    <th class="pb-2 pr-4" data-priority="medium">Status</th>
                    <th class="pb-2" data-priority="low">Updated</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($productionRecentRows): ?>
                    <?php foreach ($productionRecentRows as $productionRecentRow): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e((string) ($productionRecentRow['beverage_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) ($productionRecentRow['quantity_prepared'] ?? 0), 0)) ?></td>
                            <td class="py-2 pr-4"><?= e(ucfirst((string) ($productionRecentRow['status'] ?? '-'))) ?></td>
                            <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('updated_at', $productionRecentRow['updated_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-3 text-slate-500">No production logs for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php endif; ?>

<?php if ($canAccessSales && (!$isGeneralManager || $gmDept === 'sales')): ?>
<section id="dept-sales" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
        <h3 class="text-lg font-extrabold text-slate-900">Sales Department Dashboard</h3>
        <p class="text-sm text-slate-600">Approved POS performance, payment distribution, and recent sales activity.</p>
        <?php if (!$isGeneralManager): ?>
            <?php $renderRangeFilters($range, $rangeLabelMap); ?>
        <?php endif; ?>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Approved Orders</p>
            <p class="mt-1 text-xl font-black text-slate-900"><?= e((string) $salesStats['total_orders']) ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Total Cups Sold</p>
            <p class="mt-1 text-xl font-black text-emerald-700"><?= e(number_format($salesStats['total_qty'], 0)) ?></p>
        </div>
        <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
            <p class="text-xs uppercase tracking-wide text-brand-700">Sales Revenue</p>
            <p class="mt-1 text-xl font-black text-brand-700"><?= e(format_money($salesStats['total_revenue'])) ?></p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
            <p class="text-xs uppercase tracking-wide text-amber-700">Average Order Value</p>
            <p class="mt-1 text-xl font-black text-amber-700"><?= e(format_money($salesDashboardSnapshot['average_order_value'])) ?></p>
        </div>
        <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
            <p class="text-xs uppercase tracking-wide text-violet-700">Unique Customers</p>
            <p class="mt-1 text-xl font-black text-violet-700"><?= e((string) $salesDashboardSnapshot['unique_customers']) ?></p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Revenue by Payment Method</h4>
            <div class="flex items-center justify-center mt-3" style="height:220px">
                <canvas id="chartSalesPayment"></canvas>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Top Beverages by Sales Qty</h4>
            <div class="mt-3" style="height:220px">
                <canvas id="chartSalesBeverages"></canvas>
            </div>
        </article>
    </div>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Top Beverages by Sales Qty</h4>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[520px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-4" data-priority="high">Beverage</th>
                    <th class="pb-2 pr-4" data-priority="high">Qty Sold</th>
                    <th class="pb-2 pr-4" data-priority="medium">Orders</th>
                    <th class="pb-2" data-priority="medium">Revenue</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($highSalesCoffeeRows): ?>
                    <?php foreach ($highSalesCoffeeRows as $salesBeverageRow): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e((string) ($salesBeverageRow['beverage_name'] ?? '-')) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) ($salesBeverageRow['total_qty'] ?? 0), 0)) ?></td>
                            <td class="py-2 pr-4"><?= e((string) ((int) ($salesBeverageRow['total_orders'] ?? 0))) ?></td>
                            <td class="py-2"><?= e(format_money((float) ($salesBeverageRow['total_revenue'] ?? 0))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-3 text-slate-500">No approved sales beverage data for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Payment Method Mix</h4>
            <div class="table-scroll mt-3">
                <table class="stack-table w-full min-w-[420px] text-sm">
                    <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2 pr-4" data-priority="high">Payment Method</th>
                        <th class="pb-2 pr-4" data-priority="high">Orders</th>
                        <th class="pb-2 pr-4" data-priority="medium">Revenue</th>
                        <th class="pb-2" data-priority="medium">Revenue Share</th>
                    </tr>
                    </thead>
                    <tbody class="text-slate-700">
                    <?php if ($salesPaymentRows): ?>
                        <?php foreach ($salesPaymentRows as $salesPaymentRow): ?>
                            <?php
                            $paymentRevenue = (float) ($salesPaymentRow['total_revenue'] ?? 0);
                            $revenueShare = $salesStats['total_revenue'] > 0
                                ? ($paymentRevenue / $salesStats['total_revenue']) * 100
                                : 0.0;
                            ?>
                            <tr class="border-t border-slate-100">
                                <td class="py-2 pr-4 font-semibold"><?= e(ucfirst((string) ($salesPaymentRow['payment_method'] ?? '-'))) ?></td>
                                <td class="py-2 pr-4"><?= e((string) ((int) ($salesPaymentRow['total_orders'] ?? 0))) ?></td>
                                <td class="py-2 pr-4"><?= e(format_money($paymentRevenue)) ?></td>
                                <td class="py-2"><?= e(number_format($revenueShare, 2)) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="py-3 text-slate-500">No approved payment records for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Recent Approved Orders</h4>
            <div class="table-scroll mt-3">
                <table class="stack-table w-full min-w-[520px] text-sm">
                    <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2 pr-4" data-priority="high">Order</th>
                        <th class="pb-2 pr-4" data-priority="high">Customer</th>
                        <th class="pb-2 pr-4" data-priority="medium">Beverage</th>
                        <th class="pb-2 pr-4" data-priority="high">Qty</th>
                        <th class="pb-2 pr-4" data-priority="high">Total</th>
                        <th class="pb-2" data-priority="low">Paid At</th>
                    </tr>
                    </thead>
                    <tbody class="text-slate-700">
                    <?php if ($salesRecentRows): ?>
                        <?php foreach ($salesRecentRows as $salesRecentRow): ?>
                            <tr class="border-t border-slate-100">
                                <td class="py-2 pr-4 font-semibold"><?= e((string) ($salesRecentRow['order_code'] ?? '-')) ?></td>
                                <td class="py-2 pr-4"><?= e((string) ($salesRecentRow['customer_name'] ?? '-')) ?></td>
                                <td class="py-2 pr-4"><?= e((string) ($salesRecentRow['beverage_name'] ?? '-')) ?></td>
                                <td class="py-2 pr-4"><?= e(number_format((float) ($salesRecentRow['quantity'] ?? 0), 0)) ?></td>
                                <td class="py-2 pr-4"><?= e(format_money((float) ($salesRecentRow['total_amount'] ?? 0))) ?></td>
                                <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('paid_at', $salesRecentRow['paid_at'] ?? ($salesRecentRow['created_at'] ?? null))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-3 text-slate-500">No approved sales orders for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
<?php endif; ?>

<?php if ($canAccessAccounting && (!$isGeneralManager || $gmDept === 'accounting')): ?>
<section id="dept-accounting" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
        <h3 class="text-lg font-extrabold text-slate-900">Accounting Department Dashboard</h3>
        <p class="text-sm text-slate-600">Sales summary with tracked syrup flavor analytics (Caramel Syrup, Matcha coffee Syrup, Spanish latte Syrup, Hazelnuts Syrup, Vanilla Syrup).</p>
        <?php if (!$isGeneralManager): ?>
            <?php $renderRangeFilters($range, $rangeLabelMap); ?>
        <?php endif; ?>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Approved Sales Orders</p>
            <p class="mt-1 text-xl font-black text-slate-900"><?= e((string) $salesStats['total_orders']) ?></p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Total Cups Sold</p>
            <p class="mt-1 text-xl font-black text-emerald-700"><?= e(number_format($salesStats['total_qty'], 0)) ?></p>
        </div>
        <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
            <p class="text-xs uppercase tracking-wide text-brand-700">Sales Revenue</p>
            <p class="mt-1 text-xl font-black text-brand-700"><?= e(format_money($salesStats['total_revenue'])) ?></p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2 mb-4">
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-emerald-700">Tracked Syrup Flavor — Qty Sold</h4>
            <div class="mt-3" style="height:220px">
                <canvas id="chartAccountingFlavorQty"></canvas>
            </div>
        </article>

        <article class="rounded-2xl border border-brand-200 bg-brand-50 p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-brand-700">Tracked Syrup Flavor — Revenue</h4>
            <div class="mt-3" style="height:220px">
                <canvas id="chartAccountingFlavorRevenue"></canvas>
            </div>
        </article>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-emerald-700">High Sales Coffee</h4>
            <div class="table-scroll mt-3">
                <table class="stack-table w-full min-w-[340px] text-sm">
                    <thead>
                    <tr class="text-left text-emerald-700">
                        <th class="pb-2 pr-4" data-priority="high">Coffee Flavor</th>
                        <th class="pb-2 pr-4" data-priority="high">Qty Sold</th>
                        <th class="pb-2" data-priority="medium">Revenue</th>
                    </tr>
                    </thead>
                    <tbody class="text-slate-700">
                    <?php if ($crmHighTrackedFlavorRows): ?>
                        <?php foreach ($crmHighTrackedFlavorRows as $coffeeRow): ?>
                            <tr class="border-t border-emerald-100">
                                <td class="py-2 pr-4 font-semibold"><?= e((string) ($coffeeRow['flavor_name'] ?? '-')) ?></td>
                                <td class="py-2 pr-4"><?= e(number_format((float) ($coffeeRow['total_qty'] ?? 0), 2)) ?></td>
                                <td class="py-2"><?= e(format_money((float) ($coffeeRow['total_revenue'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="py-3 text-emerald-700">No tracked syrup flavor sales data for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-rose-700">Low Sales Coffee</h4>
            <div class="table-scroll mt-3">
                <table class="stack-table w-full min-w-[340px] text-sm">
                    <thead>
                    <tr class="text-left text-rose-700">
                        <th class="pb-2 pr-4" data-priority="high">Coffee Flavor</th>
                        <th class="pb-2 pr-4" data-priority="high">Qty Sold</th>
                        <th class="pb-2" data-priority="medium">Revenue</th>
                    </tr>
                    </thead>
                    <tbody class="text-slate-700">
                    <?php if ($crmLowTrackedFlavorRows): ?>
                        <?php foreach ($crmLowTrackedFlavorRows as $coffeeRow): ?>
                            <tr class="border-t border-rose-100">
                                <td class="py-2 pr-4 font-semibold"><?= e((string) ($coffeeRow['flavor_name'] ?? '-')) ?></td>
                                <td class="py-2 pr-4"><?= e(number_format((float) ($coffeeRow['total_qty'] ?? 0), 2)) ?></td>
                                <td class="py-2"><?= e(format_money((float) ($coffeeRow['total_revenue'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="py-3 text-rose-700">No tracked syrup flavor sales data for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
<?php endif; ?>

<?php if ($canAccessCrm && (!$isGeneralManager || $gmDept === 'crm')): ?>
<section id="dept-crm" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
        <h3 class="text-lg font-extrabold text-slate-900">CRM Department Dashboard</h3>
        <p class="text-sm text-slate-600">CRM coverage, flavor analytics, CRM summary output, sales trend leader, and automated digital promotion feed.</p>
        <?php if (!$isGeneralManager): ?>
            <?php $renderRangeFilters($range, $rangeLabelMap); ?>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">CRM Coverage</h4>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
                <p class="text-xs uppercase tracking-wide text-brand-700">Order Coverage</p>
                <p class="mt-1 text-xl font-black text-brand-700"><?= e(number_format($crmCoverageSnapshot['order_coverage_percent'], 2)) ?>%</p>
                <p class="mt-1 text-xs text-brand-700"><?= e((string) $crmCoverageSnapshot['covered_sales_orders']) ?> of <?= e((string) $crmCoverageSnapshot['total_sales_orders']) ?> orders</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Customer Coverage</p>
                <p class="mt-1 text-xl font-black text-emerald-700"><?= e(number_format($crmCoverageSnapshot['customer_coverage_percent'], 2)) ?>%</p>
                <p class="mt-1 text-xs text-emerald-700"><?= e((string) $crmCoverageSnapshot['covered_sales_customers']) ?> of <?= e((string) $crmCoverageSnapshot['total_sales_customers']) ?> customers</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Approved CRM Profiles</p>
                <p class="mt-1 text-xl font-black text-slate-900"><?= e((string) $crmCoverageSnapshot['approved_crm_profiles']) ?></p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                <p class="text-xs uppercase tracking-wide text-amber-700">Uncovered Sales Orders</p>
                <p class="mt-1 text-xl font-black text-amber-700"><?= e((string) max(0, $crmCoverageSnapshot['total_sales_orders'] - $crmCoverageSnapshot['covered_sales_orders'])) ?></p>
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Order & Customer Coverage</h4>
            <div class="flex items-center justify-center mt-3" style="height:220px">
                <canvas id="chartCrmCoverage"></canvas>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Tracked Syrup Flavor Comparison</h4>
            <div class="mt-3" style="height:220px">
                <canvas id="chartCrmFlavors"></canvas>
            </div>
        </article>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Statistical Analytics of High Sales and Low Sales Coffee Flavor</h4>
        <div class="mt-3 grid gap-4 xl:grid-cols-2">
            <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                <h5 class="text-xs font-extrabold uppercase tracking-wide text-emerald-700">High Sales Coffee Flavor</h5>
                <div class="table-scroll mt-2">
                    <table class="stack-table w-full min-w-[320px] text-sm">
                        <thead>
                        <tr class="text-left text-emerald-700">
                            <th class="pb-2 pr-4" data-priority="high">Flavor</th>
                            <th class="pb-2 pr-4" data-priority="high">Qty</th>
                            <th class="pb-2" data-priority="medium">Revenue</th>
                        </tr>
                        </thead>
                        <tbody class="text-slate-700">
                        <?php if ($crmHighTrackedFlavorRows): ?>
                            <?php foreach ($crmHighTrackedFlavorRows as $trackedFlavorRow): ?>
                                <tr class="border-t border-emerald-100">
                                    <td class="py-2 pr-4 font-semibold"><?= e((string) ($trackedFlavorRow['flavor_name'] ?? '-')) ?></td>
                                    <td class="py-2 pr-4"><?= e(number_format((float) ($trackedFlavorRow['total_qty'] ?? 0), 2)) ?></td>
                                    <td class="py-2"><?= e(format_money((float) ($trackedFlavorRow['total_revenue'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="py-3 text-emerald-700">No tracked syrup flavor sales data for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                <h5 class="text-xs font-extrabold uppercase tracking-wide text-rose-700">Low Sales Coffee Flavor</h5>
                <div class="table-scroll mt-2">
                    <table class="stack-table w-full min-w-[320px] text-sm">
                        <thead>
                        <tr class="text-left text-rose-700">
                            <th class="pb-2 pr-4" data-priority="high">Flavor</th>
                            <th class="pb-2 pr-4" data-priority="high">Qty</th>
                            <th class="pb-2" data-priority="medium">Revenue</th>
                        </tr>
                        </thead>
                        <tbody class="text-slate-700">
                        <?php if ($crmLowTrackedFlavorRows): ?>
                            <?php foreach ($crmLowTrackedFlavorRows as $trackedFlavorRow): ?>
                                <tr class="border-t border-rose-100">
                                    <td class="py-2 pr-4 font-semibold"><?= e((string) ($trackedFlavorRow['flavor_name'] ?? '-')) ?></td>
                                    <td class="py-2 pr-4"><?= e(number_format((float) ($trackedFlavorRow['total_qty'] ?? 0), 2)) ?></td>
                                    <td class="py-2"><?= e(format_money((float) ($trackedFlavorRow['total_revenue'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="py-3 text-rose-700">No tracked syrup flavor sales data for this <?= e(strtolower($rangeLabelMap[$range])) ?> view.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Summary Output of CRM</h4>
            <div class="table-scroll mt-3">
                <table class="stack-table w-full min-w-[320px] text-sm">
                    <tbody class="text-slate-700">
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold">Approved Profiles</td>
                            <td class="py-2 text-right font-semibold"><?= e((string) $crmSummarySnapshot['approved_profiles']) ?></td>
                        </tr>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold">Active Profiles (with purchases)</td>
                            <td class="py-2 text-right font-semibold"><?= e((string) $crmSummarySnapshot['active_profiles']) ?></td>
                        </tr>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold">Total Purchase Count</td>
                            <td class="py-2 text-right font-semibold"><?= e((string) $crmSummarySnapshot['total_purchase_count']) ?></td>
                        </tr>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold">Total Customer Spend</td>
                            <td class="py-2 text-right font-semibold"><?= e(format_money($crmSummarySnapshot['total_spent'])) ?></td>
                        </tr>
                        <tr class="border-t border-slate-200">
                            <td class="py-2 pr-4 font-black">Average Spend per Profile</td>
                            <td class="py-2 text-right font-black text-brand-700"><?= e(format_money($crmSummarySnapshot['avg_spent'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="rounded-2xl border border-brand-200 bg-brand-50 p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-brand-700">Sales Trend Leader</h4>
            <div class="table-scroll mt-3">
                <table class="stack-table w-full min-w-[320px] text-sm">
                    <tbody class="text-slate-700">
                        <tr class="border-t border-brand-100">
                            <td class="py-2 pr-4 font-semibold">Leading Coffee Flavor</td>
                            <td class="py-2 text-right font-semibold"><?= e((string) $salesTrendLeader['beverage_name']) ?></td>
                        </tr>
                        <tr class="border-t border-brand-100">
                            <td class="py-2 pr-4 font-semibold">Flavor Qty Sold</td>
                            <td class="py-2 text-right font-semibold"><?= e(number_format((float) $salesTrendLeader['beverage_qty'], 0)) ?></td>
                        </tr>
                        <tr class="border-t border-brand-100">
                            <td class="py-2 pr-4 font-semibold">Flavor Revenue</td>
                            <td class="py-2 text-right font-semibold"><?= e(format_money((float) $salesTrendLeader['beverage_revenue'])) ?></td>
                        </tr>
                        <tr class="border-t border-brand-100">
                            <td class="py-2 pr-4 font-semibold">Top Customer</td>
                            <td class="py-2 text-right font-semibold"><?= e((string) $salesTrendLeader['customer_name']) ?></td>
                        </tr>
                        <tr class="border-t border-brand-100">
                            <td class="py-2 pr-4 font-semibold">Top Customer Qty</td>
                            <td class="py-2 text-right font-semibold"><?= e(number_format((float) $salesTrendLeader['customer_qty'], 0)) ?></td>
                        </tr>
                        <tr class="border-t border-brand-200">
                            <td class="py-2 pr-4 font-black">Top Customer Revenue</td>
                            <td class="py-2 text-right font-black text-brand-700"><?= e(format_money((float) $salesTrendLeader['customer_revenue'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </article>
    </div>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h4 class="text-sm font-extrabold text-slate-900">Automated Digital Promotion Feed</h4>
        <div class="mt-3 grid gap-2">
            <?php foreach ($automatedPromotionFeed as $feedItem): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                    <?= e((string) $feedItem) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
</section>
<?php endif; ?>

<?php if ($canAccessMarketing && (!$isGeneralManager || $gmDept === 'marketing')): ?>
<section id="dept-marketing" class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div>
        <h3 class="text-lg font-extrabold text-slate-900">Marketing Department Dashboard</h3>
        <p class="text-sm text-slate-600">CRM coverage for campaign planning and audience reach.</p>
        <?php if (!$isGeneralManager): ?>
            <?php $renderRangeFilters($range, $rangeLabelMap); ?>
        <?php endif; ?>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
            <p class="text-xs uppercase tracking-wide text-brand-700">Order Coverage</p>
            <p class="mt-1 text-xl font-black text-brand-700"><?= e(number_format($crmCoverageSnapshot['order_coverage_percent'], 2)) ?>%</p>
            <p class="mt-1 text-xs text-brand-700"><?= e((string) $crmCoverageSnapshot['covered_sales_orders']) ?> / <?= e((string) $crmCoverageSnapshot['total_sales_orders']) ?> approved orders</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs uppercase tracking-wide text-emerald-700">Customer Coverage</p>
            <p class="mt-1 text-xl font-black text-emerald-700"><?= e(number_format($crmCoverageSnapshot['customer_coverage_percent'], 2)) ?>%</p>
            <p class="mt-1 text-xs text-emerald-700"><?= e((string) $crmCoverageSnapshot['covered_sales_customers']) ?> / <?= e((string) $crmCoverageSnapshot['total_sales_customers']) ?> customers</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Approved CRM Profiles</p>
            <p class="mt-1 text-xl font-black text-slate-900"><?= e((string) $crmCoverageSnapshot['approved_crm_profiles']) ?></p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
            <p class="text-xs uppercase tracking-wide text-amber-700">Coverage Gap Orders</p>
            <p class="mt-1 text-xl font-black text-amber-700"><?= e((string) max(0, $crmCoverageSnapshot['total_sales_orders'] - $crmCoverageSnapshot['covered_sales_orders'])) ?></p>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Order Coverage vs Gap</h4>
            <div class="flex items-center justify-center mt-3" style="height:220px">
                <canvas id="chartMarketingOrderCoverage"></canvas>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-extrabold text-slate-900">Customer Coverage vs Gap</h4>
            <div class="flex items-center justify-center mt-3" style="height:220px">
                <canvas id="chartMarketingCustomerCoverage"></canvas>
            </div>
        </article>
    </div>
</section>
<?php endif; ?>

<script src="assets/vendor/chartjs/chart.umd.js"></script>
<script>
(function () {
    'use strict';

    const COLORS = {
        emerald: '#10b981', emeraldLight: '#d1fae5',
        amber:   '#f59e0b', amberLight:   '#fef3c7',
        rose:    '#f43f5e', roseLight:    '#ffe4e6',
        brand:   '#6366f1', brandLight:   '#ede9fe',
        violet:  '#8b5cf6', violetLight:  '#ede9fe',
        slate:   '#64748b', slateLight:   '#f1f5f9',
        sky:     '#0ea5e9', skyLight:     '#e0f2fe',
        orange:  '#f97316',
    };

    const CHART_DEFAULTS = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } } },
    };

    function doughnut(id, labels, data, colors) {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2 }] },
            options: { ...CHART_DEFAULTS, cutout: '62%', plugins: { ...CHART_DEFAULTS.plugins } },
        });
    }

    function bar(id, labels, datasets, opts) {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'bar',
            data: { labels, datasets },
            options: {
                ...CHART_DEFAULTS,
                indexAxis: opts?.horizontal ? 'y' : 'x',
                scales: {
                    x: { ticks: { font: { size: 10 } }, grid: { display: false } },
                    y: { ticks: { font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                },
                plugins: { ...CHART_DEFAULTS.plugins, legend: { display: (datasets.length > 1), labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } } },
                ...opts?.extra,
            },
        });
    }

    /* ── PURCHASING ── */
    <?php if ($canAccessPurchasing): ?>
    doughnut(
        'chartPurchasingStatus',
        ['Approved', 'Pending', 'Rejected'],
        [<?= (int)$purchasingStats['approved_requests'] ?>, <?= (int)$purchasingStats['pending_requests'] ?>, <?= (int)$purchasingStats['rejected_requests'] ?>],
        [COLORS.emerald, COLORS.amber, COLORS.rose]
    );

    bar(
        'chartPurchasingIngredients',
        <?= json_encode(array_column($purchasingIngredientRows, 'ingredient_name')) ?>,
        [
            { label: 'Requested Qty', data: <?= json_encode(array_map(fn($r) => (float)($r['total_requested_qty'] ?? 0), $purchasingIngredientRows)) ?>, backgroundColor: COLORS.brandLight, borderColor: COLORS.brand, borderWidth: 1.5 },
            { label: 'Approved Qty',  data: <?= json_encode(array_map(fn($r) => (float)($r['approved_requested_qty'] ?? 0), $purchasingIngredientRows)) ?>, backgroundColor: COLORS.emeraldLight, borderColor: COLORS.emerald, borderWidth: 1.5 },
        ],
        { horizontal: true }
    );
    <?php endif; ?>

    /* ── INVENTORY ── */
    <?php if ($canAccessInventory): ?>
    doughnut(
        'chartInventoryHealth',
        ['Safe (>50)', 'Watch (11–50)', 'Low Stock (≤10)', 'Out of Stock'],
        [<?= (int)$inventoryStatusSnapshot['healthy_stock'] ?>, <?= (int)$inventoryStatusSnapshot['watch_stock'] ?>, <?= (int)$inventoryStatusSnapshot['low_stock'] ?>, <?= (int)$inventoryStatusSnapshot['out_of_stock'] ?>],
        [COLORS.emerald, COLORS.amber, COLORS.orange, COLORS.rose]
    );

    bar(
        'chartInventoryMonitoring',
        <?= json_encode(array_column(array_slice($inventoryMonitoringRows, 0, 8), 'item_name')) ?>,
        [
            { label: 'Stock Qty',     data: <?= json_encode(array_map(fn($r) => (float)($r['stock_qty'] ?? 0), array_slice($inventoryMonitoringRows, 0, 8))) ?>, backgroundColor: COLORS.brandLight, borderColor: COLORS.brand, borderWidth: 1.5 },
            { label: 'Reorder Level', data: <?= json_encode(array_map(fn($r) => (float)($r['reorder_level'] ?? 0), array_slice($inventoryMonitoringRows, 0, 8))) ?>, backgroundColor: COLORS.amberLight, borderColor: COLORS.amber, borderWidth: 1.5 },
        ],
        { horizontal: true }
    );
    <?php endif; ?>

    /* ── PRODUCTION ── */
    <?php if ($canAccessProduction): ?>
    doughnut(
        'chartProductionStatus',
        ['Approved', 'Pending', 'Rejected'],
        [<?= (int)$productionStats['approved_logs'] ?>, <?= (int)$productionStats['pending_logs'] ?>, <?= (int)$productionStats['rejected_logs'] ?>],
        [COLORS.emerald, COLORS.amber, COLORS.rose]
    );

    bar(
        'chartProductionBeverages',
        <?= json_encode(array_column($productionTopBeverageRows, 'beverage_name')) ?>,
        [
            { label: 'Qty Prepared', data: <?= json_encode(array_map(fn($r) => (float)($r['total_qty_prepared'] ?? 0), $productionTopBeverageRows)) ?>, backgroundColor: COLORS.violetLight, borderColor: COLORS.violet, borderWidth: 1.5 },
        ],
        { horizontal: true }
    );
    <?php endif; ?>

    /* ── SALES ── */
    <?php if ($canAccessSales): ?>
    doughnut(
        'chartSalesPayment',
        <?= json_encode(array_map(fn($r) => ucfirst((string)($r['payment_method'] ?? '-')), $salesPaymentRows)) ?>,
        <?= json_encode(array_map(fn($r) => (float)($r['total_revenue'] ?? 0), $salesPaymentRows)) ?>,
        [COLORS.brand, COLORS.emerald, COLORS.amber, COLORS.violet, COLORS.sky, COLORS.rose]
    );

    bar(
        'chartSalesBeverages',
        <?= json_encode(array_column($highSalesCoffeeRows, 'beverage_name')) ?>,
        [
            { label: 'Qty Sold', data: <?= json_encode(array_map(fn($r) => (float)($r['total_qty'] ?? 0), $highSalesCoffeeRows)) ?>, backgroundColor: COLORS.brandLight, borderColor: COLORS.brand, borderWidth: 1.5 },
        ],
        { horizontal: true }
    );
    <?php endif; ?>

    /* ── ACCOUNTING ── */
    <?php if ($canAccessAccounting): ?>
    (function () {
        const labels = <?= json_encode(array_column($crmTrackedFlavorRows, 'flavor_name')) ?>;
        const bgColors = [COLORS.emeraldLight, COLORS.amberLight, COLORS.brandLight];
        const borderColors = [COLORS.emerald, COLORS.amber, COLORS.brand];

        bar('chartAccountingFlavorQty',
            labels,
            [{ label: 'Qty Sold', data: <?= json_encode(array_map(fn($r) => round((float)($r['total_qty'] ?? 0), 2), $crmTrackedFlavorRows)) ?>, backgroundColor: bgColors, borderColor: borderColors, borderWidth: 1.5 }],
            {}
        );
        bar('chartAccountingFlavorRevenue',
            labels,
            [{ label: 'Revenue (₱)', data: <?= json_encode(array_map(fn($r) => round((float)($r['total_revenue'] ?? 0), 2), $crmTrackedFlavorRows)) ?>, backgroundColor: bgColors, borderColor: borderColors, borderWidth: 1.5 }],
            {}
        );
    })();
    <?php endif; ?>

    /* ── CRM ── */
    <?php if ($canAccessCrm): ?>
    (function () {
        const coveredOrders   = <?= (int)$crmCoverageSnapshot['covered_sales_orders'] ?>;
        const uncoveredOrders = Math.max(0, <?= (int)$crmCoverageSnapshot['total_sales_orders'] ?> - coveredOrders);
        const coveredCusts    = <?= (int)$crmCoverageSnapshot['covered_sales_customers'] ?>;
        const uncoveredCusts  = Math.max(0, <?= (int)$crmCoverageSnapshot['total_sales_customers'] ?> - coveredCusts);

        doughnut('chartCrmCoverage',
            ['Covered Orders', 'Uncovered Orders', 'Covered Customers', 'Uncovered Customers'],
            [coveredOrders, uncoveredOrders, coveredCusts, uncoveredCusts],
            [COLORS.brand, COLORS.brandLight, COLORS.emerald, COLORS.emeraldLight]
        );

        bar('chartCrmFlavors',
            <?= json_encode(array_column($crmHighTrackedFlavorRows, 'flavor_name')) ?>,
            [
                { label: 'Qty Sold',    data: <?= json_encode(array_map(fn($r) => round((float)($r['total_qty'] ?? 0), 2), $crmHighTrackedFlavorRows)) ?>, backgroundColor: COLORS.emeraldLight, borderColor: COLORS.emerald, borderWidth: 1.5 },
                { label: 'Revenue (₱)', data: <?= json_encode(array_map(fn($r) => round((float)($r['total_revenue'] ?? 0), 2), $crmHighTrackedFlavorRows)) ?>, backgroundColor: COLORS.brandLight,   borderColor: COLORS.brand,   borderWidth: 1.5 },
            ],
            {}
        );
    })();
    <?php endif; ?>

    /* ── MARKETING ── */
    <?php if ($canAccessMarketing): ?>
    (function () {
        const covOrders  = <?= (int)$crmCoverageSnapshot['covered_sales_orders'] ?>;
        const gapOrders  = Math.max(0, <?= (int)$crmCoverageSnapshot['total_sales_orders'] ?> - covOrders);
        const covCusts   = <?= (int)$crmCoverageSnapshot['covered_sales_customers'] ?>;
        const gapCusts   = Math.max(0, <?= (int)$crmCoverageSnapshot['total_sales_customers'] ?> - covCusts);

        doughnut('chartMarketingOrderCoverage',
            ['Covered', 'Gap'],
            [covOrders, gapOrders],
            [COLORS.brand, COLORS.slateLight]
        );
        doughnut('chartMarketingCustomerCoverage',
            ['Covered', 'Gap'],
            [covCusts, gapCusts],
            [COLORS.emerald, COLORS.slateLight]
        );
    })();
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>