<?php
/**
 * bootstrap.php — инициализация ядра.
 * Подключается из index.php (фронт) и admin/index.php (админка).
 * Диспетчеризацию (Router::dispatch) делает точка входа.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('ENGINE_PATH')) {
    define('ENGINE_PATH', __DIR__);
}

// Ошибки: логируем, пользователю показываем служебную страницу
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/core/php-error.log');
error_reporting(E_ALL);

require_once ENGINE_PATH . '/../core/rb.php';
require_once ENGINE_PATH . '/helpers.php';
require_once ENGINE_PATH . '/Config.php';
require_once ENGINE_PATH . '/Permissions.php';
require_once ENGINE_PATH . '/Auth.php';
require_once ENGINE_PATH . '/Plugins/Hub.php';
require_once ENGINE_PATH . '/Database.php';
require_once ENGINE_PATH . '/Template.php';
require_once ENGINE_PATH . '/View.php';
require_once ENGINE_PATH . '/Router.php';
require_once ENGINE_PATH . '/Controllers/Article.php';
require_once ENGINE_PATH . '/Controllers/Category.php';
require_once ENGINE_PATH . '/Controllers/Page.php';
require_once ENGINE_PATH . '/Controllers/Feed.php';

\Config::load();
\Auth::startSession();

// Обработчик фатальных исключений → страница 500
set_exception_handler(function (Throwable $e) {
    error_log((string)$e);
    \Controllers\Page::serverError();
});

if (!Config::get('installed')) {
    // CMS ещё не установлена
    if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        redirect('/install.php');
    }
    return; // install.php подключает ядро сам и на этом месте завершит свою инициализацию
}

try {
    Database::connect();
    Database::migrate();
} catch (Throwable $e) {
    error_log('DB connect: ' . $e->getMessage());
    \Controllers\Page::serverError();
}

try {
    Plugins::boot();
} catch (Throwable $e) {
    error_log('Plugin boot: ' . $e->getMessage());
}

Plugins::emit('app.boot');
