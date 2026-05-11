<?php
declare(strict_types=1);

$handlersFile = __DIR__ . '/../handlers.php';
if (!is_file($handlersFile)) {
    throw new RuntimeException('handlers.php must exist.');
}

$handlersSource = (string) file_get_contents($handlersFile);

if (strpos($handlersSource, 'Not enough production stock') !== false) {
    throw new RuntimeException('Sales creation must not be blocked by same-day production stock.');
}

if (strpos($handlersSource, 'Please log production first before recording this sale.') !== false) {
    throw new RuntimeException('Sales workflow must not require a production log before recording a sale.');
}

echo "Sales workflow rule tests passed.\n";
