<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_admin();

$pendingResetUsers = db_all(
    "SELECT u.name FROM password_resets pr JOIN users u ON u.id = pr.user_id
     WHERE pr.used_at IS NULL AND pr.issued_at IS NULL ORDER BY pr.requested_at"
);
$pendingResets = count($pendingResetUsers);
$memberCount = (int)db_value('SELECT COUNT(*) FROM users WHERE is_active = 1');
$season = season_active();
$teamCount = $season ? (int)db_value('SELECT COUNT(*) FROM teams WHERE season_id = ? AND is_active = 1', [$season['id']]) : 0;
$weekCount = $season ? (int)db_value('SELECT COUNT(*) FROM weeks WHERE season_id = ?', [$season['id']]) : 0;

layout_header('Admin');
?>
<h1>Admin</h1>
<p class="sub">
  <?= $season ? 'Active season: ' . h($season['name']) : '<span style="color:#b3271e">No active season yet &mdash; create one to get started.</span>' ?>
</p>
<p class="sub"><?= $memberCount ?> members &middot; <?= $teamCount ?> teams &middot; <?= $weekCount ?> weeks</p>
<?php if ($pendingResets > 0): ?>
  <div class="card">
    <strong><?= $pendingResets ?> password reset request<?= $pendingResets === 1 ? '' : 's' ?></strong>
    <div class="muted"><?= h(implode(', ', array_column($pendingResetUsers, 'name'))) ?></div>
    <a class="btn-small" style="margin-top:10px;display:inline-block" href="/admin/users.php">Review &amp; issue link<?= $pendingResets === 1 ? '' : 's' ?></a>
  </div>
<?php endif; ?>
<div class="list-links">
  <a href="/admin/seasons.php">Seasons</a>
  <a href="/admin/schedule.php">Weeks &amp; schedule</a>
  <a href="/admin/import.php">Import season (CSV)</a>
  <a href="/admin/teams.php">Teams</a>
  <a href="/admin/invites.php">Invites &amp; QR codes</a>
  <a href="/admin/users.php">Members</a>
</div>
<?php layout_footer();
