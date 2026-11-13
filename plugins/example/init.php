<?php
/**
 * Пример плагина: «example».
 * Демонстрирует подписку на хук template.slot:head — выводит мета-тег в область <head> темы.
 */

declare(strict_types=1);

final class ExamplePlugin implements \PluginInterface
{
    public function register(\Hub $hub): void
    {
        $hub->addFilter('template.slot:head', function (string $value): string {
            return $value . "\n<meta name=\"generator\" content=\"CMS_for_ai example-plugin\">";
        });
    }
}

return new ExamplePlugin();
