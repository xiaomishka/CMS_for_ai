<?php
/**
 * Тест: Template.php — компиляция тегов .tpl (без файлов, через eval).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/rb.php';
require_once __DIR__ . '/../../engine/helpers.php';
require_once __DIR__ . '/../../engine/Plugins/Hub.php';
require_once __DIR__ . '/../../engine/Template.php';

Template::$themeDir = '';
Template::$cacheDir = ''; // пусто = без кэша в тестах

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

function evalTpl(string $source, array $ctx): string
{
    $php = \Template::compile($source);
    $tmp = tempnam(sys_get_temp_dir(), 'tpl_') . '.php';
    file_put_contents($tmp, '<?php ' . $php, LOCK_EX);
    ob_start();
    extract($ctx, EXTR_SKIP);
    include $tmp;
    $out = (string)ob_get_clean();
    @unlink($tmp);
    return $out;
}

// простая переменная
$check(evalTpl('Hello {{ name }}', ['name' => 'World']) === 'Hello World', 'simple variable');

// модификатор upper
$check(evalTpl('{{ title|upper }}', ['title' => 'hello']) === 'HELLO', 'upper modifier');

// модификатор date
$check(evalTpl('{{ d|date:"d.m.Y" }}', ['d' => '2026-11-13']) === '13.11.2026', 'date modifier');

// если
$check(evalTpl('[if show]Yes[/if]', ['show' => true]) === 'Yes', 'if true');
$check(evalTpl('[if show]Yes[/if]', ['show' => false]) === '', 'if false');
$check(evalTpl('[if show]Yes[else]No[/if]', ['show' => false]) === 'No', 'if else');

// цикл each (ассоциативные массивы, как статьи в реальности)
$check(evalTpl('[each items as i]{{ i.title }}[/each]', ['items' => [['title' => 'A'], ['title' => 'B']]]) === 'AB', 'each loop');
$check(evalTpl('[each items as i]{{ i.title }}[else]empty[/each]', ['items' => []]) === 'empty', 'each else empty');

// слот плагина
$check(evalTpl('[slot head]', []) === '', 'slot empty');

// raw (без экранирования)
$check(evalTpl('Raw: {{ html|raw }}', ['html' => '<b>x</b>']) === 'Raw: <b>x</b>', 'raw modifier');

// вложенная переменная через точку
$check(evalTpl('{{ a.b }}', ['a' => ['b' => 'deep']]) === 'deep', 'nested variable via dot');

return ['name' => 'Template', 'pass' => $pass, 'fail' => $fail, 'errors' => $errors];
