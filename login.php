<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
            redirect('dashboard.php');
        }

        $error = 'Invalid login credentials.';
    }
}

$tailwindCssFile = __DIR__ . '/assets/css/tailwind.css';
$tailwindCssVersion = is_file($tailwindCssFile) ? (string) filemtime($tailwindCssFile) : '1';
$fontCssFile = __DIR__ . '/assets/css/fonts.css';
$fontCssVersion = is_file($fontCssFile) ? (string) filemtime($fontCssFile) : '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/fonts.css?v=<?= e($fontCssVersion) ?>">
    <link rel="stylesheet" href="assets/css/tailwind.css?v=<?= e($tailwindCssVersion) ?>">
    <style>
        :root {
            --login-page-pad: 0.75rem;
            --login-shell-gap: 1rem;
            --login-card-pad: 1.25rem;
            --login-title-size: 1.7rem;
            --login-hero-orb: 14rem;
            --login-modal-pad: 1rem;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(circle at top left, #c7ece8 0, transparent 35%), radial-gradient(circle at right bottom, #fdddb6 0, transparent 35%), #ecf3f2;
            margin: 0;
            min-width: 320px;
        }

        .login-body {
            padding: var(--login-page-pad);
        }

        .login-shell {
            gap: var(--login-shell-gap);
        }

        .login-hero,
        .login-panel {
            padding: var(--login-card-pad) !important;
        }

        .login-panel h2 {
            font-size: var(--login-title-size);
            line-height: 1.2;
        }

        .login-hero-orb {
            width: var(--login-hero-orb) !important;
            height: var(--login-hero-orb) !important;
        }

        .login-modal-panel {
            padding: var(--login-modal-pad) !important;
        }

        @media (max-width: 359px) {
            :root {
                --login-page-pad: 0.55rem;
                --login-shell-gap: 0.7rem;
                --login-card-pad: 1rem;
                --login-title-size: 1.4rem;
                --login-hero-orb: 10.25rem;
                --login-modal-pad: 0.85rem;
            }
        }

        @media (min-width: 360px) and (max-width: 389px) {
            :root {
                --login-page-pad: 0.65rem;
                --login-shell-gap: 0.85rem;
                --login-card-pad: 1.1rem;
                --login-title-size: 1.5rem;
                --login-hero-orb: 11.5rem;
                --login-modal-pad: 0.9rem;
            }
        }

        @media (min-width: 390px) and (max-width: 429px) {
            :root {
                --login-page-pad: 0.75rem;
                --login-shell-gap: 1rem;
                --login-card-pad: 1.2rem;
                --login-title-size: 1.62rem;
                --login-hero-orb: 13rem;
            }
        }

        @media (min-width: 430px) and (max-width: 539px) {
            :root {
                --login-page-pad: 0.85rem;
                --login-shell-gap: 1.05rem;
                --login-card-pad: 1.3rem;
                --login-title-size: 1.72rem;
                --login-hero-orb: 13.75rem;
            }
        }

        @media (min-width: 540px) and (max-width: 767px) {
            :root {
                --login-page-pad: 1rem;
                --login-shell-gap: 1.25rem;
                --login-card-pad: 1.4rem;
                --login-title-size: 1.82rem;
                --login-hero-orb: 15rem;
            }
        }

        @media (min-width: 768px) and (max-width: 1023px) {
            :root {
                --login-page-pad: 1.15rem;
                --login-shell-gap: 1.35rem;
                --login-card-pad: 1.55rem;
                --login-title-size: 1.9rem;
                --login-hero-orb: 17rem;
                --login-modal-pad: 1.2rem;
            }
        }

        @media (min-width: 1024px) and (max-width: 1439px) {
            :root {
                --login-page-pad: 1.3rem;
                --login-shell-gap: 1.5rem;
                --login-card-pad: 1.7rem;
                --login-title-size: 2rem;
                --login-hero-orb: 18.5rem;
                --login-modal-pad: 1.25rem;
            }
        }

        @media (min-width: 1440px) {
            :root {
                --login-page-pad: 1.5rem;
                --login-shell-gap: 1.75rem;
                --login-card-pad: 1.9rem;
                --login-title-size: 2.15rem;
                --login-hero-orb: 20.5rem;
                --login-modal-pad: 1.35rem;
            }
        }
    </style>
</head>
<body class="login-body min-h-screen flex items-center justify-center">
    <div class="login-shell w-full max-w-5xl grid lg:grid-cols-2 items-stretch">
        <section class="login-hero rounded-3xl bg-slate-900 text-slate-100 shadow-2xl shadow-slate-800/20 flex flex-col items-center justify-center text-center">
            <p class="text-sm uppercase tracking-[0.24em] text-teal-200">Don Macchiatos</p>
            <div class="mt-6 flex items-center justify-center">
                <div class="login-hero-orb rounded-full bg-gradient-to-br from-teal-300 via-cyan-200 to-amber-200 p-2 shadow-2xl shadow-slate-950/40">
                    <div class="h-full w-full overflow-hidden rounded-full border-[6px] border-slate-900/50">
                        <img src="don.jpg" alt="Don Macchiatos branding" class="h-full w-full object-cover">
                    </div>
                </div>
            </div>
        </section>

        <section class="login-panel rounded-3xl border border-slate-200 bg-white/95 shadow-xl">
            <h2 class="text-2xl font-extrabold text-slate-900">Sign in</h2>
            <p class="mt-2 text-sm text-slate-500">Use your department account to create records and send them for approval.</p>
            <button type="button" id="openAccountsModal" class="mt-4 inline-flex items-center rounded-xl border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                View Default Accounts
            </button>

            <?php if ($error): ?>
                <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php foreach (consume_flashes() as $flash): ?>
                <div class="mt-4 rounded-xl border p-3 text-sm font-semibold <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endforeach; ?>

            <form method="post" class="mt-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700">Username</label>
                    <input type="text" name="username" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700">Password</label>
                    <input type="password" name="password" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100" required>
                </div>
                <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Login</button>
            </form>
        </section>
    </div>

    <div id="accountsModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" aria-hidden="true">
        <div id="accountsModalBackdrop" class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
        <div role="dialog" aria-modal="true" aria-labelledby="accountsModalTitle" class="login-modal-panel relative z-10 w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 id="accountsModalTitle" class="text-xl font-extrabold text-slate-900">Default Accounts</h3>
                    <p class="mt-1 text-sm text-slate-500">Use these demo credentials to log in per department.</p>
                </div>
                <button type="button" id="closeAccountsModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Close</button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <div class="rounded-lg bg-slate-100 p-3">GM: gm / password123</div>
                <div class="rounded-lg bg-slate-100 p-3">Inventory: inv_head / password123</div>
                <div class="rounded-lg bg-slate-100 p-3">Production: prod_head / password123</div>
                <div class="rounded-lg bg-slate-100 p-3">Sales: sales_head / password123</div>
                <div class="rounded-lg bg-slate-100 p-3">Accounting: acct_head / password123</div>
                <div class="rounded-lg bg-slate-100 p-3">CRM: crm_head / password123</div>
                <div class="rounded-lg bg-slate-100 p-3 sm:col-span-2">Marketing: mkt_head / password123</div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('accountsModal');
            const openButton = document.getElementById('openAccountsModal');
            const closeButton = document.getElementById('closeAccountsModal');

            if (!modal || !openButton || !closeButton) {
                return;
            }

            const openModal = () => {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            openButton.addEventListener('click', openModal);
            closeButton.addEventListener('click', closeModal);

            modal.addEventListener('click', (event) => {
                if (event.target === modal || event.target.id === 'accountsModalBackdrop') {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('flex')) {
                    closeModal();
                }
            });
        })();
    </script>
</body>
</html>
