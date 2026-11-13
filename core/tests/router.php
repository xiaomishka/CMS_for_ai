<?php
/**
 * Тест: Router.php — match и defaults (статические методы, без БД).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../engine/Router.php';

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

// defaults
$defaults = Router::defaults('hierarchical');
$check(count($defaults) > 0, 'hierarchical defaults non-empty');
$foundHome = false;
foreach ($defaults as [$pattern, $target]) {
    if ($pattern === '/' && $target === 'home') {
        $foundHome = true;
    }
}
$check($foundHome, 'home route present in defaults');

$short = Router::defaults('short');
$check(count($short) > 0, 'short defaults non-empty');
$foundShortRoot = false;
foreach ($short as [$pattern, $target]) {
    if ($pattern === '/' && $target === 'home') {
        $foundShortRoot = true;
    }
}
$check($foundShortRoot, 'short scheme has home route');

// match
$check(Router::match('/', '/') !== null, 'match root path');

$m = Router::match('/{slug}.html', '/hello.html');
$check($m !== null && ($m['slug'] ?? null) === 'hello', 'match /{slug}.html');

$m = Router::match('/{cat}/{slug}.html', '/news/hello.html');
$check($m !== null && ($m['cat'] ?? null) === 'news' && ($m['slug'] ?? null) === 'hello', 'match /{cat}/{slug}.html');

$check(Router::match('/{slug}.html', '/page/2/') === null, '/{slug}.html does not match /page/2/');

$m = Router::match('/page/{n}/', '/page/3');
$check($m !== null && ($m['n'] ?? null) === '3', 'match /page/{n}/ captures number');

$check(Router::match('/news/', '/news') !== null, 'trailing slash is ignored');

$check(Router::match('/nonexistent/{x}', '/news') === null, 'different pattern does not match');

return ['name' => 'Router', 'pass' => $pass, 'fail' => $fail, 'errors' => $errors];
