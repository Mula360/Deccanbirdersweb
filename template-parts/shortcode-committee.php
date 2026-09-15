<?php
/**
 * [db_committee_grid] — Executive Committee member cards, from the
 * About page's "committee_members" repeater, ordered by member_display_order.
 *
 * Design: a grid of bordered cards, each with a 230px portrait area at the
 * top — the photograph, or a tinted block carrying the member's initials
 * when there isn't one yet — then role, name and email beneath.
 */

$page_id = get_page_by_path('about')?->ID;
$members = $page_id ? get_field('committee_members', $page_id) : [];
if (!$members) return;

usort($members, fn($a, $b) => ($a['member_display_order'] ?? 99) - ($b['member_display_order'] ?? 99));
?>
<div class="committee-grid">
  <?php foreach ($members as $m):
    // ACF hands back either an image array or a bare attachment ID
    // depending on how the row was written.
    $photo     = $m['member_photo'] ?? null;
    $photo_url = is_array($photo) ? ($photo['url'] ?? '') : ($photo ? wp_get_attachment_image_url($photo, 'medium_large') : '');

    $parts    = preg_split('/\s+/', trim($m['member_name']));
    $initials = '';
    foreach ($parts as $w) {
      if ($w !== '' && ctype_alpha($w[0])) $initials .= strtoupper($w[0]);
    }
    $initials = substr($initials, 0, 2);
  ?>
  <div class="committee-card">
    <?php if ($photo_url): ?>
      <img class="member-photo" src="<?php echo esc_url($photo_url); ?>"
           alt="<?php echo esc_attr($m['member_name']); ?>" loading="lazy">
    <?php else: ?>
      <div class="member-nophoto" aria-hidden="true">
        <span class="member-initials"><?php echo esc_html($initials); ?></span>
        <span class="member-photo-pending">Photograph to follow</span>
      </div>
    <?php endif; ?>
    <div class="member-body">
      <span class="member-role"><?php echo esc_html($m['member_role']); ?></span>
      <span class="member-name"><?php echo esc_html($m['member_name']); ?></span>
      <?php if (!empty($m['member_email'])): ?>
        <a href="mailto:<?php echo esc_attr($m['member_email']); ?>" class="member-email">
          <?php echo esc_html($m['member_email']); ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
