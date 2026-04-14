<?php
declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'role' => $user['role'],
        'department' => $user['department'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_general_manager(): void
{
    require_login();

    $user = current_user();
    if (($user['role'] ?? null) !== ROLE_GENERAL_MANAGER) {
        set_flash('error', 'General Manager access only.');
        redirect('dashboard.php');
    }
}

function require_department_access(string $department): void
{
    require_login();

    $user = current_user();
    if ($user === null || !can_user_access_department($user, $department)) {
        set_flash('error', 'You are not authorized for this department.');
        redirect('dashboard.php');
    }
}
