<?php

declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requireAdmin(array $config): void
{
    HealNest\Session::start($config);
    if (!HealNest\Session::isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function googleAllowedEmail(array $config): string
{
    return strtolower(trim((string) ($config['google_allowed_email'] ?? $config['admin_email'] ?? '')));
}
