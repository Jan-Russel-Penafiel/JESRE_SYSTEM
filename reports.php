<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_general_manager();

$pdo = db();

$financial = $pdo->query("SELECT
    COALESCE(SUM(CASE WHEN entry_type = 'income' AND status = 'approved' THEN amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN entry_type = 'expense' AND status = 'approved' THEN amount ELSE 0 END), 0) AS expense,
    COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_entries
FROM accounting_entries")
    ->fetch();

$income = (float) ($financial['income'] ?? 0);
$expense = (float) ($financial['expense'] ?? 0);
$net = $income - $expense;

$inventoryRows = $pdo->query("SELECT item_name, stock_qty, reorder_level, unit, updated_at
    FROM inventory_items
    WHERE status = 'approved'
    ORDER BY (stock_qty <= reorder_level) DESC, stock_qty ASC")
    ->fetchAll();

$lowStockCount = 0;
foreach ($inventoryRows as $inventoryRow) {
    if ((float) $inventoryRow['stock_qty'] <= (float) $inventoryRow['reorder_level']) {
        $lowStockCount++;
    }
}

$crmTopCustomers = $pdo->query("SELECT customer_name, purchase_count, total_spent, last_purchase_at
    FROM crm_profiles
    WHERE status = 'approved'
    ORDER BY total_spent DESC, purchase_count DESC
    LIMIT 10")
    ->fetchAll();

$crmHistory = $pdo->query("SELECT c.customer_name, h.amount, h.purchased_at, s.order_code
    FROM crm_purchase_history h
    LEFT JOIN crm_profiles c ON c.id = h.profile_id
    LEFT JOIN sales_orders s ON s.id = h.sales_order_id
    ORDER BY h.purchased_at DESC
    LIMIT 12")
    ->fetchAll();

$dailySalesTrends = $pdo->query("SELECT DATE(created_at) AS trend_date, COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_revenue
    FROM sales_orders
    WHERE status = 'approved'
    GROUP BY DATE(created_at)
    ORDER BY trend_date DESC
    LIMIT 10")
    ->fetchAll();

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

$marketingCampaigns = $pdo->query("SELECT campaign_name, start_date, end_date, status, updated_at
    FROM marketing_campaigns
    ORDER BY updated_at DESC
    LIMIT 12")
    ->fetchAll();

$automatedCampaignCount = (int) $pdo->query("SELECT COUNT(*)
    FROM marketing_campaigns
    WHERE campaign_name LIKE 'AUTO-DIGITAL-%'")
    ->fetchColumn();

$latestAutomatedCampaign = $pdo->query("SELECT campaign_name, updated_at, approval_note
    FROM marketing_campaigns
    WHERE campaign_name LIKE 'AUTO-DIGITAL-%'
    ORDER BY updated_at DESC
    LIMIT 1")
    ->fetch() ?: null;

$pdfInventoryRows = array_map(static function (array $row): array {
    return [
        'item' => (string) $row['item_name'],
        'stock' => number_format((float) $row['stock_qty'], 2) . ' ' . (string) $row['unit'],
        'reorder' => number_format((float) $row['reorder_level'], 2),
        'updated' => format_table_value('updated_at', $row['updated_at']),
    ];
}, array_slice($inventoryRows, 0, 30));

$pdfCrmRows = array_map(static function (array $row): array {
    return [
        'customer' => (string) ($row['customer_name'] ?? '-'),
        'order' => (string) ($row['order_code'] ?? '-'),
        'amount' => format_money((float) ($row['amount'] ?? 0)),
        'date' => format_table_value('updated_at', $row['purchased_at'] ?? null),
    ];
}, array_slice($crmHistory, 0, 30));

$pdfTrendRows = array_map(static function (array $row): array {
    return [
        'date' => format_table_value('start_date', $row['trend_date']),
        'orders' => (string) ((int) $row['total_orders']),
        'revenue' => format_money((float) $row['total_revenue']),
    ];
}, array_slice($dailySalesTrends, 0, 30));

$pdfCampaignRows = array_map(static function (array $row): array {
    return [
        'campaign' => (string) $row['campaign_name'],
        'duration' => format_table_value('start_date', $row['start_date']) . ' - ' . format_table_value('end_date', $row['end_date']),
        'status' => strtoupper((string) $row['status']),
        'updated' => format_table_value('updated_at', $row['updated_at']),
    ];
}, array_slice($marketingCampaigns, 0, 30));

$pdfPayload = [
    'generatedAt' => date('M d, Y h:i A'),
    'financial' => [
        'income' => format_money($income),
        'expense' => format_money($expense),
        'net' => format_money($net),
    ],
    'summary' => [
        'trackedCustomers' => (string) count($crmTopCustomers),
        'inventoryItems' => (string) count($inventoryRows),
        'lowStockAlerts' => (string) $lowStockCount,
        'automatedPromotions' => (string) $automatedCampaignCount,
        'highSalesCoffee' => $highSalesCoffee . ' (' . number_format($highSalesQty, 0) . ' qty)',
        'lowSalesCoffee' => $lowSalesCoffee . ' (' . number_format($lowSalesQty, 0) . ' qty)',
    ],
    'tables' => [
        'inventory' => $pdfInventoryRows,
        'crmHistory' => $pdfCrmRows,
        'trends' => $pdfTrendRows,
        'campaigns' => $pdfCampaignRows,
    ],
];

$jsPdfFile = __DIR__ . '/assets/vendor/jspdf/jspdf.umd.min.js';
$jsPdfVersion = is_file($jsPdfFile) ? (string) filemtime($jsPdfFile) : '1';

$pageTitle = 'Summary Reports';
$activePage = 'reports';
$pageTitleActionHtml = "<button type=\"button\" onclick=\"exportSummaryPdf()\" class=\"app-title-action-btn rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-900\">Export Summary PDF (jsPDF)</button>";
require_once __DIR__ . '/includes/layout_top.php';
?>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wider text-slate-500">Financial Income</p>
        <h3 class="mt-1 text-2xl font-black text-emerald-600"><?= e(format_money($income)) ?></h3>
        <p class="mt-1 text-sm text-slate-500">Approved entries included</p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wider text-slate-500">Financial Expense</p>
        <h3 class="mt-1 text-2xl font-black text-rose-600"><?= e(format_money($expense)) ?></h3>
        <p class="mt-1 text-sm text-slate-500">Operational and manual expenses</p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wider text-slate-500">Net Position</p>
        <h3 class="mt-1 text-2xl font-black <?= $net >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= e(format_money($net)) ?></h3>
        <p class="mt-1 text-sm text-slate-500">Income - Expense</p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wider text-slate-500">Inventory Alerts</p>
        <h3 class="mt-1 text-2xl font-black <?= $lowStockCount > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e((string) $lowStockCount) ?></h3>
        <p class="mt-1 text-sm text-slate-500">Items below reorder level</p>
    </article>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-lg font-extrabold text-slate-900">Unified Output: Financial + CRM + Inventory</h3>

    <div class="mt-4 grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-wider text-slate-500">Financial Summary</p>
            <p class="mt-2 text-sm text-slate-700">Income: <span class="font-bold"><?= e(format_money($income)) ?></span></p>
            <p class="text-sm text-slate-700">Expense: <span class="font-bold"><?= e(format_money($expense)) ?></span></p>
            <p class="text-sm text-slate-700">Net: <span class="font-bold"><?= e(format_money($net)) ?></span></p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-wider text-slate-500">CRM Insight</p>
            <p class="mt-2 text-sm text-slate-700">Tracked customers: <span class="font-bold"><?= e((string) count($crmTopCustomers)) ?></span></p>
            <p class="text-sm text-slate-700">Latest purchases are auto-fed from approved sales.</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-wider text-slate-500">Inventory Status</p>
            <p class="mt-2 text-sm text-slate-700">Monitored items: <span class="font-bold"><?= e((string) count($inventoryRows)) ?></span></p>
            <p class="text-sm text-slate-700">Low stock count: <span class="font-bold"><?= e((string) $lowStockCount) ?></span></p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-wider text-slate-500">Marketing Automation</p>
            <p class="mt-2 text-sm text-slate-700">Auto campaigns: <span class="font-bold"><?= e((string) $automatedCampaignCount) ?></span></p>
            <p class="text-sm text-slate-700">Latest: <span class="font-bold"><?= e((string) ($latestAutomatedCampaign['campaign_name'] ?? 'N/A')) ?></span></p>
        </div>
    </div>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-lg font-extrabold text-slate-900">Sales Classification (Accounting + CRM Input)</h3>
    <p class="mt-1 text-sm text-slate-600">Based on approved sales over the last 30 days.</p>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs uppercase tracking-wider text-emerald-700">High Sales Coffee</p>
            <p class="mt-1 text-xl font-black text-emerald-800"><?= e($highSalesCoffee) ?></p>
            <p class="mt-1 text-sm font-semibold text-emerald-700">Sold Quantity: <?= e(number_format($highSalesQty, 0)) ?></p>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs uppercase tracking-wider text-amber-700">Low Sales Coffee</p>
            <p class="mt-1 text-xl font-black text-amber-800"><?= e($lowSalesCoffee) ?></p>
            <p class="mt-1 text-sm font-semibold text-amber-700">Sold Quantity: <?= e(number_format($lowSalesQty, 0)) ?></p>
        </div>
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">Inventory Report</h3>
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
                <?php if ($inventoryRows): ?>
                    <?php foreach ($inventoryRows as $row): ?>
                        <?php $isLow = (float) $row['stock_qty'] <= (float) $row['reorder_level']; ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e($row['item_name']) ?></td>
                            <td class="py-2 pr-4 <?= $isLow ? 'text-rose-600 font-bold' : '' ?>"><?= e(number_format((float) $row['stock_qty'], 2)) ?> <?= e($row['unit']) ?></td>
                            <td class="py-2 pr-4"><?= e(number_format((float) $row['reorder_level'], 2)) ?></td>
                            <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('updated_at', $row['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No approved inventory records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">CRM Purchase History</h3>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[680px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-4" data-priority="high">Customer</th>
                    <th class="pb-2 pr-4" data-priority="medium">Order</th>
                    <th class="pb-2 pr-4" data-priority="high">Amount</th>
                    <th class="pb-2" data-priority="low">Date</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($crmHistory): ?>
                    <?php foreach ($crmHistory as $history): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e($history['customer_name'] ?? '-') ?></td>
                            <td class="py-2 pr-4"><?= e($history['order_code'] ?? '-') ?></td>
                            <td class="py-2 pr-4"><?= e(format_money((float) ($history['amount'] ?? 0))) ?></td>
                            <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('updated_at', $history['purchased_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No CRM purchase history yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">Daily Sales Trends (Marketing Input)</h3>
        <div class="table-scroll mt-3">
            <table class="stack-table w-full min-w-[560px] text-sm">
                <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2 pr-4" data-priority="high">Date</th>
                    <th class="pb-2 pr-4" data-priority="high">Orders</th>
                    <th class="pb-2" data-priority="high">Revenue</th>
                </tr>
                </thead>
                <tbody class="text-slate-700">
                <?php if ($dailySalesTrends): ?>
                    <?php foreach ($dailySalesTrends as $trend): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e(format_table_value('start_date', $trend['trend_date'])) ?></td>
                            <td class="py-2 pr-4"><?= e((string) ((int) $trend['total_orders'])) ?></td>
                            <td class="py-2"><?= e(format_money((float) $trend['total_revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="py-4 text-center text-slate-500">No approved sales trend data yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-extrabold text-slate-900">Marketing Campaign Activity</h3>
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
                <?php if ($marketingCampaigns): ?>
                    <?php foreach ($marketingCampaigns as $campaign): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 pr-4 font-semibold"><?= e($campaign['campaign_name']) ?></td>
                            <td class="py-2 pr-4"><?= e(format_table_value('start_date', $campaign['start_date'])) ?> - <?= e(format_table_value('end_date', $campaign['end_date'])) ?></td>
                            <td class="py-2 pr-4"><span class="rounded-full px-2 py-1 text-xs font-bold <?= e(status_badge_class((string) $campaign['status'])) ?>"><?= e(strtoupper((string) $campaign['status'])) ?></span></td>
                            <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('updated_at', $campaign['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No marketing records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<script src="assets/vendor/jspdf/jspdf.umd.min.js?v=<?= e($jsPdfVersion) ?>"></script>
<script>
    const reportPayload = <?= json_encode($pdfPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function ensurePdfSpace(doc, y, neededHeight) {
        if (y + neededHeight <= 790) {
            return y;
        }

        doc.addPage();
        return 50;
    }

    function writeWrappedLine(doc, text, y, options) {
        const width = options && options.width ? options.width : 515;
        const x = options && options.x ? options.x : 40;
        const lineHeight = options && options.lineHeight ? options.lineHeight : 12;
        const lines = doc.splitTextToSize(String(text), width);

        y = ensurePdfSpace(doc, y, lines.length * lineHeight + 4);
        doc.text(lines, x, y);

        return y + lines.length * lineHeight;
    }

    function writeSectionTitle(doc, title, y) {
        y = ensurePdfSpace(doc, y, 22);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(12);
        doc.text(title, 40, y);
        return y + 16;
    }

    function writeSimpleTable(doc, title, headers, rows, y) {
        y = writeSectionTitle(doc, title, y);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        y = writeWrappedLine(doc, headers.join(' | '), y, { x: 40, width: 515, lineHeight: 11 });

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);

        if (!rows.length) {
            y = writeWrappedLine(doc, 'No records available.', y, { x: 40, width: 515, lineHeight: 11 });
            return y + 8;
        }

        rows.forEach(function (row) {
            const line = headers.map(function (headerKey) {
                return row[headerKey] || '-';
            }).join(' | ');
            y = writeWrappedLine(doc, line, y, { x: 40, width: 515, lineHeight: 11 });
        });

        return y + 8;
    }

    function exportSummaryPdf() {
        const jsPdfNamespace = window.jspdf;
        if (!jsPdfNamespace || !jsPdfNamespace.jsPDF) {
            alert('jsPDF failed to load. Please refresh and try again.');
            return;
        }

        const doc = new jsPdfNamespace.jsPDF({ unit: 'pt', format: 'a4' });
        let y = 50;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(16);
        doc.text('Don Macchiatos - Summary Reports', 40, y);
        y += 16;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        y = writeWrappedLine(doc, 'Generated: ' + reportPayload.generatedAt, y, { x: 40, width: 515, lineHeight: 12 });
        y += 8;

        y = writeSectionTitle(doc, 'Financial Summary', y);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        y = writeWrappedLine(doc, 'Income total: ' + reportPayload.financial.income, y, { x: 40, width: 515, lineHeight: 12 });
        y = writeWrappedLine(doc, 'Expense total: ' + reportPayload.financial.expense, y, { x: 40, width: 515, lineHeight: 12 });
        y = writeWrappedLine(doc, 'Net Position: ' + reportPayload.financial.net, y, { x: 40, width: 515, lineHeight: 12 });
        y += 8;

        y = writeSectionTitle(doc, 'Unified Summary Output', y);
        y = writeWrappedLine(doc, 'Tracked Customers: ' + reportPayload.summary.trackedCustomers, y, { x: 40, width: 515, lineHeight: 12 });
        y = writeWrappedLine(doc, 'Inventory Items: ' + reportPayload.summary.inventoryItems, y, { x: 40, width: 515, lineHeight: 12 });
        y = writeWrappedLine(doc, 'Low Stock Count: ' + reportPayload.summary.lowStockAlerts, y, { x: 40, width: 515, lineHeight: 12 });
        y = writeWrappedLine(doc, 'Automated Promotions: ' + reportPayload.summary.automatedPromotions, y, { x: 40, width: 515, lineHeight: 12 });
        y = writeWrappedLine(doc, 'High Sales Coffee: ' + reportPayload.summary.highSalesCoffee, y, { x: 40, width: 515, lineHeight: 12 });
        y = writeWrappedLine(doc, 'Low Sales Coffee: ' + reportPayload.summary.lowSalesCoffee, y, { x: 40, width: 515, lineHeight: 12 });
        y += 8;

        y = writeSimpleTable(doc, 'Inventory Report', ['item', 'stock', 'reorder', 'updated'], reportPayload.tables.inventory, y);
        y = writeSimpleTable(doc, 'CRM Purchase History', ['customer', 'order', 'amount', 'date'], reportPayload.tables.crmHistory, y);
        y = writeSimpleTable(doc, 'Daily Sales Trends', ['date', 'orders', 'revenue'], reportPayload.tables.trends, y);
        y = writeSimpleTable(doc, 'Marketing Campaign Activity', ['campaign', 'duration', 'status', 'updated'], reportPayload.tables.campaigns, y);

        const filename = 'don-macchiatos-summary-' + new Date().toISOString().slice(0, 10) + '.pdf';
        doc.save(filename);
    }
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
