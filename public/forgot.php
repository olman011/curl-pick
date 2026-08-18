<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

if (is_post()) {
    csrf_check();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $user = db_one('SELECT id FROM users WHERE email = ? AND is_active = 1', [$email]);
    if ($user) {
        $pending = db_one(
            'SELECT id FROM password_resets WHERE user_id = ? AND used_at IS NULL AND issued_at IS NULL',
            [$user['id']]
        );
        if (!$pending) {
            db_run('INSERT INTO password_resets (user_id) VALUES (?)', [$user['id']]);
        }
    }
    // Same response either way so the form cannot be used to probe for accounts.
    flash('Request received. The league admin will send you a reset link.');
    redirect('/login.php');
}

layout_header('Forgot password', false);
?>
<h1>Forgot password</h1>
<p class="sub">Enter your email and the league admin will send you a one-time reset link.</p>
<form method="post" class="card">
  <?= csrf_field() ?>
  <label class="field">Email
    <input type="email" name="email" required autocomplete="email" autocapitalize="none">
  </label>
  <button type="submit">Request reset</button>
</form>
<p class="center"><a href="/login.php">Back to sign in</a></p>
<?php layout_footer();
