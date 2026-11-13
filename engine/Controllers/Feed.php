<?php
/**
 * Контроллер лент: RSS (/rss.xml) и карта сайта (/sitemap.xml).
 */

declare(strict_types=1);

namespace Controllers;

final class Feed
{
    public static function rss(): never
    {
        $siteUrl = \site_url();
        $siteName = \settings('site_name', 'CMS');
        $siteDesc = \settings('site_description') ?: $siteName;

        $beans = \R::find('articles', 'status = ? ORDER BY published_at DESC LIMIT ?', ['published', 20]);

        $items = '';
        foreach ($beans as $b) {
            $cat = $b->category_id ? \R::load('categories', (int)$b->category_id) : null;
            $catSlug = ($cat && $cat->id) ? (string)$cat->slug : null;
            $link = $siteUrl . \url_article($catSlug, (string)$b->slug);
            $title = (string)$b->title;
            $desc = (string)($b->short ?? '');
            $date = date('r', strtotime((string)$b->published_at) ?: time());
            $items .= "<item>\n"
                . '<title>' . self::x($title) . "</title>\n"
                . '<link>' . self::x($link) . "</link>\n"
                . '<guid isPermaLink="true">' . self::x($link) . "</guid>\n"
                . '<pubDate>' . $date . "</pubDate>\n"
                . '<description>' . self::x($desc) . "</description>\n"
                . "</item>\n";
        }

        $last = $beans ? date('r', strtotime((string)reset($beans)->published_at) ?: time()) : date('r');

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0">' . "\n"
            . "<channel>\n"
            . '<title>' . self::x($siteName) . "</title>\n"
            . '<link>' . self::x($siteUrl . '/') . "</link>\n"
            . '<description>' . self::x($siteDesc) . "</description>\n"
            . '<lastBuildDate>' . $last . "</lastBuildDate>\n"
            . '<generator>CMS_for_ai</generator>' . "\n"
            . $items
            . "</channel>\n</rss>";
        exit;
    }

    public static function sitemap(): never
    {
        $siteUrl = \site_url();
        $urls = [];

        $urls[] = [$siteUrl . '/', date('Y-m-d')];
        foreach (\R::find('categories', 'ORDER BY title ASC') as $c) {
            $urls[] = [$siteUrl . \url_category((string)$c->slug), date('Y-m-d')];
        }
        foreach (\R::find('articles', 'status = ? ORDER BY published_at DESC', ['published']) as $b) {
            $cat = $b->category_id ? \R::load('categories', (int)$b->category_id) : null;
            $catSlug = ($cat && $cat->id) ? (string)$cat->slug : null;
            $ts = strtotime((string)($b->edited_at ?: $b->published_at)) ?: time();
            $urls[] = [$siteUrl . \url_article($catSlug, (string)$b->slug), date('Y-m-d', $ts)];
        }
        try {
            foreach (\R::find('pages', 'status = ?', ['published']) as $p) {
                $ts = strtotime((string)($p->edited_at ?: $p->created_at)) ?: time();
                $urls[] = [$siteUrl . \url_page((string)$p->slug), date('Y-m-d', $ts)];
            }
        } catch (\Throwable) {
            // таблица pages ещё не создана — пропускаем
        }

        $xml = '';
        foreach ($urls as [$loc, $lastmod]) {
            $xml .= "<url>\n<loc>" . self::x($loc) . "</loc>\n<lastmod>" . $lastmod . "</lastmod>\n</url>\n";
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $xml
            . '</urlset>';
        exit;
    }

    /** Экранирование для XML. */
    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
