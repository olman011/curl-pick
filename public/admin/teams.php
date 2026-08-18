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
            flash('Team name is required.', 'error');
        } elseif (db_one('SELECT id FROM teams WHERE name = ?', [$name])) {
            flash('That team already exists.', 'error');
        } else {
            db_run('INSERT INTO teams (name) VALUES (?)', [$name]);
            flash('Team added.');
        }
    } elseif ($action === 'rename') {
        $id = post_int('team_id');
        $name = trim((string)($_POST['name'] ?? ''));
        if ($id && $name !== '') {
            db_run('UPDATE teams SET name = ? WHERE id = ?', [$name, $id]);
            flash('Team renamed.');
        }
    } elseif ($action === 'toggle') {
        $id = post_int('team_id');
        if ($id) {
            db_run('UPDATE teams SET is_active = 1 - is_active WHERE id = ?', [$id]);
            flash('Team updated.');
        }
    }
    redirect('/admin/teams.php');
}

$teams = teams_all();
layout_header('Teams');
?>
<h1>Teams</h1>
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
