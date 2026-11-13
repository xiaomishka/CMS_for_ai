<?php
/**
 * Plugins/Hub.php — шина событий.
 * Плагины подписываются: $hub->on(...), $hub->filter(...).
 * Ядро вызывает: Plugins::emit(...) и Plugins::filter(...) в точках из hooks.php.
 *
 * До появления плагинов (v0.1) шина работает вхолостую — это нормально.
 */

declare(strict_types=1);

final class Hub
{
    private array $actions = [];   // event => [priority => [callable, ...]]
    private array $filters = [];   // event => [priority => [callable, ...]]

    public function on(string $event, callable $fn, int $priority = 10): void
    {
        $this->actions[$event][$priority][] = $fn;
    }

    public function emit(string $event, mixed ...$args): void
    {
        foreach ($this->sorted($this->actions[$event] ?? []) as $fn) {
            $fn(...$args);
        }
    }

    /** Фильтр пропускает значение через цепочку подписчиков. */
    public function filter(string $event, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->sorted($this->filters[$event] ?? []) as $fn) {
            $value = $fn($value, ...$args);
        }
        return $value;
    }

    public function addFilter(string $event, callable $fn, int $priority = 10): void
    {
        $this->filters[$event][$priority][] = $fn;
    }

    private function sorted(array $byPriority): array
    {
        if (!$byPriority) {
            return [];
        }
        ksort($byPriority);
        $out = [];
        foreach ($byPriority as $list) {
            $out = array_merge($out, $list);
        }
        return $out;
    }
}

final class Plugins
{
    private static ?Hub $hub = null;
    private static array $loaded = [];

    public static function hub(): Hub
    {
        return self::$hub ??= new Hub();
    }

    /** Загрузка включённых плагинов (active_plugins в settings). */
    public static function boot(): void
    {
        $hub = self::hub();
        $list = settings('active_plugins', '');
        if ($list === '') {
            return;
        }
        foreach (array_filter(array_map('trim', explode(',', $list))) as $name) {
            self::load($name, $hub);
        }
    }

    private static function load(string $name, Hub $hub): void
    {
        $name = basename($name); // без путей
        if (isset(self::$loaded[$name])) {
            return;
        }
        self::$loaded[$name] = true;
        $init = BASE_PATH . '/plugins/' . $name . '/init.php';
        $meta = BASE_PATH . '/plugins/' . $name . '/plugin.json';
        if (!is_file($init) || !is_file($meta)) {
            return;
        }
        $plugin = require $init;
        if ($plugin instanceof PluginInterface) {
            $plugin->register($hub);
        }
    }

    public static function emit(string $event, mixed ...$args): void
    {
        self::hub()->emit($event, ...$args);
    }

    public static function filter(string $event, mixed $value, mixed ...$args): mixed
    {
        return self::hub()->filter($event, $value, ...$args);
    }
}

interface PluginInterface
{
    public function register(Hub $hub): void;
}
