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
  $rank = 0;
  $prevPoints = null;
  $prevDiff = null;
  foreach (standings((int)$season['id']) as $row): $position++;
      // Teams tied on points and diff share the same rank/medal; the next distinct
      // place picks up at its true position (e.g. two teams tied for 2nd means the
      // next team is 4th, not 3rd - same as how ties work at the Olympics).
      if ($row['points'] !== $prevPoints || $row['diff'] !== $prevDiff) {
          $rank = $position;
      }
      $prevPoints = $row['points'];
      $prevDiff = $row['diff'];
      $streak = $streaks[(int)$row['id']] ?? 0;
  ?>
    <tr>
      <td><?= $medals[$rank] ?? $rank ?></td>
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
