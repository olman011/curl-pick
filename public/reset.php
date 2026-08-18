<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$token = trim((string)($_REQUEST['token'] ?? ''));
$reset = null;
if ($token !== '') {
    $reset = db_one(
        'SELECT * FROM password_resets
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
        [hash('sha256', $token)]
    );
}

if (is_post() && $reset) {
    csrf_check();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    $error = password_problem($password, $confirm);
    if ($error !== null) {
        flash($error, 'error');
        redirect('/reset.php?token=' . urlencode($token));
    }
    db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
    db_run('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$reset['id']]);
    flash('Password updated. You can sign in now.');
    redirect('/login.php');
}

layout_header('Reset password', false);
?>
<h1>Reset password</h1>
<?php if (!$reset): ?>
  <div class="card">
    <p>This reset link is invalid, expired, or already used.</p>
    <a class="btn btn-secondary" href="/forgot.php">Request a new one</a>
  </div>
<?php else: ?>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= h($token) ?>">
    <label class="field">New password
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <label class="field">Confirm new password
      <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
    </label>
    <button type="submit">Set new password</button>
  </form>
<?php endif; ?>
<?php layout_footer();
