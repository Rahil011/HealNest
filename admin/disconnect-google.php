<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/helpers.php';

requireAdmin($config);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

HealNest\GoogleAuth::clearToken($config);
unset($_SESSION['picker_session']);

header('Location: connect-mail.php?disconnected=1');
exit;
