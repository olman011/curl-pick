-- The Hog Line - weekly picks league
-- MySQL 8 / MariaDB 10.4+

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code CHAR(32) NOT NULL,
  label VARCHAR(120) NULL,
  max_uses INT UNSIGNED NOT NULL DEFAULT 1,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invites_code (code),
  CONSTRAINT fk_invites_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A member asks for a reset; an admin issues a one-time link (no SMTP required).
CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  issued_at DATETIME NULL,
  expires_at DATETIME NULL,
  used_at DATETIME NULL,
  KEY idx_resets_user (user_id),
  KEY idx_resets_token (token_hash),
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A season is the unit of play: teams and weeks both belong to exactly one.
-- Exactly one season should have is_active = 1 at a time (enforced in app code,
-- see season_activate() in src/league.php). Picks and the live standings/leaderboard
-- only ever operate on the active season; past seasons remain as a read-only archive.
CREATE TABLE seasons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  drop_weeks TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seasons_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_teams_season_name (season_id, name),
  CONSTRAINT fk_teams_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE weeks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id INT UNSIGNED NOT NULL,
  week_number INT UNSIGNED NOT NULL,
  game_date DATE NOT NULL,
  lock_at DATETIME NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  UNIQUE KEY uq_weeks_season_number (season_id, week_number),
  CONSTRAINT fk_weeks_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE games (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  week_id INT UNSIGNED NOT NULL,
  slot INT UNSIGNED NOT NULL,
  location VARCHAR(80) NULL,
  home_team_id INT UNSIGNED NOT NULL,
  away_team_id INT UNSIGNED NOT NULL,
  home_score INT NULL,
  away_score INT NULL,
  status ENUM('scheduled','final') NOT NULL DEFAULT 'scheduled',
  UNIQUE KEY uq_games_week_slot (week_id, slot),
  KEY idx_games_week (week_id),
  CONSTRAINT fk_games_week FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE,
  CONSTRAINT fk_games_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_games_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE picks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  game_id INT UNSIGNED NOT NULL,
  picked_team_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_picks_user_game (user_id, game_id),
  KEY idx_picks_game (game_id),
  CONSTRAINT fk_picks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_picks_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
  CONSTRAINT fk_picks_team FOREIGN KEY (picked_team_id) REFERENCES teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
