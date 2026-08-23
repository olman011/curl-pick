<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_admin();

$season = season_active();
if (!$season) {
    flash('Create a season before scheduling weeks.', 'error');
    redirect('/admin/seasons.php');
}

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $number = post_int('week_number');
        $date = trim((string)($_POST['game_date'] ?? ''));
        $lockTime = trim((string)($_POST['lock_time'] ?? (string)config('app.default_lock_time')));
        if (!$number || $date === '') {
            flash('Week number and date are required.', 'error');
        } elseif (db_one('SELECT id FROM weeks WHERE season_id = ? AND week_number = ?', [$season['id'], $number])) {
            flash('Week ' . $number . ' already exists this season.', 'error');
        } else {
            db_run(
                'INSERT INTO weeks (season_id, week_number, game_date, lock_at) VALUES (?, ?, ?, ?)',
                [$season['id'], $number, $date, $date . ' ' . $lockTime . ':00']
            );
            flash('Week ' . $number . ' created. Add the matchups next.');
            redirect('/admin/week.php?id=' . (int)db()->lastInsertId());
        }
    } elseif ($action === 'delete') {
        $id = post_int('week_id');
        // Only allow deleting a week that actually belongs to the active season.
        if ($id && db_one('SELECT id FROM weeks WHERE id = ? AND season_id = ?', [$id, $season['id']])) {
            db_run('DELETE FROM weeks WHERE id = ?', [$id]);
            flash('Week deleted.');
        }
    }
    redirect('/admin/schedule.php');
}

$weeks = weeks_all(false, (int)$season['id']);
$nextNumber = $weeks ? (int)max(array_column($weeks, 'week_number')) + 1 : 1;
layout_header('Schedule');
?>
<h1>Weeks &amp; schedule</h1>
<p class="sub">Season: <?= h($season['name']) ?></p>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add">
  <div class="row">
    <label class="field">Week #
      <input type="number" name="week_number" min="1" value="<?= $nextNumber ?>" required>
    </label>
    <label class="field">Game date
      <input type="date" name="game_date" required>
    </label>
    <label class="field">Picks lock at
      <input type="time" name="lock_time" value="<?= h((string)config('app.default_lock_time')) ?>" required>
    </label>
  </div>
  <button type="submit">Create week</button>
</form>

<?php foreach ($weeks as $week):
  $gameCount = (int)db_value('SELECT COUNT(*) FROM games WHERE week_id = ?', [$week['id']]);
  $finalCount = (int)db_value("SELECT COUNT(*) FROM games WHERE week_id = ? AND status = 'final'", [$week['id']]);
?>
  <div class="card">
    <strong>Week <?= (int)$week['week_number'] ?></strong> &middot; <?= h(fmt_date($week['game_date'])) ?><br>
    <span class="muted">Locks <?= h(fmt_datetime($week['lock_at'])) ?> &middot; <?= $gameCount ?> games &middot; <?= $finalCount ?> final<?= (int)$week['is_published'] === 1 ? '' : ' &middot; hidden' ?></span>
    <div class="row" style="margin-top:10px">
      <a class="btn btn-small btn-secondary" href="/admin/week.php?id=<?= (int)$week['id'] ?>">Edit games &amp; scores</a>
      <form method="post" onsubmit="return confirm('Delete week <?= (int)$week['week_number'] ?> and all its games and picks?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="week_id" value="<?= (int)$week['id'] ?>">
        <button class="btn-small btn-danger" type="submit">Delete</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<p class="center"><a href="/admin/index.php">Back to admin</a></p>
<?php layout_footer();
