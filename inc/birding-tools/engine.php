<?php
/**
 * Birding Tools: the figures behind Bird Trends and Winter Migration.
 *
 * Everything is worked out from the eBird Observation Dataset as GBIF
 * publishes it (Cornell Lab of Ornithology, CC BY 4.0): GBIF's search API
 * answers "how many records of each species, per district, per year" in
 * one request, so a full recalculation is about 200 requests.
 *
 * GBIF turns away bursts, and a Hostinger request can't run for minutes,
 * so the work is done a slice at a time (see db_bt_tick):
 *
 *   - db_bt_compute() works out both pages from whatever GBIF answers are
 *     already saved. Each answer it lacks is noted, and it stops at the
 *     first step that can't go on without them.
 *   - Each tick fetches a batch of the missing answers, saves them, and
 *     runs the compute again. The next step's needs appear as the
 *     previous step's answers arrive, until nothing is missing and the
 *     two JSON files are written.
 *
 * A run starts every 3 days (setup.php). Answers are saved per run, so
 * each run starts from fresh data, and the previous run's are removed.
 * Files live in uploads/db-birding/.
 */

if (!defined('ABSPATH')) exit;

const DB_BT_DATASET = '4fa7b334-ce0d-4e88-aaae-2e0c138d049e';
const DB_BT_STATES  = ['TS' => 'Telangana', 'AP' => 'Andhra Pradesh'];

/* -----------------------------------------------------------------------
 * Storage
 * -------------------------------------------------------------------- */

function db_bt_dir($sub = '') {
  $dir = trailingslashit(wp_upload_dir()['basedir']) . 'db-birding' . ($sub !== '' ? '/' . $sub : '');
  if (!is_dir($dir)) {
    wp_mkdir_p($dir);
    // Raw answers and the job file are nobody's business but the site's.
    if ($sub === '') @file_put_contents($dir . '/.htaccess', "<FilesMatch \"^(job|report)\\.json$\">\nRequire all denied\n</FilesMatch>\n");
    if ($sub !== '') @file_put_contents($dir . '/.htaccess', "Require all denied\n");
  }
  return $dir;
}

function db_bt_read($name) {
  $path = db_bt_dir() . '/' . $name;
  if (!is_readable($path)) return null;
  $data = json_decode((string) file_get_contents($path), true);
  return is_array($data) ? $data : null;
}

function db_bt_write($name, array $data) {
  $path = db_bt_dir() . '/' . $name;
  $tmp  = $path . '.tmp';
  file_put_contents($tmp, wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  rename($tmp, $path); // readers never see half a file
}

/* -----------------------------------------------------------------------
 * GBIF answers: saved, or noted as missing
 * -------------------------------------------------------------------- */

/** The URL for a GBIF search. $params: name => value or list of values. */
function db_bt_url(array $params, $base = 'https://api.gbif.org/v1/occurrence/search') {
  // Defaults first (so every request reads the same way), the caller's values winning.
  $params = array_merge(['datasetKey' => DB_BT_DATASET, 'limit' => 0], $params);
  $parts = [];
  foreach ($params as $k => $v) {
    foreach ((array) $v as $one) $parts[] = rawurlencode($k) . '=' . rawurlencode((string) $one);
  }
  return $base . '?' . implode('&', $parts);
}

/**
 * A saved answer for this run, or null; a null notes the URL as wanted.
 * Callers stop when they get null and try again on the next tick.
 */
function db_bt_get($url, $run) {
  $file = db_bt_dir('cache-' . $run) . '/' . md5($url) . '.json';
  if (is_readable($file)) {
    $data = json_decode((string) file_get_contents($file), true);
    if (is_array($data)) return $data;
  }
  $GLOBALS['db_bt_wanted'][$url] = true;
  return null;
}

/** The facet counts of a GBIF answer as name => count. */
function db_bt_facet($answer, $field) {
  // GBIF names them its own way: YEAR, GADM_LEVEL_2_GID, eventDateInterval.
  $want = strtolower($field);
  foreach ($answer['facets'] ?? [] as $f) {
    $got = strtolower(str_replace('_', '', $f['field']));
    if ($got === $want || str_starts_with($got, $want)) {
      $out = [];
      foreach ($f['counts'] as $c) $out[$c['name']] = (int) $c['count'];
      return $out;
    }
  }
  return [];
}

/** Fetch one URL into this run's saved answers. Returns 'ok', 'retry' (rate limit) or 'fail'. */
function db_bt_fetch($url, $run) {
  $res = wp_remote_get($url, ['timeout' => 30, 'headers' => ['Accept' => 'application/json'],
    'user-agent' => 'DeccanBirders/1.0 (+https://deccanbirders.org)']);
  if (is_wp_error($res)) return 'retry';
  $code = (int) wp_remote_retrieve_response_code($res);
  if ($code === 429 || $code >= 500) return 'retry';
  if ($code !== 200) return 'fail';
  $body = wp_remote_retrieve_body($res);
  if (!is_array(json_decode($body, true))) return 'retry';
  file_put_contents(db_bt_dir('cache-' . $run) . '/' . md5($url) . '.json', $body);
  return 'ok';
}

/* -----------------------------------------------------------------------
 * Species names and habitats
 * -------------------------------------------------------------------- */

/** eBird's species list, keyed by lower-case scientific name. Null until fetched. */
function db_bt_taxonomy($run) {
  static $memo = [];
  if (isset($memo[$run])) return $memo[$run];
  $raw = db_bt_get('https://api.ebird.org/v2/ref/taxonomy/ebird?fmt=json&cat=species&locale=en', $run);
  if ($raw === null) return null;
  $out = [];
  foreach ($raw as $t) {
    if (empty($t['sciName'])) continue;
    $out[strtolower($t['sciName'])] = [
      'code'   => $t['speciesCode'] ?? '',
      'name'   => $t['comName'] ?? $t['sciName'],
      'sci'    => $t['sciName'],
      'family' => $t['familySciName'] ?? '',
      'order'  => (float) ($t['taxonOrder'] ?? 0),
    ];
  }
  return $memo[$run] = $out;
}

/**
 * Habitat by bird family, for the Winter Migration groups. Genera listed
 * separately where one family spans two habitats (chats and redstarts
 * are scrub birds; flycatchers, their family-mates, are woodland ones).
 * Reviewed once; any species can be overridden in wp-admin.
 */
function db_bt_habitat_table() {
  return [
    'families' => [
      'wet'   => ['Anatidae', 'Podicipedidae', 'Phoenicopteridae', 'Rallidae', 'Gruidae', 'Charadriidae', 'Recurvirostridae',
                  'Scolopacidae', 'Glareolidae', 'Laridae', 'Ciconiidae', 'Ardeidae', 'Threskiornithidae', 'Pelecanidae',
                  'Phalacrocoracidae', 'Anhingidae', 'Jacanidae', 'Rostratulidae', 'Pandionidae', 'Alcedinidae', 'Dromadidae',
                  'Haematopodidae', 'Ibidorhynchidae'],
      'grass' => ['Accipitridae', 'Falconidae', 'Alaudidae', 'Motacillidae', 'Sturnidae', 'Emberizidae', 'Hirundinidae',
                  'Apodidae', 'Coraciidae', 'Pteroclidae', 'Turnicidae', 'Phasianidae', 'Otididae', 'Upupidae', 'Meropidae',
                  'Burhinidae', 'Passeridae', 'Ploceidae', 'Estrildidae'],
      'scrub' => ['Acrocephalidae', 'Locustellidae', 'Cisticolidae', 'Sylviidae', 'Laniidae', 'Fringillidae', 'Prunellidae'],
      'wood'  => ['Phylloscopidae', 'Pittidae', 'Oriolidae', 'Cuculidae', 'Picidae', 'Campephagidae', 'Dicruridae',
                  'Monarchidae', 'Turdidae', 'Zosteropidae', 'Strigidae', 'Caprimulgidae', 'Muscicapidae', 'Stenostiridae',
                  'Leiothrichidae', 'Pellorneidae', 'Timaliidae', 'Aegithalidae', 'Paridae', 'Sittidae', 'Certhiidae',
                  'Columbidae', 'Psittacidae', 'Psittaculidae', 'Bucerotidae', 'Megalaimidae', 'Chloropseidae',
                  'Nectariniidae', 'Dicaeidae', 'Pycnonotidae', 'Rhipiduridae', 'Vangidae', 'Aegithinidae'],
    ],
    'genera' => [
      'scrub' => ['Saxicola', 'Luscinia', 'Oenanthe', 'Phoenicurus', 'Calliope', 'Cyanecula', 'Iduna', 'Hippolais', 'Curruca'],
    ],
  ];
}

/** Admin overrides, "Common or scientific name = wet|grass|scrub|wood", one per line. */
function db_bt_habitat_overrides() {
  $out = [];
  foreach (preg_split('/\r\n|\r|\n/', (string) get_option('db_bt_habitat_overrides', '')) as $line) {
    if (!preg_match('/^\s*(.+?)\s*=\s*(wet|wetland|grass|grassland|scrub|wood|woodland)\b/i', $line, $m)) continue;
    $h = strtolower($m[2]);
    $out[strtolower($m[1])] = ['wetland' => 'wet', 'grassland' => 'grass', 'woodland' => 'wood'][$h] ?? $h;
  }
  return $out;
}

function db_bt_habitat($tax) {
  static $over = null;
  if ($over === null) $over = db_bt_habitat_overrides();
  foreach ([strtolower($tax['name']), strtolower($tax['sci'])] as $k) if (isset($over[$k])) return $over[$k];
  $table = db_bt_habitat_table();
  $genus = strtok($tax['sci'], ' ');
  foreach ($table['genera'] as $h => $list) if (in_array($genus, $list, true)) return $h;
  foreach ($table['families'] as $h => $list) if (in_array($tax['family'], $list, true)) return $h;
  return 'wood';
}

/** Wetland birds carry a note on Bird Trends: they swing with water levels. */
function db_bt_is_wetland($tax) {
  return db_bt_habitat($tax) === 'wet';
}

/* -----------------------------------------------------------------------
 * Small statistics
 * -------------------------------------------------------------------- */

function db_bt_median(array $v) {
  sort($v);
  $n = count($v);
  if (!$n) return 0;
  return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
}

/** Theil–Sen slope of y against 0..n-1: the median of every pairwise slope. */
function db_bt_theil_sen(array $y) {
  $s = [];
  $n = count($y);
  for ($i = 0; $i < $n; $i++) for ($j = $i + 1; $j < $n; $j++) $s[] = ($y[$j] - $y[$i]) / ($j - $i);
  return db_bt_median($s);
}

function db_bt_percentile(array $v, $p) {
  sort($v);
  if (!$v) return 0;
  $i = ($p / 100) * (count($v) - 1);
  $lo = (int) floor($i);
  $hi = (int) ceil($i);
  return $v[$lo] + ($v[$hi] - $v[$lo]) * ($i - $lo);
}

/* -----------------------------------------------------------------------
 * Bird Trends
 * -------------------------------------------------------------------- */


/**
 * One state's trends, or null while answers are still missing. Method
 * (also on the page): share of records, balanced by district, every
 * month kept (bird-count events included), Theil–Sen trend with a
 * bootstrap range, measured against the typical species in the state
 * (see the drift note below).
 */
function db_bt_state_trends($key, $run, $now_year) {
  $state = DB_BT_STATES[$key];

  // Years: records per year without February.
  $years = db_bt_get(db_bt_url(['stateProvince' => $state, 'facet' => 'year', 'facetLimit' => 60]), $run);
  if ($years === null) return null;
  $per_year = [];
  foreach (db_bt_facet($years, 'year') as $y => $n) $per_year[(int) $y] = $n;
  ksort($per_year);

  // The window ends at the last complete year (at least 60% of the year
  // before, and never the current year) and starts at the first year
  // with 50,000 records, at most 10 years back.
  $last = null;
  foreach (array_reverse(array_keys($per_year)) as $y) {
    if ($y >= $now_year) continue;
    if (isset($per_year[$y - 1]) && $per_year[$y] < 0.6 * $per_year[$y - 1]) continue;
    $last = $y;
    break;
  }
  if (!$last) return ['error' => 'no complete year'];
  $first = $last;
  for ($y = $last; $y >= $last - 9; $y--) {
    if (($per_year[$y] ?? 0) < 50000) break;
    $first = $y;
  }
  $window = range($first, $last);
  if (count($window) < 5) return ['error' => 'fewer than 5 years with enough records'];

  // Districts per year; "core" ones have 500 records in every year.
  $district_totals = [];
  foreach ($window as $y) {
    $a = db_bt_get(db_bt_url(['stateProvince' => $state, 'year' => $y, 'facet' => 'gadmLevel2Gid', 'facetLimit' => 100]), $run);
    if ($a === null) { $missing = true; continue; } // ask for every missing year at once
    foreach (db_bt_facet($a, 'gadmLevel2Gid') as $d => $n) $district_totals[$d][$y] = $n;
  }
  if (!empty($missing)) return null;
  $core = [];
  foreach ($district_totals as $d => $by_year) {
    $ok = true;
    foreach ($window as $y) if (($by_year[$y] ?? 0) < 500) { $ok = false; break; }
    if ($ok) $core[] = $d;
  }
  if (count($core) < 2) return ['error' => 'fewer than 2 districts birded every year'];
  $core_sum = 0;
  $weight = [];
  foreach ($core as $d) { $weight[$d] = array_sum($district_totals[$d]); $core_sum += $weight[$d]; }
  foreach ($weight as $d => $w) $weight[$d] = $w / $core_sum;

  // Species per core district per year.
  $counts = []; // sci => d => y => n
  foreach ($core as $d) foreach ($window as $y) {
    $a = db_bt_get(db_bt_url(['gadmGid' => $d, 'year' => $y, 'facet' => 'verbatimScientificName', 'facetLimit' => 2000]), $run);
    if ($a === null) { $missing = true; continue; }
    foreach (db_bt_facet($a, 'verbatimScientificName') as $sci => $n) $counts[strtolower($sci)][$d][$y] = $n;
  }
  if (!empty($missing)) return null;

  $index = function($sci, array $districts) use ($counts, $district_totals, $weight, $window) {
    $wsum = 0;
    foreach ($districts as $d) $wsum += $weight[$d];
    $out = [];
    foreach ($window as $y) {
      $v = 0;
      foreach ($districts as $d) $v += ($weight[$d] / $wsum) * (($counts[$sci][$d][$y] ?? 0) / $district_totals[$d][$y]);
      $out[] = $v * 1000;
    }
    return $out;
  };

  mt_srand(1); // the same answer every run for the same data
  $resamples = [];
  for ($b = 0; $b < 300; $b++) {
    $pick = [];
    for ($i = 0; $i < count($core); $i++) $pick[] = $core[mt_rand(0, count($core) - 1)];
    $resamples[] = $pick;
  }

  // First pass: each species' index, its slope and its bootstrap slopes.
  $rows = [];
  $fits = [];
  foreach ($counts as $sci => $by_district) {
    $n_year = [];
    foreach ($window as $y) { $n = 0; foreach ($core as $d) $n += $by_district[$d][$y] ?? 0; $n_year[] = $n; }
    $vals = $index($sci, $core);
    $rows[$sci] = ['vals' => $vals, 'n' => $n_year];
    // A verdict needs records every year, enough of them, and from at least
    // two districts: a bird watched in one place only (Yellow-throated
    // Bulbul in Chittoor) moves with that one place's birding.
    $places = count(array_filter($by_district, fn($by_year) => array_sum($by_year) >= 20));
    $enough = min($n_year) > 0 && array_sum($n_year) >= 300 && db_bt_median($n_year) >= 20 && $places >= 2;
    if (!$enough) continue;
    $boot = [];
    foreach ($resamples as $pick) {
      $v = $index($sci, $pick);
      if (min($v) <= 0) continue;
      $boot[] = db_bt_theil_sen(array_map('log', $v));
    }
    $fits[$sci] = ['slope' => db_bt_theil_sen(array_map('log', $vals)), 'boot' => $boot];
  }

  // The drift. Shares of all records add up to one, so when birders log
  // more species per outing, every species' share falls a little each
  // year without any change in the birds. The typical (median) species'
  // slope measures that, and each species is judged against it: a
  // decline here means falling faster than the typical species.
  $drift = $fits ? db_bt_median(array_column($fits, 'slope')) : 0;

  $out = [];
  foreach ($rows as $sci => $row) {
    // Shown relative to the typical species, so the chart and the figures agree.
    $vals = [];
    foreach ($row['vals'] as $i => $v) $vals[] = $v * exp(-$drift * $i);
    $base = ['vals' => array_map(fn($v) => round($v, 3), $vals), 'n' => $row['n'], 'y0' => $first];
    if (!isset($fits[$sci])) { $out[$sci] = $base + ['label' => 'few']; continue; }

    $slope = $fits[$sci]['slope'] - $drift;
    $boot  = array_map(fn($b) => $b - $drift, $fits[$sci]['boot']);
    $pct = (exp($slope) - 1) * 100;
    $lo  = $boot ? (exp(db_bt_percentile($boot, 5)) - 1) * 100 : $pct;
    $hi  = $boot ? (exp(db_bt_percentile($boot, 95)) - 1) * 100 : $pct;
    $tot = (exp($slope * (count($window) - 1)) - 1) * 100;
    $early = array_sum(array_slice($vals, 0, 3)) / 3;
    $late  = array_sum(array_slice($vals, -3)) / 3;
    $el    = $early > 0 ? ($late / $early - 1) * 100 : 0;

    if ($pct <= -5 && $hi < -2 && $el <= -25)      $label = 'clear-d';
    elseif ($pct <= -3 && $el <= -15)              $label = 'poss-d';
    elseif ($pct >= 5 && $lo > 2 && $el >= 25)     $label = 'clear-i';
    elseif ($pct >= 3 && $el >= 15)                $label = 'poss-i';
    else                                           $label = 'none';

    $out[$sci] = $base + ['r' => round($slope, 5), 'pct' => round($pct, 1), 'lo' => round($lo, 1), 'hi' => round($hi, 1),
      'tot' => round($tot, 1), 'el' => round($el, 1), 'label' => $label];
  }

  return ['name' => $state, 'years' => $window, 'records' => array_map(fn($y) => $per_year[$y], $window),
    'core' => count($core), 'drift' => round((exp($drift) - 1) * 100, 1), 'species' => $out];
}

/* -----------------------------------------------------------------------
 * Winter Migration
 * -------------------------------------------------------------------- */

/** Calendar months in season order: Sep … Apr. */
const DB_BT_SEASON = [9, 10, 11, 12, 1, 2, 3, 4];

/**
 * Winter visitors with their months, peaks and per-1,000 figures, from
 * the last five complete years in both states together. Null while
 * answers are missing.
 */
function db_bt_migrants($run, $last_year, $tax) {
  $years = ($last_year - 4) . ',' . $last_year;
  $share = []; // sci => month => [n, N]
  $totals = [];
  foreach (DB_BT_STATES as $state) foreach (range(1, 12) as $m) {
    $a = db_bt_get(db_bt_url(['stateProvince' => $state, 'year' => $years, 'month' => $m, 'facet' => 'verbatimScientificName', 'facetLimit' => 2000]), $run);
    if ($a === null) { $missing = true; continue; }
    $totals[$m] = ($totals[$m] ?? 0) + (int) $a['count'];
    foreach (db_bt_facet($a, 'verbatimScientificName') as $sci => $n) {
      $sci = strtolower($sci);
      $share[$sci][$m] = ($share[$sci][$m] ?? 0) + $n;
    }
  }
  if (!empty($missing)) return null;

  $out = [];
  foreach ($share as $sci => $by_month) {
    if (!isset($tax[$sci]) || array_sum($by_month) < 300) continue;
    $f = [];
    foreach (range(1, 12) as $m) $f[$m] = ($by_month[$m] ?? 0) / max(1, $totals[$m]) * 1000;
    $winter = array_sum(array_map(fn($m) => $f[$m], DB_BT_SEASON)) / 8;
    $summer = ($f[5] + $f[6] + $f[7] + $f[8]) / 4;
    $season = array_map(fn($m) => $f[$m], DB_BT_SEASON);
    $peak = max($season);
    // A winter visitor: barely reported May to August, and reported often
    // enough in winter to say anything about.
    if ($peak < 0.5 || $summer > 0.15 * $winter) continue;

    $present = array_keys(array_filter($season, fn($v) => $v >= 0.25 * $peak));
    $peaks = array_keys(array_filter($season, fn($v) => $v >= 0.85 * $peak));
    if (count($peaks) > 3) { arsort($season); $peaks = array_slice(array_keys($season), 0, 3); sort($peaks); $season = array_map(fn($m) => $f[$m], DB_BT_SEASON); }
    $out[$sci] = [
      'm'     => array_map(fn($v) => round($v, 2), $season),
      'first' => min($present),
      'last'  => max($present),
      'peaks' => array_values($peaks),
      'peak'  => round($peak, 1),
      'n'     => array_sum($by_month),
    ];
  }
  return $out;
}

/** "Oct–Mar" from season month indexes. */
function db_bt_month_range(array $idx) {
  $M = ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'];
  if (!$idx) return '';
  return $M[min($idx)] . (max($idx) > min($idx) ? '–' . $M[max($idx)] : '');
}

/**
 * The places most winter visitors are recorded, with their best months
 * and what to look for. Null while answers are missing.
 */
function db_bt_hotspots($run, $last_year, array $migrants, $tax) {
  $years = ($last_year - 4) . ',' . $last_year;
  // The 150 most-recorded migrants keep the request a reasonable length.
  uasort($migrants, fn($a, $b) => $b['n'] <=> $a['n']);
  $names = array_map(fn($sci) => $tax[$sci]['sci'], array_slice(array_keys($migrants), 0, 150));
  $base = ['stateProvince' => array_values(DB_BT_STATES), 'year' => $years, 'month' => ['9,12', '1,4'], 'verbatimScientificName' => $names];

  $a = db_bt_get(db_bt_url($base + ['facet' => 'locality', 'facetLimit' => 30]), $run);
  if ($a === null) return null;
  $places = array_keys(db_bt_facet($a, 'locality'));

  $spots = [];
  foreach ($places as $place) {
    $b = db_bt_get(db_bt_url(['locality' => $place, 'limit' => 1, 'facet' => ['verbatimScientificName', 'month'], 'facetLimit' => 300] + $base), $run);
    if ($b === null) { $missing = true; continue; }
    $species = db_bt_facet($b, 'verbatimScientificName');
    $months  = db_bt_facet($b, 'month');
    $rec     = $b['results'][0] ?? [];
    if (!$species || empty($rec['decimalLatitude'])) continue;

    // Best months: those with at least 40% of the busiest month's records.
    $season_counts = array_map(fn($m) => $months[(string) $m] ?? 0, DB_BT_SEASON);
    $best = array_keys(array_filter($season_counts, fn($v) => $v >= 0.4 * max($season_counts)));
    // What to see: the migrants this place is especially good for, by how
    // much more of its records they make up than they do across both
    // states (so not Barn Swallow everywhere), among those seen often.
    $here_total = array_sum($species);
    $all_total  = array_sum(array_map(fn($m) => $m['n'], $migrants));
    $hab_n = [];
    $lift = [];
    foreach ($species as $sci => $n) {
      $key = strtolower($sci);
      $t = $tax[$key] ?? null;
      if (!$t || !isset($migrants[$key])) continue;
      $h = db_bt_habitat($t);
      $hab_n[$h] = ($hab_n[$h] ?? 0) + $n;
      if ($n >= 15) $lift[$t['name']] = ($n / $here_total) / ($migrants[$key]['n'] / $all_total);
    }
    arsort($lift);
    $top = array_slice(array_keys($lift), 0, 3);
    arsort($hab_n);
    $spots[] = [
      'name'  => str_replace('--', ' – ', $place), // eBird writes "Rishi Valley--School Campus"
      'area'  => trim(($rec['gadm']['level2']['name'] ?? '') . ', ' . ($rec['stateProvince'] ?? ''), ', '),
      'state' => array_search($rec['stateProvince'] ?? '', DB_BT_STATES, true) ?: '',
      'hab'   => array_key_first($hab_n) ?: 'wet',
      'best'  => db_bt_month_range($best),
      'top'   => implode(', ', $top),
      'lat'   => round((float) $rec['decimalLatitude'], 4),
      'lng'   => round((float) $rec['decimalLongitude'], 4),
      'score' => count($species),
    ];
  }
  if (!empty($missing)) return null;

  // Most migrant species first, at least three from each state.
  usort($spots, fn($a, $b) => $b['score'] <=> $a['score']);
  $chosen = [];
  foreach (array_keys(DB_BT_STATES) as $k) {
    foreach (array_filter($spots, fn($s) => $s['state'] === $k) as $s) {
      if (count(array_filter($chosen, fn($c) => $c['state'] === $k)) >= 3) break;
      $chosen[$s['name']] = $s;
    }
  }
  foreach ($spots as $s) { if (count($chosen) >= 9) break; $chosen[$s['name']] = $s; }
  $chosen = array_values($chosen);
  usort($chosen, fn($a, $b) => $b['score'] <=> $a['score']);
  return array_slice($chosen, 0, 9);
}

/**
 * When each habitat group's season starts, peaks and ends, from the last
 * five winters' daily records, placed on the coming season's calendar.
 * Null while answers are missing.
 */
function db_bt_waves($run, $last_year, array $migrants, $tax, $season_year) {
  $years = ($last_year - 4) . ',' . $last_year;
  $base = ['stateProvince' => array_values(DB_BT_STATES), 'year' => $years, 'month' => ['9,12', '1,4'], 'facet' => 'eventDate', 'facetLimit' => 2000];
  $all = db_bt_get(db_bt_url($base), $run);
  if ($all === null) return null;
  $all_by_day = db_bt_facet($all, 'eventDate');

  $groups = [];
  foreach ($migrants as $sci => $m) $groups[db_bt_habitat($tax[$sci])][] = $sci;
  $labels = ['wet' => 'Wetland migrants', 'grass' => 'Grassland & farmland migrants', 'scrub' => 'Scrub migrants', 'wood' => 'Woodland migrants'];

  $waves = [];
  foreach ($labels as $h => $label) {
    if (count($groups[$h] ?? []) < 3) continue;
    usort($groups[$h], fn($a, $b) => $migrants[$b]['n'] <=> $migrants[$a]['n']);
    $names = array_map(fn($sci) => $tax[$sci]['sci'], array_slice($groups[$h], 0, 60));
    $a = db_bt_get(db_bt_url($base + ['verbatimScientificName' => $names]), $run);
    if ($a === null) { $missing = true; continue; }

    // Share of each day's records, by day of the season (Sep 1 = 0).
    $num = array_fill(0, 243, 0);
    $den = array_fill(0, 243, 0);
    foreach (db_bt_facet($a, 'eventDate') as $date => $n) {
      $day = db_bt_season_day($date);
      if ($day !== null && isset($all_by_day[$date])) { $num[$day] += $n; $den[$day] += $all_by_day[$date]; }
    }
    // Smoothed over a fortnight either side: single big days don't win.
    $smooth = [];
    for ($d = 0; $d < 243; $d++) {
      $sn = 0; $sd = 0;
      for ($k = max(0, $d - 14); $k <= min(242, $d + 14); $k++) { $sn += $num[$k]; $sd += $den[$k]; }
      $smooth[$d] = $sd ? $sn / $sd : 0;
    }
    $peak_day = array_search(max($smooth), $smooth, true);
    $start = $peak_day; while ($start > 0 && $smooth[$start - 1] >= 0.6 * $smooth[$peak_day]) $start--;
    $end = $peak_day;   while ($end < 242 && $smooth[$end + 1] >= 0.6 * $smooth[$peak_day]) $end++;
    $sep1 = new DateTimeImmutable($season_year . '-09-01');
    $waves[] = [
      'label' => $label,
      'hab'   => $h,
      'start' => $sep1->modify("+$start days")->format('Y-m-d'),
      'peak'  => $sep1->modify("+$peak_day days")->format('Y-m-d'),
      'end'   => $sep1->modify("+$end days")->format('Y-m-d'),
      // Days from 1 September, so the page can place them on whichever
      // winter is current, whatever year the figures were worked out in.
      'days'  => [$start, $peak_day, $end],
    ];
  }
  if (!empty($missing)) return null;
  usort($waves, fn($a, $b) => strcmp($a['peak'], $b['peak']));
  return $waves;
}

/** Days since 1 September of that season, or null outside Sep–Apr. */
function db_bt_season_day($date) {
  $t = strtotime(substr($date, 0, 10) . ' 00:00:00 UTC');
  if (!$t) return null;
  $m = (int) gmdate('n', $t);
  if ($m >= 5 && $m <= 8) return null;
  $y = (int) gmdate('Y', $t) - ($m <= 4 ? 1 : 0);
  $day = (int) floor(($t - strtotime($y . '-09-01 00:00:00 UTC')) / DAY_IN_SECONDS);
  return $day >= 0 && $day < 243 ? $day : null;
}

/* -----------------------------------------------------------------------
 * The whole calculation
 * -------------------------------------------------------------------- */

/**
 * Both pages' data from this run's saved answers. Returns ['done' => bool,
 * 'trends' => …, 'migration' => …]; when not done, $GLOBALS['db_bt_wanted']
 * holds the answers still to fetch.
 */
function db_bt_compute($run, $now = null) {
  $GLOBALS['db_bt_wanted'] = [];
  $now = $now ?: time(); // a real timestamp: wp_date() adds the site's time zone itself
  $now_year = (int) wp_date('Y', $now);

  $tax = db_bt_taxonomy($run);
  if ($tax === null) return ['done' => false];

  // A state's trends, once worked out, are kept for the rest of the run:
  // the bootstrap is the slow part, and later slices only need the result.
  $states = [];
  foreach (array_keys(DB_BT_STATES) as $k) {
    $memo = db_bt_dir('cache-' . $run) . '/computed-' . $k . '.json';
    if (is_readable($memo)) {
      $states[$k] = json_decode((string) file_get_contents($memo), true);
      continue;
    }
    $states[$k] = db_bt_state_trends($k, $run, $now_year);
    if ($states[$k] !== null) file_put_contents($memo, wp_json_encode($states[$k]));
  }
  if (in_array(null, $states, true)) return ['done' => false];

  // Bird Trends: one row per species, with each state's figures.
  $species = [];
  foreach ($states as $k => $st) {
    if (!empty($st['error'])) continue;
    foreach ($st['species'] as $sci => $row) {
      if (!isset($tax[$sci])) continue;
      $t = $tax[$sci];
      $species[$sci] = ($species[$sci] ?? ['code' => $t['code'], 'name' => $t['name'], 'sci' => $t['sci'],
        'wet' => db_bt_is_wetland($t) ? 1 : 0, 'tax' => $t['order']]) + [$k => $row];
      $species[$sci][$k] = $row;
    }
  }
  uasort($species, fn($a, $b) => $a['tax'] <=> $b['tax']);
  $trends = [
    'generated' => wp_date('c', $now),
    'states'    => array_map(fn($st) => empty($st['error'])
      ? ['name' => $st['name'], 'years' => $st['years'], 'records' => $st['records'], 'core' => $st['core'], 'drift' => $st['drift']]
      : ['error' => $st['error']], $states),
    'species'   => array_values($species),
  ];

  // Winter Migration: the latest year both states have complete.
  $lasts = array_filter(array_map(fn($st) => empty($st['error']) ? end($st['years']) : null, $states));
  $last_year = $lasts ? min($lasts) : $now_year - 1;
  $migrants = db_bt_migrants($run, $last_year, $tax);
  if ($migrants === null) return ['done' => false];

  // The coming season: from May, the winter ahead; before that, this one.
  $month = (int) wp_date('n', $now);
  $season_year = $month >= 5 ? $now_year : $now_year - 1;

  $spots = db_bt_hotspots($run, $last_year, $migrants, $tax);
  $waves = db_bt_waves($run, $last_year, $migrants, $tax, $season_year);
  if ($spots === null || $waves === null) return ['done' => false];

  $list = [];
  foreach ($migrants as $sci => $m) {
    $t = $tax[$sci];
    $trend = null;
    foreach (['TS', 'AP'] as $k) {
      $row = $species[$sci][$k] ?? null;
      if ($row && isset($row['pct'])) { $trend = $row['pct']; break; }
    }
    $list[] = [$t['code'], $t['name'], $t['sci'], db_bt_habitat($t), $m['first'], $m['last'], $m['peaks'], $m['peak'], $trend, $m['m']];
  }
  usort($list, fn($a, $b) => $b[7] <=> $a[7]);

  $migration = [
    'generated' => wp_date('c', $now),
    'season'    => $season_year . '–' . substr((string) ($season_year + 1), -2),
    'years'     => ($last_year - 4) . '–' . $last_year,
    'species'   => $list,
    'spots'     => array_map(fn($s) => [$s['name'], $s['area'], $s['hab'], $s['best'], $s['top'], $s['lat'], $s['lng']], $spots),
    'waves'     => array_map(fn($w) => [$w['label'], $w['hab'], $w['start'], $w['peak'], $w['end'], $w['days']], $waves),
  ];

  return ['done' => true, 'trends' => $trends, 'migration' => $migration];
}
