<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/lib/bootstrap.php';
require dirname(__DIR__) . '/lib/helpers.php';

requireAdmin($config);

if (!HealNest\GoogleAuth::isMailConnected($config)) {
    header('Location: login.php?error=mail_required');
    exit;
}

if (!HealNest\GoogleAuth::hasGmailReadScope($config)) {
    header('Location: connect-mail.php');
    exit;
}

$client = HealNest\GoogleAuth::clientWithToken($config);
$apiError = null;
$flash = '';
$gmail = [];
$inboxNextPage = null;
$inboxEstimate = null;
$inboxPerPage = 50;
$inboxPageToken = trim((string) ($_GET['page'] ?? ''));
$viewMessage = null;
$compose = isset($_GET['compose']);
$replyTo = null;
$canReadGmail = HealNest\GoogleAuth::hasGmailReadScope($config);
$canWriteGmail = HealNest\GoogleAuth::hasGmailModifyScope($config);

if ($client === null) {
    $apiError = 'Google access expired or missing. Use Reconnect Google while signed in as admin.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $client !== null) {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if (in_array($action, ['send', 'trash', 'delete'], true) && !$canWriteGmail) {
            throw new RuntimeException('Write access requires gmail.modify. Reconnect Google and approve send/manage mail, or use read-only inbox if gmail.readonly is granted.');
        }
        if ($action === 'send') {
            $to = trim((string) ($_POST['to'] ?? ''));
            $subject = trim((string) ($_POST['subject'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $threadId = trim((string) ($_POST['thread_id'] ?? ''));
            $inReplyTo = trim((string) ($_POST['in_reply_to'] ?? ''));
            if ($to === '' || $subject === '' || $body === '') {
                throw new InvalidArgumentException('To, subject, and message are required.');
            }
            HealNest\GoogleServices::sendGmail(
                $client,
                $to,
                $subject,
                $body,
                $threadId !== '' ? $threadId : null,
                $inReplyTo !== '' ? $inReplyTo : null
            );
            header('Location: dashboard.php?sent=1');
            exit;
        }
        if ($action === 'trash') {
            $id = (string) ($_POST['message_id'] ?? '');
            if ($id !== '') {
                HealNest\GoogleServices::trashGmailMessage($client, $id);
            }
            header('Location: dashboard.php?trashed=1');
            exit;
        }
        if ($action === 'delete') {
            $id = (string) ($_POST['message_id'] ?? '');
            if ($id !== '') {
                HealNest\GoogleServices::deleteGmailMessage($client, $id);
            }
            header('Location: dashboard.php?deleted=1');
            exit;
        }
    } catch (Throwable $e) {
        $apiError = $e->getMessage();
    }
}

if ($client !== null && $apiError === null) {
    if ($compose && !$canWriteGmail) {
        header('Location: dashboard.php');
        exit;
    }
    try {
        $viewId = (string) ($_GET['view'] ?? '');
        if ($viewId !== '') {
            $viewMessage = HealNest\GoogleServices::getGmailMessage($client, $viewId);
            if ($viewMessage === null) {
                $apiError = 'Message not found.';
            }
        } elseif (isset($_GET['reply'])) {
            $replyId = (string) $_GET['reply'];
            $replyTo = HealNest\GoogleServices::getGmailMessage($client, $replyId);
            $compose = true;
        } else {
            $inbox = HealNest\GoogleServices::listGmailMessages(
                $client,
                $inboxPerPage,
                $inboxPageToken !== '' ? $inboxPageToken : null
            );
            $gmail = $inbox['messages'];
            $inboxNextPage = $inbox['nextPageToken'];
            $inboxEstimate = $inbox['resultSizeEstimate'];
        }
    } catch (Throwable $e) {
        if ($e->getMessage() === 'GMAIL_SCOPE_MISSING') {
            $apiError = 'Gmail permission missing or outdated on this token. Click Reconnect Google (or connect-mail) and allow Gmail read at minimum; allow send/manage for compose and delete. Ensure config.php uses the same OAuth Client ID as when the token was created.';
        } else {
            $apiError = 'Could not load mail. (' . $e->getMessage() . ')';
        }
    }
}

if (isset($_GET['sent'])) {
    $flash = 'Email sent.';
} elseif (isset($_GET['trashed'])) {
    $flash = 'Message moved to trash.';
} elseif (isset($_GET['deleted'])) {
    $flash = 'Message permanently deleted.';
}

$scopeWarning = $client !== null && $canReadGmail && !$canWriteGmail;
$adminUser = HealNest\Session::adminEmail() ?? '';
$connectedGoogle = HealNest\GoogleAuth::getConnectedEmail($config) ?? '';
$gmailAccessLevel = HealNest\GoogleAuth::gmailAccessLevel($config);
$gmailAccessLabel = HealNest\GoogleAuth::gmailAccessLabel($config);
$tokenActive = $client !== null;
$inboxGranted = HealNest\GoogleAuth::isGmailInboxVisible($config);
$inboxVisible = $tokenActive && $inboxGranted;

function parseReplyEmail(string $from): string
{
    if (preg_match('/<([^>]+)>/', $from, $m)) {
        return trim($m[1]);
    }

    return trim($from);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Gmail — The Heal Nest Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
  <header class="admin-header">
    <div>
      <h1>The <span>Heal</span> Nest — Gmail</h1>
      <p class="admin-meta">Admin: <?= h($adminUser) ?> · <?= h($connectedGoogle) ?></p>
    </div>
    <div class="admin-actions">
      <a class="btn btn-ghost" href="../index.html">Public site</a>
      <?php if ($canWriteGmail): ?>
        <a class="btn btn-primary" href="dashboard.php?compose=1">Compose</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="oauth-start.php?flow=reconnect">Reconnect Google</a>
      <form method="post" action="disconnect-google.php" class="inline-form" onsubmit="return confirm('Disconnect Gmail from the admin panel?');">
        <button type="submit" class="btn btn-danger">Disconnect Gmail</button>
      </form>
      <a class="btn btn-ghost" href="logout.php">Sign out</a>
    </div>
  </header>

  <main class="admin-main">
    <aside class="mail-connection panel" aria-label="Gmail connection status">
      <h2 class="mail-connection-title">Email connection</h2>
      <dl class="mail-connection-list">
        <div>
          <dt>Google account</dt>
          <dd><?= $connectedGoogle !== '' ? h($connectedGoogle) : '—' ?></dd>
        </div>
        <div>
          <dt>Token</dt>
          <dd>
            <?php if ($tokenActive): ?>
              <span class="access-badge access-badge--ok">Active</span>
            <?php else: ?>
              <span class="access-badge access-badge--bad">Inactive — reconnect</span>
            <?php endif; ?>
          </dd>
        </div>
        <div>
          <dt>Gmail access</dt>
          <dd>
            <?php if ($gmailAccessLevel === 'read_write'): ?>
              <span class="access-badge access-badge--write">Read &amp; write</span>
              <span class="access-hint">Inbox, compose, reply, trash, delete</span>
            <?php elseif ($gmailAccessLevel === 'readonly'): ?>
              <span class="access-badge access-badge--read">Read-only</span>
              <span class="access-hint">Inbox &amp; view messages</span>
            <?php else: ?>
              <span class="access-badge access-badge--bad">Not granted</span>
              <span class="access-hint"><a href="connect-mail.php">Authorize Gmail</a></span>
            <?php endif; ?>
          </dd>
        </div>
        <div>
          <dt>Inbox in admin</dt>
          <dd>
            <?php if ($inboxVisible): ?>
              <span class="access-badge access-badge--ok">Visible</span>
              <span class="access-hint">Read-only or read &amp; write</span>
            <?php else: ?>
              <span class="access-badge access-badge--bad">Hidden</span>
              <span class="access-hint">Connect Gmail with read or send permission</span>
            <?php endif; ?>
          </dd>
        </div>
      </dl>
      <p class="mail-connection-note"><?= h($gmailAccessLabel) ?> · Stored on server (works from any device with admin login).</p>
    </aside>

    <?php if ($scopeWarning): ?>
      <div class="alert alert-info"><strong>Read-only mode.</strong> You can view inbox and messages. To send, reply, trash, or delete, <a href="oauth-start.php?flow=reconnect"><strong>Reconnect Google</strong></a> and approve <strong>send and manage mail</strong>.</div>
    <?php endif; ?>
    <?php if ($flash !== ''): ?>
      <div class="alert alert-info"><?= h($flash) ?></div>
    <?php endif; ?>
    <?php if ($apiError): ?>
      <div class="alert alert-error"><?= h($apiError) ?></div>
    <?php endif; ?>

    <section class="panel">
      <?php if ($compose): ?>
        <h2><?= $replyTo ? 'Reply' : 'Compose email' ?></h2>
        <p style="margin-bottom:16px;"><a href="dashboard.php">&larr; Back to inbox</a></p>
        <form method="post" action="dashboard.php" class="compose-form">
          <input type="hidden" name="action" value="send">
          <?php if ($replyTo): ?>
            <input type="hidden" name="thread_id" value="<?= h($replyTo['threadId']) ?>">
            <input type="hidden" name="in_reply_to" value="<?= h($replyTo['messageIdHeader']) ?>">
          <?php endif; ?>
          <label class="field">
            <span>To</span>
            <input type="email" name="to" required value="<?= h($replyTo ? parseReplyEmail($replyTo['from']) : '') ?>">
          </label>
          <label class="field">
            <span>Subject</span>
            <input type="text" name="subject" required value="<?= h($replyTo ? (str_starts_with(strtolower($replyTo['subject']), 're:') ? $replyTo['subject'] : 'Re: ' . $replyTo['subject']) : '') ?>">
          </label>
          <label class="field">
            <span>Message</span>
            <textarea name="body" rows="10" required><?php if ($replyTo): ?>

--- Reply above ---

<?= h($replyTo['body']) ?><?php endif; ?></textarea>
          </label>
          <button type="submit" class="btn btn-primary">Send email</button>
        </form>

      <?php elseif ($viewMessage): ?>
        <h2><?= h($viewMessage['subject']) ?></h2>
        <p style="margin-bottom:16px;"><a href="dashboard.php">&larr; Back to inbox</a></p>
        <div class="mail-meta" style="margin-bottom:16px;">
          <div><strong>From:</strong> <?= h($viewMessage['from']) ?></div>
          <div><strong>To:</strong> <?= h($viewMessage['to']) ?></div>
          <div><strong>Date:</strong> <?= h($viewMessage['date']) ?></div>
        </div>
        <div class="mail-body"><?= nl2br(h($viewMessage['body'])) ?></div>
        <?php if ($canWriteGmail): ?>
          <div class="mail-actions" style="margin-top:24px;display:flex;flex-wrap:wrap;gap:10px;">
            <a class="btn btn-primary" href="dashboard.php?reply=<?= urlencode($viewMessage['id']) ?>">Reply</a>
            <form method="post" action="dashboard.php" class="inline-form" onsubmit="return confirm('Move this message to trash?');">
              <input type="hidden" name="action" value="trash">
              <input type="hidden" name="message_id" value="<?= h($viewMessage['id']) ?>">
              <button type="submit" class="btn btn-ghost">Move to trash</button>
            </form>
            <form method="post" action="dashboard.php" class="inline-form" onsubmit="return confirm('Permanently delete this message? This cannot be undone.');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="message_id" value="<?= h($viewMessage['id']) ?>">
              <button type="submit" class="btn btn-danger">Delete permanently</button>
            </form>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <h2>Inbox</h2>
        <?php if (!$apiError): ?>
          <p class="inbox-summary">
            Showing <?= count($gmail) ?> message<?= count($gmail) === 1 ? '' : 's' ?>
            (up to <?= (int) $inboxPerPage ?> per page, <strong>Inbox</strong> only — not your full Gmail account).
            <?php if ($inboxEstimate !== null): ?>
              Gmail reports about <?= (int) $inboxEstimate ?> in Inbox.
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if (!$apiError && count($gmail) === 0): ?>
          <p class="panel-empty">Inbox is empty or no messages loaded.</p>
        <?php elseif (!$apiError): ?>
          <ul class="mail-list">
            <?php foreach ($gmail as $m): ?>
              <li class="mail-item">
                <div class="mail-row">
                  <div class="mail-row-main">
                    <a class="mail-subject" href="dashboard.php?view=<?= urlencode($m['id']) ?>"><?= h($m['subject']) ?></a>
                    <div class="mail-meta"><?= h($m['from']) ?> · <?= h($m['date']) ?></div>
                    <div class="mail-snippet"><?= h($m['snippet']) ?></div>
                  </div>
                  <?php if ($canWriteGmail): ?>
                    <div class="mail-row-actions">
                      <form method="post" action="dashboard.php" class="inline-form" onsubmit="return confirm('Move to trash?');">
                        <input type="hidden" name="action" value="trash">
                        <input type="hidden" name="message_id" value="<?= h($m['id']) ?>">
                        <button type="submit" class="btn btn-ghost btn-sm">Trash</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
          <nav class="inbox-pager" aria-label="Inbox pages">
            <?php if ($inboxPageToken !== ''): ?>
              <a class="btn btn-ghost btn-sm" href="dashboard.php">Newest messages</a>
            <?php endif; ?>
            <?php if ($inboxNextPage !== null && $inboxNextPage !== ''): ?>
              <a class="btn btn-ghost btn-sm" href="dashboard.php?page=<?= urlencode($inboxNextPage) ?>">Older messages</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>

      <p class="reconnect">Gmail is connected on the server. Use <strong>Reconnect Google</strong> after changing permissions (read, send, delete).</p>
    </section>
  </main>
</body>
</html>
