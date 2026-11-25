<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require APP_ROOT . '/src/layout.php';

require_admin();

$themes = themes_available();

if (is_post()) {
    csrf_check();
    $choice = (string)($_POST['theme'] ?? '');
    if (!isset($themes[$choice])) {
        flash('Pick one of the listed themes.', 'error');
    } else {
        try {
            setting_set('theme', $choice);
            flash('Theme switched to ' . $themes[$choice]['label'] . '. Members see it right away.');
        } catch (Throwable $e) {
            flash('Could not save the theme - has the settings table been created? (Run MIGRATION_themes.sql once.)', 'error');
        }
    }
    redirect('/admin/theme.php');
}

$current = theme_saved_key();
layout_header('Site theme');
?>
<h1>Site theme</h1>
<p class="sub">Changes the look for everyone. Use Preview to try a theme yourself first &mdash; members won't see it until you save.</p>

<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($themes as $key => $t): ?>
    <label class="card" style="display:block;cursor:pointer">
      <div style="display:flex;align-items:center;gap:10px">
        <input type="radio" name="theme" value="<?= h($key) ?>" <?= $key === $current ? 'checked' : '' ?> style="margin:0">
        <strong><?= h($t['label']) ?></strong>
        <?= $key === $current ? '<span class="tag tag-open">live now</span>' : '' ?>
        <span style="margin-left:auto;display:flex;gap:4px">
          <?php foreach ($t['swatches'] as $color): ?>
            <span style="width:18px;height:18px;border-radius:50%;background:<?= h($color) ?>;border:1px solid rgba(0,0,0,.2)"></span>
          <?php endforeach; ?>
        </span>
      </div>
      <p class="sub" style="margin:8px 0 0"><?= h($t['description']) ?></p>
      <p style="margin:8px 0 0;font-size:14px"><a href="/index.php?theme_preview=<?= h($key) ?>">Preview this theme</a></p>
    </label>
  <?php endforeach; ?>
  <button type="submit">Use selected theme</button>
</form>
<p class="center"><a href="/admin/index.php">Back to admin</a></p>
<?php layout_footer();
