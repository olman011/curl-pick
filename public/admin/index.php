<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_admin();

$pendingResets = (int)db_value('SELECT COUNT(*) FROM password_resets WHERE used_at IS NULL AND issued_at IS NULL');
$memberCount = (int)db_value('SELECT COUNT(*) FROM users WHERE is_active = 1');
$teamCount = (int)db_value('SELECT COUNT(*) FROM teams WHERE is_active = 1');
$weekCount = (int)db_value('SELECT COUNT(*) FROM weeks');

layout_header('Admin');
?>
<h1>Admin</h1>
<p class="sub"><?= $memberCount ?> members &middot; <?= $teamCount ?> teams &middot; <?= $weekCount ?> weeks</p>
<div class="list-links">
  <a href="/admin/schedule.php">Weeks &amp; schedule</a>
  <a href="/admin/teams.php">Teams</a>
  <a href="/admin/invites.php">Invites &amp; QR codes</a>
  <a href="/admin/users.php">Members<?= $pendingResets > 0 ? ' (' . $pendingResets . ' reset request' . ($pendingResets === 1 ? '' : 's') . ')' : '' ?></a>
</div>
<?php layout_footer();
