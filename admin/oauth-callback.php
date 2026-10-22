<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/helpers.php';

HealNest\Session::start($config);

$flow = (string) ($_SESSION['oauth_flow'] ?? 'connect');

if (empty($_GET['code']) || empty($_GET['state'])) {
    header('Location: ' . ($flow === 'reconnect' ? 'dashboard.php?error=oauth' : 'connect-mail.php'));
    exit;
}

if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], (string) $_GET['state'])) {
    unset($_SESSION['oauth_flow']);
    header('Location: connect-mail.php');
    exit;
}

unset($_SESSION['oauth_state']);

$redirectUri = (string) ($_SESSION['oauth_redirect_uri'] ?? HealNest\OAuthRedirect::resolve($config));
unset($_SESSION['oauth_redirect_uri']);

$client = HealNest\GoogleAuth::createClient($config, $redirectUri);

try {
    $token = $client->fetchAccessTokenWithAuthCode((string) $_GET['code']);
} catch (Throwable) {
    unset($_SESSION['oauth_flow']);
    header('Location: login.php?error=oauth');
    exit;
}

if (isset($token['error'])) {
    unset($_SESSION['oauth_flow']);
    header('Location: login.php?error=oauth');
    exit;
}

$client->setAccessToken($token);

$oauth2 = new Google\Service\Oauth2($client);
$userinfo = $oauth2->userinfo->get();
$email = strtolower(trim((string) ($userinfo->getEmail() ?? '')));
$allowed = googleAllowedEmail($config);

if ($allowed !== '' && $email !== $allowed) {
    HealNest\GoogleAuth::clearToken($config);
    unset($_SESSION['oauth_flow']);
    header('Location: login.php?error=denied');
    exit;
}

try {
    HealNest\GoogleAuth::persistToken($client, $config);
    HealNest\GoogleAuth::markMailConnected($config, $email);
} catch (Throwable) {
    unset($_SESSION['oauth_flow']);
    header('Location: login.php?error=oauth');
    exit;
}

if ($flow === 'reconnect') {
    unset($_SESSION['oauth_flow']);
    header('Location: dashboard.php');
    exit;
}

unset($_SESSION['oauth_flow']);
header('Location: connect-mail.php?success=1');
exit;
