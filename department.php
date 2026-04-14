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
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
$allowedPerPage = [10, 25, 50, 100];
$statusOptions = ['all', 'pending', 'approved', 'rejected'];

if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}

if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = 'all';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

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

$allInventoryItems = [];
$approvedInventoryItems = [];
$inventoryMap = [];

if (in_array($department, ['production', 'sales'], true)) {
    $allInventoryItems = $pdo->query('SELECT id, item_name, stock_qty, unit, status FROM inventory_items ORDER BY item_name ASC')->fetchAll();
    foreach ($allInventoryItems as $item) {
        $inventoryMap[(int) $item['id']] = $item['item_name'] . ' (' . number_format((float) $item['stock_qty'], 2) . ' ' . $item['unit'] . ')';
        if (($item['status'] ?? '') === 'approved') {
            $approvedInventoryItems[] = $item;
        }
    }
}

$pageTitle = $config['title'];
$activePage = 'department_' . $department;
require_once __DIR__ . '/includes/layout_top.php';
?>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <form method="get" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <input type="hidden" name="dept" value="<?= e($department) ?>">

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
            <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Apply Filters</button>
                <a href="department.php?dept=<?= e($department) ?>" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                <button type="button" onclick="openModal('modal-create')" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Create</button>
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
                    $canManage = (($user['role'] ?? '') === ROLE_GENERAL_MANAGER || (int) ($row['submitted_by'] ?? 0) === (int) ($user['id'] ?? 0));
                    $isApproved = ($row['status'] ?? '') === 'approved';
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-4 font-bold">#<?= e((string) $rowId) ?></td>
                        <?php foreach ($config['list_columns'] as $column): ?>
                            <td class="py-2 pr-4">
                                <?php if ($column === 'status'): ?>
                                    <span class="rounded-full px-2 py-1 text-xs font-bold <?= e(status_badge_class((string) $row[$column])) ?>"><?= e(strtoupper((string) $row[$column])) ?></span>
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
                                <?php if ($canManage): ?>
                                    <button type="button" onclick="openModal('edit-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-lg border border-brand-300 bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700 hover:bg-brand-100 <?= $isApproved ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">Edit</button>
                                    <button type="button" onclick="openModal('delete-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100 <?= $isApproved ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">Delete</button>
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

<div id="modal-create" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'modal-create')">
    <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <h4 class="text-lg font-extrabold text-slate-900">Create <?= e($config['title']) ?> Record</h4>
            <button type="button" onclick="closeModal('modal-create')" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold text-slate-700">Close</button>
        </div>

        <form method="post" action="handlers.php" class="mt-4 grid gap-3 md:grid-cols-2">
            <input type="hidden" name="action" value="create_record">
            <input type="hidden" name="dept" value="<?= e($department) ?>">

            <?php foreach ($config['fields'] as $field): ?>
                <?php
                $fieldName = $field['name'];
                $fieldType = $field['type'];
                $required = (bool) ($field['required'] ?? false);
                $fieldClass = $fieldType === 'textarea' ? 'md:col-span-2' : '';
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
                    <?php elseif ($fieldType === 'inventory_select'): ?>
                        <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                            <option value="">Select inventory item</option>
                            <?php foreach ($approvedInventoryItems as $item): ?>
                                <option value="<?= e((string) $item['id']) ?>"><?= e($item['item_name']) ?> (<?= e(number_format((float) $item['stock_qty'], 2)) ?> <?= e($item['unit']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <?php
                        $inputType = $fieldType === 'number' ? 'number' : ($fieldType === 'date' ? 'date' : 'text');
                        $stepAttribute = $inputType === 'number' ? ' step="' . e((string) ($field['step'] ?? 'any')) . '"' : '';
                        ?>
                        <input type="<?= e($inputType) ?>" name="<?= e($fieldName) ?>"<?= $stepAttribute ?> class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($rows as $row): ?>
    <?php
    $rowId = (int) $row['id'];
    $canManage = (($user['role'] ?? '') === ROLE_GENERAL_MANAGER || (int) ($row['submitted_by'] ?? 0) === (int) ($user['id'] ?? 0));
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
                    }
                    ?>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500"><?= e($field['label']) ?></p>
                        <p class="font-semibold text-slate-800"><?= e(format_table_value($name, $value)) ?></p>
                    </div>
                <?php endforeach; ?>

                <?php if ($department === 'sales'): ?>
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
                    <input type="hidden" name="action" value="edit_record">
                    <input type="hidden" name="dept" value="<?= e($department) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $rowId) ?>">

                    <?php foreach ($config['fields'] as $field): ?>
                        <?php
                        $fieldName = $field['name'];
                        $fieldType = $field['type'];
                        $required = (bool) ($field['required'] ?? false);
                        $value = $row[$fieldName] ?? '';
                        $fieldClass = $fieldType === 'textarea' ? 'md:col-span-2' : '';
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
                            <?php elseif ($fieldType === 'inventory_select'): ?>
                                <select name="<?= e($fieldName) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                                    <option value="">Select inventory item</option>
                                    <?php foreach ($approvedInventoryItems as $item): ?>
                                        <option value="<?= e((string) $item['id']) ?>" <?= (int) $value === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['item_name']) ?> (<?= e(number_format((float) $item['stock_qty'], 2)) ?> <?= e($item['unit']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <?php
                                $inputType = $fieldType === 'number' ? 'number' : ($fieldType === 'date' ? 'date' : 'text');
                                $stepAttribute = $inputType === 'number' ? ' step="' . e((string) ($field['step'] ?? 'any')) . '"' : '';
                                ?>
                                <input type="<?= e($inputType) ?>" name="<?= e($fieldName) ?>" value="<?= e((string) $value) ?>"<?= $stepAttribute ?> class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" <?= $required ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800 <?= $isApproved ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">Save and Re-submit</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="delete-<?= e($department) ?>-<?= e((string) $rowId) ?>" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4" onclick="closeOnBackdrop(event, 'delete-<?= e($department) ?>-<?= e((string) $rowId) ?>')">
            <div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl sm:p-5">
                <h4 class="text-lg font-extrabold text-slate-900">Delete Record #<?= e((string) $rowId) ?></h4>
                <p class="mt-2 text-sm text-slate-600">This action cannot be undone. Approved records are locked and cannot be deleted.</p>

                <form method="post" action="handlers.php" class="mt-4 flex justify-end gap-2">
                    <input type="hidden" name="action" value="delete_record">
                    <input type="hidden" name="dept" value="<?= e($department) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $rowId) ?>">
                    <button type="button" onclick="closeModal('delete-<?= e($department) ?>-<?= e((string) $rowId) ?>')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-500 <?= $isApproved ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">Delete</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
