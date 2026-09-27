<?php
/**
 * Photo submissions — shared setup: tables, the reviewer permission and
 * the Submission settings screen (Gallery → Submission settings).
 *
 * Every number the submission rules use lives in one option,
 * db_gallery_settings, read through db_gallery_setting() so a missing or
 * blank value always falls back to the default below.
 */

if (!defined('ABSPATH')) exit;

const DB_GALLERY_DB_VERSION = 4;
const DB_REVIEW_CAP = 'db_review_photos';

/* -----------------------------------------------------------------------
 * Tables
 * -------------------------------------------------------------------- */

function db_member_emails_table() {
  global $wpdb;
  return $wpdb->prefix . 'db_member_emails';
}

function db_species_table() {
  global $wpdb;
  return $wpdb->prefix . 'db_species';
}

function db_photo_log_table() {
  global $wpdb;
  return $wpdb->prefix . 'db_photo_log';
}

/**
 * Create or update the tables when the theme's schema version moves on.
 * dbDelta is picky: two spaces after PRIMARY KEY, one field per line.
 */
add_action('admin_init', 'db_gallery_install');
function db_gallery_install() {
  if ((int) get_option('db_gallery_db_version') >= DB_GALLERY_DB_VERSION) return;
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  $charset = $wpdb->get_charset_collate();

  dbDelta('CREATE TABLE ' . db_member_emails_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  email varchar(191) NOT NULL,
  source varchar(20) NOT NULL DEFAULT '',
  added_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email)
) $charset;");

  // Species rows are never deleted by a refresh — photos point at their
  // id — so a species missing from a newer list is only hidden (active 0).
  dbDelta('CREATE TABLE ' . db_species_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  species_code varchar(20) NOT NULL DEFAULT '',
  common_name varchar(191) NOT NULL,
  scientific_name varchar(191) NOT NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY scientific_name (scientific_name),
  KEY common_name (common_name),
  KEY species_code (species_code)
) $charset;");

  // One row per submission, kept when the photo is rejected or deleted:
  // the submission limits count every submission, whatever became of it.
  dbDelta('CREATE TABLE ' . db_photo_log_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  email varchar(191) NOT NULL,
  species_id bigint(20) unsigned NOT NULL DEFAULT 0,
  is_member tinyint(1) NOT NULL DEFAULT 0,
  species_cap_reached tinyint(1) NOT NULL DEFAULT 0,
  submitted_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY email_time (email,submitted_at),
  KEY species_time (species_id,submitted_at),
  KEY post_id (post_id)
) $charset;");
  db_photo_log_backfill();

  dbDelta('CREATE TABLE ' . db_mail_log_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sent_at datetime NOT NULL,
  recipients varchar(500) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  context varchar(40) NOT NULL DEFAULT '',
  ok tinyint(1) NOT NULL DEFAULT 0,
  error varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY sent_at (sent_at),
  KEY ok (ok)
) $charset;");

  // Review links: only a keyed hash of each token is kept.
  dbDelta('CREATE TABLE ' . db_review_tokens_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token_hash char(64) NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  used_at datetime DEFAULT NULL,
  used_action varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY token_hash (token_hash),
  KEY post_id (post_id)
) $charset;");

  // Photos published before approval dates were recorded start their
  // time in the gallery now.
  db_expiry_backfill();

  update_option('db_gallery_db_version', DB_GALLERY_DB_VERSION);
}

/* -----------------------------------------------------------------------
 * Photo reviewer permission
 *
 * A "Photo reviewer" role for committee members who only review photos,
 * and a checkbox on any user's profile to add the permission to someone
 * who already has another role. Administrators always have it.
 * -------------------------------------------------------------------- */

add_action('init', function() {
  if (!get_role('db_photo_reviewer')) {
    add_role('db_photo_reviewer', 'Photo reviewer', ['read' => true, DB_REVIEW_CAP => true]);
  }
});

add_filter('user_has_cap', function($allcaps) {
  if (!empty($allcaps['manage_options'])) $allcaps[DB_REVIEW_CAP] = true;
  return $allcaps;
});

function db_profile_reviewer_field($user) {
  if (!current_user_can('manage_options')) return;
  $has = user_can($user, DB_REVIEW_CAP);
  $via_role = user_can($user, 'manage_options') || in_array('db_photo_reviewer', (array) $user->roles, true);
  ?>
  <h2>Deccan Birders</h2>
  <table class="form-table" role="presentation"><tr>
    <th scope="row">Photo reviewer</th>
    <td>
      <label>
        <input type="checkbox" name="db_photo_reviewer" value="1" <?php checked($has); ?> <?php disabled($via_role); ?>>
        Reviews photograph submissions (gets the approval emails)
      </label>
      <?php if ($via_role): ?><p class="description">Given by this user's role.</p><?php endif; ?>
      <?php wp_nonce_field('db_photo_reviewer_' . $user->ID, 'db_photo_reviewer_nonce'); ?>
    </td>
  </tr></table>
  <?php
}
add_action('show_user_profile', 'db_profile_reviewer_field');
add_action('edit_user_profile', 'db_profile_reviewer_field');

function db_profile_reviewer_save($user_id) {
  if (!current_user_can('manage_options')) return;
  if (!wp_verify_nonce($_POST['db_photo_reviewer_nonce'] ?? '', 'db_photo_reviewer_' . $user_id)) return;
  $user = get_userdata($user_id);
  if (!$user || user_can($user, 'manage_options') || in_array('db_photo_reviewer', (array) $user->roles, true)) return;
  if (!empty($_POST['db_photo_reviewer'])) $user->add_cap(DB_REVIEW_CAP);
  else $user->remove_cap(DB_REVIEW_CAP);
}
add_action('personal_options_update', 'db_profile_reviewer_save');
add_action('edit_user_profile_update', 'db_profile_reviewer_save');

/** Everyone who reviews photos: the people the approval emails go to. */
function db_photo_reviewers() {
  return array_values(array_filter(
    get_users(['fields' => 'all']),
    fn($u) => user_can($u, DB_REVIEW_CAP) && is_email($u->user_email)
  ));
}

/* -----------------------------------------------------------------------
 * Settings
 * -------------------------------------------------------------------- */

/** name => [label, default, min, max, unit/hint]. All whole numbers. */
function db_gallery_number_settings() {
  return [
    'member_limit'        => ['Submissions allowed per member', 5, 0, 100, 'In the window below. A member is any address on the Member Emails list.'],
    'nonmember_limit'     => ['Submissions allowed per non-member', 1, 0, 100, 'In the window below.'],
    'limit_window_days'   => ['Submission limit window', 30, 1, 365, 'days, rolling, counted by submission date. Every submission counts, including rejected ones.'],
    'species_cap'         => ['Photos per species', 2, 1, 100, 'across the whole gallery, approved or pending. Over the cap the photo is still accepted, the submitter is told, and the reviewers\' email flags it.'],
    'species_window_days' => ['Species cap window', 30, 1, 365, 'days, rolling, counted by submission date.'],
    'max_upload_mb'       => ['Largest upload', 3, 1, 20, 'MB. JPG, PNG or WebP.'],
    'max_edge_px'         => ['Longest edge after resizing', 2048, 800, 6000, 'pixels.'],
    'image_quality'       => ['Compression quality', 85, 60, 100, 'JPEG / WebP quality.'],
    'link_expiry_days'    => ['Approval links expire after', 7, 1, 30, 'days.'],
    'expiry_days'         => ['Photos leave the gallery after', 90, 1, 3650, 'days from approval. They are archived, not deleted.'],
  ];
}

function db_gallery_default_reject_reasons() {
  return implode("\n", [
    'The bird is not clear enough to identify',
    'This looks like a nest photograph, or the bird may have been disturbed',
    'The image quality is too low',
    'The species name does not match the bird in the photograph',
    'It is very similar to a photograph already in the gallery',
  ]);
}

/** A setting's value, falling back to its default when unset or out of range. */
function db_gallery_setting($name) {
  $all = (array) get_option('db_gallery_settings', []);
  $numbers = db_gallery_number_settings();
  if (isset($numbers[$name])) {
    [, $default, $min, $max] = $numbers[$name];
    $v = $all[$name] ?? '';
    return (is_numeric($v) && (int) $v >= $min && (int) $v <= $max) ? (int) $v : $default;
  }
  if ($name === 'reject_reasons') {
    $reasons = array_values(array_filter(array_map('trim', explode("\n", (string) ($all['reject_reasons'] ?? '')))));
    return $reasons ?: explode("\n", db_gallery_default_reject_reasons());
  }
  return $all[$name] ?? '';
}

add_action('admin_menu', function() {
  add_submenu_page(
    'edit.php?post_type=db_gallery_photo',
    'Submission settings',
    'Submission settings',
    'manage_options',
    'db-gallery-settings',
    'db_gallery_settings_page'
  );
});

add_action('admin_init', function() {
  register_setting('db_gallery_settings', 'db_gallery_settings', [
    'type'              => 'array',
    'sanitize_callback' => 'db_gallery_sanitize_settings',
    'default'           => [],
  ]);
});

function db_gallery_sanitize_settings($input) {
  $input = (array) $input;
  $out = [];
  foreach (db_gallery_number_settings() as $name => [$label, $default, $min, $max]) {
    $v = trim((string) ($input[$name] ?? ''));
    if ($v === '') { $out[$name] = $default; continue; }
    if (!ctype_digit($v) || (int) $v < $min || (int) $v > $max) {
      add_settings_error('db_gallery_settings', $name, sprintf('%s must be a whole number from %d to %d — kept the previous value.', $label, $min, $max));
      $out[$name] = db_gallery_setting($name);
      continue;
    }
    $out[$name] = (int) $v;
  }
  $reasons = array_filter(array_map('sanitize_text_field', explode("\n", (string) ($input['reject_reasons'] ?? ''))));
  $out['reject_reasons'] = implode("\n", $reasons);
  return $out;
}

function db_gallery_settings_page() {
  if (!current_user_can('manage_options')) return;
  ?>
  <div class="wrap">
    <h1>Submission settings</h1>
    <p>The rules for the "Submit a photograph" form on the Gallery page.</p>
    <?php // All slugs: WordPress files its "Settings saved." under 'general',
    // and only shows it by itself on the Settings menu's own pages.
    settings_errors(); ?>
    <form method="post" action="options.php">
      <?php settings_fields('db_gallery_settings'); ?>
      <table class="form-table" role="presentation">
        <?php foreach (db_gallery_number_settings() as $name => [$label, $default, $min, $max, $hint]):
          $id = 'db-gs-' . $name; ?>
          <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
              <input id="<?php echo esc_attr($id); ?>" type="number" class="small-text"
                     name="db_gallery_settings[<?php echo esc_attr($name); ?>]"
                     min="<?php echo (int) $min; ?>" max="<?php echo (int) $max; ?>" step="1"
                     value="<?php echo esc_attr(db_gallery_setting($name)); ?>">
              <span class="description"><?php echo esc_html($hint); ?> Default <?php echo (int) $default; ?>.</span>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <th scope="row"><label for="db-gs-reasons">Reasons for rejecting</label></th>
          <td>
            <textarea id="db-gs-reasons" name="db_gallery_settings[reject_reasons]" rows="6" class="large-text"><?php
              echo esc_textarea(implode("\n", db_gallery_setting('reject_reasons')));
            ?></textarea>
            <p class="description">One per line. The reviewer picks one when rejecting, and it is quoted in the email to the photographer.</p>
          </td>
        </tr>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}
