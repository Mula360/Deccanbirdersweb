<?php
/**
 * The review queue in wp-admin (Photo review): every submission by
 * status — pending, published, rejected, archived — with the decisions
 * that make sense for each, at any stage:
 *
 *   pending   → approve, reject (with a reason)
 *   published → remove from the gallery (archived, not deleted), reject
 *   rejected  → approve after all
 *   archived  → restore to the gallery
 *
 * It is its own menu item, not a page under Gallery, because the Photo
 * reviewer role cannot open WordPress's post screens; anyone with the
 * reviewer permission can use this.
 */

if (!defined('ABSPATH')) exit;

/** Off the site but kept: removed early by a reviewer, or expired. */
add_action('init', function() {
  register_post_status('db_archived', [
    'label'                     => 'Archived',
    'public'                    => false,
    'internal'                  => false,
    'protected'                 => true,
    'show_in_admin_all_list'    => true,
    'show_in_admin_status_list' => true,
    'label_count'               => _n_noop('Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>'),
  ]);
});

/** $why: 'removed' by a reviewer, or 'expired'. */
function db_photo_archive($post_id, $user_id, $why) {
  update_post_meta($post_id, '_db_archived', ['why' => $why, 'user' => $user_id, 'at' => current_time('mysql')]);
  wp_update_post(['ID' => $post_id, 'post_status' => 'db_archived']);
}

/** Back into the gallery; the expiry counts afresh from today. */
function db_photo_restore($post_id, $user_id) {
  delete_post_meta($post_id, '_db_archived');
  update_post_meta($post_id, '_db_approved_at', current_time('mysql'));
  update_post_meta($post_id, '_db_decision', ['action' => 'restored', 'user' => $user_id, 'at' => current_time('mysql')]);
  wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
}

/** When a published photo leaves the gallery (Phase 6 enforces it). */
function db_photo_expires_at($post_id) {
  $approved = get_post_meta($post_id, '_db_approved_at', true);
  return $approved ? strtotime($approved) + db_gallery_setting('expiry_days') * DAY_IN_SECONDS : null;
}

function db_queue_url(array $args = []) {
  return add_query_arg($args, admin_url('admin.php?page=db-review'));
}

/** Which actions each status allows. */
const DB_QUEUE_ACTIONS = [
  'pending'     => ['approve', 'reject'],
  'publish'     => ['remove', 'reject'],
  'db_rejected' => ['approve'],
  'db_archived' => ['restore'],
];

add_action('admin_menu', function() {
  $pending = (int) wp_count_posts('db_gallery_photo')->pending;
  add_menu_page(
    'Photo review',
    'Photo review' . ($pending ? ' <span class="awaiting-mod count-' . $pending . '"><span class="pending-count">' . $pending . '</span></span>' : ''),
    DB_REVIEW_CAP,
    'db-review',
    'db_queue_page',
    'dashicons-camera-alt',
    26
  );
});

/** Carry out one action on one submission. Returns a message, or a WP_Error. */
function db_queue_act($post_id, $action, $reason = '', $note = '') {
  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'db_gallery_photo') return new WP_Error('gone', 'That submission no longer exists.');
  if (!in_array($action, DB_QUEUE_ACTIONS[$post->post_status] ?? [], true)) {
    return new WP_Error('state', sprintf('"%s" was already %s, so it was left as it is.', get_the_title($post), db_photo_decision_text($post_id)));
  }
  $me = get_current_user_id();
  $title = get_the_title($post);
  switch ($action) {
    case 'approve':
      db_photo_approve($post_id, $me);
      return "Approved: $title. It is in the gallery and the photographer has been told.";
    case 'reject':
      if (!in_array($reason, db_gallery_setting('reject_reasons'), true)) return new WP_Error('reason', 'Pick a reason for rejecting.');
      db_photo_reject($post_id, $me, $reason, $note);
      return "Rejected: $title. The photographer has been told why.";
    case 'remove':
      db_photo_archive($post_id, $me, 'removed');
      return "Removed from the gallery: $title. It is kept under Archived.";
    case 'restore':
      db_photo_restore($post_id, $me);
      return "Restored to the gallery: $title.";
  }
  return new WP_Error('action', 'Unknown action.');
}

function db_queue_handle_post() {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !current_user_can(DB_REVIEW_CAP)) return [];
  check_admin_referer('db_queue');
  $notices = [];

  if (!empty($_POST['bulk_approve'])) {
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    if (!$ids) return [['error', 'Tick the photos to approve first.']];
    foreach ($ids as $id) {
      $r = db_queue_act($id, 'approve');
      $notices[] = is_wp_error($r) ? ['error', $r->get_error_message()] : ['success', $r];
    }
  } elseif (!empty($_POST['act'])) {
    [$action, $id] = array_pad(explode(':', sanitize_text_field(wp_unslash($_POST['act'])), 2), 2, 0);
    $reason = sanitize_text_field(wp_unslash($_POST['reason'][$id] ?? ''));
    $note   = sanitize_textarea_field(wp_unslash($_POST['note'][$id] ?? ''));
    $r = db_queue_act((int) $id, sanitize_key($action), $reason, $note);
    $notices[] = is_wp_error($r) ? ['error', $r->get_error_message()] : ['success', $r];
  }
  if ($notices && class_exists('LiteSpeed\Purge')) LiteSpeed\Purge::purge_all();
  return $notices;
}

function db_queue_page() {
  if (!current_user_can(DB_REVIEW_CAP)) return;
  $notices = db_queue_handle_post();

  $tabs = ['pending' => 'Pending', 'publish' => 'Published', 'db_rejected' => 'Rejected', 'db_archived' => 'Archived'];
  $status = sanitize_key($_GET['status'] ?? 'pending');
  if (!isset($tabs[$status])) $status = 'pending';
  $counts = wp_count_posts('db_gallery_photo');
  $paged  = max(1, (int) ($_GET['paged'] ?? 1));
  $order  = [
    'pending'     => ['orderby' => 'date', 'order' => 'ASC'], // oldest waiting first
    'publish'     => ['orderby' => 'date', 'order' => 'DESC'],
    'db_rejected' => ['orderby' => 'modified', 'order' => 'DESC'],
    'db_archived' => ['orderby' => 'modified', 'order' => 'DESC'],
  ][$status];
  $q = new WP_Query(['post_type' => 'db_gallery_photo', 'post_status' => $status, 'posts_per_page' => 20, 'paged' => $paged] + $order);
  $reasons = db_gallery_setting('reject_reasons');
  ?>
  <div class="wrap db-queue">
    <h1>Photo review</h1>
    <?php foreach ($notices as [$kind, $msg]): ?>
      <div class="notice notice-<?php echo esc_attr($kind); ?> is-dismissible"><p><?php echo esc_html($msg); ?></p></div>
    <?php endforeach; ?>

    <nav class="nav-tab-wrapper">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="nav-tab<?php echo $key === $status ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(db_queue_url(['status' => $key])); ?>">
          <?php echo esc_html($label); ?> <span class="count">(<?php echo (int) ($counts->$key ?? 0); ?>)</span></a>
      <?php endforeach; ?>
    </nav>

    <?php if (!$q->have_posts()): ?>
      <p style="margin-top:20px"><?php echo esc_html(['pending' => 'Nothing waiting for review.', 'publish' => 'No photos in the gallery.', 'db_rejected' => 'No rejected photos.', 'db_archived' => 'Nothing archived.'][$status]); ?></p>
    </div><?php return; endif; ?>

    <form method="post">
      <?php wp_nonce_field('db_queue'); ?>
      <?php if ($status === 'pending'): ?>
        <p style="margin:14px 0"><button class="button" name="bulk_approve" value="1" onclick="return document.querySelector('.db-q-id:checked') ? confirm('Approve every ticked photo and publish them?') : false">Approve ticked photos</button></p>
      <?php endif; ?>

      <div class="db-q-list">
      <?php foreach ($q->posts as $p):
        $id    = $p->ID;
        $facts = db_photo_facts($id);
        $flags = db_photo_flags($id);
        $att   = (int) get_post_meta($id, 'photo', true);
        $full  = $att ? wp_get_attachment_image_url($att, 'large') : '';
        $d     = get_post_meta($id, '_db_decision', true);
        $arch  = get_post_meta($id, '_db_archived', true);
        $exp   = $status === 'publish' ? db_photo_expires_at($id) : null; ?>
        <div class="db-q-item" id="photo-<?php echo (int) $id; ?>">
          <div class="db-q-thumb">
            <?php if ($status === 'pending'): ?><label class="db-q-tick"><input type="checkbox" class="db-q-id" name="ids[]" value="<?php echo (int) $id; ?>"> Select</label><?php endif; ?>
            <?php if ($full): ?><a href="<?php echo esc_url($full); ?>" target="_blank" rel="noopener"><?php echo wp_get_attachment_image($att, 'medium', false, ['alt' => $facts['Species']]); ?></a>
            <?php else: ?><div class="db-q-nophoto">No photo</div><?php endif; ?>
          </div>
          <div class="db-q-body">
            <h2 class="db-q-title"><?php echo esc_html($facts['Species']); ?></h2>
            <?php if ($flags): ?><div class="db-q-flags"><strong>Flagged:</strong> <?php echo esc_html(implode('; ', $flags)); ?></div><?php endif; ?>
            <table class="db-q-facts">
              <?php foreach ($facts as $k => $v): if ($k === 'Species' || $v === '') continue; ?>
                <tr><th><?php echo esc_html($k); ?></th><td><?php echo esc_html($v); ?></td></tr>
              <?php endforeach; ?>
              <?php if ($status !== 'pending'): ?><tr><th>Decision</th><td><?php echo esc_html(ucfirst(db_photo_decision_text($id))); ?>
                <?php if (is_array($d) && !empty($d['reason'])): ?><br><em><?php echo esc_html($d['reason']); ?></em><?php endif; ?></td></tr><?php endif; ?>
              <?php if ($exp): ?><tr><th>Leaves the gallery</th><td><?php echo esc_html(wp_date('j F Y', $exp)); ?>
                (<?php echo esc_html(max(0, (int) ceil(($exp - current_time('timestamp')) / DAY_IN_SECONDS))); ?> days)</td></tr><?php endif; ?>
              <?php if (is_array($arch)): ?><tr><th>Archived</th><td><?php echo esc_html(($arch['why'] === 'expired' ? 'Expired' : 'Removed by ' . (get_userdata((int) $arch['user'])->display_name ?? 'a reviewer')) . ' on ' . mysql2date('j F Y', $arch['at'])); ?></td></tr><?php endif; ?>
            </table>

            <div class="db-q-actions">
              <?php foreach (DB_QUEUE_ACTIONS[$status] as $a):
                if ($a === 'reject') continue; ?>
                <button class="button <?php echo in_array($a, ['approve', 'restore'], true) ? 'button-primary' : ''; ?>" name="act" value="<?php echo esc_attr($a . ':' . $id); ?>"
                  <?php if ($a === 'remove'): ?>onclick="return confirm('Take this photo out of the gallery? It is kept under Archived and can be restored.')"<?php endif; ?>>
                  <?php echo esc_html(['approve' => $status === 'db_rejected' ? 'Approve after all' : 'Approve', 'remove' => 'Remove from gallery', 'restore' => 'Restore to gallery'][$a]); ?></button>
              <?php endforeach; ?>
              <?php if (in_array('reject', DB_QUEUE_ACTIONS[$status], true)): ?>
                <details class="db-q-reject">
                  <summary class="button">Reject…</summary>
                  <div class="db-q-reject-box">
                    <label>Reason (sent to the photographer)<br>
                      <select name="reason[<?php echo (int) $id; ?>]">
                        <option value="">Choose a reason…</option>
                        <?php foreach ($reasons as $r): ?><option><?php echo esc_html($r); ?></option><?php endforeach; ?>
                      </select></label>
                    <label>A note for them (optional)<br>
                      <textarea name="note[<?php echo (int) $id; ?>]" rows="2" class="large-text"></textarea></label>
                    <button class="button button-link-delete" name="act" value="<?php echo esc_attr('reject:' . $id); ?>">Reject<?php echo $status === 'publish' ? ' and take it down' : ''; ?></button>
                  </div>
                </details>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </form>

    <?php if ($q->max_num_pages > 1): ?>
      <p>
        <?php if ($paged > 1): ?><a class="button" href="<?php echo esc_url(db_queue_url(['status' => $status, 'paged' => $paged - 1])); ?>">‹ Previous</a><?php endif; ?>
        Page <?php echo (int) $paged; ?> of <?php echo (int) $q->max_num_pages; ?>
        <?php if ($paged < $q->max_num_pages): ?><a class="button" href="<?php echo esc_url(db_queue_url(['status' => $status, 'paged' => $paged + 1])); ?>">Next ›</a><?php endif; ?>
      </p>
    <?php endif; ?>
  </div>

  <style>
    .db-q-list { display: grid; gap: 14px; margin-top: 16px; max-width: 1100px; }
    .db-q-item { display: grid; grid-template-columns: 260px 1fr; gap: 20px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px; }
    @media (max-width: 782px) { .db-q-item { grid-template-columns: 1fr; } }
    .db-q-thumb img { width: 100%; height: auto; border-radius: 6px; display: block; }
    .db-q-tick { display: block; margin-bottom: 6px; }
    .db-q-nophoto { background: #f0f0f1; border-radius: 6px; padding: 60px 0; text-align: center; color: #646970; }
    .db-q-title { margin: 0 0 8px; font-size: 18px; }
    .db-q-flags { background: #fcf0d0; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; }
    .db-q-facts th { text-align: left; font-weight: 400; color: #646970; padding: 3px 14px 3px 0; vertical-align: top; white-space: nowrap; }
    .db-q-facts td { padding: 3px 0; overflow-wrap: anywhere; }
    .db-q-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start; margin-top: 12px; }
    .db-q-reject summary { list-style: none; }
    .db-q-reject summary::-webkit-details-marker { display: none; }
    .db-q-reject-box { margin-top: 8px; padding: 10px; background: #f6f7f7; border-radius: 6px; display: grid; gap: 8px; min-width: 280px; }
  </style>
  <?php
}
