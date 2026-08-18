<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

if (current_user()) {
    redirect('/index.php');
}

if (is_post()) {
    csrf_check();
    $user = attempt_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($user) {
        login_user($user);
        redirect('/index.php');
    }
    flash('Email or password is incorrect.', 'error');
    redirect('/login.php');
}

layout_header('Sign in', false);
?>
<h1>Sign in</h1>
<p class="sub">Members only. Ask the league admin for an invite link if you do not have an account.</p>
<form method="post" class="card">
  <?= csrf_field() ?>
  <label class="field">Email
    <input type="email" name="email" required autocomplete="email" autocapitalize="none">
  </label>
  <label class="field">Password
    <input type="password" name="password" required autocomplete="current-password">
  </label>
  <button type="submit">Sign in</button>
</form>
<p class="center"><a href="/forgot.php">Forgot your password?</a></p>
<?php layout_footer();
