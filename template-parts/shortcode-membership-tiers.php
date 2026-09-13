<?php
/**
 * [db_membership_tiers] — pricing cards from the Membership page's "tiers"
 * repeater, linking out to the Google Form set in Site Settings.
 */

$page_id = get_page_by_path('membership')?->ID;
$tiers   = $page_id ? get_field('tiers', $page_id) : [];
if (!$tiers) return;
?>
<div class="tiers-grid">
  <?php foreach ($tiers as $tier):
    $popular = !empty($tier['tier_popular']);
  ?>
  <div class="tier-card <?php echo $popular ? 'tier-card--popular' : ''; ?>">
    <?php if ($popular): ?>
      <span class="tier-popular-badge">Most popular</span>
    <?php endif; ?>
    <h3 class="tier-name"><?php echo esc_html($tier['tier_name']); ?></h3>
    <div class="tier-price"><?php echo esc_html($tier['tier_price']); ?></div>
    <div class="tier-period"><?php echo esc_html($tier['tier_period']); ?></div>
    <ul class="tier-features">
      <?php if (!empty($tier['tier_features'])): ?>
        <?php foreach ($tier['tier_features'] as $f): ?>
          <li><?php echo esc_html($f['feature_text']); ?></li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
    <?php $form_url = get_field('db_membership_form_url', 'option'); ?>
    <a href="<?php echo esc_url($form_url ?: '#'); ?>"
       class="btn <?php echo $popular ? 'btn-primary' : 'btn-outline-dark'; ?>"
       target="_blank" rel="noopener">Apply →</a>
  </div>
  <?php endforeach; ?>
</div>
