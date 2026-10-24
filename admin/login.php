<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/helpers.php';

HealNest\Session::start($config);

if (HealNest\Session::isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (HealNest\AdminAuth::verify($config, $username, $password)) {
        HealNest\Session::setAdmin($username, 'Administrator');
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}

$flash = $_GET['error'] ?? '';
$messages = [
    'oauth' => 'Mail connection failed. Try the connect-mail link again.',
    'denied' => 'That Google account is not allowed. Use: ' . googleAllowedEmail($config),
    'mail_required' => 'Connect Gmail once using the mail setup URL before using the dashboard.',
];

$mailReady = HealNest\GoogleAuth::isMailReadyForAdmin($config);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin login — The Heal Nest</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500&family=Karla:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
  <main class="login-card">
    <h1>The <span style="color:#c9826a">Heal</span> Nest</h1>
        <p>Sign in with your admin username and password to manage Gmail.</p>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($flash && isset($messages[$flash])): ?>
      <div class="alert alert-error"><?= h($messages[$flash]) ?></div>
    <?php endif; ?>
    <?php if ($flash === 'logout'): ?>
      <div class="alert alert-info">You have been signed out.</div>
    <?php endif; ?>
    <?php if (!$mailReady): ?>
      <div class="alert alert-info">Mail is not connected yet. Open the one-time setup URL first: <strong>/admin/connect-mail.php</strong></div>
    <?php endif; ?>

    <form method="post" action="login.php" class="login-form">
      <label class="field">
        <span>Username</span>
        <input type="text" name="username" autocomplete="username" required>
      </label>
      <label class="field">
        <span>Password</span>
        <input type="password" name="password" autocomplete="current-password" required>
      </label>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">Sign in</button>
    </form>
  </main>
</body>
</html>
