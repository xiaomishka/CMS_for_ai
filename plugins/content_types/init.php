<?php
/**
 * Плагин content_types — реестр типов контента.
 *
 * Закладывает основу для интернет-магазина: статьи и товары — это разные
 * типы контента с общим интерфейсом (id, slug, title, url, type).
 * Любой плагин может зарегистрировать свой тип через фильтр content_types.list.
 *
 * Текущие типы:
 *   article — статьи (таблица articles, уже есть)
 *   product — товары (задел под интернет-магазин; таблица products +
 *     роуты добавятся при создании модуля каталога)
 */

declare(strict_types=1);

final class ContentTypesPlugin implements \PluginInterface
{
    /** Типы контента и их метаданные. */
    private const TYPES = [
        'article' => [
            'table'        => 'articles',
            'id_field'     => 'id',
            'slug_field'   => 'slug',
            'title_field'  => 'title',
            'status_field' => 'status',
            'published'    => 'published',
            'url_pattern'  => '/{cat}/{slug}.html',
        ],
        'product' => [
            'table'        => 'products',
            'id_field'     => 'id',
            'slug_field'   => 'slug',
            'title_field'  => 'title',
            'status_field' => 'status',
            'published'    => 'published',
            'url_pattern'  => '/product/{slug}.html',
        ],
    ];

    public function register(\Hub $hub): void
    {
        // Список типов: фильтр, чтобы другие плагины могли добавлять свои типы
        $hub->addFilter('content_types.list', function (array $types): array {
            return array_merge($types, array_keys(self::TYPES));
        }, 5);

        // Метаданные типа по имени: фильтр, чтобы переопределять или дополнять
        $hub->addFilter('content_types.get', function (?array $meta, string $type): ?array {
            if ($meta !== null) {
                return $meta;
            }
            return self::TYPES[$type] ?? null;
        }, 5);

        // Проверка: существует ли тип
        $hub->addFilter('content_types.has', function (bool $has, string $type): bool {
            return $has || isset(self::TYPES[$type]);
        }, 5);
    }
}

return new ContentTypesPlugin();
