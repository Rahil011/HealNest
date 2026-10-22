<?php

declare(strict_types=1);

/**
 * One-time URL: connect Gmail for the allowed account.
 * After success, use /admin/login.php with admin username & password only.
 */

$config = require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/helpers.php';

HealNest\Session::start($config);

$connected = HealNest\GoogleAuth::isMailConnected($config);
$connectedEmail = HealNest\GoogleAuth::getConnectedEmail($config);
$success = isset($_GET['success']);
$disconnected = isset($_GET['disconnected']);
$allowed = googleAllowedEmail($config);
$redirectPreview = HealNest\OAuthRedirect::resolve($config);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Connect mail — The Heal Nest</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500&family=Karla:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
  <main class="login-card">
    <h1>Mail setup</h1>
    <p>One-time Google authorization for The Heal Nest.</p>

    <?php if ($disconnected): ?>
      <div class="alert alert-info">Google account disconnected.</div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-info">Google account connected successfully.</div>
    <?php elseif ($connected): ?>
      <div class="alert alert-info">
        Mail is already connected<?= $connectedEmail ? ' as ' . h($connectedEmail) : '' ?>.
      </div>
    <?php else: ?>
      <p style="font-size:14px;color:#6b6459;margin-bottom:20px;line-height:1.6;">
        Click below and sign in with <strong><?= h($allowed) ?></strong>. This step is only needed once to connect your Mail to the Heal Nest to receive emails.
      </p>
      <a class="btn btn-primary" href="oauth-start.php?flow=connect">Connect Google account</a>
    <?php endif; ?>

  </main>
</body>
</html>
