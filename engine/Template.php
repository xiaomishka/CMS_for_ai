<?php
/**
 * Template.php — теговый шаблонизатор (режим A, файлы *.tpl).
 *
 * Синтаксис:
 *   {{ path.to.var }}                    подстановка (авто-экранирование)
 *   {{ path.var|date:"d.m.Y" }}          модификаторы: date, truncate, upper, lower, raw
 *   {{ path|raw }}                       вывести без экранирования (HTML контента)
 *   [each articles as a] ... [/each]     цикл (внутри — {{ a.title }} ...)
 *   [each list as a] ... [else] ... [/each]  блок для пустого списка
 *   [if expr] ... [else] ... [/if]       условия:
 *        a.cover            — truthy
 *        not a.cover        — отрицание
 *        a.status == "x"    — сравнение ==, !=, >, <, >=, <=
 *        user.can("perm")   — права текущего пользователя
 *        user.logged        — гость/авторизован
 *   [include partial.sidebar]            вставка другого tpl (тот же контекст)
 *   [slot head]                          вывод плагинов (hook "template.slot:head")
 *   [# комментарий #]                    удаляется при компиляции
 *
 * Шаблон компилируется в PHP и кэшируется в core/cache/.
 */

declare(strict_types=1);

final class Template
{
    public static string $themeDir = '';
    public static string $cacheDir = '';

    /* ── Рендер ───────────────────────────────────────────── */

    public static function render(string $file, array $ctx): string
    {
        $src = self::$themeDir . '/' . basename($file) . '.tpl';
        if (!is_file($src)) {
            throw new RuntimeException('Template not found: ' . $file);
        }
        $cache = self::cachePath($src);
        if ($cache === null || !is_file($cache)) {
            $php = self::compile((string)file_get_contents($src));
            if ($cache !== null) {
                @mkdir(self::$cacheDir, 0775, true);
                file_put_contents($cache, "<?php\n" . $php, LOCK_EX);
            } else {
                return self::evalSource("<?php\n" . $php, $ctx);
            }
        }
        return self::evalSource('', $ctx, $cache);
    }

    private static function evalSource(string $code, array $ctx, ?string $cacheFile = null): string
    {
        ob_start();
        try {
            $ctx = \Plugins::filter('template.context', $ctx);
            if ($cacheFile !== null) {
                include $cacheFile;
            } else {
                eval($code);
            }
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }

    private static function cachePath(string $src): ?string
    {
        if (self::$cacheDir === '' || !is_dir(self::$cacheDir) && !@mkdir(self::$cacheDir, 0775, true)) {
            return null;
        }
        $mtime = (string)@filemtime($src);
        return self::$cacheDir . '/tpl_' . md5($src . '|' . $mtime) . '.php';
    }

    /* ── Компиляция ───────────────────────────────────────── */

    public static function compile(string $source): string
    {
        $tokens = self::tokenize($source);
        $root = (object)['t' => 'root', 'children' => [], 'else' => [], 'elseActive' => false];
        $stack = [$root];

        foreach ($tokens as $tk) {
            $top = $stack[count($stack) - 1];
            $container = $top->elseActive ? 'else' : 'children';
            switch ($tk[0]) {
                case 'html':
                    $top->{$container}[] = (object)['t' => 'html', 'v' => $tk[1]];
                    break;
                case 'var':
                    $top->{$container}[] = (object)['t' => 'var', 'expr' => $tk[1]];
                    break;
                case 'each':
                    $node = (object)['t' => 'each', 'list' => $tk[1], 'var' => $tk[2], 'children' => [], 'else' => [], 'elseActive' => false];
                    $top->{$container}[] = $node;
                    $stack[] = $node;
                    break;
                case 'if':
                    $node = (object)['t' => 'if', 'expr' => $tk[1], 'children' => [], 'else' => [], 'elseActive' => false];
                    $top->{$container}[] = $node;
                    $stack[] = $node;
                    break;
                case 'else':
                    if (!in_array($top->t, ['each', 'if'], true)) {
                        throw new RuntimeException('[else] вне блока each/if');
                    }
                    $top->elseActive = true;
                    break;
                case '/each':
                case '/if':
                    if ($top->t === 'root' || ($tk[0] === '/each' && $top->t !== 'each') || ($tk[0] === '/if' && $top->t !== 'if')) {
                        throw new RuntimeException('Незакрытый/лишний блок: ' . $tk[0]);
                    }
                    array_pop($stack);
                    break;
                case 'include':
                    $top->{$container}[] = (object)['t' => 'include', 'file' => $tk[1]];
                    break;
                case 'slot':
                    $top->{$container}[] = (object)['t' => 'slot', 'name' => $tk[1]];
                    break;
            }
        }
        if (count($stack) !== 1) {
            throw new RuntimeException('Не закрыт блок: ' . $stack[count($stack) - 1]->t);
        }
        return self::emitChildren($root->children);
    }

    private static function tokenize(string $src): array
    {
        $re = '/\{\{\s*([^}]+?)\s*\}\}'
            . '|\[each\s+([a-zA-Z_][\w.]*)\s+as\s+([a-zA-Z_][\w]*)\]'
            . '|\[\/each\]'
            . '|\[if\s+([^\]\n]+)\]'
            . '|\[else\]'
            . '|\[\/if\]'
            . '|\[include\s+([a-zA-Z0-9_.]+)\]'
            . '|\[slot\s+([a-zA-Z0-9_.]+)\]'
            . '|\[#.*?#\]/s';
        $tokens = [];
        $pos = 0;
        if (preg_match_all($re, $src, $m, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($m[0] as $i => $match) {
                [$text, $offset] = $match;
                if ($offset > $pos) {
                    $tokens[] = ['html', substr($src, $pos, $offset - $pos)];
                }
                $pos = $offset + strlen($text);
                if ($text[0] === '{') {
                    $tokens[] = ['var', trim($m[1][$i][0])];
                } elseif (str_starts_with($text, '[#')) {
                    continue; // комментарий
                } elseif (str_starts_with($text, '[each')) {
                    $tokens[] = ['each', $m[2][$i][0], $m[3][$i][0]];
                } elseif ($text === '[/each]') {
                    $tokens[] = ['/each'];
                } elseif (str_starts_with($text, '[if')) {
                    $tokens[] = ['if', trim($m[4][$i][0])];
                } elseif ($text === '[else]') {
                    $tokens[] = ['else'];
                } elseif ($text === '[/if]') {
                    $tokens[] = ['/if'];
                } elseif (str_starts_with($text, '[include')) {
                    $tokens[] = ['include', $m[5][$i][0]];
                } elseif (str_starts_with($text, '[slot')) {
                    $tokens[] = ['slot', $m[6][$i][0]];
                }
            }
        }
        if ($pos < strlen($src)) {
            $tokens[] = ['html', substr($src, $pos)];
        }
        return $tokens;
    }

    private static function emitChildren(array $children): string
    {
        $out = '';
        foreach ($children as $node) {
            switch ($node->t) {
                case 'html':
                    $out .= 'echo ' . var_export($node->v, true) . ";\n";
                    break;
                case 'var':
                    $out .= self::emitVar($node->expr) . "\n";
                    break;
                case 'include':
                    $out .= 'echo \\Template::render(' . var_export($node->file, true) . ', $ctx);' . "\n";
                    break;
                case 'slot':
                    $out .= 'echo (string)\\Plugins::filter(\'template.slot:\' . ' . var_export($node->name, true) . ", '');\n";
                    break;
                case 'each':
                    $code = '$__list = \\Template::val($ctx, ' . var_export($node->list, true) . ");\n";
                    $code .= 'if (is_array($__list) || $__list instanceof Traversable) {' . "\n";
                    $code .= "\t\$__n = 0;\n";
                    $code .= "\t\$__empty = true;\n";
                    $code .= "\tforeach (\$__list as \$__k => \$__item) {" . "\n";
                    $code .= "\t\t\$__empty = false;\n";
                    $code .= "\t\t\$ctx[" . var_export($node->var, true) . '] = is_array($__item) ? $__item : ($__item instanceof ArrayAccess ? $__item : null);' . "\n";
                    $code .= "\t\t\$__n++;\n";
                    $code .= "\t\t\$ctx['index'] = \$__n - 1;\n";
                    $code .= self::emitChildren($node->children);
                    $code .= "\t}\n";
                    $code .= "\tif (\$__empty) {" . "\n" . self::emitChildren($node->else) . "\n\t}\n";
                    $code .= "\tunset(\$ctx[" . var_export($node->var, true) . '], $ctx[\'index\']);' . "\n";
                    $code .= '} else {' . "\n" . self::emitChildren($node->else) . "}\n";
                    $out .= $code;
                    break;
                case 'if':
                    $code = 'if (' . self::emitCond($node->expr) . ") {\n" . self::emitChildren($node->children);
                    if ($node->else) {
                        $code .= "} else {\n" . self::emitChildren($node->else);
                    }
                    $code .= "}\n";
                    $out .= $code;
                    break;
            }
        }
        return $out;
    }

    /** {{ path|mod:"arg" }} → PHP. */
    private static function emitVar(string $expr): string
    {
        $parts = array_map('trim', explode('|', $expr));
        $path = array_shift($parts);
        if (!preg_match('/^[a-zA-Z_][\w.]*$/', $path)) {
            throw new RuntimeException('Плохой путь переменной: ' . $path);
        }
        $code = '$__v = \\Template::val($ctx, ' . var_export($path, true) . ");\n";
        $raw = false;
        foreach ($parts as $mod) {
            $arg = null;
            if (preg_match('/^([a-z]+)(?::(?:"([^"]*)"|\'([^\']*)\'|(\d+)))?$/', $mod, $mm)) {
                $mod = $mm[1];
                $arg = $mm[2] ?? $mm[3] ?? (isset($mm[4]) ? (int)$mm[4] : null);
            }
            if ($mod === 'raw') {
                $raw = true;
                continue;
            }
            $code .= '$__v = \\Template::m(' . var_export($mod, true) . ', $__v, ' . var_export($arg, true) . ");\n";
        }
        $code .= 'echo \\Template::out($__v, ' . var_export($raw, true) . ');';
        return $code;
    }

    /** [if ...] → PHP-условие. */
    private static function emitCond(string $expr): string
    {
        $e = trim($expr);
        if ($e === '') {
            throw new RuntimeException('Пустое условие [if]');
        }
        if (preg_match('/^not\s+(.+)$/i', $e, $m)) {
            return '!(' . self::emitCond(trim($m[1])) . ')';
        }
        if (preg_match('/^user\.can\(\s*"([^"]+)"\s*\)$/', $e, $m)) {
            return '\Auth::can(' . var_export($m[1], true) . ')';
        }
        if ($e === 'user.logged') {
            return '\Auth::check()';
        }
        if (preg_match('/^([a-zA-Z_][\w.]*)\s*(===?|!==?|>=|<=|>|<)\s*("[^"]*"|\'[^\']*\'|-?\d+)$/', $e, $m)) {
            return '(\\Template::val($ctx, ' . var_export($m[1], true) . ') ' . $m[2] . ' ' . $m[3] . ')';
        }
        if (preg_match('/^([a-zA-Z_][\w.]*)$/', $e)) {
            return '(\\Template::val($ctx, ' . var_export($e, true) . ') ? true : false)';
        }
        throw new RuntimeException('Неизвестное условие: ' . $expr);
    }

    /* ── Хелперы, доступные сгенерированному коду ─────────── */

    /** Доступ по пути 'a.title' из контекста. */
    public static function val(array $ctx, string $path): mixed
    {
        $node = $ctx;
        foreach (explode('.', $path) as $p) {
            if (is_array($node) && array_key_exists($p, $node)) {
                $node = $node[$p];
            } elseif (is_object($node)) {
                $node = $node->$p ?? null;
            } else {
                return null;
            }
        }
        return $node;
    }

    /** Вывод значения (экранирование по умолчанию). */
    public static function out(mixed $value, bool $raw = false): string
    {
        if ($raw) {
            return (string)$value;
        }
        return e($value === null ? '' : (is_scalar($value) ? (string)$value : ''));
    }

    /** Модификаторы. */
    public static function m(string $name, mixed $value, mixed $arg = null): mixed
    {
        switch ($name) {
            case 'date':
                $ts = is_numeric($value) ? (int)$value : (strtotime((string)$value) ?: time());
                return date((string)($arg ?: 'd.m.Y'), $ts);
            case 'truncate':
                $len = max(1, (int)($arg ?: 100));
                $s = trim((string)$value);
                return mb_strlen($s, 'UTF-8') > $len ? mb_substr($s, 0, $len, 'UTF-8') . '…' : $s;
            case 'upper':
                return mb_strtoupper((string)$value, 'UTF-8');
            case 'lower':
                return mb_strtolower((string)$value, 'UTF-8');
            case 'nl2br':
                return nl2br((string)$value);
            default:
                return $value;
        }
    }
}
