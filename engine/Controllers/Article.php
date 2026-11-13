<?php
/**
 * Контроллер статей и главной страницы.
 */

declare(strict_types=1);

namespace Controllers;

use RedBeanPHP\OODBBean;

final class Article
{
    public static function home(array $params = []): never
    {
        $page = max(1, (int)($params['n'] ?? 1));
        self::listing(null, $page, 'list.home');
    }

    public static function view(array $params): never
    {
        $slug = (string)($params['slug'] ?? '');
        $catSlug = $params['cat'] ?? null;

        $bean = \R::findOne('articles', 'slug = ? AND status = ?', [$slug, 'published']);
        if (!$bean) {
            Page::notFound();
        }
        $cat = \R::load('categories', (int)$bean->category_id);

        // Если в URL указана рубрика — она обязана совпадать (иначе 301 на канонический адрес)
        if ($catSlug !== null) {
            if ((string)$cat->slug !== (string)$catSlug) {
                redirect(\url_article($cat->slug ?: null, $slug), 301);
            }
        }

        $ctx = \page_ctx();
        $ctx['article'] = self::present($bean, $cat);
        $ctx['meta'] = [
            'title'       => $bean->meta_title ?: ($bean->title . ' — ' . \settings('site_name')),
            'description' => $bean->meta_description ?: mb_substr(strip_tags((string)$bean->short), 0, 160),
        ];
        \Plugins::emit('article.view', $bean);
        echo \View::renderPage('view.article', \Plugins::filter('article.context', $ctx, $bean));
        exit;
    }

    /** Общая выдача списка: главная (category=null) или рубрика. */
    public static function listing(?OODBBean $category, int $page, string $tpl): never
    {
        $perPage = max(1, (int)\settings('per_page', '10'));
        $page = max(1, $page);

        [$sql, $bind] = $category
            ? ['status = ? AND category_id = ?', ['published', (int)$category->id]]
            : ['status = ?', ['published']];

        $total = (int)\R::count('articles', $sql, $bind);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);

        $beans = $total === 0 ? [] : \R::findAll('articles',
            $sql . ' ORDER BY published_at DESC LIMIT ? OFFSET ?',
            array_merge($bind, [$perPage, ($page - 1) * $perPage]));

        $catCache = [];
        $articles = [];
        foreach ($beans as $b) {
            $cid = (int)$b->category_id;
            if (!array_key_exists($cid, $catCache)) {
                $catCache[$cid] = $cid ? \R::load('categories', $cid) : null;
            }
            $articles[] = self::present($b, $catCache[$cid]);
        }

        $catSlug = $category ? (string)$category->slug : null;

        $ctx = \page_ctx();
        $ctx['articles'] = $articles;
        $ctx['pagination'] = [
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'prev_url' => $page > 1 ? ($category ? \url_category($catSlug, $page - 1) : \url_home($page - 1)) : '',
            'next_url' => $page < $pages ? ($category ? \url_category($catSlug, $page + 1) : \url_home($page + 1)) : '',
        ];
        if ($category) {
            $ctx['category'] = [
                'id'          => (int)$category->id,
                'title'       => (string)$category->title,
                'slug'        => (string)$category->slug,
                'description' => (string)($category->description ?? ''),
            ];
            $ctx['meta'] = [
                'title'       => $category->meta_title ?: ($category->title . ' — ' . \settings('site_name')),
                'description' => $category->meta_description ?: ($category->description ?: ''),
            ];
        } else {
            $ctx['category'] = null;
            $ctx['meta'] = [
                'title'       => \settings('site_title') ?: \settings('site_name'),
                'description' => \settings('site_description'),
            ];
        }
        echo \View::renderPage($tpl, $ctx);
        exit;
    }

    /** Бин → массив для шаблона. */
    public static function present(OODBBean $bean, ?OODBBean $cat): array
    {
        $catSlug = $cat && $cat->id ? (string)$cat->slug : null;
        $item = [
            'id'        => (int)$bean->id,
            'title'     => (string)$bean->title,
            'slug'      => (string)$bean->slug,
            'url'       => \url_article($catSlug, (string)$bean->slug),
            'short'     => (string)($bean->short ?? ''),
            'full'      => (string)($bean->full ?? ''),
            'cover'     => (string)($bean->cover ?? ''),
            'date'      => (string)($bean->published_at ?? ''),
            'edited'    => (string)($bean->edited_at ?? ''),
            'meta_title' => (string)($bean->meta_title ?? ''),
            'meta_description' => (string)($bean->meta_description ?? ''),
            'category'  => null,
        ];
        if ($cat && $cat->id) {
            $item['category'] = [
                'id'    => (int)$cat->id,
                'title' => (string)$cat->title,
                'slug'  => $catSlug,
                'url'   => \url_category($catSlug),
            ];
        }
        return $item;
    }
}
