<?php
/**
 * helpers.php — общие функции ядра.
 * Всё глобальное здесь: экранирование, slug, редиректы, сборка URL.
 */

declare(strict_types=1);

/** Экранирование для вывода (по умолчанию во всех шаблонах). */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Транслитерация для slug. */
function translit(string $s): string
{
    static $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'і'=>'i','ї'=>'yi','є'=>'ye','ґ'=>'g','ў'=>'u',
    ];
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, $map);
}

/** Slug из строки (транслит, a-z0-9, дефисы). */
function slugify(string $title): string
{
    $s = translit(trim($title));
    $s = preg_replace('~[^a-z0-9]+~u', '-', $s);
    $s = trim((string)preg_replace('~-{2,}~', '-', $s), '-');
    return mb_substr($s, 0, 120, 'UTF-8');
}

/** HTTP-редирект и остановка. */
function redirect(string $url, int $code = 302): never
{
    http_response_code($code);
    header('Location: ' . $url, true, $code);
    exit;
}

/** Абсолютный URL сайта (scheme://host без пути). */
function site_url(): string
{
    static $url = null;
    if ($url !== null) {
        return $url;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $url = ($https ? 'https' : 'http') . '://' . $host;
}

/* ── Настройки из БД (key/value) ─────────────────────────────── */

function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (\R::findAll('settings') as $bean) {
                $cache[$bean->key] = $bean->value;
            }
        } catch (\Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function settings(string $key, string $default = ''): string
{
    $all = settings_all();
    return (string)($all[$key] ?? $default);
}

/* ── Сборка URL по активной схеме ЧПУ ───────────────────────── */

function url_scheme(): string
{
    return \Config::get('site.url_scheme') ?: 'hierarchical';
}

/** URL главной (с пагинацией). */
function url_home(int $page = 1): string
{
    if ($page > 1) {
        return '/page/' . $page . '/';
    }
    return '/';
}

/** URL категории. */
function url_category(string $slug, int $page = 1): string
{
    $pagePart = $page > 1 ? '/page/' . $page . '/' : '/';
    switch (url_scheme()) {
        case 'short':
            $base = '/category/' . rawurlencode($slug);
            break;
        case 'clean':
        case 'hierarchical':
        default:
            $base = '/' . rawurlencode($slug);
            break;
    }
    return $base . $pagePart;
}

/** URL статьи. Схема short роняет рубрику (роут /{slug}.html — один сегмент),
 *  clean даёт /рубрика/slug/ (без .html). Должно совпадать с Router::defaults(). */
function url_article(?string $catSlug, string $slug): string
{
    $cat = $catSlug ? '/' . rawurlencode($catSlug) : '';
    switch (url_scheme()) {
        case 'short':
            return '/' . rawurlencode($slug) . '.html';
        case 'clean':
            return $cat . '/' . rawurlencode($slug) . '/';
        case 'hierarchical':
        default:
            return $cat . '/' . rawurlencode($slug) . '.html';
    }
}

/** URL статической страницы. */
function url_page(string $slug): string
{
    return '/page/' . rawurlencode($slug) . '.html';
}

/** URL из таблицы routes (для кастомных ссылок плагинов). */
function url_custom(string $name, array $params = []): string
{
    $route = \R::findOne('routes', 'name = ?', [$name]);
    if (!$route) {
        return '/';
    }
    $url = $route->pattern;
    foreach ($params as $k => $v) {
        $url = str_replace('{' . $k . '}', rawurlencode((string)$v), $url);
    }
    return $url;
}

/** Текущий путь запроса (нормализованный). */
function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    if ($path !== '/' ) {
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
    }
    return $path;
}

/* ── Общий контекст страниц ─────────────────────────────────── */

/**
 * Базовый контекст для всех страниц: сайт, меню, сайдбар, пользователь.
 * Шаблоны всегда получают эти ключи.
 */
function page_ctx(): array
{
    // Верхнее меню: корневые рубрики
    $nav = [];
    foreach (\R::find('categories', '(parent_id IS NULL OR parent_id = 0) ORDER BY title ASC') as $cat) {
        $nav[] = [
            'title' => (string)$cat->title,
            'url'   => \url_category((string)$cat->slug),
        ];
    }

    // Сайдбар: последние записи
    $recent = [];
    foreach (\R::find('articles', 'status = ? ORDER BY published_at DESC LIMIT ?', ['published', 8]) as $b) {
        $cat = $b->category_id ? \R::load('categories', (int)$b->category_id) : null;
        $catSlug = ($cat && $cat->id) ? (string)$cat->slug : null;
        $recent[] = [
            'title' => (string)$b->title,
            'url'   => \url_article($catSlug, (string)$b->slug),
            'date'  => (string)($b->published_at ?? ''),
        ];
    }

    $user = \Auth::user();
    return [
        'site' => [
            'name'   => \settings('site_name', 'CMS'),
            'url'    => \site_url(),
            'year'   => date('Y'),
        ],
        'meta'  => ['title' => \settings('site_name'), 'description' => ''],
        'nav'   => $nav,
        'recent' => $recent,
        'user'  => $user ? [
            'name'  => (string)$user['name'],
            'role'  => (string)$user['role'],
            'logged' => true,
        ] : ['name' => '', 'role' => 'guest', 'logged' => false],
    ];
}

