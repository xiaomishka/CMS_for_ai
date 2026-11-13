<?php
/**
 * Интеграционный тест: Database.php — install + migrate на SQLite в памяти.
 * Проверяет создание таблицы attachments (v0.3) и идемпотентность миграций.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/rb.php';
require_once __DIR__ . '/../../engine/Config.php';
require_once __DIR__ . '/../../engine/Database.php';
require_once __DIR__ . '/../../engine/Router.php';

\R::freeze(false);

$pass = 0;
$fail = 0;
$errors = [];

$check = function (bool $cond, string $msg) use (&$pass, &$fail, &$errors) {
    if ($cond) {
        $pass++;
    } else {
        $fail++;
        $errors[] = $msg;
    }
};

// install
try {
    Database::install(
        ['email' => 'admin@test.local', 'password' => 'secret123', 'name' => 'Admin'],
        [
            'site_name' => 'Test CMS',
            'site_title' => 'Test CMS',
            'site_description' => '',
            'per_page' => '10',
            'theme' => 'default',
            'url_scheme' => 'hierarchical',
            'active_plugins' => '',
        ],
        'hierarchical'
    );
    $check(true, 'Database::install succeeded');
} catch (\Throwable $e) {
    $check(false, 'Database::install failed: ' . $e->getMessage());
}

$check((int)\R::count('users') === 1, 'one user created');
$check((int)\R::count('articles') === 1, 'one demo article');
$check((int)\R::count('categories') === 1, 'one demo category');
$check((int)\R::count('settings') === 7, 'seven settings');

// migrate должен быть идемпотентным (таблицы уже есть)
try {
    Database::migrate();
    $check(true, 'Database::migrate idempotent after install');
} catch (\Throwable $e) {
    $check(false, 'Database::migrate failed after install: ' . $e->getMessage());
}

// таблица attachments должна существовать после миграции (v0.3)
try {
    $cnt = (int)\R::count('attachments');
    $check($cnt === 0, 'attachments table exists and is empty');
} catch (\Throwable $e) {
    $check(false, 'attachments table missing after migrate: ' . $e->getMessage());
}

// CRUD вложения
try {
    $bean = \R::dispense('attachments');
    $bean->filename = 'logo.png';
    $bean->original_name = 'Logo.png';
    $bean->mime_type = 'image/png';
    $bean->size = 5000;
    $bean->article_id = 0;
    $bean->url = '/uploads/attachments/logo.png';
    $bean->created_at = date('Y-m-d H:i:s');
    $id = (int)\R::store($bean);
    $check($id > 0, 'attachment stored with id');

    $found = \R::findOne('attachments', 'filename = ?', ['logo.png']);
    $check($found !== null && (string)$found->original_name === 'Logo.png', 'attachment retrieved');

    \R::trash($found);
    $check((int)\R::count('attachments') === 0, 'attachment deleted');
} catch (\Throwable $e) {
    $check(false, 'attachment CRUD failed: ' . $e->getMessage());
}

// freeze и повторная миграция — не должно падать
\R::freeze(true);
try {
    Database::migrate();
    $check(true, 'Database::migrate after freeze ok');
} catch (\Throwable $e) {
    $check(false, 'Database::migrate after freeze failed: ' . $e->getMessage());
}

return ['name' => 'Database', 'pass' => $pass, 'fail' => $fail, 'errors' => $errors];
