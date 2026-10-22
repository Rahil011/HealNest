<?php

declare(strict_types=1);

namespace HealNest;

final class Session
{
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($config['session_name'] ?? 'healnest_admin_session');

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function isAdminLoggedIn(): bool
    {
        return !empty($_SESSION['admin_authenticated'])
            && !empty($_SESSION['admin_email']);
    }

    public static function adminEmail(): ?string
    {
        return $_SESSION['admin_email'] ?? null;
    }

    public static function setAdmin(string $email, string $name): void
    {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_name'] = $name;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}
