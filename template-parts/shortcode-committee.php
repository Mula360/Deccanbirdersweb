<?php
/**
 * [db_committee_grid] — Executive Committee member cards, from the
 * About page's "committee_members" repeater, ordered by member_display_order.
 */

$page_id = get_page_by_path('about')?->ID;
$members = $page_id ? get_field('committee_members', $page_id) : [];
if (!$members) return;

usort($members, fn($a, $b) => ($a['member_display_order'] ?? 99) - ($b['member_display_order'] ?? 99));
?>
<div class="committee-grid">
  <?php foreach ($members as $m):
    $has_photo = !empty($m['member_photo']['url']);
    $initials  = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $m['member_name'])));
    $initials  = substr($initials, 0, 2);
  ?>
  <div class="committee-card">
    <div class="member-avatar">
      <?php if ($has_photo): ?>
        <img src="<?php echo esc_url($m['member_photo']['url']); ?>"
             alt="<?php echo esc_attr($m['member_name']); ?>"
             width="80" height="80" loading="lazy">
      <?php else: ?>
        <span class="member-initials" aria-label="<?php echo esc_attr($m['member_name']); ?>">
          <?php echo esc_html($initials); ?>
        </span>
      <?php endif; ?>
    </div>
    <h3 class="member-name"><?php echo esc_html($m['member_name']); ?></h3>
    <p class="member-role"><?php echo esc_html($m['member_role']); ?></p>
    <?php if (!empty($m['member_email'])): ?>
      <a href="mailto:<?php echo esc_attr($m['member_email']); ?>" class="member-email">
        <?php echo esc_html($m['member_email']); ?>
      </a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
