<?php

declare(strict_types=1);

namespace HealNest;

final class OAuthRedirect
{
    public static function callbackPath(): string
    {
        return '/admin/oauth-callback.php';
    }

    /**
     * Must match exactly between authorize step and token exchange.
     */
    public static function resolve(array $config): string
    {
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
            $scheme = $https ? 'https' : 'http';

            return $scheme . '://' . $_SERVER['HTTP_HOST'] . self::callbackPath();
        }

        $configured = trim((string) ($config['google_redirect_uri'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'http://localhost:8080' . self::callbackPath();
    }
}
