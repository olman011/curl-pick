<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_admin();

$season = season_active();
if (!$season) {
    flash('Create a season before importing a schedule.', 'error');
    redirect('/admin/seasons.php');
}

$errors = [];
$preview = null;

if (is_post()) {
    csrf_check();

    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        flash('Please choose a CSV file to upload.', 'error');
        redirect('/admin/import.php');
    }

    $tmpPath = $_FILES['csv']['tmp_name'];
    $handle = fopen($tmpPath, 'r');
    if ($handle === false) {
        flash('Could not read the uploaded file.', 'error');
        redirect('/admin/import.php');
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        flash('The CSV file appears to be empty.', 'error');
        redirect('/admin/import.php');
    }

    $header = array_map(static fn($col) => strtolower(trim((string)$col)), $header);
    $required = ['week_number', 'game_date', 'home_team', 'away_team'];
    $missing = array_diff($required, $header);
    if ($missing) {
        fclose($handle);
        flash('CSV is missing required column(s): ' . implode(', ', $missing) . '.', 'error');
        redirect('/admin/import.php');
    }
    $colIndex = array_flip($header);

    $defaultLockTime = (string)config('app.default_lock_time');

    // First pass: read and validate every row before touching the database.
    $rows = [];
    $lineNumber = 1;
    while (($cells = fgetcsv($handle)) !== false) {
        $lineNumber++;
        if (count(array_filter($cells, static fn($c) => trim((string)$c) !== '')) === 0) {
            continue; // skip blank lines
        }

        $get = static function (string $key) use ($cells, $colIndex): string {
            return isset($colIndex[$key], $cells[$colIndex[$key]]) ? trim((string)$cells[$colIndex[$key]]) : '';
        };

        $weekNumber = $get('week_number');
        $gameDate = $get('game_date');
        $homeTeam = $get('home_team');
        $awayTeam = $get('away_team');
        $lockTime = $get('lock_time') ?: $defaultLockTime;
        $location = $get('location');

        if ($weekNumber === '' || !ctype_digit($weekNumber)) {
            $errors[] = "Line $lineNumber: week_number must be a whole number.";
            continue;
        }
        if ($gameDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gameDate) || strtotime($gameDate) === false) {
            $errors[] = "Line $lineNumber: game_date must be in YYYY-MM-DD format.";
            continue;
        }
        if (!preg_match('/^\d{1,2}:\d{2}$/', $lockTime)) {
            $errors[] = "Line $lineNumber: lock_time must be in HH:MM format (24-hour).";
            continue;
        }
        if ($homeTeam === '' || $awayTeam === '') {
            $errors[] = "Line $lineNumber: home_team and away_team are both required.";
            continue;
        }
        if (strcasecmp($homeTeam, $awayTeam) === 0) {
            $errors[] = "Line $lineNumber: a team can't play itself ($homeTeam).";
            continue;
        }

        $rows[] = [
            'line' => $lineNumber,
            'week_number' => (int)$weekNumber,
            'game_date' => $gameDate,
            'lock_time' => $lockTime,
            'home_team' => $homeTeam,
            'away_team' => $awayTeam,
            'location' => $location,
        ];
    }
    fclose($handle);

    if (!$rows && !$errors) {
        $errors[] = 'No data rows found in the file.';
    }

    // Check for a team scheduled twice in the same week within the file itself.
    $byWeek = [];
    foreach ($rows as $row) {
        $byWeek[$row['week_number']][] = $row;
    }
    foreach ($byWeek as $weekNumber => $weekRows) {
        $used = [];
        foreach ($weekRows as $row) {
            foreach ([$row['home_team'], $row['away_team']] as $teamName) {
                $key = strtolower($teamName);
                if (isset($used[$key])) {
                    $errors[] = "Week $weekNumber: \"$teamName\" is scheduled more than once (line {$row['line']}).";
                }
                $used[$key] = true;
            }
        }
    }

    if ($errors) {
        flash('Import failed:<br>' . implode('<br>', array_map('h', $errors)), 'error');
        redirect('/admin/import.php');
    }

    // Everything validated — import inside a transaction so a failure partway through
    // doesn't leave a half-imported season behind.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $teamsCreated = 0;
        $weeksCreated = 0;
        $gamesCreated = 0;
        $slotCounters = [];

        $findTeamId = function (string $name) use (&$teamsCreated, $season): int {
            $team = db_one('SELECT id FROM teams WHERE season_id = ? AND name = ?', [$season['id'], $name]);
            if ($team) {
                return (int)$team['id'];
            }
            db_run('INSERT INTO teams (season_id, name) VALUES (?, ?)', [$season['id'], $name]);
            $teamsCreated++;
            return (int)db()->lastInsertId();
        };

        foreach ($rows as $row) {
            $week = db_one('SELECT * FROM weeks WHERE season_id = ? AND week_number = ?', [$season['id'], $row['week_number']]);
            if (!$week) {
                db_run(
                    'INSERT INTO weeks (season_id, week_number, game_date, lock_at) VALUES (?, ?, ?, ?)',
                    [$season['id'], $row['week_number'], $row['game_date'], $row['game_date'] . ' ' . $row['lock_time'] . ':00']
                );
                $weekId = (int)db()->lastInsertId();
                $weeksCreated++;
            } else {
                $weekId = (int)$week['id'];
            }

            $homeId = $findTeamId($row['home_team']);
            $awayId = $findTeamId($row['away_team']);

            $slotCounters[$weekId] = ($slotCounters[$weekId] ?? (int)db_value(
                'SELECT COALESCE(MAX(slot), 0) FROM games WHERE week_id = ?',
                [$weekId]
            )) + 1;
            $slot = $slotCounters[$weekId];

            db_run(
                'INSERT INTO games (week_id, slot, home_team_id, away_team_id, location) VALUES (?, ?, ?, ?, ?)',
                [$weekId, $slot, $homeId, $awayId, $row['location'] ?: null]
            );
            $gamesCreated++;
        }

        $pdo->commit();
        flash("Season imported: $weeksCreated week(s) created, $gamesCreated game(s) added, $teamsCreated new team(s).");
        redirect('/admin/schedule.php');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('Import failed and no changes were saved: ' . h($e->getMessage()), 'error');
        redirect('/admin/import.php');
    }
}

layout_header('Import season');
?>
<h1>Import season (CSV)</h1>
<p class="sub">Season: <?= h($season['name']) ?></p>
<p class="sub">Bulk-create weeks and matchups from a spreadsheet instead of entering them one week at a time. Existing weeks (matched by week number) get new games appended; teams that don't exist yet in this season are created automatically.</p>

<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <label class="field">CSV file
    <input type="file" name="csv" accept=".csv,text/csv" required>
  </label>
  <button type="submit">Import</button>
</form>

<div class="card">
  <h2>CSV format</h2>
  <p>One row per game. Columns, in any order (header row required):</p>
  <ul>
    <li><strong>week_number</strong> &mdash; required, whole number</li>
    <li><strong>game_date</strong> &mdash; required, YYYY-MM-DD</li>
    <li><strong>home_team</strong> &mdash; required, matched by exact name (created if new)</li>
    <li><strong>away_team</strong> &mdash; required, matched by exact name (created if new)</li>
    <li><strong>lock_time</strong> &mdash; optional, HH:MM (24-hour), defaults to <?= h((string)config('app.default_lock_time')) ?></li>
    <li><strong>location</strong> &mdash; optional, e.g. sheet name</li>
  </ul>
  <p>Example:</p>
  <pre style="white-space:pre-wrap;background:#f5f5f5;padding:10px;border-radius:6px">week_number,game_date,lock_time,home_team,away_team,location
1,2026-01-08,20:45,Rock Solid,Sheet Happens,Sheet A
1,2026-01-08,20:45,Curl Jam,Hurry Hard,Sheet B
2,2026-01-15,20:45,Rock Solid,Curl Jam,Sheet A
2,2026-01-15,20:45,Sheet Happens,Hurry Hard,Sheet B</pre>
  <p class="muted">The whole file is imported as one transaction &mdash; if any row fails validation, nothing is saved.</p>
</div>

<p class="center"><a href="/admin/schedule.php">Back to weeks</a></p>
<?php layout_footer();
