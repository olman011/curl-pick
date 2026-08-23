<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$user = require_login();

$season = season_resolve(get_int('season'));
$seasons = seasons_all();

layout_header('Leaderboard');

if (!$season) {
    echo '<h1>Leaderboard</h1><p class="sub">No season has been created yet.</p>';
    layout_footer();
    exit;
}

$isArchive = (int)$season['is_active'] !== 1;
$weeks = weeks_all(true, (int)$season['id']);
$weekId = get_int('week');
$week = $weekId ? week_find($weekId) : null;
if ($week && (int)$week['season_id'] !== (int)$season['id']) {
    $week = null;
}
if (!$week) {
    $week = $isArchive ? season_latest_week((int)$season['id']) : week_current();
}
?>
<h1>Leaderboard</h1>
<p class="sub">Season: <?= h($season['name']) ?><?= $isArchive ? ' (archive)' : '' ?></p>
<p class="sub">One point for every correct winner. Ties score no point for anyone.</p>

<?php if (count($seasons) > 1): ?>
<div class="week-nav">
  <?php foreach ($seasons as $s): ?>
    <a href="/leaderboard.php?season=<?= (int)$s['id'] ?>" class="<?= (int)$s['id'] === (int)$season['id'] ? 'active' : '' ?>"><?= h($s['name']) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<h2>Season</h2>
<table>
  <thead><tr><th>#</th><th>Member</th><th class="num">Correct</th><th class="num">Weeks</th></tr></thead>
  <tbody>
  <?php $rank = 0; foreach (season_leaderboard((int)$season['id']) as $row): $rank++; ?>
    <tr class="<?= (int)$row['id'] === (int)$user['id'] ? 'me' : '' ?>">
      <td><?= $rank ?></td>
      <td><?= h($row['name']) ?></td>
      <td class="num"><?= (int)$row['correct'] ?></td>
      <td class="num"><?= (int)$row['weeks_played'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($week): ?>
  <h2>Week <?= (int)$week['week_number'] ?></h2>
  <?php if (count($weeks) > 1): ?>
  <div class="week-nav">
    <?php foreach ($weeks as $w): ?>
      <a href="/leaderboard.php?season=<?= (int)$season['id'] ?>&amp;week=<?= (int)$w['id'] ?>" class="<?= (int)$w['id'] === (int)$week['id'] ? 'active' : '' ?>">Wk <?= (int)$w['week_number'] ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php $rows = weekly_leaderboard((int)$week['id']); ?>
  <?php if (!$rows): ?>
    <div class="card">No picks submitted for this week yet.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>#</th><th>Member</th><th class="num">Correct</th><th class="num">Picks</th></tr></thead>
    <tbody>
    <?php $rank = 0; foreach ($rows as $row): $rank++; ?>
      <tr class="<?= (int)$row['id'] === (int)$user['id'] ? 'me' : '' ?>">
        <td><?= $rank ?></td>
        <td><?= h($row['name']) ?></td>
        <td class="num"><?= (int)$row['correct'] ?></td>
        <td class="num"><?= (int)$row['picks_made'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
<?php endif; ?>
<?php layout_footer();
