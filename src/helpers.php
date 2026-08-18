<?php
declare(strict_types=1);

function config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }
    return $value;
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Invalid request token. Please reload the page and try again.');
    }
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_int(string $key): ?int
{
    $value = $_POST[$key] ?? null;
    return is_numeric($value) ? (int)$value : null;
}

function get_int(string $key): ?int
{
    $value = $_GET[$key] ?? null;
    return is_numeric($value) ? (int)$value : null;
}

function fmt_datetime(?string $mysqlDatetime): string
{
    if (!$mysqlDatetime) {
        return '';
    }
    return (new DateTimeImmutable($mysqlDatetime))->format('D M j, g:i A');
}

function fmt_date(?string $mysqlDate): string
{
    if (!$mysqlDate) {
        return '';
    }
    return (new DateTimeImmutable($mysqlDate))->format('D M j, Y');
}

function base_url(string $path = ''): string
{
    return rtrim((string)config('app.base_url'), '/') . $path;
}
