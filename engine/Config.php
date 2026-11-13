<?php
/**
 * Config.php — чтение core/config.php (создаётся install.php).
 * Доступ через точечные пути: Config::get('db.type'), Config::get('site.theme').
 */

declare(strict_types=1);

final class Config
{
    private static ?array $data = null;

    public static function load(): void
    {
        if (self::$data !== null) {
            return;
        }
        $file = BASE_PATH . '/core/config.php';
        self::$data = is_file($file) ? (require $file) : [];
        if (!is_array(self::$data)) {
            self::$data = [];
        }
    }

    /** @return mixed */
    public static function get(string $path, mixed $default = null)
    {
        $parts = explode('.', $path);
        $node = self::$data;
        foreach ($parts as $p) {
            if (!is_array($node) || !array_key_exists($p, $node)) {
                return $default;
            }
            $node = $node[$p];
        }
        return $node;
    }

    /** Полный массив конфига (только для записи из install.php). */
    public static function all(): array
    {
        self::load();
        return self::$data;
    }
}
