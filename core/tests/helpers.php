<?php
/**
 * Тест: helpers.php — чистые функции ядра (slug, url, экранирование).
 * Требует только core/rb.php (для settings() catch) — БД в памяти.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/rb.php';
require_once __DIR__ . '/../../engine/helpers.php';
require_once __DIR__ . '/../../engine/Config.php';

\R::freeze(true);

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

// translit (транслитерация букв, пробелы остаются)
$check(translit('Привет') === 'privet', 'translit Russian');
$check(translit('Ёжик') === 'yozhik', 'translit yo');
$check(translit('') === '', 'translit empty');
$check(translit('ABC') === 'abc', 'translit lowercases');

// slugify
$check(slugify('Добро пожаловать') === 'dobro-pozhalovat', 'slugify Russian');
$check(slugify('Hello World!!!') === 'hello-world', 'slugify English');
$check(slugify('   ') === '', 'slugify spaces becomes empty');
$check(slugify('Привет, мир! 123') === 'privet-mir-123', 'slugify mixed');

// e (html escaping)
$check(e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;', 'e escapes HTML');
$check(e(null) === '', 'e null returns empty');
$check(e('') === '', 'e empty returns empty');
$check(e('already safe') === 'already safe', 'e passes safe text');

// url_page
$check(url_page('about') === '/page/about.html', 'url_page');
$check(url_page('contacts-us') === '/page/contacts-us.html', 'url_page with hyphen');

// url_article (схема hierarchical по умолчанию, т.к. нет настроек)
$check(url_article(null, 'standalone') === '/standalone.html', 'url_article without category');
$check(url_article('news', 'dobro-pozhalovat') === '/news/dobro-pozhalovat.html', 'url_article with category');

// url_category
$check(url_category('news') === '/news/', 'url_category');
$check(url_category('news', 2) === '/news/page/2/', 'url_category paged');

return ['name' => 'Helpers', 'pass' => $pass, 'fail' => $fail, 'errors' => $errors];
