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

  wp_enqueue_style('db-fonts', 'https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Source+Sans+3:wght@400;600&display=swap', [], null);
  wp_enqueue_style('db-vars', get_template_directory_uri() . '/assets/css/variables.css', [], $v);
  wp_enqueue_style('db-main', get_template_directory_uri() . '/assets/css/main.css', ['db-vars'], $v);

  wp_enqueue_script('db-main', get_template_directory_uri() . '/assets/js/main.js', [], $v, true);

  // Sightings JS — load on sightings page and front page
  if (is_front_page() || is_page('sightings')) {
    wp_enqueue_script('db-sightings', get_template_directory_uri() . '/assets/js/sightings.js', [], $v, true);
  }
  // Events JS — load on events page and front page
  if (is_front_page() || is_page('events')) {
    wp_enqueue_script('db-events', get_template_directory_uri() . '/assets/js/events.js', [], $v, true);
  }
  // Videos JS — load on gallery page
  if (is_page('gallery')) {
    wp_enqueue_script('db-videos', get_template_directory_uri() . '/assets/js/videos.js', [], $v, true);
  }

  // Pass config to all JS
  wp_localize_script('db-main', 'DB_CONFIG', [
    'api_base' => rtrim(get_option('db_api_base_url', 'https://deccan-birders-api.vercel.app'), '/'),
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('db_contact_nonce'),
    'region'   => 'IN',
  ]);
});

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

  register_post_type('db_pitta', [
    'labels'    => ['name' => 'PITTA Archive', 'singular_name' => 'PITTA Issue', 'add_new_item' => 'Add New Issue'],
    'public'    => true,
    'supports'  => ['title'],
    'menu_icon' => 'dashicons-book-alt',
    'rewrite'   => ['slug' => 'pitta'],
  ]);

});

/* -----------------------------------------------------------------------
 * 5. ACF options page
 * ---------------------------------------------------------------------*/
if (function_exists('acf_add_options_page')) {
  acf_add_options_page([
    'page_title' => 'Site Settings',
    'menu_title' => 'Site Settings',
    'menu_slug'  => 'site-settings',
    'capability' => 'manage_options',
  ]);
}

/* -----------------------------------------------------------------------
 * 6. SMTP configuration
 * ---------------------------------------------------------------------*/
add_action('phpmailer_init', function($m) {
  $m->isSMTP();
  $m->Host       = 'smtp.hostinger.com';
  $m->SMTPAuth   = true;
  $m->Port       = 587;
  $m->Username   = 'info@deccanbirders.org';
  $m->Password   = defined('DB_SMTP_PASS') ? DB_SMTP_PASS : '';
  $m->SMTPSecure = 'tls';
  $m->From       = 'info@deccanbirders.org';
  $m->FromName   = 'Deccan Birders';
});

/* -----------------------------------------------------------------------
 * 7. AJAX form handlers
 * ---------------------------------------------------------------------*/
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
  $name          = sanitize_text_field($_POST['name'] ?? '');
  $email         = sanitize_email($_POST['email'] ?? '');
  $help_with_raw = $_POST['help_with'] ?? [];
  $help_with     = is_array($help_with_raw)
    ? array_map('sanitize_text_field', $help_with_raw)
    : array_filter([sanitize_text_field($help_with_raw)]);
  if (!$name || !$email) {
    wp_send_json(['success' => false, 'message' => 'Please fill in all required fields.']);
  }
  $to           = 'info@deccanbirders.org';
  $headers      = ['Content-Type: text/html; charset=UTF-8', "Reply-To: $name <$email>"];
  $help_with_str = $help_with ? implode(', ', $help_with) : 'Not specified';
  $body         = "<p><strong>From:</strong> $name ($email)</p><p><strong>Would like to help with:</strong> $help_with_str</p>";
  wp_mail($to, "New volunteer: $name", $body, $headers);
  wp_mail($email, 'Thank you for volunteering — Deccan Birders', "<p>Hi $name,</p><p>Thank you for offering to help. A committee member will be in touch soon.</p><p>— Deccan Birders</p>", $headers);
  wp_send_json(['success' => true]);
}

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
  $res = wp_remote_get($url, ['timeout' => 20]);

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
 * most-threatened first.
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
    return ($rank[$a['iucnStatus']] ?? 9) <=> ($rank[$b['iucnStatus']] ?? 9);
  });

  return $notable;
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

add_action('rest_api_init', function() {
  register_rest_route('db/v1', '/events', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function(WP_REST_Request $request) {
      $ttl = $request->get_param('scope') === 'past' ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS;
      return db_rest_no_cache(rest_ensure_response(db_proxy_fetch('/api/events', $request, ['scope'], $ttl)));
    },
  ]);

  register_rest_route('db/v1', '/sightings', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function(WP_REST_Request $request) {
      $tab = $request->get_param('tab');
      $ttl = in_array($tab, ['hotspots', 'hotspot_species', 'onthisday'], true) ? DAY_IN_SECONDS : 15 * MINUTE_IN_SECONDS;

      // "Notable" here means IUCN Near Threatened or worse — a different
      // definition than eBird's own "notable" (locally rare/reviewed),
      // which would miss a species that's globally threatened but common
      // in this specific region (e.g. Painted Stork). So instead of
      // proxying eBird's /recent/notable, pull the full /recent feed and
      // filter it against our own conservation-status watchlist.
      if ($tab === 'notable') {
        $recent_request = new WP_REST_Request('GET', $request->get_route());
        $recent_request->set_query_params(array_merge($request->get_query_params(), ['tab' => 'recent']));
        $recent = db_proxy_fetch('/api/sightings', $recent_request, ['region', 'tab'], $ttl);
        if (!empty($recent['error'])) return db_rest_no_cache(rest_ensure_response($recent));
        return db_rest_no_cache(rest_ensure_response(['data' => db_filter_notable_by_iucn($recent['data'] ?? [])]));
      }

      return db_rest_no_cache(rest_ensure_response(db_proxy_fetch('/api/sightings', $request, ['region', 'tab', 'm', 'd', 'locId', 'speciesCode'], $ttl)));
    },
  ]);

  register_rest_route('db/v1', '/videos', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true',
    'callback'            => function(WP_REST_Request $request) {
      return db_rest_no_cache(rest_ensure_response(db_proxy_fetch('/api/videos', $request, [], 6 * HOUR_IN_SECONDS)));
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

  // 4 PITTA issues
  $pittas = [
    ['PITTA Vol 42 No 9',  2026, 42, 9,  'https://archive.org/details/pitta-vol42-no9/mode/2up',  'archive_org'],
    ['PITTA Vol 42 No 8',  2026, 42, 8,  'https://archive.org/details/pitta-vol42-no8/mode/2up',  'archive_org'],
    ['PITTA Vol 41 No 12', '2025', 41, 12, 'https://archive.org/details/pitta-vol41-no12/mode/2up', 'archive_org'],
    ['PITTA Vol 41 No 6',  2025, 41, 6,  'https://drive.google.com/file/d/example/view',          'google_drive'],
  ];
  foreach ($pittas as [$title, $year, $vol, $issue, $url, $type]) {
    $id = wp_insert_post(['post_title' => $title, 'post_status' => 'publish', 'post_type' => 'db_pitta']);
    update_field('year',         $year,  $id);
    update_field('volume',       $vol,   $id);
    update_field('issue_number', $issue, $id);
    update_field('month',        $issue, $id); // PITTA is monthly: issue N of a volume year is month N
    update_field('archive_url',  $url,   $id);
    update_field('url_type',     $type,  $id);
    update_field('is_part',      false,  $id);
  }

  update_option('db_seeded_v1', true);
});
