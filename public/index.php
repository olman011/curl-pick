<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

$user = require_login();

$weekId = get_int('week') ?? post_int('week_id');
$week = $weekId ? week_find($weekId) : week_current();
// Picks only ever apply to the active season - a link to an old week (or a stale
// bookmark from a season that's since ended) falls back to the current week instead.
$activeSeason = season_active();
if ($week && (!$activeSeason || (int)$week['season_id'] !== (int)$activeSeason['id'])) {
    $week = week_current();
}

if (is_post()) {
    csrf_check();
    if (!$week) {
        redirect('/index.php');
    }
    if (week_is_locked($week)) {
        flash('Picks for week ' . (int)$week['week_number'] . ' are locked.', 'error');
        redirect('/index.php?week=' . (int)$week['id']);
    }

    $games = week_games((int)$week['id']);
    $submitted = $_POST['pick'] ?? [];
    $saved = 0;
    foreach ($games as $game) {
        $choice = $submitted[(string)$game['id']] ?? null;
        if (!is_numeric($choice)) {
            continue;
        }
        $choice = (int)$choice;
        if ($choice !== (int)$game['home_team_id'] && $choice !== (int)$game['away_team_id']) {
            continue;
        }
        db_run(
            'INSERT INTO picks (user_id, game_id, picked_team_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE picked_team_id = VALUES(picked_team_id)',
            [$user['id'], $game['id'], $choice]
        );
        $saved++;
    }
    flash($saved . ' pick' . ($saved === 1 ? '' : 's') . ' saved.');
    redirect('/index.php?week=' . (int)$week['id']);
}

layout_header('My picks');

if (!$week) {
    echo '<h1>No schedule yet</h1><p class="sub">The admin has not published a week of games yet. Check back soon.</p>';
    layout_footer();
    exit;
}

$games = week_games((int)$week['id']);
$picks = user_picks_for_week((int)$user['id'], (int)$week['id']);
$byes = week_bye_teams((int)$week['id']);
$locked = week_is_locked($week);
$weeks = weeks_all(true);

// Win/loss record shown next to each team name, e.g. "(3:1)".
$records = [];
foreach (standings((int)$week['season_id']) as $row) {
    $records[(int)$row['id']] = (int)$row['wins'] . ':' . (int)$row['losses'];
}

// How everyone picked, shown flanking each matchup once picks are locked.
$pickCounts = $locked ? week_pick_counts((int)$week['id']) : [];
?>
<h1>Week <?= (int)$week['week_number'] ?> picks</h1>
<p class="sub"><?= h(fmt_date($week['game_date'])) ?></p>

<?php if (count($weeks) > 1): ?>
<div class="week-nav">
  <?php foreach ($weeks as $w): ?>
    <a href="/index.php?week=<?= (int)$w['id'] ?>" class="<?= (int)$w['id'] === (int)$week['id'] ? 'active' : '' ?>">Wk <?= (int)$w['week_number'] ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($locked): ?>
  <div class="banner banner-locked">Picks locked <?= h(fmt_datetime($week['lock_at'])) ?>.</div>
<?php else: ?>
  <div class="banner banner-open">Open until <?= h(fmt_datetime($week['lock_at'])) ?>. Tap a team to pick the winner; you can change picks until lock.</div>
<?php endif; ?>

<?php if (!$games): ?>
  <div class="card">No games scheduled for this week yet.</div>
<?php else: ?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="week_id" value="<?= (int)$week['id'] ?>">
  <?php foreach ($games as $game):
      $picked = $picks[(int)$game['id']] ?? null;
      $winner = game_winner_id($game);
  ?>
    <div class="game">
      <div class="game-meta">
        <span><?= $game['location'] ? h($game['location']) : '&nbsp;' ?></span>
        <?php if ($game['status'] === 'final'): ?>
          <span><?= (int)$game['home_score'] ?>&ndash;<?= (int)$game['away_score'] ?> final
            <?php if ($picked !== null && $winner !== null): ?>
              <span class="tag <?= $picked === $winner ? 'tag-hit' : 'tag-miss' ?>"><?= $picked === $winner ? 'hit' : 'miss' ?></span>
            <?php endif; ?>
          </span>
        <?php elseif ($picked === null): ?>
          <span class="tag tag-open">no pick</span>
        <?php endif; ?>
      </div>
      <div class="matchup">
        <?php
        $c = $locked ? ($pickCounts[(int)$game['id']] ?? ['home' => 0, 'away' => 0]) : ['home' => 0, 'away' => 0];
        $isUpset = $locked && beat_the_odds($winner, (int)$game['home_team_id'], (int)$game['away_team_id'], $c);
        ?>
        <label class="pick <?= $isUpset && $winner === (int)$game['home_team_id'] ? 'underdog-win' : '' ?>">
          <input type="radio" name="pick[<?= (int)$game['id'] ?>]" value="<?= (int)$game['home_team_id'] ?>"
                 <?= $picked === (int)$game['home_team_id'] ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
          <span>
            <?php if ($locked): ?><span class="pick-num pick-num-left"><?= $c['home'] ?></span><?php endif; ?>
            <?= h($game['home_name']) ?> <span class="team-record">(<?= h($records[(int)$game['home_team_id']] ?? '0:0') ?>)</span>
            <?php if ($isUpset && $winner === (int)$game['home_team_id']): ?><span class="tag tag-underdog" title="Won despite fewer picks">beat the odds</span><?php endif; ?>
          </span>
        </label>
        <div class="vs">vs</div>
        <label class="pick <?= $isUpset && $winner === (int)$game['away_team_id'] ? 'underdog-win' : '' ?>">
          <input type="radio" name="pick[<?= (int)$game['id'] ?>]" value="<?= (int)$game['away_team_id'] ?>"
                 <?= $picked === (int)$game['away_team_id'] ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
          <span>
            <?= h($game['away_name']) ?> <span class="team-record">(<?= h($records[(int)$game['away_team_id']] ?? '0:0') ?>)</span>
            <?php if ($isUpset && $winner === (int)$game['away_team_id']): ?><span class="tag tag-underdog" title="Won despite fewer picks">beat the odds</span><?php endif; ?>
            <?php if ($locked): ?><span class="pick-num pick-num-right"><?= $c['away'] ?></span><?php endif; ?>
          </span>
        </label>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$locked): ?>
    <div class="sticky-save"><button type="submit">Save picks (<?= count($picks) ?>/<?= count($games) ?>)</button></div>
  <?php endif; ?>
</form>
<?php endif; ?>

<?php if ($byes): ?>
  <h2>Bye this week</h2>
  <div class="card"><?= h(implode(', ', array_column($byes, 'name'))) ?></div>
<?php endif; ?>
<?php layout_footer();
