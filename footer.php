<footer class="site-footer" role="contentinfo">
  <div class="footer-inner">

    <div class="footer-col footer-about">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-logo" aria-label="Deccan Birders home">
        <?php
        // Same artwork as the header. The design sets it on a white rounded
        // chip because the logo has dark elements that would disappear
        // against the dark footer.
        $f_logo_id  = get_theme_mod('custom_logo');
        $f_logo_src = $f_logo_id ? wp_get_attachment_image_src($f_logo_id, 'full') : null;
        if ($f_logo_src):
        ?>
          <img class="footer-logo-img" src="<?php echo esc_url($f_logo_src[0]); ?>" alt="Deccan Birders"
               width="<?php echo (int) $f_logo_src[1]; ?>" height="<?php echo (int) $f_logo_src[2]; ?>">
        <?php else: ?>
          <span class="logo-mark logo-mark--sm" aria-hidden="true">DB</span>
          <span class="logo-name">Deccan Birders</span>
        <?php endif; ?>
      </a>
      <p class="footer-tagline"><?php echo esc_html(get_field('footer_tagline','option') ?: 'Since 1980, documenting the birds of the Deccan Plateau through field trips, citizen science, and the monthly PITTA bulletin.'); ?></p>
      <div class="footer-social">
        <?php if ($eb = get_field('social_ebird','option')): ?>
          <a href="<?php echo esc_url($eb); ?>" target="_blank" rel="noopener" class="social-link">eBird</a>
        <?php endif; ?>
        <?php if ($fb = get_field('social_facebook','option')): ?>
          <a href="<?php echo esc_url($fb); ?>" target="_blank" rel="noopener" class="social-link">Facebook</a>
        <?php endif; ?>
        <?php if ($wa = get_field('contact_whatsapp','option')): ?>
          <a href="https://wa.me/91<?php echo esc_attr(preg_replace('/\D/','',$wa)); ?>" target="_blank" rel="noopener" class="social-link">WhatsApp</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="footer-col footer-links">
      <h3 class="footer-heading">Quick links</h3>
      <?php wp_nav_menu(['theme_location'=>'footer-nav','container'=>false,'menu_class'=>'footer-nav-list','fallback_cb'=>false]); ?>
    </div>

    <div class="footer-col footer-contact">
      <h3 class="footer-heading">Contact</h3>
      <?php if ($addr = get_field('contact_address','option')): ?>
        <address class="footer-address"><?php echo nl2br(esc_html($addr)); ?></address>
      <?php endif; ?>
      <a href="mailto:info@deccanbirders.org" class="footer-email">info@deccanbirders.org</a>
      <?php if ($ph = get_field('contact_phone','option')): ?>
        <p class="footer-phone"><?php echo esc_html($ph); ?></p>
      <?php endif; ?>
    </div>

  </div>
  <div class="footer-bar">
    <span>© <?php echo date('Y'); ?> Deccan Birders · Founded 1980 · Hyderabad, India</span>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
