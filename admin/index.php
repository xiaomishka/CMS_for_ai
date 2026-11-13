<?php
/**
 * admin/index.php — точка входа админки.
 * Страницы: ?page=login|logout|dashboard|articles|article_edit|article_save|
 *           article_delete|categories|category_edit|category_save|category_delete|
 *           pages|page_edit|page_save|page_delete|
 *           users|user_edit|user_save|user_delete|
 *           redirects|redirect_edit|redirect_save|redirect_delete|
 *           notfound|settings|settings_save
 */

declare(strict_types=1);

require dirname(__DIR__) . '/engine/bootstrap.php';
require __DIR__ . '/includes/views.php';

$page = (string)($_GET['page'] ?? 'dashboard');

/* ── Вход/выход доступны без авторизации ─────────────────────── */

if ($page === 'logout') {
    Auth::logout();
    redirect('/admin/?page=login');
}

if ($page === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::csrfCheck();
        if (Auth::attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
            redirect('/admin/');
        }
        exit(admin_login_page('Неверный e-mail или пароль'));
    }
    if (Auth::check()) {
        redirect('/admin/');
    }
    exit(admin_login_page());
}

Auth::requireLogin();

/* ── Все POST-действия под CSRF ──────────────────────────────── */
Auth::csrfCheck();

$perPage = max(1, (int)settings('per_page', '10'));

/** Статьи, доступные текущему пользователю (author видит только свои). */
function admin_articles_scope(): array
{
    if (Auth::can('article.edit.any')) {
        return ['', []];
    }
    return ['author_id = ?', [Auth::id() ?? 0]];
}

/** Доступна ли статья текущему пользователю для правки. */
function admin_can_edit_article(RedBeanPHP\OODBBean $bean): bool
{
    if (Auth::can('article.edit.any')) {
        return true;
    }
    return (int)$bean->author_id === (Auth::id() ?? 0);
}

/** Уникальный slug для бина таблицы (articles|categories|pages). */
function admin_unique_slug(string $table, string $slug, int $selfId): string
{
    $base = $slug;
    $n = 2;
    while (true) {
        $other = R::findOne($table, 'slug = ?', [$slug]);
        if (!$other || (int)$other->id === $selfId) {
            return $slug;
        }
        $slug = $base . '-' . $n++;
    }
}

switch ($page) {

    /* ── Обзор ── */
    case 'dashboard':
        $own = admin_articles_scope();
        try {
            $pagesCount = (int)R::count('pages');
        } catch (\Throwable) {
            $pagesCount = 0; // таблица pages ещё не создана
        }
        if ($own[0] !== '') {
            $stats = [
                'Мои статьи'      => (int)R::count('articles', $own[0], $own[1]),
                'Мои черновики'   => (int)R::count('articles', $own[0] . ' AND status = ?', array_merge($own[1], ['draft'])),
                'Рубрик'          => (int)R::count('categories'),
                'Статических страниц' => $pagesCount,
            ];
        } else {
            $stats = [
                'Статей опубликовано' => (int)R::count('articles', 'status = ?', ['published']),
                'Черновиков'          => (int)R::count('articles', 'status = ?', ['draft']),
                'Рубрик'              => (int)R::count('categories'),
                'Статических страниц' => $pagesCount,
            ];
        }
        exit(admin_dashboard($stats));

    /* ── Статьи ── */
    case 'articles':
        if (!Auth::can('article.create') && !Auth::can('article.edit.any')) {
            exit(admin_layout('Доступ запрещён', '<div class="mb-5 rounded-md border border-red-800/60 bg-red-500/10 px-4 py-2.5 text-sm text-red-300">Недостаточно прав.</div>', 'articles'));
        }
        $p = max(1, (int)($_GET['p'] ?? 1));
        [$where, $bind] = admin_articles_scope();
        $total = (int)R::count('articles', $where, $bind);
        $pages = max(1, (int)ceil($total / $perPage));
        $p = min($p, $pages);
        $beans = R::findAll('articles',
            $where . ' ORDER BY published_at DESC LIMIT ? OFFSET ?',
            array_merge($bind, [$perPage, ($p - 1) * $perPage]));
        $items = [];
        foreach ($beans as $b) {
            $cat = $b->category_id ? R::load('categories', (int)$b->category_id) : null;
            $items[] = [
                'id'       => (int)$b->id,
                'title'    => (string)$b->title,
                'slug'     => (string)$b->slug,
                'status'   => (string)$b->status,
                'date'     => (string)date('d.m.Y H:i', strtotime((string)$b->published_at)),
                'category' => ($cat && $cat->id) ? (string)$cat->title : '—',
                'url'      => url_article(($cat && $cat->id) ? (string)$cat->slug : null, (string)$b->slug),
            ];
        }
        exit(admin_articles_list($items, $p, $pages));

    case 'article_edit':
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('articles', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Статья не найдена', 'err');
            redirect('/admin/?page=articles');
        }
        if ($bean && !admin_can_edit_article($bean)) {
            Auth::flash('Можно править только свои статьи', 'err');
            redirect('/admin/?page=articles');
        }
        $cats = [];
        foreach (R::find('categories', 'ORDER BY title ASC') as $c) {
            $cats[] = ['id' => (int)$c->id, 'title' => (string)$c->title];
        }
        $article = $bean ? [
            'id'          => (int)$bean->id,
            'title'       => (string)$bean->title,
            'slug'        => (string)$bean->slug,
            'category_id' => (int)$bean->category_id,
            'status'      => (string)$bean->status,
            'short'       => (string)$bean->short,
            'full'        => (string)$bean->full,
            'cover'       => (string)$bean->cover,
            'meta_title'  => (string)$bean->meta_title,
            'meta_description' => (string)$bean->meta_description,
            'published_at' => date('Y-m-d\TH:i', strtotime((string)$bean->published_at)),
        ] : [
            'id' => 0, 'title' => '', 'slug' => '', 'category_id' => 0, 'status' => 'draft',
            'short' => '', 'full' => '', 'cover' => '', 'meta_title' => '', 'meta_description' => '',
            'published_at' => date('Y-m-d\TH:i'),
        ];
        exit(admin_article_form($cats, $article, $bean === null));

    case 'article_save':
        if (!Auth::can('article.create') && !Auth::can('article.edit.any')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=articles');
        }
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('articles', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Статья не найдена', 'err');
            redirect('/admin/?page=articles');
        }
        if ($bean && !admin_can_edit_article($bean)) {
            Auth::flash('Можно править только свои статьи', 'err');
            redirect('/admin/?page=articles');
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            Auth::flash('Заголовок обязателен', 'err');
            redirect('/admin/?page=article_edit' . ($id ? '&id=' . $id : ''));
        }

        // Whitelist-заполнение полей (никакого import из $_POST)
        $isNew = $bean === null;
        $bean = $bean ?: R::dispense('articles');
        $bean->title    = $title;
        $bean->category_id = (int)($_POST['category_id'] ?? 0);
        // author без права article.publish может сохранить только черновик
        $bean->status   = (Auth::can('article.publish') && ($_POST['status'] ?? 'draft') === 'published')
            ? 'published' : 'draft';
        $bean->short    = (string)($_POST['short'] ?? '');
        $bean->full     = (string)($_POST['full'] ?? '');
        $bean->cover    = trim((string)($_POST['cover'] ?? ''));
        $bean->meta_title = trim((string)($_POST['meta_title'] ?? ''));
        $bean->meta_description = trim((string)($_POST['meta_description'] ?? ''));

        $publishedAt = (string)($_POST['published_at'] ?? '');
        $ts = $publishedAt ? strtotime($publishedAt) : false;
        if ($ts === false) {
            $ts = $isNew ? time() : (strtotime((string)$bean->published_at) ?: time());
        }
        $bean->published_at = date('Y-m-d H:i:s', $ts);
        $bean->edited_at = date('Y-m-d H:i:s');
        if ($isNew) {
            $bean->author_id = (int)Auth::id();
        }

        // slug: из формы или из заголовка, с гарантией уникальности
        $slug = slugify((string)($_POST['slug'] ?? '') ?: $title);
        if ($slug === '') {
            $slug = 'post-' . time();
        }
        $bean->slug = admin_unique_slug('articles', $slug, (int)$bean->id);

        Plugins::emit('article.save.before', $bean);
        R::store($bean);
        Plugins::emit('article.save.after', $bean);
        Auth::flash('Статья сохранена');
        redirect('/admin/?page=articles');

    case 'article_delete':
        if (!Auth::can('article.delete')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=articles');
        }
        $bean = R::load('articles', (int)($_GET['id'] ?? 0));
        if ($bean->id) {
            R::trash($bean);
            Auth::flash('Статья удалена');
        }
        redirect('/admin/?page=articles');

    /* ── Рубрики ── */
    case 'categories':
        $beans = R::find('categories', 'ORDER BY title ASC');
        $counts = [];
        foreach (R::getAll('SELECT category_id, COUNT(*) c FROM articles WHERE category_id > 0 GROUP BY category_id') as $row) {
            $counts[(int)$row['category_id']] = (int)$row['c'];
        }
        $byId = [];
        foreach ($beans as $b) {
            $byId[(int)$b->id] = $b;
        }
        $items = [];
        foreach ($beans as $b) {
            $parent = (int)$b->parent_id;
            $items[] = [
                'id'     => (int)$b->id,
                'title'  => (string)$b->title,
                'slug'   => (string)$b->slug,
                'parent' => $parent && isset($byId[$parent]) ? (string)$byId[$parent]->title : '—',
                'count'  => $counts[(int)$b->id] ?? 0,
            ];
        }
        exit(admin_categories_list($items));

    case 'category_edit':
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('categories', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Рубрика не найдена', 'err');
            redirect('/admin/?page=categories');
        }
        $cats = [];
        foreach (R::find('categories', 'ORDER BY title ASC') as $c) {
            $cats[] = ['id' => (int)$c->id, 'title' => (string)$c->title];
        }
        $category = $bean ? [
            'id'          => (int)$bean->id,
            'title'       => (string)$bean->title,
            'slug'        => (string)$bean->slug,
            'parent_id'   => (int)$bean->parent_id,
            'description' => (string)$bean->description,
        ] : ['id' => 0, 'title' => '', 'slug' => '', 'parent_id' => 0, 'description' => ''];
        exit(admin_category_form($cats, $category, $bean === null));

    case 'category_save':
        if (!Auth::can('category.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=categories');
        }
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('categories', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Рубрика не найдена', 'err');
            redirect('/admin/?page=categories');
        }
        if ($bean === null && !Auth::can('category.create')) {
            Auth::flash('Создавать рубрики может только администратор', 'err');
            redirect('/admin/?page=categories');
        }
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            Auth::flash('Название обязательно', 'err');
            redirect('/admin/?page=category_edit' . ($id ? '&id=' . $id : ''));
        }
        $bean = $bean ?: R::dispense('categories');
        $bean->title       = $title;
        $bean->parent_id   = (int)($_POST['parent_id'] ?? 0);
        $bean->description = trim((string)($_POST['description'] ?? ''));

        $slug = slugify((string)($_POST['slug'] ?? '') ?: $title);
        if ($slug === '') {
            $slug = 'cat-' . time();
        }
        // slug не должен ломать служебные маршруты
        $reserved = ['page', 'category', 'admin', 'templates', 'uploads', 'core', 'engine', 'install', 'rss.xml', 'sitemap.xml'];
        if (in_array($slug, $reserved, true)) {
            $slug .= '-rub';
        }
        $bean->slug = admin_unique_slug('categories', $slug, (int)$bean->id);

        R::store($bean);
        Auth::flash('Рубрика сохранена');
        redirect('/admin/?page=categories');

    case 'category_delete':
        if (!Auth::can('category.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=categories');
        }
        $bean = R::load('categories', (int)($_GET['id'] ?? 0));
        if ($bean->id) {
            foreach (R::find('articles', 'category_id = ?', [(int)$bean->id]) as $a) {
                $a->category_id = 0;
                R::store($a);
            }
            R::trash($bean);
            Auth::flash('Рубрика удалена, статьи остались без рубрики');
        }
        redirect('/admin/?page=categories');

    /* ── Статические страницы ── */
    case 'pages':
        $items = [];
        foreach (R::find('pages', 'ORDER BY title ASC') as $b) {
            $items[] = [
                'id'     => (int)$b->id,
                'title'  => (string)$b->title,
                'slug'   => (string)$b->slug,
                'status' => (string)$b->status,
                'edited' => (string)date('d.m.Y H:i', strtotime((string)($b->edited_at ?: $b->created_at))),
                'url'    => url_page((string)$b->slug),
            ];
        }
        exit(admin_pages_list($items));

    case 'page_edit':
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('pages', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Страница не найдена', 'err');
            redirect('/admin/?page=pages');
        }
        $data = $bean ? [
            'id'          => (int)$bean->id,
            'title'       => (string)$bean->title,
            'slug'        => (string)$bean->slug,
            'status'      => (string)$bean->status,
            'content'     => (string)$bean->content,
            'meta_title'  => (string)($bean->meta_title ?? ''),
            'meta_description' => (string)($bean->meta_description ?? ''),
        ] : ['id' => 0, 'title' => '', 'slug' => '', 'status' => 'draft', 'content' => '', 'meta_title' => '', 'meta_description' => ''];
        exit(admin_page_form($data, $bean === null));

    case 'page_save':
        if (!Auth::can('page.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=pages');
        }
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('pages', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Страница не найдена', 'err');
            redirect('/admin/?page=pages');
        }
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            Auth::flash('Заголовок обязателен', 'err');
            redirect('/admin/?page=page_edit' . ($id ? '&id=' . $id : ''));
        }
        $bean = $bean ?: R::dispense('pages');
        $bean->title       = $title;
        $bean->status      = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $bean->content     = (string)($_POST['content'] ?? '');
        $bean->meta_title  = trim((string)($_POST['meta_title'] ?? ''));
        $bean->meta_description = trim((string)($_POST['meta_description'] ?? ''));

        $slug = slugify((string)($_POST['slug'] ?? '') ?: $title);
        if ($slug === '') {
            $slug = 'page-' . time();
        }
        // slug не должен конфликтовать с пагинацией /page/{n}/
        if (ctype_digit($slug)) {
            $slug = 'page-' . $slug;
        }
        $bean->slug = admin_unique_slug('pages', $slug, (int)$bean->id);
        $bean->edited_at = date('Y-m-d H:i:s');
        if (!$bean->created_at) {
            $bean->created_at = date('Y-m-d H:i:s');
        }

        R::store($bean);
        Auth::flash('Страница сохранена');
        redirect('/admin/?page=pages');

    case 'page_delete':
        if (!Auth::can('page.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=pages');
        }
        $bean = R::load('pages', (int)($_GET['id'] ?? 0));
        if ($bean->id) {
            R::trash($bean);
            Auth::flash('Страница удалена');
        }
        redirect('/admin/?page=pages');

    /* ── Пользователи ── */
    case 'users':
        if (!Auth::can('users.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $items = [];
        foreach (R::find('users', 'ORDER BY id ASC') as $b) {
            $items[] = [
                'id'       => (int)$b->id,
                'name'     => (string)$b->name,
                'email'    => (string)$b->email,
                'role'     => (string)$b->role,
                'created'  => (string)date('d.m.Y H:i', strtotime((string)($b->created_at ?: 'now'))),
                'is_self'  => (int)$b->id === (Auth::id() ?? 0),
            ];
        }
        exit(admin_users_list($items));

    case 'user_edit':
        if (!Auth::can('users.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('users', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Пользователь не найден', 'err');
            redirect('/admin/?page=users');
        }
        $user = $bean ? [
            'id'    => (int)$bean->id,
            'name'  => (string)$bean->name,
            'email' => (string)$bean->email,
            'role'  => (string)$bean->role,
        ] : ['id' => 0, 'name' => '', 'email' => '', 'role' => 'author'];
        exit(admin_user_form($user, $bean === null, $bean !== null && (int)$bean->id === (Auth::id() ?? 0)));

    case 'user_save':
        if (!Auth::can('users.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('users', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Пользователь не найден', 'err');
            redirect('/admin/?page=users');
        }
        $isNew = $bean === null;
        $isSelf = !$isNew && (int)$bean->id === (Auth::id() ?? 0);

        $name  = trim((string)($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $role  = $isSelf ? 'admin' : (string)($_POST['role'] ?? 'author');
        $pass  = (string)($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Auth::flash('Укажите имя и корректный e-mail', 'err');
            redirect('/admin/?page=user_edit' . ($id ? '&id=' . $id : ''));
        }
        if (!in_array($role, Permissions::roles(), true)) {
            $role = 'author';
        }
        if ($isNew && mb_strlen($pass) < 8) {
            Auth::flash('Пароль: минимум 8 символов', 'err');
            redirect('/admin/?page=user_edit');
        }
        if ($pass !== '' && mb_strlen($pass) < 8) {
            Auth::flash('Пароль: минимум 8 символов', 'err');
            redirect('/admin/?page=user_edit' . ($id ? '&id=' . $id : ''));
        }
        // уникальность e-mail
        $other = R::findOne('users', 'LOWER(email) = ?', [$email]);
        if ($other && (int)$other->id !== (int)($bean->id ?? 0)) {
            Auth::flash('Этот e-mail уже занят', 'err');
            redirect('/admin/?page=user_edit' . ($id ? '&id=' . $id : ''));
        }

        $bean = $bean ?: R::dispense('users');
        $bean->name  = $name;
        $bean->email = $email;
        $bean->role  = $role;
        if ($pass !== '') {
            $bean->password = password_hash($pass, PASSWORD_DEFAULT);
        }
        if ($isNew) {
            $bean->created_at = date('Y-m-d H:i:s');
        }
        R::store($bean);
        Auth::flash('Пользователь сохранён');
        redirect('/admin/?page=users');

    case 'user_delete':
        if (!Auth::can('users.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $bean = R::load('users', (int)($_GET['id'] ?? 0));
        if (!$bean->id) {
            redirect('/admin/?page=users');
        }
        if ((int)$bean->id === (Auth::id() ?? 0)) {
            Auth::flash('Нельзя удалить собственный аккаунт', 'err');
            redirect('/admin/?page=users');
        }
        // статьи пользователя остаются, author_id сохраняется (история авторства)
        R::trash($bean);
        Auth::flash('Пользователь удалён');
        redirect('/admin/?page=users');

    /* ── Редиректы ── */
    case 'redirects':
        if (!Auth::can('redirect.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $items = [];
        foreach (R::find('redirects', 'ORDER BY from_url ASC') as $b) {
            $items[] = [
                'id'   => (int)$b->id,
                'from' => (string)$b->from_url,
                'to'   => (string)$b->to_url,
                'code' => (int)($b->code ?: 301),
            ];
        }
        exit(admin_redirects_list($items));

    case 'redirect_edit':
        if (!Auth::can('redirect.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('redirects', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Редирект не найден', 'err');
            redirect('/admin/?page=redirects');
        }
        $from = $bean ? (string)$bean->from_url : rawurldecode((string)($_GET['from'] ?? ''));
        $data = $bean ? [
            'id'      => (int)$bean->id,
            'from_url' => (string)$bean->from_url,
            'to_url'  => (string)$bean->to_url,
            'code'    => (int)($bean->code ?: 301),
        ] : ['id' => 0, 'from_url' => $from, 'to_url' => '', 'code' => 301];
        exit(admin_redirect_form($data, $bean === null));

    case 'redirect_save':
        if (!Auth::can('redirect.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $id = (int)($_GET['id'] ?? 0);
        $bean = $id ? R::load('redirects', $id) : null;
        if ($id && (!$bean || !$bean->id)) {
            Auth::flash('Редирект не найден', 'err');
            redirect('/admin/?page=redirects');
        }
        $from = trim((string)($_POST['from_url'] ?? ''));
        $to   = trim((string)($_POST['to_url'] ?? ''));
        $code = ((int)($_POST['code'] ?? 301) === 302) ? 302 : 301;
        if ($from === '' || $to === '') {
            Auth::flash('Заполните оба поля', 'err');
            redirect('/admin/?page=redirect_edit' . ($id ? '&id=' . $id : ''));
        }
        // нормализация: абсолютные URL не трогаем, пути без домена — со слэшем
        if (!preg_match('~^https?://~i', $from)) {
            $from = '/' . ltrim(str_replace('\\', '/', $from), '/');
        }
        if (!preg_match('~^https?://~i', $to)) {
            $to = '/' . ltrim(str_replace('\\', '/', $to), '/');
        }
        // from без хвостового слэша — как его хранит роутер (current_path())
        $from = rtrim($from, '/');
        if ($from === '') {
            $from = '/';
        }
        // защита от цикла: from == to
        $toPath = preg_match('~^https?://~i', $to) ? $to : rtrim($to, '/');
        if ($toPath === $from) {
            Auth::flash('Откуда и куда совпадают — редирект не сохранён', 'err');
            redirect('/admin/?page=redirect_edit' . ($id ? '&id=' . $id : ''));
        }
        // уникальность from
        $other = R::findOne('redirects', 'from_url = ?', [$from]);
        if ($other && (int)$other->id !== (int)($bean->id ?? 0)) {
            Auth::flash('Редирект с такого адреса уже есть', 'err');
            redirect('/admin/?page=redirect_edit' . ($id ? '&id=' . $id : ''));
        }

        $bean = $bean ?: R::dispense('redirects');
        $bean->from_url = $from;
        $bean->to_url   = $to;
        $bean->code     = $code;
        R::store($bean);
        Auth::flash('Редирект сохранён');
        redirect('/admin/?page=redirects');

    case 'redirect_delete':
        if (!Auth::can('redirect.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $bean = R::load('redirects', (int)($_GET['id'] ?? 0));
        if ($bean->id) {
            R::trash($bean);
            Auth::flash('Редирект удалён');
        }
        redirect('/admin/?page=redirects');

    /* ── Лог 404 ── */
    case 'notfound':
        if (!Auth::can('redirect.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $items = [];
        foreach (R::find('notfound', 'ORDER BY hits DESC, last_at DESC LIMIT 200') as $b) {
            $items[] = [
                'url'     => (string)$b->url,
                'hits'    => (int)$b->hits,
                'last_at' => (string)date('d.m.Y H:i', strtotime((string)$b->last_at)),
            ];
        }
        exit(admin_notfound_log($items));

    /* ── Медиазагрузка ── */
    case 'media':
        if (!Auth::can('media.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $uploadDir = BASE_PATH . '/uploads/attachments';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $items = [];
        foreach (R::find('attachments', 'ORDER BY created_at DESC') as $b) {
            $items[] = [
                'id'            => (int)$b->id,
                'filename'      => (string)$b->filename,
                'original_name' => (string)$b->original_name,
                'mime_type'     => (string)$b->mime_type,
                'size'          => (int)$b->size,
                'url'           => (string)$b->url,
                'article_id'    => (int)$b->article_id,
                'created'       => date('d.m.Y H:i', strtotime((string)$b->created_at)),
                'is_image'      => str_starts_with((string)$b->mime_type, 'image/'),
            ];
        }
        exit(admin_media_list($items, $uploadDir));

    case 'media_upload':
        if (!Auth::can('media.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=media');
        }
        $uploadDir = BASE_PATH . '/uploads/attachments';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Auth::flash('Ошибка загрузки файла', 'err');
            redirect('/admin/?page=media');
        }
        // Разрешён только контент-типами изображений
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMime, true)) {
            Auth::flash('Разрешены только изображения (jpg, png, gif, webp)', 'err');
            redirect('/admin/?page=media');
        }
        $maxSize = 5 * 1024 * 1024; // 5 МБ
        if ((int)$file['size'] > $maxSize) {
            Auth::flash('Файл слишком большой (максимум 5 МБ)', 'err');
            redirect('/admin/?page=media');
        }
        $origName = basename((string)$file['name']);
        $ext = pathinfo($origName, PATHINFO_EXTENSION);
        $base = slugify(pathinfo($origName, PATHINFO_FILENAME));
        if ($base === '') {
            $base = 'file';
        }
        $storedName = $base;
        $n = 2;
        while (is_file($uploadDir . '/' . $storedName . '.' . $ext)) {
            $storedName = $base . '-' . $n++;
        }
        $storedFile = $storedName . '.' . $ext;
        $dest = $uploadDir . '/' . $storedFile;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Auth::flash('Не удалось сохранить файл на диск', 'err');
            redirect('/admin/?page=media');
        }
        $bean = R::dispense('attachments');
        $bean->filename      = $storedFile;
        $bean->original_name = $origName;
        $bean->mime_type     = $mime;
        $bean->size          = (int)$file['size'];
        $bean->article_id    = 0;
        $bean->url           = '/uploads/attachments/' . rawurlencode($storedFile);
        $bean->created_at    = date('Y-m-d H:i:s');
        R::store($bean);
        Auth::flash('Файл загружен: ' . e($origName));
        redirect('/admin/?page=media');

    case 'media_delete':
        if (!Auth::can('media.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=media');
        }
        $id = (int)($_GET['id'] ?? 0);
        $bean = R::load('attachments', $id);
        if ($bean->id) {
            $path = BASE_PATH . '/uploads/attachments/' . (string)$bean->filename;
            if (is_file($path)) {
                @unlink($path);
            }
            R::trash($bean);
            Auth::flash('Файл удалён');
        }
        redirect('/admin/?page=media');

    /* ── Плагины ── */
    case 'plugins':
        if (!Auth::can('plugins.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $active = array_filter(array_map('trim', explode(',', settings('active_plugins', ''))));
        $plugins = [];
        foreach (glob(BASE_PATH . '/plugins/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            $metaFile = $dir . '/plugin.json';
            $initFile = $dir . '/init.php';
            $meta = [];
            if (is_file($metaFile)) {
                $meta = json_decode((string)file_get_contents($metaFile), true) ?: [];
            }
            $plugins[] = [
                'name'     => $name,
                'version'  => $meta['version'] ?? '—',
                'desc'     => $meta['description'] ?? '',
                'active'   => in_array($name, $active, true),
                'hasInit'  => is_file($initFile),
            ];
        }
        usort($plugins, fn($a, $b) => strcmp($a['name'], $b['name']));
        exit(admin_plugins_list($plugins, implode(',', $active)));

    case 'plugins_toggle':
        if (!Auth::can('plugins.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/?page=plugins');
        }
        $name = basename((string)($_POST['plugin'] ?? ''));
        $enable = filter_var($_POST['enable'] ?? '1', FILTER_VALIDATE_BOOL);
        $initFile = BASE_PATH . '/plugins/' . $name . '/init.php';
        $metaFile = BASE_PATH . '/plugins/' . $name . '/plugin.json';
        if (!is_file($initFile) || !is_file($metaFile)) {
            Auth::flash('Плагин не найден или некорректен', 'err');
            redirect('/admin/?page=plugins');
        }
        $active = array_filter(array_map('trim', explode(',', settings('active_plugins', ''))));
        if ($enable) {
            if (!in_array($name, $active, true)) {
                $active[] = $name;
                Plugins::emit('plugin.activated', $name);
            }
        } else {
            $active = array_values(array_filter($active, fn($a) => $a !== $name));
            Plugins::emit('plugin.deactivated', $name);
        }
        $bean = R::findOne('settings', '`key` = ?', ['active_plugins']);
        if (!$bean) {
            $bean = R::dispense('settings');
            $bean->key = 'active_plugins';
        }
        $bean->value = implode(',', $active);
        R::store($bean);
        Auth::flash($enable ? 'Плагин активирован' : 'Плагин деактивирован');
        redirect('/admin/?page=plugins');

    /* ── Настройки ── */
    case 'settings':
        if (!Auth::can('settings.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        $themes = [];
        foreach (glob(BASE_PATH . '/templates/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $themes[] = basename($dir);
        }
        $s = [
            'site_name'        => settings('site_name'),
            'site_title'       => settings('site_title'),
            'site_description' => settings('site_description'),
            'per_page'         => settings('per_page', '10'),
            'url_scheme'       => settings('url_scheme', 'hierarchical'),
            'theme'            => settings('theme', 'default'),
        ];
        exit(admin_settings_form($s, $themes, [
            'hierarchical' => 'иерархическая — /рубрика/статья.html',
            'short'        => 'короткая — /статья.html, /category/рубрика/',
            'clean'        => 'чистая — /рубрика/статья/',
        ]));

    case 'settings_save':
        if (!Auth::can('settings.manage')) {
            Auth::flash('Недостаточно прав', 'err');
            redirect('/admin/');
        }
        // Прежняя схема: settings-кэш ещё не перезаписан (обновим позже),
        // сравнение с config.php ненадёжно (opcache/статический кэш).
        $oldScheme = settings('url_scheme', 'hierarchical');
        $update = [
            'site_name'        => trim((string)($_POST['site_name'] ?? '')) ?: 'Сайт',
            'site_title'       => trim((string)($_POST['site_title'] ?? '')),
            'site_description' => trim((string)($_POST['site_description'] ?? '')),
            'per_page'         => (string)max(1, min(100, (int)($_POST['per_page'] ?? 10))),
            'url_scheme'       => in_array($_POST['url_scheme'] ?? '', ['short', 'hierarchical', 'clean'], true) ? $_POST['url_scheme'] : 'hierarchical',
            'theme'            => basename((string)($_POST['theme'] ?? 'default')),
        ];
        foreach ($update as $key => $value) {
            $bean = R::findOne('settings', '`key` = ?', [$key]);
            if (!$bean) {
                $bean = R::dispense('settings');
                $bean->key = $key;
            }
            $bean->value = $value;
            R::store($bean);
        }
        // Смена схемы ЧПУ → перегенерация дефолтных роутов
        if ($update['url_scheme'] !== $oldScheme) {
            Database::seedRoutes($update['url_scheme']);
        }
        // Синхронизация config.php
        $cfg = Config::all();
        $cfg['site'] = array_merge($cfg['site'] ?? [], [
            'name'       => $update['site_name'],
            'theme'      => $update['theme'],
            'url_scheme' => $update['url_scheme'],
            'per_page'   => $update['per_page'],
        ]);
        file_put_contents(BASE_PATH . '/core/config.php',
            "<?php\nreturn " . var_export($cfg, true) . ";\n", LOCK_EX);
        Auth::flash('Настройки сохранены');
        redirect('/admin/?page=settings');

    default:
        redirect('/admin/?page=dashboard');
}
