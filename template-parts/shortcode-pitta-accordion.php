<?php
/**
 * [db_pitta_accordion] — the PITTA archive on the Archives page.
 *
 * Issues are shown as their front covers: a row of year pills, then one
 * year at a time as a grid of twelve month slots (a cover where an issue
 * exists, a plate where it doesn't — the plate itself says whether the
 * issue is still to come or never appeared), with Special issues
 * following the twelve as wide cards. Covers come from
 * assets/pitta-covers/{catalog_key}.jpg (tools/pitta-index/build_covers.py).
 *
 * Every year is rendered here and hidden with [hidden], so the archive is
 * in the HTML for search engines and works without JavaScript; the covers
 * are lazy, so a hidden year never downloads its images.
 * assets/js/pitta-search.js switches years and fills #pitta-results from
 * /wp-json/db/v1/pitta-search.
 */

$issues = get_posts(['post_type' => 'db_pitta', 'posts_per_page' => -1, 'post_status' => 'publish']);
if (!$issues) {
  echo '<p class="db-empty">' . esc_html__('No issues added yet.', 'deccan-birders') . '</p>';
  return;
}

$months = db_pitta_months(); // 1-indexed

// Group by year, splitting the regular monthly run from the Specials: the
// twelve months keep their slots however many Specials a year happens to have.
$by_year = [];
foreach ($issues as $issue) {
  $year  = (int) get_field('year', $issue->ID);
  $month = (int) get_field('month', $issue->ID);
  if (!$year || $month < 1 || $month > 12) continue;
  $special = db_pitta_special_name(get_field('edition', $issue->ID));
  if ($special !== '') {
    $by_year[$year]['specials'][] = ['post' => $issue, 'month' => $month, 'name' => $special];
  } else {
    $by_year[$year]['months'][$month][] = $issue;
  }
}
krsort($by_year);

$total      = count($issues);
$first_year = min(array_keys($by_year));

/** One issue's link, cover and title. */
$issue_data = function($issue) {
  $key = get_post_meta($issue->ID, 'catalog_key', true);
  return [
    'url'     => get_field('archive_url', $issue->ID),
    'cover'   => db_pitta_cover_url($key),
    'title'   => $issue->post_title,
    'is_part' => (bool) get_field('is_part', $issue->ID),
    'part'    => get_field('part_number', $issue->ID),
    'drive'   => get_field('url_type', $issue->ID) === 'google_drive',
  ];
};
?>
<div class="pitta-head">
  <div>
    <h2 class="pitta-section-title">PITTA Archives</h2>
    <p class="intro">
      <?php printf(
        /* translators: 1: number of issues, 2: first year in the archive */
        esc_html__('%1$d editions since %2$d — the full text of every page is searchable.', 'deccan-birders'),
        $total,
        $first_year
      ); ?>
    </p>
  </div>
  <label class="search" id="pitta-search-box">
    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="7" cy="7" r="5"/><path d="m11 11 4 4"/></svg>
    <input id="pitta-search" type="search" placeholder="<?php esc_attr_e('Search issues — a bird, a place, a member', 'deccan-birders'); ?>"
           aria-label="<?php esc_attr_e('Search the text of every PITTA issue', 'deccan-birders'); ?>" autocomplete="off">
    <button class="clear" type="button" id="pitta-search-clear"><?php esc_html_e('Clear ×', 'deccan-birders'); ?></button>
  </label>
</div>

<section class="results" id="pitta-results" aria-live="polite"></section>

<div class="years" id="pitta-years">
  <?php foreach (array_keys($by_year) as $i => $year):
    $count = count($by_year[$year]['specials'] ?? []);
    foreach ($by_year[$year]['months'] ?? [] as $in_month) $count += count($in_month);
  ?>
    <button type="button" class="year-pill" data-year="<?php echo esc_attr($year); ?>" aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>">
      <?php echo esc_html($year); ?><span class="n"><?php echo esc_html($count); ?></span>
    </button>
  <?php endforeach; ?>
</div>

<?php foreach (array_keys($by_year) as $i => $year):
  $year_months   = $by_year[$year]['months'] ?? [];
  $year_specials = $by_year[$year]['specials'] ?? [];
  $regular_count = array_sum(array_map('count', $year_months));
?>
<div class="pitta-year-panel" data-year="<?php echo esc_attr($year); ?>" <?php if ($i !== 0) echo 'hidden'; ?>>
  <div class="year-title">
    <strong><?php echo esc_html($year); ?></strong>
    <span>
      <?php
      printf(esc_html(_n('%d regular issue', '%d regular issues', $regular_count, 'deccan-birders')), $regular_count);
      if ($year_specials) printf(esc_html(' · %d special'), count($year_specials));
      ?>
    </span>
  </div>

  <div class="grid">
    <?php for ($m = 1; $m <= 12; $m++):
      $in_month = $year_months[$m] ?? [];
      if (!$in_month):
        // An issue lands about 45 days after its month ends, so a recent
        // month is still to come rather than missing — two different
        // things, each with its own plate.
        $awaited = db_pitta_is_awaited($year, $m);
        $plate   = $awaited ? 'pitta-coming-soon' : 'pitta-not-published';
      ?>
        <div class="issue gap<?php echo $awaited ? ' gap--soon' : ''; ?>">
          <div class="frame">
            <img class="cover" src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/' . $plate . '.jpg'); ?>"
                 alt="<?php echo esc_attr(sprintf(
                   /* translators: 1: month, 2: year */
                   $awaited ? __('%1$s %2$s — coming soon', 'deccan-birders') : __('%1$s %2$s — not published', 'deccan-birders'),
                   $months[$m], $year)); ?>"
                 loading="lazy" decoding="async" width="600" height="803">
          </div>
          <div class="cap"><?php echo esc_html($months[$m]); ?></div>
        </div>
      <?php else:
        foreach ($in_month as $issue):
          $d = $issue_data($issue);
          $caption = $months[$m] . ($d['is_part'] ? ' (Pt ' . $d['part'] . ')' : '');
      ?>
        <a class="issue" href="<?php echo esc_url($d['url']); ?>" target="_blank" rel="noopener"
           data-search="<?php echo esc_attr(strtolower($year . ' ' . $months[$m] . ' ' . $d['title'])); ?>"
           title="<?php echo esc_attr($d['title']); ?>">
          <div class="frame">
            <?php if ($d['cover']): ?>
              <img class="cover" src="<?php echo esc_url($d['cover']); ?>" alt="" loading="lazy" decoding="async" width="300" height="400">
            <?php else: ?>
              <span class="cover cover--none" aria-hidden="true"><?php echo esc_html($months[$m]); ?></span>
            <?php endif; ?>
          </div>
          <div class="cap"><?php echo esc_html($caption); ?></div>
        </a>
      <?php endforeach; endif; ?>
    <?php endfor; ?>

    <?php foreach ($year_specials as $special):
      $d = $issue_data($special['post']);
    ?>
      <a class="special" href="<?php echo esc_url($d['url']); ?>" target="_blank" rel="noopener"
         data-search="<?php echo esc_attr(strtolower($year . ' ' . $months[$special['month']] . ' ' . $special['name'] . ' ' . $d['title'])); ?>"
         title="<?php echo esc_attr($d['title']); ?>">
        <div class="cover">
          <?php if ($d['cover']): ?>
            <img src="<?php echo esc_url($d['cover']); ?>" alt="" loading="lazy" decoding="async">
          <?php endif; ?>
          <div class="shade" aria-hidden="true"></div>
          <span class="tag"><?php esc_html_e('SPECIAL ISSUE', 'deccan-birders'); ?></span>
          <div class="txt">
            <b><?php echo esc_html($special['name']); ?></b>
            <small><?php echo esc_html($months[$special['month']] . ' ' . $year); ?> · <?php echo $d['drive'] ? esc_html__('Open in Drive ↗', 'deccan-birders') : esc_html__('Read ↗', 'deccan-birders'); ?></small>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="foot" id="pitta-foot">
  <button type="button" class="btn btn-ghost" data-step="-1">‹ <?php esc_html_e('Newer', 'deccan-birders'); ?></button>
  <span id="pitta-foot-status"><?php printf(
    esc_html__('%1$d years · %2$d issues', 'deccan-birders'),
    count($by_year),
    $total
  ); ?></span>
  <button type="button" class="btn btn-dark" data-step="1"><?php esc_html_e('Older', 'deccan-birders'); ?> ›</button>
</div>
