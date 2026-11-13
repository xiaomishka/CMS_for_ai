<?php
/**
 * index.php — единственная публичная точка входа.
 * Все URL ведут сюда (см. .htaccess), дальше — Router::dispatch().
 */

declare(strict_types=1);

require __DIR__ . '/engine/bootstrap.php';

// Встроенный сервер (php -S) не умеет .htaccess: реальные файлы отдаём как есть
if (PHP_SAPI === 'cli-server') {
    $req = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($req !== '/' && (is_file(__DIR__ . $req) || is_dir(__DIR__ . $req))) {
        return false;
    }
}

Router::dispatch(current_path());
