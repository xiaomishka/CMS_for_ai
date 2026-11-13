# CMS_for_ai — легковесная CMS для статейников

Alpha **0.2**. PHP 8.1+, RedBeanPHP (без composer), MySQL или SQLite.
Главная фишка — быстрая адаптация любого HTML-шаблона силами ИИ
(папка `templates/<имя>/`, теговый шаблонизатор `*.tpl`, спецификация в `agents.md`).

> Документы проекта: `ARCHITECTURE.md` — архитектура, `DEV_NOTES.md` —
> инструкция для ИИ/разработчика (среда, протокол тестов, roadmap v0.2).

## Установка

1. Скопируйте файлы на сервер (или в папку локального веб-сервера).
2. Убедитесь, что `core/` и `uploads/` доступны на запись.
3. Откройте `/install.php`:
   - выберите SQLite (ничего не нужно) или MySQL;
   - задайте e-mail и пароль администратора, название сайта;
   - выберите схему ЧПУ и тему.
4. Установщик сам создаст схему, демо-контент, запишет `core/config.php`,
   заблокирует себя (`install.php` → `install.done.php`) и отправит в админку.

После установки админка: `/admin/` (тёмно-синяя тема на локальном Tailwind CSS —
`admin/assets/admin.css`, без CDN): статьи, рубрики, статические страницы,
пользователи и роли, 301-редиректы с логом 404, настройки ЧПУ/темы.

## Changelog v

### v1.1 (в разработке)
- **TinyMCE** (локально, `admin/assets/tinymce/`) — визуальный редактор для полей
  `short`/`full` статьи и `content` страницы в админке (без CDN, все ресурсы
  локально).
- **Редактор шаблонов** (ACE, `admin/assets/ace/`) — раздел «Шаблоны тем»
  (`?page=templates`), редактирование любых файлов тем (активных и неактивных)
  без FTP, право `templates.manage` (только admin).

### v1.0
- **agents.md** — спецификация для ИИ-адаптации любого HTML-шаблона: карта
  файлов темы, реестр тегов `.tpl`, правила адаптации, режим вебархива, правила
  кода ядра.
- **Тема-пример адаптации** — `templates/ai-adapted/` показывает, как адаптировать
  готовый HTML-шаблон силами ИИ (движок `tpl`).
- **Автотесты** — `core/tests/run.php` (65 тестов, без composer): Permissions,
  Helpers, Router, Template, Database. Запуск: `php core/tests/run.php`.

### v0.3
- **Медиазагрузка**: раздел «Медиа» в админке (`?page=media`), загрузка
  изображений (jpg, png, gif, webp, до 5 МБ) в `uploads/attachments`, удаление
  файлов, копирование URL, вставка в обложку статьи из медиатеки. Права:
  `media.manage` (admin, editor).
- **Плагины в UI**: раздел «Плагины» (`?page=plugins`), включение/выключение
  через `active_plugins` (`?page=plugins_toggle`), право `plugins.manage`
  (admin). Пример плагина `plugins/example/` (хук `template.slot:head`).
- **phtml-режим**: тема-пример `templates/phtml-example/` на чистых PHP-
  шаблонах (движок `PhtmlView`, режим выбирается в `theme.json`).

### v0.2
- **Роли**: admin / editor / author. Автор видит и правит только свои статьи и не
  может публиковать (статус принудительно draft); editor — любые статьи.
  Пользователей создаёт админ в разделе «Пользователи».
- **Статические страницы**: CRUD по образцу статей, URL `/page/{slug}.html`.
- **301-редиректы**: раздел «Редиректы» (from → to, код 301/302). Проверяются до
  роутов ЧПУ, путь нормализуется (со слэшем или без).
- **404-лог**: все «слетевшие» адреса пишутся в таблицу `notfound` (URL, число
  запросов, последний раз); из лога — быстрое создание редиректа.
- **RSS + sitemap**: `/rss.xml` (20 последних статей) и `/sitemap.xml`
  (главная, рубрики, статьи, страницы). Роуты есть во всех пресетах ЧПУ.

## Структура

```
index.php          точка входа (все URL через роутер)
install.php        установщик
engine/            ядро: Router, Template, View, Auth, Permissions, Database, Controllers (Article, Category, Page, Feed), Plugins/Hub
templates/default/ тема: layout.tpl, view.*.tpl, partial.*.tpl, theme.json, assets/
admin/             админка (index.php + includes/views.php + assets/admin.css — Tailwind)
plugins/           плагины (папка с plugin.json + init.php)
core/              config.php, rb.php (RedBean), кэш шаблонов, логи — закрыт от веба
uploads/           медиафайлы — исполнение PHP запрещено
tools/             сборочный инструмент Tailwind CLI (не входит в пакет)
```

## Схемы ЧПУ (переключаются в настройках, кастомные роуты сохраняются)

| Пресет | Статья | Рубрика |
|---|---|---|
| hierarchical | `/рубрика/статья.html` | `/рубрика/` |
| short | `/статья.html` (без рубрики) | `/category/рубрика/` |
| clean | `/рубрика/статья/` | `/рубрика/` |

Кастомные правила и 301-редиректы старых адресов — таблицы `routes`/`redirects`
(редиректы уже в админке v0.2). Пример восстановления иерархии старого сайта:
добавить в `routes` строку `pattern=/blog/{slug}.html`, `target=article.view`,
`priority=100`.

## Шаблонизатор (режим tpl)

```html
{{ site.name }}                       переменная (авто-экранирование)
{{ article.full|raw }}                HTML без экранирования
{{ article.date|date:"d.m.Y" }}       модификатор: date, truncate:"N", upper, lower
[each articles as a] ... [/each]      цикл
[each list as a] ... [else] ... [/each]  для пустого списка
[if a.cover] ... [else] ... [/if]     условия: truthy, not, ==/!=/>/<, user.can("..."), user.logged
[include partial.sidebar]             вставка другого tpl
[slot head]                           точка вывода плагинов
```

Маппинг страниц → файлы: `layout.tpl`, `view.article.tpl`, `view.category.tpl`,
`view.page.tpl`, `list.home.tpl`, `404.tpl`, `500.tpl`. Режим движка — в
`theme.json` (`"engine": "tpl"` или `"phtml"` — чистые PHP-шаблоны для форков).

## Безопасность (что уже сделано)

- запросы к БД только параметризованные (RedBean), схема заморожена после установки;
- whitelist-заполнение бинов, данные из `$_POST` в `import()` не попадают;
- CSRF на всех POST формах админки, сессии httponly+samesite;
- `password_hash`/`password_verify` (+перехеширование);
- `engine/`, `core/`, `plugins/` закрыты от веба, в `uploads/` запрещён PHP;
- шаблоны компилируются в кэш `core/cache/`.

## Локальный запуск (Windows)

Нужен PHP 8.1+ с расширениями pdo_sqlite (или pdo_mysql), mbstring:

```
php -S localhost:8080        # из корня проекта
# открыть http://localhost:8080/install.php
```

### Тестирование в OpenServer Panel (cms-ai.test)

Домен-зеркало: `G:\OSPanel\home\cms-ai.test`. После правок в проекте синхронизируйте:

```
sync-osp.cmd            # двойной клик, или:
powershell -File sync-osp.ps1
```

Скрипт зеркалит проект (robocopy /MIR) и заодно сбрасывает в домене рантайм-файлы
(`core/config.php`, БД, кэш, логи, `install.done.php`) — домен остаётся «чистым»
для установки. Если домен новый, перезапустите OSPanel Panel, чтобы он попал в hosts.

Apache в OSP уже настроен правильно: `AllowOverride All`, `mod_rewrite`, PHP-FastCGI.
Установка: `http://cms-ai.test/install.php` (SQLite — ничего не нужно; MySQL —
создайте базу заранее, например через phpMyAdmin в OSP).

## Сборка Tailwind CSS (админка)

Админка оформлена утилитарными классами Tailwind, CSS лежит локально
(`admin/assets/admin.css`, никакого CDN). После изменения классов во вьюхах
пересоберите:

```
tools\tailwindcss.exe -i tools\input.css -o admin\assets\admin.css --minify
```

Сам CLI (`tools/tailwindcss.exe`, ~111 МБ) в пакет/git не входит — скачивается
с GitHub releases tailwindlabs/tailwindcss (tailwindcss-windows-x64.exe).

## Roadmap

Актуальный список изменений по версиям — в разделе **Changelog v** (сверху —
самая новая версия). Правило: каждая новая версия добавляется в начало Changelog.
