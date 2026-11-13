# ARCHITECTURE.md — легковесная CMS для статейников

> Статус: согласованная архитектура v1.0 (основа для каркаса и agents.md)
> Принципы: без composer, минимум кода, гибкость для форков, адаптация шаблонов силами ИИ.

---

## 1. Стек и принципы

| Решение | Что выбрали | Почему |
|---|---|---|
| Язык | PHP 8.1+ (рекомендуем 8.2) | без зависимостей, есть везде |
| ORM/БД | RedBeanPHP, один файл `core/rb.php` | параметризация (анти-инъекции), fluid-схема на этапе установки |
| Composer | ❌ запрещён | всё подключается `require` |
| БД | MySQL (utf8mb4) **и** SQLite | выбор в install.php, код один и тот же |
| Шаблоны | собственный движок, 2 режима (см. §4) | главная фишка — быстрая ИИ-адаптация |
| Точка входа | один `index.php` + `.htaccess` | front controller, все URL через роутер |

**Железные правила ядра:**
1. Все `R::dispense/import` — только через whitelist полей. Запрещено кормить бину данные напрямую из `$_POST` (mass-assignment).
2. Схема БД после установки — `R::freeze(true)`. Изменения схемы — только через миграции плагинов/версий.
3. Прямой доступ к `engine/` и `admin/includes` — закрыт .htaccess. Всё через `index.php` / `admin/index.php`.
4. Пароли — `password_hash()`, сессии — httponly/samesite, CSRF-токен на всех POST-формах админки.

---

## 2. Структура папок

```
/
├── index.php              # единственная публичная точка входа
├── install.php            # установка (после неё — авто-блокировка)
├── .htaccess              # rewrite → index.php + deny для engine/
├── agents.md              # спецификация для ИИ: шаблоны, правила кода
├── ARCHITECTURE.md        # этот файл
│
├── engine/                # ядро (то, что форкают)
│   ├── bootstrap.php      # config + rb + сессия + загрузка плагинов + dispatch
│   ├── Config.php
│   ├── Database.php       # обёртка над R::setup, freeze, миграции ядра
│   ├── Router.php         # парсинг URL по таблице routes
│   ├── Template.php       # теговый движок (.tpl)
│   ├── View.php           # PHP-движок (.phtml), общий рендер-интерфейс
│   ├── Auth.php           # сессии, login, CSRF
│   ├── Permissions.php    # роли и права (см. §6)
│   ├── Plugins/
│   │   ├── Hub.php        # event hub (см. §5)
│   │   └── PluginInterface.php
│   ├── hooks.php          # реестр точек расширения ядра
│   └── Controllers/
│       ├── Article.php
│       ├── Category.php
│       ├── Page.php       # статические страницы
│       ├── Feed.php       # RSS/sitemap
│       └── Search.php
│
├── admin/                 # админка (свой index.php, своя сессия)
│   ├── index.php
│   └── includes/          # контроллеры/вьюхи админки
│
├── templates/             # тема (структура в §4.3)
│   └── default/
│
├── plugins/               # плагины, по папке на плагин
│   └── example/
│
├── uploads/               # картинки/файлы статей
│
└── core/                  # сгенерированное/локальное, закрыто от веба
    ├── config.php         # пишет install.php
    ├── rb.php             # RedBeanPHP
    └── cms.sqlite         # (если SQLite)
```

---

## 3. База данных (ядро)

RedBean создаёт таблицы сам; install.php прогоняет сид и вешает freeze.

- **users** — email, password_hash, role (admin|editor|author), name, created
- **articles** — title, slug, category_id, author_id, short, full, meta_title,
  meta_description, cover, status (draft|published), published_at, edited_at
- **categories** — title, slug, parent_id, description, meta (дерево через parent_id)
- **pages** — статические страницы (title, slug, content, status)
- **routes** — см. §7 (pattern, controller, params, redirect_to, status)
- **settings** — key/value (название сайта, тема, схема URL, режим шаблонов…)
- **active_plugins** — список включённых плагинов и порядок загрузки
- **attachments** — файлы статей (для медиаменеджера)
- **redirects** — 301/302 редиректы (from_url, to_url, code; UI в админке)
- **notfound** — лог 404 (url, hits, last_at; UI в админке, «404-лог»)

MySQL: utf8mb4 + InnoDB, индексы на slug/category_id/status/published_at.

---

## 4. Шаблонизатор — главная фишка

### 4.1 Юридическая чистота (важно)
Синтаксис **осознанно не копирует DLE**: другие ограничители, другие имена тегов,
другая структура файлов темы. Совпадает только общая *идея* «папка с tpl-файлами»,
которая не охраняется. Оригинальный код парсера, документация, дистрибутив — свои.

### 4.2 Режим A — теговый `.tpl` (по умолчанию, для ИИ-адаптации)

Ограничители: `{{ ... }}` для переменных и `[...] ... [/...]` для блоков.

```
{{ site.name }}                    — простая подстановка (авто-экранирование)
{{ article.title|raw }}            — без экранирования (только для контента с wysiwyg)
{{ article.date|date:"d.m.Y" }}    — модификатор

[each articles as a]
  <h2><a href="{{ a.url }}">{{ a.title }}</a></h2>
  [if a.cover]<img src="{{ a.cover }}">[/if]
  {{ a.short }}
[/each]

[if user.can("edit")] ... [/if]    — доступ к правам из шаблона
[include partial.sidebar]          — вставка другого tpl
[slot article.header]              — хук-слот для плагинов (см. §5)
```

Парсер: один класс ~150–200 строк. str_replace по плоским тегам,
regex по each/if/include/slot. Кэш скомпилированных шаблонов в `core/cache/`.

### 4.3 Структура темы (имена НЕ как в DLE)

```
templates/default/
├── theme.json          # метаданные: name, engine ("tpl"|"phtml"), схема URL по умолчанию
├── layout.tpl          # общий каркас (аналог main, но имя другое)
├── view.article.tpl    # полная статья
├── view.category.tpl   # рубрика
├── view.page.tpl
├── list.home.tpl       # главная
├── list.search.tpl
├── partial.news.tpl    # аномсеры в циклах
├── partial.sidebar.tpl
├── 404.tpl
└── assets/ (css/js/img)
```

Маппинг «страница → шаблон» описан в theme.json — ИИ при адаптации меняет
разметку, но не трогает ядро.

### 4.4 Режим B — PHP `.phtml` (продвинутый, для форков)

Тот же `View.php` умеет включать чистые PHP-шаблоны:
- все данные приходят как `$this->…` (read-only view-модель);
- хелпер `e($x)` для экранирования по умолчанию;
- доступ к хукам: `$this->slot('article.header')`.

Режим выбирается в theme.json (`"engine": "phtml"`), можно смешивать
(layout в phtml, партиалы в tpl и наоборот — рендер-интерфейс общий).

---

## 5. Плагинная система

Плагин = папка `plugins/<name>/`:

```
plugins/<name>/
├── plugin.json      # name, version, description, hooks[]
└── init.php         # <?php return new \Plugins\<Name>\Plugin();
```

`init.php` обязан вернуть объект, реализующий `PluginInterface`:
`register(Hub $hub): void` — внутри подписки на хуки. Никакого автозагрузчика
на весь глобальный неймспейс: плагин сам подключает свои файлы (без composer).

### Hub (event bus)
- `on("event", fn, priority)` / `emit("event", payload)` — действия
- `filter("event", value, payload)` — фильтры, возвращают изменённое значение

### Точки расширения (hooks.php — единый реестр)
| Хук | Тип | Назначение |
|---|---|---|
| `app.boot` | action | после bootstrap, до роутинга |
| `route.resolve` | filter | переопределить роут |
| `article.query` | filter | изменить выборку статей |
| `article.save.before / after` | action | валидация, доп.поля |
| `article.render` | filter | правка HTML контента |
| `template.tag` | filter | **регистрация своих тегов** в tpl-движке |
| `template.slot:<name>` | filter | вывод в `[slot ...]` шаблонов |
| `admin.menu` | filter | пункты меню админки |
| `admin.page:<slug>` | action | свои страницы в админке |
| `install.after` | action | свои миграции при активации |

Плагин включается в админке → пишется в `active_plugins` → bootstrap грузит их
по порядку. Миграции плагина — по собственной версии в plugin.json (RedBean
добавляет колонки fluid-ом до freeze или через R::exec ALTER).

---

## 6. Роли и права

Фиксированные роли + матрица прав в `Permissions.php` (единый массив, легко
расширять плагином):

| Право | admin | editor | author |
|---|---|---|---|
| статьи: создавать | ✓ | ✓ | ✓ |
| статьи: править любые | ✓ | ✓ | — |
| статьи: публиковать | ✓ | ✓ | — |
| статьи: удалять | ✓ | ✓ | — |
| рубрики/страницы | ✓ | ✓ (создавать — admin) | — |
| настройки, темы, роуты | ✓ | — | — |
| пользователи, плагины | ✓ | — | — |

Проверка: `Auth::can("article.edit.any")` — одна функция и в PHP, и в шаблонах
(`[if user.can(...)]`). Админ в install.php — первый, роль admin;
доп. пользователей с ролью admin/editor/author создаёт админ в панели.

---

## 7. ЧПУ и восстановление иерархии

Ядро роутится **по таблице routes**, а не по жёстким регуляркам:

- `pattern` — шаблон URL со слотами: `/category/{cat}/`, `/category/{cat}/{slug}.html`
- `controller` + `params` — куда мапится
- `redirect_to` + `status` (301/302) — для миграции старого сайта

**Пресеты схемы URL** (настройка в админке):
1. `short` — `/{slug}.html`, `/{cat}/`
2. `hierarchical` (по умолчанию) — `/{cat}/{slug}.html` (DLE-привычный вид)
3. `clean` — `/{cat}/{slug}/`, главная — `/`
4. `custom` — свои правила в таблице routes (восстановление вебархива: любая
   старая иерархия маппится на те же контроллеры, а несуществующие URL — 301
   на новые).

Slug: автогенерация из заголовка (транслит) + уникальность + ручное
переопределение. При смене slug старый адрес автоматически уходит в redirects.

---

## 8. install.php

1. Проверка окружения (версия PHP, доступ на запись core/, uploads/).
2. Выбор БД: SQLite (путь) или MySQL (host, base, user, pass).
3. Админ: email + пароль (+ название сайта).
4. Выбор темы и схемы URL (можно пройти с дефолтом).
5. Прогон сидов, `R::freeze(true)`, запись `core/config.php`,
   **блокировка инсталлятора** (флаг в config + переименование install.php в
   install.done), редирект в админку.

---

## 9. agents.md (для ИИ-адаптации шаблонов) — состав

1. Карта файлов темы: какой tpl за какую страницу отвечает (из §4.3).
2. Полный реестр тегов tpl-движка с примерами (§4.2) + какие данные приходят
   в each-цикл (поля article: url, title, short, cover, date, category…).
3. Правила адаптации: layout → разрезка на partials, замена контентных зон
   тегами, куда класть assets, что выносить в theme.json.
4. Режим вебархива: сохранить исходную иерархию URL → составить routes,
   расставить 301.
5. Правила кода ядра (для форков): whitelist полей, freeze, CSRF,
   запрет composer, стиль кода.

---

## 10. Roadmap

- **v0.1** — каркас: bootstrap, Router+routes, Template (режим A), Auth,
  install.php, CRUD статей/категорий, тема default.
- **v0.2** ✅ — роли editor/author + CRUD пользователей, страницы в UI,
  redirects + 404-лог, RSS/sitemap, тёмно-синяя админка на локальном Tailwind.
- **v0.3** ✅ — медиазагрузка (`attachments`, раздел «Медиа» в админке), плагины
  в UI (раздел «Плагины», `active_plugins`, пример `plugins/example/`),
  phtml-режим (тема-пример `templates/phtml-example/`).
- **v1.0** ✅ — `agents.md` (спецификация ИИ-адаптации шаблонов), тема-пример
  `templates/ai-adapted/` (адаптация готового HTML-шаблона), автотесты
  `core/tests/` (65 тестов, без composer).
- **v1.11** ✅ — TinyMCE (локально, `admin/assets/tinymce/`) — визуальный редактор
  для статей и страниц; редактор шаблонов (ACE, `admin/assets/ace/`) — раздел
  «Шаблоны тем» в админке, право `templates.manage` (только admin).
