<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_general_manager();

$pdo = db();
$pendingRecords = [];

$inventoryMap = [];
$inventoryRows = $pdo->query('SELECT id, item_name, stock_qty, unit FROM inventory_items ORDER BY item_name ASC')->fetchAll();
foreach ($inventoryRows as $inventoryRow) {
    $inventoryMap[(int) $inventoryRow['id']] = $inventoryRow['item_name'] . ' (' . number_format((float) $inventoryRow['stock_qty'], 2) . ' ' . $inventoryRow['unit'] . ')';
}

$formatIngredientSelection = static function ($value, array $record) use ($inventoryMap): string {
    $ids = normalize_inventory_item_ids($value);
    if ($ids === []) {
        $ids = inventory_item_ids_from_record($record);
    }

    return format_inventory_item_selection($ids, $inventoryMap);
};

foreach (department_configs() as $departmentKey => $departmentConfig) {
    $table = $departmentConfig['table'];
    $primaryLabel = $departmentConfig['primary_label'];

    $stmt = $pdo->query("SELECT '{$departmentKey}' AS department_key, t.*, su.full_name AS submitted_name
        FROM {$table} t
        LEFT JOIN users su ON su.id = t.submitted_by
        WHERE t.status = 'pending'
        ORDER BY t.created_at ASC");
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $row['primary_value'] = $row[$primaryLabel] ?? ('#' . $row['id']);
        $pendingRecords[] = $row;
    }
}

usort($pendingRecords, static function (array $a, array $b): int {
    return strtotime((string) $a['created_at']) <=> strtotime((string) $b['created_at']);
});

$recentLogs = $pdo->query("SELECT l.*, u.full_name AS approver_name
    FROM approval_logs l
    LEFT JOIN users u ON u.id = l.action_by
    ORDER BY l.action_at DESC
    LIMIT 12")
    ->fetchAll();

$pageTitle = 'Manager Review Queue';
$activePage = 'approvals';
require_once __DIR__ . '/includes/layout_top.php';
?>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-3 text-left sm:text-right">
        <span class="inline-flex rounded-xl bg-amber-50 px-3 py-2 text-sm font-bold text-amber-700">Pending Review: <?= e((string) count($pendingRecords)) ?></span>
    </div>

    <div class="table-scroll">
        <table class="stack-table w-full min-w-[860px] text-sm">
            <thead>
            <tr class="text-left text-slate-500">
                <th class="pb-2 pr-4" data-priority="high">Department</th>
                <th class="pb-2 pr-4" data-priority="high">Record</th>
                <th class="pb-2 pr-4" data-priority="low">Submitted By</th>
                <th class="pb-2 pr-4" data-priority="low">Created</th>
                <th class="pb-2" data-priority="high">Actions</th>
            </tr>
            </thead>
            <tbody class="text-slate-700">
            <?php if ($pendingRecords): ?>
                <?php foreach ($pendingRecords as $record): ?>
                    <?php
                    $departmentKey = (string) $record['department_key'];
                    $recordId = (int) $record['id'];
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-4 font-semibold"><?= e(department_short_label($departmentKey)) ?></td>
                        <td class="py-2 pr-4">#<?= e((string) $recordId) ?> - <?= e((string) $record['primary_value']) ?></td>
                        <td class="py-2 pr-4"><?= e($record['submitted_name'] ?? '-') ?></td>
                        <td class="py-2 pr-4"><?= e(format_table_value('created_at', $record['created_at'] ?? null)) ?></td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="openModal('view-approval-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')" class="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50">View</button>
                                <button type="button" onclick="openModal('approve-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100">Approve</button>
                                <button type="button" onclick="openModal('reject-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')" class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100">Reject</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="py-4 text-center text-slate-500">No pending records. All departments are up-to-date.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($pendingRecords as $record): ?>
    <?php
    $departmentKey = (string) $record['department_key'];
    $recordId = (int) $record['id'];
    $departmentConfig = department_config($departmentKey) ?? [];
    $fields = $departmentConfig['fields'] ?? [];
    ?>

    <div id="view-approval-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'view-approval-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')">
        <div class="w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <h4 class="text-lg font-extrabold text-slate-900">Review <?= e(department_short_label($departmentKey)) ?> Record #<?= e((string) $recordId) ?></h4>
                <button type="button" onclick="closeModal('view-approval-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
            </div>

            <div class="mt-4 grid gap-2 text-sm">
                <?php foreach ($fields as $field): ?>
                    <?php
                    $name = $field['name'];
                    $value = $record[$name] ?? null;
                    if ($name === 'inventory_item_id') {
                        $value = $inventoryMap[(int) ($record[$name] ?? 0)] ?? '-';
                    } elseif ($name === 'ingredient_item_ids') {
                        $value = $formatIngredientSelection($record[$name] ?? null, $record);
                    }
                    ?>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500"><?= e($field['label']) ?></p>
                        <p class="font-semibold text-slate-800"><?= e(format_table_value($name, $value)) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php if ($departmentKey === 'sales'): ?>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Total Amount</p>
                        <p class="font-semibold text-slate-800"><?= e(format_money((float) ($record['total_amount'] ?? 0))) ?></p>
                    </div>
                <?php endif; ?>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Submitted By</p>
                    <p class="font-semibold text-slate-800"><?= e($record['submitted_name'] ?? '-') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div id="approve-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'approve-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')">
        <div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <h4 class="text-lg font-extrabold text-slate-900">Approve Record #<?= e((string) $recordId) ?></h4>
            <p class="mt-2 text-sm text-slate-600">Confirm manager review approval for this record.</p>

            <form method="post" action="handlers.php" class="mt-4 space-y-3">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="approve_record">
                <input type="hidden" name="dept" value="<?= e($departmentKey) ?>">
                <input type="hidden" name="id" value="<?= e((string) $recordId) ?>">
                <div>
                    <label class="block text-sm font-semibold text-slate-700">Approval Note (optional)</label>
                    <textarea name="approval_note" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('approve-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500">Approve</button>
                </div>
            </form>
        </div>
    </div>

    <div id="reject-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'reject-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')">
        <div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
            <h4 class="text-lg font-extrabold text-slate-900">Reject Record #<?= e((string) $recordId) ?></h4>
            <p class="mt-2 text-sm text-slate-600">Rejected data remains in the current department for correction and re-submission.</p>

            <form method="post" action="handlers.php" class="mt-4 space-y-3">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="reject_record">
                <input type="hidden" name="dept" value="<?= e($departmentKey) ?>">
                <input type="hidden" name="id" value="<?= e((string) $recordId) ?>">
                <div>
                    <label class="block text-sm font-semibold text-slate-700">Reason for Rejection</label>
                    <textarea name="approval_note" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeModal('reject-<?= e($departmentKey) ?>-<?= e((string) $recordId) ?>')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-500">Reject</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-lg font-extrabold text-slate-900">Recent Review History</h3>
    <div class="table-scroll mt-3">
        <table class="stack-table w-full min-w-[760px] text-sm">
            <thead>
            <tr class="text-left text-slate-500">
                <th class="pb-2 pr-4" data-priority="high">Department</th>
                <th class="pb-2 pr-4" data-priority="high">Record</th>
                <th class="pb-2 pr-4" data-priority="high">Decision</th>
                <th class="pb-2 pr-4" data-priority="low">Approver</th>
                <th class="pb-2" data-priority="low">Date</th>
            </tr>
            </thead>
            <tbody class="text-slate-700">
            <?php if ($recentLogs): ?>
                <?php foreach ($recentLogs as $log): ?>
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-4 font-semibold"><?= e(department_short_label((string) $log['module'])) ?></td>
                        <td class="py-2 pr-4">#<?= e((string) $log['record_id']) ?></td>
                        <td class="py-2 pr-4">
                            <span class="rounded-full px-2 py-1 text-xs font-bold <?= $log['action'] === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>"><?= e(strtoupper((string) $log['action'])) ?></span>
                        </td>
                        <td class="py-2 pr-4"><?= e($log['approver_name'] ?? '-') ?></td>
                        <td class="py-2 text-xs text-slate-500"><?= e(format_table_value('updated_at', $log['action_at'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="py-4 text-center text-slate-500">No approval history yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
