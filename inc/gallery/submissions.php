<?php
/**
 * The "Submit a photograph" form's handler, with its two rules:
 *
 *  - A submission limit per email address over a rolling window (members,
 *    i.e. addresses on the Member Emails list, get more). Every
 *    submission counts, including rejected ones, so it is counted from
 *    db_photo_log, which keeps a row after the photo itself is gone.
 *  - A gallery-wide cap per species. Over it the photo is still
 *    accepted; the photographer is told and the reviewers' email flags it.
 *
 * Both windows run from the submission date. Limits and windows come
 * from Gallery → Submission settings.
 */

if (!defined('ABSPATH')) exit;

/** The image types the form accepts: extension pattern => MIME type. */
const DB_PHOTO_TYPES = ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
const DB_PHOTO_IMAGETYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
const DB_PHOTO_MAX_PER_HOUR = 10; // abuse guard per IP, above any single address's own limit

function db_photo_max_bytes() {
  return db_gallery_setting('max_upload_mb') * MB_IN_BYTES;
}

/**
 * Where an address stands against its limit. 'next' is the Unix time
 * the oldest counted submission leaves the window, when over the limit.
 */
function db_photo_limit_status($email) {
  global $wpdb;
  $email  = db_normalise_email($email);
  $member = db_is_member_email($email);
  $limit  = db_gallery_setting($member ? 'member_limit' : 'nonmember_limit');
  $days   = db_gallery_setting('limit_window_days');
  $since  = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

  $times = $wpdb->get_col($wpdb->prepare(
    'SELECT submitted_at FROM ' . db_photo_log_table() . ' WHERE email = %s AND submitted_at > %s ORDER BY submitted_at ASC',
    $email, $since
  ));
  $used = count($times);
  $next = null;
  if ($used >= $limit) {
    // Room opens when enough of the oldest have aged out to drop below the limit.
    $next = $limit === 0 ? null : strtotime($times[$used - $limit] . ' UTC') + $days * DAY_IN_SECONDS;
  }
  return ['member' => $member, 'limit' => $limit, 'used' => $used, 'days' => $days, 'next' => $next];
}

/**
 * How many photos of a species are in the gallery or waiting for review,
 * submitted inside the species window.
 */
function db_species_cap_status($species_id) {
  global $wpdb;
  $cap   = db_gallery_setting('species_cap');
  $days  = db_gallery_setting('species_window_days');
  $since = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
  $count = (int) $wpdb->get_var($wpdb->prepare(
    'SELECT COUNT(*) FROM ' . db_photo_log_table() . " l
     JOIN {$wpdb->posts} p ON p.ID = l.post_id
     WHERE l.species_id = %d AND l.submitted_at > %s AND p.post_status IN ('pending', 'publish')",
    $species_id, $since
  ));
  return ['count' => $count, 'cap' => $cap, 'days' => $days, 'reached' => $count >= $cap];
}

/** What the photographer is told when a species is at its cap. */
function db_species_cap_message(array $cap) {
  return sprintf(
    'This species already has %d %s in the gallery this month. You can still send yours; the committee will decide.',
    $cap['count'], $cap['count'] === 1 ? 'photo' : 'photos'
  );
}

/**
 * Log rows for submissions made before the log existed, so they count
 * against their limits and the species cap like any other.
 */
function db_photo_log_backfill() {
  global $wpdb;
  $table = db_photo_log_table();
  $posts = get_posts([
    'post_type'   => 'db_gallery_photo',
    'post_status' => 'any',
    'numberposts' => -1,
    'meta_key'    => '_db_submitter_email',
  ]);
  foreach ($posts as $p) {
    if ($wpdb->get_var($wpdb->prepare("SELECT 1 FROM $table WHERE post_id = %d", $p->ID))) continue;
    $email = db_normalise_email(get_post_meta($p->ID, '_db_submitter_email', true));
    if (!is_email($email)) continue;
    $local = get_post_meta($p->ID, '_db_submitted_at', true) ?: $p->post_date;
    $wpdb->insert($table, [
      'post_id'      => $p->ID,
      'email'        => $email,
      'species_id'   => (int) get_post_meta($p->ID, '_db_species_id', true),
      'is_member'    => db_is_member_email($email) ? 1 : 0,
      'submitted_at' => get_gmt_from_date($local),
    ]);
  }
}

/* -----------------------------------------------------------------------
 * The species cap, asked as soon as a species is picked
 * -------------------------------------------------------------------- */

add_action('rest_api_init', function() {
  register_rest_route('db/v1', '/species-cap', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'args'                => ['id' => ['required' => true, 'type' => 'integer']],
    'callback'            => function(WP_REST_Request $request) {
      $species = db_species_get((int) $request->get_param('id'));
      if (!$species || !(int) $species->active) return new WP_Error('not_found', 'No such species.', ['status' => 404]);
      $cap = db_species_cap_status((int) $species->id);
      return db_rest_no_cache(rest_ensure_response([
        'reached' => $cap['reached'],
        'message' => $cap['reached'] ? db_species_cap_message($cap) : '',
      ]));
    },
  ]);
});

/* -----------------------------------------------------------------------
 * The form
 * -------------------------------------------------------------------- */

add_action('wp_enqueue_scripts', function() {
  if (!is_page('gallery')) return;
  $path = get_template_directory() . '/assets/js/photo-submit.js';
  wp_enqueue_script('db-photo-submit', get_template_directory_uri() . '/assets/js/photo-submit.js', ['db-main'], (string) @filemtime($path), true);
  wp_localize_script('db-photo-submit', 'DB_PHOTO', [
    'max_bytes' => db_photo_max_bytes(),
    'max_mb'    => db_gallery_setting('max_upload_mb'),
  ]);
});

/** Reply to the form and stop. */
function db_photo_reply($ok, $message, array $extra = []) {
  wp_send_json(['success' => $ok, 'message' => $message] + $extra);
}

add_action('wp_ajax_nopriv_db_photo_submit', 'db_handle_photo_submit');
add_action('wp_ajax_db_photo_submit', 'db_handle_photo_submit');
function db_handle_photo_submit() {
  global $wpdb;
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'db_contact_nonce')) {
    db_photo_reply(false, 'Security check failed. Please reload the page and try again.');
  }
  // Honeypot: a field hidden from people, filled in only by bots.
  if (!empty($_POST['website'])) db_photo_reply(true, '');
  if (db_rate_limited('photo', DB_PHOTO_MAX_PER_HOUR)) {
    db_photo_reply(false, 'That is a lot of submissions in a short time. Please try again in an hour.');
  }

  $name       = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
  $email      = db_normalise_email(sanitize_email(wp_unslash($_POST['email'] ?? '')));
  $species_id = (int) ($_POST['species_id'] ?? 0);
  $location   = sanitize_text_field(wp_unslash($_POST['location'] ?? ''));

  if ($name === '' || $email === '' || $location === '') db_photo_reply(false, 'Please fill in every field.');
  if (!is_email($email)) db_photo_reply(false, 'That email address does not look right.');
  $species = $species_id ? db_species_get($species_id) : null;
  if (!$species || !(int) $species->active) db_photo_reply(false, 'Please pick the species from the list as you type.');
  if (empty($_POST['no_nest'])) {
    db_photo_reply(false, 'Please confirm this is not a nest photograph and the bird was not disturbed.');
  }
  if (empty($_POST['consent'])) {
    db_photo_reply(false, 'Please confirm the photograph is yours and that we may show it with your credit.');
  }

  // The file: present, small enough, and really a JPG, PNG or WebP.
  $max  = db_photo_max_bytes();
  $mb   = db_gallery_setting('max_upload_mb');
  $file = $_FILES['photo'] ?? null;
  $err  = $file['error'] ?? UPLOAD_ERR_NO_FILE;
  if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($err === UPLOAD_ERR_OK && $file['size'] > $max)) {
    db_photo_reply(false, sprintf('That photograph is over %d MB. Please resize it and try again.', $mb));
  }
  if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    db_photo_reply(false, 'Please attach a photograph (JPG, PNG or WebP).');
  }
  $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], DB_PHOTO_TYPES);
  $size  = @getimagesize($file['tmp_name']);
  if (empty($check['type']) || !in_array($check['type'], DB_PHOTO_TYPES, true)
      || !$size || !in_array($size[2], DB_PHOTO_IMAGETYPES, true)) {
    db_photo_reply(false, 'Please send a JPG, PNG or WebP photograph.');
  }

  // Two submissions from one address at the same moment must not both
  // slip under the limit, so the count and the log row are taken inside
  // a lock on the address.
  $lock = 'db_photo_' . md5($email);
  $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock));
  $release = fn() => $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));

  $status = db_photo_limit_status($email);
  if ($status['used'] >= $status['limit']) {
    $release();
    db_photo_reply(false, $status['next']
      ? sprintf(
          'This address has already sent %d %s in the last %d days, the most we can accept in that time. You can send another from %s.',
          $status['used'], $status['used'] === 1 ? 'photograph' : 'photographs', $status['days'],
          wp_date('j F Y, g:i a', $status['next'])
        )
      : 'We are not able to accept photographs from this address.',
      ['limit_reached' => true]
    );
  }
  $cap = db_species_cap_status((int) $species->id);

  $post_id = wp_insert_post([
    'post_type'   => 'db_gallery_photo',
    'post_title'  => $species->common_name . ' — ' . $location,
    'post_status' => 'pending',
  ], true);
  if (is_wp_error($post_id)) {
    $release();
    db_photo_reply(false, 'Something went wrong saving your photograph. Please try again later.');
  }
  // Resized, recompressed and stripped of its location before it is
  // stored; the upload itself is never kept (inc/gallery/images.php).
  $attachment_id = db_photo_store($file['tmp_name'], $post_id, $species->common_name);
  if (is_wp_error($attachment_id)) {
    wp_delete_post($post_id, true);
    $release();
    db_photo_reply(false, 'That photograph could not be read. Please try another.');
  }

  $now_local = current_time('mysql');
  update_field('field_gallery_photo', $attachment_id, $post_id);
  update_field('field_gallery_species_name', $species->common_name, $post_id);
  update_field('field_gallery_scientific_name', $species->scientific_name, $post_id);
  update_field('field_gallery_photo_location', $location, $post_id);
  update_field('field_gallery_photographer', $name, $post_id);
  // Kept out of the ACF fields: for the review and for replying.
  update_post_meta($post_id, '_db_submitter_email', $email);
  update_post_meta($post_id, '_db_submitted_at', $now_local);
  update_post_meta($post_id, '_db_species_id', (int) $species->id);
  update_post_meta($post_id, '_db_is_member', $status['member'] ? 1 : 0);
  update_post_meta($post_id, '_db_confirmed', ['no_nest' => $now_local, 'consent' => $now_local]);
  $flags = $cap['reached'] ? ['species_cap' => sprintf('%s already had %d of %d photos in the last %d days', $species->common_name, $cap['count'], $cap['cap'], $cap['days'])] : [];
  update_post_meta($post_id, '_db_flags', $flags);

  $wpdb->insert(db_photo_log_table(), [
    'post_id'             => $post_id,
    'email'               => $email,
    'species_id'          => (int) $species->id,
    'is_member'           => $status['member'] ? 1 : 0,
    'species_cap_reached' => $cap['reached'] ? 1 : 0,
    'submitted_at'        => current_time('mysql', true),
  ]);
  $release();

  db_photo_notify_reviewers($post_id);
  wp_mail(
    $email,
    'We received your photograph — Deccan Birders',
    '<p>Hi ' . esc_html($name) . ',</p><p>Thank you for sending us your photograph of the '
    . esc_html($species->common_name) . '. A committee member will review it, usually within a week, and you will hear back either way.</p>'
    . '<p>— Deccan Birders</p>',
    ['Content-Type: text/html; charset=UTF-8']
  );

  db_photo_reply(true, 'Thank you — your photograph has been sent for review. You will hear back within about a week.', [
    'species_note' => $cap['reached'] ? db_species_cap_message($cap) : '',
  ]);
}

/**
 * The reviewers' email for a new submission. Phase 4 replaces this with
 * the full version (photo inline, approve / reject buttons); for now it
 * carries the same facts with a link to the entry in wp-admin.
 */
function db_photo_notify_reviewers($post_id) {
  $email   = get_post_meta($post_id, '_db_submitter_email', true);
  $name    = get_field('photographer', $post_id);
  $species = get_field('species_name', $post_id) . ' (' . get_field('scientific_name', $post_id) . ')';
  $flags   = (array) get_post_meta($post_id, '_db_flags', true);
  $to      = wp_list_pluck(db_photo_reviewers(), 'user_email') ?: db_notify_email('photos');

  $rows = [
    'Photographer'   => $name,
    'Email'          => $email,
    'Member'         => get_post_meta($post_id, '_db_is_member', true) ? 'Yes — on the Member Emails list' : 'No',
    'Species'        => $species,
    'Where and when' => get_field('photo_location', $post_id),
  ];
  $body = ($flags ? '<p style="background:#FFF4D6;padding:10px 12px;border-radius:6px"><strong>Flagged:</strong> ' . esc_html(implode('; ', $flags)) . '</p>' : '')
    . '<table cellpadding="4">';
  foreach ($rows as $k => $v) $body .= '<tr><td><strong>' . esc_html($k) . '</strong></td><td>' . esc_html($v) . '</td></tr>';
  $body .= '</table><p><a href="' . esc_url(admin_url('post.php?post=' . $post_id . '&action=edit')) . '">Review this submission</a></p>';

  wp_mail(
    $to,
    ($flags ? '[Flagged] ' : '') . 'Photograph submitted: ' . get_field('species_name', $post_id),
    $body,
    ['Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>']
  );
}
