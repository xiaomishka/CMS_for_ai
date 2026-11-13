<?php
/**
 * View.php — рендер страницы темы.
 * theme.json решает, каким движком рендерить: "tpl" (теговый) или "phtml" (PHP).
 * Схема: сначала рендерится внутренний шаблон (view.article и т.п.),
 * затем он вставляется в layout.tpl как {{ content|raw }}.
 */

declare(strict_types=1);

final class View
{
    private static ?array $theme = null;

    /** Метаданные темы. */
    public static function theme(): array
    {
        if (self::$theme !== null) {
            return self::$theme;
        }
        $dir = BASE_PATH . '/templates/' . basename(\Config::get('site.theme', 'default'));
        $meta = [];
        if (is_file($dir . '/theme.json')) {
            $meta = json_decode((string)file_get_contents($dir . '/theme.json'), true) ?: [];
        }
        $meta['engine'] = in_array($meta['engine'] ?? 'tpl', ['tpl', 'phtml'], true) ? $meta['engine'] : 'tpl';
        $meta['dir'] = $dir;
        return self::$theme = $meta;
    }

    /** Рендер внутреннего шаблона. */
    public static function render(string $tpl, array $ctx): string
    {
        \Plugins::emit('view.render', $tpl, $ctx);
        $theme = self::theme();
        if ($theme['engine'] === 'phtml') {
            return self::renderPhtml($tpl, $ctx, $theme);
        }
        Template::$themeDir = $theme['dir'];
        Template::$cacheDir = BASE_PATH . '/core/cache';
        return Template::render(basename($tpl), $ctx);
    }

    /** Полная страница: внутренний шаблон + layout. */
    public static function renderPage(string $tpl, array $ctx): string
    {
        $ctx['content'] = self::render($tpl, $ctx);
        $out = self::render('layout', $ctx);
        $out = \Plugins::filter('page.render', $out, $ctx);
        return $out;
    }

    /* ── Режим B: чистые PHP-шаблоны (для форков) ─────────── */

    private static function renderPhtml(string $tpl, array $ctx, array $theme): string
    {
        $file = $theme['dir'] . '/' . basename($tpl) . '.phtml';
        if (!is_file($file)) {
            throw new RuntimeException('PHTML not found: ' . $tpl);
        }
        $view = new PhtmlView($ctx);
        extract($ctx, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}

/**
 * Контекст phtml-шаблона: $view->e(...), $view->slot(...), $view->inc(...).
 */
final class PhtmlView
{
    public function __construct(private array $ctx) {}

    public function __get(string $name): mixed
    {
        return $this->ctx[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->ctx[$name]);
    }

    /** Экранирование (в phtml НЕ экранируем автоматически — экранирует сам шаблон). */
    public function e(mixed $v): string
    {
        return e($v === null ? '' : (is_scalar($v) ? (string)$v : ''));
    }

    /** Вывод без экранирования (HTML контента). */
    public function raw(mixed $v): string
    {
        return (string)$v;
    }

    /** Слот для плагинов. */
    public function slot(string $name): string
    {
        return (string)\Plugins::filter('template.slot:' . $name, '');
    }

    /** Включение другого шаблона той же темы. */
    public function inc(string $tpl): void
    {
        $theme = View::theme();
        $file = $theme['dir'] . '/' . basename($tpl) . '.phtml';
        if (is_file($file)) {
            $v = $this;
            extract($this->ctx, EXTR_SKIP);
            include $file;
        }
    }
}
