<?php
/**
 * Контроллер рубрик.
 */

declare(strict_types=1);

namespace Controllers;

final class Category
{
    public static function view(array $params): never
    {
        $slug = (string)($params['cat'] ?? '');
        $page = max(1, (int)($params['n'] ?? 1));

        $cat = \R::findOne('categories', 'slug = ?', [$slug]);
        if (!$cat) {
            // Одиночный сегмент может оказаться статьёй без рубрики
            Article::view(['slug' => $slug]);
        }
        Article::listing($cat, $page, 'view.category');
    }
}
