<?php
/**
 * Member Emails — the list that decides who counts as a member for the
 * photo submission limits (Gallery → Member Emails, administrators only).
 *
 * Every way in — one address, a pasted list, a CSV — goes through
 * db_member_emails_add(), so they all trim, lowercase, validate and skip
 * duplicates the same way and report the same summary.
 */

if (!defined('ABSPATH')) exit;

/** Trimmed and lowercased — the one form addresses are stored and looked up in. */
function db_normalise_email($email) {
  return strtolower(trim((string) $email, " \t\n\r\0\x0B\"'<>;,"));
}

/** Is this address on the list? The check the submission form makes. */
function db_is_member_email($email) {
  global $wpdb;
  $email = db_normalise_email($email);
  if ($email === '') return false;
  return (bool) $wpdb->get_var($wpdb->prepare(
    'SELECT 1 FROM ' . db_member_emails_table() . ' WHERE email = %s LIMIT 1', $email
  ));
}

function db_member_emails_count() {
  global $wpdb;
  return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . db_member_emails_table());
}

/**
 * Add addresses. Returns ['added' => n, 'duplicates' => n, 'invalid' => [raw, …]].
 * A duplicate is an address already on the list or repeated in this batch.
 */
function db_member_emails_add(array $raw, $source) {
  global $wpdb;
  $summary = ['added' => 0, 'duplicates' => 0, 'invalid' => []];
  $seen = [];
  $now = current_time('mysql');

  foreach ($raw as $item) {
    $original = trim((string) $item);
    if ($original === '') continue;
    $email = db_normalise_email($original);
    if (!is_email($email) || strlen($email) > 191) {
      $summary['invalid'][] = $original;
      continue;
    }
    if (isset($seen[$email])) { $summary['duplicates']++; continue; }
    $seen[$email] = true;

    // INSERT IGNORE leans on the unique key, so a race cannot double up.
    $inserted = $wpdb->query($wpdb->prepare(
      'INSERT IGNORE INTO ' . db_member_emails_table() . ' (email, source, added_at) VALUES (%s, %s, %s)',
      $email, $source, $now
    ));
    if ($inserted) $summary['added']++;
    else $summary['duplicates']++;
  }
  return $summary;
}

/** A pasted list: one per line, or separated by commas, semicolons or spaces. */
function db_member_emails_split($text) {
  return preg_split('/[\s,;]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY);
}

/**
 * The "email" column of a CSV, other columns ignored. Returns the values,
 * or a WP_Error when there is no such column.
 */
function db_member_emails_from_csv($path) {
  $fh = @fopen($path, 'r');
  if (!$fh) return new WP_Error('csv', 'The file could not be read.');
  $header = fgetcsv($fh, 0, ',', '"', '\\');
  if (!$header) { fclose($fh); return new WP_Error('csv', 'The file is empty.'); }
  $header = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
  $col = array_search('email', $header, true);
  if ($col === false) {
    fclose($fh);
    return new WP_Error('csv', 'No "email" column found. The first row should be headings, one of them "email".');
  }
  $values = [];
  while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
    if (isset($row[$col])) $values[] = $row[$col];
  }
  fclose($fh);
  return $values;
}

function db_member_emails_remove(array $ids) {
  global $wpdb;
  $ids = array_filter(array_map('intval', $ids));
  if (!$ids) return 0;
  return (int) $wpdb->query('DELETE FROM ' . db_member_emails_table() . ' WHERE id IN (' . implode(',', $ids) . ')');
}

/* -----------------------------------------------------------------------
 * Screen
 * -------------------------------------------------------------------- */

add_action('admin_menu', function() {
  add_submenu_page(
    'edit.php?post_type=db_gallery_photo',
    'Member Emails',
    'Member Emails',
    'manage_options',
    'db-member-emails',
    'db_member_emails_page'
  );
});

// Export streams a file, so it runs before any page output.
add_action('admin_post_db_member_emails_export', function() {
  if (!current_user_can('manage_options')) wp_die('Not allowed.', 403);
  check_admin_referer('db_member_emails_export');
  global $wpdb;
  $rows = $wpdb->get_results('SELECT email, source, added_at FROM ' . db_member_emails_table() . ' ORDER BY email', ARRAY_N);
  nocache_headers();
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="member-emails-' . gmdate('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['email', 'source', 'added_at'], ',', '"', '\\');
  foreach ($rows as $row) fputcsv($out, $row, ',', '"', '\\');
  fclose($out);
  exit;
});

/** Handle whichever form on the screen was sent; returns a notice to show. */
function db_member_emails_handle_post() {
  $action = sanitize_key($_POST['db_me_action'] ?? '');
  if (!$action) return null;
  check_admin_referer('db_member_emails_' . $action);

  switch ($action) {
    case 'add_one':
      return ['summary' => db_member_emails_add([wp_unslash($_POST['email'] ?? '')], 'manual')];
    case 'paste':
      return ['summary' => db_member_emails_add(db_member_emails_split(wp_unslash($_POST['emails'] ?? '')), 'paste')];
    case 'csv':
      $file = $_FILES['csv'] ?? null;
      if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Choose a CSV file to upload.'];
      }
      if ($file['size'] > 5 * MB_IN_BYTES) return ['error' => 'That file is over 5 MB.'];
      $values = db_member_emails_from_csv($file['tmp_name']);
      if (is_wp_error($values)) return ['error' => $values->get_error_message()];
      return ['summary' => db_member_emails_add($values, 'csv')];
    case 'remove':
      $n = db_member_emails_remove((array) ($_POST['ids'] ?? []));
      return ['message' => sprintf(_n('Removed %d address.', 'Removed %d addresses.', $n), $n)];
  }
  return null;
}

function db_member_emails_page() {
  if (!current_user_can('manage_options')) return;
  global $wpdb;
  $result = db_member_emails_handle_post();

  $search   = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
  $paged    = max(1, (int) ($_GET['paged'] ?? 1));
  $per_page = 50;
  $where    = $search !== '' ? $wpdb->prepare(' WHERE email LIKE %s', '%' . $wpdb->esc_like(strtolower($search)) . '%') : '';
  $matching = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . db_member_emails_table() . $where);
  $rows     = $wpdb->get_results($wpdb->prepare(
    'SELECT id, email, source, added_at FROM ' . db_member_emails_table() . $where . ' ORDER BY email LIMIT %d OFFSET %d',
    $per_page, ($paged - 1) * $per_page
  ));
  $pages = max(1, (int) ceil($matching / $per_page));
  $base  = admin_url('edit.php?post_type=db_gallery_photo&page=db-member-emails');
  ?>
  <div class="wrap">
    <h1 class="wp-heading-inline">Member Emails</h1>
    <a class="page-title-action" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=db_member_emails_export'), 'db_member_emails_export')); ?>">Export CSV</a>
    <hr class="wp-header-end">
    <p>Photograph submissions from these addresses get the member limit; everyone else gets the non-member limit
      (<a href="<?php echo esc_url(admin_url('edit.php?post_type=db_gallery_photo&page=db-gallery-settings')); ?>">Submission settings</a>).
      <strong><?php echo esc_html(number_format_i18n(db_member_emails_count())); ?></strong> addresses on the list.</p>

    <?php if (!empty($result['summary'])): $s = $result['summary']; ?>
      <div class="notice notice-<?php echo $s['invalid'] ? 'warning' : 'success'; ?> db-me-summary">
        <p><strong><?php echo esc_html(sprintf('Added %d, duplicates %d, invalid %d.', $s['added'], $s['duplicates'], count($s['invalid']))); ?></strong></p>
        <?php if ($s['invalid']): ?>
          <p>Not added, as they are not valid addresses:</p>
          <ul style="list-style:disc;margin-left:20px"><?php foreach ($s['invalid'] as $bad): ?><li><code><?php echo esc_html($bad); ?></code></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
    <?php elseif (!empty($result['error'])): ?>
      <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
    <?php elseif (!empty($result['message'])): ?>
      <div class="notice notice-success"><p><?php echo esc_html($result['message']); ?></p></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;max-width:1200px;margin:16px 0">
      <form method="post" class="card" style="margin:0;max-width:none">
        <h2 class="title">Add one</h2>
        <?php wp_nonce_field('db_member_emails_add_one'); ?>
        <input type="hidden" name="db_me_action" value="add_one">
        <p><input type="email" name="email" class="regular-text" style="width:100%" placeholder="name@example.com" required></p>
        <p><button class="button button-primary">Add</button></p>
      </form>

      <form method="post" class="card" style="margin:0;max-width:none">
        <h2 class="title">Paste a list</h2>
        <?php wp_nonce_field('db_member_emails_paste'); ?>
        <input type="hidden" name="db_me_action" value="paste">
        <p><textarea name="emails" rows="4" class="large-text" placeholder="One per line, or separated by commas"></textarea></p>
        <p><button class="button button-primary">Add all</button></p>
      </form>

      <form method="post" enctype="multipart/form-data" class="card" style="margin:0;max-width:none">
        <h2 class="title">Upload a CSV</h2>
        <?php wp_nonce_field('db_member_emails_csv'); ?>
        <input type="hidden" name="db_me_action" value="csv">
        <p><input type="file" name="csv" accept=".csv,text/csv" required></p>
        <p class="description">Uses the column headed "email"; any other columns are ignored.</p>
        <p><button class="button button-primary">Upload and add</button></p>
      </form>
    </div>

    <form method="get" style="margin:16px 0 8px">
      <input type="hidden" name="post_type" value="db_gallery_photo">
      <input type="hidden" name="page" value="db-member-emails">
      <label class="screen-reader-text" for="db-me-search">Search addresses</label>
      <input type="search" id="db-me-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search addresses">
      <button class="button">Search</button>
      <?php if ($search !== ''): ?>
        <a href="<?php echo esc_url($base); ?>">Clear</a>
        <span class="description"><?php echo esc_html(sprintf('%d matching', $matching)); ?></span>
      <?php endif; ?>
    </form>

    <form method="post">
      <?php wp_nonce_field('db_member_emails_remove'); ?>
      <input type="hidden" name="db_me_action" value="remove">
      <table class="widefat striped" style="max-width:900px">
        <thead><tr>
          <td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.db-me-id').forEach(c => c.checked = this.checked)" aria-label="Select all"></td>
          <th>Email</th><th>Added by</th><th>Added</th><th></th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5"><?php echo $search !== '' ? 'No addresses match.' : 'No addresses yet.'; ?></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <th class="check-column"><input type="checkbox" class="db-me-id" name="ids[]" value="<?php echo (int) $row->id; ?>" aria-label="Select <?php echo esc_attr($row->email); ?>"></th>
            <td><?php echo esc_html($row->email); ?></td>
            <td><?php echo esc_html(['manual' => 'Added one', 'paste' => 'Pasted list', 'csv' => 'CSV upload'][$row->source] ?? $row->source); ?></td>
            <td><?php echo esc_html(mysql2date(get_option('date_format'), $row->added_at)); ?></td>
            <td><button class="button-link" style="color:#b32d2e" name="ids[]" value="<?php echo (int) $row->id; ?>"
                        onclick="if (!confirm('Remove <?php echo esc_js($row->email); ?>?')) return false; document.querySelectorAll('.db-me-id').forEach(c => c.checked = false)">Remove</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p style="display:flex;gap:12px;align-items:center;max-width:900px">
        <button class="button" onclick="return document.querySelector('.db-me-id:checked') ? confirm('Remove the selected addresses?') : false">Remove selected</button>
        <?php if ($pages > 1): ?>
          <span style="margin-left:auto">
            <?php if ($paged > 1): ?><a class="button" href="<?php echo esc_url(add_query_arg(['s' => $search, 'paged' => $paged - 1], $base)); ?>">‹ Previous</a><?php endif; ?>
            Page <?php echo (int) $paged; ?> of <?php echo (int) $pages; ?>
            <?php if ($paged < $pages): ?><a class="button" href="<?php echo esc_url(add_query_arg(['s' => $search, 'paged' => $paged + 1], $base)); ?>">Next ›</a><?php endif; ?>
          </span>
        <?php endif; ?>
      </p>
    </form>
  </div>
  <?php
}
