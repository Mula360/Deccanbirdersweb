<?php
/**
 * Mail: sending through the society's SMTP mailbox, photos embedded in
 * emails, and a log of everything the site sends
 * (Gallery → Email & mail log).
 *
 * SMTP host, port, encryption, username and sender come from the screen;
 * the password only ever from wp-config.php (DB_SMTP_PASS), so it is not
 * in the database. With no password, WordPress is left to send as it
 * would anyway.
 */

if (!defined('ABSPATH')) exit;

function db_mail_log_table() {
  global $wpdb;
  return $wpdb->prefix . 'db_mail_log';
}

/** name => [label, default, hint]. */
function db_smtp_fields() {
  return [
    'host'       => ['SMTP server', 'smtp.hostinger.com', ''],
    'port'       => ['Port', '587', '587 with TLS, or 465 with SSL.'],
    'encryption' => ['Encryption', 'tls', 'tls or ssl.'],
    'username'   => ['Username', 'info@deccanbirders.org', 'The mailbox the site sends from.'],
    'from_email' => ['From address', '', 'Leave blank to use the username. Most mail servers insist they match.'],
    'from_name'  => ['From name', 'Deccan Birders', ''],
  ];
}

function db_smtp_setting($name) {
  $all = (array) get_option('db_smtp_settings', []);
  $v = trim((string) ($all[$name] ?? ''));
  // wp-config.php constants from before this screen existed still count.
  $const = ['host' => 'DB_SMTP_HOST', 'port' => 'DB_SMTP_PORT', 'username' => 'DB_SMTP_USER'][$name] ?? '';
  if ($v === '' && $const && defined($const) && constant($const)) $v = (string) constant($const);
  if ($v === '' && $name === 'from_email') return db_smtp_setting('username');
  return $v !== '' ? $v : db_smtp_fields()[$name][1];
}

function db_smtp_password_set() {
  return defined('DB_SMTP_PASS') && DB_SMTP_PASS;
}

function db_wp_mail_smtp_active() {
  return defined('WPMS_PLUGIN_VER') || class_exists('WPMailSMTP\\Core');
}

/**
 * Send through the society's mailbox — but only when a password is
 * configured. Without one, switching to SMTP would fail every email with
 * "Could not authenticate", so WordPress is left alone.
 */
add_action('phpmailer_init', function($m) {
  if (db_smtp_password_set()) {
    $m->isSMTP();
    $m->Host       = db_smtp_setting('host');
    $m->Port       = (int) db_smtp_setting('port');
    $m->SMTPAuth   = true;
    $m->Username   = db_smtp_setting('username');
    $m->Password   = DB_SMTP_PASS;
    $m->SMTPSecure = db_smtp_setting('encryption') === 'ssl' ? 'ssl' : 'tls';
    $m->Timeout    = 20;
    $m->setFrom(db_smtp_setting('from_email'), db_smtp_setting('from_name'), false);
  }
  // Images for this message to carry inline (see db_mail_with_images).
  foreach ($GLOBALS['db_mail_embeds'] ?? [] as [$path, $cid, $name]) {
    if (is_readable($path)) $m->addEmbeddedImage($path, $cid, $name);
  }
});

/**
 * wp_mail() with images embedded in the message rather than linked, so
 * they show in Gmail and the like without "display images". Refer to each
 * in the HTML as src="cid:<cid>". $embeds: [[path, cid, filename], …].
 * $context labels the message in the mail log.
 */
function db_mail_with_images($to, $subject, $html, array $headers, array $embeds, $context = '') {
  $GLOBALS['db_mail_embeds'] = $embeds;
  $GLOBALS['db_mail_context'] = $context;
  try {
    return wp_mail($to, $subject, $html, $headers);
  } finally {
    $GLOBALS['db_mail_embeds'] = [];
    $GLOBALS['db_mail_context'] = '';
  }
}

/** wp_mail() with a label for the mail log. */
function db_mail($to, $subject, $html, array $headers = [], $context = '') {
  return db_mail_with_images($to, $subject, $html, $headers, [], $context);
}

/* -----------------------------------------------------------------------
 * Log: every email the site sends, whoever sends it
 * -------------------------------------------------------------------- */

function db_mail_log_write(array $data, $ok, $error = '') {
  global $wpdb;
  $to = $data['to'] ?? [];
  $wpdb->insert(db_mail_log_table(), [
    'sent_at'    => current_time('mysql'),
    'recipients' => mb_substr(implode(', ', (array) $to), 0, 500),
    'subject'    => mb_substr((string) ($data['subject'] ?? ''), 0, 255),
    'context'    => mb_substr((string) ($GLOBALS['db_mail_context'] ?? ''), 0, 40),
    'ok'         => $ok ? 1 : 0,
    'error'      => mb_substr($error, 0, 500),
  ]);
  // Keep six months.
  if (wp_rand(1, 50) === 1) {
    $wpdb->query($wpdb->prepare('DELETE FROM ' . db_mail_log_table() . ' WHERE sent_at < %s', date('Y-m-d H:i:s', current_time('timestamp') - 180 * DAY_IN_SECONDS)));
  }
}
add_action('wp_mail_succeeded', fn($data) => db_mail_log_write((array) $data, true));
add_action('wp_mail_failed', fn($error) => db_mail_log_write((array) $error->get_error_data(), false, $error->get_error_message()));

/* -----------------------------------------------------------------------
 * Screen
 * -------------------------------------------------------------------- */

add_action('admin_menu', function() {
  add_submenu_page(
    'edit.php?post_type=db_gallery_photo',
    'Email & mail log',
    'Email & mail log',
    'manage_options',
    'db-mail',
    'db_mail_page'
  );
});

add_action('admin_init', function() {
  register_setting('db_smtp_settings', 'db_smtp_settings', [
    'type'              => 'array',
    'sanitize_callback' => function($input) {
      $out = [];
      foreach (array_keys(db_smtp_fields()) as $k) {
        $v = trim(sanitize_text_field((string) (((array) $input)[$k] ?? '')));
        if ($k === 'port') $v = ctype_digit($v) && (int) $v > 0 && (int) $v < 65536 ? $v : '';
        if ($k === 'encryption') $v = in_array(strtolower($v), ['tls', 'ssl'], true) ? strtolower($v) : '';
        if (in_array($k, ['username', 'from_email'], true) && $v !== '' && !is_email($v)) {
          add_settings_error('db_smtp_settings', $k, db_smtp_fields()[$k][0] . ' is not an email address — left as it was.');
          $v = ((array) get_option('db_smtp_settings', []))[$k] ?? '';
        }
        $out[$k] = $v;
      }
      return $out;
    },
    'default' => [],
  ]);
});

function db_mail_page() {
  if (!current_user_can('manage_options')) return;
  global $wpdb;

  $test = null;
  if (isset($_POST['db_mail_test']) && check_admin_referer('db_mail_test')) {
    $to = sanitize_email(wp_unslash($_POST['to'] ?? ''));
    if (!is_email($to)) {
      $test = ['error' => 'Enter an email address to send the test to.'];
    } else {
      $err = null;
      $catch = function($e) use (&$err) { $err = $e->get_error_message(); };
      add_action('wp_mail_failed', $catch);
      $ok = db_mail($to, 'Test email — Deccan Birders website',
        '<p>This is a test from the Deccan Birders website, sent ' . esc_html(current_time('j F Y, g:i a')) . '.</p><p>If you can read it, the site can send email.</p>',
        ['Content-Type: text/html; charset=UTF-8'], 'test');
      remove_action('wp_mail_failed', $catch);
      $test = $ok ? ['ok' => "Sent to $to. Check the inbox, and the spam folder."] : ['error' => 'Not sent: ' . ($err ?: 'unknown error')];
    }
  }

  $only_failed = !empty($_GET['failed']);
  $paged = max(1, (int) ($_GET['paged'] ?? 1));
  $per   = 50;
  $where = $only_failed ? ' WHERE ok = 0' : '';
  $total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . db_mail_log_table() . $where);
  $rows  = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . db_mail_log_table() . $where . ' ORDER BY id DESC LIMIT %d OFFSET %d', $per, ($paged - 1) * $per));
  $failed_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . db_mail_log_table() . ' WHERE ok = 0');
  $base = admin_url('edit.php?post_type=db_gallery_photo&page=db-mail');
  ?>
  <div class="wrap">
    <h1>Email &amp; mail log</h1>

    <?php if (db_wp_mail_smtp_active()): ?>
      <div class="notice notice-warning inline"><p><strong>The WP Mail SMTP plugin is active.</strong> It takes over sending, so the settings below are not used while it is on. Deactivate it (Plugins) to send with these.</p></div>
    <?php endif; ?>
    <?php if (!db_smtp_password_set()): ?>
      <div class="notice notice-warning inline"><p><strong>No SMTP password is set</strong>, so email goes out through the web server's own mail, which often lands in spam.
        Add this line to <code>wp-config.php</code>, above "That's all, stop editing":<br>
        <code>define('DB_SMTP_PASS', 'the mailbox password');</code></p></div>
    <?php else: ?>
      <div class="notice notice-success inline"><p>SMTP password is set in wp-config.php. Email is sent through <strong><?php echo esc_html(db_smtp_setting('host')); ?></strong> as <strong><?php echo esc_html(db_smtp_setting('from_email')); ?></strong>.</p></div>
    <?php endif; ?>

    <?php settings_errors(); ?>
    <h2>SMTP</h2>
    <form method="post" action="options.php">
      <?php settings_fields('db_smtp_settings'); ?>
      <table class="form-table" role="presentation">
        <?php foreach (db_smtp_fields() as $k => [$label, $default, $hint]):
          $stored = ((array) get_option('db_smtp_settings', []))[$k] ?? ''; ?>
          <tr>
            <th scope="row"><label for="db-smtp-<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input id="db-smtp-<?php echo esc_attr($k); ?>" type="text" class="regular-text" name="db_smtp_settings[<?php echo esc_attr($k); ?>]"
                       value="<?php echo esc_attr($stored); ?>" placeholder="<?php echo esc_attr($default); ?>">
              <?php if ($hint): ?><p class="description"><?php echo esc_html($hint); ?></p><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <tr><th scope="row">Password</th><td><?php echo db_smtp_password_set() ? 'Set in wp-config.php' : '<em>Not set</em>'; ?>
          <p class="description">Kept out of the database on purpose: set <code>DB_SMTP_PASS</code> in wp-config.php.</p></td></tr>
      </table>
      <?php submit_button('Save SMTP settings'); ?>
    </form>

    <h2>Send a test</h2>
    <?php if ($test): ?>
      <div class="notice notice-<?php echo isset($test['ok']) ? 'success' : 'error'; ?> inline"><p><?php echo esc_html($test['ok'] ?? $test['error']); ?></p></div>
    <?php endif; ?>
    <form method="post" style="display:flex;gap:8px;align-items:center">
      <?php wp_nonce_field('db_mail_test'); ?>
      <input type="email" name="to" class="regular-text" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" required>
      <button class="button" name="db_mail_test" value="1">Send test email</button>
    </form>

    <h2 id="log">Mail log</h2>
    <p>Every email the site sends, whoever sends it. Kept for six months.
      <?php if ($only_failed): ?><a href="<?php echo esc_url($base . '#log'); ?>">Show all</a>
      <?php else: ?><a href="<?php echo esc_url(add_query_arg('failed', 1, $base) . '#log'); ?>">Show only failed (<?php echo (int) $failed_count; ?>)</a><?php endif; ?></p>
    <table class="widefat striped" style="max-width:1200px">
      <thead><tr><th style="width:150px">Time</th><th>To</th><th>Subject</th><th style="width:90px">Kind</th><th style="width:220px">Result</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="5">Nothing yet.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo esc_html(mysql2date('j M Y, H:i', $r->sent_at)); ?></td>
            <td><?php echo esc_html($r->recipients); ?></td>
            <td><?php echo esc_html($r->subject); ?></td>
            <td><?php echo esc_html($r->context ?: '—'); ?></td>
            <td><?php echo $r->ok ? '<span style="color:#008a20">Sent</span>' : '<span style="color:#b32d2e"><strong>Failed:</strong> ' . esc_html($r->error) . '</span>'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php $pages = (int) ceil($total / $per); if ($pages > 1): ?>
      <p><?php if ($paged > 1): ?><a class="button" href="<?php echo esc_url(add_query_arg(['paged' => $paged - 1, 'failed' => $only_failed ? 1 : null], $base) . '#log'); ?>">‹ Newer</a><?php endif; ?>
        Page <?php echo (int) $paged; ?> of <?php echo (int) $pages; ?>
        <?php if ($paged < $pages): ?><a class="button" href="<?php echo esc_url(add_query_arg(['paged' => $paged + 1, 'failed' => $only_failed ? 1 : null], $base) . '#log'); ?>">Older ›</a><?php endif; ?></p>
    <?php endif; ?>
  </div>
  <?php
}
