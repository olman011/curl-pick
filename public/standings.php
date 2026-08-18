<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_login();
layout_header('Standings');
?>
<h1>Team standings</h1>
<p class="sub">2 points for a win, 1 for a tie. Diff is total points scored minus allowed.</p>
<table>
  <thead>
    <tr><th>#</th><th>Team</th><th class="num">GP</th><th class="num">W</th><th class="num">L</th><th class="num">T</th><th class="num">Pts</th><th class="num">Diff</th></tr>
  </thead>
  <tbody>
  <?php $rank = 0; foreach (standings() as $row): $rank++; ?>
    <tr>
      <td><?= $rank ?></td>
      <td><?= h($row['name']) ?></td>
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
