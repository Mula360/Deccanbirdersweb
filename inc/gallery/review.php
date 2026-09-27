<?php
/**
 * Reviewing a submission from the email.
 *
 * Every reviewer gets their own email, with the photograph embedded and
 * Approve / Reject buttons. Each button is a link carrying a token that
 * belongs to that reviewer and that submission only; it expires after
 * the configured number of days and works once. Only a hash of it is
 * stored (keyed with the site's secret salt), so the database alone
 * cannot produce a working link.
 *
 * A link never acts by being opened: mail scanners (Gmail, Outlook Safe
 * Links) open links to check them, and would approve everything. It opens
 * a page showing the photo with a button to confirm, or a reason to pick
 * for rejecting. Nothing is published until someone confirms.
 */

if (!defined('ABSPATH')) exit;

function db_review_tokens_table() {
  global $wpdb;
  return $wpdb->prefix . 'db_review_tokens';
}

/** Rejected submissions: kept, off the site, out of the species cap. */
add_action('init', function() {
  register_post_status('db_rejected', [
    'label'                     => 'Rejected',
    'public'                    => false,
    'internal'                  => false,
    'protected'                 => true,
    'show_in_admin_all_list'    => true,
    'show_in_admin_status_list' => true,
    'label_count'               => _n_noop('Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>'),
  ]);
});

/* -----------------------------------------------------------------------
 * Tokens
 * -------------------------------------------------------------------- */

function db_review_hash($raw) {
  return hash_hmac('sha256', $raw, wp_salt('auth'));
}

/** A new link token for one reviewer and one submission. Returns the raw token. */
function db_review_token_create($post_id, $user_id) {
  global $wpdb;
  $raw = bin2hex(random_bytes(32));
  $now = current_time('timestamp', true);
  $wpdb->insert(db_review_tokens_table(), [
    'token_hash' => db_review_hash($raw),
    'post_id'    => $post_id,
    'user_id'    => $user_id,
    'created_at' => gmdate('Y-m-d H:i:s', $now),
    'expires_at' => gmdate('Y-m-d H:i:s', $now + db_gallery_setting('link_expiry_days') * DAY_IN_SECONDS),
  ]);
  return $raw;
}

function db_review_token_find($raw) {
  global $wpdb;
  if (!is_string($raw) || !preg_match('/^[a-f0-9]{64}$/', $raw)) return null;
  return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . db_review_tokens_table() . ' WHERE token_hash = %s', db_review_hash($raw)));
}

/** Use a token up. False when it was already used, so each link acts once. */
function db_review_token_use($row, $action) {
  global $wpdb;
  return (bool) $wpdb->query($wpdb->prepare(
    'UPDATE ' . db_review_tokens_table() . ' SET used_at = %s, used_action = %s WHERE id = %d AND used_at IS NULL',
    current_time('mysql', true), $action, $row->id
  ));
}

function db_review_url($raw, $do) {
  return add_query_arg(['db_review' => $raw, 'do' => $do], home_url('/'));
}

/* -----------------------------------------------------------------------
 * Decisions
 * -------------------------------------------------------------------- */

function db_photo_approve($post_id, $user_id) {
  update_post_meta($post_id, '_db_decision', ['action' => 'approved', 'user' => $user_id, 'at' => current_time('mysql')]);
  wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
}

function db_photo_reject($post_id, $user_id, $reason, $note = '') {
  update_post_meta($post_id, '_db_decision', ['action' => 'rejected', 'user' => $user_id, 'at' => current_time('mysql'), 'reason' => $reason, 'note' => $note]);
  wp_update_post(['ID' => $post_id, 'post_status' => 'db_rejected']);
}

/** "approved by Anita on 3 October 2026, 9:12 am", for a decided submission. */
function db_photo_decision_text($post_id) {
  $d = get_post_meta($post_id, '_db_decision', true);
  $status = get_post_status($post_id);
  $what = ['publish' => 'approved', 'db_rejected' => 'rejected', 'trash' => 'removed', 'db_archived' => 'approved (now archived)'][$status] ?? $status;
  if (!is_array($d)) return $what;
  $who = ($u = get_userdata((int) $d['user'])) ? $u->display_name : 'a reviewer';
  return sprintf('%s by %s on %s', $what, $who, mysql2date('j F Y, g:i a', $d['at']));
}

/**
 * The photographer hears about the decision, however it was made — from
 * the email, or by publishing in wp-admin. Publishing from anywhere also
 * stamps the approval date, which the 90-day expiry counts from.
 */
add_action('transition_post_status', function($new_status, $old_status, $post) {
  if ($post->post_type !== 'db_gallery_photo' || $new_status === $old_status) return;

  if ($new_status === 'publish') {
    if (!get_post_meta($post->ID, '_db_approved_at', true)) update_post_meta($post->ID, '_db_approved_at', current_time('mysql'));
    if (!is_array(get_post_meta($post->ID, '_db_decision', true))) {
      update_post_meta($post->ID, '_db_decision', ['action' => 'approved', 'user' => get_current_user_id(), 'at' => current_time('mysql')]);
    }
  }

  $email = get_post_meta($post->ID, '_db_submitter_email', true);
  if (!$email || !is_email($email)) return;
  $name    = get_field('photographer', $post->ID) ?: 'there';
  $species = get_field('species_name', $post->ID) ?: 'bird';
  $headers = ['Content-Type: text/html; charset=UTF-8'];

  if ($new_status === 'publish' && !get_post_meta($post->ID, '_db_published_notified', true)) {
    update_post_meta($post->ID, '_db_published_notified', 1);
    db_mail($email, 'Your photograph is in the gallery — Deccan Birders',
      '<p>Hi ' . esc_html($name) . ',</p><p>Your photograph of the ' . esc_html($species) . ' is now in the Deccan Birders gallery, credited to you: '
      . '<a href="' . esc_url(home_url('/gallery/')) . '">see it here</a>.</p><p>Thank you for sharing it.</p><p>— Deccan Birders</p>',
      $headers, 'decision');
  }

  if ($new_status === 'db_rejected') {
    $d = (array) get_post_meta($post->ID, '_db_decision', true);
    db_mail($email, 'About the photograph you sent — Deccan Birders',
      '<p>Hi ' . esc_html($name) . ',</p><p>Thank you for sending us your photograph of the ' . esc_html($species) . '. '
      . 'On this occasion the committee has not taken it for the gallery.</p>'
      . (!empty($d['reason']) ? '<p><strong>Reason:</strong> ' . esc_html($d['reason']) . '</p>' : '')
      . (!empty($d['note']) ? '<p>' . nl2br(esc_html($d['note'])) . '</p>' : '')
      . '<p>Please do keep sending them — we would like to see more.</p><p>— Deccan Birders</p>',
      $headers, 'decision');
  }
}, 10, 3);

/* -----------------------------------------------------------------------
 * The reviewers' email
 * -------------------------------------------------------------------- */

/** Things that need a reviewer's attention (they put [Flagged] in the subject). */
function db_photo_flags($post_id) {
  $flags = array_values((array) get_post_meta($post_id, '_db_flags', true));
  $img = get_post_meta($post_id, '_db_image', true);
  if (is_array($img)) {
    if (max($img['after']['width'], $img['after']['height']) < 1200) {
      $flags[] = sprintf('Small image: %d × %d pixels', $img['after']['width'], $img['after']['height']);
    }
  }
  return $flags;
}

/** The facts about a submission, label => value, for the email and the review page. */
function db_photo_facts($post_id) {
  $submitted = get_post_meta($post_id, '_db_submitted_at', true);
  return [
    'Photographer'   => get_field('photographer', $post_id),
    'Email'          => get_post_meta($post_id, '_db_submitter_email', true),
    'Member'         => get_post_meta($post_id, '_db_is_member', true) ? 'Yes (on the Member Emails list)' : 'No',
    'Species'        => get_field('species_name', $post_id) . ' (' . get_field('scientific_name', $post_id) . ')',
    'Where and when' => get_field('photo_location', $post_id),
    'Submitted'      => $submitted ? mysql2date('j F Y, g:i a', $submitted) : '',
  ] + (is_array($img = get_post_meta($post_id, '_db_image', true)) ? [
    'Location data'  => !empty($img['had_location']) ? 'Removed from the original; not stored' : 'None in the original',
  ] : []);
}

/** A file of the photo small enough to embed: the 768px size if there is one. */
function db_photo_email_image($post_id) {
  $att = (int) get_post_meta($post_id, 'photo', true);
  if (!$att) return '';
  $meta = wp_get_attachment_metadata($att);
  $full = get_attached_file($att);
  if (!empty($meta['sizes']['medium_large']['file'])) {
    $sized = path_join(dirname($full), $meta['sizes']['medium_large']['file']);
    if (is_readable($sized)) return $sized;
  }
  return is_readable($full) ? $full : '';
}

function db_email_button($href, $label, $colour) {
  return '<a href="' . esc_url($href) . '" style="display:inline-block;padding:12px 26px;border-radius:999px;background:' . $colour
    . ';color:#ffffff;font-weight:600;font-size:16px;text-decoration:none;font-family:Arial,sans-serif">' . esc_html($label) . '</a>';
}

/** Email every reviewer about a new submission: one email each, their own links. */
function db_photo_notify_reviewers($post_id) {
  $flags   = db_photo_flags($post_id);
  $facts   = db_photo_facts($post_id);
  $image   = db_photo_email_image($post_id);
  $cid     = 'db-photo-' . $post_id;
  $days    = db_gallery_setting('link_expiry_days');
  $subject = ($flags ? '[Flagged] ' : '') . 'Photograph to review: ' . get_field('species_name', $post_id);
  $reply   = 'Reply-To: ' . $facts['Photographer'] . ' <' . $facts['Email'] . '>';

  $reviewers = db_photo_reviewers();
  foreach ($reviewers as $user) {
    $token = db_review_token_create($post_id, $user->ID);
    $html  = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#16241D;max-width:640px">'
      . '<p>Hi ' . esc_html($user->display_name) . ', a photograph is waiting for review.</p>'
      . ($flags ? '<div style="background:#FFF4D6;border-radius:8px;padding:10px 14px;margin:12px 0"><strong>Flagged</strong><ul style="margin:6px 0 0;padding-left:20px">'
          . implode('', array_map(fn($f) => '<li>' . esc_html($f) . '</li>', $flags)) . '</ul></div>' : '')
      . ($image ? '<p><img src="cid:' . esc_attr($cid) . '" alt="' . esc_attr($facts['Species']) . '" style="max-width:100%;height:auto;border-radius:8px;display:block"></p>' : '')
      . '<table cellpadding="5" style="border-collapse:collapse;margin:8px 0 18px">';
    foreach ($facts as $k => $v) {
      $html .= '<tr><td style="color:#5C6B63;padding-right:14px;vertical-align:top">' . esc_html($k) . '</td><td><strong>' . esc_html($v) . '</strong></td></tr>';
    }
    $html .= '</table>'
      . '<p>' . db_email_button(db_review_url($token, 'approve'), 'Approve', '#17924C') . ' &nbsp; '
      . db_email_button(db_review_url($token, 'reject'), 'Reject', '#B3261E') . '</p>'
      . '<p style="font-size:13px;color:#5C6B63">Each button opens a page to confirm — nothing happens until you do. These links are yours alone, work once, and expire in '
      . (int) $days . ' days. You can also <a href="' . esc_url(admin_url('post.php?post=' . $post_id . '&action=edit')) . '">review it in wp-admin</a>.</p>'
      . '</div>';

    db_mail_with_images(
      $user->user_email, $subject, $html,
      ['Content-Type: text/html; charset=UTF-8', $reply],
      $image ? [[$image, $cid, basename($image)]] : [],
      'review'
    );
  }
  return count($reviewers);
}

/* -----------------------------------------------------------------------
 * The page a link opens
 * -------------------------------------------------------------------- */

add_action('template_redirect', function() {
  if (!isset($_GET['db_review'])) return;

  // Never cached, never indexed: every visit is one person's decision.
  if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
  do_action('litespeed_control_set_nocache', 'photo review link');
  nocache_headers();
  header('X-Robots-Tag: noindex, nofollow');
  header('Referrer-Policy: no-referrer');

  $raw  = sanitize_text_field(wp_unslash($_GET['db_review']));
  $row  = db_review_token_find($raw);
  $post = $row ? get_post((int) $row->post_id) : null;
  $user = $row ? get_userdata((int) $row->user_id) : null;
  $state = ['kind' => 'form'];

  if (!$row || !$post || $post->post_type !== 'db_gallery_photo' || !$user || !user_can($user, DB_REVIEW_CAP)) {
    $state = ['kind' => 'message', 'title' => 'This link is not valid', 'text' => 'It may have been copied incompletely. Open the submission from wp-admin instead.'];
  } elseif ($row->used_at) {
    $state = ['kind' => 'message', 'title' => 'This link has already been used', 'text' => 'The submission was ' . db_photo_decision_text($post->ID) . '. Each link works once.'];
  } elseif ($post->post_status !== 'pending') {
    $state = ['kind' => 'message', 'title' => 'Already decided', 'text' => 'This submission was ' . db_photo_decision_text($post->ID) . '.'];
  } elseif (strtotime($row->expires_at . ' UTC') < time()) {
    $state = ['kind' => 'message', 'title' => 'This link has expired', 'text' => 'Review links last ' . db_gallery_setting('link_expiry_days') . ' days; this one expired on '
      . wp_date('j F Y, g:i a', strtotime($row->expires_at . ' UTC')) . '. The submission is still waiting: review it in wp-admin.'];
  } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_key($_POST['db_action'] ?? '');
    if (!wp_verify_nonce($_POST['_db_nonce'] ?? '', 'db_review_' . $row->id) || !in_array($action, ['approve', 'reject'], true)) {
      $state = ['kind' => 'message', 'title' => 'That did not go through', 'text' => 'The page was open too long. Open the link from the email again.'];
    } else {
      $reasons = db_gallery_setting('reject_reasons');
      $reason  = sanitize_text_field(wp_unslash($_POST['reason'] ?? ''));
      $note    = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
      if ($action === 'reject' && !in_array($reason, $reasons, true)) {
        $state = ['kind' => 'form', 'error' => 'Pick a reason for rejecting.'];
      } elseif (!db_review_token_use($row, $action)) {
        $state = ['kind' => 'message', 'title' => 'This link has already been used', 'text' => 'The submission was ' . db_photo_decision_text($post->ID) . '.'];
      } else {
        // Act as the reviewer the link was sent to.
        wp_set_current_user($user->ID);
        if ($action === 'approve') db_photo_approve($post->ID, $user->ID);
        else db_photo_reject($post->ID, $user->ID, $reason, $note);
        $state = ['kind' => 'message', 'title' => $action === 'approve' ? 'Approved' : 'Rejected',
          'text' => $action === 'approve'
            ? 'The photograph is now in the gallery, and the photographer has been told.'
            : 'The photograph will not be published. The photographer has been told, with the reason.',
          'link' => $action === 'approve' ? home_url('/gallery/') : ''];
      }
    }
  }

  db_review_render($state, $row, $post, $user);
  exit;
});

function db_review_render(array $state, $row, $post, $user) {
  add_filter('wp_robots', 'wp_robots_no_robots');
  add_filter('pre_get_document_title', fn() => 'Review a photograph — Deccan Birders');
  get_header();
  $do = sanitize_key($_GET['do'] ?? 'approve');
  ?>
  <main class="db-review">
    <div class="db-review-card">
    <?php if ($state['kind'] === 'message'): ?>
      <h1 class="db-review-title"><?php echo esc_html($state['title']); ?></h1>
      <p><?php echo esc_html($state['text']); ?></p>
      <?php if (!empty($state['link'])): ?><p><a class="btn btn-primary" href="<?php echo esc_url($state['link']); ?>">See the gallery</a></p><?php endif; ?>
      <?php if ($post && $state['title'] !== 'Approved' && $state['title'] !== 'Rejected'): ?>
        <p class="db-review-small"><a href="<?php echo esc_url(admin_url('post.php?post=' . $post->ID . '&action=edit')); ?>">Open in wp-admin</a></p>
      <?php endif; ?>
    <?php else:
      $facts = db_photo_facts($post->ID);
      $flags = db_photo_flags($post->ID);
      $att   = (int) get_post_meta($post->ID, 'photo', true); ?>
      <h1 class="db-review-title">Review: <?php echo esc_html(get_field('species_name', $post->ID)); ?></h1>
      <p class="db-review-small">Signed in by link as <strong><?php echo esc_html($user->display_name); ?></strong>. This link works once.</p>
      <?php if ($flags): ?>
        <div class="db-review-flags"><strong>Flagged</strong><ul><?php foreach ($flags as $f): ?><li><?php echo esc_html($f); ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <?php if ($att) echo wp_get_attachment_image($att, 'large', false, ['class' => 'db-review-photo', 'alt' => $facts['Species']]); ?>
      <table class="db-review-facts">
        <?php foreach ($facts as $k => $v): ?><tr><th><?php echo esc_html($k); ?></th><td><?php echo esc_html($v); ?></td></tr><?php endforeach; ?>
      </table>
      <?php if (!empty($state['error'])): ?><p class="field-error" role="alert"><?php echo esc_html($state['error']); ?></p><?php endif; ?>

      <div class="db-review-actions">
        <form method="post" class="db-review-approve"<?php echo $do === 'reject' ? ' hidden' : ''; ?>>
          <?php wp_nonce_field('db_review_' . $row->id, '_db_nonce'); ?>
          <input type="hidden" name="db_action" value="approve">
          <button type="submit" class="btn btn-primary">Approve and publish</button>
          <?php if ($do !== 'reject'): ?><a href="<?php echo esc_url(add_query_arg('do', 'reject')); ?>" class="db-review-switch">Reject instead</a><?php endif; ?>
        </form>
        <form method="post" class="db-review-reject stacked-form"<?php echo $do === 'reject' ? '' : ' hidden'; ?>>
          <?php wp_nonce_field('db_review_' . $row->id, '_db_nonce'); ?>
          <input type="hidden" name="db_action" value="reject">
          <label class="stacked-field"><span class="stacked-label">Reason (sent to the photographer)</span>
            <select name="reason" required>
              <option value="">Choose a reason…</option>
              <?php foreach (db_gallery_setting('reject_reasons') as $r): ?><option><?php echo esc_html($r); ?></option><?php endforeach; ?>
            </select></label>
          <label class="stacked-field"><span class="stacked-label">A note for them (optional)</span>
            <textarea name="note" rows="3"></textarea></label>
          <div><button type="submit" class="btn btn-danger">Reject</button>
            <?php if ($do === 'reject'): ?><a href="<?php echo esc_url(add_query_arg('do', 'approve')); ?>" class="db-review-switch">Approve instead</a><?php endif; ?></div>
        </form>
      </div>
    <?php endif; ?>
    </div>
  </main>
  <?php
  get_footer();
}
