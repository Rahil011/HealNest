<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/lib/bootstrap.php';

HealNest\Session::start($config);
HealNest\Session::destroy();

header('Location: login.php?error=logout');
exit;
