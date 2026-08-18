<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$admin = require_admin();

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $label = trim((string)($_POST['label'] ?? ''));
        $maxUses = max(1, (int)($_POST['max_uses'] ?? 1));
        $days = max(1, (int)($_POST['expires_days'] ?? 30));
        db_run(
            'INSERT INTO invites (code, label, max_uses, expires_at, created_by) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), ?)',
            [bin2hex(random_bytes(16)), $label ?: null, $maxUses, $days, $admin['id']]
        );
        flash('Invite created.');
    } elseif ($action === 'revoke') {
        $id = post_int('invite_id');
        if ($id) {
            db_run('UPDATE invites SET expires_at = NOW() WHERE id = ?', [$id]);
            flash('Invite revoked.');
        }
    }
    redirect('/admin/invites.php');
}

$invites = db_all('SELECT * FROM invites ORDER BY created_at DESC');
layout_header('Invites');
?>
<h1>Invites</h1>
<p class="sub">Share the link or let people scan the QR code. Use a multi-use invite for a whole team.</p>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <label class="field">Label (optional)
    <input type="text" name="label" placeholder="e.g. Tuesday league">
  </label>
  <div class="row">
    <label class="field">Max uses
      <input type="number" name="max_uses" min="1" value="1">
    </label>
    <label class="field">Expires in (days)
      <input type="number" name="expires_days" min="1" value="30">
    </label>
  </div>
  <button type="submit">Create invite</button>
</form>

<?php foreach ($invites as $invite):
  $url = base_url('/signup.php?code=' . $invite['code']);
  $expired = $invite['expires_at'] !== null && strtotime($invite['expires_at']) < time();
  $spent = (int)$invite['used_count'] >= (int)$invite['max_uses'];
?>
  <div class="card">
    <strong><?= h($invite['label'] ?: 'Invite') ?></strong>
    <div class="muted">
      <?= (int)$invite['used_count'] ?>/<?= (int)$invite['max_uses'] ?> used
      <?= $invite['expires_at'] ? '&middot; expires ' . h(fmt_datetime($invite['expires_at'])) : '' ?>
      <?= $expired ? '&middot; expired' : ($spent ? '&middot; fully used' : '') ?>
    </div>
    <?php if (!$expired && !$spent): ?>
      <div class="qr" data-qr="<?= h($url) ?>"></div>
      <p class="mono"><?= h($url) ?></p>
      <div class="row">
        <button class="btn-small btn-secondary" type="button" onclick="navigator.clipboard.writeText('<?= h($url) ?>')">Copy link</button>
        <a class="btn btn-small btn-secondary" href="mailto:?subject=<?= rawurlencode('Join ' . (string)config('app.name')) ?>&amp;body=<?= rawurlencode("You're invited to the picks league. Create your account here: " . $url) ?>">Email invite</a>
      </div>
      <form method="post" style="margin-top:10px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="revoke">
        <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
        <button class="btn-small btn-danger" type="submit">Revoke</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<p class="center"><a href="/admin/index.php">Back to admin</a></p>
<script src="/assets/qrcode.min.js"></script>
<script>
document.querySelectorAll('[data-qr]').forEach(function (el) {
  new QRCode(el, { text: el.dataset.qr, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M });
});
</script>
<?php layout_footer();
