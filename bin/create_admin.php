<?php
declare(strict_types=1);

// Usage: php bin/create_admin.php "Ole" ole@example.com "password"
require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;

if (!$name || !$email || !$password) {
    exit("Usage: php bin/create_admin.php \"Name\" email@example.com \"password\"\n");
}

$existing = db_one('SELECT id FROM users WHERE email = ?', [strtolower($email)]);
if ($existing) {
    db_run(
        'UPDATE users SET name = ?, password_hash = ?, is_admin = 1, is_active = 1 WHERE id = ?',
        [$name, password_hash($password, PASSWORD_DEFAULT), $existing['id']]
    );
    echo "Updated existing user #{$existing['id']} and made them an admin.\n";
    exit;
}

$id = create_user($name, $email, $password, true);
echo "Created admin user #$id.\n";
