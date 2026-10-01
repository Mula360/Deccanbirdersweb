<?php
/**
 * Tools → Birding Tools: where the 3-day recalculation stands, a button
 * to run it now, and the habitat overrides for Migration Season.
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
  add_management_page('Birding Tools', 'Birding Tools', 'manage_options', 'db-birding-tools', 'db_bt_admin_page');
});

function db_bt_admin_page() {
  if (!current_user_can('manage_options')) return;
  $notice = '';

  if (isset($_POST['db_bt_run']) && check_admin_referer('db_bt_admin')) {
    if (db_bt_running()) {
      $notice = 'A recalculation is already under way.';
    } else {
      db_bt_start();
      spawn_cron();
      $notice = 'Recalculation started. It takes about 15–30 minutes; this page shows its progress.';
    }
  }
  if (isset($_POST['db_bt_save_habitats']) && check_admin_referer('db_bt_admin')) {
    update_option('db_bt_habitat_overrides', sanitize_textarea_field(wp_unslash($_POST['db_bt_habitats'] ?? '')), false);
    $notice = 'Habitat overrides saved. They take effect at the next recalculation (press "Recalculate now" to apply them straight away).';
  }

  $job    = db_bt_job();
  $next   = wp_next_scheduled(DB_BT_START_HOOK);
  $trends = db_bt_read('trends.json');
  $status = $job['status'] ?? 'never';
  $labels = ['clear-d' => 'clear declines', 'poss-d' => 'possible declines', 'clear-i' => 'clear increases', 'poss-i' => 'possible increases', 'none' => 'no clear change', 'few' => 'too few records'];
  ?>
  <div class="wrap">
    <h1>Birding Tools</h1>
    <p style="max-width:760px">The <a href="<?php echo esc_url(home_url('/migration-season/')); ?>">Migration Season</a> and <a href="<?php echo esc_url(home_url('/bird-trends/')); ?>">Bird Trends</a> pages are worked out from eBird records (via GBIF) and recalculated every 3 days, in the background, a little at a time.</p>
    <?php if ($notice): ?><div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>

    <h2>Status</h2>
    <table class="widefat striped" style="max-width:760px">
      <tbody>
        <tr><th style="width:220px">Figures on the site</th><td><?php echo $trends ? 'Worked out ' . esc_html(wp_date('j M Y, g:i a', strtotime($trends['generated']))) : '<strong>None yet</strong>'; ?></td></tr>
        <tr><th>Latest run</th><td><?php
          if ($status === 'running') {
            $pct = !empty($job['wanted']) ? min(99, round(100 * $job['fetched'] / max(1, $job['wanted']))) : 0;
            printf('Under way since %s: %d requests fetched so far.%s', esc_html(wp_date('j M, g:i a', $job['started'])), (int) $job['fetched'],
              !empty($job['retries']) ? ' GBIF asked us to slow down ' . (int) $job['retries'] . ' times; that is normal.' : '');
          } elseif ($status === 'done') {
            printf('Finished %s.', esc_html(wp_date('j M Y, g:i a', $job['finished'])));
          } elseif ($status === 'failed') {
            echo '<span style="color:#B3261E">Stopped: ' . esc_html($job['error'] ?? 'unknown error') . '</span>';
            foreach (array_slice($job['failed'] ?? [], 0, 3) as $u) echo '<br><code style="font-size:11px;word-break:break-all">' . esc_html($u) . '</code>';
          } else {
            echo 'Never run.';
          }
        ?></td></tr>
        <tr><th>Next scheduled run</th><td><?php echo $next ? esc_html(wp_date('j M Y, g:i a', $next)) : 'Not scheduled'; ?></td></tr>
        <?php if (!empty($job['summary'])): $s = $job['summary']; ?>
          <?php foreach (['TS' => 'Telangana', 'AP' => 'Andhra Pradesh'] as $k => $name): if (empty($s[$k])) continue; ?>
            <tr><th><?php echo esc_html($name); ?></th><td><?php
              if (!empty($s[$k]['error'])) { echo esc_html($s[$k]['error']); }
              else {
                echo esc_html($s[$k]['years']) . ': ';
                echo esc_html(implode(', ', array_map(fn($l, $n) => $n . ' ' . ($labels[$l] ?? $l), array_keys($s[$k]['labels']), $s[$k]['labels'])));
              }
            ?></td></tr>
          <?php endforeach; ?>
          <tr><th>Migration Season</th><td><?php printf('%d winter visitors, %d hotspots, %d countdowns', (int) $s['migrants'], (int) $s['spots'], (int) $s['waves']); ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <form method="post" style="margin-top:12px">
      <?php wp_nonce_field('db_bt_admin'); ?>
      <button class="button button-primary" name="db_bt_run" value="1"<?php disabled($status === 'running' && db_bt_running()); ?>>Recalculate now</button>
    </form>

    <h2 style="margin-top:32px">Habitats</h2>
    <p style="max-width:760px">Migration Season groups each winter visitor as Wetland, Grassland &amp; farmland, Scrub or Woodland, from its bird family (ducks, waders, gulls and herons are wetland; harriers, larks, pipits and starlings grassland; reed warblers and shrikes scrub; leaf warblers, flycatchers and pittas woodland). To move a species, add a line here: its name, an equals sign, and <code>wet</code>, <code>grass</code>, <code>scrub</code> or <code>wood</code>.</p>
    <form method="post">
      <?php wp_nonce_field('db_bt_admin'); ?>
      <textarea name="db_bt_habitats" rows="8" class="large-text code" style="max-width:760px" placeholder="Bluethroat = scrub&#10;Rosy Starling = grass"><?php echo esc_textarea(get_option('db_bt_habitat_overrides', '')); ?></textarea>
      <p><button class="button" name="db_bt_save_habitats" value="1">Save habitats</button></p>
    </form>
  </div>
  <?php
}
