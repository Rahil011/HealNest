<?php

declare(strict_types=1);

/**
 * PHP built-in server router:
 * php -S localhost:8080 router.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = __DIR__ . ($uri === '/' ? '/index.html' : $uri);

if ($uri !== '/' && is_file($path)) {
    return false;
}

if ($uri === '/' && is_file(__DIR__ . '/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found\n";
return true;
