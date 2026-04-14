<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_general_manager();

$pdo = db();

$moduleOptions = ['all', 'inventory', 'production', 'sales', 'accounting', 'crm', 'marketing', 'system'];
$actionOptions = ['all', 'create', 'edit', 'delete', 'approved', 'rejected', 'system_create', 'system_update'];
$sourceOptions = ['all', 'user', 'system'];

$search = trim((string) ($_GET['q'] ?? ''));
$module = (string) ($_GET['module'] ?? 'all');
$actionType = (string) ($_GET['action_type'] ?? 'all');
$source = (string) ($_GET['source'] ?? 'all');
$dateFrom = (string) ($_GET['from'] ?? '');
$dateTo = (string) ($_GET['to'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 20);
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 20;
}

if (!in_array($module, $moduleOptions, true)) {
    $module = 'all';
}

if (!in_array($actionType, $actionOptions, true)) {
    $actionType = 'all';
}

if (!in_array($source, $sourceOptions, true)) {
    $source = 'all';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$where = [];
$params = [];

if ($module !== 'all') {
    $where[] = 'a.module = ?';
    $params[] = $module;
}

if ($actionType !== 'all') {
    $where[] = 'a.action_type = ?';
    $params[] = $actionType;
}

if ($source !== 'all') {
    $where[] = 'a.source = ?';
    $params[] = $source;
}

if ($search !== '') {
    $where[] = '(a.note LIKE ? OR a.table_name LIKE ? OR CAST(a.record_id AS CHAR) LIKE ? OR a.action_type LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($dateFrom !== '') {
    $where[] = 'DATE(a.performed_at) >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(a.performed_at) <= ?';
    $params[] = $dateTo;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*)
    FROM audit_trails a
    LEFT JOIN users u ON u.id = a.performed_by
    {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare("SELECT a.*, u.full_name AS performed_name
    FROM audit_trails a
    LEFT JOIN users u ON u.id = a.performed_by
    {$whereSql}
    ORDER BY a.performed_at DESC, a.id DESC
    LIMIT {$perPage} OFFSET {$offset}");
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$pageTitle = 'Audit Logs';
$activePage = 'audit_logs';
require_once __DIR__ . '/includes/layout_top.php';
?>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <form method="get" class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div class="md:col-span-2 xl:col-span-2">
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="note, table, record, actor" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Module</label>
            <select name="module" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <?php foreach ($moduleOptions as $option): ?>
                    <option value="<?= e($option) ?>" <?= $module === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Action</label>
            <select name="action_type" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <?php foreach ($actionOptions as $option): ?>
                    <option value="<?= e($option) ?>" <?= $actionType === $option ? 'selected' : '' ?>><?= e(strtoupper($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Source</label>
            <select name="source" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <?php foreach ($sourceOptions as $option): ?>
                    <option value="<?= e($option) ?>" <?= $source === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Rows</label>
            <select name="per_page" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <?php foreach ($allowedPerPage as $size): ?>
                    <option value="<?= e((string) $size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e((string) $size) ?></option>
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

        <div class="md:col-span-3 xl:col-span-6 flex flex-wrap items-center gap-2">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Apply Filters</button>
            <a href="audit_logs.php" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            <p class="w-full rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 sm:ml-auto sm:w-auto">Total Logs: <?= e((string) $totalRows) ?></p>
        </div>
    </form>

    <div class="table-scroll mt-4">
        <table class="stack-table w-full min-w-[1100px] text-sm">
            <thead>
            <tr class="text-left text-slate-500">
                <th class="pb-2 pr-4" data-priority="low">Date</th>
                <th class="pb-2 pr-4" data-priority="high">Module</th>
                <th class="pb-2 pr-4" data-priority="high">Record</th>
                <th class="pb-2 pr-4" data-priority="high">Action</th>
                <th class="pb-2 pr-4" data-priority="medium">Source</th>
                <th class="pb-2 pr-4" data-priority="low">By</th>
                <th class="pb-2 pr-4" data-priority="low">Note</th>
                <th class="pb-2" data-priority="high">Details</th>
            </tr>
            </thead>
            <tbody class="text-slate-700">
            <?php if ($rows): ?>
                <?php foreach ($rows as $row): ?>
                    <?php $modalId = 'audit-view-' . (int) $row['id']; ?>
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-4 text-xs text-slate-500"><?= e(format_table_value('updated_at', $row['performed_at'])) ?></td>
                        <td class="py-2 pr-4 font-semibold"><?= e(ucfirst((string) $row['module'])) ?></td>
                        <td class="py-2 pr-4"><?= e((string) $row['table_name']) ?> #<?= e((string) $row['record_id']) ?></td>
                        <td class="py-2 pr-4"><span class="rounded-full border border-slate-300 bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700"><?= e(strtoupper((string) $row['action_type'])) ?></span></td>
                        <td class="py-2 pr-4"><span class="rounded-full px-2 py-1 text-xs font-bold <?= $row['source'] === 'system' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-700' ?>"><?= e(strtoupper((string) $row['source'])) ?></span></td>
                        <td class="py-2 pr-4"><?= e($row['performed_name'] ?? 'System') ?></td>
                        <td class="py-2 pr-4 max-w-[260px] truncate" title="<?= e((string) ($row['note'] ?? '')) ?>"><?= e((string) ($row['note'] ?? '-')) ?></td>
                        <td class="py-2">
                            <button type="button" onclick="openModal('<?= e($modalId) ?>')" class="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50">View</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="py-4 text-center text-slate-500">No audit records match the current filters.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-slate-500">Page <?= e((string) $page) ?> of <?= e((string) $totalPages) ?></p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="audit_logs.php?<?= e(query_with($_GET, ['page' => $page - 1])) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="audit_logs.php?<?= e(query_with($_GET, ['page' => $page + 1])) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php foreach ($rows as $row): ?>
    <?php
    $modalId = 'audit-view-' . (int) $row['id'];
    $oldData = $row['old_data'] ? json_decode((string) $row['old_data'], true) : null;
    $newData = $row['new_data'] ? json_decode((string) $row['new_data'], true) : null;
    $diffData = $row['diff_data'] ? json_decode((string) $row['diff_data'], true) : null;
    $oldJson = $oldData ? json_encode($oldData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
    $newJson = $newData ? json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
    $diffJson = $diffData ? json_encode($diffData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
    ?>
    <div id="<?= e($modalId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, '<?= e($modalId) ?>')">
        <div class="w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h4 class="text-lg font-extrabold text-slate-900">Audit Detail #<?= e((string) $row['id']) ?></h4>
                    <p class="text-sm text-slate-600"><?= e((string) $row['table_name']) ?> #<?= e((string) $row['record_id']) ?> | <?= e(strtoupper((string) $row['action_type'])) ?></p>
                </div>
                <button type="button" onclick="closeModal('<?= e($modalId) ?>')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Old Data</p>
                    <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-all text-xs text-slate-700"><?= e((string) $oldJson) ?></pre>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">New Data</p>
                    <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-all text-xs text-slate-700"><?= e((string) $newJson) ?></pre>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Changed Fields</p>
                    <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-all text-xs text-slate-700"><?= e((string) $diffJson) ?></pre>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
