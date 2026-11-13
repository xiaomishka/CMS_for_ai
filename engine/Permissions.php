<?php
/**
 * Permissions.php — матрица прав ролей.
 * Роли: admin / editor / author.
 * Расширение: плагин может добавить право и проверять его через Auth::can().
 */

declare(strict_types=1);

final class Permissions
{
    /** Право => роли, у которых оно есть. */
    private const MATRIX = [
        // Статьи
        'article.create'      => ['admin', 'editor', 'author'],
        'article.edit.any'    => ['admin', 'editor'],
        'article.publish'     => ['admin', 'editor'],
        'article.delete'      => ['admin', 'editor'],
        // Рубрики и страницы
        'category.manage'     => ['admin', 'editor'],
        'category.create'     => ['admin'],
        'page.manage'         => ['admin', 'editor'],
        // Служебное (только админ)
        'redirect.manage'     => ['admin'],
        'settings.manage'     => ['admin'],
        'users.manage'        => ['admin'],
        'plugins.manage'      => ['admin'],
        'media.manage'        => ['admin', 'editor'],
        'templates.manage'    => ['admin'],
    ];

    public static function can(?string $role, string $perm): bool
    {
        if ($role === null || !isset(self::MATRIX[$perm])) {
            return false;
        }
        return in_array($role, self::MATRIX[$perm], true);
    }

    /** Список прав роли (для админки/отладки). */
    public static function forRole(string $role): array
    {
        $out = [];
        foreach (self::MATRIX as $perm => $roles) {
            if (in_array($role, $roles, true)) {
                $out[] = $perm;
            }
        }
        return $out;
    }

    public static function roles(): array
    {
        return ['admin', 'editor', 'author'];
    }
}
