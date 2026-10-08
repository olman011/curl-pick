<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $cached = null;
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    if ($cached === null || (int)$cached['id'] !== (int)$id) {
        $cached = db_one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
        if ($cached === null) {
            unset($_SESSION['user_id']);
        }
    }
    return $cached;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ((int)$user['is_admin'] !== 1) {
        http_response_code(403);
        exit('Admins only.');
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function attempt_login(string $email, string $password): ?array
{
    $user = db_one('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    if (!$user || (int)$user['is_active'] !== 1) {
        return null;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}

function password_problem(string $password, string $confirm): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        return 'Passwords do not match.';
    }
    return null;
}

function find_usable_invite(string $code): ?array
{
    $invite = db_one('SELECT * FROM invites WHERE code = ?', [$code]);
    if (!$invite) {
        return null;
    }
    if ($invite['expires_at'] !== null && strtotime($invite['expires_at']) < time()) {
        return null;
    }
    if ((int)$invite['used_count'] >= (int)$invite['max_uses']) {
        return null;
    }
    return $invite;
}

/**
 * Invalidates any existing unused reset for this user and issues a fresh one.
 * $markIssued controls whether it's immediately flagged as "handled" (shown on the
 * admin dashboard only while NOT issued) - pass true when an admin is issuing it
 * directly, or when an automatic email just went out successfully; pass false when
 * email delivery hasn't been confirmed, so it still surfaces for the admin to send
 * manually as a fallback. issued_at is purely a bookkeeping flag - reset.php itself
 * doesn't check it, so the token works either way once a person actually has it.
 */
function create_password_reset(int $userId, bool $markIssued): string
{
    db_run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
    $token = bin2hex(random_bytes(24));
    db_run(
        'INSERT INTO password_resets (user_id, token_hash, issued_at, expires_at)
         VALUES (?, ?, ' . ($markIssued ? 'NOW()' : 'NULL') . ', DATE_ADD(NOW(), INTERVAL 3 DAY))',
        [$userId, hash('sha256', $token)]
    );
    return $token;
}

function create_user(string $name, string $email, string $password, bool $isAdmin = false): int
{
    db_run(
        'INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)',
        [trim($name), strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $isAdmin ? 1 : 0]
    );
    return (int)db()->lastInsertId();
}
