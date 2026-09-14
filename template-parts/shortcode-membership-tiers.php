<?php
/**
 * [db_membership_tiers] — membership application options + benefits list,
 * from the Membership page's fields. (Shortcode tag kept for backward
 * compatibility; the site has no pricing tiers — membership is a single
 * flat application, online or by downloadable form.)
 */

$page_id = get_page_by_path('membership')?->ID;
if (!$page_id) return;

$apply_online_url = get_field('apply_online_url', $page_id);
$apply_pdf_url    = get_field('apply_pdf_url', $page_id);
$benefits         = get_field('benefits', $page_id);
?>
<div class="apply-options">
  <div class="apply-option">
    <span class="eyebrow" style="color:var(--blue)">Option One</span>
    <h3>Apply online</h3>
    <p>Fill in the membership form in your browser. Takes about two minutes.</p>
    <?php if ($apply_online_url): ?>
      <a href="<?php echo esc_url($apply_online_url); ?>" class="btn btn-primary" target="_blank" rel="noopener">Apply for membership online</a>
    <?php endif; ?>
  </div>
  <div class="apply-option">
    <span class="eyebrow" style="color:var(--blue)">Option Two</span>
    <h3>Download the form</h3>
    <p>Download the membership form, fill it up, scan it and email it to us at the email ID given within.</p>
    <?php if ($apply_pdf_url): ?>
      <a href="<?php echo esc_url($apply_pdf_url); ?>" class="btn btn-outline-dark" target="_blank" rel="noopener">Download membership form (PDF)</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($benefits)): ?>
<div class="membership-benefits">
  <h3 class="membership-benefits-heading">Membership Benefits</h3>
  <ul class="benefit-list">
    <?php foreach ($benefits as $b): ?>
      <li><?php echo esc_html($b['benefit_text']); ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
