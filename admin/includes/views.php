<?php
/**
 * views.php — HTML-вьюхи админки (обычный PHP, без шаблонизатора).
 * Оформление: тёмно-синяя тема на локальном Tailwind CSS
 * (admin/assets/admin.css, собран tools/tailwindcss.exe — без CDN).
 */

declare(strict_types=1);

function admin_head_css(): string
{
    return '<link rel="stylesheet" href="/admin/assets/admin.css?v=2">';
}

/* ── Классы-константы (чтобы не дублировать) ─────────────────── */

function admin_btn(string $kind = 'primary', string $extra = ''): string
{
    [$bg, $fg] = match ($kind) {
        'danger'  => ['bg-red-700 hover:bg-red-600', 'text-white'],
        'ghost'   => ['border border-blue-800/60 bg-blue-950 hover:bg-blue-900/60', 'text-slate-300 hover:text-white'],
        default   => ['bg-blue-600 hover:bg-blue-500', 'text-white'],
    };
    return 'inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium transition-colors '
        . $bg . ' ' . $fg . ($extra ? ' ' . $extra : '');
}

function admin_input(string $extra = ''): string
{
    return 'w-full rounded-md border border-blue-800/60 bg-slate-900 px-3 py-2 text-sm text-slate-100 '
        . 'placeholder-slate-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500'
        . ($extra ? ' ' . $extra : '');
}

function admin_label(string $text, string $hint = ''): string
{
    return '<label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-400">'
        . e($text) . ($hint ? ' <span class="normal-case text-slate-500">' . e($hint) . '</span>' : '')
        . '</label>';
}

function admin_badge(string $status): string
{
    if ($status === 'published') {
        return '<span class="inline-block rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-300">опубликовано</span>';
    }
    return '<span class="inline-block rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-semibold text-amber-300">черновик</span>';
}

function admin_role_badge(string $role): string
{
    $map = [
        'admin'  => ['bg-blue-500/15 text-blue-300', 'админ'],
        'editor' => ['bg-violet-500/15 text-violet-300', 'редактор'],
        'author' => ['bg-teal-500/15 text-teal-300', 'автор'],
    ];
    [$cls, $label] = $map[$role] ?? ['bg-slate-500/15 text-slate-300', $role];
    return '<span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $cls . '">' . e($label) . '</span>';
}

/* ── Каркас ──────────────────────────────────────────────────── */

function admin_layout(string $title, string $content, string $active = ''): string
{
    $user = Auth::user();
    $siteName = settings('site_name', 'CMS');

    $menu = [
        'dashboard' => ['Обзор', '?page=dashboard', 'all'],
    ];
    if (Auth::can('article.create') || Auth::can('article.edit.any')) {
        $menu['articles'] = ['Статьи', '?page=articles', 'article.create'];
    }
    if (Auth::can('category.manage')) {
        $menu['categories'] = ['Рубрики', '?page=categories', 'category.manage'];
    }
    if (Auth::can('page.manage')) {
        $menu['pages'] = ['Страницы', '?page=pages', 'page.manage'];
    }
    if (Auth::can('media.manage')) {
        $menu['media'] = ['Медиа', '?page=media', 'media.manage'];
    }
    if (Auth::can('redirect.manage')) {
        $menu['redirects'] = ['Редиректы', '?page=redirects', 'redirect.manage'];
        $menu['notfound']  = ['404-лог', '?page=notfound', 'redirect.manage'];
    }
    if (Auth::can('users.manage')) {
        $menu['users'] = ['Пользователи', '?page=users', 'users.manage'];
    }
    if (Auth::can('settings.manage')) {
        $menu['settings'] = ['Настройки', '?page=settings', 'settings.manage'];
    }
    if (Auth::can('templates.manage')) {
        $menu['templates'] = ['Шаблоны', '?page=templates', 'templates.manage'];
    }
    if (Auth::can('plugins.manage')) {
        $menu['plugins'] = ['Плагины', '?page=plugins', 'plugins.manage'];
    }
    $menu = Plugins::filter('admin.menu', $menu);

    $nav = '';
    foreach ($menu as $key => [$label, $url]) {
        if ($key === $active) {
            $nav .= '<a href="' . e($url) . '" class="flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white">' . e($label) . '</a>';
        } else {
            $nav .= '<a href="' . e($url) . '" class="flex items-center rounded-md px-3 py-2 text-sm text-slate-300 hover:bg-blue-900/60 hover:text-white">' . e($label) . '</a>';
        }
    }

    $flash = '';
    foreach (Auth::takeFlash() as $f) {
        if ($f['t'] === 'err') {
            $flash .= '<div class="mb-5 rounded-md border border-red-800/60 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">' . e($f['m']) . '</div>';
        } else {
            $flash .= '<div class="mb-5 rounded-md border border-emerald-800/60 bg-emerald-500/10 px-4 py-2.5 text-sm text-emerald-300">' . e($f['m']) . '</div>';
        }
    }

    return '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . ' — ' . e($siteName) . '</title>'
        . admin_head_css() . '</head>'
        . '<body class="min-h-screen bg-slate-950 text-slate-200 antialiased">'
        . '<div class="flex min-h-screen">'
        . '<aside class="flex w-60 shrink-0 flex-col bg-blue-950/80 border-r border-blue-800/50">'
        . '<div class="border-b border-blue-800/50 px-5 py-4">'
        . '<div class="truncate text-sm font-bold text-white">' . e($siteName) . '</div>'
        . '<div class="mt-0.5 text-xs text-slate-400">админ-панель · v1.1</div></div>'
        . '<nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">' . $nav . '</nav>'
        . '<div class="border-t border-blue-800/50 px-5 py-4 text-xs text-slate-400">'
        . '<div class="truncate font-semibold text-slate-300">' . e($user['name'] ?? '') . '</div>'
        . '<div class="mt-0.5">' . e($user['email'] ?? '') . ' · ' . e($user['role'] ?? '') . '</div>'
        . '<div class="mt-3 flex gap-3"><a href="/" target="_blank" class="text-blue-400 hover:text-blue-300">Сайт</a>'
        . '<a href="?page=logout" class="text-red-400 hover:text-red-300">Выйти</a></div></div>'
        . '</aside>'
        . '<main class="min-w-0 flex-1 px-8 py-8"><div class="max-w-5xl">' . $flash . $content . '</div></main>'
        . '<script>'
        . 'function copyUrl(btn,url){navigator.clipboard.writeText(url).then(()=>{const old=btn.innerText;btn.innerText="скопировано";setTimeout(()=>btn.innerText=old,1200);});}'
        . 'function setCoverUrl(url){const w=window.opener;if(w){try{w.document.getElementById("cover").value=url;w.focus();}catch(e){}}if(window.close){window.close();}}'
        . '</script>'
        . '</div></body></html>';
}

function admin_login_page(string $error = ''): string
{
    $err = $error
        ? '<div class="mb-4 rounded-md border border-red-800/60 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">' . e($error) . '</div>'
        : '';
    return '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Вход — CMS</title>'
        . admin_head_css() . '</head>'
        . '<body class="flex min-h-screen items-center justify-center bg-slate-950 text-slate-200 antialiased">'
        . '<div class="w-full max-w-sm rounded-xl border border-blue-800/50 bg-blue-950/70 p-8 shadow-2xl shadow-blue-950">'
        . '<div class="mb-6 text-center"><div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-lg bg-blue-600 text-lg font-black text-white">C</div>'
        . '<h1 class="text-lg font-bold text-white">Вход в админку</h1></div>'
        . $err
        . '<form method="post" action="?page=login" class="space-y-4">' . Auth::csrfField()
        . '<div>' . admin_label('E-mail') . '<input type="email" name="email" required autofocus class="' . admin_input() . '"></div>'
        . '<div>' . admin_label('Пароль') . '<input type="password" name="password" required class="' . admin_input() . '"></div>'
        . '<button type="submit" class="' . admin_btn() . ' w-full justify-center py-2.5">Войти</button>'
        . '</form></div></body></html>';
}

/* ── Обзор ───────────────────────────────────────────────────── */

function admin_dashboard(array $stats): string
{
    $tiles = '';
    $i = 0;
    foreach ($stats as $label => $value) {
        $tiles .= '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
            . '<div class="text-2xl font-bold text-white">' . e((string)$value) . '</div>'
            . '<div class="mt-1 text-xs uppercase tracking-wider text-slate-400">' . e($label) . '</div></div>';
        $i++;
    }
    $tiles = '<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">' . $tiles . '</div>';

    $quick = '<div class="card-none mb-6 rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-slate-400">Быстрые действия</h2>'
        . '<div class="flex flex-wrap gap-3">'
        . '<a class="' . admin_btn() . '" href="?page=article_edit">+ Новая статья</a>';
    if (Auth::can('category.manage')) {
        $quick .= '<a class="' . admin_btn('ghost') . '" href="?page=category_edit">+ Новая рубрика</a>';
    }
    if (Auth::can('page.manage')) {
        $quick .= '<a class="' . admin_btn('ghost') . '" href="?page=page_edit">+ Новая страница</a>';
    }
    $quick .= '<a class="' . admin_btn('ghost') . '" href="/rss.xml" target="_blank">RSS</a>'
        . '<a class="' . admin_btn('ghost') . '" href="/sitemap.xml" target="_blank">Sitemap</a>'
        . '</div></div>';

    $content = '<h1 class="mb-6 text-xl font-bold text-white">Обзор</h1>' . $tiles . $quick
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<p class="text-xs text-slate-500">v1.1 — статьи, рубрики, статические страницы, пользователи и роли, '
        . '301-редиректы с логом 404, RSS и sitemap. Медиазагрузка и плагины — в следующих версиях.</p></div>';
    return admin_layout('Обзор', $content, 'dashboard');
}

/* ── Статьи ──────────────────────────────────────────────────── */

function admin_articles_list(array $items, int $page, int $pages): string
{
    $rows = '';
    foreach ($items as $a) {
        $rows .= '<tr>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><a class="font-medium text-blue-300 hover:text-blue-200" href="?page=article_edit&id=' . (int)$a['id'] . '">' . e($a['title']) . '</a>'
            . '<div class="mt-0.5 text-xs text-slate-500">/' . e($a['slug']) . '</div></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($a['category']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top">' . admin_badge($a['status']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($a['date']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 align-top text-sm"><div class="flex gap-3">'
            . '<a class="text-blue-400 hover:text-blue-300" href="?page=article_edit&id=' . (int)$a['id'] . '">править</a>'
            . '<a class="text-slate-400 hover:text-slate-300" href="' . e($a['url']) . '" target="_blank">открыть</a>'
            . '</div></td></tr>';
    }
    $pager = '';
    if ($pages > 1) {
        $links = [];
        for ($i = 1; $i <= $pages; $i++) {
            $links[] = $i === $page
                ? '<span class="inline-block rounded-md bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white">' . $i . '</span>'
                : '<a class="inline-block rounded-md px-2.5 py-1 text-xs text-slate-400 hover:bg-blue-900/60 hover:text-white" href="?page=articles&p=' . $i . '">' . $i . '</a>';
        }
        $pager = '<div class="mt-4 flex flex-wrap gap-1.5">' . implode('', $links) . '</div>';
    }
    $content = '<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold text-white">Статьи</h1>'
        . '<a class="' . admin_btn() . '" href="?page=article_edit">+ Новая статья</a></div>'
        . '<div class="overflow-x-auto rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<table class="w-full"><thead><tr>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Заголовок</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Рубрика</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Статус</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Дата</th>'
        . '<th class="border-b border-blue-800/50 pb-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"></th>'
        . '</tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="py-4 text-sm text-slate-500">Пока нет статей</td></tr>') . '</tbody></table>'
        . $pager . '</div>';
    return admin_layout('Статьи', $content, 'articles');
}

function admin_article_form(array $cats, array $article, bool $isNew): string
{
    $catOptions = '<option value="0">— без рубрики —</option>';
    foreach ($cats as $c) {
        $sel = ((int)$article['category_id'] === (int)$c['id']) ? ' selected' : '';
        $catOptions .= '<option value="' . (int)$c['id'] . '"' . $sel . '>' . e($c['title']) . '</option>';
    }
    $statusOptions = '';
    foreach (['draft' => 'Черновик', 'published' => 'Опубликовано'] as $k => $label) {
        $statusOptions .= '<option value="' . $k . '"' . ($article['status'] === $k ? ' selected' : '') . '>' . $label . '</option>';
    }
    $delete = $isNew ? '' : ' <a class="' . admin_btn('danger') . '" href="?page=article_delete&id=' . (int)$article['id']
        . '" onclick="return confirm(\'Удалить статью?\')">Удалить</a>';
    $tinymce = '<script src="/admin/assets/tinymce/tinymce.min.js"></script>'
        . '<script>'
        . 'tinymce.baseURL = "/admin/assets/tinymce";'
        . 'tinymce.init({'
        . 'selector: "textarea.tinymce",'
        . 'skin: "oxide",'
        . 'content_css: "/admin/assets/tinymce/skins/content/default/content.min.css",'
        . 'toolbar: "undo redo | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | blockquote code",'
        . 'plugins: "lists link image media code",'
        . 'branding: false,'
        . 'height: 400,'
        . '});'
        . '</script>';
    $content = '<h1 class="mb-6 text-xl font-bold text-white">' . ($isNew ? 'Новая статья' : 'Правка статьи') . '</h1>'
        . '<form method="post" action="?page=article_save&id=' . (int)$article['id'] . '">' . Auth::csrfField()
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<div class="mb-4">' . admin_label('Заголовок') . '<input type="text" name="title" required class="' . admin_input() . '" value="' . e($article['title']) . '"></div>'
        . '<div class="mb-4 grid grid-cols-2 gap-x-4 gap-y-4 lg:grid-cols-4">'
        . '<div>' . admin_label('URL (slug)', 'пусто = из заголовка') . '<input type="text" name="slug" class="' . admin_input() . '" value="' . e($article['slug']) . '"></div>'
        . '<div>' . admin_label('Рубрика') . '<select name="category_id" class="' . admin_input() . '">' . $catOptions . '</select></div>'
        . '<div>' . admin_label('Статус') . '<select name="status" class="' . admin_input() . '">' . $statusOptions . '</select></div>'
        . '<div>' . admin_label('Дата публикации') . '<input type="datetime-local" name="published_at" class="' . admin_input() . '" value="' . e($article['published_at']) . '"></div>'
        . '</div>'
        . '<div class="mb-4">' . admin_label('Короткое описание (анонс)') . '<textarea name="short" rows="3" class="tinymce ' . admin_input() . ' resize-y">' . e($article['short']) . '</textarea></div>'
        . '<div class="mb-4">' . admin_label('Полный текст (HTML)') . '<textarea name="full" rows="14" class="tinymce ' . admin_input() . ' font-mono resize-y">' . e($article['full']) . '</textarea></div>'
        . '<div class="mb-4 grid grid-cols-2 gap-x-4">'
        . '<div>' . admin_label('Обложка (URL картинки)') . '<input type="text" name="cover" id="cover" class="' . admin_input() . '" value="' . e($article['cover']) . '"><button type="button" class="mt-2 ' . admin_btn('ghost') . ' px-3 py-2 text-xs" onclick="window.open(\'?page=media\', \'media\', \'width=900,height=700\')">из медиатеки</button></div>'
        . '<div>' . admin_label('SEO title') . '<input type="text" name="meta_title" class="' . admin_input() . '" value="' . e($article['meta_title']) . '"></div>'
        . '</div>'
        . '<div class="mb-5">' . admin_label('SEO description') . '<textarea name="meta_description" rows="3" class="' . admin_input() . ' resize-y">' . e($article['meta_description']) . '</textarea></div>'
        . '<div class="flex flex-wrap items-center gap-3">'
        . '<button class="' . admin_btn() . '" type="submit">Сохранить</button>' . $delete
        . '<a class="text-sm text-slate-400 hover:text-slate-300" href="/" target="_blank">Открыть сайт</a>'
        . '</div></div></form>' . $tinymce;
    return admin_layout($isNew ? 'Новая статья' : 'Правка статьи', $content, 'articles');
}

/* ── Рубрики ─────────────────────────────────────────────────── */

function admin_categories_list(array $items): string
{
    $rows = '';
    foreach ($items as $c) {
        $rows .= '<tr>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><a class="font-medium text-blue-300 hover:text-blue-200" href="?page=category_edit&id=' . (int)$c['id'] . '">' . e($c['title']) . '</a>'
            . '<div class="mt-0.5 text-xs text-slate-500">/' . e($c['slug']) . '/</div></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($c['parent']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . (int)$c['count'] . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 align-top text-sm"><a class="text-blue-400 hover:text-blue-300" href="?page=category_edit&id=' . (int)$c['id'] . '">править</a></td></tr>';
    }
    $content = '<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold text-white">Рубрики</h1>'
        . '<a class="' . admin_btn() . '" href="?page=category_edit">+ Новая рубрика</a></div>'
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<table class="w-full"><thead><tr>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Название</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Родитель</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Статей</th>'
        . '<th class="border-b border-blue-800/50 pb-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"></th>'
        . '</tr></thead><tbody>' . ($rows ?: '<tr><td colspan="4" class="py-4 text-sm text-slate-500">Пока нет рубрик</td></tr>') . '</tbody></table></div>';
    return admin_layout('Рубрики', $content, 'categories');
}

function admin_category_form(array $cats, array $category, bool $isNew): string
{
    $parentOptions = '<option value="0">— корень —</option>';
    foreach ($cats as $c) {
        if (!$isNew && (int)$c['id'] === (int)$category['id']) {
            continue;
        }
        $sel = ((int)$category['parent_id'] === (int)$c['id']) ? ' selected' : '';
        $parentOptions .= '<option value="' . (int)$c['id'] . '"' . $sel . '>' . e($c['title']) . '</option>';
    }
    $delete = $isNew ? '' : ' <a class="' . admin_btn('danger') . '" href="?page=category_delete&id=' . (int)$category['id']
        . '" onclick="return confirm(\'Удалить рубрику? Статьи останутся без рубрики.\')">Удалить</a>';
    $content = '<h1 class="mb-6 text-xl font-bold text-white">' . ($isNew ? 'Новая рубрика' : 'Правка рубрики') . '</h1>'
        . '<form method="post" action="?page=category_save&id=' . (int)$category['id'] . '">' . Auth::csrfField()
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<div class="mb-4">' . admin_label('Название') . '<input type="text" name="title" required class="' . admin_input() . '" value="' . e($category['title']) . '"></div>'
        . '<div class="mb-4 grid grid-cols-2 gap-x-4">'
        . '<div>' . admin_label('URL (slug)', 'пусто = из названия') . '<input type="text" name="slug" class="' . admin_input() . '" value="' . e($category['slug']) . '"></div>'
        . '<div>' . admin_label('Родитель') . '<select name="parent_id" class="' . admin_input() . '">' . $parentOptions . '</select></div>'
        . '</div>'
        . '<div class="mb-5">' . admin_label('Описание') . '<textarea name="description" rows="3" class="' . admin_input() . ' resize-y">' . e($category['description']) . '</textarea></div>'
        . '<div class="flex items-center gap-3"><button class="' . admin_btn() . '" type="submit">Сохранить</button>' . $delete . '</div>'
        . '</div></form>';
    return admin_layout($isNew ? 'Новая рубрика' : 'Правка рубрики', $content, 'categories');
}

/* ── Пользователи и роли ─────────────────────────────────────── */

function admin_users_list(array $items): string
{
    $rows = '';
    foreach ($items as $u) {
        $rows .= '<tr>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><span class="font-medium text-slate-200">' . e($u['name']) . '</span>'
            . '<div class="mt-0.5 text-xs text-slate-500">' . e($u['email']) . '</div></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top">' . admin_role_badge($u['role']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($u['created']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 align-top text-sm"><div class="flex gap-3">'
            . '<a class="text-blue-400 hover:text-blue-300" href="?page=user_edit&id=' . (int)$u['id'] . '">править</a>'
            . ($u['is_self']
                ? '<span class="text-slate-600" title="Нельзя удалить свой аккаунт">удалить</span>'
                : '<a class="text-red-400 hover:text-red-300" href="?page=user_delete&id=' . (int)$u['id'] . '" onclick="return confirm(\'Удалить пользователя?\')">удалить</a>')
            . '</div></td></tr>';
    }
    $content = '<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold text-white">Пользователи</h1>'
        . '<a class="' . admin_btn() . '" href="?page=user_edit">+ Новый пользователь</a></div>'
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<table class="w-full"><thead><tr>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Пользователь</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Роль</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Создан</th>'
        . '<th class="border-b border-blue-800/50 pb-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table></div>'
        . '<div class="mt-4 rounded-lg border border-blue-800/40 bg-blue-950/50 p-4 text-xs text-slate-500">'
        . '<span class="font-semibold text-slate-400">Роли:</span> '
        . '<span class="' . e('text-blue-300') . '">admin</span> — всё; '
        . '<span class="text-violet-300">editor</span> — все статьи, рубрики и страницы; '
        . '<span class="text-teal-300">author</span> — только свои статьи, без публикации.</div>';
    return admin_layout('Пользователи', $content, 'users');
}

function admin_user_form(array $user, bool $isNew, bool $isSelf): string
{
    $roleOptions = '';
    foreach (['admin' => 'Администратор', 'editor' => 'Редактор', 'author' => 'Автор'] as $k => $label) {
        $sel = ($user['role'] === $k) ? ' selected' : '';
        $roleOptions .= '<option value="' . $k . '"' . $sel . '>' . $label . '</option>';
    }
    $content = '<h1 class="mb-6 text-xl font-bold text-white">' . ($isNew ? 'Новый пользователь' : 'Правка пользователя') . '</h1>'
        . '<form method="post" action="?page=user_save&id=' . (int)$user['id'] . '">' . Auth::csrfField()
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<div class="mb-4 grid grid-cols-2 gap-x-4">'
        . '<div>' . admin_label('Имя') . '<input type="text" name="name" required class="' . admin_input() . '" value="' . e($user['name']) . '"></div>'
        . '<div>' . admin_label('E-mail') . '<input type="email" name="email" required class="' . admin_input() . '" value="' . e($user['email']) . '"></div>'
        . '</div>'
        . '<div class="mb-4">'
        . admin_label('Роль')
        . ($isSelf && !$isNew
            ? '<input type="hidden" name="role" value="admin"><select disabled class="' . admin_input() . ' opacity-60">' . $roleOptions . '</select>'
            . '<div class="mt-1 text-xs text-slate-500">Свою роль изменить нельзя (защита от блокировки).</div>'
            : '<select name="role" class="' . admin_input() . '">' . $roleOptions . '</select>')
        . '</div>'
        . '<div class="mb-5">'
        . admin_label('Пароль', $isNew ? 'мин. 8 символов' : 'пусто = не менять')
        . '<input type="password" name="password"' . ($isNew ? ' required minlength="8"' : '') . ' class="' . admin_input() . '" autocomplete="new-password">'
        . '</div>'
        . '<div class="flex items-center gap-3"><button class="' . admin_btn() . '" type="submit">Сохранить</button>'
        . '<a class="text-sm text-slate-400 hover:text-slate-300" href="?page=users">Отмена</a></div>'
        . '</div></form>';
    return admin_layout($isNew ? 'Новый пользователь' : 'Правка пользователя', $content, 'users');
}

/* ── Статические страницы ────────────────────────────────────── */

function admin_pages_list(array $items): string
{
    $rows = '';
    foreach ($items as $p) {
        $rows .= '<tr>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><a class="font-medium text-blue-300 hover:text-blue-200" href="?page=page_edit&id=' . (int)$p['id'] . '">' . e($p['title']) . '</a>'
            . '<div class="mt-0.5 text-xs text-slate-500">' . e($p['url']) . '</div></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top">' . admin_badge($p['status']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($p['edited']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 align-top text-sm"><div class="flex gap-3">'
            . '<a class="text-blue-400 hover:text-blue-300" href="?page=page_edit&id=' . (int)$p['id'] . '">править</a>'
            . '<a class="text-red-400 hover:text-red-300" href="?page=page_delete&id=' . (int)$p['id'] . '" onclick="return confirm(\'Удалить страницу?\')">удалить</a>'
            . '</div></td></tr>';
    }
    $content = '<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold text-white">Страницы</h1>'
        . '<a class="' . admin_btn() . '" href="?page=page_edit">+ Новая страница</a></div>'
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<table class="w-full"><thead><tr>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Заголовок</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Статус</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Изменена</th>'
        . '<th class="border-b border-blue-800/50 pb-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"></th>'
        . '</tr></thead><tbody>' . ($rows ?: '<tr><td colspan="4" class="py-4 text-sm text-slate-500">Пока нет страниц</td></tr>') . '</tbody></table></div>';
    return admin_layout('Страницы', $content, 'pages');
}

function admin_page_form(array $page, bool $isNew): string
{
    $statusOptions = '';
    foreach (['draft' => 'Черновик', 'published' => 'Опубликовано'] as $k => $label) {
        $statusOptions .= '<option value="' . $k . '"' . ($page['status'] === $k ? ' selected' : '') . '>' . $label . '</option>';
    }
    $delete = $isNew ? '' : ' <a class="' . admin_btn('danger') . '" href="?page=page_delete&id=' . (int)$page['id']
        . '" onclick="return confirm(\'Удалить страницу?\')">Удалить</a>';
    $tinymce = '<script src="/admin/assets/tinymce/tinymce.min.js"></script>'
        . '<script>'
        . 'tinymce.baseURL = "/admin/assets/tinymce";'
        . 'tinymce.init({'
        . 'selector: "textarea.tinymce",'
        . 'skin: "oxide",'
        . 'content_css: "/admin/assets/tinymce/skins/content/default/content.min.css",'
        . 'toolbar: "undo redo | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | blockquote code",'
        . 'plugins: "lists link image media code",'
        . 'branding: false,'
        . 'height: 400,'
        . '});'
        . '</script>';
    $content = '<h1 class="mb-6 text-xl font-bold text-white">' . ($isNew ? 'Новая страница' : 'Правка страницы') . '</h1>'
        . '<form method="post" action="?page=page_save&id=' . (int)$page['id'] . '">' . Auth::csrfField()
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<div class="mb-4">' . admin_label('Заголовок') . '<input type="text" name="title" required class="' . admin_input() . '" value="' . e($page['title']) . '"></div>'
        . '<div class="mb-4 grid grid-cols-2 gap-x-4">'
        . '<div>' . admin_label('URL (slug)', 'пусто = из заголовка') . '<input type="text" name="slug" class="' . admin_input() . '" value="' . e($page['slug']) . '"></div>'
        . '<div>' . admin_label('Статус') . '<select name="status" class="' . admin_input() . '">' . $statusOptions . '</select></div>'
        . '</div>'
        . '<div class="mb-4">' . admin_label('Контент (HTML)') . '<textarea name="content" rows="12" class="tinymce ' . admin_input() . ' font-mono resize-y">' . e($page['content']) . '</textarea></div>'
        . '<div class="mb-4 grid grid-cols-2 gap-x-4">'
        . '<div>' . admin_label('SEO title') . '<input type="text" name="meta_title" class="' . admin_input() . '" value="' . e($page['meta_title']) . '"></div>'
        . '<div>' . admin_label('SEO description') . '<input type="text" name="meta_description" class="' . admin_input() . '" value="' . e($page['meta_description']) . '"></div>'
        . '</div>'
        . '<div class="flex flex-wrap items-center gap-3">'
        . '<button class="' . admin_btn() . '" type="submit">Сохранить</button>' . $delete
        . ($isNew ? '' : '<a class="text-sm text-slate-400 hover:text-slate-300" href="' . e(url_page($page['slug'])) . '" target="_blank">Открыть на сайте</a>')
        . '</div></div></form>' . $tinymce;
    return admin_layout($isNew ? 'Новая страница' : 'Правка страницы', $content, 'pages');
}

/* ── Редиректы и лог 404 ─────────────────────────────────────── */

function admin_redirects_list(array $items): string
{
    $rows = '';
    foreach ($items as $r) {
        $rows .= '<tr>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><span class="font-mono text-sm text-slate-200">' . e($r['from']) . '</span></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><span class="font-mono text-sm text-blue-300">' . e($r['to']) . '</span></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><span class="inline-block rounded-full bg-blue-500/15 px-2.5 py-0.5 text-xs font-semibold text-blue-300">' . (int)$r['code'] . '</span></td>'
            . '<td class="border-b border-blue-800/30 py-3 align-top text-sm"><div class="flex gap-3">'
            . '<a class="text-blue-400 hover:text-blue-300" href="?page=redirect_edit&id=' . (int)$r['id'] . '">править</a>'
            . '<a class="text-red-400 hover:text-red-300" href="?page=redirect_delete&id=' . (int)$r['id'] . '" onclick="return confirm(\'Удалить редирект?\')">удалить</a>'
            . '</div></td></tr>';
    }
    $content = '<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold text-white">301-редиректы</h1>'
        . '<a class="' . admin_btn() . '" href="?page=redirect_edit">+ Новый редирект</a></div>'
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<table class="w-full"><thead><tr>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Откуда</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Куда</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Код</th>'
        . '<th class="border-b border-blue-800/50 pb-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"></th>'
        . '</tr></thead><tbody>' . ($rows ?: '<tr><td colspan="4" class="py-4 text-sm text-slate-500">Редиректов пока нет. «Слетевшие» адреса ищите в разделе 404-лог.</td></tr>') . '</tbody></table></div>'
        . '<div class="mt-4 rounded-lg border border-blue-800/40 bg-blue-950/50 p-4 text-xs text-slate-500">'
        . 'Редиректы проверяются до роутов ЧПУ: точное совпадение пути (со слэшем или без) → 301/302. '
        . 'Также роут (в таблице routes) может иметь поле redirect_to — используется при смене схемы ЧПУ.</div>';
    return admin_layout('Редиректы', $content, 'redirects');
}

function admin_redirect_form(array $redirect, bool $isNew): string
{
    $codeOptions = '';
    foreach ([301 => '301 — постоянно', 302 => '302 — временно'] as $k => $label) {
        $codeOptions .= '<option value="' . $k . '"' . ((int)$redirect['code'] === $k ? ' selected' : '') . '>' . $label . '</option>';
    }
    $content = '<h1 class="mb-6 text-xl font-bold text-white">' . ($isNew ? 'Новый редирект' : 'Правка редиректа') . '</h1>'
        . '<form method="post" action="?page=redirect_save&id=' . (int)$redirect['id'] . '">' . Auth::csrfField()
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<div class="mb-4 grid grid-cols-3 gap-x-4">'
        . '<div>' . admin_label('Откуда (путь)', 'напр. /old-page.html') . '<input type="text" name="from_url" required class="' . admin_input() . ' font-mono" value="' . e($redirect['from_url']) . '"></div>'
        . '<div>' . admin_label('Куда (путь или полный URL)') . '<input type="text" name="to_url" required class="' . admin_input() . ' font-mono" value="' . e($redirect['to_url']) . '"></div>'
        . '<div>' . admin_label('Код') . '<select name="code" class="' . admin_input() . '">' . $codeOptions . '</select></div>'
        . '</div>'
        . '<div class="flex items-center gap-3"><button class="' . admin_btn() . '" type="submit">Сохранить</button>'
        . '<a class="text-sm text-slate-400 hover:text-slate-300" href="?page=redirects">Отмена</a></div>'
        . '</div></form>';
    return admin_layout($isNew ? 'Новый редирект' : 'Правка редиректа', $content, 'redirects');
}

function admin_notfound_log(array $items): string
{
    $rows = '';
    foreach ($items as $r) {
        $rows .= '<tr>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><span class="font-mono text-sm text-slate-200">' . e($r['url']) . '</span></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . (int)$r['hits'] . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($r['last_at']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 align-top text-sm">'
            . '<a class="text-blue-400 hover:text-blue-300" href="?page=redirect_edit&from=' . rawurlencode($r['url']) . '">→ редирект</a>'
            . '</td></tr>';
    }
    $content = '<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold text-white">404-лог</h1>'
        . '<a class="' . admin_btn() . '" href="?page=redirect_edit">+ Новый редирект</a></div>'
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<table class="w-full"><thead><tr>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">URL</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Запросов</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Последний</th>'
        . '<th class="border-b border-blue-800/50 pb-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"></th>'
        . '</tr></thead><tbody>' . ($rows ?: '<tr><td colspan="4" class="py-4 text-sm text-slate-500">404-запросов пока не было.</td></tr>') . '</tbody></table></div>';
    return admin_layout('404-лог', $content, 'notfound');
}

/* ── Медиазагрузка ─────────────────────────────────────────── */

function format_size(int $bytes): string
{
    $units = ['Б', 'КБ', 'МБ', 'ГБ'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

function admin_media_list(array $items, string $uploadDir): string
{
    $rows = '';
    foreach ($items as $f) {
        $thumb = $f['is_image']
            ? '<img src="' . e($f['url']) . '" class="w-12 h-12 rounded object-cover border border-blue-800/40" alt="">'
            : '<div class="w-12 h-12 rounded flex items-center justify-center border border-blue-800/40 bg-blue-900/50 text-slate-500 text-xs font-mono">' . e(strtoupper(pathinfo($f['filename'], PATHINFO_EXTENSION))) . '</div>';
        $delete = ' <a class="text-red-400 hover:text-red-300 text-xs" href="?page=media_delete&id=' . (int)$f['id'] . '" onclick="return confirm(\'Удалить файл?\')">удалить</a>';
        $copyBtn = '<button class="text-xs text-blue-400 hover:text-blue-300 underline underline-offset-4" onclick="copyUrl(this, \'' . e($f['url'], ENT_QUOTES) . '\')">скопировать URL</button>';
        $insertBtn = '<button class="text-xs text-emerald-400 hover:text-emerald-300 underline underline-offset-4" onclick="setCoverUrl(\'' . e($f['url'], ENT_QUOTES) . '\')">вставить в обложку</button>';
        $rows .= '<tr>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top">' . $thumb
            . '<div class="mt-1 text-xs text-slate-500 font-mono">' . e($f['filename']) . '</div></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($f['original_name']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-xs text-slate-500 font-mono">' . e($f['mime_type']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . format_size($f['size']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400 font-mono truncate max-w-xs" title="' . e($f['url']) . '">' . e($f['url']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($f['created']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 align-top text-sm"><div class="flex gap-3">'
            . $insertBtn . $copyBtn . $delete . '</div></td></tr>';
    }
    $content = '<div class="mb-5 flex flex-wrap items-center justify-between gap-4"><h1 class="text-xl font-bold text-white">Медиатека</h1>'
        . '<form method="post" action="?page=media_upload" enctype="multipart/form-data" class="flex flex-wrap gap-3">' . Auth::csrfField()
        . '<input type="file" name="file" accept="image/*" required class="' . admin_input() . ' text-sm">'
        . '<button class="' . admin_btn() . '" type="submit">Загрузить</button>'
        . '</form></div>'
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<table class="w-full"><thead><tr>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Файл</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Оригинал</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">MIME</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Размер</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">URL</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Загружен</th>'
        . '<th class="border-b border-blue-800/50 pb-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"></th>'
        . '</tr></thead><tbody>' . ($rows ?: '<tr><td colspan="7" class="py-4 text-sm text-slate-500">Файлов пока нет.</td></tr>') . '</tbody></table></div>'
        . '<div class="mt-4 rounded-lg border border-blue-800/40 bg-blue-950/50 p-4 text-xs text-slate-500">'
        . 'Файлы хранятся в <code class="text-blue-400">/uploads/attachments/</code>. Только изображения (jpg, png, gif, webp), до 5 МБ. '
        . 'Используйте URL для вставки в поле «Обложка» статьи.</div>';
    return admin_layout('Медиатека', $content, 'media');
}

/* ── Плагины ─────────────────────────────────────────────── */

function admin_plugins_list(array $plugins, string $activeList): string
{
    $active = array_filter(array_map('trim', explode(',', $activeList)));
    $rows = '';
    foreach ($plugins as $p) {
        $badge = $p['active']
            ? '<span class="inline-block rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-300">активен</span>'
            : '<span class="inline-block rounded-full bg-slate-500/15 px-2.5 py-0.5 text-xs font-semibold text-slate-400">выключен</span>';
        if ($p['active']) {
            $form = '<form method="post" action="?page=plugins_toggle" class="inline">' . Auth::csrfField()
                . '<input type="hidden" name="plugin" value="' . e($p['name']) . '">'
                . '<input type="hidden" name="enable" value="0">'
                . '<button class="text-xs text-red-400 hover:text-red-300 underline underline-offset-4">Выключить</button>'
                . '</form>';
        } else {
            $form = '<form method="post" action="?page=plugins_toggle" class="inline">' . Auth::csrfField()
                . '<input type="hidden" name="plugin" value="' . e($p['name']) . '">'
                . '<input type="hidden" name="enable" value="1">'
                . '<button class="text-xs text-blue-400 hover:text-blue-300 underline underline-offset-4">Включить</button>'
                . '</form>';
        }
        $initHint = $p['hasInit'] ? '' : ' <span class="text-xs text-slate-500">(нет init.php — не загрузится)</span>';
        $rows .= '<tr>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top"><span class="font-medium text-slate-200">' . e($p['name']) . '</span>'
            . '<div class="mt-0.5 text-xs text-slate-500">v' . e($p['version']) . $initHint . '</div></td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top text-sm text-slate-400">' . e($p['desc']) . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 pr-4 align-top">' . $badge . '</td>'
            . '<td class="border-b border-blue-800/30 py-3 align-top text-sm">' . $form . '</td></tr>';
    }
    $content = '<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold text-white">Плагины</h1>'
        . '<p class="text-xs text-slate-500">Плагин = папка <code>/plugins/&lt;имя&gt;/</code> с <code>plugin.json</code> и <code>init.php</code>. '
        . 'Подключаются при загрузке ядра в порядке списка.</p></div>'
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<table class="w-full"><thead><tr>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Плагин</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Описание</th>'
        . '<th class="border-b border-blue-800/50 pb-2 pr-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Статус</th>'
        . '<th class="border-b border-blue-800/50 pb-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"></th>'
        . '</tr></thead><tbody>' . ($rows ?: '<tr><td colspan="4" class="py-4 text-sm text-slate-500">Плагинов не найдено. Создайте папку в <code>/plugins/</code>.</td></tr>') . '</tbody></table></div>';
    return admin_layout('Плагины', $content, 'plugins');
}

/* ── Настройки ──────────────────────────────────────────────── */

function admin_settings_form(array $s, array $themes, array $schemes): string
{
    $themeOptions = '';
    foreach ($themes as $t) {
        $themeOptions .= '<option value="' . e($t) . '"' . ($s['theme'] === $t ? ' selected' : '') . '>' . e($t) . '</option>';
    }
    $schemeOptions = '';
    foreach ($schemes as $k => $label) {
        $schemeOptions .= '<option value="' . $k . '"' . ($s['url_scheme'] === $k ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $content = '<h1 class="mb-6 text-xl font-bold text-white">Настройки</h1>'
        . '<form method="post" action="?page=settings_save">' . Auth::csrfField()
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5">'
        . '<div class="mb-4">' . admin_label('Название сайта') . '<input type="text" name="site_name" required class="' . admin_input() . '" value="' . e($s['site_name']) . '"></div>'
        . '<div class="mb-4">' . admin_label('SEO title', 'пусто = название') . '<input type="text" name="site_title" class="' . admin_input() . '" value="' . e($s['site_title']) . '"></div>'
        . '<div class="mb-4">' . admin_label('SEO description') . '<textarea name="site_description" rows="3" class="' . admin_input() . ' resize-y">' . e($s['site_description']) . '</textarea></div>'
        . '<div class="mb-4 grid grid-cols-3 gap-x-4">'
        . '<div>' . admin_label('Схема ЧПУ') . '<select name="url_scheme" class="' . admin_input() . '">' . $schemeOptions . '</select>'
        . '<div class="mt-1 text-xs text-slate-500">Дефолтные роуты перезаписываются, кастомные сохраняются.</div></div>'
        . '<div>' . admin_label('Статей на странице') . '<input type="number" name="per_page" min="1" max="100" class="' . admin_input() . '" value="' . e($s['per_page']) . '"></div>'
        . '<div>' . admin_label('Тема') . '<select name="theme" class="' . admin_input() . '">' . $themeOptions . '</select></div>'
        . '</div>'
        . '<button class="' . admin_btn() . '" type="submit">Сохранить</button>'
        . '</div></form>';
    return admin_layout('Настройки', $content, 'settings');
}

/* ── Редактирование шаблонов (ACE) ─────────────────────────────── */

function admin_templates_list(array $themes): string
{
    $cards = '';
    foreach ($themes as $t) {
        $files = '';
        foreach ($t['files'] as $f) {
            $files .= '<a class="text-xs text-blue-400 hover:text-blue-300 underline underline-offset-4"'
                . ' href="?page=template_edit&theme=' . urlencode($t['name']) . '&file=' . urlencode($f) . '">'
                . e($f) . '</a> ';
        }
        $cards .= '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-4 mb-4">'
            . '<div class="flex items-center justify-between mb-3">'
            . '<span class="font-semibold text-white">' . e($t['title']) . '</span>'
            . '<span class="text-xs text-slate-400 font-mono">/' . e($t['name']) . '/</span>'
            . '</div>'
            . '<p class="text-xs text-slate-500 mb-3">'
            . ($t['name'] === settings('theme', 'default')
                ? '<span class="text-emerald-400 font-semibold">активная тема</span>'
                : '<span class="text-slate-400">не активна</span>')
            . '</p>'
            . '<div class="flex flex-wrap gap-2 text-xs">' . ($files ?: '<span class="text-slate-500">нет редактируемых файлов</span>') . '</div>'
            . '</div>';
    }
    $content = '<div class="mb-5 flex items-center justify-between"><h1 class="text-xl font-bold text-white">Шаблоны тем</h1>'
        . '<p class="text-xs text-slate-500">Редактируйте файлы тем прямо из админки — без FTP. '
        . 'Доступны активные и неактивные темы.</p></div>'
        . '<div class="space-y-3">' . ($cards ?: '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-5 text-sm text-slate-500">Темы не найдены.</div>') . '</div>'
        . '<div class="mt-6 rounded-lg border border-blue-800/40 bg-blue-950/50 p-4 text-xs text-slate-500">'
        . 'Разрешенные типы файлов: <code>.tpl</code>, <code>.phtml</code>, <code>.json</code>, <code>.css</code>, <code>.js</code>. '
        . 'Изменения в .tpl вступают в силу сразу (кэш шаблонов очищается при сохранении).</div>';
    return admin_layout('Шаблоны тем', $content, 'templates');
}

function admin_template_edit(string $theme, string $themeTitle, string $file, string $content): string
{
    $ace = '<script src="/admin/assets/ace/ace.js" type="text/javascript" charset="utf-8"></script>'
        . '<script src="/admin/assets/ace/ext-language_tools.js" type="text/javascript" charset="utf-8"></script>';
    $mode = 'ace/mode/text';
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $modeMap = [
        'tpl'  => 'ace/mode/html',
        'phtml'=> 'ace/mode/php',
        'html' => 'ace/mode/html',
        'css'  => 'ace/mode/css',
        'js'   => 'ace/mode/javascript',
        'json' => 'ace/mode/json',
        'php'  => 'ace/mode/php',
    ];
    $mode = $modeMap[$ext] ?? $mode;
    $initScript = '<script>'
        . 'var editor = ace.edit("editor");'
        . 'editor.setTheme("ace/theme/monokai");'
        . 'editor.session.setMode("' . $mode . '");'
        . 'editor.setOptions({'
        . '  enableBasicAutocompletion: true,'
        . '  enableLiveAutocompletion: true,'
        . '  enableSnippets: true,'
        . '  showPrintMargin: false,'
        . '  fontSize: "13px",'
        . '  tabSize: 2,'
        . '  useSoftTabs: true'
        . '});'
        . 'editor.session.setValue(' . json_encode($content, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');'
        . '</script>';
    $contentHtml = '<h1 class="mb-6 text-xl font-bold text-white">Правка шаблона</h1>'
        . '<div class="mb-4 flex items-center gap-3 text-sm">'
        . '<span class="font-mono text-slate-400">templates/</span>'
        . '<span class="font-semibold text-blue-300 font-mono">' . e($theme) . '</span>'
        . '<span class="text-slate-500">/</span>'
        . '<span class="font-semibold text-white font-mono">' . e($file) . '</span>'
        . '</div>'
        . '<form method="post" action="?page=template_save">' . Auth::csrfField()
        . '<input type="hidden" name="theme" value="' . e($theme) . '">'
        . '<input type="hidden" name="file" value="' . e($file) . '">'
        . '<div class="rounded-lg border border-blue-800/40 bg-blue-950/50 p-1">'
        . '<div id="editor" class="h-96 border border-blue-800/40"></div>'
        . '</div>'
        . '<div class="mt-4 flex items-center gap-3">'
        . '<button class="' . admin_btn() . '" type="submit">Сохранить файл</button>'
        . '<a class="text-sm text-slate-400 hover:text-slate-300" href="?page=templates&theme=' . urlencode($theme) . '">Назад к теме</a>'
        . '<a class="text-sm text-blue-400 hover:text-blue-300 ml-auto" href="/" target="_blank">Открыть сайт</a>'
        . '</div>'
        . '</form>' . $ace . $initScript;
    return admin_layout($themeTitle . ' · ' . e($file), $contentHtml, 'templates');
}
