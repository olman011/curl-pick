<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$user = require_login();
$weeks = weeks_all(true);
$weekId = get_int('week');
$week = $weekId ? week_find($weekId) : week_current();

layout_header('Results');
?>
<h1>Results</h1>
<?php if (!$week): ?>
  <p class="sub">No weeks published yet.</p>
  <?php layout_footer(); exit; ?>
<?php endif; ?>
<p class="sub">Week <?= (int)$week['week_number'] ?> &middot; <?= h(fmt_date($week['game_date'])) ?></p>

<div class="week-nav">
  <?php foreach ($weeks as $w): ?>
    <a href="/results.php?week=<?= (int)$w['id'] ?>" class="<?= (int)$w['id'] === (int)$week['id'] ? 'active' : '' ?>">Wk <?= (int)$w['week_number'] ?></a>
  <?php endforeach; ?>
</div>

<?php
$games = week_results_summary((int)$week['id']);
$picks = user_picks_for_week((int)$user['id'], (int)$week['id']);
$locked = week_is_locked($week);
if (!$games): ?>
  <div class="card">No games scheduled.</div>
<?php else: foreach ($games as $game):
    $winner = $game['winner_id'];
    $picked = $picks[(int)$game['id']] ?? null;
?>
  <div class="game">
    <div class="game-meta">
      <span>Game <?= (int)$game['slot'] ?><?= $game['location'] ? ' &middot; ' . h($game['location']) : '' ?></span>
      <span><?= $game['status'] === 'final' ? 'Final' : 'Scheduled' ?></span>
    </div>
    <div class="result-team <?= $winner === (int)$game['home_team_id'] ? 'win' : '' ?>">
      <span><?= h($game['home_name']) ?></span>
      <span class="score"><?= $game['status'] === 'final' ? (int)$game['home_score'] : '&ndash;' ?></span>
    </div>
    <div class="result-team <?= $winner === (int)$game['away_team_id'] ? 'win' : '' ?>">
      <span><?= h($game['away_name']) ?></span>
      <span class="score"><?= $game['status'] === 'final' ? (int)$game['away_score'] : '&ndash;' ?></span>
    </div>
    <?php if ($locked): ?>
      <div class="game-meta" style="margin:8px 0 0">
        <span>Your pick: <?= $picked ? h($picked === (int)$game['home_team_id'] ? $game['home_name'] : $game['away_name']) : 'none' ?></span>
        <?php if ($picked && $winner !== null): ?>
          <span class="tag <?= $picked === $winner ? 'tag-hit' : 'tag-miss' ?>"><?= $picked === $winner ? 'hit' : 'miss' ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; endif; ?>

<?php $byes = week_bye_teams((int)$week['id']); if ($byes): ?>
  <h2>Bye</h2>
  <div class="card"><?= h(implode(', ', array_column($byes, 'name'))) ?></div>
<?php endif; ?>
<?php layout_footer();
