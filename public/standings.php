<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_login();

$season = season_resolve(get_int('season'));
$seasons = seasons_all();

layout_header('Standings');

if (!$season) {
    echo '<h1>Team standings</h1><p class="sub">No season has been created yet.</p>';
    layout_footer();
    exit;
}

$isArchive = (int)$season['is_active'] !== 1;
?>
<h1>Team standings</h1>
<p class="sub">Season: <?= h($season['name']) ?><?= $isArchive ? ' (archive)' : '' ?></p>
<p class="sub">2 points for a win, 1 for a tie. Diff is total points scored minus allowed.</p>

<?php if (count($seasons) > 1): ?>
<div class="week-nav">
  <?php foreach ($seasons as $s): ?>
    <a href="/standings.php?season=<?= (int)$s['id'] ?>" class="<?= (int)$s['id'] === (int)$season['id'] ? 'active' : '' ?>"><?= h($s['name']) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<table>
  <thead>
    <tr><th>#</th><th>Team</th><th class="num">GP</th><th class="num">W</th><th class="num">L</th><th class="num">T</th><th class="num">Pts</th><th class="num">Diff</th></tr>
  </thead>
  <tbody>
  <?php
  // Easy to tweak: swap these for different emoji, or change the streak threshold below.
  $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
  $streakFlame = '🔥';
  $streakMinimum = 3;
  $streaks = team_win_streaks((int)$season['id']);
  $position = 0;
  $tier = 0;
  $rank = 0;
  $prevPoints = null;
  $prevDiff = null;
  foreach (standings((int)$season['id']) as $row): $position++;
      // Teams tied on points and diff share a tier. $rank (position-based, with the
      // usual sports-style skip after a tie) drives the plain number shown once we're
      // past 3rd. $tier counts distinct scoring tiers with no skipping, so the 1st,
      // 2nd, and 3rd *tiers* always get gold/silver/bronze - even if an earlier tier
      // had multiple teams in it (e.g. two teams tied for 2nd still leaves a real
      // 3rd tier that gets bronze, rather than being skipped past medal range).
      if ($row['points'] !== $prevPoints || $row['diff'] !== $prevDiff) {
          $rank = $position;
          $tier++;
      }
      $prevPoints = $row['points'];
      $prevDiff = $row['diff'];
      $streak = $streaks[(int)$row['id']] ?? 0;
  ?>
    <tr>
      <td><?= $medals[$tier] ?? $rank ?></td>
      <td><?= h($row['name']) ?><?= $streak >= $streakMinimum ? ' ' . $streakFlame : '' ?></td>
      <td class="num"><?= (int)$row['played'] ?></td>
      <td class="num"><?= (int)$row['wins'] ?></td>
      <td class="num"><?= (int)$row['losses'] ?></td>
      <td class="num"><?= (int)$row['ties'] ?></td>
      <td class="num"><?= (int)$row['points'] ?></td>
      <td class="num"><?= ((int)$row['diff'] > 0 ? '+' : '') . (int)$row['diff'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php layout_footer();
