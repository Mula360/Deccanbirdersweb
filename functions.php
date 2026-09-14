<?php
/**
 * Deccan Birders theme functions.
 *
 * Standalone theme — no parent, no page-builder dependency in PHP.
 * Elementor is installed separately and manages page content; this file
 * provides theme setup, enqueues, custom post types, ACF options,
 * SMTP config, AJAX handlers, shortcodes, and one-time seed data.
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
  add_theme_support('elementor');
  register_nav_menus([
    'primary-nav' => 'Primary Navigation',
    'footer-nav'  => 'Footer Navigation',
  ]);
});

/* -----------------------------------------------------------------------
 * 2. Enqueue scripts and styles
 * ---------------------------------------------------------------------*/
add_action('wp_enqueue_scripts', function() {
  $v = '1.0';

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
    'region'   => 'IN-TG',
  ]);
});

/* -----------------------------------------------------------------------
 * 3. Disable Elementor duplicate Google Fonts
 * ---------------------------------------------------------------------*/
add_filter('elementor/frontend/print_google_fonts', '__return_false');

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
  $name     = sanitize_text_field($_POST['name'] ?? '');
  $email    = sanitize_email($_POST['email'] ?? '');
  $species  = sanitize_text_field($_POST['species'] ?? '');
  $location = sanitize_text_field($_POST['location'] ?? '');
  $date     = sanitize_text_field($_POST['date'] ?? '');
  $notes    = sanitize_textarea_field($_POST['notes'] ?? '');
  if (!$name || !$email || !$species || !$location) {
    wp_send_json(['success' => false, 'message' => 'Please fill in all required fields.']);
  }
  $to      = 'info@deccanbirders.org';
  $headers = ['Content-Type: text/html; charset=UTF-8', "Reply-To: $name <$email>"];
  $body    = "<p><strong>From:</strong> $name ($email)</p><p><strong>Species:</strong> $species</p><p><strong>Location:</strong> $location</p>"
    . ($date ? "<p><strong>Date:</strong> $date</p>" : '')
    . ($notes ? "<p><strong>Notes:</strong> $notes</p>" : '');
  wp_mail($to, "Sighting report: $species at $location", $body, $headers);
  wp_mail($email, 'We received your sighting report — Deccan Birders', "<p>Hi $name,</p><p>Thank you for reporting your sighting of $species at $location. We appreciate your contribution to our records.</p><p>— Deccan Birders</p>", $headers);
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
