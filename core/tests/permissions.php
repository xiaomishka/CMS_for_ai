<?php
/**
 * Тест: Permissions.php — матрица прав ролей.
 * Не требует БД.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../engine/Permissions.php';

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

$check(Permissions::can('admin', 'article.create') === true, 'admin can article.create');
$check(Permissions::can('editor', 'article.create') === true, 'editor can article.create');
$check(Permissions::can('author', 'article.create') === true, 'author can article.create');
$check(Permissions::can('author', 'article.publish') === false, 'author cannot article.publish');
$check(Permissions::can('editor', 'article.edit.any') === true, 'editor can article.edit.any');
$check(Permissions::can('author', 'article.edit.any') === false, 'author cannot article.edit.any');
$check(Permissions::can('editor', 'media.manage') === true, 'editor can media.manage (v0.3)');
$check(Permissions::can('author', 'media.manage') === false, 'author cannot media.manage');
$check(Permissions::can('admin', 'plugins.manage') === true, 'admin can plugins.manage (v0.3)');
$check(Permissions::can(null, 'article.create') === false, 'null role returns false');
$check(Permissions::can('admin', 'nonexistent.perm') === false, 'unknown permission returns false');
$check(in_array('admin', Permissions::roles(), true), 'roles contain admin');
$check(in_array('editor', Permissions::roles(), true), 'roles contain editor');
$check(in_array('author', Permissions::roles(), true), 'roles contain author');

return ['name' => 'Permissions', 'pass' => $pass, 'fail' => $fail, 'errors' => $errors];
