<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

logout_user();
set_flash('success', 'You have logged out.');
redirect('login.php');
