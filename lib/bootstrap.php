<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_readable($configPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing config/config.php — copy config/config.example.php to config/config.php and set Google OAuth credentials.\n";
    exit;
}

$config = require $configPath;

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_readable($vendorAutoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Run composer install in the project root.\n";
    exit;
}

require $vendorAutoload;

$storageDir = dirname(__DIR__) . '/storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0700, true);
}

return $config;
