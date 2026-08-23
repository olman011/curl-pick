<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_admin();

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('Season name is required.', 'error');
        } elseif (db_one('SELECT id FROM seasons WHERE name = ?', [$name])) {
            flash('A season named "' . $name . '" already exists.', 'error');
        } else {
            db_run('INSERT INTO seasons (name) VALUES (?)', [$name]);
            $newId = (int)db()->lastInsertId();
            // First season ever created becomes active automatically.
            if (!season_active()) {
                season_activate($newId);
            }
            flash('Season created.');
        }
    } elseif ($action === 'activate') {
        $id = post_int('season_id');
        if ($id && season_find($id)) {
            season_activate($id);
            flash('Active season switched. Picks, standings, and the leaderboard now use this season.');
        }
    } elseif ($action === 'rename') {
        $id = post_int('season_id');
        $name = trim((string)($_POST['name'] ?? ''));
        if ($id && $name !== '') {
            db_run('UPDATE seasons SET name = ? WHERE id = ?', [$name, $id]);
            flash('Season renamed.');
        }
    } elseif ($action === 'delete') {
        $id = post_int('season_id');
        $season = $id ? season_find($id) : null;
        if ($season && (int)$season['is_active'] === 1) {
            flash('Cannot delete the active season. Activate a different one first.', 'error');
        } elseif ($season) {
            db_run('DELETE FROM seasons WHERE id = ?', [$id]);
            flash('Season and all its teams, weeks, games, and picks were deleted.');
        }
    }
    redirect('/admin/seasons.php');
}

$seasons = seasons_all();
layout_header('Seasons');
?>
<h1>Seasons</h1>
<p class="sub">Picks, live standings, and the leaderboard always use the active season. Past seasons stay visible as a read-only archive.</p>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add">
  <label class="field">New season name
    <input type="text" name="name" placeholder="e.g. Winter 2027" required>
  </label>
  <button type="submit">Create season</button>
</form>

<?php foreach ($seasons as $season):
  $teamCount = (int)db_value('SELECT COUNT(*) FROM teams WHERE season_id = ?', [$season['id']]);
  $weekCount = (int)db_value('SELECT COUNT(*) FROM weeks WHERE season_id = ?', [$season['id']]);
  $isActive = (int)$season['is_active'] === 1;
?>
  <div class="card">
    <strong><?= h($season['name']) ?></strong><?= $isActive ? ' &middot; <span class="tag tag-open">active</span>' : '' ?>
    <div class="muted"><?= $teamCount ?> teams &middot; <?= $weekCount ?> weeks</div>
    <div class="row" style="margin-top:10px">
      <?php if (!$isActive): ?>
        <form method="post" onsubmit="return confirm('Make &quot;<?= h($season['name']) ?>&quot; the active season? Picks and live stats will switch to it immediately.')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="activate">
          <input type="hidden" name="season_id" value="<?= (int)$season['id'] ?>">
          <button class="btn-small" type="submit">Make active</button>
        </form>
        <a class="btn btn-small btn-secondary" href="/standings.php?season=<?= (int)$season['id'] ?>">View archive</a>
        <form method="post" onsubmit="return confirm('Delete &quot;<?= h($season['name']) ?>&quot; and ALL its teams, weeks, games, and picks? This cannot be undone.')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="season_id" value="<?= (int)$season['id'] ?>">
          <button class="btn-small btn-danger" type="submit">Delete</button>
        </form>
      <?php else: ?>
        <a class="btn btn-small btn-secondary" href="/admin/teams.php">Manage teams</a>
        <a class="btn btn-small btn-secondary" href="/admin/schedule.php">Manage schedule</a>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<p class="center"><a href="/admin/index.php">Back to admin</a></p>
<?php layout_footer();
