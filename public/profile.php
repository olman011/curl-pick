<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$user = require_login();

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('Name cannot be empty.', 'error');
        } else {
            db_run('UPDATE users SET name = ? WHERE id = ?', [$name, $user['id']]);
            flash('Profile updated.');
        }
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if (!password_verify($current, $user['password_hash'])) {
            flash('Current password is incorrect.', 'error');
        } elseif (($problem = password_problem($password, $confirm)) !== null) {
            flash($problem, 'error');
        } else {
            db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            flash('Password changed.');
        }
    }
    redirect('/profile.php');
}

layout_header('My account');
?>
<h1>My account</h1>
<p class="sub"><?= h($user['email']) ?><?= (int)$user['is_admin'] === 1 ? ' &middot; admin' : '' ?></p>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="profile">
  <label class="field">Display name
    <input type="text" name="name" required value="<?= h($user['name']) ?>">
  </label>
  <button type="submit">Save name</button>
</form>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="password">
  <label class="field">Current password
    <input type="password" name="current_password" required autocomplete="current-password">
  </label>
  <label class="field">New password
    <input type="password" name="password" required minlength="8" autocomplete="new-password">
  </label>
  <label class="field">Confirm new password
    <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
  </label>
  <button type="submit">Change password</button>
</form>

<a class="btn btn-secondary" href="/logout.php">Sign out</a>
<?php layout_footer();
