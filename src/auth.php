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

function create_user(string $name, string $email, string $password, bool $isAdmin = false): int
{
    db_run(
        'INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)',
        [trim($name), strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $isAdmin ? 1 : 0]
    );
    return (int)db()->lastInsertId();
}
