<?php
// Copy to config/config.php and fill in for your server.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'hogline',
        'user' => 'hogline',
        'pass' => 'change-me',
    ],
    'app' => [
        'name' => 'The Hog Line',
        'base_url' => 'https://thehogline.com',
        'timezone' => 'America/Chicago',
        'default_lock_time' => '20:45',
        'games_per_week' => 8,
    ],
];
