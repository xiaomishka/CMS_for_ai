<?php
/**
 * Запуск всех тестов ядра (без composer, чистый PHP).
 * Использование: php core/tests/run.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/rb.php';

// Один экземпляр БД в памяти на все тесты
\R::setup('sqlite::memory:');
\R::freeze(false);

$tests = [
    'permissions' => __DIR__ . '/permissions.php',
    'helpers'     => __DIR__ . '/helpers.php',
    'router'      => __DIR__ . '/router.php',
    'template'    => __DIR__ . '/template.php',
    'database'    => __DIR__ . '/database.php',
];

$totalPass = 0;
$totalFail = 0;

foreach ($tests as $name => $file) {
    if (!is_file($file)) {
        echo sprintf("%-12s SKIP (file not found)\n", $name);
        continue;
    }
    /** @var array{name: string, pass: int, fail: int, errors: string[]} $result */
    $result = require $file;
    $totalPass += $result['pass'];
    $totalFail += $result['fail'];
    echo sprintf("%-12s pass=%d fail=%d\n", $result['name'], $result['pass'], $result['fail']);
    foreach ($result['errors'] as $err) {
        echo "      ERROR: $err\n";
    }
}

echo "\n=== TOTAL: pass=$totalPass fail=$totalFail ===\n";
exit($totalFail > 0 ? 1 : 0);
