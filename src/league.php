<?php
declare(strict_types=1);

function seasons_all(): array
{
    return db_all('SELECT * FROM seasons ORDER BY created_at DESC, id DESC');
}

function season_find(int $id): ?array
{
    return db_one('SELECT * FROM seasons WHERE id = ?', [$id]);
}

function season_active(): ?array
{
    return db_one('SELECT * FROM seasons WHERE is_active = 1 LIMIT 1');
}

/** Resolves a season for display: the given id if valid, else the active season. */
function season_resolve(?int $seasonId): ?array
{
    if ($seasonId) {
        return season_find($seasonId);
    }
    return season_active();
}

/** Makes the given season the only active one. Everything else is deactivated. */
function season_activate(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_run('UPDATE seasons SET is_active = 0');
        db_run('UPDATE seasons SET is_active = 1 WHERE id = ?', [$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Most recent week in a season, for archive views that have no "current" concept. */
function season_latest_week(int $seasonId): ?array
{
    return db_one('SELECT * FROM weeks WHERE season_id = ? ORDER BY week_number DESC LIMIT 1', [$seasonId]);
}

function teams_all(bool $activeOnly = false, ?int $seasonId = null): array
{
    $seasonId ??= (int)(season_active()['id'] ?? 0);
    $sql = 'SELECT * FROM teams WHERE season_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    return db_all($sql . ' ORDER BY name', [$seasonId]);
}

function weeks_all(bool $publishedOnly = false, ?int $seasonId = null): array
{
    $seasonId ??= (int)(season_active()['id'] ?? 0);
    $sql = 'SELECT * FROM weeks WHERE season_id = ?';
    if ($publishedOnly) {
        $sql .= ' AND is_published = 1';
    }
    return db_all($sql . ' ORDER BY week_number', [$seasonId]);
}

function week_find(int $id): ?array
{
    return db_one('SELECT * FROM weeks WHERE id = ?', [$id]);
}

/** The week members should be looking at: the next one still open, else the most recent. Active season only. */
function week_current(): ?array
{
    $season = season_active();
    if (!$season) {
        return null;
    }
    $week = db_one(
        'SELECT * FROM weeks WHERE season_id = ? AND is_published = 1 AND lock_at > NOW() ORDER BY lock_at LIMIT 1',
        [$season['id']]
    );
    if ($week) {
        return $week;
    }
    return db_one(
        'SELECT * FROM weeks WHERE season_id = ? AND is_published = 1 ORDER BY lock_at DESC LIMIT 1',
        [$season['id']]
    );
}

function week_is_locked(array $week): bool
{
    return strtotime($week['lock_at']) <= time();
}

function week_games(int $weekId): array
{
    return db_all(
        'SELECT g.*, ht.name AS home_name, at.name AS away_name
         FROM games g
         JOIN teams ht ON ht.id = g.home_team_id
         JOIN teams at ON at.id = g.away_team_id
         WHERE g.week_id = ?
         ORDER BY g.slot',
        [$weekId]
    );
}

/** @return array<int,array{home:int,away:int}> game_id => pick counts for each side */
function week_pick_counts(int $weekId): array
{
    $rows = db_all(
        'SELECT g.id AS game_id,
                SUM(p.picked_team_id = g.home_team_id) AS home_count,
                SUM(p.picked_team_id = g.away_team_id) AS away_count
         FROM games g
         LEFT JOIN picks p ON p.game_id = g.id
         WHERE g.week_id = ?
         GROUP BY g.id',
        [$weekId]
    );
    $counts = [];
    foreach ($rows as $row) {
        $counts[(int)$row['game_id']] = ['home' => (int)$row['home_count'], 'away' => (int)$row['away_count']];
    }
    return $counts;
}

function week_bye_teams(int $weekId): array
{
    $week = week_find($weekId);
    if (!$week) {
        return [];
    }
    return db_all(
        'SELECT t.* FROM teams t
         WHERE t.season_id = :season
           AND t.is_active = 1
           AND t.id NOT IN (
             SELECT home_team_id FROM games WHERE week_id = :w
             UNION SELECT away_team_id FROM games WHERE week_id = :w2
           )
         ORDER BY t.name',
        ['season' => $week['season_id'], 'w' => $weekId, 'w2' => $weekId]
    );
}

/** @return array<int,int> game_id => picked_team_id */
function user_picks_for_week(int $userId, int $weekId): array
{
    $rows = db_all(
        'SELECT p.game_id, p.picked_team_id
         FROM picks p JOIN games g ON g.id = p.game_id
         WHERE p.user_id = ? AND g.week_id = ?',
        [$userId, $weekId]
    );
    $picks = [];
    foreach ($rows as $row) {
        $picks[(int)$row['game_id']] = (int)$row['picked_team_id'];
    }
    return $picks;
}

function game_winner_id(array $game): ?int
{
    if ($game['status'] !== 'final' || $game['home_score'] === null || $game['away_score'] === null) {
        return null;
    }
    if ((int)$game['home_score'] === (int)$game['away_score']) {
        return null; // tie: nobody scores a point
    }
    return (int)$game['home_score'] > (int)$game['away_score']
        ? (int)$game['home_team_id']
        : (int)$game['away_team_id'];
}

function weekly_leaderboard(int $weekId): array
{
    return db_all(
        "SELECT u.id, u.name,
                SUM(CASE WHEN g.status = 'final'
                         AND g.home_score <> g.away_score
                         AND p.picked_team_id = IF(g.home_score > g.away_score, g.home_team_id, g.away_team_id)
                    THEN 1 ELSE 0 END) AS correct,
                COUNT(p.id) AS picks_made
         FROM users u
         JOIN picks p ON p.user_id = u.id
         JOIN games g ON g.id = p.game_id AND g.week_id = ?
         WHERE u.is_active = 1
         GROUP BY u.id, u.name
         ORDER BY correct DESC, u.name",
        [$weekId]
    );
}

/** Count of weeks in a season that have at least one final game. */
function season_graded_week_count(int $seasonId): int
{
    return (int)db_value(
        'SELECT COUNT(DISTINCT w.id) FROM weeks w JOIN games g ON g.week_id = w.id
         WHERE w.season_id = ? AND g.status = "final"',
        [$seasonId]
    );
}

/**
 * Season-wide leaderboard, scoped to one season (defaults to the active one).
 * Each user's lowest-scoring weeks are dropped per the season's drop_weeks setting
 * (a week with no picks submitted counts as 0 correct, so it's eligible to be
 * dropped same as a bad week - the season's admin can adjust the drop count).
 */
function season_leaderboard(?int $seasonId = null): array
{
    $season = $seasonId ? season_find($seasonId) : season_active();
    if (!$season) {
        return [];
    }
    $seasonId = (int)$season['id'];
    $dropWeeks = (int)$season['drop_weeks'];

    $gradedWeekIds = array_column(
        db_all(
            'SELECT DISTINCT w.id FROM weeks w JOIN games g ON g.week_id = w.id
             WHERE w.season_id = ? AND g.status = "final"',
            [$seasonId]
        ),
        'id'
    );

    $users = db_all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name');
    if (!$gradedWeekIds) {
        // No graded weeks yet - everyone starts at zero, nothing to drop.
        return array_map(static fn($u) => ['id' => (int)$u['id'], 'name' => $u['name'], 'correct' => 0, 'weeks_played' => 0], $users);
    }

    $placeholders = implode(',', array_fill(0, count($gradedWeekIds), '?'));
    $rows = db_all(
        "SELECT u.id AS user_id, w.id AS week_id,
                SUM(CASE WHEN p.picked_team_id = IF(g.home_score > g.away_score, g.home_team_id, g.away_team_id)
                    THEN 1 ELSE 0 END) AS correct
         FROM users u
         CROSS JOIN weeks w
         JOIN games g ON g.week_id = w.id AND g.status = 'final' AND g.home_score <> g.away_score
         LEFT JOIN picks p ON p.game_id = g.id AND p.user_id = u.id
         WHERE u.is_active = 1 AND w.id IN ($placeholders)
         GROUP BY u.id, w.id",
        $gradedWeekIds
    );

    // Per user, the list of correct-pick counts for every graded week (0 for a week
    // they didn't play, since that's just as droppable as a bad week).
    $byUser = [];
    foreach ($users as $u) {
        $byUser[(int)$u['id']] = array_fill_keys($gradedWeekIds, 0);
    }
    foreach ($rows as $row) {
        $byUser[(int)$row['user_id']][(int)$row['week_id']] = (int)$row['correct'];
    }

    $totalWeeks = count($gradedWeekIds);
    // Always count at least one week, even if the admin sets drop_weeks too high.
    $effectiveDrop = min($dropWeeks, max(0, $totalWeeks - 1));

    $result = [];
    foreach ($users as $u) {
        $weekScores = array_values($byUser[(int)$u['id']]);
        sort($weekScores);
        $counted = array_slice($weekScores, $effectiveDrop);
        $result[] = [
            'id' => (int)$u['id'],
            'name' => $u['name'],
            'correct' => array_sum($counted),
            'weeks_played' => count($counted),
        ];
    }

    usort($result, static fn($a, $b) => $b['correct'] <=> $a['correct'] ?: strcmp($a['name'], $b['name']));
    return $result;
}

/**
 * Trailing win streak per team, as of their most recent finished game this season.
 * A loss or tie resets the streak to 0; teams with no finished games aren't included.
 * @return array<int,int> team_id => current win streak
 */
function team_win_streaks(int $seasonId): array
{
    $rows = db_all(
        "SELECT r.team_id, r.win
         FROM weeks w
         JOIN games g ON g.week_id = w.id AND g.status = 'final'
            AND g.home_score IS NOT NULL AND g.away_score IS NOT NULL
         JOIN (
            SELECT id AS game_id, home_team_id AS team_id, (home_score > away_score) AS win
            FROM games WHERE status = 'final'
            UNION ALL
            SELECT id, away_team_id, (away_score > home_score)
            FROM games WHERE status = 'final'
         ) r ON r.game_id = g.id
         WHERE w.season_id = ?
         ORDER BY r.team_id, w.week_number",
        [$seasonId]
    );
    $streaks = [];
    foreach ($rows as $row) {
        $teamId = (int)$row['team_id'];
        $streaks[$teamId] = $row['win'] ? (($streaks[$teamId] ?? 0) + 1) : 0;
    }
    return $streaks;
}

/** Team standings, scoped to one season (defaults to the active one). */
function standings(?int $seasonId = null): array
{
    $seasonId ??= (int)(season_active()['id'] ?? 0);
    return db_all(
        "SELECT t.id, t.name,
                COUNT(r.game_id) AS played,
                SUM(r.win) AS wins,
                SUM(r.loss) AS losses,
                SUM(r.tie) AS ties,
                SUM(r.win) * 2 + SUM(r.tie) AS points,
                SUM(r.scored) AS scored,
                SUM(r.allowed) AS allowed,
                SUM(r.scored) - SUM(r.allowed) AS diff
         FROM teams t
         LEFT JOIN (
            SELECT g.id AS game_id, g.home_team_id AS team_id,
                   g.home_score > g.away_score AS win,
                   g.home_score < g.away_score AS loss,
                   g.home_score = g.away_score AS tie,
                   g.home_score AS scored, g.away_score AS allowed
            FROM games g WHERE g.status = 'final' AND g.home_score IS NOT NULL AND g.away_score IS NOT NULL
            UNION ALL
            SELECT g.id, g.away_team_id,
                   g.away_score > g.home_score,
                   g.away_score < g.home_score,
                   g.away_score = g.home_score,
                   g.away_score, g.home_score
            FROM games g WHERE g.status = 'final' AND g.home_score IS NOT NULL AND g.away_score IS NOT NULL
         ) r ON r.team_id = t.id
         WHERE t.season_id = ? AND t.is_active = 1
         GROUP BY t.id, t.name
         ORDER BY points DESC, diff DESC, t.name",
        [$seasonId]
    );
}

function week_results_summary(int $weekId): array
{
    $games = week_games($weekId);
    foreach ($games as &$game) {
        $game['winner_id'] = game_winner_id($game);
    }
    return $games;
}
