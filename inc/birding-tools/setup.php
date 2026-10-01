<?php
/**
 * Birding Tools: the pages, the menu, and the 3-day recalculation.
 *
 * Every 3 days db_bt_start begins a run; db_bt_tick then does it a slice
 * at a time (about 20 seconds each, a minute apart) until both pages'
 * data are written, and purges the page cache so visitors see them. The
 * Birding Tools screen in wp-admin shows where a run is, and starts one
 * on demand.
 */

if (!defined('ABSPATH')) exit;

const DB_BT_START_HOOK = 'db_bt_start';
const DB_BT_TICK_HOOK  = 'db_bt_tick';

add_filter('cron_schedules', function($s) {
  $s['db_bt_every_3_days'] = ['interval' => 3 * DAY_IN_SECONDS, 'display' => 'Every 3 days'];
  return $s;
});

add_action('init', function() {
  if (!wp_next_scheduled(DB_BT_START_HOOK)) {
    // Early morning, India time, when the site is quiet.
    wp_schedule_event((new DateTime('tomorrow 02:30', wp_timezone()))->getTimestamp(), 'db_bt_every_3_days', DB_BT_START_HOOK);
  }
  // First time round there are no figures at all: show the ones shipped
  // with the theme (worked out when it was built) and start a fresh run
  // straight away (once; a failed first run waits for the schedule or the
  // admin button).
  if (!db_bt_read('trends.json')) {
    foreach (['trends', 'migration'] as $name) {
      $seed = get_template_directory() . '/assets/data/bt-seed-' . $name . '.json';
      $data = is_readable($seed) ? json_decode((string) file_get_contents($seed), true) : null;
      if (is_array($data)) db_bt_write($name . '.json', $data);
    }
    if (!db_bt_job()) db_bt_start();
  }
});

add_action(DB_BT_START_HOOK, 'db_bt_start');
add_action(DB_BT_TICK_HOOK, 'db_bt_tick');

// Switching the theme off stops the recalculation with it.
add_action('switch_theme', function() {
  wp_clear_scheduled_hook(DB_BT_START_HOOK);
  wp_clear_scheduled_hook(DB_BT_TICK_HOOK);
});

function db_bt_job() {
  return db_bt_read('job.json') ?: [];
}

function db_bt_running() {
  $job = db_bt_job();
  // A run that has made no progress for 6 hours has died; let a new one start.
  return ($job['status'] ?? '') === 'running' && (time() - (int) ($job['touched'] ?? 0)) < 6 * HOUR_IN_SECONDS;
}

/** Begin a new run (unless one is under way) and take its first slice soon. */
function db_bt_start() {
  if (db_bt_running()) return;
  $run = (string) time();
  db_bt_write('job.json', ['status' => 'running', 'run' => $run, 'started' => time(), 'touched' => time(),
    'fetched' => 0, 'wanted' => 0, 'retries' => 0, 'failed' => []]);
  wp_schedule_single_event(time() + 5, DB_BT_TICK_HOOK);
}

/** One slice of a run: fetch what is missing for up to ~20 seconds. */
function db_bt_tick() {
  $job = db_bt_job();
  if (($job['status'] ?? '') !== 'running') return;
  if (get_transient('db_bt_tick_lock')) return;
  set_transient('db_bt_tick_lock', 1, 2 * MINUTE_IN_SECONDS);
  @set_time_limit(90);
  $began = microtime(true);
  $run = $job['run'];

  try {
    while (true) {
      $result = db_bt_compute($run);
      if ($result['done']) {
        db_bt_write('trends.json', $result['trends']);
        db_bt_write('migration.json', $result['migration']);
        $job['status'] = 'done';
        $job['finished'] = time();
        $job['summary'] = db_bt_summary($result);
        db_bt_write('job.json', $job + ['touched' => time()]);
        db_bt_clean_old_runs($run);
        if (class_exists('LiteSpeed\Purge')) LiteSpeed\Purge::purge_all();
        return;
      }

      $wanted = array_keys($GLOBALS['db_bt_wanted'] ?? []);
      $job['wanted'] = $job['fetched'] + count($wanted);
      foreach ($wanted as $url) {
        if (microtime(true) - $began > 20) break 2;
        $status = db_bt_fetch($url, $run);
        if ($status === 'ok') {
          $job['fetched']++;
        } elseif ($status === 'retry') {
          // GBIF asked us to slow down: come back in a couple of minutes.
          $job['retries']++;
          $job['touched'] = time();
          db_bt_write('job.json', $job);
          wp_schedule_single_event(time() + 2 * MINUTE_IN_SECONDS, DB_BT_TICK_HOOK);
          return;
        } else {
          $job['failed'][] = $url;
          $job['status'] = 'failed';
          $job['error'] = 'GBIF refused a request (see Birding Tools in wp-admin).';
          db_bt_write('job.json', $job + ['touched' => time()]);
          return;
        }
        usleep(300000); // gently: GBIF turns away bursts
      }
      if (microtime(true) - $began > 20) break;
    }
    $job['touched'] = time();
    db_bt_write('job.json', $job);
    wp_schedule_single_event(time() + MINUTE_IN_SECONDS, DB_BT_TICK_HOOK);
  } finally {
    delete_transient('db_bt_tick_lock');
  }
}

/** A few numbers for the status screen. */
function db_bt_summary(array $r) {
  $out = [];
  foreach ($r['trends']['states'] as $k => $st) {
    if (!empty($st['error'])) { $out[$k] = ['error' => $st['error']]; continue; }
    $labels = [];
    foreach ($r['trends']['species'] as $sp) if (isset($sp[$k]['label'])) $labels[$sp[$k]['label']] = ($labels[$sp[$k]['label']] ?? 0) + 1;
    $out[$k] = ['years' => $st['years'][0] . '–' . end($st['years']), 'labels' => $labels];
  }
  $out['migrants'] = count($r['migration']['species']);
  $out['spots'] = count($r['migration']['spots']);
  $out['waves'] = count($r['migration']['waves']);
  return $out;
}

/** Saved answers from earlier runs are not needed once a run completes. */
function db_bt_clean_old_runs($keep) {
  foreach (glob(db_bt_dir() . '/cache-*', GLOB_ONLYDIR) ?: [] as $dir) {
    if (basename($dir) === 'cache-' . $keep) continue;
    array_map('unlink', glob($dir . '/{,.}*.json', GLOB_BRACE) ?: []);
    @unlink($dir . '/.htaccess');
    @rmdir($dir);
  }
}

/* -----------------------------------------------------------------------
 * Pages and menu, once
 * -------------------------------------------------------------------- */

add_action('init', function() {
  if (get_option('db_bt_setup_v1')) return;
  if (!current_user_can('manage_options') && !wp_doing_cron()) return; // only when an admin visits, so it never races
  update_option('db_bt_setup_v1', 1, false);

  $ids = [];
  foreach (['migration-season' => 'Migration Season', 'bird-trends' => 'Bird Trends'] as $slug => $title) {
    $page = get_page_by_path($slug);
    $ids[$slug] = $page ? $page->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug]);
  }

  // Birding Tools before Contact in the main menu, opening Migration Season.
  $locations = get_nav_menu_locations();
  $menu_id = $locations['primary-nav'] ?? 0;
  if (!$menu_id || is_wp_error($ids['migration-season']) || is_wp_error($ids['bird-trends'])) return;
  $items = wp_get_nav_menu_items($menu_id) ?: [];
  foreach ($items as $item) if ($item->title === 'Birding Tools') return; // someone added it already

  $top = array_filter($items, fn($i) => !(int) $i->menu_item_parent);
  usort($top, fn($a, $b) => $a->menu_order <=> $b->menu_order);
  $position = count($items) + 1;
  foreach ($top as $item) {
    if (stripos($item->title, 'contact') !== false) { $position = (int) $item->menu_order; break; }
  }
  // Make room: everything from Contact on moves along by three.
  foreach ($items as $item) {
    if ((int) $item->menu_order >= $position) wp_update_post(['ID' => $item->ID, 'menu_order' => (int) $item->menu_order + 3]);
  }
  $parent = wp_update_nav_menu_item($menu_id, 0, [
    'menu-item-title'    => 'Birding Tools',
    'menu-item-url'      => get_permalink($ids['migration-season']),
    'menu-item-type'     => 'custom',
    'menu-item-status'   => 'publish',
    'menu-item-position' => $position,
  ]);
  if (is_wp_error($parent)) return;
  foreach (['migration-season', 'bird-trends'] as $i => $slug) {
    wp_update_nav_menu_item($menu_id, 0, [
      'menu-item-object-id' => $ids[$slug],
      'menu-item-object'    => 'page',
      'menu-item-type'      => 'post_type',
      'menu-item-parent-id' => $parent,
      'menu-item-status'    => 'publish',
      'menu-item-position'  => $position + 1 + $i,
    ]);
  }
});

// The menu's Birding Tools item is a link, so mark it current on its two pages.
add_filter('nav_menu_css_class', function($classes, $item) {
  if ($item->title === 'Birding Tools' && (is_page('migration-season') || is_page('bird-trends'))) {
    $classes[] = 'current-menu-ancestor';
  }
  return $classes;
}, 10, 2);

/* -----------------------------------------------------------------------
 * Assets
 * -------------------------------------------------------------------- */

add_action('wp_enqueue_scripts', function() {
  $pages = ['bird-trends' => 'bird-trends', 'migration-season' => 'migration-season'];
  foreach ($pages as $slug => $script) {
    if (!is_page($slug)) continue;
    $dir = get_template_directory();
    $uri = get_template_directory_uri();
    wp_enqueue_style('db-birding-tools', $uri . '/assets/css/birding-tools.css', ['db-main'], (string) @filemtime($dir . '/assets/css/birding-tools.css'));
    wp_enqueue_script('db-' . $script, $uri . '/assets/js/' . $script . '.js', [], (string) @filemtime($dir . '/assets/js/' . $script . '.js'), true);
    if ($slug === 'migration-season') {
      wp_localize_script('db-migration-season', 'DB_BT', ['geo' => $uri . '/assets/data/ts-ap-geo.json']);
    }
  }
});

/** The data credit under both pages, with when the figures were worked out. */
function db_bt_credit($generated, $extra = '') {
  $when = $generated ? wp_date('j M Y', strtotime($generated)) : '';
  printf(
    '<p class="bt-credit">Data: <a href="https://www.gbif.org/dataset/%s" target="_blank" rel="noopener">eBird Observation Dataset</a>, Cornell Lab of Ornithology, via GBIF (CC BY 4.0).%s%s</p>',
    esc_attr(DB_BT_DATASET),
    $extra ? ' ' . esc_html($extra) : '',
    $when ? ' Figures worked out ' . esc_html($when) . ', and again every 3 days.' : ''
  );
}
