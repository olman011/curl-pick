<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$admin = require_admin();
$issuedLink = null;

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $userId = post_int('user_id');

    if ($action === 'toggle_admin' && $userId && $userId !== (int)$admin['id']) {
        db_run('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?', [$userId]);
        flash('Member updated.');
    } elseif ($action === 'toggle_active' && $userId && $userId !== (int)$admin['id']) {
        db_run('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$userId]);
        flash('Member updated.');
    } elseif ($action === 'issue_reset' && $userId) {
        $token = bin2hex(random_bytes(24));
        db_run(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL',
            [$userId]
        );
        db_run(
            'INSERT INTO password_resets (user_id, token_hash, issued_at, expires_at)
             VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY))',
            [$userId, hash('sha256', $token)]
        );
        $resetUrl = base_url('/reset.php?token=' . $token);
        $_SESSION['issued_reset'] = ['user_id' => $userId, 'url' => $resetUrl];

        $target = db_one('SELECT name, email FROM users WHERE id = ?', [$userId]);
        if ($target && mail_is_configured()) {
            $appName = (string)config('app.name');
            $result = send_mail(
                $target['email'],
                $target['name'],
                "Reset your $appName password",
                "Hi {$target['name']},\n\n"
                    . "Use the link below to set a new password. It's valid for 3 days and works once.\n\n"
                    . "$resetUrl\n\n"
                    . "If you didn't request this, you can ignore this email.\n\n"
                    . $appName
            );
            if ($result['ok']) {
                flash('Reset link emailed to ' . $target['email'] . '.');
            } else {
                flash('Could not email the link automatically (' . $result['error'] . '). Use the copyable link below instead.', 'error');
            }
        } else {
            flash('Reset link created. Email is not configured, so copy the link below and send it manually.');
        }
        redirect('/admin/users.php');
    }
    redirect('/admin/users.php');
}

if (!empty($_SESSION['issued_reset'])) {
    $issuedLink = $_SESSION['issued_reset'];
    unset($_SESSION['issued_reset']);
}

$pending = db_all(
    'SELECT pr.id, pr.requested_at, u.id AS user_id, u.name, u.email
     FROM password_resets pr JOIN users u ON u.id = pr.user_id
     WHERE pr.used_at IS NULL AND pr.issued_at IS NULL
     ORDER BY pr.requested_at'
);
$users = db_all('SELECT * FROM users ORDER BY name');

layout_header('Members');
?>
<h1>Members</h1>

<?php if ($issuedLink): ?>
  <div class="card">
    <strong>One-time reset link (valid 3 days)</strong>
    <p class="muted" style="margin:4px 0 8px">Also shown here as a backup, whether or not the email above went out.</p>
    <p class="mono"><?= h($issuedLink['url']) ?></p>
    <button class="btn-small btn-secondary" type="button" onclick="navigator.clipboard.writeText('<?= h($issuedLink['url']) ?>')">Copy link</button>
  </div>
<?php endif; ?>

<?php if ($pending): ?>
  <h2>Reset requests</h2>
  <?php foreach ($pending as $row): ?>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
      <strong><?= h($row['name']) ?></strong>
      <div class="muted"><?= h($row['email']) ?> &middot; asked <?= h(fmt_datetime($row['requested_at'])) ?></div>
      <button class="btn-small" style="margin-top:10px" type="submit" name="action" value="issue_reset">Issue reset link</button>
    </form>
  <?php endforeach; ?>
<?php endif; ?>

<h2>All members</h2>
<?php foreach ($users as $row): ?>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
    <strong><?= h($row['name']) ?></strong><?= (int)$row['is_admin'] === 1 ? ' &middot; admin' : '' ?><?= (int)$row['is_active'] === 1 ? '' : ' &middot; disabled' ?>
    <div class="muted"><?= h($row['email']) ?> &middot; joined <?= h(fmt_date($row['created_at'])) ?></div>
    <div class="row" style="margin-top:10px">
      <button class="btn-small btn-secondary" type="submit" name="action" value="issue_reset">Reset link</button>
      <?php if ((int)$row['id'] !== (int)$admin['id']): ?>
        <button class="btn-small btn-secondary" type="submit" name="action" value="toggle_admin"><?= (int)$row['is_admin'] === 1 ? 'Remove admin' : 'Make admin' ?></button>
        <button class="btn-small btn-danger" type="submit" name="action" value="toggle_active"><?= (int)$row['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
      <?php endif; ?>
    </div>
  </form>
<?php endforeach; ?>
<p class="center"><a href="/admin/index.php">Back to admin</a></p>
<?php layout_footer();
