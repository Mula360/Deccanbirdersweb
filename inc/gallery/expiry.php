<?php
/**
 * Photos leave the gallery a set number of days after approval
 * (Submission settings, default 90). They are archived, never deleted:
 * the Photo review queue shows them under Archived as "Expired", and a
 * reviewer can restore one.
 *
 * The check runs once a day on WordPress's scheduler (WP-Cron). WP-Cron
 * only runs when someone visits the site, so on the live site it should
 * be driven by a real cron job in Hostinger instead — see
 * docs/cron-hostinger.md. The Photo review screen shows when the check
 * last ran, and warns when it has not run for over a day and a half.
 */

if (!defined('ABSPATH')) exit;

const DB_EXPIRY_HOOK = 'db_gallery_daily';
const DB_EXPIRY_BATCH = 200;

add_action('init', function() {
  if (!wp_next_scheduled(DB_EXPIRY_HOOK)) {
    // Early morning, India time, when the site is quiet.
    $next = (new DateTime('tomorrow 03:15', wp_timezone()))->getTimestamp();
    wp_schedule_event($next, 'daily', DB_EXPIRY_HOOK);
  }
});

add_action(DB_EXPIRY_HOOK, 'db_expire_photos');

/** Archive every published photo past its time. Returns how many. */
function db_expire_photos() {
  global $wpdb;
  $days   = db_gallery_setting('expiry_days');
  $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - $days * DAY_IN_SECONDS);

  $ids = $wpdb->get_col($wpdb->prepare(
    "SELECT p.ID FROM {$wpdb->posts} p
     JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_db_approved_at'
     WHERE p.post_type = 'db_gallery_photo' AND p.post_status = 'publish' AND m.meta_value <> '' AND m.meta_value <= %s
     ORDER BY m.meta_value ASC LIMIT %d",
    $cutoff, DB_EXPIRY_BATCH
  ));
  foreach ($ids as $id) db_photo_archive((int) $id, 0, 'expired');

  // Housekeeping: review links long past their expiry.
  $wpdb->query($wpdb->prepare(
    'DELETE FROM ' . db_review_tokens_table() . ' WHERE expires_at < %s',
    gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
  ));

  if ($ids && class_exists('LiteSpeed\Purge')) LiteSpeed\Purge::purge_all();
  update_option('db_expiry_last_run', ['at' => current_time('mysql'), 'archived' => count($ids), 'via' => wp_doing_cron() ? 'cron' : 'manual'], false);
  return count($ids);
}

/**
 * Published photos from before approval dates were recorded get today,
 * so they have their full time in the gallery from now.
 */
function db_expiry_backfill() {
  $posts = get_posts([
    'post_type'   => 'db_gallery_photo',
    'post_status' => 'publish',
    'numberposts' => -1,
    'fields'      => 'ids',
    'meta_query'  => [['key' => '_db_approved_at', 'compare' => 'NOT EXISTS']],
  ]);
  foreach ($posts as $id) update_post_meta($id, '_db_approved_at', current_time('mysql'));
  return count($posts);
}

/** "Run the check now", before the screen counts anything. Returns a message or null. */
function db_expiry_handle_post() {
  if (!isset($_POST['db_expiry_run']) || !current_user_can(DB_REVIEW_CAP)) return null;
  check_admin_referer('db_expiry_run');
  $n = db_expire_photos();
  return $n ? sprintf(_n('%d photo had reached its time and was archived.', '%d photos had reached their time and were archived.', $n), $n) : 'Checked: no photo has reached its time yet.';
}

/** The status line and "Run now" for the top of the Photo review screen. */
function db_expiry_status_box($notice = null) {
  $last  = get_option('db_expiry_last_run');
  $next  = wp_next_scheduled(DB_EXPIRY_HOOK);
  $stale = !is_array($last) || strtotime($last['at']) < current_time('timestamp') - 36 * HOUR_IN_SECONDS;
  ?>
  <?php if ($notice): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
  <div class="notice notice-<?php echo $stale ? 'warning' : 'info'; ?> inline" style="margin:12px 0;max-width:1100px">
    <form method="post" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:6px 0">
      <?php wp_nonce_field('db_expiry_run'); ?>
      <span>Photos leave the gallery <strong><?php echo (int) db_gallery_setting('expiry_days'); ?> days</strong> after approval.
        Daily check <?php echo is_array($last)
          ? 'last ran ' . esc_html(mysql2date('j M Y, g:i a', $last['at'])) . ' (' . esc_html($last['via']) . ', ' . (int) $last['archived'] . ' archived)'
          : '<strong>has not run yet</strong>'; ?><?php if ($next): ?>; next due <?php echo esc_html(wp_date('j M Y, g:i a', $next)); ?><?php endif; ?>.
        <?php if ($stale && is_array($last)): ?><strong>It has not run for over a day and a half: check the Hostinger cron job.</strong><?php endif; ?></span>
      <button class="button" name="db_expiry_run" value="1">Run the check now</button>
    </form>
  </div>
  <?php
}

// Switching the theme off stops the daily check with it.
add_action('switch_theme', fn() => wp_clear_scheduled_hook(DB_EXPIRY_HOOK));
