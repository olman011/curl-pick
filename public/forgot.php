<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

if (is_post()) {
    csrf_check();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $user = db_one('SELECT id, name, email FROM users WHERE email = ? AND is_active = 1', [$email]);
    if ($user) {
        if (mail_is_configured()) {
            // Issue a token now (unissued until the send confirms) and try to email it.
            $token = create_password_reset((int)$user['id'], false);
            $appName = (string)config('app.name');
            $result = send_mail(
                $user['email'],
                $user['name'],
                "Reset your $appName password",
                "Hi {$user['name']},\n\n"
                    . "Use the link below to set a new password. It's valid for 3 days and works once.\n\n"
                    . base_url('/reset.php?token=' . $token) . "\n\n"
                    . "If you didn't request this, you can ignore this email.\n\n"
                    . $appName
            );
            if ($result['ok']) {
                db_run('UPDATE password_resets SET issued_at = NOW() WHERE token_hash = ?', [hash('sha256', $token)]);
            }
            // If sending failed, the row from create_password_reset() above is left
            // unissued, so it still shows up for the admin to send manually.
        } else {
            // Email isn't configured at all - same as the original manual-only flow.
            db_run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
            db_run('INSERT INTO password_resets (user_id) VALUES (?)', [$user['id']]);
        }
    }
    // Same response either way so the form cannot be used to probe for accounts.
    flash('If that email is registered, a reset link is on its way - check your inbox in a few minutes. If you don\'t see it, the league admin will be notified and can send one manually.');
    redirect('/login.php');
}

layout_header('Forgot password', false);
?>
<h1>Forgot password</h1>
<p class="sub">Enter your email and we'll send you a one-time reset link.</p>
<form method="post" class="card">
  <?= csrf_field() ?>
  <label class="field">Email
    <input type="email" name="email" required autocomplete="email" autocapitalize="none">
  </label>
  <button type="submit">Request reset</button>
</form>
<p class="center"><a href="/login.php">Back to sign in</a></p>
<?php layout_footer();
