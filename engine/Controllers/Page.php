<?php
/**
 * Контроллер статических страниц и служебных ответов.
 */

declare(strict_types=1);

namespace Controllers;

final class Page
{
    public static function view(array $params): never
    {
        $slug = (string)($params['slug'] ?? '');
        try {
            $bean = \R::findOne('pages', 'slug = ? AND status = ?', [$slug, 'published']);
        } catch (\Throwable) {
            $bean = null; // таблица pages ещё не создана
        }
        if (!$bean) {
            self::notFound();
        }
        $ctx = \page_ctx();
        $ctx['page'] = [
            'id'     => (int)$bean->id,
            'title'  => (string)$bean->title,
            'slug'   => (string)$bean->slug,
            'content' => (string)($bean->content ?? ''),
        ];
        $ctx['meta'] = [
            'title'       => $bean->meta_title ?: ($bean->title . ' — ' . \settings('site_name')),
            'description' => $bean->meta_description ?: '',
        ];
        echo \View::renderPage('view.page', $ctx);
        exit;
    }

    /** 404. */
    public static function notFound(): never
    {
        http_response_code(404);
        self::logNotFound();
        try {
            $ctx = \page_ctx();
            $ctx['meta'] = ['title' => 'Страница не найдена', 'description' => ''];
            echo \View::renderPage('404', $ctx);
        } catch (\Throwable) {
            echo '404 — страница не найдена';
        }
        exit;
    }

    /** Лог «слетевших» URL (таблица notfound, в админке → Редиректы). */
    private static function logNotFound(): void
    {
        $path = \current_path();
        if ($path === '/favicon.ico') {
            return;
        }
        try {
            $row = \R::findOne('notfound', 'url = ?', [$path]);
            if ($row) {
                $row->hits = (int)$row->hits + 1;
                $row->last_at = date('Y-m-d H:i:s');
            } else {
                $row = \R::dispense('notfound');
                $row->url = $path;
                $row->hits = 1;
                $row->last_at = date('Y-m-d H:i:s');
            }
            \R::store($row);
        } catch (\Throwable) {
            // таблица ещё не создана — лог не критичен
        }
    }

    /** 500. */
    public static function serverError(): never
    {
        http_response_code(500);
        try {
            $ctx = \page_ctx();
            $ctx['meta'] = ['title' => 'Ошибка сервера', 'description' => ''];
            echo \View::renderPage('500', $ctx);
        } catch (\Throwable) {
            echo '500 — внутренняя ошибка сервера';
        }
        exit;
    }
}
