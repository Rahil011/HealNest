<?php

declare(strict_types=1);

namespace HealNest;

use Google\Client as GoogleClient;
use Google\Service\Gmail;

final class GoogleAuth
{
    public const GMAIL_READONLY = 'https://www.googleapis.com/auth/gmail.readonly';
    public const GMAIL_MODIFY = 'https://www.googleapis.com/auth/gmail.modify';
    /** Full Gmail access (Google may return this instead of separate readonly/modify URLs). */
    public const GMAIL_FULL = 'https://mail.google.com/';

    /** @var list<string> */
    public const SCOPES = [
        'openid',
        'email',
        'profile',
        Gmail::GMAIL_READONLY,
        Gmail::GMAIL_MODIFY,
    ];

    public static function createClient(array $config, ?string $redirectUri = null, bool $forceFreshConsent = false): GoogleClient
    {
        $client = new GoogleClient();
        $client->setApplicationName('The Heal Nest Admin');
        $client->setClientId($config['google_client_id']);
        $client->setClientSecret($config['google_client_secret']);
        $client->setRedirectUri($redirectUri ?? OAuthRedirect::resolve($config));
        $client->setAccessType('offline');
        $client->setPrompt($forceFreshConsent ? 'consent select_account' : 'consent');

        $hint = strtolower(trim((string) ($config['google_allowed_email'] ?? $config['admin_email'] ?? '')));
        if ($hint !== '') {
            $client->setLoginHint($hint);
        }

        $client->setScopes(self::SCOPES);

        return $client;
    }

    public static function authUrl(GoogleClient $client, string $state): string
    {
        $client->setState($state);

        return $client->createAuthUrl(null, ['state' => $state]);
    }

    public static function persistToken(GoogleClient $client, array $config): void
    {
        $token = $client->getAccessToken();
        if (!is_array($token)) {
            return;
        }
        $path = self::tokenPath($config);
        $existing = [];
        if (is_readable($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        $token = self::mergeTokenScopes($token, $existing);
        if (empty($token['refresh_token']) && !empty($existing['refresh_token'])) {
            $token['refresh_token'] = $existing['refresh_token'];
        }
        file_put_contents($path, json_encode($token, JSON_THROW_ON_ERROR), LOCK_EX);
        chmod($path, 0600);
    }

    /**
     * @param array<string, mixed> $token
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private static function mergeTokenScopes(array $token, array $existing): array
    {
        $newScope = trim((string) ($token['scope'] ?? ''));
        $oldScope = trim((string) ($existing['scope'] ?? ''));
        if ($newScope === '' && $oldScope !== '') {
            $token['scope'] = $oldScope;

            return $token;
        }
        if ($newScope !== '' && $oldScope !== '') {
            $merged = array_unique(array_merge(
                preg_split('/\s+/', $newScope) ?: [],
                preg_split('/\s+/', $oldScope) ?: []
            ));
            $token['scope'] = implode(' ', array_filter($merged));

            return $token;
        }

        return $token;
    }

    public static function loadToken(GoogleClient $client, array $config): bool
    {
        $path = self::tokenPath($config);
        if (!is_readable($path)) {
            return false;
        }
        $json = file_get_contents($path);
        if ($json === false) {
            return false;
        }
        $token = json_decode($json, true);
        if (!is_array($token)) {
            return false;
        }
        $client->setAccessToken($token);
        if ($client->isAccessTokenExpired()) {
            $refresh = $client->getRefreshToken();
            if ($refresh) {
                $client->fetchAccessTokenWithRefreshToken($refresh);
                self::persistToken($client, $config);
            } else {
                return false;
            }
        }
        return true;
    }

    public static function clearToken(array $config): void
    {
        self::clearAccessTokenFile($config);
        $lock = self::mailLockPath($config);
        if (is_file($lock)) {
            unlink($lock);
        }
    }

    public static function clearAccessTokenFile(array $config): void
    {
        $path = self::tokenPath($config);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** @return list<string> */
    public static function grantedScopes(array $config): array
    {
        $path = self::tokenPath($config);
        if (!is_readable($path)) {
            return [];
        }
        $token = json_decode((string) file_get_contents($path), true);
        if (!is_array($token)) {
            return [];
        }
        $scope = $token['scope'] ?? '';
        if (!is_string($scope) || $scope === '') {
            return [];
        }

        return preg_split('/\s+/', trim($scope)) ?: [];
    }

    public static function hasGmailReadScope(array $config): bool
    {
        foreach (self::grantedScopes($config) as $scope) {
            if (in_array($scope, [
                self::GMAIL_READONLY,
                self::GMAIL_MODIFY,
                self::GMAIL_FULL,
                Gmail::GMAIL_READONLY,
                Gmail::GMAIL_MODIFY,
            ], true)) {
                return true;
            }
        }

        return false;
    }

    public static function hasGmailModifyScope(array $config): bool
    {
        foreach (self::grantedScopes($config) as $scope) {
            if (in_array($scope, [self::GMAIL_MODIFY, self::GMAIL_FULL, Gmail::GMAIL_MODIFY], true)) {
                return true;
            }
        }

        return false;
    }

    /** Mail setup complete: connected with read-only and/or read & write Gmail scopes. */
    public static function isMailReadyForAdmin(array $config): bool
    {
        return self::isMailConnected($config) && self::hasGmailReadScope($config);
    }

    /** @return bool Inbox may be shown in admin (read-only or read & write). */
    public static function isGmailInboxVisible(array $config): bool
    {
        $level = self::gmailAccessLevel($config);

        return $level === 'readonly' || $level === 'read_write';
    }

    public static function hasGmailReadonlyScopeOnly(array $config): bool
    {
        return self::hasGmailReadScope($config) && !self::hasGmailModifyScope($config);
    }

    /** @return 'none'|'readonly'|'read_write' */
    public static function gmailAccessLevel(array $config): string
    {
        if (!self::hasGmailReadScope($config)) {
            return 'none';
        }
        if (self::hasGmailModifyScope($config)) {
            return 'read_write';
        }

        return 'readonly';
    }

    public static function gmailAccessLabel(array $config): string
    {
        return match (self::gmailAccessLevel($config)) {
            'read_write' => 'Read & write (inbox, send, delete)',
            'readonly' => 'Read-only (inbox & view)',
            default => 'No Gmail permission on token',
        };
    }

    public static function isMailConnected(array $config): bool
    {
        return is_readable(self::tokenPath($config)) && is_readable(self::mailLockPath($config));
    }

    public static function getConnectedEmail(array $config): ?string
    {
        $lock = self::mailLockPath($config);
        if (!is_readable($lock)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($lock), true);
        if (!is_array($json)) {
            return null;
        }
        $email = $json['email'] ?? null;
        return is_string($email) ? $email : null;
    }

    public static function markMailConnected(array $config, string $email): void
    {
        $path = self::mailLockPath($config);
        file_put_contents($path, json_encode([
            'email' => strtolower(trim($email)),
            'connected_at' => gmdate('c'),
        ], JSON_THROW_ON_ERROR), LOCK_EX);
        chmod($path, 0600);
    }

    private static function mailLockPath(array $config): string
    {
        $base = $config['app_base_path'] ?? dirname(__DIR__);
        return $base . '/storage/mail_connected.json';
    }

    private static function tokenPath(array $config): string
    {
        $base = $config['app_base_path'] ?? dirname(__DIR__);
        return $base . '/storage/google_token.json';
    }

    public static function clientWithToken(array $config): ?GoogleClient
    {
        $client = self::createClient($config);
        if (!self::loadToken($client, $config)) {
            return null;
        }
        return $client;
    }
}
