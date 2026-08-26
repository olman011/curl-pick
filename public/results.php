<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$user = require_login();

$season = season_resolve(get_int('season'));
$seasons = seasons_all();

layout_header('Results');

if (!$season) {
    echo '<h1>Results</h1><p class="sub">No season has been created yet.</p>';
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
<h1>Results</h1>
<p class="sub">Season: <?= h($season['name']) ?><?= $isArchive ? ' (archive)' : '' ?></p>

<?php if (count($seasons) > 1): ?>
<div class="week-nav">
  <?php foreach ($seasons as $s): ?>
    <a href="/results.php?season=<?= (int)$s['id'] ?>" class="<?= (int)$s['id'] === (int)$season['id'] ? 'active' : '' ?>"><?= h($s['name']) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$week): ?>
  <p class="sub">No weeks published yet.</p>
  <?php layout_footer(); exit; ?>
<?php endif; ?>
<p class="sub">Week <?= (int)$week['week_number'] ?> &middot; <?= h(fmt_date($week['game_date'])) ?></p>

<?php if (count($weeks) > 1): ?>
<div class="week-nav">
  <?php foreach ($weeks as $w): ?>
    <a href="/results.php?season=<?= (int)$season['id'] ?>&amp;week=<?= (int)$w['id'] ?>" class="<?= (int)$w['id'] === (int)$week['id'] ? 'active' : '' ?>">Wk <?= (int)$w['week_number'] ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$games = week_results_summary((int)$week['id']);
$picks = user_picks_for_week((int)$user['id'], (int)$week['id']);
$locked = week_is_locked($week);
$pickCounts = $locked ? week_pick_counts((int)$week['id']) : [];
if (!$games): ?>
  <div class="card">No games scheduled.</div>
<?php else: foreach ($games as $game):
    $winner = $game['winner_id'];
    $picked = $picks[(int)$game['id']] ?? null;
    $counts = $pickCounts[(int)$game['id']] ?? ['home' => 0, 'away' => 0];
    $isUpset = $locked && beat_the_odds($winner, (int)$game['home_team_id'], (int)$game['away_team_id'], $counts);
?>
  <div class="game">
    <div class="game-meta">
      <span><?= $game['location'] ? h($game['location']) : '&nbsp;' ?></span>
      <span><?= $game['status'] === 'final' ? 'Final' : 'Scheduled' ?></span>
    </div>
    <div class="result-team <?= $winner === (int)$game['home_team_id'] ? 'win' : '' ?> <?= $isUpset && $winner === (int)$game['home_team_id'] ? 'underdog' : '' ?>">
      <span><?= h($game['home_name']) ?><?= $isUpset && $winner === (int)$game['home_team_id'] ? ' <span class="tag tag-underdog" title="Won despite fewer picks">beat the odds</span>' : '' ?></span>
      <span class="score"><?= $game['status'] === 'final' ? (int)$game['home_score'] : '&ndash;' ?></span>
    </div>
    <div class="result-team <?= $winner === (int)$game['away_team_id'] ? 'win' : '' ?> <?= $isUpset && $winner === (int)$game['away_team_id'] ? 'underdog' : '' ?>">
      <span><?= h($game['away_name']) ?><?= $isUpset && $winner === (int)$game['away_team_id'] ? ' <span class="tag tag-underdog" title="Won despite fewer picks">beat the odds</span>' : '' ?></span>
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
