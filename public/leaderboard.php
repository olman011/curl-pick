<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$user = require_login();
$weeks = weeks_all(true);
$weekId = get_int('week');
$week = $weekId ? week_find($weekId) : week_current();

layout_header('Leaderboard');
?>
<h1>Leaderboard</h1>
<p class="sub">One point for every correct winner. Ties score no point for anyone.</p>

<h2>Season</h2>
<table>
  <thead><tr><th>#</th><th>Member</th><th class="num">Correct</th><th class="num">Weeks</th></tr></thead>
  <tbody>
  <?php $rank = 0; foreach (season_leaderboard() as $row): $rank++; ?>
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
  <div class="week-nav">
    <?php foreach ($weeks as $w): ?>
      <a href="/leaderboard.php?week=<?= (int)$w['id'] ?>" class="<?= (int)$w['id'] === (int)$week['id'] ? 'active' : '' ?>">Wk <?= (int)$w['week_number'] ?></a>
    <?php endforeach; ?>
  </div>
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
