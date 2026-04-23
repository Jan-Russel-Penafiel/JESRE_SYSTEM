<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_login();

$user = current_user();
$pdo = db();

$todaySalesAmount = 0.0;
$todaySalesOrders = 0;
$approvedPurchasingCount = 0;
$approvedInventoryCount = 0;
$approvedProductionCount = 0;
$approvedSalesCount = 0;
$approvedAccountingCount = 0;
$lowStockCount = 0;
$crmProfiles = 0;
$approvedMarketingCount = 0;
$automatedMarketingCount = 0;
$income = 0.0;
$expense = 0.0;
$net = 0.0;
$pendingApprovals = 0;
$myPendingSubmissions = 0;
$lowStockItems = [];
$topBeverages = [];
$liveStockMonitorRows = [];
$automatedPromotionFeed = [];
$recentApprovals = [];
$salesPerformance = [];
$highSalesCoffee = 'N/A';
$highSalesQty = 0.0;
$lowSalesCoffee = 'N/A';
$lowSalesQty = 0.0;

$todaySalesAmount = (float) $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales_orders WHERE status = 'approved' AND DATE(created_at) = CURDATE()")
    ->fetchColumn();
$todaySalesOrders = (int) $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status = 'approved' AND DATE(created_at) = CURDATE()")
    ->fetchColumn();
$approvedPurchasingCount = (int) $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'approved'")
    ->fetchColumn();
$approvedInventoryCount = (int) $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status = 'approved'")
    ->fetchColumn();
$approvedProductionCount = (int) $pdo->query("SELECT COUNT(*) FROM production_logs WHERE status = 'approved'")
    ->fetchColumn();
$approvedSalesCount = (int) $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status = 'approved'")
    ->fetchColumn();
$approvedAccountingCount = (int) $pdo->query("SELECT COUNT(*) FROM accounting_entries WHERE status = 'approved'")
    ->fetchColumn();
$lowStockCount = (int) $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status = 'approved' AND stock_qty <= reorder_level")
    ->fetchColumn();
$crmProfiles = (int) $pdo->query("SELECT COUNT(*) FROM crm_profiles WHERE status = 'approved'")
    ->fetchColumn();
$approvedMarketingCount = (int) $pdo->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status = 'approved'")
    ->fetchColumn();
$automatedMarketingCount = (int) $pdo->query("SELECT COUNT(*) FROM marketing_campaigns WHERE campaign_name LIKE 'AUTO-DIGITAL-%'")
    ->fetchColumn();

$salesPerformance = fetch_sales_performance_snapshot($pdo, 30);
$highSalesCoffee = trim((string) ($salesPerformance['high_sales_beverage_name'] ?? ''));
if ($highSalesCoffee === '') {
    $highSalesCoffee = 'N/A';
}
$highSalesQty = (float) ($salesPerformance['high_sales_qty'] ?? 0);

$lowSalesCoffee = trim((string) ($salesPerformance['low_sales_beverage_name'] ?? ''));
if ($lowSalesCoffee === '') {
    $lowSalesCoffee = 'N/A';
}
$lowSalesQty = (float) ($salesPerformance['low_sales_qty'] ?? 0);

$moduleApprovedCounts = [
    'Purchasing' => $approvedPurchasingCount,
    'Inventory' => $approvedInventoryCount,
    'Production' => $approvedProductionCount,
    'Sales' => $approvedSalesCount,
    'Accounting' => $approvedAccountingCount,
    'CRM' => $crmProfiles,
    'Marketing' => $approvedMarketingCount,
];

$financialStmt = $pdo->query("SELECT
    COALESCE(SUM(CASE WHEN entry_type = 'income' AND status = 'approved' THEN amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN entry_type = 'expense' AND status = 'approved' THEN amount ELSE 0 END), 0) AS expense
FROM accounting_entries");
$financial = $financialStmt->fetch();
$income = (float) ($financial['income'] ?? 0);
$expense = (float) ($financial['expense'] ?? 0);
$net = $income - $expense;

if (($user['role'] ?? null) === ROLE_GENERAL_MANAGER) {
    foreach (department_configs() as $departmentConfig) {
        $table = $departmentConfig['table'];
        $pendingApprovals += (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'")
            ->fetchColumn();
    }

    $recentApprovals = $pdo->query("SELECT l.module, l.record_id, l.action, l.note, l.action_at, u.full_name
        FROM approval_logs l
        LEFT JOIN users u ON u.id = l.action_by
        ORDER BY l.action_at DESC
        LIMIT 8")
        ->fetchAll();
} elseif (($user['role'] ?? '') === ROLE_DEPARTMENT_HEAD) {
    $table = department_table($user['department']);
    if ($table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE submitted_by = ? AND status = 'pending'");
        $stmt->execute([(int) $user['id']]);
        $myPendingSubmissions = (int) $stmt->fetchColumn();
    }
}

$lowStockItems = $pdo->query("SELECT item_name, stock_qty, reorder_level, unit, updated_at
    FROM inventory_items
    WHERE status = 'approved'
    ORDER BY (stock_qty <= reorder_level) DESC, stock_qty ASC
    LIMIT 8")
    ->fetchAll();

$topBeverages = $pdo->query("SELECT beverage_name, SUM(quantity) AS total_quantity, SUM(total_amount) AS total_revenue
    FROM sales_orders
    WHERE status = 'approved'
    GROUP BY beverage_name
    ORDER BY total_quantity DESC
    LIMIT 6")
    ->fetchAll();

$liveStockMonitorRows = $pdo->query("SELECT item_name, stock_qty, reorder_level, unit, updated_at
    FROM inventory_items
    WHERE status = 'approved'
    ORDER BY (stock_qty <= reorder_level) DESC, stock_qty ASC
    LIMIT 8")
    ->fetchAll();

$automatedPromotionFeed = $pdo->query("SELECT campaign_name, start_date, end_date, status, updated_at
    FROM marketing_campaigns
    WHERE campaign_name LIKE 'AUTO-DIGITAL-%'
    ORDER BY updated_at DESC
    LIMIT 8")
    ->fetchAll();

$chartJsFile = __DIR__ . '/assets/vendor/chartjs/chart.umd.js';
$chartJsVersion = is_file($chartJsFile) ? (string) filemtime($chartJsFile) : '1';

$pageTitle = 'Central Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/layout_top.php';
?>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wider text-slate-500">Today Sales</p>
        <h3 class="mt-1 text-2xl font-black text-slate-900"><?= e(format_money($todaySalesAmount)) ?></h3>
        <p class="mt-1 text-sm text-slate-500">Total orders: <?= e((string) $todaySalesOrders) ?></p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wider text-slate-500">Inventory Monitoring</p>
        <h3 class="mt-1 text-2xl font-black text-slate-900"><?= e((string) $approvedInventoryCount) ?> active items</h3>
        <p id="inventoryAlertText" class="mt-1 text-sm <?= $lowStockCount > 0 ? 'text-rose-600 font-semibold' : 'text-slate-500' ?>">Alert items: <span id="inventoryAlertCount"><?= e((string) $lowStockCount) ?></span></p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wider text-slate-500">CRM Coverage</p>
        <h3 class="mt-1 text-2xl font-black text-slate-900"><?= e((string) $crmProfiles) ?> customer profiles</h3>
        <p class="mt-1 text-sm text-slate-500">Customer records</p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wider text-slate-500">Net Financial Position</p>
        <h3 class="mt-1 text-2xl font-black <?= $net >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= e(format_money($net)) ?></h3>
        <p class="mt-1 text-sm text-slate-500">Approved accounting totals</p>
    </article>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-3">
    <article class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-extrabold text-slate-900">Summary of All Data Statistics</h3>
                <p class="text-sm text-slate-600">Processed and approved records per module.</p>
            </div>
            <?php if (($user['role'] ?? '') === ROLE_GENERAL_MANAGER): ?>
                <a href="approvals.php" class="inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Open Review Queue (<?= e((string) $pendingApprovals) ?>)</a>
            <?php else: ?>
                <p class="rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700">Pending submissions: <?= e((string) $myPendingSubmissions) ?></p>
            <?php endif; ?>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="h-72">
                <canvas id="dashboardStatsChart" aria-label="Summary statistics chart" role="img"></canvas>
            </div>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">Summary Output Snapshot</h3>
        <p class="mt-1 text-sm text-slate-600">Latest consolidated metrics.</p>

        <div class="mt-4 space-y-2">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Financial</p>
                <p class="text-sm font-bold text-slate-900">Net: <?= e(format_money($net)) ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">CRM</p>
                <p class="text-sm font-bold text-slate-900"><?= e((string) $crmProfiles) ?> tracked customers</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Inventory</p>
                <p class="text-sm font-bold text-slate-900"><span id="summaryLowStockCount"><?= e((string) $lowStockCount) ?></span> low stock items</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Marketing Automation</p>
                <p class="text-sm font-bold text-slate-900"><?= e((string) $automatedMarketingCount) ?> automated campaigns</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Sales Classification</p>
                <p class="text-sm font-bold text-slate-900">High-sales coffee: <?= e($highSalesCoffee) ?> (<?= e(number_format($highSalesQty, 0)) ?> qty)</p>
                <p class="text-sm font-bold text-slate-900">Low-sales coffee: <?= e($lowSalesCoffee) ?> (<?= e(number_format($lowSalesQty, 0)) ?> qty)</p>
            </div>
        </div>

        <?php if (($user['role'] ?? '') === ROLE_GENERAL_MANAGER): ?>
            <a href="reports.php" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-900">Open Full Summary Reports</a>
        <?php endif; ?>
    </article>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">Inventory Status</h3>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[680px] text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2 pr-4" data-priority="high">Item</th>
                        <th class="pb-2 pr-4" data-priority="high">Stock</th>
                        <th class="pb-2 pr-4" data-priority="low">Reorder</th>
                        <th class="pb-2" data-priority="low">Updated</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($lowStockItems): ?>
                    <?php foreach ($lowStockItems as $item): ?>
                        <?php $isLow = (float) $item['stock_qty'] <= (float) $item['reorder_level']; ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e($item['item_name']) ?></td>
                            <td class="py-2 pr-4 <?= $isLow ? 'text-rose-600 font-bold' : '' ?>"><?= e(number_format((float) $item['stock_qty'], 2)) ?> <?= e($item['unit']) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) $item['reorder_level'], 2)) ?></td>
                            <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('updated_at', $item['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-3 text-slate-500">No inventory data yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">Sales Trend Leaders</h3>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[560px] text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2 pr-4" data-priority="high">Beverage</th>
                        <th class="pb-2 pr-4" data-priority="high">Qty Sold</th>
                        <th class="pb-2" data-priority="high">Revenue</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($topBeverages): ?>
                    <?php foreach ($topBeverages as $beverage): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e($beverage['beverage_name']) ?></td>
                            <td class="py-2 pr-4"><?= e((string) ((int) $beverage['total_quantity'])) ?></td>
                            <td class="py-2"><?= e(format_money((float) $beverage['total_revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="py-3 text-slate-500">No approved sales yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-lg font-extrabold text-slate-900">Live Stock Monitor</h3>
            <p id="liveStockGeneratedAt" class="text-xs font-semibold text-slate-500">Auto-refresh: pending</p>
        </div>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[760px] text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2 pr-4" data-priority="high">Item</th>
                        <th class="pb-2 pr-4" data-priority="high">Available Stock</th>
                        <th class="pb-2 pr-4" data-priority="medium">Reorder Level</th>
                        <th class="pb-2" data-priority="high">Health</th>
                    </tr>
                </thead>
                <tbody id="liveStockTableBody" class="text-slate-700">
                <?php if ($liveStockMonitorRows): ?>
                    <?php foreach ($liveStockMonitorRows as $stockRow): ?>
                        <?php $isLowStock = (float) $stockRow['stock_qty'] <= (float) $stockRow['reorder_level']; ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e($stockRow['item_name']) ?></td>
                            <td class="py-2 pr-4 <?= $isLowStock ? 'font-bold text-rose-600' : 'font-semibold text-slate-700' ?>"><?= e(number_format((float) $stockRow['stock_qty'], 2)) ?> <?= e($stockRow['unit']) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) $stockRow['reorder_level'], 2)) ?> <?= e($stockRow['unit']) ?></td>
                            <td class="py-2">
                                <span class="rounded-full px-2 py-1 text-xs font-bold <?= $isLowStock ? 'bg-rose-100 text-rose-700 border border-rose-300' : 'bg-emerald-100 text-emerald-700 border border-emerald-300' ?>">
                                    <?= $isLowStock ? 'LOW STOCK' : 'HEALTHY' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-3 text-slate-500">No approved inventory items found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">Automated Digital Promotion Feed</h3>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[760px] text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2 pr-4" data-priority="high">Campaign</th>
                        <th class="pb-2 pr-4" data-priority="medium">Duration</th>
                        <th class="pb-2 pr-4" data-priority="high">Status</th>
                        <th class="pb-2" data-priority="low">Updated</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($automatedPromotionFeed): ?>
                    <?php foreach ($automatedPromotionFeed as $campaign): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e($campaign['campaign_name']) ?></td>
                            <td class="py-2 pr-4"><?= e(format_table_value('start_date', $campaign['start_date'])) ?> - <?= e(format_table_value('end_date', $campaign['end_date'])) ?></td>
                            <td class="py-2 pr-4"><span class="rounded-full px-2 py-1 text-xs font-bold <?= e(status_badge_class((string) $campaign['status'])) ?>"><?= e(strtoupper((string) $campaign['status'])) ?></span></td>
                            <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('updated_at', $campaign['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-3 text-slate-500">No automated campaigns found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php if (($user['role'] ?? '') === ROLE_GENERAL_MANAGER): ?>
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">Recent Manager Review Activity</h3>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[760px] text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pb-2 pr-4" data-priority="high">Department</th>
                        <th class="pb-2 pr-4" data-priority="high">Record</th>
                        <th class="pb-2 pr-4" data-priority="high">Decision</th>
                        <th class="pb-2 pr-4" data-priority="low">By</th>
                        <th class="pb-2" data-priority="low">Date</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($recentApprovals): ?>
                    <?php foreach ($recentApprovals as $log): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e(department_short_label($log['module'])) ?></td>
                            <td class="py-2 pr-4">#<?= e((string) $log['record_id']) ?></td>
                            <td class="py-2 pr-4">
                                <span class="rounded-full px-2 py-1 text-xs font-bold <?= $log['action'] === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>"><?= e(strtoupper($log['action'])) ?></span>
                            </td>
                            <td class="py-2 pr-4"><?= e($log['full_name'] ?? 'N/A') ?></td>
                            <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('updated_at', $log['action_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="py-3 text-slate-500">No approval logs yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<script src="assets/vendor/chartjs/chart.umd.js?v=<?= e($chartJsVersion) ?>"></script>
<script>
    (function () {
        const canvas = document.getElementById('dashboardStatsChart');
        if (!canvas || !window.Chart) {
            return;
        }

        const labels = <?= json_encode(array_keys($moduleApprovedCounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const values = <?= json_encode(array_values($moduleApprovedCounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Processed / Approved Records',
                    data: values,
                    borderWidth: 1,
                    borderRadius: 8,
                    backgroundColor: [
                        '#60a5fa',
                        '#34d399',
                        '#f59e0b',
                        '#f87171',
                        '#a78bfa',
                        '#38bdf8',
                        '#f97316'
                    ],
                    borderColor: '#0f172a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    })();

    (function () {
        const canRefreshLiveStock = <?= json_encode(can_user_access_department($user ?? [], 'inventory')) ?>;
        if (!canRefreshLiveStock) {
            return;
        }

        const tableBody = document.getElementById('liveStockTableBody');
        const alertCountNode = document.getElementById('inventoryAlertCount');
        const summaryLowStockNode = document.getElementById('summaryLowStockCount');
        const alertTextNode = document.getElementById('inventoryAlertText');
        const generatedLabelNode = document.getElementById('liveStockGeneratedAt');

        if (!tableBody || !alertCountNode || !summaryLowStockNode || !alertTextNode || !generatedLabelNode) {
            return;
        }

        const escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        const renderRows = function (items) {
            if (!Array.isArray(items) || !items.length) {
                tableBody.innerHTML = '<tr><td colspan="4" class="py-3 text-slate-500">No approved inventory items found.</td></tr>';
                return;
            }

            tableBody.innerHTML = items.map(function (item) {
                const stockQty = Number(item.stock_qty || 0);
                const reorderLevel = Number(item.reorder_level || 0);
                const isLow = item.health === 'low_stock' || stockQty <= reorderLevel;
                const stockClass = isLow ? 'font-bold text-rose-600' : 'font-semibold text-slate-700';
                const badgeClass = isLow
                    ? 'bg-rose-100 text-rose-700 border border-rose-300'
                    : 'bg-emerald-100 text-emerald-700 border border-emerald-300';
                const health = isLow ? 'LOW STOCK' : 'HEALTHY';
                const unit = escapeHtml(item.unit || '');
                const itemName = escapeHtml(item.item_name || '-');

                return '<tr class="border-t border-slate-100">'
                    + '<td class="py-2 pr-4 font-semibold">' + itemName + '</td>'
                    + '<td class="py-2 pr-4 ' + stockClass + '">' + stockQty.toFixed(2) + ' ' + unit + '</td>'
                    + '<td class="py-2 pr-4">' + reorderLevel.toFixed(2) + ' ' + unit + '</td>'
                    + '<td class="py-2"><span class="rounded-full px-2 py-1 text-xs font-bold ' + badgeClass + '">' + health + '</span></td>'
                    + '</tr>';
            }).join('');

            if (window.applyStackTableLabels) {
                const table = tableBody.closest('table.stack-table');
                if (table) {
                    window.applyStackTableLabels(table);
                }
            }
        };

        const refreshLiveStock = function () {
            fetch('inventory_live.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to refresh live stock monitor.');
                    }

                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || payload.ok !== true) {
                        throw new Error('Invalid live stock payload.');
                    }

                    const lowStockCount = Number((payload.summary || {}).low_stock_count || 0);
                    alertCountNode.textContent = String(lowStockCount);
                    summaryLowStockNode.textContent = String(lowStockCount);
                    generatedLabelNode.textContent = 'Auto-refresh: ' + (payload.generated_label || 'updated');
                    alertTextNode.className = 'mt-1 text-sm ' + (lowStockCount > 0 ? 'text-rose-600 font-semibold' : 'text-slate-500');

                    renderRows(payload.items || []);
                })
                .catch(function () {
                    generatedLabelNode.textContent = 'Auto-refresh: unavailable';
                });
        };

        refreshLiveStock();
        window.setInterval(refreshLiveStock, 15000);
    })();
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
