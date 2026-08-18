<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$code = trim((string)($_REQUEST['code'] ?? ''));
$invite = $code !== '' ? find_usable_invite($code) : null;

if (is_post() && $invite) {
    csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    $error = null;
    if ($name === '') {
        $error = 'Please enter your name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (db_one('SELECT id FROM users WHERE email = ?', [$email])) {
        $error = 'That email already has an account. Try signing in instead.';
    } else {
        $error = password_problem($password, $confirm);
    }

    if ($error !== null) {
        flash($error, 'error');
    } else {
        db()->beginTransaction();
        try {
            $userId = create_user($name, $email, $password);
            db_run('UPDATE invites SET used_count = used_count + 1 WHERE id = ?', [$invite['id']]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }
        login_user(['id' => $userId]);
        flash('Welcome to the league! Get your picks in.');
        redirect('/index.php');
    }
    redirect('/signup.php?code=' . urlencode($code));
}

layout_header('Create your account', false);
?>
<h1>Create your account</h1>
<?php if (!$invite): ?>
  <div class="card">
    <p>This invite link is invalid, expired, or already used.</p>
    <p class="muted">Ask the league admin for a fresh invite link or QR code.</p>
    <a class="btn btn-secondary" href="/login.php">Back to sign in</a>
  </div>
<?php else: ?>
  <p class="sub">Invited<?= $invite['label'] ? ' as ' . h($invite['label']) : '' ?>. Pick a password you will remember.</p>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="code" value="<?= h($code) ?>">
    <label class="field">Name
      <input type="text" name="name" required autocomplete="name" value="<?= h((string)($_POST['name'] ?? '')) ?>">
    </label>
    <label class="field">Email
      <input type="email" name="email" required autocomplete="email" autocapitalize="none" value="<?= h((string)($_POST['email'] ?? '')) ?>">
    </label>
    <label class="field">Password
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <label class="field">Confirm password
      <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
    </label>
    <button type="submit">Create account</button>
  </form>
<?php endif; ?>
<?php layout_footer();
