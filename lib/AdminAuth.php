<?php

declare(strict_types=1);

namespace HealNest;

final class AdminAuth
{
    public static function verify(array $config, string $username, string $password): bool
    {
        $expectedUser = (string) ($config['admin_username'] ?? 'admin');
        $expectedPass = (string) ($config['admin_password'] ?? '');

        if ($expectedPass === '') {
            return false;
        }

        return hash_equals($expectedUser, $username)
            && hash_equals($expectedPass, $password);
    }
}
