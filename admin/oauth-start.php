<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/helpers.php';

HealNest\Session::start($config);

$flow = (string) ($_GET['flow'] ?? '');

if ($flow === 'connect') {
    if (HealNest\GoogleAuth::isMailConnected($config)) {
        header('Location: connect-mail.php');
        exit;
    }
    $_SESSION['oauth_flow'] = 'connect';
} elseif ($flow === 'reconnect') {
    requireAdmin($config);
    HealNest\GoogleAuth::clearAccessTokenFile($config);
    $_SESSION['oauth_flow'] = 'reconnect';
} else {
    header('Location: login.php');
    exit;
}

$redirectUri = HealNest\OAuthRedirect::resolve($config);
$_SESSION['oauth_redirect_uri'] = $redirectUri;

$forceConsent = ($flow === 'reconnect');
$client = HealNest\GoogleAuth::createClient($config, $redirectUri, $forceConsent);
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

header('Location: ' . HealNest\GoogleAuth::authUrl($client, $state));
exit;
