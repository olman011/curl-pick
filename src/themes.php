<?php
declare(strict_types=1);

/**
 * Site themes. To add one: drop a stylesheet in public/assets/themes/ that overrides
 * the :root variables from app.css (and anything else it wants to restyle), then add
 * an entry here. 'css' is the file name inside /assets/themes/ (null = app.css alone).
 *
 * @return array<string,array{label:string,description:string,css:?string,theme_color:string,swatches:list<string>}>
 */
function themes_available(): array
{
    return [
        'classic' => [
            'label' => 'Classic',
            'description' => 'The original blue, white and red look.',
            'css' => null,
            'theme_color' => '#1857a4',
            'swatches' => ['#1857a4', '#123f7a', '#c62b34', '#ffffff'],
        ],
        'thanksgiving' => [
            'label' => 'Thanksgiving',
            'description' => 'Harvest orange, rust and gold on warm cream, with a woven-band header, drifting leaves and a turkey.',
            'css' => 'thanksgiving.css',
            'theme_color' => '#9a4a14',
            'swatches' => ['#9a4a14', '#6e3410', '#e0a526', '#fbf6ec'],
        ],
        'christmas' => [
            'label' => 'Christmas',
            'description' => 'Evergreen and red with a candy-cane header, light snowfall and a festive curling house.',
            'css' => 'christmas.css',
            'theme_color' => '#1f5a37',
            'swatches' => ['#1f5a37', '#163f27', '#c1272d', '#ffffff'],
        ],
        'newyear' => [
            'label' => "New Year's",
            'description' => 'Midnight indigo with a shimmering gold stripe, falling confetti and a curling house that strikes midnight.',
            'css' => 'newyear.css',
            'theme_color' => '#232a6b',
            'swatches' => ['#232a6b', '#161b4a', '#d4af37', '#ffffff'],
        ],
    ];
}

/** All saved settings. Quietly empty if the settings table hasn't been created yet. */
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        try {
            foreach (db_all('SELECT name, value FROM settings') as $row) {
                $cache[$row['name']] = $row['value'];
            }
        } catch (Throwable $e) {
            // Migration not run yet: behave as if nothing is set so the site keeps working.
        }
    }
    return $cache;
}

function setting_get(string $name, ?string $default = null): ?string
{
    return settings_all()[$name] ?? $default;
}

/** Throws if the settings table is missing - callers that can recover should catch it. */
function setting_set(string $name, string $value): void
{
    db_run('REPLACE INTO settings (name, value) VALUES (?, ?)', [$name, $value]);
    settings_all(true);
}

/** The theme members see: the saved choice, falling back to classic if it's unknown. */
function theme_saved_key(): string
{
    $saved = setting_get('theme', 'classic');
    return isset(themes_available()[$saved]) ? $saved : 'classic';
}

/**
 * A theme an admin is trying out. Remembered in their session so they can click around
 * every page; it never changes what members see. ?theme_preview=off ends it.
 */
function theme_preview_key(): ?string
{
    $viewer = current_user();
    if (!$viewer || (int)$viewer['is_admin'] !== 1) {
        return null;
    }
    if (isset($_GET['theme_preview'])) {
        $want = (string)$_GET['theme_preview'];
        if (isset(themes_available()[$want])) {
            $_SESSION['theme_preview'] = $want;
        } else {
            unset($_SESSION['theme_preview']);
        }
    }
    $key = $_SESSION['theme_preview'] ?? null;
    return (is_string($key) && isset(themes_available()[$key])) ? $key : null;
}

function theme_active_key(): string
{
    return theme_preview_key() ?? theme_saved_key();
}

/** Stylesheet URL with its modified time attached, so a changed file is never served stale from a cache. */
function asset_url(string $path): string
{
    $file = APP_ROOT . '/public' . $path;
    return $path . '?v=' . (is_file($file) ? (string)filemtime($file) : '1');
}
