<?php
declare(strict_types=1);

function teams_all(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM teams';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    return db_all($sql . ' ORDER BY name');
}

function weeks_all(bool $publishedOnly = false): array
{
    $sql = 'SELECT * FROM weeks';
    if ($publishedOnly) {
        $sql .= ' WHERE is_published = 1';
    }
    return db_all($sql . ' ORDER BY week_number');
}

function week_find(int $id): ?array
{
    return db_one('SELECT * FROM weeks WHERE id = ?', [$id]);
}

/** The week members should be looking at: the next one still open, else the most recent. */
function week_current(): ?array
{
    $week = db_one(
        'SELECT * FROM weeks WHERE is_published = 1 AND lock_at > NOW() ORDER BY lock_at LIMIT 1'
    );
    if ($week) {
        return $week;
    }
    return db_one('SELECT * FROM weeks WHERE is_published = 1 ORDER BY lock_at DESC LIMIT 1');
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

function week_bye_teams(int $weekId): array
{
    return db_all(
        'SELECT t.* FROM teams t
         WHERE t.is_active = 1
           AND t.id NOT IN (
             SELECT home_team_id FROM games WHERE week_id = :w
             UNION SELECT away_team_id FROM games WHERE week_id = :w2
           )
         ORDER BY t.name',
        ['w' => $weekId, 'w2' => $weekId]
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

function season_leaderboard(): array
{
    return db_all(
        "SELECT u.id, u.name,
                SUM(CASE WHEN g.status = 'final'
                         AND g.home_score <> g.away_score
                         AND p.picked_team_id = IF(g.home_score > g.away_score, g.home_team_id, g.away_team_id)
                    THEN 1 ELSE 0 END) AS correct,
                SUM(CASE WHEN g.status = 'final' AND g.home_score <> g.away_score THEN 1 ELSE 0 END) AS graded,
                COUNT(DISTINCT g.week_id) AS weeks_played
         FROM users u
         LEFT JOIN picks p ON p.user_id = u.id
         LEFT JOIN games g ON g.id = p.game_id
         WHERE u.is_active = 1
         GROUP BY u.id, u.name
         ORDER BY correct DESC, u.name"
    );
}

function standings(): array
{
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
         WHERE t.is_active = 1
         GROUP BY t.id, t.name
         ORDER BY points DESC, diff DESC, t.name"
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
