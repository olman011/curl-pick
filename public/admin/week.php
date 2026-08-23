<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_admin();

$weekId = get_int('id') ?? post_int('week_id');
$week = $weekId ? week_find($weekId) : null;
if (!$week) {
    http_response_code(404);
    exit('Week not found.');
}

$season = season_active();
if (!$season || (int)$week['season_id'] !== (int)$season['id']) {
    flash('That week belongs to a past season and can no longer be edited. Past weeks are viewable read-only in Results.', 'error');
    redirect('/admin/schedule.php');
}

$slots = (int)config('app.games_per_week');
$teams = teams_all(true);

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'week') {
        $date = trim((string)($_POST['game_date'] ?? $week['game_date']));
        $lockTime = trim((string)($_POST['lock_time'] ?? '20:45'));
        db_run(
            'UPDATE weeks SET game_date = ?, lock_at = ?, is_published = ?, notes = ? WHERE id = ?',
            [$date, $date . ' ' . $lockTime . ':00', isset($_POST['is_published']) ? 1 : 0, trim((string)($_POST['notes'] ?? '')) ?: null, $week['id']]
        );
        flash('Week settings saved.');
        redirect('/admin/week.php?id=' . (int)$week['id']);
    }

    if ($action === 'games') {
        $used = [];
        $errors = [];
        $rows = [];
        for ($slot = 1; $slot <= $slots; $slot++) {
            $home = post_int("home_$slot");
            $away = post_int("away_$slot");
            if (!$home && !$away) {
                $rows[$slot] = null;
                continue;
            }
            if (!$home || !$away) {
                $errors[] = "Game $slot needs both teams.";
                continue;
            }
            if ($home === $away) {
                $errors[] = "Game $slot has the same team twice.";
                continue;
            }
            foreach ([$home, $away] as $teamId) {
                if (isset($used[$teamId])) {
                    $errors[] = "A team is scheduled twice (game $slot).";
                }
                $used[$teamId] = true;
            }
            $rows[$slot] = ['home' => $home, 'away' => $away, 'location' => trim((string)($_POST["location_$slot"] ?? ''))];
        }

        if ($errors) {
            flash(implode(' ', array_unique($errors)), 'error');
            redirect('/admin/week.php?id=' . (int)$week['id']);
        }

        foreach ($rows as $slot => $row) {
            $existing = db_one('SELECT * FROM games WHERE week_id = ? AND slot = ?', [$week['id'], $slot]);
            if ($row === null) {
                if ($existing) {
                    db_run('DELETE FROM games WHERE id = ?', [$existing['id']]);
                }
                continue;
            }
            if ($existing) {
                $teamsChanged = (int)$existing['home_team_id'] !== $row['home'] || (int)$existing['away_team_id'] !== $row['away'];
                db_run(
                    'UPDATE games SET home_team_id = ?, away_team_id = ?, location = ? WHERE id = ?',
                    [$row['home'], $row['away'], $row['location'] ?: null, $existing['id']]
                );
                if ($teamsChanged) {
                    // Old picks point at teams that are no longer in this game.
                    db_run('DELETE FROM picks WHERE game_id = ?', [$existing['id']]);
                }
            } else {
                db_run(
                    'INSERT INTO games (week_id, slot, home_team_id, away_team_id, location) VALUES (?, ?, ?, ?, ?)',
                    [$week['id'], $slot, $row['home'], $row['away'], $row['location'] ?: null]
                );
            }
        }
        flash('Matchups saved.');
        redirect('/admin/week.php?id=' . (int)$week['id']);
    }

    if ($action === 'scores') {
        foreach (week_games((int)$week['id']) as $game) {
            $home = $_POST["hs_{$game['id']}"] ?? '';
            $away = $_POST["as_{$game['id']}"] ?? '';
            if ($home === '' || $away === '') {
                db_run("UPDATE games SET home_score = NULL, away_score = NULL, status = 'scheduled' WHERE id = ?", [$game['id']]);
                continue;
            }
            db_run(
                "UPDATE games SET home_score = ?, away_score = ?, status = 'final' WHERE id = ?",
                [(int)$home, (int)$away, $game['id']]
            );
        }
        flash('Scores saved.');
        redirect('/admin/week.php?id=' . (int)$week['id']);
    }
}

$games = week_games((int)$week['id']);
$bySlot = [];
foreach ($games as $game) {
    $bySlot[(int)$game['slot']] = $game;
}
$byes = week_bye_teams((int)$week['id']);

layout_header('Week ' . (int)$week['week_number']);
?>
<h1>Week <?= (int)$week['week_number'] ?></h1>
<p class="sub"><?= h(fmt_date($week['game_date'])) ?> &middot; locks <?= h(fmt_datetime($week['lock_at'])) ?></p>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="week_id" value="<?= (int)$week['id'] ?>">
  <input type="hidden" name="action" value="week">
  <div class="row">
    <label class="field">Game date
      <input type="date" name="game_date" value="<?= h($week['game_date']) ?>" required>
    </label>
    <label class="field">Picks lock at
      <input type="time" name="lock_time" value="<?= h(substr((string)$week['lock_at'], 11, 5)) ?>" required>
    </label>
  </div>
  <label class="field">Notes (shown nowhere yet, for your reference)
    <input type="text" name="notes" value="<?= h((string)$week['notes']) ?>">
  </label>
  <label class="field"><input type="checkbox" name="is_published" <?= (int)$week['is_published'] === 1 ? 'checked' : '' ?>> Visible to members</label>
  <button type="submit">Save week settings</button>
</form>

<h2>Matchups</h2>
<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="week_id" value="<?= (int)$week['id'] ?>">
  <input type="hidden" name="action" value="games">
  <?php for ($slot = 1; $slot <= $slots; $slot++):
      $game = $bySlot[$slot] ?? null; ?>
    <fieldset style="border:0;padding:0;margin:0 0 14px">
      <strong>Game <?= $slot ?></strong>
      <div class="row">
        <label class="field">Home
          <select name="home_<?= $slot ?>">
            <option value="">&mdash;</option>
            <?php foreach ($teams as $team): ?>
              <option value="<?= (int)$team['id'] ?>" <?= $game && (int)$game['home_team_id'] === (int)$team['id'] ? 'selected' : '' ?>><?= h($team['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Away
          <select name="away_<?= $slot ?>">
            <option value="">&mdash;</option>
            <?php foreach ($teams as $team): ?>
              <option value="<?= (int)$team['id'] ?>" <?= $game && (int)$game['away_team_id'] === (int)$team['id'] ? 'selected' : '' ?>><?= h($team['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">Sheet / location
          <input type="text" name="location_<?= $slot ?>" value="<?= h((string)($game['location'] ?? '')) ?>">
        </label>
      </div>
    </fieldset>
  <?php endfor; ?>
  <button type="submit">Save matchups</button>
</form>

<?php if ($byes): ?>
  <div class="card">Bye this week: <?= h(implode(', ', array_column($byes, 'name'))) ?></div>
<?php endif; ?>

<?php if ($games): ?>
<h2>Scores</h2>
<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="week_id" value="<?= (int)$week['id'] ?>">
  <input type="hidden" name="action" value="scores">
  <p class="muted">Leave both boxes blank to mark a game as not played yet. Equal scores count as a tie and score no pick points.</p>
  <?php foreach ($games as $game): ?>
    <div class="row" style="align-items:flex-end">
      <label class="field"><?= h($game['home_name']) ?>
        <input type="number" name="hs_<?= (int)$game['id'] ?>" inputmode="numeric" value="<?= $game['home_score'] === null ? '' : (int)$game['home_score'] ?>">
      </label>
      <label class="field"><?= h($game['away_name']) ?>
        <input type="number" name="as_<?= (int)$game['id'] ?>" inputmode="numeric" value="<?= $game['away_score'] === null ? '' : (int)$game['away_score'] ?>">
      </label>
    </div>
  <?php endforeach; ?>
  <button type="submit">Save scores</button>
</form>
<?php endif; ?>
<p class="center"><a href="/admin/schedule.php">Back to weeks</a></p>
<?php layout_footer();
