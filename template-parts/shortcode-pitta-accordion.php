<?php
/**
 * [db_pitta_accordion] — PITTA newsletter archive (db_pitta CPT), grouped
 * by year with a 12-month grid per year (Jan–Dec, "Read" or "—"), plus a
 * live search box.
 */

$issues = get_posts(['post_type' => 'db_pitta', 'posts_per_page' => -1, 'post_status' => 'publish']);
if (!$issues) {
  echo '<p class="db-empty">' . esc_html__('No issues added yet.', 'deccan-birders') . '</p>';
  return;
}

$months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

// Group by year
$by_year = [];
foreach ($issues as $issue) {
  $y = get_field('year', $issue->ID) ?: 'Unknown';
  $by_year[$y][] = $issue;
}
krsort($by_year);
$first = true;
?>
<div class="pitta-search-wrap">
  <input type="search" id="pitta-search" placeholder="Search by year or title..." aria-label="Search PITTA archive">
</div>
<div class="pitta-accordion" id="pitta-accordion">
<?php foreach ($by_year as $year => $issues_in_year):
  // Index this year's issues by month (1-12) for grid lookup.
  $by_month = [];
  foreach ($issues_in_year as $issue) {
    $m = (int) get_field('month', $issue->ID);
    if ($m >= 1 && $m <= 12) $by_month[$m] = $issue;
  }
  $count = count($issues_in_year);
?>
  <details class="pitta-year" data-search="<?php echo esc_attr(strtolower($year)); ?>" <?php if ($first) echo 'open'; ?>>
    <summary class="pitta-year-heading">
      <?php echo esc_html($year); ?>
      <span class="pitta-count"><?php echo esc_html($count); ?> issue<?php echo $count === 1 ? '' : 's'; ?></span>
    </summary>
    <div class="pitta-month-grid">
      <?php for ($m = 1; $m <= 12; $m++):
        $issue = $by_month[$m] ?? null;
      ?>
        <?php if ($issue):
          $url     = get_field('archive_url', $issue->ID);
          $type    = get_field('url_type', $issue->ID);
          $is_part = get_field('is_part', $issue->ID);
          $part_no = get_field('part_number', $issue->ID);
          $label   = $type === 'google_drive' ? 'Open in Drive' : 'Read';
        ?>
          <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"
             class="pitta-month-cell has-issue"
             data-search="<?php echo esc_attr(strtolower($year . ' ' . $issue->post_title)); ?>"
             title="<?php echo esc_attr($issue->post_title); ?>">
            <span class="pitta-month-label"><?php echo esc_html($months[$m - 1]); ?></span>
            <span class="pitta-month-action"><?php echo esc_html($label); ?><?php if ($is_part): ?> (Pt <?php echo esc_html($part_no); ?>)<?php endif; ?></span>
          </a>
        <?php else: ?>
          <span class="pitta-month-cell">
            <span class="pitta-month-label"><?php echo esc_html($months[$m - 1]); ?></span>
            <span class="pitta-month-action">—</span>
          </span>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  </details>
<?php $first = false; endforeach; ?>
</div>
<script>
document.getElementById('pitta-search').addEventListener('input', function() {
  const q = this.value.toLowerCase();

  document.querySelectorAll('.pitta-year').forEach(yearEl => {
    const cells = yearEl.querySelectorAll('.pitta-month-cell.has-issue');
    let anyMatch = false;

    cells.forEach(cell => {
      const match = !q || cell.dataset.search.includes(q);
      cell.style.display = match ? '' : 'none';
      if (match) anyMatch = true;
    });

    const yearMatch = !q || yearEl.dataset.search.includes(q);
    yearEl.hidden = !(yearMatch || anyMatch);
    if (q && (yearMatch || anyMatch)) yearEl.open = true;
  });
});
</script>
