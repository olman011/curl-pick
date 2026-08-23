<?php
declare(strict_types=1);

// Loads 18 sample teams, three sample weeks and a few members so you can click around.
// Safe to run on an empty database only.
require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

if ((int)db_value('SELECT COUNT(*) FROM teams') > 0) {
    exit("Teams already exist - refusing to seed.\n");
}

db_run('INSERT INTO seasons (name, is_active) VALUES (?, 1)', ['Demo Season']);
$seasonId = (int)db()->lastInsertId();

$teamNames = [
    'Anderson', 'Bergman', 'Carlson', 'Dahl', 'Erickson', 'Fredrickson',
    'Gustafson', 'Halvorson', 'Iverson', 'Johnson', 'Knutson', 'Larson',
    'Monson', 'Nelson', 'Olson', 'Peterson', 'Quarnstrom', 'Rasmussen',
];
foreach ($teamNames as $name) {
    db_run('INSERT INTO teams (season_id, name) VALUES (?, ?)', [$seasonId, $name]);
}
$teams = db_all('SELECT id FROM teams WHERE season_id = ? ORDER BY id', [$seasonId]);
$teamIds = array_map(static fn($t) => (int)$t['id'], $teams);

$lockTime = (string)config('app.default_lock_time');
for ($week = 1; $week <= 3; $week++) {
    $date = (new DateTimeImmutable('tuesday this week'))->modify('+' . ($week - 3) . ' week')->format('Y-m-d');
    db_run('INSERT INTO weeks (season_id, week_number, game_date, lock_at) VALUES (?, ?, ?, ?)', [$seasonId, $week, $date, "$date $lockTime:00"]);
    $weekId = (int)db()->lastInsertId();

    $rotated = $teamIds;
    for ($i = 0; $i < $week - 1; $i++) {
        array_push($rotated, array_shift($rotated));
    }
    $playing = array_slice($rotated, 0, 16); // last two teams get the bye
    for ($slot = 1; $slot <= 8; $slot++) {
        $home = $playing[($slot - 1) * 2];
        $away = $playing[($slot - 1) * 2 + 1];
        $final = $week < 3;
        db_run(
            'INSERT INTO games (week_id, slot, location, home_team_id, away_team_id, home_score, away_score, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $weekId, $slot, 'Sheet ' . $slot, $home, $away,
                $final ? random_int(3, 10) : null,
                $final ? random_int(3, 10) : null,
                $final ? 'final' : 'scheduled',
            ]
        );
    }
}

$members = [['Ole Olmanson', 'ole@example.com', true], ['Dana Curler', 'dana@example.com', false], ['Sam Sweep', 'sam@example.com', false]];
foreach ($members as [$name, $email, $isAdmin]) {
    $userId = create_user($name, $email, 'curling123', $isAdmin);
    foreach (db_all('SELECT g.id, g.home_team_id, g.away_team_id FROM games g') as $game) {
        $choice = random_int(0, 1) ? (int)$game['home_team_id'] : (int)$game['away_team_id'];
        db_run('INSERT INTO picks (user_id, game_id, picked_team_id) VALUES (?, ?, ?)', [$userId, $game['id'], $choice]);
    }
}

echo "Seeded a demo season with 18 teams, 3 weeks and 3 members (password: curling123).\n";
