<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_admin();

$season = season_active();
if (!$season) {
    flash('Create a season before adding teams.', 'error');
    redirect('/admin/seasons.php');
}

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id = post_int('team_id');
    // Any team_id in the form must belong to the active season - guards against a stale
    // page from a season that has since been switched away from.
    $team = $id ? db_one('SELECT * FROM teams WHERE id = ? AND season_id = ?', [$id, $season['id']]) : null;

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('Team name is required.', 'error');
        } elseif (db_one('SELECT id FROM teams WHERE season_id = ? AND name = ?', [$season['id'], $name])) {
            flash('That team already exists this season.', 'error');
        } else {
            db_run('INSERT INTO teams (season_id, name) VALUES (?, ?)', [$season['id'], $name]);
            flash('Team added.');
        }
    } elseif ($action === 'rename') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($team && $name !== '') {
            db_run('UPDATE teams SET name = ? WHERE id = ?', [$name, $team['id']]);
            flash('Team renamed.');
        }
    } elseif ($action === 'toggle') {
        if ($team) {
            db_run('UPDATE teams SET is_active = 1 - is_active WHERE id = ?', [$team['id']]);
            flash('Team updated.');
        }
    }
    redirect('/admin/teams.php');
}

$teams = teams_all(false, (int)$season['id']);
layout_header('Teams');
?>
<h1>Teams</h1>
<p class="sub">Season: <?= h($season['name']) ?></p>
<p class="sub"><?= count(array_filter($teams, fn($t) => (int)$t['is_active'] === 1)) ?> active teams. Inactive teams stay in past results but cannot be scheduled.</p>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add">
  <label class="field">New team name
    <input type="text" name="name" required>
  </label>
  <button type="submit">Add team</button>
</form>

<?php foreach ($teams as $team): ?>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>">
    <label class="field">Team <?= (int)$team['is_active'] === 1 ? '' : '(inactive)' ?>
      <input type="text" name="name" value="<?= h($team['name']) ?>" required>
    </label>
    <div class="row">
      <button class="btn-small" type="submit" name="action" value="rename">Save</button>
      <button class="btn-small btn-secondary" type="submit" name="action" value="toggle"><?= (int)$team['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button>
    </div>
  </form>
<?php endforeach; ?>
<p class="center"><a href="/admin/index.php">Back to admin</a></p>
<?php layout_footer();
