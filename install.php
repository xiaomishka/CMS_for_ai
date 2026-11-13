<?php
/**
 * install.php — установка CMS.
 *
 * Шаги: проверка окружения → выбор БД (SQLite или MySQL) → админ + сайт →
 * создание схемы и сидов → запись core/config.php → блокировка инсталлятора.
 *
 * После установки сам переименовывается в install.done.php.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
if (!defined('ENGINE_PATH')) {
    define('ENGINE_PATH', BASE_PATH . '/engine');
}

require_once ENGINE_PATH . '/../core/rb.php';
require_once ENGINE_PATH . '/helpers.php';
require_once ENGINE_PATH . '/Config.php';
require_once ENGINE_PATH . '/Permissions.php';
require_once ENGINE_PATH . '/Auth.php';
require_once ENGINE_PATH . '/Router.php';
require_once ENGINE_PATH . '/Database.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('cms_session');
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ── Уже установлено? ────────────────────────────────────────── */
if (is_file(BASE_PATH . '/core/config.php')) {
    $cfg = require BASE_PATH . '/core/config.php';
    if (!empty($cfg['installed'])) {
        http_response_code(403);
        exit('<h2>CMS уже установлена</h2><p>Файл install.php заблокирован. Удалите его с сервера.</p>');
    }
}

/* ── Проверка окружения ─────────────────────────────────────── */
$errors = [];
$checks = [
    'PHP >= 8.1'                     => version_compare(PHP_VERSION, '8.1.0', '>='),
    'Файл core/rb.php (RedBeanPHP)'  => is_file(BASE_PATH . '/core/rb.php'),
    'Каталог core/ доступен на запись' => is_writable(BASE_PATH . '/core'),
    'Каталог uploads/ доступен на запись' => is_writable(BASE_PATH . '/uploads') || @mkdir(BASE_PATH . '/uploads', 0775, true),
];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $errors[] = $label;
    }
}

$themes = [];
foreach (glob(BASE_PATH . '/templates/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $themes[] = basename($dir);
}
if (!$themes) {
    $errors[] = 'В каталоге templates/ нет ни одной темы';
}

$done = null;
$fail = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    $type   = ($_POST['db_type'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    $email  = trim((string)($_POST['admin_email'] ?? ''));
    $pass   = (string)($_POST['admin_password'] ?? '');
    $pass2  = (string)($_POST['admin_password2'] ?? '');
    $site   = trim((string)($_POST['site_name'] ?? ''));
    $scheme = in_array($_POST['url_scheme'] ?? '', ['short', 'hierarchical', 'clean'], true) ? $_POST['url_scheme'] : 'hierarchical';
    $theme  = in_array($_POST['theme'] ?? '', $themes, true) ? $_POST['theme'] : $themes[0];
    $perPage = max(1, min(100, (int)($_POST['per_page'] ?? 10)));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fail = 'Укажите корректный e-mail администратора';
    } elseif (mb_strlen($pass) < 8) {
        $fail = 'Пароль администратора: минимум 8 символов';
    } elseif ($pass !== $pass2) {
        $fail = 'Пароли не совпадают';
    } elseif ($site === '') {
        $fail = 'Укажите название сайта';
    }

    if (!$fail) {
        try {
            if ($type === 'mysql') {
                $host = trim((string)($_POST['db_host'] ?? 'localhost'));
                $name = trim((string)($_POST['db_name'] ?? ''));
                $user = trim((string)($_POST['db_user'] ?? ''));
                $dbpass = (string)($_POST['db_pass'] ?? '');
                if ($name === '') {
                    throw new RuntimeException('Укажите имя базы MySQL');
                }
                \R::setup('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4', $user, $dbpass);
                $dbConfig = ['type' => 'mysql', 'host' => $host, 'name' => $name, 'user' => $user, 'pass' => $dbpass, 'path' => ''];
            } else {
                $path = 'core/cms.sqlite';
                \R::setup('sqlite:' . BASE_PATH . '/' . $path);
                $dbConfig = ['type' => 'sqlite', 'host' => '', 'name' => '', 'user' => '', 'pass' => '', 'path' => $path];
            }
            if (!\R::testConnection()) {
                throw new RuntimeException('Нет соединения с базой данных. Проверьте параметры.');
            }

            $settings = [
                'site_name'        => $site,
                'site_title'       => $site,
                'site_description' => '',
                'per_page'         => (string)$perPage,
                'theme'            => $theme,
                'url_scheme'       => $scheme,
                'active_plugins'   => '',
            ];

            Database::install(
                ['email' => $email, 'password' => $pass, 'name' => 'Администратор'],
                $settings,
                $scheme
            );

            // config.php
            $config = [
                'installed' => true,
                'secret'    => bin2hex(random_bytes(16)),
                'db'        => $dbConfig,
                'site'      => [
                    'name'       => $site,
                    'theme'      => $theme,
                    'url_scheme' => $scheme,
                    'per_page'   => (string)$perPage,
                ],
            ];
            @mkdir(BASE_PATH . '/core/cache', 0775, true);
            file_put_contents(
                BASE_PATH . '/core/config.php',
                "<?php\n// Сгенерировано install.php " . date('Y-m-d H:i:s') . "\nreturn " . var_export($config, true) . ";\n",
                LOCK_EX
            );

            // Автовход и переход в админку
            \Auth::attempt($email, $pass);
            @rename(__FILE__, BASE_PATH . '/install.done.php'); // best effort: блокируем инсталлятор
            header('Location: ' . site_url() . '/admin/');
            exit;
        } catch (Throwable $e) {
            $fail = 'Ошибка установки: ' . $e->getMessage();
        }
    }
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Установка CMS</title>
<style>
:root { --accent:#0a6cff; --bg:#f4f6f9; --card:#fff; --line:#dde3ea; }
* { box-sizing:border-box; }
body { font-family:system-ui,Segoe UI,Arial,sans-serif; background:var(--bg); margin:0; color:#222; }
.wrap { max-width:640px; margin:40px auto; padding:0 16px; }
h1 { font-size:22px; }
.card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:24px; margin-bottom:16px; }
label { display:block; margin:14px 0 4px; font-size:14px; font-weight:600; }
input[type=text],input[type=password],input[type=email],input[type=number],select {
  width:100%; padding:9px 10px; border:1px solid var(--line); border-radius:6px; font-size:14px; }
button { margin-top:20px; background:var(--accent); color:#fff; border:0; padding:11px 22px;
  border-radius:6px; font-size:15px; cursor:pointer; }
button:hover { filter:brightness(1.08); }
.ok { color:#0a7a2f; } .bad { color:#c0392b; }
.notice { background:#fff6e5; border:1px solid #f0d8a8; border-radius:6px; padding:10px 14px; margin:12px 0; }
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:0 16px; }
.hint { font-size:12px; color:#777; }
fieldset { border:1px solid var(--line); border-radius:8px; margin:14px 0 0; padding:12px 16px 16px; }
legend { font-weight:600; font-size:14px; padding:0 6px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Установка CMS</h1>

  <?php if ($errors): ?>
    <div class="notice bad">
      Не выполнены требования:<br>
      <?php foreach ($errors as $er) echo '• ' . h($er) . '<br>'; ?>
    </div>
  <?php endif; ?>

  <?php if ($fail): ?>
    <div class="notice bad"><?= h($fail) ?></div>
  <?php endif; ?>

  <?php if (!$errors): ?>
  <form method="post" class="card">
    <fieldset>
      <legend>База данных</legend>
      <label><input type="radio" name="db_type" value="sqlite" checked
        onchange="dbBlocks(this.value)"> SQLite (файл, нулевая настройка)</label>
      <label><input type="radio" name="db_type" value="mysql"
        onchange="dbBlocks(this.value)"> MySQL</label>

      <div id="block-sqlite">
        <p class="hint">База будет создана в <code>core/cms.sqlite</code>. Ничего настраивать не нужно.</p>
      </div>

      <div id="block-mysql" style="display:none">
        <div class="grid2">
          <div><label>Сервер</label><input type="text" name="db_host" value="localhost"></div>
          <div><label>База</label><input type="text" name="db_name" placeholder="cms"></div>
          <div><label>Пользователь</label><input type="text" name="db_user"></div>
          <div><label>Пароль</label><input type="password" name="db_pass"></div>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Администратор</legend>
      <label>E-mail</label>
      <input type="email" name="admin_email" required value="<?= h($_POST['admin_email'] ?? '') ?>">
      <div class="grid2">
        <div><label>Пароль (мин. 8 символов)</label>
          <input type="password" name="admin_password" required minlength="8"></div>
        <div><label>Повторите пароль</label>
          <input type="password" name="admin_password2" required minlength="8"></div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Сайт</legend>
      <label>Название сайта</label>
      <input type="text" name="site_name" required value="<?= h($_POST['site_name'] ?? 'Мой сайт') ?>">
      <div class="grid2">
        <div>
          <label>Схема ЧПУ</label>
          <select name="url_scheme">
            <option value="hierarchical">иерархическая — /рубрика/статья.html</option>
            <option value="short">короткая — /статья.html, /category/рубрика/</option>
            <option value="clean">чистая — /рубрика/статья/</option>
          </select>
          <p class="hint">Потом можно сменить в настройках; старые адреса — через 301-редиректы.</p>
        </div>
        <div>
          <label>Тема</label>
          <select name="theme">
            <?php foreach ($themes as $t): ?>
              <option value="<?= h($t) ?>" <?= ($_POST['theme'] ?? 'default') === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
          <label>Статей на странице</label>
          <input type="number" name="per_page" min="1" max="100" value="<?= h($_POST['per_page'] ?? '10') ?>">
        </div>
      </div>
    </fieldset>

    <button type="submit">Установить</button>
  </form>
  <?php endif; ?>
</div>
<script>
function dbBlocks(t) {
  document.getElementById('block-sqlite').style.display = t === 'sqlite' ? '' : 'none';
  document.getElementById('block-mysql').style.display = t === 'mysql' ? '' : 'none';
}
</script>
</body>
</html>
