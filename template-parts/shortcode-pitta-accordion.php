<?php
/**
 * [db_pitta_accordion] — PITTA newsletter archive (db_pitta CPT), grouped
 * by year with a live search box.
 */

$issues = get_posts(['post_type' => 'db_pitta', 'posts_per_page' => -1, 'post_status' => 'publish']);
if (!$issues) {
  echo '<p class="db-empty">' . esc_html__('No issues added yet.', 'deccan-birders') . '</p>';
  return;
}

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
  usort($issues_in_year, fn($a, $b) => get_field('issue_number', $b->ID) - get_field('issue_number', $a->ID));
?>
  <details class="pitta-year" <?php if ($first) echo 'open'; ?>>
    <summary class="pitta-year-heading"><?php echo esc_html($year); ?> <span class="pitta-count"><?php echo count($issues_in_year); ?> issues</span></summary>
    <div class="pitta-issues">
    <?php foreach ($issues_in_year as $issue):
      $vol     = get_field('volume', $issue->ID);
      $num     = get_field('issue_number', $issue->ID);
      $url     = get_field('archive_url', $issue->ID);
      $type    = get_field('url_type', $issue->ID);
      $is_part = get_field('is_part', $issue->ID);
      $part_no = get_field('part_number', $issue->ID);
      $label   = $type === 'google_drive' ? 'Open in Drive' : 'Read on Archive.org';
    ?>
      <div class="pitta-row" data-search="<?php echo esc_attr(strtolower($year . ' ' . $issue->post_title)); ?>">
        <span class="pitta-vol">Vol <?php echo esc_html($vol); ?> No <?php echo esc_html($num); ?></span>
        <span class="pitta-title"><?php echo esc_html($issue->post_title); ?></span>
        <?php if ($is_part): ?>
          <span class="pitta-part-badge">Part <?php echo esc_html($part_no); ?></span>
        <?php endif; ?>
        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="btn-pitta-link"><?php echo esc_html($label); ?> →</a>
      </div>
    <?php endforeach; ?>
    </div>
  </details>
<?php $first = false; endforeach; ?>
</div>
<script>
document.getElementById('pitta-search').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.pitta-row').forEach(row => {
    row.style.display = row.dataset.search.includes(q) ? '' : 'none';
  });
  if (q) document.querySelectorAll('.pitta-year').forEach(d => d.open = true);
});
</script>
