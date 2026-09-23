<?php
/**
 * Deccan Birders theme functions.
 *
 * Standalone theme — pages render directly from PHP templates
 * (page-{slug}.php, dispatched via page.php/front-page.php), not
 * Elementor. Elementor's own CSS generation was found to produce empty
 * output on this host even when correctly triggered via its own verified
 * public API (confirmed against a real browser session and the plugin's
 * own editor preview, not just synthetic requests) — see git history for
 * the investigation. This file provides theme setup, enqueues, custom
 * post types, ACF options, SMTP config, AJAX handlers, shortcodes, and
 * one-time seed data.
 */

if (!defined('ABSPATH')) exit;

/* -----------------------------------------------------------------------
 * 1. Theme setup
 * ---------------------------------------------------------------------*/
add_action('after_setup_theme', function() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('html5', ['script', 'style', 'search-form']);
  add_theme_support('menus');
  register_nav_menus([
    'primary-nav' => 'Primary Navigation',
    'footer-nav'  => 'Footer Navigation',
  ]);
});

/* -----------------------------------------------------------------------
 * 2. Enqueue scripts and styles
 * ---------------------------------------------------------------------*/
add_action('wp_enqueue_scripts', function() {
  // File-based version so edits to CSS/JS always bust browser and
  // LiteSpeed/proxy caches instead of being served stale under a static tag.
  $v = wp_get_theme()->get('Version') ?: '1.0';
  $main_css_path = get_template_directory() . '/assets/css/main.css';
  if (file_exists($main_css_path)) {
    $v = filemtime($main_css_path);
  }

  // Fonts are served from this domain (assets/fonts) rather than Google's
  // CDN: one less third-party connection, and no request to Google from a
  // visitor's browser.
  wp_enqueue_style('db-fonts', get_template_directory_uri() . '/assets/css/fonts.css', [], $v);
  wp_enqueue_style('db-vars', get_template_directory_uri() . '/assets/css/variables.css', [], $v);
  wp_enqueue_style('db-main', get_template_directory_uri() . '/assets/css/main.css', ['db-vars'], $v);

  // The loading bird is shared by every script that waits on data.
  wp_enqueue_script('db-bird-loader', get_template_directory_uri() . '/assets/js/bird-loader.js', [], $v, true);
  wp_enqueue_script('db-main', get_template_directory_uri() . '/assets/js/main.js', [], $v, true);

  // Sightings JS — load on sightings page and front page
  if (is_front_page() || is_page('sightings')) {
    wp_enqueue_script('db-sightings', get_template_directory_uri() . '/assets/js/sightings.js', ['db-bird-loader'], $v, true);
  }
  // Events JS — load on events page and front page
  if (is_front_page() || is_page('events')) {
    wp_enqueue_script('db-events', get_template_directory_uri() . '/assets/js/events.js', ['db-bird-loader'], $v, true);
  }
  // Videos JS — load on gallery page
  if (is_page('gallery')) {
    wp_enqueue_script('db-videos', get_template_directory_uri() . '/assets/js/videos.js', ['db-bird-loader'], $v, true);
  }
  // Activities panel — load on about page (and /activities, which redirects there)
  if (is_page('about')) {
    wp_enqueue_script('db-activities', get_template_directory_uri() . '/assets/js/activities.js', [], $v, true);
  }
  // PITTA search — load on archives page
  if (is_page('archives')) {
    wp_enqueue_script('db-pitta-search', get_template_directory_uri() . '/assets/js/pitta-search.js', ['db-bird-loader'], $v, true);
  }

  // Pass config to all JS
  wp_localize_script('db-main', 'DB_CONFIG', [
    'api_base' => rtrim(get_option('db_api_base_url', 'https://deccan-birders-api.vercel.app'), '/'),
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('db_contact_nonce'),
    'region'   => 'IN',
    'rest_url' => esc_url_raw(rest_url('db/v1/')),
  ]);
});

/**
 * The loading state, for markup rendered before any JavaScript runs — the
 * same bird assets/js/bird-loader.js draws, so a page never flips from one
 * kind of placeholder to another while it waits.
 */
/**
 * Credit for eBird, wherever its data is shown.
 *
 * The eBird API terms require the source to be attributed "wherever it is
 * used or displayed", with a link back to eBird.org, so every section
 * drawing on the API carries this. $compact is the one-line version for
 * a card or a section head; the full one adds the citation link.
 */
function db_ebird_credit($compact = false) {
  $ebird = '<a href="https://ebird.org" target="_blank" rel="noopener">eBird</a>';
  if ($compact) {
    return '<p class="ebird-credit ebird-credit--compact">'
      . sprintf(
        /* translators: %s: link reading "eBird" */
        esc_html__('Data from %s', 'deccan-birders'),
        $ebird
      ) . '</p>';
  }
  return '<p class="ebird-credit">'
    . sprintf(
      /* translators: 1: link reading "eBird", 2: link reading "how to cite" */
      esc_html__('Data from %1$s, Cornell Lab of Ornithology — %2$s.', 'deccan-birders'),
      $ebird,
      '<a href="https://ebird.org/science/citation" target="_blank" rel="noopener">'
        . esc_html__('how to cite', 'deccan-birders') . '</a>'
    ) . '</p>';
}

function db_bird_loader($message = 'Loading…') {
  printf(
    '<div class="bird-loader" role="status" aria-live="polite">'
    . '<div class="bird-loader-flight">'
    . '<svg class="bird-loader-bird" viewBox="0 0 64 40" aria-hidden="true" focusable="false">'
    . '<path class="bird-wing-up" d="M4 26c8 2 14-2 19-9 3-4 6-7 9-7s6 3 9 7c5 7 11 11 19 9-7 6-14 8-19 5-4-2-6-5-9-5s-5 3-9 5c-5 3-12 1-19-5Z"/>'
    . '<path class="bird-wing-down" d="M4 12c8-2 14 2 19 9 3 4 6 7 9 7s6-3 9-7c5-7 11-11 19-9-7-6-14-8-19-5-4 2-6 5-9 5s-5-3-9-5c-5-3-12-1-19 5Z"/>'
    . '</svg></div>'
    . '<p class="bird-loader-text">%s</p></div>',
    esc_html($message)
  );
}

/**
 * Open the connections the page is about to need, during the wait for
 * HTML: on the Gallery page, YouTube's thumbnail and player hosts. Fonts
 * are same-origin, so they need no hint.
 */
add_action('wp_head', function() {
  // The two faces almost every page starts with. Without this they'd only
  // be discovered after the stylesheet parses, delaying the first text.
  foreach (['sora-700-latin.woff2', 'source-sans-3-400-latin.woff2'] as $file) {
    printf('<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
      esc_url(get_template_directory_uri() . '/assets/fonts/' . $file));
  }
}, 1);

add_filter('wp_resource_hints', function($urls, $relation) {
  if ($relation === 'preconnect') {
    if (is_page('gallery')) {
      $urls[] = 'https://i.ytimg.com';
      $urls[] = 'https://www.youtube-nocookie.com';
    }
  }
  return $urls;
}, 10, 2);

/* -----------------------------------------------------------------------
 * 4. Custom post types
 * ---------------------------------------------------------------------*/
add_action('init', function() {

  register_post_type('db_event', [
    'labels'       => ['name' => 'Events', 'singular_name' => 'Event', 'add_new_item' => 'Add New Event', 'edit_item' => 'Edit Event'],
    'public'       => true,
    'show_in_rest' => true,
    'supports'     => ['title', 'editor', 'thumbnail'],
    'menu_icon'    => 'dashicons-calendar-alt',
    'rewrite'      => ['slug' => 'events'],
  ]);

  register_post_type('db_gallery_photo', [
    'labels'    => ['name' => 'Gallery', 'singular_name' => 'Photo', 'add_new_item' => 'Add New Photo'],
    'public'    => true,
    'supports'  => ['title', 'thumbnail'],
    'menu_icon' => 'dashicons-camera',
    'rewrite'   => ['slug' => 'gallery'],
  ]);

  // Photos from past trips, for the carousel at the foot of the Events
  // page. Deliberately its own menu item rather than a field buried in a
  // page, so "add the photos from Sunday" is one obvious place to go. The
  // featured image is the photo; the title only describes it for screen
  // readers.
  register_post_type('db_trip_photo', [
    'labels'    => [
      'name'          => 'Trip Photos',
      'singular_name' => 'Trip Photo',
      'add_new_item'  => 'Add Trip Photo',
      'edit_item'     => 'Edit Trip Photo',
      'menu_name'     => 'Trip Photos',
    ],
    'public'    => false,
    'show_ui'   => true,
    'supports'  => ['title', 'thumbnail', 'page-attributes'],
    'menu_icon' => 'dashicons-images-alt2',
  ]);

  register_post_type('db_pitta', [
    'labels'    => ['name' => 'PITTA Archive', 'singular_name' => 'PITTA Issue', 'add_new_item' => 'Add New Issue'],
    'public'    => true,
    'supports'  => ['title'],
    'menu_icon' => 'dashicons-book-alt',
    'rewrite'   => ['slug' => 'pitta'],
  ]);

});

/**
 * Activities now live inside About, so the old page and its menu item
 * step aside: anyone arriving at /activities — an old link, a search
 * result, a bookmark — lands on that section of About instead.
 */
add_action('template_redirect', function() {
  if (!is_page('activities')) return;
  $about = get_page_by_path('about');
  wp_safe_redirect(($about ? get_permalink($about) : home_url('/about/')) . '#activities', 301);
  exit;
});

add_filter('wp_nav_menu_objects', function($items) {
  return array_values(array_filter($items, function($item) {
    return !preg_match('#/activities/?$#', (string) $item->url);
  }));
});

/* -----------------------------------------------------------------------
 * 5. ACF options page
 * ---------------------------------------------------------------------*/
// ACF options pages are a Pro feature, and this site runs ACF free — so
// the Site Settings page ACF was asked for never appeared, and every
// value below silently fell back to its hard-coded default. The settings
// are stored under the same options_<name> keys ACF would use, so they
// still work if Pro is ever installed; it registers its own page then and
// this one stands down. Must be on acf/init: ACF 6 ignores an options
// page registered while functions.php is still loading.
add_action('acf/init', function() {
  if (function_exists('acf_add_options_page')) {
    acf_add_options_page([
      'page_title' => 'Site Settings',
      'menu_title' => 'Site Settings',
      'menu_slug'  => 'site-settings',
      'capability' => 'manage_options',
      'redirect'   => false,
    ]);
  }
});

/**
 * A Site Settings value. Stored as options_<name>, the same key ACF Pro's
 * options pages use, so both the theme's own settings page and ACF read
 * and write the same place.
 */
function db_setting($name) {
  $val = get_option('options_' . $name, '');
  if (is_string($val) && trim($val) !== '') return trim($val);
  if (function_exists('get_field')) {
    $acf = get_field($name, 'option');
    if (is_string($acf) && trim($acf) !== '') return trim($acf);
  }
  return '';
}

/** The Site Settings fields: name => [label, type, hint]. */
function db_settings_fields() {
  return [
    'footer_tagline'        => ['Footer tagline', 'text', 'The line under the logo in the footer.'],
    'contact_address'       => ['Contact address', 'textarea', ''],
    'contact_phone'         => ['Contact phone', 'text', ''],
    'contact_whatsapp'      => ['WhatsApp number', 'text', 'Digits only, with country code, e.g. 919738840070.'],
    'social_ebird'          => ['eBird URL', 'url', ''],
    'social_facebook'       => ['Facebook URL', 'url', ''],
    'social_youtube'        => ['YouTube channel URL', 'url', 'The "Open our YouTube channel" button on the Gallery.'],
    'youtube_channel_id'    => ['YouTube channel ID', 'text', 'Starts with UC. YouTube Studio → Settings → Channel → Advanced.'],
    'youtube_api_key'       => ['YouTube API key', 'text', 'A YouTube Data API v3 key: lists every video on the Gallery. Without it only the newest 12 show. Can also be set as DB_YOUTUBE_API_KEY in wp-config.php.'],
    'db_api_base_url'       => ['Bird data API URL', 'url', 'The Vercel deployment used for eBird and Calendar data. No trailing slash.'],
    'db_proxy_secret'       => ['Bird data API key', 'text', 'Must match DB_PROXY_SECRET in the Vercel project. Stops anyone else using our eBird quota.'],
    'db_member_count'       => ['Member count', 'text', ''],
    'db_founded_year'       => ['Founded year', 'text', 'Used to count the "Years …" figure on the home page, which works itself out from this. Defaults to 1980.'],
    'db_membership_form_url' => ['Membership form URL', 'url', ''],
    'email_photos'          => ['Photo submissions email', 'text', 'Who reviews photograph submissions. Several addresses can be separated by commas. Defaults to photos@deccanbirders.org.'],
    'email_volunteers'      => ['Volunteer submissions email', 'text', 'Who hears about volunteer sign-ups. Several addresses can be separated by commas. Defaults to info@deccanbirders.org.'],
    'volunteer_sheet_url'   => ['Volunteer sheet webhook URL', 'url', 'The Apps Script web app URL that appends volunteers to your Google Sheet. See docs/google-sheet-volunteers.md.'],
    'volunteer_sheet_secret' => ['Volunteer sheet secret', 'text', 'Must match the SECRET in the Apps Script, so only this site can write to the sheet.'],
  ];
}

/**
 * Years the society has been going. Always counted, never stored, so the
 * homepage stat cannot go stale — only the founding year is a setting,
 * and it hardly changes.
 */
function db_founded_year() {
  $year = (int) db_setting('db_founded_year');
  return ($year >= 1800 && $year <= (int) current_time('Y')) ? $year : 1980;
}

function db_years_active() {
  return max(1, (int) current_time('Y') - db_founded_year());
}

/**
 * Where each kind of submission is emailed. The setting takes a list
 * separated by commas, so several committee members can be notified.
 */
function db_notify_email($kind) {
  // The committee members who handle submissions today; override either
  // list in Settings → Site Settings without touching the theme.
  $committee = ['srikanth@deccanbirders.org', 'Gowthama@deccanbirders.org', 'gokul@deccanbirders.org'];
  $defaults = ['photos' => $committee, 'volunteers' => $committee];
  $valid = array_values(array_filter(
    array_map('trim', explode(',', db_setting('email_' . $kind))),
    'is_email'
  ));
  return $valid ?: $defaults[$kind];
}

add_action('admin_menu', function() {
  // ACF Pro registers its own Site Settings page; don't offer two.
  if (function_exists('acf_add_options_page')) return;
  add_options_page('Site Settings', 'Site Settings', 'manage_options', 'db-site-settings', 'db_settings_page');
});

add_action('admin_init', function() {
  if (function_exists('acf_add_options_page')) return;
  foreach (array_keys(db_settings_fields()) as $name) {
    register_setting('db_site_settings', 'options_' . $name, [
      'type'              => 'string',
      'sanitize_callback' => 'db_sanitize_setting',
      'default'           => '',
    ]);
  }
});

function db_sanitize_setting($value) {
  $value = is_string($value) ? trim($value) : '';
  return strpos($value, "\n") !== false ? sanitize_textarea_field($value) : sanitize_text_field($value);
}

function db_settings_page() {
  if (!current_user_can('manage_options')) return;
  ?>
  <div class="wrap">
    <h1>Site Settings</h1>
    <p>Used across the site: the footer, the contact page, and the live data feeds.</p>
    <form method="post" action="options.php">
      <?php settings_fields('db_site_settings'); ?>
      <table class="form-table" role="presentation">
        <?php foreach (db_settings_fields() as $name => [$label, $type, $hint]):
          $value = get_option('options_' . $name, '');
          $id = 'db-' . $name;
        ?>
          <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
              <?php if ($type === 'textarea'): ?>
                <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr('options_' . $name); ?>" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
              <?php else: ?>
                <input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr('options_' . $name); ?>"
                       type="<?php echo $type === 'url' ? 'url' : 'text'; ?>"
                       value="<?php echo esc_attr($value); ?>" class="regular-text">
              <?php endif; ?>
              <?php if ($hint): ?><p class="description"><?php echo esc_html($hint); ?></p><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

/* -----------------------------------------------------------------------
 * 6. SMTP configuration
 * ---------------------------------------------------------------------*/
/**
 * Send through the society's mailbox — but only when a password is
 * actually configured. Without DB_SMTP_PASS this used to switch WordPress
 * to SMTP and then authenticate with an empty password, so every email on
 * the site failed with "Could not authenticate" and nothing was sent,
 * including submissions from the forms. With no password set we leave
 * WordPress alone, so WP Mail SMTP (or the host's own mail) handles it.
 */
add_action('phpmailer_init', function($m) {
  if (!defined('DB_SMTP_PASS') || !DB_SMTP_PASS) return;

  $m->isSMTP();
  $m->Host       = defined('DB_SMTP_HOST') ? DB_SMTP_HOST : 'smtp.hostinger.com';
  $m->SMTPAuth   = true;
  $m->Port       = defined('DB_SMTP_PORT') ? (int) DB_SMTP_PORT : 587;
  $m->Username   = defined('DB_SMTP_USER') ? DB_SMTP_USER : 'info@deccanbirders.org';
  $m->Password   = DB_SMTP_PASS;
  $m->SMTPSecure = $m->Port === 465 ? 'ssl' : 'tls';
  $m->From       = $m->Username;
  $m->FromName   = 'Deccan Birders';
});

/* -----------------------------------------------------------------------
 * 7. AJAX form handlers
 * ---------------------------------------------------------------------*/
/**
 * A security token for the forms, minted here rather than printed into
 * the page: LiteSpeed can serve a page for longer than a token's 24-hour
 * life, and a stale one fails every submission.
 *
 * It lives on admin-ajax, not the REST API, because REST treats a request
 * carrying cookies but no REST token as logged out — so a signed-in
 * committee member got a token for "anonymous" and then submitted as
 * themselves, which never verified. admin-ajax sees the same identity on
 * both trips, so the token matches for visitors and editors alike.
 */
add_action('wp_ajax_nopriv_db_nonce', 'db_send_form_nonce');
add_action('wp_ajax_db_nonce', 'db_send_form_nonce');
function db_send_form_nonce() {
  nocache_headers();
  wp_send_json(['nonce' => wp_create_nonce('db_contact_nonce')]);
}

add_action('wp_ajax_nopriv_db_contact', 'db_handle_contact');
add_action('wp_ajax_db_contact', 'db_handle_contact');
function db_handle_contact() {
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'db_contact_nonce')) {
    wp_send_json(['success' => false, 'message' => 'Security check failed.']);
  }
  $name    = sanitize_text_field($_POST['name'] ?? '');
  $email   = sanitize_email($_POST['email'] ?? '');
  $subject = sanitize_text_field($_POST['subject'] ?? 'General');
  $message = sanitize_textarea_field($_POST['message'] ?? '');
  if (!$name || !$email || !$message) {
    wp_send_json(['success' => false, 'message' => 'Please fill in all required fields.']);
  }
  $to      = 'info@deccanbirders.org';
  $headers = ['Content-Type: text/html; charset=UTF-8', "Reply-To: $name <$email>"];
  $body    = "<p><strong>From:</strong> $name ($email)</p><p><strong>Subject:</strong> $subject</p><p>$message</p>";
  wp_mail($to, "Website enquiry: $subject", $body, $headers);
  wp_mail($email, 'We received your message — Deccan Birders', "<p>Hi $name,</p><p>Thank you for getting in touch. We will reply within 2 working days.</p><p>— Deccan Birders</p>", $headers);
  wp_send_json(['success' => true]);
}

add_action('wp_ajax_nopriv_db_volunteer', 'db_handle_volunteer');
add_action('wp_ajax_db_volunteer', 'db_handle_volunteer');
function db_handle_volunteer() {
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'db_contact_nonce')) {
    wp_send_json(['success' => false, 'message' => 'Security check failed.']);
  }
  // Hidden from people, filled in by bots — drop it without a word.
  if (!empty($_POST['website'])) {
    wp_send_json(['success' => true]);
  }
  if (db_rate_limited('volunteer', 3)) {
    wp_send_json(['success' => false, 'message' => 'That is a few submissions in a short time — please try again in an hour.']);
  }
  $name          = sanitize_text_field($_POST['name'] ?? '');
  $email         = sanitize_email($_POST['email'] ?? '');
  $help_with_raw = $_POST['help_with'] ?? [];
  $help_with     = is_array($help_with_raw)
    ? array_map('sanitize_text_field', $help_with_raw)
    : array_filter([sanitize_text_field($help_with_raw)]);
  if (!$name || !$email) {
    wp_send_json(['success' => false, 'message' => 'Please fill in all required fields.']);
  }
  $to           = db_notify_email('volunteers');
  $headers      = ['Content-Type: text/html; charset=UTF-8', "Reply-To: $name <$email>"];
  $help_with_str = $help_with ? implode(', ', $help_with) : 'Not specified';
  $body         = "<p><strong>From:</strong> $name ($email)</p><p><strong>Would like to help with:</strong> $help_with_str</p>";
  wp_mail($to, "New volunteer: $name", $body, $headers);
  wp_mail($email, 'Thank you for volunteering — Deccan Birders', "<p>Hi $name,</p><p>Thank you for offering to help. A committee member will be in touch soon.</p><p>— Deccan Birders</p>", $headers);

  db_append_to_sheet([
    'submitted' => current_time('mysql'),
    'name'      => $name,
    'email'     => $email,
    'help_with' => $help_with_str,
    'source'    => 'Volunteer form',
  ]);

  wp_send_json(['success' => true]);
}

/**
 * Append a row to the volunteer Google Sheet through its Apps Script web
 * app (see docs/google-sheet-volunteers.md). The sheet is a convenience,
 * not the record of truth — the email still goes out either way — so a
 * failure is logged for the admin notice rather than shown to the person
 * who just filled the form in.
 */
function db_append_to_sheet(array $row) {
  $url = db_setting('volunteer_sheet_url');
  if (!$url) return false;
  $row['secret'] = db_setting('volunteer_sheet_secret');

  $res = wp_remote_post($url, [
    'timeout'     => 8,
    'redirection' => 5, // Apps Script answers via a redirect
    'headers'     => ['Content-Type' => 'application/json'],
    'body'        => wp_json_encode($row),
  ]);

  $failed = is_wp_error($res) ? $res->get_error_message() : '';
  if (!$failed) {
    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if ($code < 200 || $code >= 300) $failed = 'HTTP ' . $code;
    elseif (isset($body['ok']) && !$body['ok']) $failed = (string) ($body['error'] ?? 'rejected by the sheet');
  }

  if ($failed) {
    $log = (array) get_option('db_sheet_failures', []);
    array_unshift($log, ['when' => current_time('mysql'), 'error' => $failed, 'name' => $row['name'] ?? '']);
    update_option('db_sheet_failures', array_slice($log, 0, 20), false);
    return false;
  }
  delete_option('db_sheet_failures');
  return true;
}

/** Say so in wp-admin when the sheet stopped accepting rows. */
add_action('admin_notices', function() {
  if (!current_user_can('manage_options')) return;
  $log = (array) get_option('db_sheet_failures', []);
  if (!$log) return;
  printf(
    '<div class="notice notice-warning"><p><strong>Volunteer sheet:</strong> %d recent submission(s) could not be written to the Google Sheet — most recently %s (%s). The volunteer emails were still sent. Check the webhook URL and secret in <a href="%s">Site Settings</a>.</p></div>',
    count($log),
    esc_html($log[0]['when']),
    esc_html($log[0]['error']),
    esc_url(admin_url('options-general.php?page=db-site-settings'))
  );
});

/* -----------------------------------------------------------------------
 * 7b. Photograph submissions
 *
 * A public upload form, so it is deliberately cautious: nonce, a hidden
 * honeypot field, a few submissions per hour per address, JPEG only, a
 * size cap, and the file is verified to be a real image before it is
 * accepted. Submissions land as a PENDING Gallery entry — approving one
 * is just pressing Publish in wp-admin — and never appear on the site
 * until a committee member does that.
 * ---------------------------------------------------------------------*/
const DB_PHOTO_MAX_BYTES = 10 * MB_IN_BYTES;
const DB_PHOTO_MAX_PER_HOUR = 3;

/** Crude per-visitor throttle: true when this one has had enough. */
function db_rate_limited($action, $max_per_hour) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!$ip) return false;
  $key = 'db_rate_' . md5($action . '|' . $ip);
  $count = (int) get_transient($key);
  if ($count >= $max_per_hour) return true;
  set_transient($key, $count + 1, HOUR_IN_SECONDS);
  return false;
}

add_action('wp_ajax_nopriv_db_photo_submit', 'db_handle_photo_submit');
add_action('wp_ajax_db_photo_submit', 'db_handle_photo_submit');
function db_handle_photo_submit() {
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'db_contact_nonce')) {
    wp_send_json(['success' => false, 'message' => 'Security check failed. Please reload the page and try again.']);
  }
  // Honeypot: a field hidden from people, filled in only by bots.
  if (!empty($_POST['website'])) {
    wp_send_json(['success' => true]); // silently drop
  }
  if (db_rate_limited('photo', DB_PHOTO_MAX_PER_HOUR)) {
    wp_send_json(['success' => false, 'message' => 'That is a few submissions in a short time — please try again in an hour.']);
  }

  $name     = sanitize_text_field($_POST['name'] ?? '');
  $email    = sanitize_email($_POST['email'] ?? '');
  $species  = sanitize_text_field($_POST['species'] ?? '');
  $location = sanitize_text_field($_POST['location'] ?? '');
  $consent  = !empty($_POST['consent']);

  if (!$name || !$email || !$species || !$location) {
    wp_send_json(['success' => false, 'message' => 'Please fill in every field.']);
  }
  if (!is_email($email)) {
    wp_send_json(['success' => false, 'message' => 'That email address does not look right.']);
  }
  if (!$consent) {
    wp_send_json(['success' => false, 'message' => 'Please confirm the photograph is yours to publish.']);
  }

  $file = $_FILES['photo'] ?? null;
  if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    wp_send_json(['success' => false, 'message' => 'Please attach a JPEG photograph.']);
  }
  if ($file['size'] > DB_PHOTO_MAX_BYTES) {
    wp_send_json(['success' => false, 'message' => 'That file is over 10 MB. Please send a smaller JPEG.']);
  }
  // Extension, declared type and actual content must all say JPEG.
  $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], ['jpg|jpeg' => 'image/jpeg']);
  $size = @getimagesize($file['tmp_name']);
  if (empty($check['type']) || $check['type'] !== 'image/jpeg' || !$size || $size[2] !== IMAGETYPE_JPEG) {
    wp_send_json(['success' => false, 'message' => 'Please send a JPEG photograph (.jpg).']);
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  $post_id = wp_insert_post([
    'post_type'   => 'db_gallery_photo',
    'post_title'  => $species . ($location ? ' — ' . $location : ''),
    'post_status' => 'pending',
  ], true);
  if (is_wp_error($post_id)) {
    wp_send_json(['success' => false, 'message' => 'Something went wrong saving your photograph. Please try again later.']);
  }

  $attachment_id = media_handle_upload('photo', $post_id, ['post_title' => $species]);
  if (is_wp_error($attachment_id)) {
    wp_delete_post($post_id, true);
    wp_send_json(['success' => false, 'message' => 'That photograph could not be read. Please try another JPEG.']);
  }

  update_field('field_gallery_photo', $attachment_id, $post_id);
  update_field('field_gallery_species_name', $species, $post_id);
  update_field('field_gallery_photo_location', $location, $post_id);
  update_field('field_gallery_photographer', $name, $post_id);
  // Kept out of the ACF fields: only for replying to the submitter.
  update_post_meta($post_id, '_db_submitter_email', $email);
  update_post_meta($post_id, '_db_submitted_at', current_time('mysql'));

  $edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');
  $headers = ['Content-Type: text/html; charset=UTF-8', "Reply-To: $name <$email>"];
  wp_mail(
    db_notify_email('photos'),
    'Photograph submitted: ' . $species,
    '<p><strong>' . esc_html($species) . '</strong> by ' . esc_html($name) . ' (' . esc_html($email) . ')</p>'
    . '<p><strong>Where and when:</strong> ' . esc_html($location) . '</p>'
    . '<p>It is waiting as a pending Gallery entry. Publishing it puts it on the site and tells the photographer; '
    . 'moving it to Trash declines it, also with a note to them.</p>'
    . '<p><a href="' . esc_url($edit_link) . '">Review this submission</a></p>',
    $headers
  );
  wp_mail(
    $email,
    'We received your photograph — Deccan Birders',
    '<p>Hi ' . esc_html($name) . ',</p><p>Thank you for sending us your photograph of the '
    . esc_html($species) . '. A committee member will review it, usually within a week, and you will hear back either way.</p>'
    . '<p>— Deccan Birders</p>',
    ['Content-Type: text/html; charset=UTF-8']
  );

  wp_send_json(['success' => true]);
}

/**
 * Tell the photographer what happened to their submission: published, or
 * declined. Only fires for entries that came through the form (they are
 * the ones carrying a submitter address).
 */
add_action('transition_post_status', function($new_status, $old_status, $post) {
  if ($post->post_type !== 'db_gallery_photo' || $new_status === $old_status) return;
  $email = get_post_meta($post->ID, '_db_submitter_email', true);
  if (!$email || !is_email($email)) return;
  $name = get_field('photographer', $post->ID) ?: 'there';
  $headers = ['Content-Type: text/html; charset=UTF-8'];

  if ($new_status === 'publish' && !get_post_meta($post->ID, '_db_published_notified', true)) {
    update_post_meta($post->ID, '_db_published_notified', 1);
    wp_mail($email, 'Your photograph is in the gallery — Deccan Birders',
      '<p>Hi ' . esc_html($name) . ',</p><p>Your photograph is now in the Deccan Birders gallery, credited to you: '
      . '<a href="' . esc_url(home_url('/gallery/')) . '">see it here</a>.</p><p>Thank you for sharing it.</p><p>— Deccan Birders</p>',
      $headers);
  }

  if ($new_status === 'trash' && $old_status === 'pending') {
    wp_mail($email, 'About the photograph you sent — Deccan Birders',
      '<p>Hi ' . esc_html($name) . ',</p><p>Thank you for sending us your photograph. On this occasion the committee '
      . 'has not taken it for the gallery. Please do keep sending them — we would like to see more.</p><p>— Deccan Birders</p>',
      $headers);
  }
}, 10, 3);

/** Who sent it, in the Gallery list, so pending entries are reviewable at a glance. */
add_filter('manage_db_gallery_photo_posts_columns', function($cols) {
  $cols['db_submitter'] = 'Submitted by';
  return $cols;
});
add_action('manage_db_gallery_photo_posts_custom_column', function($col, $post_id) {
  if ($col !== 'db_submitter') return;
  $email = get_post_meta($post_id, '_db_submitter_email', true);
  echo $email ? esc_html($email) : '—';
}, 10, 2);

add_action('wp_ajax_nopriv_db_sighting_report', 'db_handle_sighting_report');
add_action('wp_ajax_db_sighting_report', 'db_handle_sighting_report');
function db_handle_sighting_report() {
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'db_contact_nonce')) {
    wp_send_json(['success' => false, 'message' => 'Security check failed.']);
  }
  // The design merges "report a sighting" and "volunteer" into one short
  // form: a single free-text "species and location" field plus a
  // "what would you like to help with" choice. There is deliberately no
  // name or email field, so submissions are anonymous and there is no
  // address to send an acknowledgement to or to set as Reply-To.
  $species_location = sanitize_text_field($_POST['species_location'] ?? '');
  $help_with        = sanitize_text_field($_POST['help_with'] ?? '');
  if (!$species_location) {
    wp_send_json(['success' => false, 'message' => 'Please tell us the species and location.']);
  }
  $to      = 'info@deccanbirders.org';
  $headers = ['Content-Type: text/html; charset=UTF-8'];
  $body = '<p><strong>Species and location:</strong> ' . esc_html($species_location) . '</p>'
    . ($help_with ? '<p><strong>Would like to help with:</strong> ' . esc_html($help_with) . '</p>' : '')
    . '<p><em>Submitted anonymously — the form collects no contact details.</em></p>';
  wp_mail($to, 'Sighting / volunteer: ' . $species_location, $body, $headers);
  wp_send_json(['success' => true]);
}

/* -----------------------------------------------------------------------
 * 8. Shortcodes
 * ---------------------------------------------------------------------*/
add_shortcode('db_bird_fact',        'db_sc_bird_fact');
add_shortcode('db_birding_joke',     'db_sc_birding_joke');
add_shortcode('db_milestones',       'db_sc_milestones');
add_shortcode('db_committee_grid',   'db_sc_committee');
add_shortcode('db_photo_gallery',    'db_sc_gallery');
add_shortcode('db_pitta_accordion',  'db_sc_pitta');
add_shortcode('db_membership_tiers', 'db_sc_tiers');
add_shortcode('db_aims',             'db_sc_aims');

function db_sc_bird_fact($atts) {
  ob_start();
  get_template_part('template-parts/shortcode-bird-fact');
  return ob_get_clean();
}

function db_sc_birding_joke($atts) {
  ob_start();
  get_template_part('template-parts/shortcode-birding-joke');
  return ob_get_clean();
}

function db_sc_milestones($atts) {
  ob_start();
  get_template_part('template-parts/shortcode-milestones');
  return ob_get_clean();
}

function db_sc_committee($atts) {
  ob_start();
  get_template_part('template-parts/shortcode-committee');
  return ob_get_clean();
}

function db_sc_gallery($atts) {
  ob_start();
  get_template_part('template-parts/shortcode-photo-gallery');
  return ob_get_clean();
}

function db_sc_pitta($atts) {
  ob_start();
  get_template_part('template-parts/shortcode-pitta-accordion');
  return ob_get_clean();
}

function db_sc_tiers($atts) {
  ob_start();
  get_template_part('template-parts/shortcode-membership-tiers');
  return ob_get_clean();
}

function db_sc_aims($atts) {
  ob_start();
  get_template_part('template-parts/shortcode-aims');
  return ob_get_clean();
}

/* -----------------------------------------------------------------------
 * 8a. Template helpers
 * ---------------------------------------------------------------------*/

/**
 * Renders the wide banner image that sits under the page heading on most
 * inner pages in the design. Falls back to the styled "Photo coming soon"
 * placeholder when the ACF image field is empty (the media library is
 * currently empty, so that's the normal case for now).
 */
/**
 * $height is a CSS height value — the design gives each page its own
 * clamp() rather than a shared aspect ratio.
 */
function db_hero_image($field, $post_id, $fallback_alt = '', $height = 'clamp(200px, 34vw, 400px)') {
  $img = $post_id ? get_field($field, $post_id) : null;
  if (!empty($img['url'])) {
    printf(
      '<img src="%s" alt="%s" style="width:100%%;height:%s;object-fit:cover;border-radius:var(--radius-card);">',
      esc_url($img['url']),
      esc_attr($img['alt'] ?: $fallback_alt),
      esc_attr($height)
    );
    return;
  }
  printf(
    '<div class="img-placeholder" aria-label="Photo coming soon" style="height:%s;"><span>Photo coming soon</span></div>',
    esc_attr($height)
  );
}

/* -----------------------------------------------------------------------
 * 8b. Same-origin proxy for the deccan-birders-api Vercel endpoints
 *
 * The Vercel API's CORS config only allows the eventual production domain
 * (deccanbirders.org), so a browser on this Hostinger preview domain gets
 * every /api/events, /api/sightings and /api/videos response blocked by
 * CORS even though the API itself returns good data. Proxying through a
 * REST route on this same site sidesteps CORS entirely (same origin) and
 * doubles as a server-side cache, so eBird/Calendar/YouTube aren't hit on
 * every page view.
 * ---------------------------------------------------------------------*/
function db_api_base() {
  return rtrim(get_option('db_api_base_url', 'https://deccan-birders-api.vercel.app'), '/');
}

/**
 * Fetch a Vercel API path, cached in a transient. $allowed_params whitelists
 * which query args from the incoming request are forwarded upstream.
 */
/**
 * A short-window cap on the db/v1 routes, applied per caller.
 *
 * These routes are where visitors' browsers actually arrive, so this is
 * the place a per-IP limit means anything — the proxy behind them only
 * ever sees this one server. A page view costs four to six calls, so 60
 * a minute is about ten page loads from a single address: far above a
 * reader, well below anyone harvesting the lot.
 */
function db_rest_rate_limited($max_per_minute = 60) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!$ip) return false;
  $key = 'db_rest_' . md5($ip . '|' . floor(time() / MINUTE_IN_SECONDS));
  $count = (int) get_transient($key);
  if ($count >= $max_per_minute) return true;
  set_transient($key, $count + 1, 2 * MINUTE_IN_SECONDS);
  return false;
}

add_filter('rest_pre_dispatch', function($result, $server, WP_REST_Request $request) {
  if (strpos($request->get_route(), '/db/v1/') !== 0) return $result;
  if (!db_rest_rate_limited()) return $result;
  return new WP_Error(
    'db_too_many_requests',
    __('Too many requests — please wait a moment and reload.', 'deccan-birders'),
    ['status' => 429]
  );
}, 10, 3);

/**
 * The shared secret the proxy expects, sent on every request to it.
 *
 * The proxy holds our eBird key, and eBird holds us responsible for
 * every call made with it. Only this server calls the proxy — browsers
 * go to /wp-json/db/v1 instead — so a header nobody else knows is enough
 * to keep the proxy ours. Empty until it is set in Site Settings, which
 * leaves the proxy open exactly as it was.
 */
function db_proxy_headers() {
  $secret = db_setting('db_proxy_secret');
  return $secret ? ['x-db-key' => $secret] : [];
}

function db_proxy_fetch($path, WP_REST_Request $request, array $allowed_params, $ttl) {
  $query = [];
  foreach ($allowed_params as $param) {
    $val = $request->get_param($param);
    if ($val !== null && $val !== '') $query[$param] = $val;
  }
  ksort($query);
  $cache_key = 'db_proxy_' . md5($path . '?' . http_build_query($query));

  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $url = db_api_base() . $path . (($query) ? ('?' . http_build_query($query)) : '');
  // Country-wide "recent" pulls (used as the base for the notable/IUCN
  // filter, see below) can return a large payload, so allow a bit more
  // time than a typical small proxied request.
  $res = wp_remote_get($url, ['timeout' => 20, 'headers' => db_proxy_headers()]);

  if (is_wp_error($res)) {
    return ['error' => true, 'message' => $res->get_error_message()];
  }
  $code = wp_remote_retrieve_response_code($res);
  $body = json_decode(wp_remote_retrieve_body($res), true);

  if ($code !== 200 || !is_array($body)) {
    return ['error' => true, 'message' => 'Upstream API returned HTTP ' . $code];
  }
  if (empty($body['error'])) {
    set_transient($cache_key, $body, $ttl);
  }
  return $body;
}

/**
 * Best-effort IUCN Red List status for species that turn up in eBird
 * checklists across India, keyed by eBird's common name. Not exhaustive —
 * covers species reasonably likely to be seen on Deccan Birders' trips
 * plus nationally significant threatened species. Review/expand against
 * the current IUCN Red List (iucnredlist.org) periodically; eBird's API
 * does not provide conservation status itself, so this has to be
 * maintained by hand.
 */
function db_iucn_watchlist() {
  return [
    // Critically Endangered
    'White-rumped Vulture'    => 'CR',
    'Indian Vulture'          => 'CR',
    'Red-headed Vulture'      => 'CR',
    'Slender-billed Vulture'  => 'CR',
    'Great Indian Bustard'    => 'CR',
    'Sociable Lapwing'        => 'CR',
    'Spoon-billed Sandpiper'  => 'CR',
    'White-bellied Heron'     => 'CR',
    "Jerdon's Courser"        => 'CR',
    // Endangered
    'Egyptian Vulture'        => 'EN',
    'Steppe Eagle'            => 'EN',
    "Pallas's Fish-Eagle"     => 'EN',
    'Black-bellied Tern'      => 'EN',
    'Indian Skimmer'          => 'EN',
    'Greater Adjutant'        => 'EN',
    'Lesser Florican'         => 'EN',
    'Yellow-breasted Bunting' => 'EN',
    'Nordmann\'s Greenshank'  => 'EN',
    'Black-necked Crane'      => 'EN',
    // Vulnerable
    'Sarus Crane'             => 'VU',
    'Lesser Adjutant'         => 'VU',
    'Woolly-necked Stork'     => 'VU',
    'Common Pochard'          => 'VU',
    'Marbled Duck'            => 'VU',
    'Andaman Teal'            => 'VU',
    'Nilgiri Wood-Pigeon'     => 'VU',
    // Near Threatened
    'Painted Stork'           => 'NT',
    'Black-headed Ibis'       => 'NT',
    'Oriental Darter'         => 'NT',
    'River Tern'              => 'NT',
    'Black-necked Stork'      => 'NT',
    'Eurasian Curlew'         => 'NT',
    'Ferruginous Duck'        => 'NT',
    'Lesser Fish-Eagle'       => 'NT',
    'Grey-headed Fish-Eagle'  => 'NT',
    'Black-tailed Godwit'     => 'NT',
    'Eurasian Oystercatcher'  => 'NT',
    'Alexandrine Parakeet'    => 'NT',
  ];
}

/**
 * Filters a list of sighting records (mapRecord() shape from the API) down
 * to species on the IUCN watchlist, tags each with its status, and sorts
 * most-threatened first — within the home states before the rest of India,
 * so db_sightings_regional()'s ordering survives this sort.
 */
function db_filter_notable_by_iucn(array $records) {
  $watchlist = db_iucn_watchlist();
  $rank = ['CR' => 0, 'EN' => 1, 'VU' => 2, 'NT' => 3];

  $notable = [];
  foreach ($records as $r) {
    $species = $r['species'] ?? '';
    if (!isset($watchlist[$species])) continue;
    $r['iucnStatus'] = $watchlist[$species];
    $r['rare'] = true;
    $notable[] = $r;
  }

  usort($notable, function($a, $b) use ($rank) {
    return [empty($a['local']), $rank[$a['iucnStatus']] ?? 9]
       <=> [empty($b['local']), $rank[$b['iucnStatus']] ?? 9];
  });

  return $notable;
}

/* -----------------------------------------------------------------------
 * Home-state priority for eBird data.
 *
 * The society is based in Hyderabad, so Telangana and Andhra Pradesh
 * records come first and the rest of India follows. eBird has no "order
 * by region" option, so each state is fetched as its own region and the
 * results are merged here: the same upstream calls as before, each still
 * cached by db_proxy_fetch(), just combined in priority order. Records
 * carry 'local' => true/false.
 * ---------------------------------------------------------------------*/
function db_home_regions() {
  // eBird calls Telangana IN-TS, not the ISO 3166-2 code IN-TG — an IN-TG
  // request is accepted but comes back empty, with no error to notice.
  return ['IN-TS', 'IN-AP'];
}

/** Key used to spot the same record arriving from two regions. */
function db_sighting_dedupe_key($tab, array $r) {
  if ($tab === 'hotspots') return $r['locId'] ?? wp_json_encode($r);
  return ($r['species'] ?? '') . '|' . ($r['locId'] ?? '') . '|' . ($r['when'] ?? '');
}

/** The eight most-matched pages (1-based), listed in reading order. */
function db_pitta_page_numbers(array $page_hits) {
  $pages = array_map(fn($i) => $i + 1, array_slice(array_keys($page_hits), 0, 8));
  sort($pages, SORT_NUMERIC);
  return $pages;
}

/**
 * Slice a record list for the requested page. Without a per_page param
 * the whole list comes back, so older callers keep working.
 * Returns ['data' => …, 'page' => n, 'pages' => n, 'total' => n].
 */
/**
 * One card per species for the Notable tab.
 *
 * A threatened bird at a well-watched lake can fill the page with the
 * same species twenty times over. Each species is folded into a single
 * record — its most recent sighting — carrying the other places it has
 * been seen in 'others', which the page reveals behind a + button.
 *
 * Grouping happens before paging, so a page is twenty species rather
 * than twenty observations of three.
 */
function db_group_notable_by_species(array $records, $days = 30) {
  $cutoff = strtotime("-{$days} days");

  $by_species = [];
  $seen = [];
  foreach ($records as $r) {
    $when = strtotime((string) ($r['when'] ?? ''));
    if (!$when || $when < $cutoff) continue;

    // The same observation can arrive twice; one bird at one place at
    // one moment is one record.
    $key = ($r['species'] ?? '') . '|' . ($r['locId'] ?? '') . '|' . ($r['when'] ?? '') . '|' . ($r['count'] ?? '');
    if (isset($seen[$key])) continue;
    $seen[$key] = true;

    $by_species[$r['species'] ?? ''][] = $r;
  }

  $grouped = [];
  foreach ($by_species as $species => $sightings) {
    // Newest first, so the card leads with the latest sighting.
    usort($sightings, fn($a, $b) => strcmp((string) ($b['when'] ?? ''), (string) ($a['when'] ?? '')));

    $primary = array_shift($sightings);

    // The extra places, newest sighting of each kept.
    $others = [];
    foreach ($sightings as $s) {
      $loc = $s['locId'] ?? $s['locality'] ?? '';
      if ($loc === '' || $loc === ($primary['locId'] ?? '') || isset($others[$loc])) continue;
      $others[$loc] = [
        'locality' => $s['locality'] ?? '',
        'when'     => $s['when'] ?? '',
        'count'    => $s['count'] ?? '?',
        'local'    => !empty($s['local']),
      ];
    }
    $primary['others']      = array_values($others);
    $primary['totalRecords'] = count($sightings) + 1;
    $grouped[] = $primary;
  }

  // Most recently seen species first.
  usort($grouped, fn($a, $b) => strcmp((string) ($b['when'] ?? ''), (string) ($a['when'] ?? '')));
  return $grouped;
}

function db_sightings_page(WP_REST_Request $request, array $records) {
  $total = count($records);
  $per_page = (int) $request->get_param('per_page');
  if ($per_page < 1) {
    return ['data' => $records, 'page' => 1, 'pages' => 1, 'total' => $total];
  }
  $per_page = min($per_page, 200);
  $pages = max(1, (int) ceil($total / $per_page));
  $page = min(max(1, (int) $request->get_param('page') ?: 1), $pages);
  return [
    'data'  => array_slice($records, ($page - 1) * $per_page, $per_page),
    'page'  => $page,
    'pages' => $pages,
    'total' => $total,
  ];
}

/**
 * Each region arrives sorted on its own, so a merged group (Telangana +
 * Andhra Pradesh) has to be re-sorted the way that tab sorts.
 */
function db_sort_sightings($tab, array $records) {
  if ($tab === 'hotspots') {
    usort($records, fn($a, $b) => (int) ($b['species'] ?? 0) <=> (int) ($a['species'] ?? 0));
  } elseif ($tab === 'onthisday') {
    usort($records, fn($a, $b) => (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0));
  } else {
    usort($records, fn($a, $b) => strcmp((string) ($b['when'] ?? ''), (string) ($a['when'] ?? '')));
  }
  return $records;
}

/**
 * Fetch $tab for each home state and then for the wider region, and merge.
 * $limits caps how many records each group contributes ([local, rest]);
 * null means no cap. Returns the merged list, or a proxy error array.
 */
function db_sightings_regional(WP_REST_Request $request, $tab, $ttl, array $allowed_params, array $limits = [null, null]) {
  $base = $request->get_param('region') ?: 'IN';
  $groups = [];
  $error = null;

  foreach ([db_home_regions(), [$base]] as $is_rest => $regions) {
    $records = [];
    foreach ($regions as $region) {
      $sub = new WP_REST_Request('GET', $request->get_route());
      $sub->set_query_params(array_merge($request->get_query_params(), ['region' => $region, 'tab' => $tab]));
      $res = db_proxy_fetch('/api/sightings', $sub, $allowed_params, $ttl);
      if (!empty($res['error'])) { $error = $res; continue; }
      foreach ($res['data'] ?? [] as $r) {
        $r['local'] = !$is_rest;
        $records[] = $r;
      }
    }
    $groups[] = db_sort_sightings($tab, $records);
  }

  // Every region failed upstream: pass the error through rather than an
  // empty list, so the page can say so instead of showing "no sightings".
  if ($error && !$groups[0] && !$groups[1]) return $error;

  $merged = [];
  $seen = [];
  foreach ($groups as $i => $records) {
    $kept = 0;
    foreach ($records as $r) {
      $key = db_sighting_dedupe_key($tab, $r);
      if (isset($seen[$key])) continue;
      if ($limits[$i] !== null && $kept >= $limits[$i]) break;
      $seen[$key] = true;
      $merged[] = $r;
      $kept++;
    }
  }
  return $merged;
}

/**
 * The host's .htaccess has a blanket "ExpiresDefault access plus 1 week"
 * mod_expires rule that applies to every response without its own
 * Cache-Control, including these dynamic JSON endpoints — a browser that
 * hit /wp-json/db/v1/sightings once would keep serving that exact response
 * from its disk cache for 7 days, completely ignoring our server-side
 * transient TTLs (and any subsequent bug fix) until the cache expired.
 * Explicitly setting a real Cache-Control header here overrides that.
 */
function db_rest_no_cache(WP_REST_Response $response) {
  $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
  $response->header('Pragma', 'no-cache');
  $response->header('Expires', '0');
  return $response;
}

/* -----------------------------------------------------------------------
 * Trip coordinators
 *
 * Calendar invitations end with the committee members to ring, as
 * "Gowthama Poludasu - 9440910967" (sometimes several lines, sometimes a
 * +91 prefix or spaced digits). The Vercel API passes the description
 * through untouched, so the names and numbers are pulled out here and
 * added to each event for the Events page to show.
 * ---------------------------------------------------------------------*/
/** The invitation's text, with tags turned into line breaks. */
function db_event_text($description) {
  $text = preg_replace('#<(br|/p|/div|/li|/h[1-6])[^>]*>#i', "\n", (string) $description);
  $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
  return str_replace("\xc2\xa0", ' ', $text);
}

/** A 10-digit Indian mobile from whatever grouping the writer used. */
function db_event_phone_digits($raw) {
  $digits = preg_replace('/\D/', '', $raw);
  if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
  if (strlen($digits) === 11 && $digits[0] === '0') $digits = substr($digits, 1);
  return strlen($digits) === 10 && in_array($digits[0], ['6', '7', '8', '9'], true) ? $digits : '';
}

/**
 * The committee members to ring, read out of the invitation. Writers vary:
 * "Gowthama Poludasu - 9440910967", "K Sudhir 8977613055", a +91 prefix,
 * digits grouped with spaces, two on one line, or the number on the line
 * after the name — all of which turn up in real invitations, so all are
 * accepted. A name is letters, dots and spaces; a number is a 10-digit
 * Indian mobile, which keeps lines like "Rs. 100/- per head" out.
 */
function db_event_coordinators($description) {
  if (!$description) return [];
  $text = db_event_text($description);

  // Anything after the "coordinators" line is the contact list; without
  // such a heading, scan the whole description.
  if (preg_match('/coordinator[s]?\b/i', $text, $m, PREG_OFFSET_CAPTURE)) {
    $text = substr($text, $m[0][1]);
  }

  $name_re = '[\p{L}][\p{L}\.\s]{1,39}';
  $num_re  = '(?:(?:\+?91|0)[\s-]?)?[6-9]\d{4}[\s-]?\d{5}';
  // Words that introduce the list rather than name anybody, so a stray
  // number on the next line can't be attributed to "Coordinators".
  $heading_re = '/\b(coordinator|committee|member|contact|information|call|details|number)/i';

  $found = [];
  $previous_name = '';
  foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
    $line = trim(preg_replace('/\s+/u', ' ', $line));
    // Writers often label the line itself — "Trip Coordinator: K Sudhir
    // 8977613055" — so drop any leading label before reading it.
    $line = trim(preg_replace('/^(?:trip|event)?\s*(?:coordinators?|contacts?|call)\s*[-–—:]*\s*/iu', '', $line));
    if ($line === '' || mb_strlen($line) > 160) continue;

    // A line holding only a number belongs to the name above it.
    if (preg_match('/^(' . $num_re . ')$/u', $line, $m) && $previous_name !== '') {
      $digits = db_event_phone_digits($m[1]);
      if ($digits) $found[$digits] = $previous_name;
      $previous_name = '';
      continue;
    }

    // "Name 99999 99999" / "Name - 99999 99999", possibly several per line.
    $matched = false;
    foreach (preg_split('/\s*(?:,|;|&|\band\b)\s*/iu', $line) as $part) {
      if (!preg_match('/^(' . $name_re . ')\s*(?:[-–—:]\s*)?(' . $num_re . ')$/u', trim($part), $m)) continue;
      $digits = db_event_phone_digits($m[2]);
      if (!$digits) continue;
      $found[$digits] = trim($m[1]);
      $matched = true;
    }
    if ($matched) { $previous_name = ''; continue; }

    // Remember a plain name in case the next line is its number.
    $previous_name = (preg_match('/^(' . $name_re . ')$/u', $line) && !preg_match($heading_re, $line))
      ? $line
      : '';
  }

  $out = [];
  foreach (array_slice($found, 0, 4, true) as $digits => $name) {
    $out[] = [
      'name'  => $name,
      'phone' => substr($digits, 0, 5) . ' ' . substr($digits, 5),
      'tel'   => '+91' . $digits,
    ];
  }
  return $out;
}

/**
 * The invitation as written, ready to show inside a card. Google Calendar
 * gives us HTML, so it is run through wp_kses down to text markup and
 * links — no styles, images or scripts — bare URLs are made clickable,
 * and every link opens in a new tab. Invitations carry map links and the
 * membership page, and those should be usable rather than plain text.
 */
function db_event_note_html($description) {
  if (!$description) return '';

  $allowed = [
    'a'      => ['href' => true, 'title' => true],
    'p'      => [],
    'br'     => [],
    'strong' => [], 'b' => [],
    'em'     => [], 'i' => [], 'u' => [],
    'ul'     => [], 'ol' => [], 'li' => [],
    'span'   => [],
    'div'    => [],
  ];
  $html = wp_kses((string) $description, $allowed);
  $html = make_clickable($html);

  // Every link leaves the site, so force the same treatment on all of
  // them, whether they came from the calendar or from make_clickable().
  $html = preg_replace('/\s(?:target|rel)="[^"]*"/i', '', $html);
  $html = str_ireplace('<a ', '<a target="_blank" rel="noopener nofollow" ', $html);

  return trim($html);
}

/**
 * The invitation as one line of plain text, for the pattern matching
 * below. Links are dropped: the calendar splits a URL across two anchors,
 * so leaving them in breaks sentences mid-word.
 */
function db_event_prose($description) {
  $text = db_event_text($description);
  $text = preg_replace('#https?://\S*#i', ' ', $text);
  return trim(preg_replace('/\s+/u', ' ', $text));
}

/**
 * Birds the invitation says to expect, as a list. Written as a run-on
 * sentence — "Birds to expect Whistling Ducks, Cotton-Pygmy goose, Jungle
 * Bushquail…" — so this takes what follows the cue up to the full stop and
 * splits it. Returns [] when the invitation doesn't list any, which is the
 * point: a card shows the row only when there is something to put in it.
 */
function db_event_species($description) {
  $text = db_event_prose($description);
  if (!preg_match('/\b(?:birds?|species)\s+(?:to\s+expect|expected|one\s+can\s+expect|likely)\b[:,]?\s*(.+?)(?:\.\s|$)/iu', $text, $m)) {
    return [];
  }
  $names = preg_split('/\s*(?:,|;|\band\b|&)\s*/iu', $m[1]);
  $out = [];
  foreach ($names as $name) {
    $name = trim($name, " \t\n\r\0\x0B.-");
    $name = preg_replace('/^(?:the|a|an)\s+/i', '', $name);
    // A species name, not the sentence carrying on past the list: these
    // usually trail off with "and other wetland and woodland birds".
    if ($name === '' || str_word_count($name) > 5 || mb_strlen($name) > 46) continue;
    if (!preg_match('/^[\p{L}][\p{L}\'\-\s\(\)]*$/u', $name)) continue;
    if (preg_match('/^(?:other|various|many|several|etc|more)\b/i', $name)) break;
    if (preg_match('/\b(?:birds|species|visitors|migrants)$/i', $name)) break;
    $out[] = $name;
    if (count($out) >= 20) break;
  }
  return $out;
}

/**
 * Where people gather to travel together, and where the trip itself
 * starts: "may meet at Taaza Tiffins, Thumkunta by 5:15 AM <link> … those
 * who wish to join directly at the final stop can reach at Lakshimapur
 * Lake by 6:00 AM <link>". Each comes back as name/time/map, with '' for
 * anything the invitation doesn't say.
 */
function db_event_stops($description) {
  $text = db_event_prose($description);
  $maps = db_event_map_links($description);

  $stop = function($pattern) use ($text) {
    if (!preg_match($pattern, $text, $m)) return ['name' => '', 'time' => ''];
    $name = trim(preg_replace('/\s+/u', ' ', $m['name'] ?? ''), " ,.-");
    return [
      'name' => mb_strlen($name) <= 70 ? $name : '',
      'time' => isset($m['time']) ? db_event_tidy_time($m['time']) : '',
    ];
  };

  $meet  = $stop('/\bmeet\s+at\s+(?<name>[^.]{3,70}?)\s+by\s+(?<time>\d{1,2}[:.]?\d{0,2}\s*(?:am|pm))/iu');
  $final = $stop('/\b(?:final\s+stop|directly)\b[^.]{0,60}?\breach\s+at\s+(?<name>[^.]{3,70}?)\s+by\s+(?<time>\d{1,2}[:.]?\d{0,2}\s*(?:am|pm))/iu');

  $meet['map']  = $maps ? reset($maps) : '';
  $final['map'] = db_event_destination_map($description);
  if ($final['map'] === $meet['map'] && count($maps) < 2) $meet['map'] = '';

  return ['meet' => $meet, 'final' => $final];
}

function db_event_tidy_time($raw) {
  $raw = strtolower(trim(preg_replace('/\s+/u', ' ', $raw)));
  return str_replace(['.', ' am', ' pm'], [':', ' am', ' pm'], $raw);
}

/** Every map link in the invitation, in the order they appear. */
function db_event_map_links($description) {
  $text = db_event_text($description);
  preg_match_all('#https?://(?:maps\.app\.goo\.gl|(?:www\.)?google\.[a-z.]+/maps)[^\s<>"\']*#i', $text, $m);
  return array_values(array_unique(array_map('db_event_clean_url', $m[0])));
}

/**
 * The practical details, for the "at a glance" panel: when to be where,
 * what it costs a non-member, and what to bring. Each is only included
 * when the invitation actually says it.
 */
function db_event_facts($description, $start_time = '') {
  $text  = db_event_prose($description);
  $stops = db_event_stops($description);
  $facts = [];

  if ($stops['meet']['name'] || $start_time) {
    $facts[] = [
      'label' => 'Starts',
      'value' => $stops['meet']['time'] ?: $start_time,
      'note'  => $stops['meet']['name'] ? 'Meet at ' . $stops['meet']['name'] : '',
    ];
  }
  if ($stops['final']['name']) {
    $facts[] = [
      'label' => 'Joining at the site',
      'value' => $stops['final']['time'],
      'note'  => $stops['final']['name'],
    ];
  }
  if (preg_match('/(?:Rs\.?|₹|INR)\s*(\d{2,5})\s*\/?-?\s*(per\s+head|per\s+person|each)?/iu', $text, $m)) {
    $facts[] = [
      'label' => 'Non-member contribution',
      'value' => '₹' . $m[1] . (empty($m[2]) ? '' : ' ' . strtolower($m[2])),
      'note'  => preg_match('/t-?shirts?|caps?/i', $text) ? 'Carry cash — T-shirts and caps on sale' : '',
    ];
  }
  if (preg_match('/\bcarry\s+([^.]{3,60})/iu', $text, $m)) {
    $bring = trim(preg_replace('/\s+/u', ' ', $m[1]));
    $bring = preg_replace('/\s+(to|and enough|enough)\s+keep.*$/iu', '', $bring);
    $facts[] = [
      'label' => 'Bring',
      'value' => ucfirst(rtrim($bring, ' ,')),
      'note'  => preg_match('/half[\s-]day/i', $text) ? 'Half-day trip' : '',
    ];
  }
  return $facts;
}

/**
 * The map link for where the trip actually ends up. Invitations often carry
 * two: a meeting point to convoy from, and the final stop for people joining
 * directly ("Those who wish to join directly at the final stop can reach at
 * Lakshimapur Lake … <link>"). The card's pin should be the final stop, so
 * prefer the last link introduced that way, and otherwise the last link.
 */
function db_event_destination_map($description) {
  if (!$description) return '';
  $text = db_event_text($description);
  if (!preg_match_all('#https?://(?:maps\.app\.goo\.gl|(?:www\.)?google\.[a-z.]+/maps)[^\s<>"\']*#i',
      $text, $m, PREG_OFFSET_CAPTURE)) {
    return '';
  }

  $links = $m[0];
  foreach (array_reverse($links) as [$url, $offset]) {
    $lead = substr($text, max(0, $offset - 220), min(220, $offset));
    if (preg_match('/\b(final|directly|destination|reach at|end point)\b/i', $lead)) {
      return db_event_clean_url($url);
    }
  }
  return db_event_clean_url(end($links)[0]);
}

/** Trailing punctuation from prose ("… <link>, as it takes 45 mins"). */
function db_event_clean_url($url) {
  return rtrim($url, '.,;:)]');
}

add_action('rest_api_init', function() {
  register_rest_route('db/v1', '/events', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function(WP_REST_Request $request) {
      // A finished trip never changes, so past events can sit for hours;
      // an upcoming one gets edited up to the morning of the walk.
      $ttl = $request->get_param('scope') === 'past' ? 6 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS;
      $res = db_proxy_fetch('/api/events', $request, ['scope'], $ttl);
      if (!empty($res['data']) && is_array($res['data'])) {
        foreach ($res['data'] as &$event) {
          $event['coordinators'] = db_event_coordinators($event['note'] ?? '');
          $event['mapUrl']       = db_event_destination_map($event['note'] ?? '');
          $event['noteHtml']     = db_event_note_html($event['note'] ?? '');
          $event['species']      = db_event_species($event['note'] ?? '');
          $event['stops']        = db_event_stops($event['note'] ?? '');
          $event['facts']        = db_event_facts($event['note'] ?? '');
        }
        unset($event);
      }
      return db_rest_no_cache(rest_ensure_response($res));
    },
  ]);

  register_rest_route('db/v1', '/sightings', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function(WP_REST_Request $request) {
      $tab = $request->get_param('tab');
      // eBird checklists trickle in over days, not minutes, so six hours
      // is fresh enough and keeps the page quick; the reference data
      // (hotspots, species lists, this-day-in-history) holds for a day.
      // The taxonomy is the species list itself, which changes once a
      // year at most, so it sits with the other reference data.
      $ttl = in_array($tab, ['hotspots', 'hotspot_species', 'onthisday', 'taxonomy'], true) ? DAY_IN_SECONDS : 6 * HOUR_IN_SECONDS;

      // "Notable" here means IUCN Near Threatened or worse — a different
      // definition than eBird's own "notable" (locally rare/reviewed),
      // which would miss a species that's globally threatened but common
      // in this specific region (e.g. Painted Stork). So instead of
      // proxying eBird's /recent/notable, pull the full /recent feed and
      // filter it against our own conservation-status watchlist.
      // merge=0 asks for one region, unmerged — "on this day" uses it to
      // pull its regions in parallel from the browser instead of waiting
      // for all of them here (30 upstream eBird calls, ~11s cold).
      if ($request->get_param('merge') !== '0') {
        // "Notable" here means IUCN Near Threatened or worse — a different
        // definition than eBird's own "notable" (locally rare/reviewed),
        // which would miss a species that's globally threatened but common
        // in this specific region (e.g. Painted Stork). So instead of
        // proxying eBird's /recent/notable, pull the full /recent feed and
        // filter it against our own conservation-status watchlist.
        if ($tab === 'notable') {
          $recent = db_sightings_regional($request, 'recent', $ttl, ['region', 'tab']);
          if (!empty($recent['error'])) return db_rest_no_cache(rest_ensure_response($recent));
          $notable = db_group_notable_by_species(db_filter_notable_by_iucn($recent));
          return db_rest_no_cache(rest_ensure_response(db_sightings_page($request, $notable)));
        }

        // Telangana and Andhra Pradesh first, then the rest of India.
        // Hotspots is a fixed-length list, so each group gets half the
        // slots; the sightings feeds are paged instead.
        $limits = ['hotspots' => [5, 5], 'onthisday' => [10, 10]];
        if (isset($limits[$tab]) || $tab === 'recent') {
          $data = db_sightings_regional($request, $tab, $ttl, ['region', 'tab', 'm', 'd'], $limits[$tab] ?? [null, null]);
          if (!empty($data['error'])) return db_rest_no_cache(rest_ensure_response($data));
          return db_rest_no_cache(rest_ensure_response(db_sightings_page($request, $data)));
        }
      }

      // hotspot_species and the species lookup are already tied to one
      // place, so they pass straight through.
      return db_rest_no_cache(rest_ensure_response(db_proxy_fetch('/api/sightings', $request, ['region', 'tab', 'm', 'd', 'locId', 'speciesCode'], $ttl)));
    },
  ]);

  register_rest_route('db/v1', '/videos', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function(WP_REST_Request $request) {
      // With a YouTube key configured we fetch the channel here, which
      // pages through every upload; the Vercel API only ever returned the
      // newest 12. Without a key we fall back to it.
      if (db_youtube_key()) {
        return db_rest_no_cache(rest_ensure_response(db_youtube_videos()));
      }
      return db_rest_no_cache(rest_ensure_response(db_proxy_fetch('/api/videos', $request, [], 6 * HOUR_IN_SECONDS)));
    },
  ]);
});

/* -----------------------------------------------------------------------
 * 8c. YouTube uploads
 *
 * The full uploads playlist, fetched here rather than through the Vercel
 * API so it can page past the first 50 and carry the publish date and
 * description the cards show. Key and channel come from Site Settings (or
 * the DB_YOUTUBE_API_KEY / DB_YOUTUBE_CHANNEL_ID constants); the whole
 * list is cached for six hours.
 * ---------------------------------------------------------------------*/
function db_youtube_key() {
  if (defined('DB_YOUTUBE_API_KEY') && DB_YOUTUBE_API_KEY) return DB_YOUTUBE_API_KEY;
  return db_setting('youtube_api_key');
}

function db_youtube_channel_id() {
  if (defined('DB_YOUTUBE_CHANNEL_ID') && DB_YOUTUBE_CHANNEL_ID) return DB_YOUTUBE_CHANNEL_ID;
  return db_setting('youtube_channel_id') ?: 'UChYefSo9bbi-BBbRn9euCpg';
}

/** GET a YouTube Data API endpoint. Returns the decoded body or null. */
function db_youtube_get($endpoint, array $params) {
  $params['key'] = db_youtube_key();
  $url = 'https://www.googleapis.com/youtube/v3/' . $endpoint . '?' . http_build_query($params);
  $res = wp_remote_get($url, ['timeout' => 15]);
  if (is_wp_error($res)) return ['error' => true, 'message' => $res->get_error_message()];
  $body = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($body)) return ['error' => true, 'message' => 'Unreadable response from YouTube'];
  if (isset($body['error'])) return ['error' => true, 'message' => $body['error']['message'] ?? 'YouTube API error'];
  return $body;
}

function db_youtube_duration($iso) {
  if (!preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', (string) $iso, $m)) return '?';
  [$h, $min, $sec] = [(int) ($m[1] ?? 0), (int) ($m[2] ?? 0), (int) ($m[3] ?? 0)];
  return $h ? sprintf('%d:%02d:%02d', $h, $min, $sec) : sprintf('%d:%02d', $min, $sec);
}

function db_youtube_views($n) {
  $n = (int) $n;
  if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
  if ($n >= 1000) return round($n / 1000, 1) . 'K';
  return (string) $n;
}

/** Every upload, newest first. Cached; ['error' => true, …] on failure. */
function db_youtube_videos($force = false) {
  $cache_key = 'db_youtube_videos_' . md5(db_youtube_channel_id());
  if (!$force) {
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;
  }

  $channel = db_youtube_get('channels', ['part' => 'contentDetails', 'id' => db_youtube_channel_id()]);
  if (!empty($channel['error'])) return $channel;
  $uploads = $channel['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
  if (!$uploads) return ['error' => true, 'message' => 'YouTube channel not found'];

  // Page through the uploads playlist (50 at a time, max 10 pages).
  $items = [];
  $page_token = '';
  for ($page = 0; $page < 10; $page++) {
    $args = ['part' => 'snippet', 'playlistId' => $uploads, 'maxResults' => 50];
    if ($page_token) $args['pageToken'] = $page_token;
    $res = db_youtube_get('playlistItems', $args);
    if (!empty($res['error'])) {
      if (!$items) return $res;
      break; // keep the pages we already have
    }
    $items = array_merge($items, $res['items'] ?? []);
    $page_token = $res['nextPageToken'] ?? '';
    if (!$page_token) break;
  }
  if (!$items) return ['error' => true, 'message' => 'No uploads found'];

  // Duration and view count come from a second endpoint, 50 ids per call.
  $ids = array_values(array_filter(array_map(fn($i) => $i['snippet']['resourceId']['videoId'] ?? '', $items)));
  $meta = [];
  foreach (array_chunk($ids, 50) as $chunk) {
    $res = db_youtube_get('videos', ['part' => 'contentDetails,statistics', 'id' => implode(',', $chunk)]);
    if (!empty($res['error'])) break; // duration/views are optional
    foreach ($res['items'] ?? [] as $v) $meta[$v['id']] = $v;
  }

  $data = [];
  foreach ($items as $i) {
    $snippet = $i['snippet'] ?? [];
    $id = $snippet['resourceId']['videoId'] ?? '';
    if (!$id) continue;
    $m = $meta[$id] ?? [];
    $description = trim(preg_replace('/\s+/u', ' ', (string) ($snippet['description'] ?? '')));
    $data[] = [
      'videoId'     => $id,
      'title'       => $snippet['title'] ?? '',
      'description' => mb_substr($description, 0, 200),
      'published'   => $snippet['publishedAt'] ?? '',
      'thumbnail'   => $snippet['thumbnails']['medium']['url'] ?? "https://i.ytimg.com/vi/$id/mqdefault.jpg",
      'duration'    => isset($m['contentDetails']) ? db_youtube_duration($m['contentDetails']['duration']) : '?',
      'views'       => isset($m['statistics']) ? db_youtube_views($m['statistics']['viewCount'] ?? 0) : '?',
    ];
  }

  // Private and deleted uploads come back without a playable snippet.
  usort($data, fn($a, $b) => strcmp($b['published'], $a['published']));
  $payload = ['data' => $data, 'count' => count($data)];
  set_transient($cache_key, $payload, 6 * HOUR_IN_SECONDS);
  return $payload;
}

/* -----------------------------------------------------------------------
 * 8b. PITTA archive — catalog sync + full-text search
 *
 * data/pitta-catalog.csv lists every edition (year, month, edition, Drive
 * file id). tools/pitta-index/build_index.py turns those PDFs into
 * data/pitta-index.json (per-page text). "PITTA Archive → Sync from
 * catalog" mirrors the catalog into db_pitta posts, and
 * /wp-json/db/v1/pitta-search searches the index, returning only editions
 * that have a published post. Both sides key editions by
 * db_pitta_catalog_key(), which must match catalog_key() in the script.
 * ---------------------------------------------------------------------*/
function db_pitta_catalog_key($year, $month, $edition) {
  $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(remove_accents($edition))), '-');
  return sprintf('%d-%02d-%s', (int) $year, (int) $month, $slug);
}

/** Month names for 1–12 (index 0 unused). */
function db_pitta_months() {
  return ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
}

/** "Special - Talakona" → "Talakona"; Regular (or empty) → ''. */
function db_pitta_special_name($edition) {
  $edition = trim((string) $edition);
  if ($edition === '' || strcasecmp($edition, 'Regular') === 0) return '';
  return preg_replace('/^special\s*[-–—:]\s*/i', '', $edition);
}

/**
 * An issue is put together after its month has finished, so it appears
 * about 45 days later — September's issue goes up in November. Until
 * then a month is "coming soon" rather than missing, which is a
 * different thing to tell a reader.
 */
function db_pitta_issue_due($year, $month) {
  // 45 days after the last day of that month.
  $end = mktime(0, 0, 0, (int) $month + 1, 1, (int) $year);
  return $end + 45 * DAY_IN_SECONDS;
}

function db_pitta_is_awaited($year, $month) {
  return current_time('timestamp') < db_pitta_issue_due($year, $month);
}

/**
 * The cover thumbnail for an edition, or '' when there isn't one yet.
 * Covers are rendered from the PDFs by tools/pitta-index/build_covers.py
 * and named after the catalog key, so no lookup table is needed.
 */
function db_pitta_cover_url($catalog_key) {
  if (!$catalog_key) return '';
  $file = '/assets/pitta-covers/' . $catalog_key . '.jpg';
  return is_readable(get_template_directory() . $file)
    ? get_template_directory_uri() . $file
    : '';
}

function db_pitta_title($year, $month, $edition) {
  $when = db_pitta_months()[(int) $month] . ' ' . $year;
  $special = db_pitta_special_name($edition);
  return $special === '' ? "PITTA — $when" : "PITTA Special: $special — $when";
}

/** Catalog rows as assoc arrays, each with its 'key'. */
function db_pitta_catalog() {
  $path = get_template_directory() . '/data/pitta-catalog.csv';
  if (!is_readable($path)) return [];
  $fh = fopen($path, 'r');
  $head = fgetcsv($fh, 0, ',', '"', '');
  $rows = [];
  while (($cols = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    if (count($cols) !== count($head)) continue;
    $row = array_combine($head, array_map('trim', $cols));
    $row['key'] = db_pitta_catalog_key($row['year'], $row['month'], $row['edition']);
    $rows[] = $row;
  }
  fclose($fh);
  return $rows;
}

/**
 * Work out what a sync would do. Returns lists of [row, post_id|null]
 * for create/update, rows to skip with a reason, and posts to trash
 * (issues with no catalog_key — the old hand-made/sample entries).
 */
function db_pitta_sync_plan() {
  $existing = [];
  $legacy = [];
  foreach (get_posts(['post_type' => 'db_pitta', 'post_status' => ['publish', 'draft', 'pending', 'private'], 'posts_per_page' => -1]) as $p) {
    $key = get_post_meta($p->ID, 'catalog_key', true);
    if ($key) $existing[$key] = $p->ID; else $legacy[] = $p;
  }
  $plan = ['create' => [], 'update' => [], 'skip' => [], 'trash' => $legacy];
  foreach (db_pitta_catalog() as $row) {
    $month = (int) $row['month'];
    if (strtoupper($row['status']) !== 'IN DRIVE' || $row['drive_id'] === '') {
      $plan['skip'][] = [$row, 'Missing — no file yet'];
    } elseif ($month < 1 || $month > 12) {
      $plan['skip'][] = [$row, 'No month — date it in the catalog first'];
    } elseif (isset($existing[$row['key']])) {
      $plan['update'][] = [$row, $existing[$row['key']]];
    } else {
      $plan['create'][] = [$row, null];
    }
  }
  return $plan;
}

function db_pitta_apply_row(array $row, $post_id) {
  $post = [
    'post_type'   => 'db_pitta',
    'post_title'  => db_pitta_title($row['year'], $row['month'], $row['edition']),
    'post_status' => 'publish',
  ];
  if ($post_id) {
    $post['ID'] = $post_id;
    wp_update_post($post);
  } else {
    $post_id = wp_insert_post($post);
  }
  $fields = [
    'field_pitta_year'        => (int) $row['year'],
    'field_pitta_month'       => (int) $row['month'],
    'field_pitta_edition'     => $row['edition'],
    'field_pitta_catalog_key' => $row['key'],
    'field_pitta_archive_url' => 'https://drive.google.com/file/d/' . $row['drive_id'] . '/view',
    'field_pitta_url_type'    => 'google_drive',
    'field_pitta_is_part'     => 0,
  ];
  foreach ($fields as $field_key => $value) {
    update_field($field_key, $value, $post_id);
  }
  return $post_id;
}

add_action('admin_menu', function() {
  add_submenu_page('edit.php?post_type=db_pitta', 'Sync PITTA from catalog', 'Sync from catalog', 'manage_options', 'db-pitta-sync', 'db_pitta_sync_page');
});

function db_pitta_sync_page() {
  if (!current_user_can('manage_options')) return;
  if (!function_exists('update_field')) {
    echo '<div class="wrap"><h1>Sync PITTA from catalog</h1><p>Advanced Custom Fields must be active.</p></div>';
    return;
  }

  $done = null;
  if (isset($_POST['db_pitta_sync']) && check_admin_referer('db_pitta_sync')) {
    $plan = db_pitta_sync_plan();
    foreach (array_merge($plan['create'], $plan['update']) as [$row, $post_id]) db_pitta_apply_row($row, $post_id);
    foreach ($plan['trash'] as $p) wp_trash_post($p->ID);
    db_pitta_bump_rev();
    $done = [count($plan['create']), count($plan['update']), count($plan['trash'])];
  }

  $plan = db_pitta_sync_plan();
  $index = db_pitta_index();
  $row_label = function($row) {
    return esc_html(trim($row['year'] . ' ' . (db_pitta_months()[(int) $row['month']] ?? '') . ' · ' . $row['edition']));
  };
  ?>
  <div class="wrap">
    <h1>Sync PITTA from catalog</h1>
    <?php if ($done): ?>
      <div class="notice notice-success"><p><?php printf('Done: %d created, %d updated, %d moved to Trash.', $done[0], $done[1], $done[2]); ?></p></div>
    <?php endif; ?>
    <p>Mirrors <code>data/pitta-catalog.csv</code> (shipped with the theme) into PITTA issues. Safe to run again — issues are matched by their catalog key, so a second run only updates them.</p>
    <p>Search index: <?php echo $index ? esc_html(count($index['issues']) . ' editions, built ' . ($index['generated'] ?? '?')) : '<strong>not found</strong> — run tools/pitta-index/build_index.py and deploy data/pitta-index.json'; ?></p>

    <h2><?php echo count($plan['create']); ?> to create · <?php echo count($plan['update']); ?> to update</h2>
    <?php
    $unindexed = array_filter(array_merge($plan['create'], $plan['update']), fn($x) => !isset($index['issues'][$x[0]['key']]));
    if ($index && $unindexed): ?>
      <p><strong><?php echo count($unindexed); ?> will not be searchable yet</strong> (no text in the index): <?php echo implode(', ', array_map(fn($x) => $row_label($x[0]), $unindexed)); ?></p>
    <?php endif; ?>

    <?php if ($plan['trash']): ?>
      <h2><?php echo count($plan['trash']); ?> to move to Trash</h2>
      <p>Issues not created from the catalog (e.g. the original sample issues):</p>
      <ul style="list-style:disc;margin-left:20px;">
        <?php foreach ($plan['trash'] as $p): ?><li><?php echo esc_html($p->post_title); ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($plan['skip']): ?>
      <h2><?php echo count($plan['skip']); ?> skipped</h2>
      <ul style="list-style:disc;margin-left:20px;">
        <?php foreach ($plan['skip'] as [$row, $why]): ?><li><?php echo $row_label($row) . ' — ' . esc_html($why); ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post">
      <?php wp_nonce_field('db_pitta_sync'); ?>
      <?php submit_button('Run sync', 'primary', 'db_pitta_sync'); ?>
    </form>
  </div>
  <?php
}

/**
 * Revision counter for search-result caching: bumped whenever a PITTA
 * issue changes, so cached results never outlive an edit.
 */
function db_pitta_bump_rev() {
  update_option('db_pitta_rev', (int) get_option('db_pitta_rev', 0) + 1, false);
}
add_action('save_post_db_pitta', 'db_pitta_bump_rev');
add_action('trashed_post', function($id) { if (get_post_type($id) === 'db_pitta') db_pitta_bump_rev(); });
add_action('untrashed_post', function($id) { if (get_post_type($id) === 'db_pitta') db_pitta_bump_rev(); });

/**
 * The parsed search index, or null. Not stored in a transient — at several
 * MB it's too big for an options row — but only loaded on a cache miss.
 */
function db_pitta_index() {
  static $index = false;
  if ($index !== false) return $index;
  $path = get_template_directory() . '/data/pitta-index.json';
  $index = is_readable($path) ? json_decode(file_get_contents($path), true) : null;
  if (!is_array($index) || !isset($index['issues'])) $index = null;
  return $index;
}

/** Lowercase, strip accents, treat hyphens/dashes as spaces. */
function db_pitta_fold($s) {
  $s = remove_accents($s);
  $s = preg_replace('/[\x{2010}-\x{2015}\-]/u', ' ', $s); // one-for-one, so positions line up with the original
  return mb_strtolower($s, 'UTF-8');
}

/**
 * db_pitta_fold() one character at a time, keeping any character whose
 * folded form isn't a single character — slower, but the result is
 * exactly as long as the input. Only used for snippet alignment.
 */
function db_pitta_fold_aligned($s) {
  $out = '';
  foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
    $f = db_pitta_fold($ch);
    $out .= mb_strlen($f) === 1 ? $f : $ch;
  }
  return $out;
}

/**
 * Search every edition's text (plus its title and date). Words match at
 * the start of a word, so "pitta" also finds "pittas".
 *
 * A multi-word query is a phrase search: "indian pitta" returns only the
 * editions containing those words in sequence (punctuation between them
 * is ignored). Only when the phrase occurs nowhere does it fall back to
 * editions containing all the words anywhere ('mode' => 'all_words').
 * Ranked by hits, then newest.
 */
function db_pitta_search($q) {
  $index = db_pitta_index();
  if (!$index) return ['error' => 'index_missing', 'results' => [], 'total' => 0];

  // Terms need a letter or digit; stray punctuation is dropped.
  $words = array_values(array_unique(array_filter(
    preg_split('/\s+/u', trim(db_pitta_fold($q)), -1, PREG_SPLIT_NO_EMPTY),
    fn($w) => preg_match('/[\p{L}\p{N}]/u', $w)
  )));
  $words = array_slice($words, 0, 8);
  if (!$words) return ['results' => [], 'total' => 0, 'mode' => 'phrase'];

  $quoted = array_map(fn($w) => preg_quote($w, '/'), $words);
  $phrase_re = '/(?<![\p{L}\p{N}])' . implode('[^\p{L}\p{N}]+', $quoted) . '[\p{L}\p{N}]*/u';
  $any_re = '/(?<![\p{L}\p{N}])(?:' . implode('|', $quoted) . ')[\p{L}\p{N}]*/u';
  $word_res = array_map(fn($w) => '/(?<![\p{L}\p{N}])' . $w . '/u', $quoted);

  // Pass 1: score every edition, both ways.
  $months = db_pitta_months();
  $phrase_matches = [];
  $word_matches = [];
  foreach (get_posts(['post_type' => 'db_pitta', 'post_status' => 'publish', 'posts_per_page' => -1]) as $post) {
    $key = get_post_meta($post->ID, 'catalog_key', true);
    $year = (int) get_post_meta($post->ID, 'year', true);
    $month = (int) get_post_meta($post->ID, 'month', true);
    $pages = ($key && isset($index['issues'][$key])) ? $index['issues'][$key]['pages'] : [];
    $folded = array_map('db_pitta_fold', $pages);
    $meta = db_pitta_fold($post->post_title . ' ' . ($months[$month] ?? '') . ' ' . $year);
    $all = $meta . "\n" . implode("\n", $folded);

    $match = ['post' => $post, 'year' => $year, 'month' => $month, 'pages' => $pages, 'folded' => $folded];
    if (preg_match($phrase_re, $all)) {
      $phrase_matches[] = $match;
    } elseif (!$phrase_matches && count($words) > 1) {
      foreach ($word_res as $re) {
        if (!preg_match($re, $all)) continue 2;
      }
      $word_matches[] = $match;
    }
  }

  $mode = ($phrase_matches || count($words) === 1) ? 'phrase' : 'all_words';
  $hit_re = $mode === 'phrase' ? $phrase_re : $any_re;
  $matches = $mode === 'phrase' ? $phrase_matches : $word_matches;

  // Pass 2: per-page hits for the chosen mode; rank; snippets for the top 50.
  foreach ($matches as &$m) {
    $m['page_hits'] = [];
    foreach ($m['folded'] as $i => $text) {
      if ($n = preg_match_all($hit_re, $text)) $m['page_hits'][$i] = $n;
    }
    arsort($m['page_hits']);
    $m['hits'] = array_sum($m['page_hits']);
  }
  unset($m);
  usort($matches, fn($a, $b) => [$b['hits'], $b['year'] * 100 + $b['month']] <=> [$a['hits'], $a['year'] * 100 + $a['month']]);

  $results = [];
  foreach (array_slice($matches, 0, 50) as $m) {
    $post = $m['post'];
    $url = get_post_meta($post->ID, 'archive_url', true);
    $snippets = [];
    foreach (array_slice(array_keys($m['page_hits']), 0, 3) as $i) {
      $snippets[] = [
        'page'    => $i + 1,
        'snippet' => db_pitta_snippet($m['folded'][$i], $m['pages'][$i], $hit_re),
        'link'    => db_pitta_page_link($url, $i + 1, $q),
      ];
    }
    $edition = (string) get_post_meta($post->ID, 'edition', true);
    $results[] = [
      'title'     => $post->post_title,
      'edition'   => $edition,
      'special'   => db_pitta_special_name($edition),
      'cover'     => db_pitta_cover_url($key),
      'year'      => $m['year'],
      'month'     => $m['month'],
      'url'       => $url,
      'url_type'  => get_post_meta($post->ID, 'url_type', true),
      'hits'      => $m['hits'],
      // Every page that matched, for the "p. 2, 7, 11" line on a card —
      // page_hits is ordered by hit count, so re-sort into reading order.
      'pageNumbers' => db_pitta_page_numbers($m['page_hits']),
      'pages'     => $snippets,
    ];
  }
  return ['results' => $results, 'total' => count($matches), 'mode' => $mode];
}

/**
 * ~180-char excerpt of $original around the first match of $hit_re in
 * $folded, HTML-escaped, with every match wrapped in <mark>. Positions in
 * $folded index straight into $original as long as folding kept the length;
 * when it didn't (e.g. "æ" → "ae"), the page is refolded character by
 * character so they line up again.
 */
function db_pitta_snippet($folded, $original, $hit_re) {
  if (mb_strlen($folded) !== mb_strlen($original)) $folded = db_pitta_fold_aligned($original);
  $pos = 0;
  if (preg_match($hit_re, $folded, $m, PREG_OFFSET_CAPTURE)) {
    $pos = mb_strlen(substr($folded, 0, $m[0][1]));
  }
  $len = mb_strlen($folded);
  $start = max(0, $pos - 70);
  $end = min($len, $start + 180);
  // Snap to word boundaries so the excerpt doesn't open or close mid-word.
  if ($start > 0 && ($sp = mb_strpos($folded, ' ', $start)) !== false && $sp < $pos) $start = $sp + 1;
  if ($end < $len && ($sp = mb_strrpos(mb_substr($folded, 0, $end), ' ')) !== false && $sp > $pos) $end = $sp;

  $f = mb_substr($folded, $start, $end - $start);
  $o = mb_substr($original, $start, $end - $start);
  $out = '';
  $at = 0;
  if (preg_match_all($hit_re, $f, $ms, PREG_OFFSET_CAPTURE)) {
    foreach ($ms[0] as [$hit, $byte]) {
      $cpos = mb_strlen(substr($f, 0, $byte));
      $clen = mb_strlen($hit);
      $out .= esc_html(mb_substr($o, $at, $cpos - $at)) . '<mark>' . esc_html(mb_substr($o, $cpos, $clen)) . '</mark>';
      $at = $cpos + $clen;
    }
  }
  $out .= esc_html(mb_substr($o, $at));
  return ($start > 0 ? '…' : '') . $out . ($end < $len ? '…' : '');
}

/**
 * Link to a page of an issue: archive.org's reader can open at a page with
 * the query highlighted; other hosts (Drive) just get the issue link.
 */
function db_pitta_page_link($url, $page, $q) {
  if (preg_match('#^https?://(?:www\.)?archive\.org/details/([^/?#]+)#', (string) $url, $m)) {
    return 'https://archive.org/details/' . $m[1] . '/page/n' . ($page - 1) . '/mode/2up?q=' . rawurlencode($q);
  }
  return $url;
}

add_action('rest_api_init', function() {
  register_rest_route('db/v1', '/pitta-search', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'args'                => ['q' => ['required' => true, 'type' => 'string']],
    'callback'            => function(WP_REST_Request $request) {
      $q = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $request->get_param('q'))));
      $q = mb_substr($q, 0, 100);
      if (mb_strlen($q) < 3) {
        return new WP_Error('db_pitta_query_too_short', 'Type at least 3 characters.', ['status' => 400]);
      }
      $index_path = get_template_directory() . '/data/pitta-index.json';
      $cache_key = 'db_pitta_q_' . md5(implode('|', [
        mb_strtolower($q), (int) get_option('db_pitta_rev', 0), @filemtime($index_path),
      ]));
      $data = get_transient($cache_key);
      if ($data === false) {
        $data = db_pitta_search($q);
        if (empty($data['error'])) set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
      }
      return db_rest_no_cache(rest_ensure_response($data));
    },
  ]);
});

/* -----------------------------------------------------------------------
 * 9. Seed data — runs once
 * ---------------------------------------------------------------------*/
add_action('init', function() {
  if (get_option('db_seeded_v1')) return;

  // 3 upcoming events
  $events = [
    ['Ameenpur Lake Field Trip', '2026-10-11', 'Ameenpur Lake, Hyderabad', 'Bring water. Loaner binoculars available.', true, null],
    ['KBR Park Bird Walk',       '2026-10-18', 'KBR National Park Gate 1', 'Easy walk, beginners welcome.',           false, null],
    ['Hussain Sagar Survey',     '2026-11-02', 'Hussain Sagar Boat Club',  'Pre-census survey. ₹50 contribution.',   false, 50],
  ];
  foreach ($events as [$title, $date, $loc, $note, $bins, $fee]) {
    $id = wp_insert_post(['post_title' => $title, 'post_status' => 'publish', 'post_type' => 'db_event']);
    update_field('event_date',  $date, $id);
    update_field('location',    $loc,  $id);
    update_field('notes',       $note, $id);
    update_field('loaner_bins', $bins, $id);
    update_field('entry_fee',   $fee,  $id);
    update_field('is_past',     false, $id);
  }

  // 3 past events
  $past = [
    ['Manjeera Reservoir Trip', '2026-09-14', 'Manjeera Dam', 'Excellent raptor sightings', 42, 18, 'Priya Sharma'],
    ['KBR Monthly Walk',        '2026-09-07', 'KBR National Park', 'Good diversity of warblers', 28, 12, 'Srikanth Bhamidipati'],
    ['Ameenpur Census',         '2026-08-31', 'Ameenpur Lake', 'Recorded 6 duck species', 38, 22, 'Ravi Kumar'],
  ];
  foreach ($past as [$title, $date, $loc, $highlights, $species, $turnout, $leader]) {
    $id = wp_insert_post(['post_title' => $title, 'post_status' => 'publish', 'post_type' => 'db_event']);
    update_field('event_date',    $date,       $id);
    update_field('location',      $loc,        $id);
    update_field('highlights',    $highlights, $id);
    update_field('species_count', $species,    $id);
    update_field('turnout',       $turnout,    $id);
    update_field('leader',        $leader,     $id);
    update_field('is_past',       true,        $id);
  }

  // PITTA issues come from data/pitta-catalog.csv via PITTA Archive → Sync from catalog.

  update_option('db_seeded_v1', true);
});
