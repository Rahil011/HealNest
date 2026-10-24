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
$gmailOk = HealNest\GoogleAuth::hasGmailReadScope($config);
$connectedEmail = HealNest\GoogleAuth::getConnectedEmail($config);
$success = isset($_GET['success']);
$disconnected = isset($_GET['disconnected']);
$scopeError = isset($_GET['error']) && $_GET['error'] === 'gmail_scope';
$allowed = googleAllowedEmail($config);
$redirectPreview = HealNest\OAuthRedirect::resolve($config);
$accessLevel = HealNest\GoogleAuth::gmailAccessLevel($config);

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
    <p>Connect Gmail for The Heal Nest admin panel.</p>

    <?php if ($scopeError): ?>
      <div class="alert alert-error">Gmail access was not granted. On the Google screen, expand permissions and allow <strong>Read, compose, send, and permanently delete</strong> (or at least read your email). Then try again.</div>
    <?php endif; ?>
    <?php if ($disconnected): ?>
      <div class="alert alert-info">Google account disconnected. Connect again below.</div>
    <?php endif; ?>
    <?php if ($success && $gmailOk): ?>
      <div class="alert alert-info">Gmail connected successfully.</div>
    <?php elseif ($connected && $gmailOk): ?>
      <div class="alert alert-info">
        Gmail is connected<?= $connectedEmail ? ' as ' . h($connectedEmail) : '' ?>.
        <?php if ($accessLevel === 'read_write'): ?>
          <span class="access-badge access-badge--write">Read &amp; write</span>
        <?php else: ?>
          <span class="access-badge access-badge--read">Read-only</span>
        <?php endif; ?>
        <a href="login.php">Admin login</a>
      </div>
    <?php else: ?>
      <?php if ($connected && !$gmailOk): ?>
        <div class="alert alert-error">Account is linked but <strong>Gmail permission is missing</strong> on the token. Authorize Gmail below (use <?= h($allowed) ?>).</div>
      <?php endif; ?>
      <p style="font-size:14px;color:#6b6459;margin-bottom:20px;line-height:1.6;">
        Sign in with <strong><?= h($allowed) ?></strong> and approve <strong>all Gmail permissions</strong> shown by Google.
      </p>
      <a class="btn btn-primary" href="oauth-start.php?flow=connect">Authorize Gmail access</a>
    <?php endif; ?>

  </main>
</body>
</html>
