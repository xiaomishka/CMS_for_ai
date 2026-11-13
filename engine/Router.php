<?php
/**
 * Router.php — маршрутизация.
 *
 * Роуты живут в БД (таблица routes). Это позволяет:
 *  - менять схему ЧПУ (пресеты перезаписывают дефолтные роуты, priority < 100);
 *  - добавлять кастомные правила (priority >= 100) — например, восстанавливать
 *    иерархию сайта из вебархива;
 *  - держать 301-редиректы старых адресов в той же системе.
 *
 * Плейсхолдеры: {slug}, {cat} — любой сегмент, {n} — число.
 */

declare(strict_types=1);

final class Router
{
    /** Дефолтные роуты для пресетов схем ЧПУ. */
    public static function defaults(string $scheme): array
    {
        switch ($scheme) {
            case 'short':
                return [
                    ['/', 'home'],
                    ['/{slug}.html', 'article.view'],
                    ['/page/{n}/', 'home'],
                    ['/page/{slug}.html', 'page.view'],
                    ['/category/{cat}/', 'category.view'],
                    ['/category/{cat}/page/{n}/', 'category.view'],
                    ['/rss.xml', 'feed.rss'],
                    ['/sitemap.xml', 'feed.sitemap'],
                ];
            case 'clean':
                return [
                    ['/', 'home'],
                    ['/{cat}/{slug}/', 'article.view'],
                    ['/{cat}/', 'category.view'],
                    ['/{cat}/page/{n}/', 'category.view'],
                    ['/page/{n}/', 'home'],
                    ['/page/{slug}/', 'page.view'],
                    ['/rss.xml', 'feed.rss'],
                    ['/sitemap.xml', 'feed.sitemap'],
                ];
            case 'hierarchical':
            default:
                return [
                    ['/', 'home'],
                    ['/{cat}/{slug}.html', 'article.view'],
                    ['/{cat}/', 'category.view'],
                    ['/{cat}/page/{n}/', 'category.view'],
                    ['/{slug}.html', 'article.view'],
                    ['/page/{slug}.html', 'page.view'],
                    ['/page/{n}/', 'home'],
                    ['/rss.xml', 'feed.rss'],
                    ['/sitemap.xml', 'feed.sitemap'],
                ];
        }
    }

    private const TARGETS = [
        'home'          => ['Controllers\Article', 'home'],
        'article.view'  => ['Controllers\Article', 'view'],
        'category.view' => ['Controllers\Category', 'view'],
        'page.view'     => ['Controllers\Page', 'view'],
        'feed.rss'      => ['Controllers\Feed', 'rss'],
        'feed.sitemap'  => ['Controllers\Feed', 'sitemap'],
    ];

    private static ?array $routes = null;

    private static function routes(): array
    {
        if (self::$routes !== null) {
            return self::$routes;
        }
        $rows = \R::find('routes');
        self::$routes = [];
        foreach ($rows as $bean) {
            self::$routes[] = [
                'pattern'    => (string)$bean->pattern,
                'target'     => (string)$bean->target,
                'redirect_to' => (string)($bean->redirect_to ?? ''),
                'priority'   => (int)$bean->priority,
            ];
        }
        usort(self::$routes, fn($a, $b) => [$b['priority'], self::specificity($b['pattern'])] <=> [$a['priority'], self::specificity($a['pattern'])]);
        return self::$routes;
    }

    /** Чем больше «статичных» символов в шаблоне, тем он специфичнее.
     *  При равенстве — шаблон с более короткими плейсхолдерами ({n} бьёт {slug}). */
    private static function specificity(string $pattern): array
    {
        preg_match_all('~\{([^}]+)\}~', $pattern, $m);
        $phLen = 0;
        foreach ($m[1] as $name) {
            $phLen += strlen((string)$name);
        }
        $lit = strlen((string)(preg_replace('~\{[^}]+\}~', '', $pattern) ?? $pattern));
        return [$lit, -$phLen];
    }

    public static function dispatch(string $path): never
    {
        $path = \Plugins::filter('route.resolve', $path);

        // Точные 301-редиректы (миграция со старого сайта)
        try {
            $red = \R::findOne('redirects', 'from_url = ?', [$path]);
        } catch (\Throwable) {
            $red = null; // таблица ещё не создана
        }
        if ($red) {
            redirect((string)$red->to_url, (int)($red->code ?: 301));
        }

        foreach (self::routes() as $route) {
            $params = self::match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }
            if ($route['redirect_to'] !== '') {
                redirect($route['redirect_to'], 301);
            }
            $target = self::TARGETS[$route['target']] ?? null;
            if (!$target) {
                continue;
            }
            [$class, $method] = $target;
            $class::$method($params);
            exit;
        }

        Controllers\Page::notFound();
    }

    /** Совпадение шаблона с URL → параметры или null. */
    public static function match(string $pattern, string $path): ?array
    {
        // Хвостовой слэш не влияет: /news и /news/ — одно и то же
        if ($pattern !== '/') {
            $pattern = rtrim($pattern, '/');
        }
        $re = '~^' . self::patternToRegex($pattern) . '$~iu';
        if (preg_match($re, $path, $m) !== 1) {
            return null;
        }
        $params = [];
        if (preg_match_all('~\{([a-z]+)\}~i', $pattern, $ph, PREG_OFFSET_CAPTURE)) {
            $i = 1;
            foreach ($ph[1] as [$name]) {
                $params[$name] = $m[$i++] ?? null;
            }
        }
        return $params;
    }

    private static function patternToRegex(string $pattern): string
    {
        $re = '';
        $pos = 0;
        preg_match_all('~\{([a-z]+)\}~i', $pattern, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as $i => [$ph, $offset]) {
            $re .= preg_quote(substr($pattern, $pos, $offset - $pos), '~');
            $name = $m[1][$i][0];
            $re .= $name === 'n' ? '(\d+)' : '([^/]+)';
            $pos = $offset + strlen($ph);
        }
        return $re . preg_quote(substr($pattern, $pos), '~');
    }
}
