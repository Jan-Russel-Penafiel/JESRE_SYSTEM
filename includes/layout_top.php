<?php
declare(strict_types=1);

if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}

if (!isset($activePage)) {
    $activePage = 'dashboard';
}

if (!isset($pageTitleActionHtml)) {
    $pageTitleActionHtml = '';
}

$user = current_user();

$navClass = static function (bool $active): string {
    if ($active) {
        return 'bg-white/20 text-white border border-white/20';
    }

    return 'text-slate-200 border border-transparent hover:bg-white/10 hover:border-white/20';
};

$tailwindCssFile = __DIR__ . '/../assets/css/tailwind.css';
$tailwindCssVersion = is_file($tailwindCssFile) ? (string) filemtime($tailwindCssFile) : '1';
$fontCssFile = __DIR__ . '/../assets/css/fonts.css';
$fontCssVersion = is_file($fontCssFile) ? (string) filemtime($fontCssFile) : '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=<?= e($fontCssVersion) ?>">
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?= e($tailwindCssVersion) ?>">
    <script>
        function openModal(id) {
            const element = document.getElementById(id);
            if (!element) {
                return;
            }
            element.classList.remove('hidden');
            element.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal(id) {
            const element = document.getElementById(id);
            if (!element) {
                return;
            }
            element.classList.add('hidden');
            element.classList.remove('flex');
            if (document.querySelectorAll('.fixed[id^="modal-"]:not(.hidden), .fixed[id^="view-"]:not(.hidden), .fixed[id^="edit-"]:not(.hidden), .fixed[id^="delete-"]:not(.hidden), .fixed[id^="approve-"]:not(.hidden), .fixed[id^="reject-"]:not(.hidden)').length === 0) {
                document.body.classList.remove('overflow-hidden');
            }
        }

        function closeOnBackdrop(event, id) {
            if (event.target.id === id) {
                closeModal(id);
            }
        }

        function resolveStackPriority(label, index, total, explicitPriority) {
            const configured = (explicitPriority || '').toLowerCase();
            if (configured === 'high' || configured === 'medium' || configured === 'low') {
                return configured;
            }

            const text = (label || '').toLowerCase();
            if (text === '#' || text === 'actions' || text === 'details') {
                return 'high';
            }

            if (
                text.indexOf('note') !== -1 ||
                text.indexOf('updated') !== -1 ||
                text.indexOf('date') !== -1 ||
                text.indexOf('submitted') !== -1 ||
                text.indexOf('approved by') !== -1 ||
                text === 'by'
            ) {
                return 'low';
            }

            if (index <= 1) {
                return 'high';
            }

            if (index >= total - 2) {
                return 'low';
            }

            return 'medium';
        }

        function syncStackRowToggle(row) {
            const existingToggle = row.querySelector('.stack-row-toggle-wrap');
            if (existingToggle) {
                existingToggle.remove();
            }

            row.classList.remove('has-collapsible');
            row.classList.remove('stack-expanded');

            const lowPriorityCells = Array.from(row.children).filter(function (cell) {
                return cell.tagName === 'TD' && cell.getAttribute('data-priority') === 'low';
            });

            if (!lowPriorityCells.length) {
                return;
            }

            row.classList.add('has-collapsible');

            const toggleWrap = document.createElement('td');
            toggleWrap.className = 'stack-row-toggle-wrap';
            toggleWrap.colSpan = Math.max(1, row.children.length);

            const toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            toggleButton.className = 'stack-row-toggle';
            toggleButton.setAttribute('aria-expanded', 'false');
            toggleButton.textContent = 'Show more';

            toggleButton.addEventListener('click', function () {
                const expanded = row.classList.toggle('stack-expanded');
                toggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                toggleButton.textContent = expanded ? 'Show less' : 'Show more';
            });

            toggleWrap.appendChild(toggleButton);
            row.appendChild(toggleWrap);
        }

        function applyStackTableLabels(table) {
            const headerCells = Array.from(table.querySelectorAll('thead th'));
            if (!headerCells.length) {
                return;
            }

            const headers = headerCells.map(function (cell, index) {
                const label = (cell.textContent || '').trim();
                return {
                    label: label !== '' ? label : ('Column ' + (index + 1)),
                    priority: resolveStackPriority(label, index, headerCells.length, cell.getAttribute('data-priority'))
                };
            });

            table.querySelectorAll('tbody tr').forEach(function (row) {
                Array.from(row.children).forEach(function (cell, index) {
                    if (cell.tagName !== 'TD') {
                        return;
                    }

                    const header = headers[index] || {
                        label: 'Column ' + (index + 1),
                        priority: resolveStackPriority('', index, headers.length, '')
                    };

                    cell.setAttribute('data-label', header.label);
                    cell.setAttribute('data-priority', header.priority);
                });

                syncStackRowToggle(row);
            });
        }

        function syncIngredientSelectAllState(groupId) {
            const selectAll = document.querySelector('[data-select-all="' + groupId + '"]');
            if (!selectAll) {
                return;
            }

            const items = Array.from(document.querySelectorAll('[data-select-item="' + groupId + '"]'));
            if (!items.length) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
                return;
            }

            const checkedCount = items.filter(function (item) { return item.checked; }).length;
            selectAll.checked = checkedCount > 0 && checkedCount === items.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < items.length;
        }

        function initializeIngredientSelectionControls() {
            document.querySelectorAll('[data-select-all]').forEach(function (toggle) {
                if (toggle.dataset.selectAllInitialized === '1') {
                    return;
                }

                const groupId = toggle.getAttribute('data-select-all') || '';
                if (groupId === '') {
                    return;
                }

                toggle.dataset.selectAllInitialized = '1';

                toggle.addEventListener('change', function () {
                    document.querySelectorAll('[data-select-item="' + groupId + '"]').forEach(function (checkbox) {
                        checkbox.checked = toggle.checked;
                    });

                    syncIngredientSelectAllState(groupId);
                });

                document.querySelectorAll('[data-select-item="' + groupId + '"]').forEach(function (checkbox) {
                    if (checkbox.dataset.selectItemInitialized === '1') {
                        return;
                    }

                    checkbox.dataset.selectItemInitialized = '1';
                    checkbox.addEventListener('change', function () {
                        syncIngredientSelectAllState(groupId);
                    });
                });

                syncIngredientSelectAllState(groupId);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('table.stack-table').forEach(function (table) {
                applyStackTableLabels(table);
            });

            initializeIngredientSelectionControls();
        });

        window.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            document.querySelectorAll('.fixed.flex[id]').forEach(function (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            });
            document.body.classList.remove('overflow-hidden');
        });
    </script>
    <style>
        :root {
            --canvas: #eef2f2;
            --text-main: #0f172a;

            --app-sidebar-md: 13.5rem;
            --app-sidebar-lg: 14.5rem;

            --app-content-x: 0.75rem;
            --app-content-y: 0.75rem;
            --app-card-pad: 1rem;
            --app-title-size: 1.35rem;
            --app-title-line: 1.25;

            --mobile-topbar-x: 0.75rem;
            --mobile-topbar-y: 0.75rem;
            --mobile-chip-font: 0.72rem;
            --mobile-chip-pad-x: 0.75rem;
            --mobile-chip-pad-y: 0.25rem;

            --table-scroll-edge: -0.25rem;
            --table-scroll-pad: 0.25rem;

            --stack-row-gap: 0.75rem;
            --stack-card-pad-y: 0.25rem;
            --stack-card-pad-x: 0.75rem;
            --stack-cell-font: 0.83rem;
            --stack-label-font: 0.64rem;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            font-size: 14px;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(circle at 10% 10%, #d8eceb 0%, transparent 30%), radial-gradient(circle at 90% 90%, #fde8cf 0%, transparent 35%), var(--canvas);
            color: var(--text-main);
            margin: 0;
            min-width: 320px;
            overflow-x: hidden;
        }

        .app-sidebar {
            width: 100%;
        }

        .app-main {
            min-width: 0;
        }

        @media (min-width: 768px) {
            .app-sidebar {
                width: var(--app-sidebar-md);
            }

            .app-main {
                margin-left: var(--app-sidebar-md);
            }
        }

        @media (min-width: 1024px) {
            .app-sidebar {
                width: var(--app-sidebar-lg);
            }

            .app-main {
                margin-left: var(--app-sidebar-lg);
            }
        }

        .app-content-wrap {
            padding: var(--app-content-y) var(--app-content-x);
        }

        .app-card-pad {
            padding: var(--app-card-pad) !important;
        }

        .app-page-title-wrap {
            margin: 0 0 0.9rem;
            padding: 0.1rem 0.15rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .app-page-title-main {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 0;
        }

        .app-page-title {
            font-size: 18px;
            line-height: 1.2;
            font-weight: 800;
            font-style: italic;
            letter-spacing: 0.01em;
            margin: 0;
        }

        .app-page-title-accent {
            display: inline-block;
            width: 2.8rem;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, #0f172a 0%, #94a3b8 100%);
            opacity: 0.75;
        }

        .app-page-title-action {
            margin-left: auto;
        }

        .app-title-action-btn {
            white-space: nowrap;
        }

        .app-mobile-topbar {
            padding: var(--mobile-topbar-y) var(--mobile-topbar-x);
        }

        .app-mobile-nav-row > a {
            font-size: var(--mobile-chip-font) !important;
            line-height: 1.2;
            padding: var(--mobile-chip-pad-y) var(--mobile-chip-pad-x) !important;
        }

        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        .table-scroll table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .stack-row-toggle-wrap {
            display: none;
        }

        @media (max-width: 639px) {
            .app-page-title-wrap {
                margin-bottom: 0.75rem;
                gap: 0.5rem;
            }

            .app-page-title-accent {
                width: 2.2rem;
            }

            .app-page-title-action {
                width: 100%;
                margin-left: 0;
            }

            .app-title-action-btn {
                width: 100%;
                text-align: center;
            }

            .table-scroll {
                overflow-x: visible;
                margin-inline: var(--table-scroll-edge);
                padding-inline: var(--table-scroll-pad);
            }

            .stack-table {
                min-width: 100% !important;
            }

            .stack-table thead {
                display: none;
            }

            .stack-table,
            .stack-table tbody,
            .stack-table tr,
            .stack-table td {
                display: block;
                width: 100%;
            }

            .stack-table tbody {
                display: grid;
                gap: var(--stack-row-gap);
            }

            .stack-table tr {
                border: 1px solid #e2e8f0;
                border-radius: 0.85rem;
                background: #ffffff;
                padding: var(--stack-card-pad-y) var(--stack-card-pad-x);
                box-shadow: 0 1px 1px rgba(15, 23, 42, 0.03);
            }

            .stack-table td {
                border: 0 !important;
                padding: 0.45rem 0 !important;
                font-size: var(--stack-cell-font);
                line-height: 1.35;
            }

            .stack-table td::before {
                content: attr(data-label);
                display: block;
                margin-bottom: 0.15rem;
                font-size: var(--stack-label-font);
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: #64748b;
            }

            .stack-table td[data-label="Actions"]::before {
                margin-bottom: 0.35rem;
            }

            .stack-table td[data-label="Actions"] > div {
                justify-content: flex-start;
            }

            .stack-table td[data-label="#"] {
                padding-top: 0.6rem !important;
                font-weight: 800;
                color: #0f172a;
            }

            .stack-table td[data-label="#"]::before {
                display: none;
            }
        }

        @media (max-width: 420px) {
            .stack-table tr.has-collapsible td[data-priority="low"] {
                display: none;
            }

            .stack-table tr.has-collapsible.stack-expanded td[data-priority="low"] {
                display: block;
            }

            .stack-table tr.has-collapsible .stack-row-toggle-wrap {
                display: block;
                margin-top: 0.15rem;
                padding: 0.45rem 0 0.25rem;
                border-top: 1px dashed #e2e8f0;
            }

            .stack-row-toggle {
                border: 1px solid #cbd5e1;
                border-radius: 9999px;
                background: #f8fafc;
                color: #334155;
                font-size: 0.68rem;
                font-weight: 700;
                letter-spacing: 0.03em;
                text-transform: uppercase;
                padding: 0.25rem 0.6rem;
            }
        }

        @media (max-width: 359px) {
            :root {
                --app-content-x: 0.55rem;
                --app-content-y: 0.55rem;
                --app-card-pad: 0.8rem;
                --app-title-size: 1.15rem;

                --mobile-topbar-x: 0.55rem;
                --mobile-topbar-y: 0.55rem;
                --mobile-chip-font: 0.66rem;
                --mobile-chip-pad-x: 0.6rem;
                --mobile-chip-pad-y: 0.2rem;

                --table-scroll-edge: -0.125rem;
                --table-scroll-pad: 0.125rem;

                --stack-row-gap: 0.55rem;
                --stack-card-pad-y: 0.2rem;
                --stack-card-pad-x: 0.55rem;
                --stack-cell-font: 0.76rem;
                --stack-label-font: 0.6rem;
            }
        }

        @media (min-width: 360px) and (max-width: 389px) {
            :root {
                --app-content-x: 0.65rem;
                --app-content-y: 0.65rem;
                --app-card-pad: 0.9rem;
                --app-title-size: 1.22rem;

                --mobile-topbar-x: 0.65rem;
                --mobile-topbar-y: 0.65rem;
                --mobile-chip-font: 0.69rem;
                --mobile-chip-pad-x: 0.64rem;
                --mobile-chip-pad-y: 0.22rem;

                --table-scroll-edge: -0.125rem;
                --table-scroll-pad: 0.125rem;

                --stack-row-gap: 0.6rem;
                --stack-card-pad-y: 0.22rem;
                --stack-card-pad-x: 0.62rem;
                --stack-cell-font: 0.78rem;
                --stack-label-font: 0.61rem;
            }
        }

        @media (min-width: 390px) and (max-width: 429px) {
            :root {
                --app-content-x: 0.72rem;
                --app-content-y: 0.72rem;
                --app-card-pad: 0.96rem;
                --app-title-size: 1.3rem;

                --mobile-topbar-x: 0.72rem;
                --mobile-topbar-y: 0.72rem;
                --mobile-chip-font: 0.71rem;
                --mobile-chip-pad-x: 0.7rem;
                --mobile-chip-pad-y: 0.24rem;
            }
        }

        @media (min-width: 430px) and (max-width: 539px) {
            :root {
                --app-content-x: 0.82rem;
                --app-content-y: 0.82rem;
                --app-card-pad: 1.02rem;
                --app-title-size: 1.36rem;

                --mobile-topbar-x: 0.82rem;
                --mobile-topbar-y: 0.78rem;
                --mobile-chip-font: 0.73rem;
                --mobile-chip-pad-x: 0.74rem;
                --mobile-chip-pad-y: 0.26rem;
            }
        }

        @media (min-width: 540px) and (max-width: 767px) {
            :root {
                --app-content-x: 0.95rem;
                --app-content-y: 0.9rem;
                --app-card-pad: 1.1rem;
                --app-title-size: 1.44rem;

                --mobile-topbar-x: 0.95rem;
                --mobile-topbar-y: 0.85rem;
                --mobile-chip-font: 0.74rem;
                --mobile-chip-pad-x: 0.78rem;
                --mobile-chip-pad-y: 0.28rem;
            }
        }

        @media (min-width: 768px) and (max-width: 819px) {
            :root {
                --app-content-x: 1.1rem;
                --app-content-y: 1rem;
                --app-card-pad: 1.15rem;
                --app-title-size: 1.52rem;
            }

            .table-scroll table {
                font-size: 0.9rem;
            }
        }

        @media (min-width: 820px) and (max-width: 1023px) {
            :root {
                --app-content-x: 1.2rem;
                --app-content-y: 1.05rem;
                --app-card-pad: 1.2rem;
                --app-title-size: 1.6rem;
            }

            .table-scroll table {
                font-size: 0.92rem;
            }
        }

        @media (min-width: 1024px) and (max-width: 1279px) {
            :root {
                --app-content-x: 1.4rem;
                --app-content-y: 1.2rem;
                --app-card-pad: 1.22rem;
                --app-title-size: 1.72rem;
            }
        }

        @media (min-width: 1280px) and (max-width: 1439px) {
            :root {
                --app-content-x: 1.65rem;
                --app-content-y: 1.35rem;
                --app-card-pad: 1.28rem;
                --app-title-size: 1.85rem;
            }
        }

        @media (min-width: 1440px) and (max-width: 1919px) {
            :root {
                --app-content-x: 1.85rem;
                --app-content-y: 1.55rem;
                --app-card-pad: 1.35rem;
                --app-title-size: 1.95rem;
            }
        }

        @media (min-width: 1920px) and (max-width: 2559px) {
            :root {
                --app-content-x: 2.3rem;
                --app-content-y: 1.9rem;
                --app-card-pad: 1.45rem;
                --app-title-size: 2.1rem;
            }
        }

        @media (min-width: 2560px) and (max-width: 3839px) {
            :root {
                --app-content-x: 2.9rem;
                --app-content-y: 2.25rem;
                --app-card-pad: 1.6rem;
                --app-title-size: 2.35rem;
            }
        }

        @media (min-width: 3840px) {
            :root {
                --app-content-x: 3.4rem;
                --app-content-y: 2.65rem;
                --app-card-pad: 1.75rem;
                --app-title-size: 2.55rem;
            }
        }
    </style>
</head>
<body>
<div class="min-h-screen flex w-full overflow-x-hidden">
    <aside class="app-sidebar hidden md:flex md:fixed md:inset-y-0 md:left-0 md:flex-col md:overflow-y-auto bg-slate-900 text-slate-100 p-4 lg:p-5">
        <div>
            <div class="mb-5 flex items-center gap-2 px-1">
                <img src="don.jpg" alt="DM Hub branding" class="h-8 w-8 rounded-full border-2 border-white/70 ring-2 ring-white/20 object-cover shadow">
                <h1 class="overflow-hidden text-ellipsis whitespace-nowrap text-base font-extrabold tracking-tight text-white"><?= e(APP_NAME) ?></h1>
            </div>
            <nav class="space-y-2">
                <a href="dashboard.php" class="block rounded-xl px-3 py-2 text-sm font-semibold <?= $navClass($activePage === 'dashboard') ?>">Dashboard</a>

                <?php foreach (departments() as $departmentKey => $departmentName): ?>
                    <?php if (can_user_access_department($user ?? [], $departmentKey)): ?>
                        <a href="department.php?dept=<?= e($departmentKey) ?>" class="block rounded-xl px-3 py-2 text-sm font-semibold <?= $navClass($activePage === 'department_' . $departmentKey) ?>"><?= e($departmentName) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (($user['role'] ?? '') === ROLE_GENERAL_MANAGER): ?>
                    <a href="approvals.php" class="block rounded-xl px-3 py-2 text-sm font-semibold <?= $navClass($activePage === 'approvals') ?>">Review Queue</a>
                    <a href="reports.php" class="block rounded-xl px-3 py-2 text-sm font-semibold <?= $navClass($activePage === 'reports') ?>">Summary Reports</a>
                    <a href="audit_logs.php" class="block rounded-xl px-3 py-2 text-sm font-semibold <?= $navClass($activePage === 'audit_logs') ?>">Audit Logs</a>
                <?php endif; ?>

                <a href="logout.php" class="mt-3 block rounded-xl border border-rose-300/30 bg-rose-500 px-3 py-2 text-sm font-bold text-white hover:bg-rose-400">Log out</a>
            </nav>
        </div>
    </aside>

    <main class="app-main flex-1">
        <div class="app-mobile-topbar md:hidden sticky top-0 z-40 border-b border-slate-200 bg-white/85 backdrop-blur">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <img src="don.jpg" alt="DM Hub branding" class="h-7 w-7 rounded-full border-2 border-slate-200 ring-2 ring-white/80 object-cover">
                    <h2 class="text-sm font-extrabold text-slate-900"><?= e(APP_NAME) ?></h2>
                </div>
                <a href="logout.php" class="rounded-lg bg-rose-500 px-3 py-1.5 text-xs font-bold text-white hover:bg-rose-400">Log out</a>
            </div>
            <div class="mt-3 overflow-x-auto">
                <div class="app-mobile-nav-row flex w-max gap-2 pb-1 pr-2">
                    <a href="dashboard.php" class="rounded-full border px-3 py-1 text-xs font-semibold <?= $activePage === 'dashboard' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300' ?>">Dashboard</a>
                    <?php foreach (departments() as $departmentKey => $departmentName): ?>
                        <?php if (can_user_access_department($user ?? [], $departmentKey)): ?>
                            <a href="department.php?dept=<?= e($departmentKey) ?>" class="rounded-full border px-3 py-1 text-xs font-semibold <?= $activePage === 'department_' . $departmentKey ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300' ?>"><?= e($departmentName) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (($user['role'] ?? '') === ROLE_GENERAL_MANAGER): ?>
                        <a href="approvals.php" class="rounded-full border px-3 py-1 text-xs font-semibold <?= $activePage === 'approvals' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300' ?>">Review</a>
                        <a href="reports.php" class="rounded-full border px-3 py-1 text-xs font-semibold <?= $activePage === 'reports' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300' ?>">Reports</a>
                        <a href="audit_logs.php" class="rounded-full border px-3 py-1 text-xs font-semibold <?= $activePage === 'audit_logs' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300' ?>">Audit</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="app-content-wrap">
            <header class="app-page-title-wrap" role="banner">
                <div class="app-page-title-main">
                    <h2 class="app-page-title text-slate-900"><?= e($pageTitle) ?></h2>
                    <span class="app-page-title-accent" aria-hidden="true"></span>
                </div>
                <?php if ($pageTitleActionHtml !== ''): ?>
                    <div class="app-page-title-action"><?= $pageTitleActionHtml ?></div>
                <?php endif; ?>
            </header>

            <?php foreach (consume_flashes() as $flash): ?>
                <div class="mb-4 rounded-xl border px-4 py-3 text-sm font-semibold <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endforeach; ?>
