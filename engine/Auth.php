<?php
/**
 * Auth.php — аутентификация и права в сессии.
 * Пользователь хранится в сессии компактно (id, name, email, role).
 */

declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('cms_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
    }

    /** Попытка входа. true — успех. */
    public static function attempt(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));
        $bean = \R::findOne('users', 'LOWER(email) = ?', [$email]);
        if (!$bean || !password_verify($password, $bean->password)) {
            return false;
        }
        // Перехеширование при смене алгоритма/параметров
        if (password_needs_rehash($bean->password, PASSWORD_DEFAULT)) {
            $bean->password = password_hash($password, PASSWORD_DEFAULT);
            \R::store($bean);
        }
        return self::signIn($bean);
    }

    /** Записать пользователя в сессию (используется и install.php). */
    public static function signIn(RedBeanPHP\OODBBean $bean): bool
    {
        self::$user = [
            'id'    => (int)$bean->id,
            'name'  => (string)($bean->name ?: $bean->email),
            'email' => (string)$bean->email,
            'role'  => (string)$bean->role,
        ];
        session_regenerate_id(true);
        $_SESSION['user'] = self::$user;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        self::$user = null;
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Массив user|null. */
    public static function user(): ?array
    {
        if (self::$user === null) {
            $u = $_SESSION['user'] ?? null;
            self::$user = (is_array($u) && isset($u['id'], $u['role'])) ? $u : null;
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u ? $u['role'] : null;
    }

    /** Проверка права текущего пользователя. */
    public static function can(string $perm): bool
    {
        return \Permissions::can(self::role(), $perm);
    }

    /** В админку без входа нельзя. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/admin/?page=login');
        }
    }

    /* ── CSRF ─────────────────────────────────────────────── */

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::csrfToken()) . '">';
    }

    /** Проверка CSRF на POST-запросах. */
    public static function csrfCheck(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $sent = $_POST['csrf'] ?? '';
        if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
            http_response_code(403);
            exit('CSRF token invalid');
        }
    }

    /* ── Flash-сообщения ──────────────────────────────────── */

    public static function flash(string $message, string $type = 'ok'): void
    {
        $_SESSION['flash'][] = ['m' => $message, 't' => $type];
    }

    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}
