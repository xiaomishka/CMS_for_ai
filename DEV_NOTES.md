# DEV_NOTES.md — инструкция для новых чатов (передавай в каждый новый сеанс)

> Файл для ИИ-агента, продолжающего разработку. Читай целиком перед первым действием.
> Основные проектные документы: `ARCHITECTURE.md` (архитектура), `README.md` (пользователю).

## 1. Проект

**CMS_for_ai** — легковесная CMS для статейников. PHP 8.1+ + RedBeanPHP 5.7.6
(один файл `core/rb.php`), БЕЗ composer. MySQL и SQLite одним кодом.
Главная фишка — адаптация любого HTML-шаблона силами ИИ (папка темы `templates/<имя>/`,
теговый шаблонизатор `*.tpl`). Целевой сценарий: скилл в чате «адаптируй шаблон».

**Статус: v0.3 alpha — готова и протестирована.** Полный цикл
установка → фронт → админка проверен на SQLite и MySQL (v0.1, v0.2 тоже пройдены).
Готово в v0.2: роли editor/author с CRUD пользователей, статические страницы
в UI, 301-редиректы + лог 404, RSS + sitemap.xml, админка переведена на
тёмно-синюю тему с локальным Tailwind CSS (`admin/assets/admin.css`).
Готово в v0.3: медиазагрузка (uploads/attachments, таблица `attachments`,
раздел «Медиа» в админке, вставка URL обложки из медиатеки); плагины в UI
(раздел «Плагины», включение/выключение через `active_plugins`, пример
плагина `plugins/example/`); phtml-режим (тема-пример `templates/phtml-example/`
на чистых PHP-шаблонах).

**v1.0 готова**: `agents.md` — спецификация для ИИ-адаптации HTML-шаблонов;
тема-пример `templates/ai-adapted/` — пример адаптации готового HTML-шаблона
силами ИИ; набор автотестов в `core/tests/` (65 тестов, без composer).

## 2. Железные правила (нарушать нельзя)

1. **Никакого composer**. Всё подключается require/require_once.
2. Все шаблоны `.tpl` компилируются ядром; теги НЕ копировать у DLE (юридическая
   чистота: у нас `{{ var }}`, `[each]/[if]/[include]/[slot]`).
3. Данные из `$_POST` никогда не попадают в бин через `import()` — только
   whitelist-присваивания полей (см. admin/index.php:article_save).
4. После установки схема заморожена: `R::freeze(true)`. Новые таблицы/колонки —
   только через миграции ядра/плагинов в fluid-режиме.
5. В коде с namespace ловить исключения как `catch (\Throwable)` — без слэша
   это `Controllers\Throwable` и ловушка не срабатывает (уже наступали).
6. В `bootstrap.php` и `install.php` — только `require_once` + guards на
   define: файлы исполняются дважды в одном процессе на встроенном сервере
   PHP (router script) и в OpenServer Apache+FastCGI.
7. `core/rb.php` пропатчен (константа `RB_PDO_MYSQL_ATTR_INIT_COMMAND` через
   fallback 1002) — не перезатирать при обновлении RedBean, патч нужен снова.
8. Runtime-файлы (core/config.php, core/*.sqlite, core/cache, *.log,
   install.done.php) — не часть пакета, в git/синхронизацию не попадают
   (см. .gitignore и исключения sync-osp.ps1).
9. Тип бина (имя таблицы) у RedBean — только `[a-z0-9]+`: подчёркивания
   запрещены (ловили с `notfound_log` → переименована в `notfound`). Колонки
   со подчёркиваниями (`published_at` и т.п.) разрешены.
10. Таблицы/колонки новых версий создавать только через `Database::migrate()`
    (вызывается из bootstrap при каждом буте; идемпотентно: отсутствие таблицы
    ловим try/catch, создаём в fluid-режиме, freeze возвращаем в finally).

## 3. Карта файлов

```
index.php                точка входа фронта (router script для php -S)
install.php              установщик; самоблокируется в install.done.php
.htaccess                rewrite → index.php; в engine/ core/ plugins/ — deny,
                         в uploads/ — запрет PHP, в templates/ — запрет .tpl/.json
engine/
  bootstrap.php          инициализация: requires, Config, session, R::setup,
                         Database::connect + migrate, Plugins::boot
  Config.php             core/config.php, доступ Config::get('db.type')
  Database.php           connect (mysql|sqlite), install() — схема+сиды,
                         seedRoutes(), migrate() — идемпотентные миграции ядра
  Router.php             роуты из таблицы routes; пресеты в defaults() (+rss.xml,
                         sitemap.xml); match/dispatch; 301 из redirects
  Template.php           компилятор .tpl → PHP с кэшем core/cache (синтаксис внутри)
  View.php               renderPage (layout + контент), режимы tpl|phtml (theme.json)
  Auth.php               сессии, attempt/signIn/logout, can(), csrf*, flash
  Permissions.php        матрица прав admin|editor|author (+redirect.manage)
  Plugins/Hub.php        шина on/emit/filter; Plugins::boot(); PluginInterface
  Controllers/Article.php  home/listing/view + present() (бин → массив шаблона)
  Controllers/Category.php view (fallback: одиночный сегмент = статья без рубрики)
  Controllers/Page.php     view.page, notFound() (+запись в лог notfound), serverError()
  Controllers/Feed.php     rss (/rss.xml), sitemap (/sitemap.xml)
templates/default/       тема-образец (engine tpl): layout, view.*, list.*, partial.*, 404, 500
templates/phtml-example/ тема-пример (engine phtml): layout.phtml, view.*.phtml, partial.*, 404, 500, assets/
admin/index.php          админка: ?page=login|dashboard|articles|...|pages|users|
                         redirects|notfound|settings|media|plugins|plugins_toggle
                         (см. шапку файла)
admin/includes/views.php вьюхи админки (чистый PHP + классы Tailwind)
admin/assets/admin.css   локальный Tailwind (сборка: tools/tailwindcss.exe, §Tailwind в README)
tools/input.css          вход сборки Tailwind (exe в git не входит)
core/rb.php              RedBeanPHP 5.7.6 (пропатчен)
plugins/example/         пример плагина (plugin.json + init.php, хук template.slot:head)
templates/ai-adapted/    пример адаптированной HTML-темы (engine tpl, см. agents.md)
agents.md                спецификация для ИИ-адаптации HTML-шаблонов
core/tests/              автотесты ядра (запуск: php core/tests/run.php)
sync-osp.ps1 / .cmd      синхронизация в домен OSPanel (см. §4)
```

## 4. Среда тестирования

**Путь проекта (правим здесь):** `G:\AI_projects\CMS_for_ai`

**Домен OSPanel (тест здесь):** `G:\OSPanel\home\cms-ai.test`
- После КАЖДОЙ порции правок запускать `sync-osp.ps1` (robocopy /MIR + очистка
  рантайма в домене).
- OSPanel уже запущен у пользователя; PHP 8.2: `G:\OSPanel\modules\PHP-8.2\php.exe`
  (все нужные расширения включены в его php.ini).
- MariaDB-10.6 слушает `127.0.1.16:3306`, root без пароля.
  Клиент: `G:\OSPanel\modules\MariaDB-10.6\bin\mysql.exe`.
- Apache OSP: AllowOverride All + mod_rewrite → .htaccess работает в домене.
- hosts-запись `cms-ai.test` появляется после рестарта панели (домен добавили
  после её запуска) — попросить пользователя перезапустить, если не резолвится.
- Запасной локальный PHP (winget): `C:\Users\admin\AppData\Local\Microsoft\WinGet\`
  `Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`
  (php.ini там же, pdo_sqlite/pdo_mysql/mbstring включены вручную).

## 5. Протокол смоук-теста (после крупных правок)

1. `sync-osp.ps1` → домен чистый.
2. Тест быстрее всего через встроенный сервер OSP-шного PHP:
   `G:\OSPanel\modules\PHP-8.2\php.exe -S 127.0.0.1:8081 -t G:\OSPanel\home\cms-ai.test G:\OSPanel\home\cms-ai.test\index.php`
3. POST `/install.php` (sqlite) → ожидание 302 → dashboard.
4. Проверить: `/`, `/news/`, `/news/dobro-pozhalovat.html`, `/dobro-pozhalovat.html`,
   `/page/2/` → 200; `/nope.html` → 404; `/rss.xml`, `/sitemap.xml` → 200.
5. Админка: GET `?page=login` → взять csrf из формы → POST login → 302 `/admin/`;
   все страницы 200 (dashboard, articles, article_edit, categories, category_edit,
   pages, page_edit, users, user_edit, redirects, redirect_edit, notfound, settings);
   POST без csrf → 403.
6. Создать статью через POST article_save → проверить на фронте; создать страницу
   (page_save → `/page/{slug}.html`), редирект (redirect_save, from без слэша →
   нормализуется) → 301; создать пользователя editor/author.
7. Автор (вход под author): список статей = только свои; article_save со
   status=published → принудительно draft; article_edit чужой статьи → редирект
   в список. Editor: users/redirects/settings → 302 на dashboard.
8. Сменить url_scheme в settings_save → проверить статьи/рубрики/страницы по
   новой схеме (все три пресета должны давать 200; `short` даёт статью без
   рубрики — `/slug.html`).
9. Убрать рантайм-артефакты (config.php, *.sqlite, cache, log, install.done.php →
   переименовать обратно в install.php; удобно — просто перезапустить
   `sync-osp.ps1`, он чистит рантайм и вернёт install.php) и повторить sync.
10. Ошибки смотреть в `core/php-error.log` домена.

## 6. Roadmap (выполнено v0.3, v1.0)

**v0.2 готова** (роли/пользователи, страницы, редиректы + 404-лог, RSS/sitemap,
тёмно-синяя админка на локальном Tailwind).

**v0.3 готова** (все три пункта реализованы и протестированы):

1. **Медиазагрузка**: таблица `attachments` (через `Database::migrate()`),
   раздел «Медиа» в админке (`?page=media`), загрузка изображений (jpg/png/gif/webp,
   до 5 МБ), удаление файлов, копирование URL, вставка в обложку статьи из
   медиатеки. Право `media.manage` (admin, editor).
2. **Плагины в UI**: страница «Плагины» (`?page=plugins`), включение/выключение
   через `active_plugins` (`?page=plugins_toggle`), право `plugins.manage`
   (admin), пример плагина `plugins/example/` (хук `template.slot:head`).
3. **phtml-режим**: тема-пример `templates/phtml-example/` на чистых PHP-
   шаблонах (движок `PhtmlView` встроен, режим выбирается в `theme.json`).

**v1.0 готова**:

4. **agents.md** — спецификация для ИИ-адаптации HTML-шаблонов (карта файлов
   темы, реестр тегов tpl-движка, правила адаптации, режим вебархива, правила
   кода ядра).
5. **Тема-пример адаптации** — `templates/ai-adapted/` demonstrates how to
   adapt a ready-made HTML theme using the tpl engine.
6. **Автотесты** — `core/tests/run.php` (65 тестов, без composer):
   Permissions, Helpers, Router, Template, Database.

Внимание к phtml-шаблонам: внутри включаемого через `$view->inc()` файла
доступна переменная `$v` (экземпляр `PhtmlView`), а не `$view` (см.
`engine/View.php:PhtmlView::inc`).

## 7. Известные мелочи

- При смене url_scheme в настройках: дефолтные роуты (priority<100)
  перезаписываются, кастомные (>=100) сохраняются (Database::seedRoutes).
  Условие смены сравнивается со settings-кэшем ДО записи (не с config.php —
  opcache/статика могут дать устаревшее значение; ловили).
- Старые ссылки после смены схемы НЕ редиректятся автоматически (нет
  redirects-строк) — это штатно: раздел «Редиректы» в админке + 404-лог.
- `settings()` кэширует всё в static — после записи настроек в том же
  запросе не рассчитывать на свежие значения.
- MySQL: колонка settings.`key` — зарезервированное слово, в запросах
  экранировать бэктиками (уже сделано).
- Титул темы берётся из `theme.json` (engine: tpl|phtml). Режим phtml
  встроен (PhtmlView), но темы-примера phtml пока нет.
- Схема ЧПУ `short` даёт статью БЕЗ рубрики (`/slug.html`, роут `/{slug}.html`),
  `clean` — `/рубрика/slug/` без .html; `url_article()` согласован с роутами
  (в v0.1 расходился — правили).
- Встроенный сервер PHP: `php -S` держит opcache для cli-server — после правок
  config.php первой секундой возможен устаревший конфиг (в тестах проявилось);
  в Apache+FastCGI OSP не критично.
