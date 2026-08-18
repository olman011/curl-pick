<?php
declare(strict_types=1);

function layout_header(string $title, bool $chrome = true): void
{
    $user = current_user();
    $appName = (string)config('app.name');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d2b45">
<title><?= h($title) ?> &middot; <?= h($appName) ?></title>
<link rel="stylesheet" href="/assets/app.css?v=3">
</head>
<body>
<header class="topbar">
  <a class="brand" href="/"><?= h($appName) ?></a>
  <?php if ($chrome && $user): ?>
    <a class="topbar-user" href="/profile.php"><?= h($user['name']) ?></a>
  <?php endif; ?>
</header>

<?php if ($chrome && $user): ?>
<nav class="tabs">
  <a href="/index.php">Picks</a>
  <a href="/results.php">Results</a>
  <a href="/leaderboard.php">Leaders</a>
  <a href="/standings.php">Standings</a>
  <?php if ((int)$user['is_admin'] === 1): ?><a href="/admin/index.php">Admin</a><?php endif; ?>
</nav>
<?php endif; ?>

<main class="wrap">
<?php foreach (take_flashes() as $flash): ?>
  <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
<?php endforeach; ?>
<?php
}

function layout_footer(): void
{
    ?>
</main>
<footer class="foot">Picks lock at <?= h((string)config('app.default_lock_time')) ?> game day &middot; times shown in <?= h((string)config('app.timezone')) ?></footer>
</body>
</html>
<?php
}
