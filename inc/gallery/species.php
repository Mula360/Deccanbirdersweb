<?php
/**
 * The species list behind the submission form's species picker
 * (Gallery → Species list).
 *
 * Filled from eBird: every species recorded in India (the same data the
 * Sightings page's species lookup uses, through our API proxy), species
 * only — no hybrids, "sp.", slashes or domestic forms. A CSV can replace
 * or extend it. Both go through db_species_import(), which matches rows
 * on the scientific name so existing photos keep pointing at the same
 * species id, and only ever hides a species that has dropped off a list.
 */

if (!defined('ABSPATH')) exit;

/**
 * Why a row is not a species, or '' when it is one. eBird's own category
 * is trusted when there is one; otherwise the name gives it away.
 */
function db_species_reject_reason($common, $scientific, $category = '') {
  if ($category !== '' && strtolower($category) !== 'species') return 'category "' . $category . '"';
  if (preg_match('/\bsp\.|\bspp\./i', $common . ' ' . $scientific)) return '"sp."';
  if (strpos($common . $scientific, '/') !== false) return 'slash';
  if (preg_match('/\sx\s|\(hybrid\)|\bhybrid\b/i', $common) || preg_match('/\sx\s/i', $scientific)) return 'hybrid';
  if (preg_match('/\bdomestic\b/i', $common)) return 'domestic form';
  if (count(preg_split('/\s+/', trim($scientific))) !== 2) return 'not a two-part scientific name';
  return '';
}

/**
 * Import rows of [common, scientific, code, category]. When $replace is
 * true, active species absent from these rows are hidden from the picker.
 * Returns a summary: added, updated, unchanged, hidden, skipped[].
 */
function db_species_import(array $rows, $replace) {
  global $wpdb;
  $table = db_species_table();
  $now = current_time('mysql');
  $summary = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'hidden' => 0, 'skipped' => []];

  $existing = [];
  foreach ($wpdb->get_results("SELECT id, species_code, common_name, scientific_name, sort_order, active FROM $table") as $r) {
    $existing[strtolower($r->scientific_name)] = $r;
  }

  // A replacing list sets the order; an added-to list goes on the end
  // and leaves the existing order alone.
  $seen = [];
  $order = $replace ? 0 : (int) $wpdb->get_var("SELECT MAX(sort_order) FROM $table");
  foreach ($rows as $row) {
    $common     = sanitize_text_field($row['common'] ?? '');
    $scientific = sanitize_text_field($row['scientific'] ?? '');
    $code       = sanitize_key($row['code'] ?? '');
    if ($common === '' || $scientific === '') {
      $summary['skipped'][] = [trim($common . ' ' . $scientific) ?: '(blank row)', 'missing a name'];
      continue;
    }
    if ($why = db_species_reject_reason($common, $scientific, (string) ($row['category'] ?? ''))) {
      $summary['skipped'][] = [$common . ' (' . $scientific . ')', $why];
      continue;
    }
    $key = strtolower($scientific);
    if (isset($seen[$key])) {
      $summary['skipped'][] = [$common . ' (' . $scientific . ')', 'listed twice'];
      continue;
    }
    $seen[$key] = true;

    $current = $existing[$key] ?? null;
    if (!$current || $replace) $order++;
    if (!$current) {
      $wpdb->insert($table, [
        'species_code' => $code, 'common_name' => $common, 'scientific_name' => $scientific,
        'sort_order' => $order, 'active' => 1, 'updated_at' => $now,
      ]);
      $summary['added']++;
      continue;
    }
    $changes = [];
    if ($current->common_name !== $common) $changes['common_name'] = $common;
    if ($current->scientific_name !== $scientific) $changes['scientific_name'] = $scientific;
    if ($code !== '' && $current->species_code !== $code) $changes['species_code'] = $code;
    if ($replace && (int) $current->sort_order !== $order) $changes['sort_order'] = $order;
    if (!(int) $current->active) $changes['active'] = 1;
    if ($changes) {
      $wpdb->update($table, $changes + ['updated_at' => $now], ['id' => $current->id]);
      // A reorder alone is housekeeping, not a change anyone needs told about.
      if (array_diff_key($changes, ['sort_order' => 1])) $summary['updated']++;
      else $summary['unchanged']++;
    } else {
      $summary['unchanged']++;
    }
  }

  if ($replace && $seen) {
    foreach ($existing as $key => $r) {
      if ((int) $r->active && !isset($seen[$key])) {
        $wpdb->update($table, ['active' => 0, 'updated_at' => $now], ['id' => $r->id]);
        $summary['hidden']++;
      }
    }
  }
  return $summary;
}

/** India's species from eBird, through our API proxy. Rows, or a WP_Error. */
function db_species_rows_from_ebird() {
  $request = new WP_REST_Request('GET');
  $request->set_param('tab', 'taxonomy');
  $request->set_param('region', 'IN');
  $body = db_proxy_fetch('/api/sightings', $request, ['region', 'tab'], DAY_IN_SECONDS);
  if (!empty($body['error']) || empty($body['data']) || !is_array($body['data'])) {
    return new WP_Error('ebird', 'eBird did not return a species list' . (!empty($body['message']) ? ': ' . $body['message'] : '.'));
  }
  return array_map(fn($t) => [
    'common'     => $t['species'] ?? '',
    'scientific' => $t['scientific'] ?? '',
    'code'       => $t['speciesCode'] ?? '',
    'category'   => 'species', // the proxy asks eBird for cat=species only
  ], $body['data']);
}

/**
 * Rows from a CSV. Headings are matched loosely so both our own export
 * and an eBird/Clements taxonomy download work: common name, scientific
 * name, and optionally a species code and category.
 */
function db_species_rows_from_csv($path) {
  $fh = @fopen($path, 'r');
  if (!$fh) return new WP_Error('csv', 'The file could not be read.');
  $header = fgetcsv($fh, 0, ',', '"', '\\');
  if (!$header) { fclose($fh); return new WP_Error('csv', 'The file is empty.'); }
  $norm = array_map(fn($h) => preg_replace('/[^a-z]/', '', strtolower(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
  $find = function(array $names) use ($norm) {
    foreach ($names as $n) { $i = array_search($n, $norm, true); if ($i !== false) return $i; }
    return false;
  };
  $c_common = $find(['commonname', 'comname', 'primarycomname', 'englishname', 'common', 'name']);
  $c_sci    = $find(['scientificname', 'sciname', 'scientific']);
  $c_code   = $find(['speciescode', 'code']);
  $c_cat    = $find(['category']);
  if ($c_common === false || $c_sci === false) {
    fclose($fh);
    return new WP_Error('csv', 'The CSV needs a common name column and a scientific name column (e.g. "common_name" and "scientific_name").');
  }
  $rows = [];
  while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
    if ($r === [null]) continue;
    $rows[] = [
      'common'     => $r[$c_common] ?? '',
      'scientific' => $r[$c_sci] ?? '',
      'code'       => $c_code !== false ? ($r[$c_code] ?? '') : '',
      'category'   => $c_cat !== false ? ($r[$c_cat] ?? '') : '',
    ];
  }
  fclose($fh);
  return $rows;
}

function db_species_get($id) {
  global $wpdb;
  return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . db_species_table() . ' WHERE id = %d', $id));
}

/** Search by common or scientific name; names starting with the query first. */
function db_species_search($q, $limit = 20) {
  global $wpdb;
  $q = trim($q);
  if (mb_strlen($q) < 2) return [];
  $like   = '%' . $wpdb->esc_like($q) . '%';
  $prefix = $wpdb->esc_like($q) . '%';
  $word   = '% ' . $wpdb->esc_like($q) . '%';
  return $wpdb->get_results($wpdb->prepare(
    'SELECT id, common_name, scientific_name FROM ' . db_species_table() . '
     WHERE active = 1 AND (common_name LIKE %s OR scientific_name LIKE %s)
     ORDER BY (common_name LIKE %s OR scientific_name LIKE %s) DESC,
              (common_name LIKE %s) DESC,
              sort_order
     LIMIT %d',
    $like, $like, $prefix, $prefix, $word, $limit
  ));
}

add_action('rest_api_init', function() {
  register_rest_route('db/v1', '/species', [
    'methods'             => 'GET',
    'permission_callback' => '__return_true', // the list is public
    'args'                => ['q' => ['required' => true, 'type' => 'string']],
    'callback'            => function(WP_REST_Request $request) {
      $q = mb_substr(sanitize_text_field($request->get_param('q')), 0, 60);
      $data = array_map(fn($r) => [
        'id'         => (int) $r->id,
        'common'     => $r->common_name,
        'scientific' => $r->scientific_name,
      ], db_species_search($q));
      $response = rest_ensure_response(['data' => $data]);
      $response->header('Cache-Control', 'public, max-age=3600');
      return $response;
    },
  ]);
});

/* -----------------------------------------------------------------------
 * Existing photos: free-text species → list
 * -------------------------------------------------------------------- */

/** Lowercase, apostrophes and hyphens dropped, spaces collapsed. */
function db_species_key($name) {
  $name = strtolower(html_entity_decode((string) $name, ENT_QUOTES));
  $name = str_replace(['’', "'", '`'], '', $name);
  $name = preg_replace('/[^a-z0-9]+/', ' ', $name);
  return trim($name);
}

/**
 * Point every gallery photo without a species id at its species, matching
 * the free-text name against common and scientific names. The typed name
 * is kept in _db_species_typed. Returns ['matched' => [...], 'unmatched' => [...]].
 */
function db_species_match_existing() {
  global $wpdb;
  $index = [];
  foreach ($wpdb->get_results('SELECT id, common_name, scientific_name FROM ' . db_species_table()) as $s) {
    $index[db_species_key($s->common_name)] = $s;
    $index[db_species_key($s->scientific_name)] = $s;
  }

  $photos = get_posts([
    'post_type'   => 'db_gallery_photo',
    'post_status' => 'any',
    'numberposts' => -1,
    'meta_query'  => [['key' => '_db_species_id', 'compare' => 'NOT EXISTS']],
  ]);

  $out = ['matched' => [], 'unmatched' => []];
  foreach ($photos as $p) {
    $typed = trim((string) get_post_meta($p->ID, 'species_name', true));
    $s = $typed !== '' ? ($index[db_species_key($typed)] ?? null) : null;
    if (!$s) {
      $out['unmatched'][] = ['id' => $p->ID, 'typed' => $typed, 'status' => $p->post_status];
      continue;
    }
    update_post_meta($p->ID, '_db_species_id', (int) $s->id);
    update_post_meta($p->ID, '_db_species_typed', $typed);
    update_field('field_gallery_species_name', $s->common_name, $p->ID);
    update_field('field_gallery_scientific_name', $s->scientific_name, $p->ID);
    $out['matched'][] = ['id' => $p->ID, 'typed' => $typed, 'species' => $s->common_name . ' (' . $s->scientific_name . ')'];
  }
  return $out;
}

/* -----------------------------------------------------------------------
 * Screen
 * -------------------------------------------------------------------- */

add_action('admin_menu', function() {
  add_submenu_page(
    'edit.php?post_type=db_gallery_photo',
    'Species list',
    'Species list',
    'manage_options',
    'db-species',
    'db_species_page'
  );
});

add_action('admin_post_db_species_export', function() {
  if (!current_user_can('manage_options')) wp_die('Not allowed.', 403);
  check_admin_referer('db_species_export');
  global $wpdb;
  $rows = $wpdb->get_results('SELECT common_name, scientific_name, species_code FROM ' . db_species_table() . ' WHERE active = 1 ORDER BY sort_order', ARRAY_N);
  nocache_headers();
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="species-' . gmdate('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['common_name', 'scientific_name', 'species_code'], ',', '"', '\\');
  foreach ($rows as $row) fputcsv($out, $row, ',', '"', '\\');
  fclose($out);
  exit;
});

function db_species_handle_post() {
  $action = sanitize_key($_POST['db_sp_action'] ?? '');
  if (!$action) return null;
  check_admin_referer('db_species_' . $action);
  switch ($action) {
    case 'ebird':
      $rows = db_species_rows_from_ebird();
      if (is_wp_error($rows)) return ['error' => $rows->get_error_message()];
      return ['import' => db_species_import($rows, true), 'source' => 'eBird (India)'];
    case 'csv':
      $file = $_FILES['csv'] ?? null;
      if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Choose a CSV file to upload.'];
      }
      if ($file['size'] > 10 * MB_IN_BYTES) return ['error' => 'That file is over 10 MB.'];
      $rows = db_species_rows_from_csv($file['tmp_name']);
      if (is_wp_error($rows)) return ['error' => $rows->get_error_message()];
      return ['import' => db_species_import($rows, !empty($_POST['replace'])), 'source' => 'CSV'];
    case 'match':
      return ['match' => db_species_match_existing()];
  }
  return null;
}

function db_species_page() {
  if (!current_user_can('manage_options')) return;
  global $wpdb;
  $result = db_species_handle_post();
  $table  = db_species_table();
  $active = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE active = 1");
  $hidden = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE active = 0");
  $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
  $found  = $search !== '' ? db_species_search($search, 50) : [];
  ?>
  <div class="wrap">
    <h1 class="wp-heading-inline">Species list</h1>
    <?php if ($active): ?>
      <a class="page-title-action" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=db_species_export'), 'db_species_export')); ?>">Export CSV</a>
    <?php endif; ?>
    <hr class="wp-header-end">
    <p>The species a photographer can pick when submitting a photograph.
      <strong><?php echo esc_html(number_format_i18n($active)); ?></strong> species<?php
      if ($hidden) echo esc_html(sprintf(', plus %d hidden (dropped from a newer list, kept for photos that use them)', $hidden)); ?>.</p>

    <?php if (!empty($result['error'])): ?>
      <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($result['import'])): $s = $result['import']; ?>
      <div class="notice notice-success">
        <p><strong><?php echo esc_html(sprintf('%s: added %d, updated %d, unchanged %d, hidden %d, skipped %d.',
          $result['source'], $s['added'], $s['updated'], $s['unchanged'], $s['hidden'], count($s['skipped']))); ?></strong></p>
        <?php if ($s['skipped']): ?>
          <details><summary>Skipped rows</summary>
            <ul style="list-style:disc;margin-left:20px"><?php foreach (array_slice($s['skipped'], 0, 200) as [$what, $why]): ?>
              <li><?php echo esc_html($what . ' — ' . $why); ?></li><?php endforeach; ?></ul>
            <?php if (count($s['skipped']) > 200): ?><p>…and <?php echo count($s['skipped']) - 200; ?> more.</p><?php endif; ?>
          </details>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($result['match'])): $m = $result['match']; ?>
      <div class="notice notice-<?php echo $m['unmatched'] ? 'warning' : 'success'; ?>">
        <p><strong><?php echo esc_html(sprintf('Existing photos: matched %d, not matched %d.', count($m['matched']), count($m['unmatched']))); ?></strong></p>
        <?php if ($m['unmatched']): ?>
          <p>These kept their typed species name. Open each and pick the species, or leave them as they are:</p>
          <ul style="list-style:disc;margin-left:20px"><?php foreach ($m['unmatched'] as $u): ?>
            <li><a href="<?php echo esc_url(get_edit_post_link($u['id'])); ?>"><?php echo esc_html($u['typed'] !== '' ? $u['typed'] : '(no species typed)'); ?></a>
              <span class="description">— <?php echo esc_html($u['status']); ?></span></li>
          <?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($m['matched']): ?>
          <details><summary>Matched</summary><ul style="list-style:disc;margin-left:20px"><?php foreach ($m['matched'] as $x): ?>
            <li><?php echo esc_html($x['typed'] . ' → ' . $x['species']); ?></li><?php endforeach; ?></ul></details>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;max-width:1200px;margin:16px 0">
      <form method="post" class="card" style="margin:0;max-width:none">
        <h2 class="title">Refresh from eBird</h2>
        <?php wp_nonce_field('db_species_ebird'); ?>
        <input type="hidden" name="db_sp_action" value="ebird">
        <p>Every species recorded in India on eBird, species only. Species no longer on eBird's list are hidden, never deleted.</p>
        <p><button class="button button-primary">Refresh from eBird</button></p>
      </form>

      <form method="post" enctype="multipart/form-data" class="card" style="margin:0;max-width:none">
        <h2 class="title">Import a CSV</h2>
        <?php wp_nonce_field('db_species_csv'); ?>
        <input type="hidden" name="db_sp_action" value="csv">
        <p><input type="file" name="csv" accept=".csv,text/csv" required></p>
        <p class="description">Columns: common name and scientific name, optionally species code and category (an eBird taxonomy CSV works as it is).</p>
        <p><label><input type="checkbox" name="replace" value="1"> Replace the list — hide species not in this file</label></p>
        <p><button class="button button-primary">Import</button></p>
      </form>

      <form method="post" class="card" style="margin:0;max-width:none">
        <h2 class="title">Match existing photos</h2>
        <?php wp_nonce_field('db_species_match'); ?>
        <input type="hidden" name="db_sp_action" value="match">
        <p>Links gallery photos whose species was typed in by hand to this list, where the name matches. Safe to run again.</p>
        <p><button class="button button-primary" <?php disabled(!$active); ?>>Match photos</button></p>
      </form>
    </div>

    <form method="get">
      <input type="hidden" name="post_type" value="db_gallery_photo">
      <input type="hidden" name="page" value="db-species">
      <label class="screen-reader-text" for="db-sp-search">Search species</label>
      <input type="search" id="db-sp-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Common or scientific name">
      <button class="button">Search</button>
    </form>
    <?php if ($search !== ''): ?>
      <table class="widefat striped" style="max-width:700px;margin-top:8px">
        <thead><tr><th>Common name</th><th>Scientific name</th></tr></thead>
        <tbody>
          <?php if (!$found): ?><tr><td colspan="2">No species match.</td></tr><?php endif; ?>
          <?php foreach ($found as $f): ?>
            <tr><td><?php echo esc_html($f->common_name); ?></td><td><em><?php echo esc_html($f->scientific_name); ?></em></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php
}
