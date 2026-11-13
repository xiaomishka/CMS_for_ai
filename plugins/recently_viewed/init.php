<?php
/**
 * Плагин recently_viewed — недавние просмотры.
 *
 * Сохраняет в куках браузера просмотренные статьи (только статьи, не рубрики)
 * и делает их доступными в шаблоне через контекст recently_viewed
 * и слот [slot recently_viewed].
 *
 * Готов к интернет-магазину: использует реестр content_types, поэтому в
 * будущем можно отслеживать просмотренные товары так же просто.
 */

declare(strict_types=1);

final class RecentlyViewedPlugin implements \PluginInterface
{
    private const COOKIE_NAME = 'cms_recently_viewed';
    private const MAX_ITEMS   = 10;

    public function register(\Hub $hub): void
    {
        // 1. Отслеживаем просмотр статьи: пишем id в куку
        $hub->on('article.view', function (\RedBeanPHP\OODBBean $bean): void {
            $this->trackView((int)$bean->id);
        });

        // 2. Добавляем recently_viewed в контекст шаблона
        $hub->addFilter('template.context', function (array $ctx): array {
            $ctx['recently_viewed'] = $this->getItems();
            return $ctx;
        }, 20);

        // 3. Слот [slot recently_viewed] — готовая верстка списка
        $hub->addFilter('template.slot:recently_viewed', function (string $html): string {
            $items = $this->getItems();
            if (empty($items)) {
                return $html;
            }
            $out = '<div class="recently-viewed">' . "\n";
            $out .= '  <h3 class="recently-viewed-title">Недавно просмотренные статьи</h3>' . "\n";
            $out .= '  <ul class="recently-viewed-list">' . "\n";
            foreach ($items as $item) {
                $out .= '    <li><a href="' . e($item['url']) . '">' . e($item['title']) . '</a></li>' . "\n";
            }
            $out .= '  </ul>' . "\n";
            $out .= '</div>' . "\n";
            return $html . $out;
        }, 20);

        // 4. JS-отслеживание в браузере (localStorage) — резервный канал,
        //    работает даже если куки отключены
        $hub->addFilter('template.slot:before_end', function (string $html): string {
            $html .= "\n" . '<script>' . "\n";
            $html .= '(function(){' . "\n";
            $html .= '  var key = "cms_recently_viewed";' . "\n";
            $html .= '  var max = ' . self::MAX_ITEMS . ';' . "\n";
            $html .= '  var path = window.location.pathname;' . "\n";
            $html .= '  var m = path.match(/\/([^\/]+)\.html$/);' . "\n";
            $html .= '  if (!m) return;' . "\n";
            $html .= '  var slug = m[1];' . "\n";
            $html .= '  try {' . "\n";
            $html .= '    var stored = localStorage.getItem(key);' . "\n";
            $html .= '    var arr = stored ? JSON.parse(stored) : [];' . "\n";
            $html .= '    if (!Array.isArray(arr)) arr = [];' . "\n";
            $html .= '    arr = arr.filter(function(s){ return s !== slug; });' . "\n";
            $html .= '    arr.unshift(slug);' . "\n";
            $html .= '    if (arr.length > max) arr = arr.slice(0, max);' . "\n";
            $html .= '    localStorage.setItem(key, JSON.stringify(arr));' . "\n";
            $html .= '  } catch (e) {}' . "\n";
            $html .= '})();' . "\n";
            $html .= '</script>' . "\n";
            return $html;
        }, 10);
    }

    /** Записать просмотр статьи в куку. */
    private function trackView(int $id): void
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? '[]';
        $ids = json_decode($raw, true);
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter($ids, fn($x) => (int)$x !== $id));
        array_unshift($ids, $id);
        $ids = array_slice($ids, 0, self::MAX_ITEMS);
        setcookie(self::COOKIE_NAME, json_encode($ids), time() + 365 * 24 * 3600, '/', '', false, true);
    }

    /** Получить просмотренные статьи из куки. */
    private function getItems(): array
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? '[]';
        $ids = json_decode($raw, true);
        if (!is_array($ids) || empty($ids)) {
            return [];
        }
        $items = [];
        foreach ($ids as $id) {
            $bean = \R::load('articles', (int)$id);
            if (!$bean->id) {
                continue;
            }
            $cat = \R::load('categories', (int)$bean->category_id);
            $items[] = \Controllers\Article::present($bean, $cat);
        }
        return $items;
    }
}

return new RecentlyViewedPlugin();
