<?php
/**
 * [db_pitta_accordion] — PITTA newsletter archive (db_pitta CPT), grouped
 * by year with a 12-month grid per year (each month showing a "Read" link
 * when an issue exists — plus one per Special edition or part — and an em
 * dash otherwise), plus a search box. assets/js/pitta-search.js filters
 * the grid and lists full-text matches from /wp-json/db/v1/pitta-search
 * in #pitta-results.
 */

$issues = get_posts(['post_type' => 'db_pitta', 'posts_per_page' => -1, 'post_status' => 'publish']);
if (!$issues) {
  echo '<p class="db-empty">' . esc_html__('No issues added yet.', 'deccan-birders') . '</p>';
  return;
}

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// Group by year, then by month — a month can hold more than one issue
// (e.g. a volume published in two parts).
$by_year = [];
foreach ($issues as $issue) {
  $y = get_field('year', $issue->ID) ?: 'Unknown';
  $m = (int) get_field('month', $issue->ID);
  if ($m < 1 || $m > 12) continue;
  $by_year[$y][$m][] = $issue;
}
krsort($by_year);
$first = true;
?>
<div class="pitta-search-wrap">
  <input type="search" id="pitta-search" placeholder="Search every PITTA issue — e.g. Indian Pitta, Talakona, 2019" aria-label="Search the text of every PITTA issue" autocomplete="off">
</div>
<div class="pitta-results" id="pitta-results" aria-live="polite" hidden></div>
<div class="pitta-accordion" id="pitta-accordion">
<?php foreach ($by_year as $year => $by_month):
  $count = array_sum(array_map('count', $by_month));
?>
  <details class="pitta-year" data-search="<?php echo esc_attr(strtolower($year)); ?>" <?php if ($first) echo 'open'; ?>>
    <summary class="pitta-year-heading">
      <span class="pitta-year-number"><?php echo esc_html($year); ?></span>
      <span class="pitta-count"><?php echo esc_html($count); ?> issue<?php echo $count === 1 ? '' : 's'; ?></span>
    </summary>
    <div class="pitta-month-grid">
      <?php for ($m = 1; $m <= 12; $m++):
        $issues_in_month = $by_month[$m] ?? [];
      ?>
        <div class="pitta-month-col">
          <span class="pitta-month-label"><?php echo esc_html($months[$m - 1]); ?></span>
          <?php if ($issues_in_month): ?>
            <div class="pitta-month-links">
              <?php foreach ($issues_in_month as $issue):
                $url     = get_field('archive_url', $issue->ID);
                $type    = get_field('url_type', $issue->ID);
                $is_part = get_field('is_part', $issue->ID);
                $part_no = get_field('part_number', $issue->ID);
                $special = db_pitta_special_name(get_field('edition', $issue->ID));
                $label   = $special !== ''
                  ? 'Special: ' . $special
                  : ($type === 'google_drive' ? 'Open in Drive' : 'Read') . ($is_part ? ' (Pt ' . $part_no . ')' : '');
              ?>
                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"
                   class="pitta-month-link"
                   data-search="<?php echo esc_attr(strtolower($year . ' ' . $months[$m - 1] . ' ' . $issue->post_title)); ?>"
                   title="<?php echo esc_attr($issue->post_title); ?>">
                  <?php echo esc_html($label); ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <span class="pitta-month-empty" data-empty>—</span>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
  </details>
<?php $first = false; endforeach; ?>
</div>
