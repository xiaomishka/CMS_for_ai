<?php
/**
 * Database.php — подключение к БД через RedBean и установка схемы.
 *
 * MySQL и SQLite — один и тот же код: R::setup с разным DSN.
 * После установки схема замораживается (R::freeze(true)).
 */

declare(strict_types=1);

final class Database
{
    private static bool $connected = false;

    public static function connect(): void
    {
        if (self::$connected) {
            return;
        }
        $type = \Config::get('db.type', 'sqlite');
        if ($type === 'mysql') {
            $host = (string)\Config::get('db.host', 'localhost');
            $name = (string)\Config::get('db.name');
            \R::setup('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
                (string)\Config::get('db.user'), (string)\Config::get('db.pass'));
        } else {
            \R::setup('sqlite:' . BASE_PATH . '/' . \Config::get('db.path', 'core/cms.sqlite'));
        }
        if (!\R::testConnection()) {
            throw new RuntimeException('Не удалось подключиться к базе данных');
        }
        \R::freeze(true);
        self::$connected = true;
    }

    /**
     * Установка: создание схемы + сиды. Вызывается ТОЛЬКО из install.php
     * в fluid-режиме — RedBean создаёт таблицы по структуре бинов.
     */
    public static function install(array $admin, array $settings, string $scheme): void
    {
        \R::freeze(false);

        // 1. Пользователь-админ
        $user = \R::dispense('users');
        $user->email      = $admin['email'];
        $user->password   = password_hash($admin['password'], PASSWORD_DEFAULT);
        $user->name       = $admin['name'] ?: 'Администратор';
        $user->role       = 'admin';
        $user->created_at = date('Y-m-d H:i:s');
        \R::store($user);

        // 2. Настройки
        foreach ($settings as $key => $value) {
            $s = \R::dispense('settings');
            $s->key   = $key;
            $s->value = $value;
            \R::store($s);
        }

        // 3. Роуты пресета схемы
        foreach (Router::defaults($scheme) as [$pattern, $target]) {
            $bean = \R::dispense('routes');
            $bean->pattern     = $pattern;
            $bean->target      = $target;
            $bean->redirect_to = '';
            $bean->priority    = 0;
            $bean->name        = '';
            \R::store($bean);
        }

        // 4. Демо-контент, чтобы тема сразу показала себя
        $cat = \R::dispense('categories');
        $cat->title       = 'Новости';
        $cat->slug        = 'news';
        $cat->parent_id   = 0;
        $cat->description = 'Пример рубрики. Создана установщиком.';
        $catId = \R::store($cat);

        $article = \R::dispense('articles');
        $article->title        = 'Добро пожаловать в CMS';
        $article->slug         = 'dobro-pozhalovat';
        $article->category_id  = $catId;
        $article->author_id    = $user->id;
        $article->short        = 'Демо-статья создана установщиком. Отредактируйте или удалите её в админке.';
        $article->full         = '<p>Это демо-статья, созданная установщиком.</p>'
            . '<p>Что дальше:</p><ul><li>Войдите в <a href="/admin/">админку</a></li>'
            . '<li>Создайте рубрики и статьи</li>'
            . '<li>Загрузите свою тему в <code>/templates/</code> — или попросите ИИ адаптировать любой HTML-шаблон.</li></ul>';
        $article->meta_title   = '';
        $article->meta_description = '';
        $article->cover        = '';
        $article->status       = 'published';
        $article->published_at = date('Y-m-d H:i:s');
        $article->edited_at    = date('Y-m-d H:i:s');
        \R::store($article);

        \R::freeze(true);
    }

    /**
     * Миграции версий ядра. Вызывается при каждом буте после connect.
     * Не хватает таблиц/колонок → временно fluid, создаём, снова freeze.
     * Idempotent: отсутствие таблицы проверяем попыткой запроса.
     */
    public static function migrate(): void
    {
        // 1. Какие таблицы версии 0.2 ещё не созданы
        $need = [];
        foreach (['pages', 'redirects', 'notfound', 'attachments'] as $table) {
            try {
                \R::count($table);
            } catch (\Throwable) {
                $need[] = $table; // таблицы нет (frozen-режим бросает исключение)
            }
        }

        // 2. Создаём недостающие в fluid-режиме, с полным набором колонок
        if ($need) {
            \R::freeze(false);
            try {
                $prototypes = [
                    'pages' => [
                        'title' => '', 'slug' => '', 'content' => '', 'status' => 'draft',
                        'meta_title' => '', 'meta_description' => '',
                        'created_at' => date('Y-m-d H:i:s'), 'edited_at' => date('Y-m-d H:i:s'),
                    ],
                    'redirects' => ['from_url' => '', 'to_url' => '', 'code' => 301],
                    'notfound' => ['url' => '', 'hits' => 1, 'last_at' => date('Y-m-d H:i:s')],
                    'attachments' => [
                        'filename' => '', 'original_name' => '', 'mime_type' => '',
                        'size' => 0, 'article_id' => 0, 'url' => '',
                        'created_at' => date('Y-m-d H:i:s'),
                    ],
                ];
                foreach ($need as $table) {
                    $bean = \R::dispense($table);
                    foreach ($prototypes[$table] as $field => $value) {
                        $bean->$field = $value;
                    }
                    \R::store($bean);
                    \R::trash($bean); // таблица и колонки созданы, строку-заглушку убираем
                }
            } finally {
                \R::freeze(true);
            }
        }

        // 3. Роуты RSS/sitemap (появились в 0.2): если их нет — перегенерируем
        //    дефолтные для текущей схемы, кастомные (priority >= 100) сохраняются.
        try {
            if ((int)\R::count('routes', 'target IN (?, ?)', ['feed.rss', 'feed.sitemap']) === 0) {
                self::seedRoutes((string)\Config::get('site.url_scheme') ?: 'hierarchical');
            }
        } catch (\Throwable) {
            // routes ещё нет (не должно случиться после install) — молча пропускаем
        }
    }

    /** Перегенерация дефолтных роутов при смене схемы ЧПУ (кастомные priority>=100 сохраняются). */
    public static function seedRoutes(string $scheme): void
    {
        $custom = [];
        foreach (\R::find('routes', 'priority >= 100') as $bean) {
            $custom[] = [
                'pattern'     => (string)$bean->pattern,
                'target'      => (string)$bean->target,
                'redirect_to' => (string)($bean->redirect_to ?? ''),
                'priority'    => (int)$bean->priority,
                'name'        => (string)($bean->name ?? ''),
            ];
        }
        \R::wipe('routes');
        foreach ($custom as $c) {
            $bean = \R::dispense('routes');
            $bean->import($c, ['pattern', 'target', 'redirect_to', 'priority', 'name']);
            \R::store($bean);
        }
        foreach (Router::defaults($scheme) as [$pattern, $target]) {
            $bean = \R::dispense('routes');
            $bean->pattern     = $pattern;
            $bean->target      = $target;
            $bean->redirect_to = '';
            $bean->priority    = 0;
            $bean->name        = '';
            \R::store($bean);
        }
    }
}
